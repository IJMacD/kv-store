<?php
//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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

    public static function sendBucketCreated(string $to, string $name, string $secret)
    {
        $mail = self::getMailer();

        // try {
        //Recipients
        $mail->setFrom('kv@ijmacd.com', 'kv store');
        $mail->addAddress($to);     //Add a recipient

        //Content
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = 'Bucket Created: ' . $name;
        $mail->Body    = '<p>A new bucket has been created on kv.ijmacd.com.</p><p>The endpoint for this bucket is <a href="https://kv.ijmacd.com/' . $name . '">https://kv.ijmacd.com/' . $name . '</a>.</p><p>The admin secret for this bucket is: <code style="font-family: monospace; color: #666; border: 1px solid #CCC; border-radius: 1px; background: #EEE; padding: 1px">' . $secret . '</code></p>';
        // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        $mail->send();
        //     echo 'Message has been sent';
        // } catch (Exception $e) {
        //     echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        // }
    }
}
