<?php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@buzznation.com');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@buzznation.com');
define('SMTP_FROM_NAME', 'Buzznation Portal');

function sendEmail(string $to, string $subject, string $body, string $toName = ''): bool {
    $fromEmail = SMTP_FROM_EMAIL;
    $fromName  = SMTP_FROM_NAME;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $htmlBody = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;'>" .
                "<div style='max-width:600px;margin:auto;padding:20px;border:1px solid #ddd;border-radius:8px;'>" .
                "<h2 style='color:#e63946;'>Buzznation Client Requirement Portal</h2>" .
                "<hr>" .
                nl2br(htmlspecialchars($body)) .
                "<hr><p style='font-size:12px;color:#888;'>This is an automated email. Please do not reply.</p>" .
                "</div></body></html>";

    $result = mail($to, $subject, $htmlBody, $headers);
    if (!$result) {
        error_log("sendEmail failed to send to {$to} with subject: {$subject}");
    }
    return $result;
}
