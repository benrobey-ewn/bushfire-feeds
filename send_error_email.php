<?php
require_once (dirname(__FILE__).  '/lib/phpmailer/PHPMailerAutoload.php');

function mailError($bodyEmail) {
	$mail = new PHPMailer;
	$mail->isSMTP();                                      // Set mailer to use SMTP
	$mail->Host = 'localhost';  // Specify main and backup SMTP servers
	$mail->SMTPAuth = false;                               // Enable SMTP authentication
	$mail->Port = 25;                                    // TCP port to connect to

	$mail->From = 'errors@ewn.com.au';
	$mail->FromName = 'Error Report Bushfire/Traffic';
	$mail->addAddress('julian@ewn.com.au');   
//	$mail->addAddress('mark@ewn.com.au'); 
	$mail->addAddress('jonathan@ewn.com.au');
//	$mail->addAddress('support@ewn.com.au');

	$mail->isHTML(true);                                  // Set email format to HTML

	$mail->Subject = 'Error Report Traffic/Bushfire';
	$mail->Body    = $bodyEmail . " live";

	if(!$mail->Send()) {
		echo "Mailer Error: " . $mail->ErrorInfo;
	} 
	else {
		echo "Admin notified.";
		echo $bodyEmail;
	}
}
?>