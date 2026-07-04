<?php
// api/process_payment.php — Charge card via Square Web Payments SDK token
ini_set('display_errors', 0);  // never let PHP errors corrupt the JSON response

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/applog.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }

$d         = body();
$source_id = trim($d['source_id'] ?? '');
$order_id  = trim($d['order_id'] ?? '');

if (!$source_id || !$order_id) fail('Missing source_id or order_id');

applog('PAY-START', "order=$order_id src_len=".strlen($source_id));
$pdo = db();

// test_mode is admin-only bypass for regression tests
if (!empty($d['test_mode'])) requireAdmin();

// Load order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) fail('Order not found', 404);
if ($order['status'] !== 'Awaiting Payment') fail('Order is not awaiting payment');

// Recalculate total server-side from stored line items (prevents client-side tampering)
$items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items->execute([$order_id]);
$lineItems = $items->fetchAll();

$subtotal = 0;
$shipping = 0;
foreach ($lineItems as $it) {
    if ($it['product_id'] === '_ship') {
        $shipping = (float)$it['price'];
    } else {
        $subtotal += (float)$it['price'] * (int)$it['quantity'];
    }
}
$tax          = round($subtotal * 0.0975, 2);
$total        = round($subtotal + $shipping + $tax, 2);
$amountCents  = (int)round($total * 100);

// Skip Square in test_mode (regression test bypass)
if (!empty($d['test_mode'])) {
    $pdo->prepare("UPDATE orders SET status='Paid', total=?, tax_amount=?, confirm_sent_at=NOW() WHERE id=?")
        ->execute([$total, $tax, $order_id]);
    ok(['message' => 'Test payment accepted', 'total' => $total, 'order_id' => $order_id]);
}

// Load Square credentials — token from secrets.php, mode/location from DB
$token    = defined('SQUARE_TOKEN') ? SQUARE_TOKEN : '';
$sqMode   = getSetting($pdo, 'square_mode') ?: 'live';
$location = ($sqMode === 'test' && defined('SQUARE_SANDBOX_LOCATION_ID'))
    ? SQUARE_SANDBOX_LOCATION_ID
    : (getSetting($pdo, 'square_location_id') ?: 'LJP687TQBTWTA');
if (!$token) fail('Payment not configured');

$sqBase = ($sqMode === 'test') ? 'https://connect.squareupsandbox.com' : 'https://connect.squareup.com';

// Atomic lock: claim the order before hitting Square — prevents double-charge on concurrent requests
$guard = $pdo->prepare("UPDATE orders SET status='Processing' WHERE id=? AND status='Awaiting Payment'");
$guard->execute([$order_id]);
if ($guard->rowCount() === 0) fail('Order is no longer awaiting payment. Please refresh and try again.');

// Charge the card
$body = [
    'source_id'           => $source_id,
    'idempotency_key'     => $order_id . '-' . substr(md5($source_id), 0, 8),  // per-nonce: dedupes identical retries; allows retry with new card
    'amount_money'        => ['amount' => $amountCents, 'currency' => 'USD'],
    'location_id'         => $location,
    'note'                => $order_id,
    'buyer_email_address' => $order['customer_email'] ?? '',
];

$resp = sq_curl($sqBase . '/v2/payments', 'POST', $body, $token);

if (!$resp || !isset($resp['payment'])) {
    // Roll back status so customer can retry with a different card
    $pdo->prepare("UPDATE orders SET status='Awaiting Payment' WHERE id=? AND status='Processing'")->execute([$order_id]);
    $sqErr  = $resp ? json_encode($resp) : 'sq_curl returned null';
    applog('PAYMENT-FAIL', "order=$order_id mode=$sqMode loc=$location err=$sqErr");
    $errCode = $resp ? ($resp['errors'][0]['code'] ?? '') : '';
    $codeMap = [
        'CARD_DECLINED'                => 'Your card was declined. Please try a different card.',
        'CVV_FAILURE'                  => 'Card security code did not match. Please check and try again.',
        'ADDRESS_VERIFICATION_FAILURE' => 'Billing ZIP code did not match. Please check and try again.',
        'CARD_EXPIRED'                 => 'Your card has expired. Please use a different card.',
        'INSUFFICIENT_FUNDS'           => 'Insufficient funds. Please try a different card.',
        'INVALID_CARD'                 => 'Invalid card number. Please check and try again.',
        'CARD_NOT_SUPPORTED'           => 'This card type is not supported. Please try a different card.',
        'UNAUTHORIZED'                 => 'Payment configuration error. Please contact us.',
        'NOT_FOUND'                    => 'Payment configuration error. Please contact us.',
    ];
    $userMsg = $codeMap[$errCode] ?? ($resp ? ($resp['errors'][0]['detail'] ?? 'Payment failed. Please try again.') : 'Payment failed. Please try again.');
    fail($userMsg);
}

$payment = $resp['payment'];
if ($payment['status'] !== 'COMPLETED') {
    $pdo->prepare("UPDATE orders SET status='Awaiting Payment' WHERE id=? AND status='Processing'")->execute([$order_id]);
    fail('Payment not completed. Status: ' . $payment['status']);
}

$payId = $payment['id'];

// Mark order paid — if this write fails the card was still charged, so log for manual reconciliation
try {
    $pdo->prepare("UPDATE orders SET status='Paid', square_payment_id=?, total=?, tax_amount=?, confirm_sent_at=NOW() WHERE id=?")
        ->execute([$payId, $total, $tax, $order_id]);
} catch (Exception $e) {
    applog('CHARGE-ORPHANED', "order=$order_id sq_payment=$payId err=".$e->getMessage());
    fail('Payment received but order update failed. Please contact us with order reference: '.$order_id);
}

// Increment customer order count
$pdo->prepare("UPDATE customers SET order_count = order_count + 1 WHERE email = ?")
    ->execute([$order['customer_email']]);

// Send confirmation email to customer + admin (shared with the PayPal path)
require_once __DIR__ . '/order_confirm_email.php';
sendOrderConfirmation($pdo, $order, $lineItems, $total, $shipping, $tax, $payId);

ok(['message' => 'Payment successful', 'payment_id' => $payId, 'total' => $total, 'order_id' => $order_id]);
