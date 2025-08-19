<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

$GMAIL_USER = $_ENV['GMAIL_USER'];
$GMAIL_APP_PASSWORD = $_ENV['GMAIL_APP_PASSWORD'];
$IT_SUPPORT_EMAIL = $_ENV['IT_SUPPORT_EMAIL'];

$ticket_file = __DIR__ . '/last_ticket_number.txt';
$csv_file = __DIR__ . '/tickets.csv';

function getNextTicketNumber($file) {
    if (!file_exists($file)) file_put_contents($file, '0');
    $last = (int)file_get_contents($file);
    $next = $last + 1;
    file_put_contents($file, $next);
    return $next;
}

function containsLink($text) {
    return preg_match('/https?:\/\/|www\./i', $text);
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname   = trim($_POST['fullname']);
    $department = trim($_POST['department']);
    $contact    = trim($_POST['contact']);
    $category   = trim($_POST['category']);
    $description= trim($_POST['description']);

    $fields_to_check = [
        'Full Name' => $fullname,
        'Department/Store' => $department,
        'Contact (Email or Phone)' => $contact,
        'Issue Description' => $description,
    ];

    foreach ($fields_to_check as $field_name => $field_value) {
        if (containsLink($field_value)) {
            $error = "Error: Links are not allowed in the field \"$field_name\".";
            break;
        }
    }

    if (!$error) {
        $fullname   = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
        $department = htmlspecialchars($department, ENT_QUOTES, 'UTF-8');
        $contact    = htmlspecialchars($contact, ENT_QUOTES, 'UTF-8');
        $category   = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
        $description= htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

        $num = getNextTicketNumber($ticket_file);
        $ticket_number = 'AKIG-' . str_pad($num, 10, '0', STR_PAD_LEFT);

        $mail = new PHPMailer(true);

        try {
            // SMTP settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $GMAIL_USER;
            $mail->Password   = $GMAIL_APP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom($GMAIL_USER, 'IT Support Ticket');
            $mail->addAddress($IT_SUPPORT_EMAIL, 'IT Support');

            // Email content
            $mail->isHTML(true);
            $mail->Subject = "New IT Support Ticket: $ticket_number";
            $mail->Body = "
              <h2>New IT Support Ticket</h2>
              <table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>
                <tr><th align='left'>Ticket Number</th><td>{$ticket_number}</td></tr>
                <tr><th align='left'>Full Name</th><td>{$fullname}</td></tr>
                <tr><th align='left'>Department</th><td>{$department}</td></tr>
                <tr><th align='left'>Contact</th><td>{$contact}</td></tr>
                <tr><th align='left'>Category</th><td>{$category}</td></tr>
                <tr><th align='left'>Description</th><td>" . nl2br($description) . "</td></tr>
              </table>
            ";

            // ✅ Handle file attachment
            if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
                $mail->addAttachment($_FILES['screenshot']['tmp_name'], $_FILES['screenshot']['name']);
            }

            $mail->send();

            // Save CSV log
            $csv_line = [
                $ticket_number,
                $fullname,
                $department,
                $contact,
                $category,
                str_replace(["\r", "\n"], [' ', ' '], $description),
                date('Y-m-d H:i:s')
            ];
            $fp = fopen($csv_file, 'a');
            fputcsv($fp, $csv_line);
            fclose($fp);

            $message = "Ticket submitted successfully! Your ticket number is: <strong>$ticket_number</strong>";
        } catch (Exception $e) {
            $error = "Error: Unable to send ticket. Mailer Error: {$mail->ErrorInfo}";
        }
    }
} else {
    $error = "Invalid request method.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IT Support Ticket Submission</title>
<style>
body, html { margin:0; padding:0; font-family: Arial,sans-serif; background:#111; color:#fff; text-align:center; }
.container { max-width:600px; margin:80px auto; background:rgba(0,0,0,0.75); padding:30px; border-radius:12px; box-shadow:0 0 15px #00c8ff; }
h1 { color:#00c8ff; margin-bottom:20px; }
.message { font-size:18px; margin:20px 0; }
.error { color:#ff5555; }
.success { color:#55ff55; }
button { background:#00c8ff; border:none; padding:12px 25px; font-size:16px; color:#000; font-weight:bold; border-radius:6px; cursor:pointer; }
button:hover { background:#0099cc; }
</style>
</head>
<body>
<div class="container">
  <h1>IT Support Ticket Submission</h1>
  <?php if ($message): ?>
    <p class="message success"><?= $message ?></p>
  <?php elseif ($error): ?>
    <p class="message error"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>
  <button onclick="window.location.href='index.html'">Go Back to Form</button>
</div>
</body>
</html>
