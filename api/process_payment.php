<?php
// api/process_payment.php — Charge card via Square Web Payments SDK token

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/applog.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }

$d         = body();
$source_id = trim($d['source_id'] ?? '');
$order_id  = trim($d['order_id'] ?? '');

if (!$source_id || !$order_id) fail('Missing source_id or order_id');

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

// Load Square credentials
$token  = getSetting($pdo, 'square_access_token');
$sqMode = getSetting($pdo, 'square_mode') ?: 'live';
if (!$token) fail('Payment not configured');

$sqBase   = ($sqMode === 'test') ? 'https://connect.squaresandbox.com' : 'https://connect.squareup.com';
$location = 'LJP687TQBTWTA';

// Charge the card
$body = [
    'source_id'           => $source_id,
    'idempotency_key'     => $order_id . '-' . time(),
    'amount_money'        => ['amount' => $amountCents, 'currency' => 'USD'],
    'location_id'         => $location,
    'note'                => $order_id,
    'buyer_email_address' => $order['customer_email'] ?? '',
];

$resp = sq_curl($sqBase . '/v2/payments', 'POST', $body, $token);

if (!$resp || !isset($resp['payment'])) {
    $errMsg = $resp['errors'][0]['detail'] ?? 'Payment failed. Please try again.';
    fail($errMsg);
}

$payment = $resp['payment'];
if ($payment['status'] !== 'COMPLETED') {
    fail('Payment not completed. Status: ' . $payment['status']);
}

$payId = $payment['id'];

// Mark order paid
$pdo->prepare("UPDATE orders SET status='Paid', square_payment_id=?, total=?, tax_amount=?, confirm_sent_at=NOW() WHERE id=?")
    ->execute([$payId, $total, $tax, $order_id]);

// Increment customer order count
$pdo->prepare("UPDATE customers SET order_count = order_count + 1 WHERE email = ?")
    ->execute([$order['customer_email']]);

// Send confirmation email to customer + admin
$itemHtml = '';
foreach ($lineItems as $it) {
    if ($it['product_id'] === '_ship') continue;
    $lineTotal = number_format((float)$it['price'] * (int)$it['quantity'], 2);
    $itemHtml .= "<tr><td style='padding:.3rem .5rem'>" . htmlspecialchars($it['product_name']) .
        " &times;" . (int)$it['quantity'] . "</td>" .
        "<td style='padding:.3rem .5rem;text-align:right'>$" . $lineTotal . "</td></tr>\n";
}

$firstName = explode(' ', $order['customer_name'])[0];
$html = "<!DOCTYPE html><html><body>
<div style='font-family:sans-serif;max-width:560px;margin:0 auto'>
<div style='background:#2d2220;padding:20px 28px'>
  <h1 style='color:#d4a017;margin:0;font-size:1.4rem'>Handmade Designs By Suzi</h1>
</div>
<div style='padding:28px'>
  <h2 style='color:#a07810;margin-top:0'>Order Confirmed! 🎉</h2>
  <p>Hi " . htmlspecialchars($firstName) . ", thank you for your order! Your payment has been received and your order is being prepared with care.</p>
  <div style='background:#fffdf0;border:1px solid #e8e0b8;border-radius:10px;padding:16px;margin:16px 0'>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem'>
      " . $itemHtml . "
      <tr><td style='padding:.3rem .5rem;border-top:1px solid #e8e0b8'>Shipping</td><td style='padding:.3rem .5rem;text-align:right;border-top:1px solid #e8e0b8'>" . ($shipping > 0 ? '$' . number_format($shipping, 2) : 'Free') . "</td></tr>
      <tr><td style='padding:.3rem .5rem'>Tax (9.75%)</td><td style='padding:.3rem .5rem;text-align:right'>$" . number_format($tax, 2) . "</td></tr>
      <tr style='font-weight:700'><td style='padding:.5rem .5rem;border-top:2px solid #d4a017'>Total Charged</td><td style='padding:.5rem .5rem;text-align:right;border-top:2px solid #d4a017;color:#a07810'>$" . number_format($total, 2) . "</td></tr>
    </table>
  </div>
  <p><strong>Shipping to:</strong> " . htmlspecialchars($order['shipping_address']) . "</p>
  <p>We'll send you a shipping confirmation with tracking info when your order is on its way!</p>
  <p style='color:#6b6040;font-size:.85rem'>Order #" . $order_id . " &bull; Payment ID: " . $payId . "</p>
</div>
<div style='background:#2d2220;padding:16px 28px;text-align:center'>
  <div style='color:rgba(255,255,255,.6);font-size:.8rem'>
    Handmade Designs By Suzi &bull; Knoxville, TN<br>
    Questions? <a href='mailto:handmadedesignsbysuzi@yahoo.com' style='color:#d4a017'>handmadedesignsbysuzi@yahoo.com</a>
  </div>
</div>
</div></body></html>";

require_once dirname(__DIR__) . '/mailer.php';
$recipients = [$order['customer_email'], 'handmadedesignsbysuzi@yahoo.com'];
sendEmail($recipients, 'Order Confirmed — ' . $order_id, $html, 'handmadedesignsbysuzi@yahoo.com', 'Handmade Designs By Suzi');

ok(['message' => 'Payment successful', 'payment_id' => $payId, 'total' => $total, 'order_id' => $order_id]);
