<?php
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

// Handle login
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

// Find driver by email
$url = SUPABASE_URL . '/rest/v1/drivers?email=eq.' . urlencode($email) . '&select=*';
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

if ($httpCode !== 200) {
    echo json_encode(['status' => 500, 'message' => 'Database connection error']);
    exit();
}

$drivers = json_decode($response, true);
$driver = !empty($drivers) ? $drivers[0] : null;

if (!$driver) {
    echo json_encode(['status' => 401, 'message' => 'Driver not found']);
    exit();
}

// Verify password
if (password_verify($password, $driver['password_hash'] ?? '')) {
    // If it's a legacy SHA256 hash (64 chars), verify it that way too for migration
} elseif (hash('sha256', $password) === ($driver['password_hash'] ?? '')) {
    // Legacy support
} else {
    echo json_encode(['status' => 401, 'message' => 'Invalid password']);
    exit();
}

// Check status
if ($driver['status'] === 'blocked') {
    echo json_encode(['status' => 401, 'message' => 'Account blocked']);
    exit();
}

// Return success
session_start();
$_SESSION['driver_id'] = $driver['driver_id'];
$_SESSION['full_name'] = $driver['full_name'];
$_SESSION['role'] = 'driver';

$sessionToken = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

echo json_encode([
    'status' => 200,
    'message' => 'Login successful',
    'session' => [
        'driver_id' => $driver['driver_id'],
        'full_name' => $driver['full_name'],
        'email' => $driver['email'],
        'phone' => $driver['phone'] ?? '',
        'license_number' => $driver['license_number'] ?? '',
        'license_class' => $driver['license_class'] ?? '',
        'license_expiry' => $driver['license_expiry'] ?? '',
        'safety_score' => $driver['safety_score'] ?? 100,
        'status' => $driver['status'],
        'shift' => $driver['shift'] ?? 'morning',
        'session_token' => $sessionToken,
        'expires_at' => $expiresAt
    ],
    'redirect' => 'driver-dashboard.html'
]);
?>