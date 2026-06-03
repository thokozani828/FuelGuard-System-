<?php
// api/change-password.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config.php';

function hashPassword($password) {
    return hash('sha256', $password);
}

function getDriverById($driverId) {
    $url = SUPABASE_URL . '/rest/v1/drivers?driver_id=eq.' . urlencode($driverId) . '&select=*';
    
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

function updateDriverPassword($driverId, $newPasswordHash, $newPlainPassword) {
    $url = SUPABASE_URL . '/rest/v1/drivers?driver_id=eq.' . urlencode($driverId);
    
    $data = json_encode([
        'password_hash' => $newPasswordHash,
        'plain_password' => $newPlainPassword,
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $driverId = $input['driver_id'] ?? '';
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    
    if (empty($driverId) || empty($currentPassword) || empty($newPassword)) {
        echo json_encode([
            'status' => 400,
            'message' => 'Missing required fields'
        ]);
        exit();
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode([
            'status' => 400,
            'message' => 'Password must be at least 6 characters'
        ]);
        exit();
    }
    
    $driver = getDriverById($driverId);
    
    if (!$driver) {
        echo json_encode([
            'status' => 404,
            'message' => 'Driver not found'
        ]);
        exit();
    }
    
    $hashedCurrentPassword = hash('sha256', $currentPassword);
    
    if (password_verify($currentPassword, $driver['password_hash'] ?? '')) {
        // Correct
    } elseif ($hashedCurrentPassword === ($driver['password_hash'] ?? '')) {
        // Legacy SHA256 correct
    } else {
        echo json_encode([
            'status' => 401,
            'message' => 'Current password is incorrect'
        ]);
        exit();
    }
    
    $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $updated = updateDriverPassword($driverId, $newPasswordHash, $newPassword);
    
    if ($updated) {
        echo json_encode([
            'status' => 200,
            'message' => 'Password changed successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 500,
            'message' => 'Failed to update password'
        ]);
    }
    exit();
} else {
    echo json_encode([
        'status' => 405,
        'message' => 'Method not allowed'
    ]);
    exit();
}
?>