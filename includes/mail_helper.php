<?php
/**
 * Mail Helper - Sends emails for password reset and notifications
 */

function send_password_reset_email($to_email, $user_name, $reset_link) {
    $config = require __DIR__ . '/../config.php';
    
    $subject = 'Reset Your Password - CHPCEBU Attendance System';
    $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
        }
        .content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset Request</h1>
        </div>
        
        <div class="content">
            <p>Hello <strong>{$user_name}</strong>,</p>
            
            <p>We received a request to reset the password associated with your account. If you made this request, please click the button below to reset your password.</p>
            
            <center>
                <a href="{$reset_link}" class="button">Reset Password</a>
            </center>
            
            <p><strong>Or copy and paste this link in your browser:</strong></p>
            <p><a href="{$reset_link}">{$reset_link}</a></p>
            
            <div class="warning">
                <strong>⚠️ Security Notice:</strong> This link will expire in 24 hours. If you did not request a password reset, please ignore this email or contact support immediately.
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; Center for Healthcare Profession Cebu, Inc. All rights reserved.</p>
            <p>This is an automated email. Please do not reply directly to this message.</p>
        </div>
    </div>
</body>
</html>
HTML;

    return send_email($to_email, $subject, $body);
}

function send_email($to_email, $subject, $body) {
    $config = require __DIR__ . '/../config.php';
    
    $from_email = $config['email_from'];
    $from_name = $config['email_from_name'];
    
    // Prepare headers
    $headers = [];
    $headers[] = "From: {$from_name} <{$from_email}>";
    $headers[] = "Reply-To: {$from_email}";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    
    $headers_str = implode("\r\n", $headers);
    
    try {
        // Check if in development/testing mode
        if (defined('MAIL_DEVELOPMENT_MODE') && MAIL_DEVELOPMENT_MODE) {
            // Save email to file for testing instead of sending
            $email_log_dir = __DIR__ . '/../storage/emails';
            if (!is_dir($email_log_dir)) {
                mkdir($email_log_dir, 0755, true);
            }
            
            $filename = $email_log_dir . '/' . date('Y-m-d-H-i-s-') . uniqid() . '.txt';
            $email_content = "TO: {$to_email}\n";
            $email_content .= "SUBJECT: {$subject}\n";
            $email_content .= "FROM: {$from_name} <{$from_email}>\n";
            $email_content .= "TIME: " . date('Y-m-d H:i:s') . "\n";
            $email_content .= "---\n";
            $email_content .= "HEADERS:\n{$headers_str}\n";
            $email_content .= "---\n";
            $email_content .= "BODY:\n{$body}\n";
            
            file_put_contents($filename, $email_content);
            error_log("Email saved to: {$filename}");
            return true; // Success in development mode
        }
        
        // Use PHP's built-in mail function (default)
        if ($config['use_php_mail'] && !$config['smtp_enabled']) {
            // Escape subject to prevent header injection
            $subject = str_replace(["\r", "\n"], '', $subject);
            $result = @mail($to_email, $subject, $body, $headers_str);
            
            if (!$result) {
                error_log("Mail failed to send to {$to_email}. Check SMTP settings in php.ini");
                // In development, return true anyway
                if (strpos(php_uname(), 'Windows') !== false) {
                    error_log("Windows detected - email not configured. Use development mode or configure SMTP.");
                    return send_email_development_fallback($to_email, $subject, $body);
                }
            }
            return $result;
        }
        
        // SMTP support (can be implemented later if needed)
        if ($config['smtp_enabled']) {
            // For now, fallback to mail function
            // A full SMTP implementation would require PHPMailer or SwiftMailer
            $subject = str_replace(["\r", "\n"], '', $subject);
            return @mail($to_email, $subject, $body, $headers_str);
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Mail error: " . $e->getMessage());
        return send_email_development_fallback($to_email, $subject, $body);
    }
}

function send_email_development_fallback($to_email, $subject, $body) {
    // Fallback for development/testing - save email to file
    $email_log_dir = __DIR__ . '/../storage/emails';
    if (!is_dir($email_log_dir)) {
        mkdir($email_log_dir, 0755, true);
    }
    
    $filename = $email_log_dir . '/' . date('Y-m-d-H-i-s-') . uniqid() . '.txt';
    $email_content = "TO: {$to_email}\n";
    $email_content .= "SUBJECT: {$subject}\n";
    $email_content .= "TIME: " . date('Y-m-d H:i:s') . "\n";
    $email_content .= "---\n";
    $email_content .= "BODY:\n{$body}\n";
    
    file_put_contents($filename, $email_content);
    error_log("Email saved (fallback) to: {$filename}");
    return true;
}

function generate_reset_token() {
    return bin2hex(random_bytes(32));
}

function get_reset_token_expiry($hours = 24) {
    return date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
}
