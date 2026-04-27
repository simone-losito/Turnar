<?php
// core/mail.php
// Invio email con PHPMailer (SMTP reale)

require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/settings.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('send_email')) {
    function send_email(string $to, string $subject, string $html, string $alt = ''): bool
    {
        $to = trim($to);
        $subject = trim($subject);
        $html = (string)$html;
        $alt = trim($alt);

        if ($to === '' || $subject === '' || $html === '') {
            return false;
        }

        $host = trim((string)setting('smtp_host', ''));
        $port = (int)setting('smtp_port', 587);
        $username = trim((string)setting('smtp_username', ''));
        $password = (string)setting('smtp_password', '');
        $encryption = trim((string)setting('smtp_encryption', 'tls'));
        $fromAddress = trim((string)setting('mail_from_address', ''));
        $fromName = trim((string)setting('mail_from_name', 'Turnar'));

        if ($host === '' || $fromAddress === '') {
            return false;
        }

        $mail = new PHPMailer(true);

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
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($fromAddress, $fromName !== '' ? $fromName : 'Turnar');
            $mail->addAddress($to);

            $replyTo = trim((string)setting('mail_reply_to', ''));
            if ($replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $alt !== '' ? $alt : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

            return $mail->send();
        } catch (Exception $e) {
            return false;
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