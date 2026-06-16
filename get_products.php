<?php
header('Content-Type: application/json');
require_once '/home/u541882440/domains/handmadedesignsbysuzi.com/public_html/api/config.php';
require_once '/home/u541882440/domains/handmadedesignsbysuzi.com/public_html/api/applog.php';
pagelog('store', 'Shop page visited' . (isset($_SERVER['HTTP_REFERER']) ? ' from ' . $_SERVER['HTTP_REFERER'] : ''));
$pdo = db();
$prods = $pdo->query("SELECT id, sku, name, description, category, price FROM products ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($prods, JSON_PRETTY_PRINT);
