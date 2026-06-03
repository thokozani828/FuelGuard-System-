<?php
// api/send_email.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// EMAIL CONFIGURATION
// ============================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'mickeythokozani828@gmail.com');
define('SMTP_PASS', 'hgug yhzg lfcx bnkv');
define('SMTP_FROM_NAME', 'FuelGuard Pro');

require_once dirname(__DIR__) . '/config.php';

// Supabase details


// Load PHPMailer classes
require_once 'PHPMailer.php';
require_once 'SMTP.php';
require_once 'Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function hashPassword($password) {
    return hash('sha256', $password);
}

function getDriverFromSupabase($driverId, $email = null) {
    if ($driverId) {
        $url = SUPABASE_URL . '/rest/v1/drivers?driver_id=eq.' . urlencode($driverId) . '&select=driver_id,full_name,email,password_hash,status';
    } elseif ($email) {
        $url = SUPABASE_URL . '/rest/v1/drivers?email=eq.' . urlencode($email) . '&select=driver_id,full_name,email,password_hash,status';
    } else {
        return null;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }
    return null;
}

function checkDriverExists($email) {
    return getDriverFromSupabase(null, $email);
}

function updateDriverPasswordInSupabase($driverId, $passwordHash) {
    $url = SUPABASE_URL . '/rest/v1/drivers?driver_id=eq.' . urlencode($driverId);
    
    $data = json_encode([
        'password_hash' => $passwordHash,
        'status' => 'pending_invite',
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Prefer: return=minimal'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

// New function to update the plain text password in a separate column (for resending)
function updateDriverPlainPassword($driverId, $plainPassword) {
    $url = SUPABASE_URL . '/rest/v1/drivers?driver_id=eq.' . urlencode($driverId);
    
    $data = json_encode([
        'plain_password' => $plainPassword, // Add this column to your Supabase table
        'last_password_sent' => date('Y-m-d H:i:s')
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Prefer: return=minimal'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

function sendDriverCredentials($to, $full_name, $driver_id, $password) {
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
        $mail->Subject = 'Your FuelGuard Pro Driver Account Credentials';
        
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
                .content { padding: 30px; }
                .credentials-box { background: linear-gradient(135deg, #fef7e0, #fff9e8); border-left: 4px solid #ff6b35; padding: 20px; border-radius: 10px; margin: 20px 0; }
                .credential-item { margin: 10px 0; font-size: 16px; }
                .credential-label { font-weight: bold; color: #1a472a; display: inline-block; width: 100px; }
                .credential-value { color: #ff6b35; font-weight: bold; font-family: monospace; font-size: 18px; }
                .warning { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 20px 0; border-radius: 8px; font-size: 13px; }
                .btn { display: inline-block; background: linear-gradient(135deg, #ff6b35, #e55a2b); color: white; padding: 12px 30px; text-decoration: none; border-radius: 40px; margin: 20px 0; font-weight: bold; }
                .footer { background: #f5f1e8; padding: 20px; text-align: center; font-size: 12px; color: #7a7060; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⛽ FuelGuard Pro</h1>
                    <p>Driver Management System</p>
                </div>
                <div class='content'>
                    <h2>Welcome, {$full_name}!</h2>
                    <p>Your driver account has been created in FuelGuard Pro. Below are your login credentials:</p>
                    
                    <div class='credentials-box'>
                        <div class='credential-item'>
                            <span class='credential-label'>📧 Email:</span>
                            <span class='credential-value'>{$to}</span>
                        </div>
                        <div class='credential-item'>
                            <span class='credential-label'>🔑 Password:</span>
                            <span class='credential-value'>{$password}</span>
                        </div>
                        <div class='credential-item'>
                            <span class='credential-label'>🆔 Driver ID:</span>
                            <span class='credential-value'>{$driver_id}</span>
                        </div>
                    </div>
                    
                    <div class='warning'>
                        ⚠️ <strong>Important Security Notice:</strong><br>
                        • This is your permanent password for this account<br>
                        • You can change your password after login<br>
                        • Do not share these credentials with anyone<br>
                        • If you request a password reset, you will receive the SAME password via email<br>
                        • Login at: FuelGuard Pro Driver Portal
                    </div>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='https://your-login-page.com' class='btn'>Go to Login Page →</a>
                    </div>
                    
                    <hr style='margin: 20px 0; border: none; border-top: 1px solid #eee;'>
                    <p style='font-size: 11px; color: #999; text-align: center;'>
                        This is an automated message from FuelGuard Pro. Please do not reply to this email.
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 FuelGuard Pro. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $htmlContent;
        $mail->AltBody = "Welcome to FuelGuard Pro!\n\nYour driver account credentials:\nEmail: {$to}\nPassword: {$password}\nDriver ID: {$driver_id}\n\nThis is your permanent password for this account. You can change it after login.\n\nIf you request this again, you will receive the SAME password.";
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully', 'password' => $password];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

// Handle the API request
$data = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

if ($action === 'sendCredentials') {
    $to = $data['email'] ?? '';
    $full_name = $data['full_name'] ?? '';
    $driver_id = $data['driver_id'] ?? '';
    
    if (!$to || !$full_name) {
        echo json_encode(['status' => 400, 'error' => 'Missing required fields: email and full_name are required']);
        exit();
    }
    
    if (!$driver_id) {
        echo json_encode(['status' => 400, 'error' => 'Missing driver_id']);
        exit();
    }
    
    // Check if driver exists in Supabase
    $driver = getDriverFromSupabase($driver_id);
    
    if (!$driver) {
        echo json_encode(['status' => 404, 'error' => 'Driver not found in database. Please create driver first.']);
        exit();
    }
    
    // Check if driver already has a password
    $existing_password = null;
    $is_new_account = false;
    
    // If there's already a plain_password stored, use it
    if (isset($driver['plain_password']) && !empty($driver['plain_password'])) {
        $existing_password = $driver['plain_password'];
        $message = "Existing credentials resent successfully";
    } 
    // If no plain_password exists but password_hash exists (can't retrieve original)
    else if (isset($driver['password_hash']) && !empty($driver['password_hash'])) {
        // Generate a new password since we can't retrieve the old one
        $generated_password = generateRandomPassword(12);
        $hashed_password = hashPassword($generated_password);
        
        // Update both hash and plain password
        $updated = updateDriverPasswordInSupabase($driver_id, $hashed_password);
        if ($updated) {
            updateDriverPlainPassword($driver_id, $generated_password);
            $existing_password = $generated_password;
            $message = "New password generated and sent successfully";
            $is_new_account = true;
        } else {
            echo json_encode(['status' => 500, 'error' => 'Failed to store password in database']);
            exit();
        }
    }
    else {
        // First time setup - generate new password
        $generated_password = generateRandomPassword(12);
        $hashed_password = hashPassword($generated_password);
        
        // Update both hash and plain password
        $updated = updateDriverPasswordInSupabase($driver_id, $hashed_password);
        if ($updated) {
            updateDriverPlainPassword($driver_id, $generated_password);
            $existing_password = $generated_password;
            $message = "Driver account created and credentials sent successfully";
            $is_new_account = true;
        } else {
            echo json_encode(['status' => 500, 'error' => 'Failed to store password in database']);
            exit();
        }
    }
    
    // Send email with the same password
    $result = sendDriverCredentials($to, $full_name, $driver_id, $existing_password);
    
    if ($result['success']) {
        echo json_encode([
            'status' => 200, 
            'message' => $message . ' to ' . $to,
            'driver_id' => $driver_id,
            'is_new_account' => $is_new_account,
            'temporary_password' => $is_new_account ? $existing_password : null // Only show if new
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => $result['error']]);
    }
    
} elseif ($action === 'resetPassword') {
    $to = $data['email'] ?? '';
    $full_name = $data['full_name'] ?? '';
    $driver_id = $data['driver_id'] ?? '';
    
    if (!$to || !$full_name || !$driver_id) {
        echo json_encode(['status' => 400, 'error' => 'Missing required fields']);
        exit();
    }
    
    // Get existing driver data
    $driver = getDriverFromSupabase($driver_id);
    
    if (!$driver) {
        echo json_encode(['status' => 404, 'error' => 'Driver not found']);
        exit();
    }
    
    // Check if there's an existing plain password
    $existing_password = $driver['plain_password'] ?? null;
    
    if ($existing_password) {
        // Resend the same password without changing it
        $result = sendDriverCredentials($to, $full_name, $driver_id, $existing_password);
        
        if ($result['success']) {
            echo json_encode([
                'status' => 200,
                'message' => 'Password resent successfully (same password)',
                'same_password' => true
            ]);
        } else {
            echo json_encode(['status' => 500, 'error' => $result['error']]);
        }
    } else {
        // No existing plain password, generate a new one
        $new_password = generateRandomPassword(12);
        $hashed_password = hashPassword($new_password);
        
        $updated = updateDriverPasswordInSupabase($driver_id, $hashed_password);
        
        if ($updated) {
            updateDriverPlainPassword($driver_id, $new_password);
            $result = sendDriverCredentials($to, $full_name, $driver_id, $new_password);
            
            if ($result['success']) {
                echo json_encode([
                    'status' => 200,
                    'message' => 'New password generated and sent',
                    'new_password' => $new_password,
                    'same_password' => false
                ]);
            } else {
                echo json_encode(['status' => 500, 'error' => $result['error']]);
            }
        } else {
            echo json_encode(['status' => 500, 'error' => 'Failed to update password in database']);
        }
    }
    
} elseif ($action === 'checkExistingPassword') {
    // New endpoint to check if a driver has an existing password
    $driver_id = $_GET['driver_id'] ?? '';
    
    if (!$driver_id) {
        echo json_encode(['status' => 400, 'error' => 'Missing driver_id']);
        exit();
    }
    
    $driver = getDriverFromSupabase($driver_id);
    
    if (!$driver) {
        echo json_encode(['status' => 404, 'error' => 'Driver not found']);
        exit();
    }
    
    $hasPassword = isset($driver['plain_password']) && !empty($driver['plain_password']);
    
    echo json_encode([
        'status' => 200,
        'has_existing_password' => $hasPassword,
        'driver_id' => $driver_id
    ]);
    
} else {
    echo json_encode(['status' => 400, 'error' => 'Invalid action. Use action=sendCredentials, action=resetPassword, or action=checkExistingPassword']);
}
?>