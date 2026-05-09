<?php
// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set response header
header('Content-Type: application/json');

// Get the form data
$firstName = isset($_POST['firstName']) ? sanitize_input($_POST['firstName']) : '';
$lastName = isset($_POST['lastName']) ? sanitize_input($_POST['lastName']) : '';
$email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
$subject = isset($_POST['subject']) ? sanitize_input($_POST['subject']) : 'Website Inquiry';
$message = isset($_POST['message']) ? sanitize_input($_POST['message']) : '';

// Validate required fields
$errors = [];
if (empty($firstName)) $errors[] = 'First name is required';
if (empty($lastName)) $errors[] = 'Last name is required';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
if (empty($message)) $errors[] = 'Message is required';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Prepare the email
$to = 'info@afmportsmouth.co.uk';
$fullName = $firstName . ' ' . $lastName;
$emailSubject = 'New Contact Form Submission: ' . $subject;

// Build the email body
$emailBody = "Name: " . $fullName . "\r\n";
$emailBody .= "Email: " . $email . "\r\n";
$emailBody .= "Subject: " . $subject . "\r\n";
$emailBody .= "\r\nMessage:\r\n";
$emailBody .= $message . "\r\n";

// Set email headers
$headers = "From: noreply@afmportsmouth.co.uk\r\n";
$headers .= "Reply-To: " . $email . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Send the email
$mailResult = mail($to, $emailSubject, $emailBody, $headers);

if ($mailResult) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your message! We will get back to you soon.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'There was an error sending your message. Please try again later.'
    ]);
}

// Sanitize input to prevent injection
function sanitize_input($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}
?>
