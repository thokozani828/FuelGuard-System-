<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Supabase Configuration
define('SUPABASE_URL', 'https://shdaldiqnbtlgjajxroi.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNoZGFsZGlxbmJ0bGdqYWp4cm9pIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzkwMzA0MTcsImV4cCI6MjA5NDYwNjQxN30.BDRnisUkar6CaBKc0-AI6IXw16yfgjrkqEv59PWkJIo');

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
$hashedPassword = hashPassword($password);
if ($hashedPassword !== ($driver['password_hash'] ?? '')) {
    echo json_encode(['status' => 401, 'message' => 'Invalid password']);
    exit();
}

// Check status
if ($driver['status'] === 'blocked') {
    echo json_encode(['status' => 401, 'message' => 'Account blocked']);
    exit();
}

// Return success
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