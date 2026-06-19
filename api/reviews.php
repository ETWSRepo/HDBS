<?php
// api/reviews.php — Customer reviews: submit, list approved, admin manage

require_once __DIR__ . '/config.php';
cors();

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = db();
$d      = body();

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    product_name VARCHAR(255),
    rating INT DEFAULT 5,
    review_text TEXT NOT NULL,
    status ENUM('pending','approved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// GET — return approved reviews (or all for admin)
if ($method === 'GET') {
    $admin = isset($_GET['admin']) && $_GET['admin'] === '1';
    if ($admin) { requireAdmin();
        $rows = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC")->fetchAll();
    } else {
        $rows = $pdo->query("SELECT * FROM reviews WHERE status='approved' ORDER BY created_at DESC")->fetchAll();
    }
    ok(['reviews' => array_map(function($r) {
        return [
            'id'            => (int)$r['id'],
            'customer_name' => $r['customer_name'],
            'product_name'  => $r['product_name'],
            'rating'        => (int)$r['rating'],
            'review_text'   => $r['review_text'],
            'status'        => $r['status'],
            'created_at'    => $r['created_at'],
        ];
    }, $rows)]);
}

// POST — submit a new review (pending)
if ($method === 'POST') {
    // Per-IP rate limit: 5 review submissions per 15 minutes
    (function() use ($pdo) {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
        $key = md5('review_' . $ip);
        $now = time();
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            key_hash CHAR(32) PRIMARY KEY,
            attempts INT NOT NULL DEFAULT 0,
            last_at  INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $row = $pdo->prepare("SELECT attempts, last_at FROM rate_limits WHERE key_hash = ?");
        $row->execute([$key]);
        $row = $row->fetch() ?: ['attempts' => 0, 'last_at' => 0];
        if ($row['attempts'] >= 5 && ($now - $row['last_at']) < 900) {
            $mins = (int)ceil((900 - ($now - $row['last_at'])) / 60);
            fail("Too many requests. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.', 429);
        }
        if ($row['attempts'] >= 5) {
            $pdo->prepare("INSERT INTO rate_limits (key_hash,attempts,last_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=1,last_at=?")->execute([$key,$now,$now]);
        } else {
            $new = $row['attempts'] + 1;
            $pdo->prepare("INSERT INTO rate_limits (key_hash,attempts,last_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE attempts=?,last_at=?")->execute([$key,$new,$now,$new,$now]);
        }
    })();

    $name   = trim($d['customer_name'] ?? '');
    $prod   = trim($d['product_name']  ?? '');
    $rating = max(1, min(5, (int)($d['rating'] ?? 5)));
    $text   = trim($d['review_text']   ?? '');

    if (!$name || !$text) fail('Name and review text are required');
    if (strlen($text) < 10) fail('Review is too short');

    $stmt = $pdo->prepare("INSERT INTO reviews (customer_name, product_name, rating, review_text) VALUES (?,?,?,?)");
    $stmt->execute([$name, $prod, $rating, $text]);
    ok(['message' => 'Review submitted — thank you!']);
}

// PUT — approve a review (admin)
if ($method === 'PUT') {
    requireAdmin();
    $id     = (int)($d['id'] ?? 0);
    $status = $d['status'] ?? 'approved';
    if (!$id) fail('Missing review id');
    $pdo->prepare("UPDATE reviews SET status=? WHERE id=?")->execute([$status, $id]);
    ok(['message' => 'Review updated']);
}

// DELETE — remove a review (admin)
if ($method === 'DELETE') {
    requireAdmin();
    $id = (int)($d['id'] ?? 0);
    if (!$id) fail('Missing review id');
    $pdo->prepare("DELETE FROM reviews WHERE id=?")->execute([$id]);
    ok(['message' => 'Review deleted']);
}

fail('Method not allowed', 405);
