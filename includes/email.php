<?php

function emailDeliveryError(): string
{
    return (string) ($GLOBALS['nexora_email_delivery_error'] ?? '');
}

function setEmailDeliveryError(string $message): string
{
    $secrets = array_filter([MAIL_PASSWORD, MAIL_USERNAME], static function (string $value): bool {
        return $value !== '';
    });
    $message = str_replace($secrets, '[redacted]', $message);
    $message = preg_replace('/\s+/', ' ', trim($message));
    $GLOBALS['nexora_email_delivery_error'] = $message;

    return $message;
}

function emailDeliveryFailureMessage(string $message): string
{
    $detail = emailDeliveryError();
    if (APP_DEBUG && $detail !== '') {
        return $message . ' Development diagnostic: ' . $detail;
    }

    return $message;
}

function nexoraEmailTemplate(string $content): string
{
    $name = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');

    return '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
        . $name . '</title></head><body style="margin:0;padding:24px;background:#faf9f5;font-family:Arial,sans-serif;color:#26332a;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
        . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e6ece7;border-radius:16px;overflow:hidden;">'
        . '<tr><td style="padding:24px 30px;background:#1d2d24;color:#ffffff;font-size:20px;font-weight:bold;">NEXORA INSTITUTE</td></tr>'
        . '<tr><td style="padding:30px;line-height:1.7;font-size:15px;">' . $content . '</td></tr>'
        . '<tr><td style="padding:18px 30px;background:#f1f5f1;color:#68736b;font-size:12px;">'
        . 'Learn &bull; Grow &bull; Succeed<br>' . $name . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function sendEmail(string $to, string $subject, string $htmlBody, string $textBody = '', array $replyTo = []): bool
{
    $GLOBALS['nexora_email_delivery_error'] = '';

    if (MAIL_MAILER !== 'smtp') {
        setEmailDeliveryError('MAIL_MAILER must be set to smtp.');
        return false;
    }
    if (MAIL_HOST === '' || !ctype_digit(MAIL_PORT) || (int) MAIL_PORT < 1 || (int) MAIL_PORT > 65535
        || !in_array(MAIL_SMTP_SECURE, ['tls', 'ssl'], true)) {
        setEmailDeliveryError('SMTP host, valid port, and TLS/SSL encryption must be configured in .env.');
        return false;
    }
    if (!filter_var(MAIL_USERNAME, FILTER_VALIDATE_EMAIL) || !filter_var(MAIL_FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
        setEmailDeliveryError('MAIL_USERNAME and MAIL_FROM_ADDRESS must be valid email addresses.');
        return false;
    }
    if (MAIL_PASSWORD === '' || MAIL_PASSWORD === 'YOUR_NEW_GMAIL_APP_PASSWORD') {
        setEmailDeliveryError('MAIL_PASSWORD is empty or still contains the .env.example placeholder. Set the Gmail App Password in the local .env file.');
        return false;
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        setEmailDeliveryError('The destination email address is invalid.');
        return false;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        setEmailDeliveryError('Composer dependencies are missing. Run composer install in the project directory.');
        return false;
    }

    require_once $autoload;
    if (!class_exists(PHPMailer\PHPMailer\PHPMailer::class)) {
        setEmailDeliveryError('PHPMailer is not available from the Composer autoloader.');
        return false;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_SMTP_SECURE === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) MAIL_PORT;
        $mail->Timeout = 20;
        $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to);

        foreach ($replyTo as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($address);
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nexoraEmailTemplate($htmlBody);
        $mail->AltBody = $textBody !== ''
            ? $textBody
            : strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

        $sent = $mail->send();
        if (!$sent) {
            $diagnostic = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : 'PHPMailer returned false without an SMTP error.';
            setEmailDeliveryError($diagnostic);
            return false;
        }

        return true;
    } catch (PHPMailer\PHPMailer\Exception $exception) {
        $diagnostic = isset($mail) && $mail->ErrorInfo !== ''
            ? $mail->ErrorInfo
            : $exception->getMessage();
        $diagnostic = setEmailDeliveryError($diagnostic);
        error_log('Nexora email delivery failed: ' . $diagnostic);
        return false;
    }
}
