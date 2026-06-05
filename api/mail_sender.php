<?php
/**
 * FuelGuard Pro - Mail Utility
 * Provides shared email sending functionality
 */

require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';
require_once __DIR__ . '/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email Configuration (Centralized)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', 'mickeythokozani828@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'hgug yhzg lfcx bnkv');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'FuelGuard Pro');

/**
 * Send a professional invitation email to a driver
 */
function sendInvitationEmail($to, $full_name, $register_link) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to, $full_name);
        
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to FuelGuard Pro - Action Required: Set Up Your Account';
        
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f5f1e8; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #1a472a, #0d2818); padding: 30px; text-align: center; }
                .header h1 { color: #ff6b35; margin: 0; font-size: 28px; }
                .header p { color: #c8d5b9; margin: 5px 0 0; }
                .content { padding: 40px; }
                .welcome-text { font-size: 20px; color: #1a472a; font-weight: bold; margin-bottom: 20px; }
                .info-box { background-color: #f9f9f9; border-left: 4px solid #ff6b35; padding: 20px; margin: 25px 0; border-radius: 8px; }
                .btn-container { text-align: center; margin: 35px 0; }
                .btn { display: inline-block; background: linear-gradient(135deg, #ff6b35, #e55a2b); color: white !important; padding: 15px 35px; text-decoration: none; border-radius: 40px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(255,107,53,0.3); }
                .footer { background: #f5f1e8; padding: 20px; text-align: center; font-size: 12px; color: #7a7060; }
                .expiry-note { font-size: 12px; color: #999; margin-top: 20px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⛽ FuelGuard Pro</h1>
                    <p>Fleet Intelligence System</p>
                </div>
                <div class='content'>
                    <div class='welcome-text'>Hello, {$full_name}!</div>
                    <p>Welcome to the company! You have been accepted as a driver in the FuelGuard Pro management system.</p>
                    
                    <p>To get started and access the Driver App on the Truck Entertainment System, you need to set up your secure password.</p>
                    
                    <div class='info-box'>
                        <strong>Account Details:</strong><br>
                        • Role: Professional Driver<br>
                        • App: Driver Portal & Telemetry System
                    </div>
                    
                    <div class='btn-container'>
                        <a href='{$register_link}' class='btn'>Set Up Your Password →</a>
                    </div>
                    
                    <p style='font-size: 14px;'>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='font-size: 12px; color: #666; word-break: break-all;'>{$register_link}</p>
                    
                    <div class='expiry-note'>
                        Note: This invitation link is unique to you and will expire in 1 hour.
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 FuelGuard Pro. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $htmlContent;
        $mail->AltBody = "Welcome to FuelGuard Pro, {$full_name}!\n\nYou have been added as a driver. Please set up your password using the link below:\n\n{$register_link}\n\nThis link will expire in 1 hour.";
        
        $mail->send();
        return ['success' => true, 'message' => 'Invitation email sent'];
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Send direct credentials (old method, kept for compatibility)
 */
function sendDriverCredentialsEmail($to, $full_name, $driver_id, $password) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to, $full_name);
        
        $mail->isHTML(true);
        $mail->Subject = 'FuelGuard Pro - Your Driver Credentials';
        
        $htmlContent = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
            <h2 style='color: #ff6b35;'>Welcome to FuelGuard Pro!</h2>
            <p>Hello <strong>{$full_name}</strong>,</p>
            <p>Your driver account has been created. Use the credentials below to log in to the driver application:</p>
            <div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong>Driver ID:</strong> {$driver_id}</p>
                <p style='margin: 5px 0;'><strong>Email:</strong> {$to}</p>
                <p style='margin: 5px 0;'><strong>Temporary Password:</strong> <span style='color: #e55a2b; font-family: monospace; font-size: 16px;'>{$password}</span></p>
            </div>
            <p style='color: #777; font-size: 12px;'>Please change your password after your first login.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 11px; color: #999;'>Sent by FuelGuard Pro Automated System</p>
        </div>
        ";
        
        $mail->Body = $htmlContent;
        $mail->AltBody = "Welcome to FuelGuard Pro!\n\nYour account has been created.\nDriver ID: {$driver_id}\nEmail: {$to}\nTemporary Password: {$password}\n\nPlease change your password after first login.";
        
        $mail->send();
        return ['success' => true, 'message' => 'Credentials email sent'];
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
?>