<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../../php_libs/PHPMailer/src/Exception.php';
require '../../php_libs/PHPMailer/src/PHPMailer.php';
require '../../php_libs/PHPMailer/src/SMTP.php';

require_once '../../php_libs/formr/class.formr.php';

require '../../php_libs/IPLogger/ip-logging.php';
$log_file = 'spam-protection.log';

// NOTE:The field mail is a fake field for spam protection
$fields = ['apartment', 'group_size', 'full_name', 'pronouns', 'email', 'age', 'mail', 'wer', 'wohnvorstellung',
'sonstiges', 'barrier_free', 'children', 'spam_protection'];

$i18n = [
    "de" => [
        "apartment" => "Wohnung",
        "group_size" => "Gruppengröße",
        "full_name" => "Name",
        "email" => "E-mail",
        "age" => "Geburtstag",
        "pronouns" => "Pronomen",
        "leitbild" => "Leitbild",
        "wer" => "Wer seid ihr?"
        "wohnvorstellung" => "Wohnvorstellung"
        "sonstiges" => "Sonstiges",
        "info" => "Wie haben Sie uns gefunden",
        "barrier_free" => "Barrierefreiheit",
        "children" => "Kinder",
        "application" => "Bewerbung",
        "application-sent" => "bewerbung-verschickt",
	    "spam-protection" => "spamschutz",
        "mail-message" => "Vielen Dank für deine Bewerbung beim CA!\nWir freuen uns, dass du bei uns einziehen möchtest. Mit dieser Nachricht bestätigen wir, dass wir deine Bewerbung erhalten haben. Bitte beachte, dass wir uns erst nach Ende der Bewerbungsfrist bei dir melden, weil wir je nach Anzahl der Bewerbungen ggf. auslosen müssen, welche Bewerber*innen wir zum Kennenlerntag einladen können. Falls du Fragen zu deiner Bewerbung hast, kannst du einfach auf diese E-Mail antworten.",
        "with-data" => "Wir haben folgende Daten empfangen:",
        "privacy-notice" => "Wir behalten diese Daten nur für die Dauer deiner Bewerbung. Danach werden sie gelöscht. Auf https://collegiumacademicum.de/datenschutz/ findest du weitere Informationen zu unserer Datenschutzerklärung.",
        "dear" => "Liebe*r",
    ],
    "en" => [
        "apartment" => "Apartment",
        "group_size" => "Group size",
        "full_name" => "Name",
        "email" => "E-mail",
        "age" => "Birthday",
        "pronouns" => "Pronouns",
        "who" => "Who are you?"
        "wohnvorstellung" => "Living Idea"
        "sonstiges" => "Miscellaneous",
        "info" => "How did you find us?"
        "barrier_free" => "Accessibility",
        "children" => "Children",
        "application" => "Application",
        "application-sent" => "en/application-sent",
	    "spam-protection" => "en/spam_protection",
        "mail-message" => "Thank you for your application to the CA!\nWe are happy that you are interested in moving into our dormitory. With this message we confirm that we have received your application. Please note that we will only contact you after the application deadline, because depending on the number of applications, we may have to draw lots to decide which applicants we can invite to the get-to-know day. If you have any questions about your application, you can reply to this email.",
        "with-data" => "We received the following data:",
        "privacy-notice" => "We are keeping this data only for the duration of your application, after which it will be deleted. Please see https://collegiumacademicum.de/datenschutz/ for further information about our privacy policy.",
        "dear" => "Dear",
    ]];
// $number_of_inboxes = 4;

// Creates the form: command inserts the html form tag
$form = new Formr();

function send_mail($from, $to, $data, $lang, $with_message) {
    global $i18n;
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $hr = "\n\n" . str_repeat("-", 45) . "\n\n";

    try {
        call_user_func_array(array($mail, "setFrom"), $from);
        call_user_func_array(array($mail, "addAddress"), $to);
        call_user_func_array(array($mail, "addReplyTo"), $from);

        $mail->Subject = "{$i18n[$lang]['application']} {$data['full_name']} Collegium Academicum";
        $body = "";

        if ($with_message) {
            $body .= "{$i18n[$lang]['dear']} {$data['full_name']},";
            $body .= "\n\n{$i18n[$lang]['mail-message']}";
        }

        $body .= $hr;
        $body .= "{$i18n[$lang]['with-data']}\n\n";
        foreach ($data as $key => $value) {
            $_val = wordwrap(str_replace("\n", "\n\t", $value), 60, "\n\t");
            $body .= "{$i18n[$lang][$key]}:\n";
            $body .= "\t{$_val}\n";
        }
        $body .= $hr;

        if ($with_message) {
            $body .= $i18n[$lang]["privacy-notice"];
        }

        $mail->Body = $body;
        $mail->send();
    } catch (Exception $e) {
        echo "Message from {$from[0]} to {$to[0]} could not be sent.\nMailer Error: {$mail->ErrorInfo}.\nPlease, contact m_frank@collegiumacademicum.de";
    }
}

// check in the ip logs for computer who try to sent more than three times
function check_for_spam($logs, $ip_address) {
    $hashed_ip = hash('sha256', $ip_address);
    foreach ($logs as $log) {
        if($log['ip'] == $hashed_ip and $log['tries'] > 3) {
            return true;
        }
    }
    return false;
}
    

if($form->submit()){
    $lang = $form->post("language");
    if (!in_array($lang, ['de', 'en'])) {
        header('Location:./');
    }
    // log ip address
    $ip_address = get_ip_addr();
    $logs = read_logs($log_file);
    $logs = update_logs($logs, $ip_address);
    write_logs($logs, $log_file);

    // spam protection
    $spam = check_for_spam($logs, $ip_address);
    $spam_protection = $form->post("spam_protection");
    $fake_mail_field = $form->post("mail");
    if ($spam_protection == 8 and $fake_mail_field == "" and ! $spam ) {
        $data = ["email" => $form->post('email','Email','valid_email')];
    	foreach ($fields as $field) {
        	$_dat = $form->post($field);
        	// just a sanity check, shouldnt happen but if somebody does shenanigans this will cut it down
        	if (mb_strlen($_dat, 'utf8') > 2500) {
            	   $_dat = mb_substr($_dat, 0, 2500, 'utf8');
        	}	   
        $data[$field] = $_dat;
    	}

    	$applicant = array($data["email"], $data['full_name']);

    	// The id of the auswahl team this email goes to
    	// $rid = rand(1,$number_of_inboxes);
    	$contact = array("einziehen.altbau@collegiumacademicum.de", "Collegium Academicum");

    	// Send the mail to the applicant as a confirmation
    	send_mail($contact, $applicant, $data, $lang, True);

    	// Send the mail to us @ posteo
    	send_mail($applicant, $contact, $data, $lang, False);

    	header("Location:/{$i18n[$lang]["application-sent"]}");
    } else {
       header("Location:/{$i18n[$lang]["spam-protection"]}");
    }
} else {
    header("Location:./");
}
?>
