
<?php
// Bulletproof CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';


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
    
    // Rigorous Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address format']);
        exit;
    }

    if (!preg_match("/^[a-zA-Z\s\-]{2,50}$/", $firstName) || !preg_match("/^[a-zA-Z\s\-]{2,50}$/", $lastName)) {
        echo json_encode(['success' => false, 'message' => 'Names must be 2-50 characters and contain only letters, spaces, or hyphens']);
        exit;
    }

    if (!empty($phone) && !preg_match("/^[0-9\+\s\-]{9,15}$/", $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
        exit;
    }
    
    if (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long and include one uppercase letter and one number']);
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