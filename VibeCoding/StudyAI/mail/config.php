<?php
// Mail configuration sourced from environment variables.
// No secrets are stored here; the file just maps env -> config array for MailService.

function env_val(string $key, $default = null) {
    $v = getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

// Fallback: if critical vars are missing from process env, try to parse executor/.env
// This helps in web/Apache contexts where .env is not exported.
// Supports simple KEY=VALUE lines; ignores quotes and comments.
function load_dotenv_if_needed(array $keys): void {
    $missing = array_filter($keys, fn($k) => getenv($k) === false || getenv($k) === '');
    if (empty($missing)) return;
    static $loaded = false;
    if ($loaded) return;
    $envPath = realpath(__DIR__ . '/../../.env'); // executor/.env
    if ($envPath && is_readable($envPath)) {
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            if ($line[0] === '#' || trim($line) === '') continue;
            if (!str_contains($line, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            // Strip potential surrounding quotes
            $v = trim($v, "\"' ");
            // Do not override existing env
            if ($k !== '' && (getenv($k) === false || getenv($k) === '')) {
                putenv("{$k}={$v}");
            }
        }
        $loaded = true;
    }
}

load_dotenv_if_needed([
    'MAIL_TRANSPORT','SMTP_HOST','SMTP_PORT','SMTP_SECURE','SMTP_USER','SMTP_PASS',
    'MAIL_FROM','MAIL_FROM_NAME','MAIL_REPLY_TO','MAIL_TO',
    'DKIM_DOMAIN','DKIM_SELECTOR','DKIM_PRIVATE_KEY_PATH'
]);

$transport   = env_val('MAIL_TRANSPORT', 'smtp');
$smtp_host   = env_val('SMTP_HOST');
$smtp_port   = (int) env_val('SMTP_PORT', 587);
$smtp_secure = env_val('SMTP_SECURE', 'tls'); // tls | ssl | null
$smtp_user   = env_val('SMTP_USER');
$smtp_pass   = env_val('SMTP_PASS');

$from_email  = env_val('MAIL_FROM', 'no-reply@localhost');
$from_name   = env_val('MAIL_FROM_NAME', 'App');
$reply_to    = env_val('MAIL_REPLY_TO');

$dkim_domain           = env_val('DKIM_DOMAIN');
$dkim_selector         = env_val('DKIM_SELECTOR');
$dkim_private_key_path = env_val('DKIM_PRIVATE_KEY_PATH');

return [
    'transport'   => $transport,

    // SMTP
    'smtp_host'   => $smtp_host,
    'smtp_port'   => $smtp_port,
    'smtp_secure' => $smtp_secure,
    'smtp_user'   => $smtp_user,
    'smtp_pass'   => $smtp_pass,

    // From / Reply-To
    'from_email'  => $from_email,
    'from_name'   => $from_name,
    'reply_to'    => $reply_to,

    // DKIM (optional)
    'dkim_domain'           => $dkim_domain,
    'dkim_selector'         => $dkim_selector,
    'dkim_private_key_path' => $dkim_private_key_path,
];
