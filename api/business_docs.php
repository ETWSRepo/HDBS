<?php
// api/business_docs.php — Upload/list/download/delete business documents
// (sales tax resale certificate, business license). Files are stored outside
// the webroot (alongside secrets.php) and served only through this admin-gated endpoint.

require_once __DIR__ . '/config.php';
cors();
$pdo = db();
requireAdmin();

$DOC_TYPES = ['resale_cert' => 'Sales Tax Resale Certificate', 'business_license' => 'Business License'];
$storeDir  = dirname(dirname(__DIR__)) . '/business_documents/';
if (!is_dir($storeDir)) mkdir($storeDir, 0755, true);

function bizDocsMeta($pdo) {
    $raw = getSetting($pdo, 'biz_documents');
    return $raw ? (json_decode($raw, true) ?: []) : [];
}
function saveBizDocsMeta($pdo, $meta) {
    setSetting($pdo, 'biz_documents', json_encode($meta));
}

$d = body();
$action = $d['action'] ?? '';

if ($action === 'list') {
    ok(['documents' => bizDocsMeta($pdo)]);
}

if ($action === 'download') {
    $type = $d['doc_type'] ?? '';
    if (!isset($DOC_TYPES[$type])) fail('Invalid document type', 400);
    $meta = bizDocsMeta($pdo);
    if (empty($meta[$type]['filename'])) fail('No document on file', 404);
    $path = $storeDir . $meta[$type]['filename'];
    if (!is_file($path)) fail('File not found', 404);
    header('Content-Type: ' . ($meta[$type]['mime'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($meta[$type]['orig_name']) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit();
}

if ($action === 'upload') {
    $type = $d['doc_type'] ?? '';
    if (!isset($DOC_TYPES[$type])) fail('Invalid document type', 400);
    $origName = trim($d['filename'] ?? 'document');
    $data = $d['data'] ?? '';
    if (!preg_match('/^data:([\w\/+.-]+);base64,(.+)$/s', $data, $m)) fail('Invalid file data', 400);
    $bytes = base64_decode($m[2], true);
    if (!$bytes) fail('Could not decode file', 400);
    if (strlen($bytes) > 5 * 1024 * 1024) fail('File too large (max 5MB)', 400);

    // Validate by magic bytes, not the client-reported mime type
    $magic4 = substr($bytes, 0, 4);
    $isPdf  = (substr($bytes, 0, 4) === '%PDF');
    $isJpeg = (substr($magic4, 0, 2) === "\xFF\xD8");
    $isPng  = ($magic4 === "\x89PNG");
    if (!$isPdf && !$isJpeg && !$isPng) fail('Only PDF, JPG, or PNG files are accepted', 400);
    $ext     = $isPdf ? 'pdf' : ($isPng ? 'png' : 'jpg');
    $mimeOut = $isPdf ? 'application/pdf' : ($isPng ? 'image/png' : 'image/jpeg');
    $filename = $type . '_' . time() . '.' . $ext;

    $meta = bizDocsMeta($pdo);
    // Replace any previous file for this doc type
    if (!empty($meta[$type]['filename'])) {
        $old = $storeDir . $meta[$type]['filename'];
        if (is_file($old)) @unlink($old);
    }
    file_put_contents($storeDir . $filename, $bytes);
    $meta[$type] = [
        'filename'    => $filename,
        'orig_name'   => $origName,
        'mime'        => $mimeOut,
        'size'        => strlen($bytes),
        'uploaded_at' => date('Y-m-d H:i:s'),
    ];
    saveBizDocsMeta($pdo, $meta);
    ok(['document' => $meta[$type]]);
}

if ($action === 'delete') {
    $type = $d['doc_type'] ?? '';
    if (!isset($DOC_TYPES[$type])) fail('Invalid document type', 400);
    $meta = bizDocsMeta($pdo);
    if (!empty($meta[$type]['filename'])) {
        $path = $storeDir . $meta[$type]['filename'];
        if (is_file($path)) @unlink($path);
        unset($meta[$type]);
        saveBizDocsMeta($pdo, $meta);
    }
    ok(['message' => 'Deleted']);
}

fail('Unknown action', 400);
