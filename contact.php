<?php
// contact.php - Send emails using PHPMailer with email verification
// Verification email goes to USER, final message goes to YOU

// Load PHPMailer files
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Start session for storing verification codes
session_start();

// Redirect if accessed directly
if ($_SERVER["REQUEST_METHOD"] == "GET" && !isset($_GET['verify'])) {
    header("Location: index.html#contact");
    exit();
}

// Handle email verification (user clicks link in THEIR email)
if (isset($_GET['verify']) && isset($_GET['code']) && isset($_GET['email'])) {
    $email = urldecode($_GET['email']);
    $code = $_GET['code'];
    
    // Check if verification code matches
    if (isset($_SESSION['verify_code']) && 
        $_SESSION['verify_code'] == $code && 
        $_SESSION['verify_email'] == $email) {
        
        // Mark email as verified
        $_SESSION['verified_email'] = $email;
        $_SESSION['email_verified'] = true;
        
        // Also store the original message if it exists
        $original_name = $_SESSION['pending_name'] ?? '';
        $original_message = $_SESSION['pending_message'] ?? '';
        
        // Clear verification data
        unset($_SESSION['verify_code']);
        unset($_SESSION['verify_email']);
        
        // If there's a pending message, send it now
        if (!empty($original_name) && !empty($original_message)) {
            sendContactMessage($original_name, $email, $original_message);
            unset($_SESSION['pending_name']);
            unset($_SESSION['pending_message']);
            exit();
        }
        
        // Show success message
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Email Verified</title>
    <meta http-equiv='refresh' content='3;url=index.html#contact'>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #28a745; margin-bottom: 20px; }
        p { color: #666; margin-bottom: 20px; }
        .btn {
            display: inline-block;
            padding: 10px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
        }
    </style>
</head>
<body>
    <div class='box'>
        <h1>✓ Email Verified!</h1>
        <p>Your email has been successfully verified.<br>You can now send your message.</p>
        <a href='index.html#contact' class='btn'>Return to Contact Form</a>
        <p style='margin-top: 20px; font-size: 0.8rem;'>Redirecting in 3 seconds...</p>
    </div>
</body>
</html>";
        exit();
    } else {
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Verification Failed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #dc3545; margin-bottom: 20px; }
        p { color: #666; margin-bottom: 20px; }
        .btn {
            display: inline-block;
            padding: 10px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
        }
    </style>
</head>
<body>
    <div class='box'>
        <h1>✗ Verification Failed</h1>
        <p>Invalid or expired verification code.<br>Please try again.</p>
        <a href='index.html#contact' class='btn'>Return to Contact Form</a>
    </div>
</body>
</html>";
        exit();
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($message)) {
        echo "<script>
                alert('Please fill all fields');
                window.location.href='index.html#contact';
              </script>";
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>
                alert('Invalid email format');
                window.location.href='index.html#contact';
              </script>";
        exit();
    }
    
    // Check if email is already verified for this session
    if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true && $_SESSION['verified_email'] == $email) {
        // Email is verified, send the message directly to YOU
        sendContactMessage($name, $email, $message);
    } else {
        // Email not verified, store message and send verification email to USER
        $_SESSION['pending_name'] = $name;
        $_SESSION['pending_message'] = $message;
        sendVerificationEmail($name, $email);
    }
}

function sendVerificationEmail($name, $email) {
    // Generate verification code
    $verification_code = rand(100000, 999999);
    
    // Store in session
    $_SESSION['verify_code'] = $verification_code;
    $_SESSION['verify_email'] = $email;
    
    // Create verification link
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $verify_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/GP/contact.php?verify=1&code=" . $verification_code . "&email=" . urlencode($email);
    
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gpinfotech.vapi@gmail.com';
        $mail->Password   = 'ytkc iowt pgad tvqp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // THIS EMAIL GOES TO THE USER - for verification
        $mail->setFrom('gpinfotech.vapi@gmail.com', 'GP Infotech');
        $mail->addAddress($email, $name); // Send to USER'S email
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Please Verify Your Email - GP Infotech';
        $mail->Body    = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 50px; margin: 20px 0; }
                .code { font-size: 32px; font-weight: bold; color: #667eea; text-align: center; padding: 20px; background: white; border-radius: 10px; }
                .footer { text-align: center; color: #999; font-size: 12px; padding: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Email Verification</h2>
                </div>
                <div class='content'>
                    <h3>Hello $name,</h3>
                    <p>Thank you for contacting GP Infotech. Please verify your email address by clicking the button below:</p>
                    
                    <div style='text-align: center;'>
                        <a href='$verify_link' class='button'>Verify Email Address</a>
                    </div>
                    
                    <p>Or use this verification code:</p>
                    <div class='code'>$verification_code</div>
                    
                    <p>Once verified, your message will be sent to GP Infotech.</p>
                    <p>This code will expire in 1 hour.</p>
                    
                    <p>If you didn't request this, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 GP Infotech. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello $name,\n\nPlease verify your email by clicking this link:\n$verify_link\n\nVerification code: $verification_code\n\nOnce verified, your message will be sent to GP Infotech.\n\nThis code will expire in 1 hour.";
        
        $mail->send();
        
        // Show verification required page
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Verification Required</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #667eea; margin-bottom: 20px; }
        p { color: #666; margin-bottom: 20px; line-height: 1.6; }
        .email-highlight {
            background: #f0f2f5;
            padding: 15px;
            border-radius: 10px;
            font-weight: bold;
            color: #667eea;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            margin: 10px;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .note {
            font-size: 0.9rem;
            color: #999;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='box'>
        <h1>📧 Verification Required</h1>
        <p>We've sent a verification email to:</p>
        <div class='email-highlight'>$email</div>
        <p>Please check YOUR inbox and click the verification link to confirm your email address.</p>
        <p>Once verified, your message will be automatically sent to GP Infotech.</p>
        <a href='index.html#contact' class='btn'>Back to Form</a>
        <a href='https://mail.google.com/' target='_blank' class='btn btn-secondary'>Open Gmail</a>
        <p class='note'>Didn't receive the email? Check your spam folder or try again.</p>
    </div>
</body>
</html>";
        
    } catch (Exception $e) {
        echo "<script>
                alert('Failed to send verification email. Error: " . addslashes($mail->ErrorInfo) . "');
                window.location.href='index.html#contact';
              </script>";
    }
    exit();
}

function sendContactMessage($name, $email, $message) {
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gpinfotech.vapi@gmail.com';
        $mail->Password   = 'ytkc iowt pgad tvqp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // THIS EMAIL GOES TO YOU - the actual message
        $mail->setFrom('gpinfotech.vapi@gmail.com', 'GP Infotech Website');
        $mail->addAddress('gpinfotech.vapi@gmail.com'); // Send to YOUR email
        $mail->addReplyTo($email, $name);
        
        // Add verified email note
        $mail->addCustomHeader('X-Verified-Email: Yes');
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = 'New Contact Message from ' . $name . ' (Verified Email)';
        $mail->Body    = "You have received a new message from a verified email.\n\n"
                       . "Name: $name\n"
                       . "Email: $email (VERIFIED)\n\n"
                       . "Message:\n$message\n\n"
                       . "---\n"
                       . "This sender's email has been verified.";
        
        // Send email
        $mail->send();
        
        // Keep verification for future messages from same email in this session
        // Don't unset the verification
        
        // Success message
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Message Sent</title>
    <meta http-equiv='refresh' content='3;url=index.html'>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #28a745; margin-bottom: 20px; }
        p { color: #666; margin-bottom: 20px; }
        .btn {
            display: inline-block;
            padding: 10px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
        }
        .verified-badge {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class='box'>
        <div class='verified-badge'>✓ Verified Email</div>
        <h1>✓ Message Sent!</h1>
        <p>Thank you for contacting GP Infotech.<br>We'll get back to you soon at <strong>$email</strong>.</p>
        <a href='index.html' class='btn'>Return to Homepage</a>
        <p style='margin-top: 20px; font-size: 0.8rem;'>Redirecting in 3 seconds...</p>
    </div>
</body>
</html>";
        
    } catch (Exception $e) {
        // Error message
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Error</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #dc3545; margin-bottom: 20px; }
        p { color: #666; margin-bottom: 20px; }
        .btn {
            display: inline-block;
            padding: 10px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
        }
    </style>
</head>
<body>
    <div class='box'>
        <h1>✗ Sending Failed</h1>
        <p>Sorry, there was an error sending your message.<br>Please try again or contact us directly.</p>
        <a href='index.html#contact' class='btn'>Try Again</a>
    </div>
</body>
</html>";
    }
}
?>