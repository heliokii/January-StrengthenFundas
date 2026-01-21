<?php
// Minimal mail service for the workspace app (VM).
// Usage:
//   require_once __DIR__ . '/MailService.php';
//   // Generic:
//   MailService::sendMail($to, $subject, $htmlBody, $textBody = null, $opts = []);
//   // Contact form helper:
//   MailService::sendContactMessage($name, $email, $message, $to = null, $subject = 'New contact form');

class MailService
{
    // Universal mail sender (no attachments by design)
    public static function sendMail($to, string $subject, string $htmlBody, ?string $textBody = null, array $opts = [])
    {
        $cfg = self::loadConfig();

        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            @require_once 'libphp-phpmailer/autoload.php';
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                @require_once 'libphp-phpmailer/src/Exception.php';
                @require_once 'libphp-phpmailer/src/SMTP.php';
                @require_once 'libphp-phpmailer/src/PHPMailer.php';
            }
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                @require_once 'PHPMailer/src/Exception.php';
                @require_once 'PHPMailer/src/SMTP.php';
                @require_once 'PHPMailer/src/PHPMailer.php';
            }
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                @require_once 'PHPMailer/Exception.php';
                @require_once 'PHPMailer/SMTP.php';
                @require_once 'PHPMailer/PHPMailer.php';
            }
        }

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return [ 'success' => false, 'error' => 'PHPMailer not available' ];
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $cfg['smtp_host'] ?? '';
            $mail->Port       = (int)($cfg['smtp_port'] ?? 587);
            $secure = $cfg['smtp_secure'] ?? 'tls';
            if ($secure === 'ssl') $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            elseif ($secure === 'tls') $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            else $mail->SMTPSecure = false;
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['smtp_user'] ?? '';
            $mail->Password   = $cfg['smtp_pass'] ?? '';

            $fromEmail = $opts['from_email'] ?? ($cfg['from_email'] ?? 'no-reply@localhost');
            $fromName  = $opts['from_name']  ?? ($cfg['from_name']  ?? 'App');
            $mail->setFrom($fromEmail, $fromName);
            if (!empty($opts['reply_to']) && filter_var($opts['reply_to'], FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($opts['reply_to']);
            } elseif (!empty($cfg['reply_to'])) {
                $mail->addReplyTo($cfg['reply_to']);
            }

            // Recipients
            $toList = [];
            if ($to) {
                if (is_string($to)) $toList = array_map('trim', explode(',', $to));
                elseif (is_array($to)) $toList = $to;
            } elseif (!empty(getenv('MAIL_TO'))) {
                $toList = array_map('trim', explode(',', getenv('MAIL_TO')));
            }
            $added = 0;
            foreach ($toList as $addr) {
                if (filter_var($addr, FILTER_VALIDATE_EMAIL)) { $mail->addAddress($addr); $added++; }
            }
            if ($added === 0) {
                return [ 'success' => false, 'error' => 'No recipients defined (set MAIL_TO or pass $to)' ];
            }

            foreach ((array)($opts['cc'] ?? []) as $cc)  { if (filter_var($cc,  FILTER_VALIDATE_EMAIL)) $mail->addCC($cc); }
            foreach ((array)($opts['bcc'] ?? []) as $bcc){ if (filter_var($bcc, FILTER_VALIDATE_EMAIL)) $mail->addBCC($bcc); }

            // Optional DKIM
            if (!empty($cfg['dkim_domain']) && !empty($cfg['dkim_selector']) && !empty($cfg['dkim_private_key_path'])) {
                $mail->DKIM_domain = $cfg['dkim_domain'];
                $mail->DKIM_selector = $cfg['dkim_selector'];
                $mail->DKIM_private = $cfg['dkim_private_key_path'];
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?? strip_tags($htmlBody);
            $ok = $mail->send();
            return [ 'success' => $ok ];
        } catch (\Throwable $e) {
            return [ 'success' => false, 'error' => 'PHPMailer error: ' . $e->getMessage() ];
        }
    }
    private static function loadConfig(): array
    {
        $configPath = __DIR__ . '/config.php';
        if (!file_exists($configPath)) {
            throw new \RuntimeException('Mail config not found. Copy mail/config.sample.php to mail/config.php and fill in credentials.');
        }
        $cfg = require $configPath;
        if (!is_array($cfg)) {
            throw new \RuntimeException('Invalid mail config format: expected array');
        }
        return $cfg;
    }

    // Send a contact message
    // $to can be: a single email string, a comma-separated list, an array of emails, or null (fallback to MAIL_TO/MAIL_FROM)
    public static function sendContactMessage(string $name, string $email, string $message, $to = null, string $subject = 'New contact form')
    {
        $cfg = self::loadConfig();

        // Try Composer autoload if available (for PHPMailer)
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        // Fallback to system-wide PHPMailer (installed via apt: libphp-phpmailer)
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            // Debian/Ubuntu package layout (libphp-phpmailer)
            @require_once 'libphp-phpmailer/autoload.php';
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                @require_once 'libphp-phpmailer/src/Exception.php';
                @require_once 'libphp-phpmailer/src/SMTP.php';
                @require_once 'libphp-phpmailer/src/PHPMailer.php';
            }
            // Alternative layout (older PHPMailer package names)
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                @require_once 'PHPMailer/src/Exception.php';
                @require_once 'PHPMailer/src/SMTP.php';
                @require_once 'PHPMailer/src/PHPMailer.php';
            }
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                @require_once 'PHPMailer/Exception.php';
                @require_once 'PHPMailer/SMTP.php';
                @require_once 'PHPMailer/PHPMailer.php';
            }
        }

        $transport = $cfg['transport'] ?? 'smtp';
        if ($transport === 'smtp' && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return self::sendViaPHPMailer($cfg, $name, $email, $message, $to, $subject);
        }

        // Fallback: attempt native mail() — works only if MTA is configured on the VM
        return self::sendViaNativeMail($cfg, $name, $email, $message, $to, $subject);
    }

    private static function sendViaPHPMailer(array $cfg, string $name, string $email, string $body, $to, string $subject)
    {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $cfg['smtp_host'] ?? '';
            $mail->Port       = (int)($cfg['smtp_port'] ?? 587);
            $secure = $cfg['smtp_secure'] ?? 'tls';
            if ($secure === 'ssl') $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            elseif ($secure === 'tls') $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            else $mail->SMTPSecure = false;
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['smtp_user'] ?? '';
            $mail->Password   = $cfg['smtp_pass'] ?? '';

            $fromEmail = $cfg['from_email'] ?? 'no-reply@localhost';
            $fromName  = $cfg['from_name']  ?? 'App';
            $mail->setFrom($fromEmail, $fromName);

            // Use Reply-To for the user's email to avoid spoofing From
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($email, $name ?: $email);
            }
            if (!empty($cfg['reply_to'])) {
                $mail->addReplyTo($cfg['reply_to']);
            }

            // Destination: prefer dynamic recipients ($to), fallback to MAIL_TO; no silent FROM fallback
            $toList = [];
            if ($to) {
                if (is_string($to)) {
                    // allow comma-separated list
                    $toList = array_map('trim', explode(',', $to));
                } elseif (is_array($to)) {
                    $toList = $to;
                }
            } elseif (!empty(getenv('MAIL_TO'))) {
                $toList = array_map('trim', explode(',', getenv('MAIL_TO')));
            }
            $added = 0;
            foreach ($toList as $addr) {
                if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($addr);
                    $added++;
                }
            }
            if ($added === 0) {
                return [ 'success' => false, 'error' => 'No recipients defined (set MAIL_TO or pass $to)' ];
            }

            // DKIM (optional)
            if (!empty($cfg['dkim_domain']) && !empty($cfg['dkim_selector']) && !empty($cfg['dkim_private_key_path'])) {
                $mail->DKIM_domain = $cfg['dkim_domain'];
                $mail->DKIM_selector = $cfg['dkim_selector'];
                $mail->DKIM_private = $cfg['dkim_private_key_path'];
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $safeName  = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeBody  = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $mail->Body = "<p><strong>Name:</strong> {$safeName}</p><p><strong>Email:</strong> {$safeEmail}</p><hr>{$safeBody}";
            $mail->AltBody = "Name: {$name}\nEmail: {$email}\n\n{$body}";

            $ok = $mail->send();
            return [ 'success' => $ok ];
        } catch (\Throwable $e) {
            return [ 'success' => false, 'error' => 'PHPMailer error: ' . $e->getMessage() ];
        }
    }

    private static function sendViaNativeMail(array $cfg, string $name, string $email, string $body, $to, string $subject)
    {
        $opts = ['reply_to' => $email];
        $html = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        return self::sendMail($to, $subject, $html, $body, $opts);
    }
}
