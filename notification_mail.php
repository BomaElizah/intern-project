<?php
function getNotificationMailSettings() {
    return [
        'from' => getenv('MAIL_FROM') ?: 'no-reply@wpu.local',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'WPU Maintenance System',
        'reply_to' => getenv('MAIL_REPLY_TO') ?: (getenv('MAIL_FROM') ?: 'no-reply@wpu.local'),
        'transport' => getenv('MAIL_TRANSPORT') ?: 'mail'
    ];
}

function buildNotificationEmailContent($message, $requestId = null) {
    $subject = 'WPU Maintenance Request Notification';
    $requestLabel = $requestId ? "Request #{$requestId}" : 'Maintenance Request';
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{$subject}</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #222;">
  <h2 style="color: #0f4c81;">{$subject}</h2>
  <p>Hello,</p>
  <p>{$safeMessage}</p>
  <p><strong>{$requestLabel}</strong></p>
  <p>This is an automated notification from the WPU Maintenance Request System.</p>
</body>
</html>
HTML;

    return ['subject' => $subject, 'body' => $body];
}
?>
