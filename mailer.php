<?php
// Simple mailer wrapper using PHPMailer when available, falling back to PHP mail().
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config.php';

function sendEmailSMTP($to, $toName, $subject, $body, $isHtml = false) {
    // Prefer PHPMailer via Composer autoload if available
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        $mail = new PHPMailer(true);
        try {
            // Server settings
            if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = SMTP_AUTH;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port = SMTP_PORT;
            }

            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($to, $toName ?: '');
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML($isHtml);

            return $mail->send();
        } catch (Exception $e) {
            error_log('PHPMailer Error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback to mail()
    $contentType = $isHtml ? 'text/html' : 'text/plain';
    $fromHeader = 'From: ' . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : '') . ' <' . (defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : '') . '>' . "\r\n" . 'Content-Type: ' . $contentType . '; charset=UTF-8' . "\r\n";
    return @mail($to, $subject, $body, $fromHeader);
}

?>