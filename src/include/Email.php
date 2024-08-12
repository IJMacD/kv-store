<?php

namespace KVStore;

use KVStore\Emails\BaseEmail;
use PHPMailer\PHPMailer\PHPMailer;
use ReflectionException;

class Email
{
    private static function getMailer()
    {
        //Create an instance; passing `true` enables exceptions
        $mail = new PHPMailer(true);

        //Server settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;                  //Enable verbose debug output
        $mail->isSMTP();                                        //Send using SMTP
        $mail->Host       = getenv("SMTP_SERVER");              //Set the SMTP server to send through
        $mail->SMTPAuth   = true;                               //Enable SMTP authentication
        $mail->Username   = getenv("SMTP_USER");                //SMTP username
        $mail->Password   = getenv("SMTP_PASS");                //SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;        //Enable implicit TLS encryption
        $mail->Port       = 465;                                //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        return $mail;
    }

    public static function send(string $emailClass, string $to, $params)
    {
        $mail = self::getMailer();

        // try {
        //Recipients
        $mail->setFrom('kv@ijmacd.com', 'kv store');
        $mail->addAddress($to);     //Add a recipient

        if (!is_subclass_of($emailClass, BaseEmail::class)) {
            throw new ReflectionException($emailClass . " is not a subclass of " . BaseEmail::class);
        }

        /** @var BaseEmail */
        $email = new $emailClass();

        //Content
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = $email->subject($params);
        $mail->Body    = $email->body($params);
        // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        $mail->send();
        //     echo 'Message has been sent';
        // } catch (Exception $e) {
        //     echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        // }
    }
}
