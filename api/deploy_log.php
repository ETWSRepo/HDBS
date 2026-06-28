<?php
// api/deploy_log.php — Record and retrieve deployment history
require_once __DIR__ . '/config.php';
cors();

$logFile = dirname(__DIR__) . '/deploy.log';
$method  = $_SERVER['REQUEST_METHOD'];
$d       = body();

if ($method === 'POST') {
    $files = $d['files'] ?? [];
    if (!is_array($files)) fail('Invalid files', 400);
    $count = (int)($d['count'] ?? count($files));
    $mode  = $d['mode'] ?? 'single';
    if (!in_array($mode, ['single', 'full'])) $mode = 'single';
    // Cap entry size and log file size to prevent disk fill
    $files = array_slice(array_map('strval', $files), 0, 500);
    if (file_exists($logFile) && filesize($logFile) > 512 * 1024) {
        // Trim to last 200 lines
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        file_put_contents($logFile, implode("\n", array_slice($lines, -200)) . "\n");
    }
    // Auto-increment the minor version once per logical change.
    // Deploys within SESSION_GAP of each other count as one change (matches the
    // Deploy History 5-minute session grouping), so a multi-file task bumps once.
    $bumped = false;
    try {
        $pdo    = db();
        $now    = time();
        $lastTs = (int)(getSetting($pdo, 'last_deploy_ts') ?? 0);
        if ($now - $lastTs > 300) { // 5 minutes — start of a new logical change
            $minor = (int)(getSetting($pdo, 'minor_version') ?? 0) + 1;
            setSetting($pdo, 'minor_version', (string)$minor);
            $bumped = true;
        }
        setSetting($pdo, 'last_deploy_ts', (string)$now);
    } catch (Exception $e) {}
    // Capture the current site version so each deploy records the version it produced
    $version = '';
    try {
        $vs = db()->prepare("SELECT value FROM settings WHERE key_name=? LIMIT 1");
        $vs->execute(['major_version']); $maj = $vs->fetchColumn();
        $vs->execute(['minor_version']); $min = $vs->fetchColumn();
        if ($maj !== false || $min !== false) $version = ($maj !== false ? $maj : '0') . '.' . ($min !== false ? $min : '0');
    } catch (Exception $e) {}
    $entry = json_encode(['ts' => date('c'), 'count' => $count, 'mode' => $mode, 'version' => $version, 'files' => array_values($files)]);
    file_put_contents($logFile, $entry . "\n", FILE_APPEND | LOCK_EX);
    ok(['message' => 'Logged', 'version' => $version, 'bumped' => $bumped]);
}

if ($method === 'GET') {
    requireAdmin();
    $entries = [];
    if (file_exists($logFile)) {
        foreach (file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $r = json_decode($line, true);
            if ($r) $entries[] = $r;
        }
    }
    // Most recent first
    $entries = array_reverse($entries);
    ok(['deploys' => $entries]);
}
