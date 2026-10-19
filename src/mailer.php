<?php

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

function send_mail(string $toEmail, string $toName, string $subject, string $html, string $text): bool
{
    // no smtp configured: write the mail to a log so local signups still work
    if (env('MAIL_HOST') === null) {
        file_put_contents(
            APP_ROOT . '/storage/logs/mail.log',
            sprintf("[%s] To: %s <%s>\nSubject: %s\n\n%s\n%s\n", date('c'), $toName, $toEmail, $subject, $text, str_repeat('-', 60)),
            FILE_APPEND | LOCK_EX,
        );

        return true;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = env('MAIL_HOST');
        $mail->Port = (int) env('MAIL_PORT', '587');
        $mail->SMTPAuth = env('MAIL_USERNAME') !== null;
        $mail->Username = env('MAIL_USERNAME', '');
        $mail->Password = env('MAIL_PASSWORD', '');
        $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME', 'no-reply@localhost')), env('MAIL_FROM_NAME', app_name()));
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;

        return $mail->send();
    } catch (MailerException $e) {
        error_log("mail to {$toEmail} failed: {$mail->ErrorInfo}");

        return false;
    }
}
