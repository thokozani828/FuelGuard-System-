<?php
/**
 * FleetGuard Pro - Settings Management API
 * Manages fuelguard_users table for profile and settings
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config.php';

// Table name constant
define('USERS_TABLE', 'fuelguard_users');

// Simple CURL wrapper for Supabase
function supabaseRequest($method, $table, $params = [], $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $table;
    
    if (!empty($params)) {
        $query = http_build_query($params);
        $url .= '?' . $query;
    }
    
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['status' => 500, 'error' => 'CURL Error: ' . $curlError];
    }
    
    $decoded = json_decode($response, true);
    
    return [
        'status' => $httpCode,
        'data' => $decoded
    ];
}

// Get user by ID or username from fuelguard_users
function getUser($identifier) {
    $result = supabaseRequest('GET', USERS_TABLE, [
        'or' => '(user_id.eq.' . $identifier . ',username.eq.' . $identifier . ')',
        'select' => '*'
    ]);
    
    if ($result['status'] === 200 && !empty($result['data'])) {
        $user = $result['data'][0];
        // Remove sensitive data
        unset($user['password_hash']);
        return ['status' => 200, 'data' => $user];
    }
    return ['status' => 404, 'error' => 'User not found'];
}

// Get all users (admin only)
function getAllUsers($filters = []) {
    $params = ['select' => '*', 'order' => 'created_at.desc'];
    
    if (isset($filters['role']) && !empty($filters['role'])) {
        $params['role'] = 'eq.' . $filters['role'];
    }
    if (isset($filters['status']) && !empty($filters['status'])) {
        $params['status'] = 'eq.' . $filters['status'];
    }
    
    $result = supabaseRequest('GET', USERS_TABLE, $params);
    
    if ($result['status'] === 200 && !empty($result['data'])) {
        foreach ($result['data'] as &$user) {
            unset($user['password_hash']);
        }
    }
    
    return $result;
}

// Update user profile
function updateUserProfile($userId, $data) {
    try {
        $updateData = [];
        
        if (isset($data['full_name'])) $updateData['full_name'] = $data['full_name'];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['phone'])) $updateData['phone'] = $data['phone'];
        if (isset($data['username'])) $updateData['username'] = $data['username'];
        if (isset($data['theme_preference'])) $updateData['theme_preference'] = $data['theme_preference'];
        if (isset($data['notification_enabled'])) $updateData['notification_enabled'] = $data['notification_enabled'] === true || $data['notification_enabled'] === 'true';
        if (isset($data['language'])) $updateData['language'] = $data['language'];
        if (isset($data['timezone'])) $updateData['timezone'] = $data['timezone'];
        
        if (isset($data['password']) && !empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        if (empty($updateData)) {
            return ['status' => 400, 'error' => 'No fields to update'];
        }
        
        $result = supabaseRequest('PATCH', USERS_TABLE, ['user_id' => 'eq.' . $userId], $updateData);
        
        // Get updated user
        $user = getUser($userId);
        
        return ['status' => 200, 'message' => 'Profile updated successfully', 'data' => $user['data']];
    } catch (Exception $e) {
        return ['status' => 500, 'error' => 'Update failed: ' . $e->getMessage()];
    }
}

// Create new user (admin only)
function createUser($data) {
    try {
        if (empty($data['full_name'])) return ['status' => 400, 'error' => 'Full name required'];
        if (empty($data['email'])) return ['status' => 400, 'error' => 'Email required'];
        if (empty($data['username'])) return ['status' => 400, 'error' => 'Username required'];
        if (empty($data['password'])) return ['status' => 400, 'error' => 'Password required'];
        
        // Check if user exists
        $existing = getUser($data['username']);
        if ($existing['status'] === 200) {
            return ['status' => 409, 'error' => 'Username already exists'];
        }
        
        // Check email uniqueness
        $emailCheck = supabaseRequest('GET', USERS_TABLE, ['email' => 'eq.' . $data['email']]);
        if ($emailCheck['status'] === 200 && !empty($emailCheck['data'])) {
            return ['status' => 409, 'error' => 'Email already exists'];
        }
        
        $userData = [
            'user_id' => $data['username'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'username' => $data['username'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $data['role'] ?? 'viewer',
            'status' => $data['status'] ?? 'pending',
            'theme_preference' => $data['theme_preference'] ?? 'light',
            'notification_enabled' => true,
            'language' => 'en',
            'timezone' => 'Africa/Johannesburg',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = supabaseRequest('POST', USERS_TABLE, [], $userData);
        
        if ($result['status'] === 200 || $result['status'] === 201) {
            unset($userData['password_hash']);
            return ['status' => 201, 'message' => 'User created successfully', 'data' => $userData];
        }
        return $result;
    } catch (Exception $e) {
        return ['status' => 500, 'error' => 'Create failed: ' . $e->getMessage()];
    }
}

// Delete user (admin only)
function deleteUser($userId) {
    try {
        if ($userId === 'admin') {
            return ['status' => 403, 'error' => 'Cannot delete the main admin user'];
        }
        
        $result = supabaseRequest('DELETE', USERS_TABLE, ['user_id' => 'eq.' . $userId]);
        return ['status' => 200, 'message' => 'User deleted successfully'];
    } catch (Exception $e) {
        return ['status' => 500, 'error' => 'Delete failed: ' . $e->getMessage()];
    }
}

// Update user role/status (admin only)
function updateUserAdmin($userId, $data) {
    try {
        $updateData = [];
        if (isset($data['role'])) $updateData['role'] = $data['role'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        
        if (empty($updateData)) {
            return ['status' => 400, 'error' => 'No fields to update'];
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $result = supabaseRequest('PATCH', USERS_TABLE, ['user_id' => 'eq.' . $userId], $updateData);
        
        return ['status' => 200, 'message' => 'User updated successfully'];
    } catch (Exception $e) {
        return ['status' => 500, 'error' => 'Update failed: ' . $e->getMessage()];
    }
}

// Main routing
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$response = [];

// For demo, using 'admin' as current user - in production, use session
$currentUser = $_GET['user'] ?? 'admin';

try {
    switch ($action) {
        case 'getProfile':
            $response = getUser($currentUser);
            break;
            
        case 'getAll':
            $filters = [
                'role' => $_GET['role'] ?? null,
                'status' => $_GET['status'] ?? null
            ];
            $response = getAllUsers($filters);
            break;
            
        case 'updateProfile':
            if ($method !== 'POST') {
                $response = ['status' => 405, 'error' => 'Method not allowed'];
            } else {
                $response = updateUserProfile($currentUser, $input);
            }
            break;
            
        case 'updateUser':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                $response = ['status' => 400, 'error' => 'User ID required'];
            } else {
                $response = updateUserAdmin($id, $input);
            }
            break;
            
        case 'createUser':
            if ($method !== 'POST') {
                $response = ['status' => 405, 'error' => 'Method not allowed'];
            } else {
                $response = createUser($input);
            }
            break;
            
        case 'deleteUser':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                $response = ['status' => 400, 'error' => 'User ID required'];
            } else {
                $response = deleteUser($id);
            }
            break;
            
        case 'test':
            $response = [
                'status' => 200,
                'message' => 'Settings API is working',
                'timestamp' => date('Y-m-d H:i:s'),
                'table' => USERS_TABLE,
                'supabase_url' => SUPABASE_URL
            ];
            break;
            
        default:
            $response = ['status' => 400, 'error' => 'Invalid action. Available: getProfile, getAll, updateProfile, updateUser, createUser, deleteUser, test'];
    }
} catch (Exception $e) {
    $response = ['status' => 500, 'error' => 'Server error: ' . $e->getMessage()];
}

http_response_code($response['status'] ?? 200);
echo json_encode($response);
?>