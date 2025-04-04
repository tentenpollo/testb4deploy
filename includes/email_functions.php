<?php
// These paths were incorrect - the '../' at the beginning is causing problems
// require '/../PHPMailer/src/PHPMailer.php';
// require '/../PHPMailer/src/Exception.php';
// require '/../PHPMailer/src/SMTP.php';

// Use __DIR__ to make sure the path is relative to the current file
require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/Exception.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $plain_body Plain text alternative (optional)
 * @param array $attachments Array of file paths to attach (optional)
 * @param array $cc Array of CC email addresses (optional)
 * @param array $bcc Array of BCC email addresses (optional)
 * @return bool Success or failure
 */
function send_email_smtp($to, $subject, $body, $plain_body = '', $attachments = [], $cc = [], $bcc = []) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp-relay.brevo.com'; // Replace with your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = '898b50001@smtp-brevo.com'; // Replace with your SMTP username
        $mail->Password   = 'LPUCkaOIzSmK0d7E'; // Replace with your SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // or PHPMailer::ENCRYPTION_SMTPS
        $mail->Port       = 587; // TCP port to connect to, use 465 for ENCRYPTION_SMTPS
        
        // System email settings
        $mail->setFrom('sandsbelando1021@gmail.com', 'NKTI TEST');
        $mail->addAddress($to);
        
        // Add CC recipients
        if (!empty($cc)) {
            foreach ($cc as $cc_email) {
                $mail->addCC($cc_email);
            }
        }
        
        // Add BCC recipients
        if (!empty($bcc)) {
            foreach ($bcc as $bcc_email) {
                $mail->addBCC($bcc_email);
            }
        }
        
        // Add attachments
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        // Set plain text alternative if provided
        if (!empty($plain_body)) {
            $mail->AltBody = $plain_body;
        } else {
            // Create a simple plain text version by stripping HTML
            $mail->AltBody = strip_tags($body);
        }
        
        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Get the email template content and replace placeholders
 * 
 * @param string $template_name Template file name
 * @param array $replacements Key-value array of placeholders and their replacements
 * @return string The email content with replacements
 */
function get_email_template($template_name, $replacements = []) {
    // This path was also incorrect - missing a slash between __DIR__ and '../templates'
    $template_path = __DIR__ . '/../templates/emails/' . $template_name . '.html';
    
    if (!file_exists($template_path)) {
        error_log("Email template not found: $template_path");
        return '';
    }
    
    $content = file_get_contents($template_path);
    
    // Replace placeholders with actual values
    foreach ($replacements as $placeholder => $value) {
        $content = str_replace('{{' . $placeholder . '}}', $value, $content);
    }
    
    return $content;
}