<?php
// core/mail.php
// Invio email SMTP con PHPMailer se disponibile.
// Se vendor/PHPMailer non è presente, il software non va in fatal error.

require_once __DIR__ . '/settings.php';

$phpMailerBase = __DIR__ . '/../vendor/PHPMailer/src/';
$phpMailerAvailable = is_file($phpMailerBase . 'PHPMailer.php')
    && is_file($phpMailerBase . 'SMTP.php')
    && is_file($phpMailerBase . 'Exception.php');

if ($phpMailerAvailable) {
    require_once $phpMailerBase . 'PHPMailer.php';
    require_once $phpMailerBase . 'SMTP.php';
    require_once $phpMailerBase . 'Exception.php';
}

if (!function_exists('mail_phpmailer_available')) {
    function mail_phpmailer_available(): bool
    {
        return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
    }
}

if (!function_exists('send_email')) {
    function send_email(string $to, string $subject, string $html, string $alt = '', array $attachments = []): bool
    {
        if (!mail_phpmailer_available()) {
            return false;
        }

        $to = trim($to);
        $subject = trim($subject);
        $html = (string)$html;
        $alt = trim($alt);

        if ($to === '' || $subject === '' || $html === '') {
            return false;
        }

        $host = trim((string)setting('smtp_host', ''));
        $port = (int)setting('smtp_port', 587);
        $username = trim((string)setting('smtp_user', setting('smtp_username', '')));
        $password = (string)setting('smtp_pass', setting('smtp_password', ''));
        $encryption = trim((string)setting('smtp_secure', setting('smtp_encryption', 'tls')));
        $fromAddress = trim((string)setting('email_from', setting('mail_from_address', '')));
        $fromName = trim((string)setting('email_from_name', setting('mail_from_name', 'Turnar')));

        if ($host === '' || $fromAddress === '') {
            return false;
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;

            if ($username !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = $password;
            } else {
                $mail->SMTPAuth = false;
            }

            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($fromAddress, $fromName !== '' ? $fromName : 'Turnar');
            $mail->addAddress($to);

            $replyTo = trim((string)setting('email_reply_to', setting('mail_reply_to', '')));
            if ($replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }

            foreach ($attachments as $attachment) {
                $path = (string)($attachment['path'] ?? '');
                $name = (string)($attachment['name'] ?? basename($path));
                if ($path !== '' && is_file($path)) {
                    $mail->addAttachment($path, $name !== '' ? $name : basename($path));
                }
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $alt !== '' ? $alt : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

            return $mail->send();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('send_test_email')) {
    function send_test_email(string $to): bool
    {
        return send_email(
            $to,
            'Test Email Turnar',
            '<h2>Email funzionante!</h2><p>SMTP configurato correttamente.</p>',
            "Email funzionante!\nSMTP configurato correttamente."
        );
    }
}
