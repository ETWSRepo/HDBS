<?php
// api/paypal_status.php — Admin-only PayPal configuration check. Reports whether the
// backend credentials are present and actually work, WITHOUT ever exposing their values.
// Lets the admin confirm secrets.php / secrets.staging.php is set up before a live checkout.
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/paypal.php';
cors();
requireAdmin();

$env = pp_env();
list($clientId, $secret) = pp_creds($env);

$clientSet = ($clientId !== '');
$secretSet = ($secret !== '');

// Only try a live token exchange when both parts are present (otherwise it's a guaranteed
// failure that tells us nothing new). token_ok=true proves the credentials are valid.
$tokenOk = false;
if ($clientSet && $secretSet) {
    $tokenOk = (pp_token($env) !== null);
}

$ready = $clientSet && $secretSet && $tokenOk;
$msg = $ready
    ? 'PayPal is fully configured for the ' . $env . ' environment — credentials verified with PayPal.'
    : (!$clientSet || !$secretSet
        ? 'Missing PayPal credentials for the ' . $env . ' environment. Add them to ' . ($env === 'sandbox' ? 'secrets.staging.php' : 'secrets.php') . '.'
        : 'Credentials are present but PayPal rejected them (check for typos or a mismatched sandbox/live key).');

ok([
    'env'               => $env,
    'client_id_set'     => $clientSet,
    'secret_set'        => $secretSet,
    'credentials_valid' => $tokenOk,
    'ready'             => $ready,
    'message'           => $msg,
]);
