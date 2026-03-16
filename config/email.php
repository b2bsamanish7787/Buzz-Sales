<?php
/**
 * Email configuration.
 *
 * sendEmail() uses PHP's built-in mail() function which relies on the server's
 * sendmail/MTA configuration.  For production deployments that require SMTP
 * authentication (e.g. Gmail, SendGrid, Mailgun) replace the mail() call with
 * a dedicated library such as PHPMailer or Symfony Mailer and populate the
 * constants below via environment variables:
 *
 *   SMTP_HOST      – SMTP server hostname
 *   SMTP_PORT      – SMTP port (587 for TLS, 465 for SSL, 25 for plain)
 *   SMTP_USERNAME  – SMTP account username / address
 *   SMTP_PASSWORD  – SMTP account password  (set via env var, never hardcode)
 *   SMTP_FROM_EMAIL – Sender address shown in the From: header
 *   SMTP_FROM_NAME  – Sender display name
 *
 * If SMTP_PASSWORD is empty, email delivery will silently fail on servers that
 * require authentication.  Set the environment variable before deployment.
 */
define('SMTP_HOST',       getenv('SMTP_HOST')       ?: 'localhost');
define('SMTP_PORT',       (int)(getenv('SMTP_PORT') ?: 25));
define('SMTP_USERNAME',   getenv('SMTP_USERNAME')   ?: '');
define('SMTP_PASSWORD',   getenv('SMTP_PASSWORD')   ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'noreply@buzznation.com');
define('SMTP_FROM_NAME',  getenv('SMTP_FROM_NAME')  ?: 'Buzznation Portal');

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
        // Sanitize the address before writing it to the log (prevent log injection)
        $safeRecipient = preg_replace('/[^\w@.\-]/', '', $to);
        error_log("sendEmail failed to send to {$safeRecipient} with subject: {$subject}");
    }
    return $result;
}
