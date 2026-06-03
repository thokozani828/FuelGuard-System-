<?php
// api/create_invitation.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Supabase configuration
define('SUPABASE_URL', 'https://your-project-id.supabase.co');
define('SUPABASE_KEY', 'your-supabase-anon-key');
define('NETLIFY_URL', 'https://stellular-buttercream-cafeaa.netlify.app');

function callSupabase($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . $endpoint;
    $ch = curl_init($url);
    
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json'
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return json_decode($response, true);
}

function generateUniqueToken() {
    return bin2hex(random_bytes(32));
}

function createInvitation($driverData) {
    // Generate unique token
    $token = generateUniqueToken();
    
    // Create register link
    $register_link = NETLIFY_URL . "/create-password.html?token=" . $token;
    
    // Insert into driver_invitations table
    $inviteData = [
        'driver_id' => $driverData['driver_id'],
        'token' => $token,
        'email' => $driverData['email'],
        'full_name' => $driverData['full_name'],
        'register_link' => $register_link,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        'is_used' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $result = callSupabase('/rest/v1/driver_invitations', 'POST', $inviteData);
    
    if (isset($result['id'])) {
        return ['success' => true, 'token' => $token, 'register_link' => $register_link];
    } else {
        return ['success' => false, 'error' => $result];
    }
}

// Handle the request
$data = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

if ($action === 'createAndSendInvitation') {
    $driver_id = $data['driver_id'] ?? '';
    $full_name = $data['full_name'] ?? '';
    $email = $data['email'] ?? '';
    
    if (!$driver_id || !$full_name || !$email) {
        echo json_encode(['status' => 400, 'error' => 'Missing required fields: driver_id, full_name, email']);
        exit();
    }
    
    // Create invitation in database
    $invitation = createInvitation([
        'driver_id' => $driver_id,
        'full_name' => $full_name,
        'email' => $email
    ]);
    
    if (!$invitation['success']) {
        echo json_encode(['status' => 500, 'error' => 'Failed to create invitation: ' . json_encode($invitation['error'])]);
        exit();
    }
    
    // Send email using your existing send_email.php
    $emailData = [
        'email' => $email,
        'full_name' => $full_name,
        'register_link' => $invitation['register_link']
    ];
    
    $ch = curl_init('http://localhost/api/send_email.php?action=sendInvitation');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $emailResult = curl_exec($ch);
    $emailHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($emailHttpCode === 200) {
        echo json_encode([
            'status' => 200,
            'message' => 'Invitation created and email sent successfully',
            'token' => $invitation['token'],
            'invite_link' => $invitation['register_link']
        ]);
    } else {
        echo json_encode([
            'status' => 500,
            'error' => 'Invitation created but email failed to send',
            'invite_link' => $invitation['register_link']
        ]);
    }
} else {
    echo json_encode(['status' => 400, 'error' => 'Invalid action. Use action=createAndSendInvitation']);
}
?>