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
    // Auto-bump the minor version on PRODUCTION deploys only. Staging is deliberately left
    // untouched so active-dev staging deploys (incl. watch.ps1 auto-deploy) don't inflate the
    // version. Debounced via version_updated_at (300s) so several deploy calls inside one
    // checkpoint window bump the minor once, not once per call. $__staging comes from config.php.
    $pdo = db();
    if (empty($__staging)) {
        try {
            $lastTs = ($la = getSetting($pdo, 'version_updated_at')) ? strtotime($la) : 0;
            if (time() - $lastTs > 300) {
                $minNow = (int)(getSetting($pdo, 'minor_version') ?? 0);
                setSetting($pdo, 'minor_version', (string)($minNow + 1));
                setSetting($pdo, 'version_updated_at', date('c'));
            }
        } catch (Exception $e) {}
    }
    // Capture the current site version so each deploy records the version it produced
    $version = '';
    try {
        $maj = getSetting($pdo, 'major_version');
        $min = getSetting($pdo, 'minor_version');
        if ($maj !== null || $min !== null) $version = ($maj !== null ? $maj : '0') . '.' . ($min !== null ? $min : '0');
    } catch (Exception $e) {}
    $entry = json_encode(['ts' => date('c'), 'count' => $count, 'mode' => $mode, 'version' => $version, 'files' => array_values($files)]);
    file_put_contents($logFile, $entry . "\n", FILE_APPEND | LOCK_EX);
    ok(['message' => 'Logged', 'version' => $version]);
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
