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
    $cleanMessage = trim($message);
    $safeMessage = htmlspecialchars($cleanMessage, ENT_QUOTES, 'UTF-8');
    $requestLabel = $requestId ? "Request #{$requestId}" : 'Maintenance Request';
    $lowerMessage = strtolower($cleanMessage);

    if (strpos($lowerMessage, 'submitted') !== false) {
        $subject = 'WPU Maintenance Request Submitted';
    } elseif (strpos($lowerMessage, 'assigned') !== false) {
        $subject = 'WPU Maintenance Request Assignment Update';
    } elseif (strpos($lowerMessage, 'status') !== false || strpos($lowerMessage, 'updated') !== false) {
        $subject = 'WPU Maintenance Request Status Update';
    } elseif (strpos($lowerMessage, 'evidence') !== false) {
        $subject = 'WPU Maintenance Request Evidence Update';
    } else {
        $subject = 'WPU Maintenance Request Notification';
    }

    $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>{$subject}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #222;">
  <h2 style="color: #0f4c81;">{$subject}</h2>
  <p>Hello,</p>
  <p>{$safeMessage}</p>
  <p><strong>{$requestLabel}</strong></p>
  <p>This is an automated notification from the WPU Maintenance Request System.</p>
  <p>Regards,<br>WPU Maintenance Team</p>
</body>
</html>
HTML;

    return ['subject' => $subject, 'body' => $body];
}
?>
