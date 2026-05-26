
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Supabase configuration
define('SUPABASE_URL', 'https://shdaldiqnbtlgjajxroi.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNoZGFsZGlxbmJ0bGdqYWp4cm9pIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzkwMzA0MTcsImV4cCI6MjA5NDYwNjQxN30.BDRnisUkar6CaBKc0-AI6IXw16yfgjrkqEv59PWkJIo');


function callSupabase($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . $endpoint;
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['data' => json_decode($response, true), 'status' => $httpCode];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $firstName = trim($input['firstName'] ?? '');
    $lastName = trim($input['lastName'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validation
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }
    
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    // Check if email exists
    $checkResult = callSupabase('/rest/v1/fuelguard_users?email=eq.' . urlencode($email), 'GET');
    
    if (!empty($checkResult['data']) && count($checkResult['data']) > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }
    
    // Insert user
    $userData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'password_hash' => $passwordHash,
        'is_active' => true,
        'is_verified' => true,
        'user_role' => 'user'
    ];
    
    $result = callSupabase('/rest/v1/fuelguard_users', 'POST', $userData);
    
    if ($result['status'] === 201) {
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Please sign in.',
            'email' => $email
        ]);
    } else {
        $errorMsg = $result['data']['message'] ?? 'Registration failed';
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>