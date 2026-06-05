<?php
/**
 * FuelGuard Pro - Driver Management API
 * Complete CRUD Operations for Drivers with Invitation System
 */

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/mail_sender.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class SupabaseAPI {
    private $url;
    private $key;
    
    public function __construct() {
        $this->url = SUPABASE_URL;
        $this->key = SUPABASE_KEY;
    }
    
    private function request($method, $endpoint, $data = null) {
        $url = $this->url . '/rest/v1/' . $endpoint;
        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
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
        curl_close($ch);
        
        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }
    
    public function get($table, $params = []) {
        $query = http_build_query($params);
        $endpoint = $table . ($query ? '?' . $query : '');
        return $this->request('GET', $endpoint);
    }
    
    public function insert($table, $data) {
        return $this->request('POST', $table, $data);
    }
    
    public function update($table, $id, $data, $idColumn = 'driver_id') {
        $endpoint = $table . '?' . $idColumn . '=eq.' . urlencode($id);
        return $this->request('PATCH', $endpoint, $data);
    }
    
    public function delete($table, $id, $idColumn = 'driver_id') {
        $endpoint = $table . '?' . $idColumn . '=eq.' . urlencode($id);
        return $this->request('DELETE', $endpoint);
    }
}

class DriverManager {
    private $supabase;
    
    public function __construct() {
        $this->supabase = new SupabaseAPI();
    }
    
    public function getAllDrivers() {
        $result = $this->supabase->get('drivers', ['order' => 'created_at.desc']);
        return $result;
    }
    
    public function getDriver($driverId) {
        $result = $this->supabase->get('drivers', ['driver_id' => 'eq.' . $driverId]);
        if ($result['status'] === 200 && !empty($result['data'])) {
            $result['data'] = $result['data'][0];
        }
        return $result;
    }
    
    public function createDriver($data) {
        if (empty($data['driver_id'])) {
            $data['driver_id'] = $this->generateDriverId();
        }
        
        $existing = $this->getDriver($data['driver_id']);
        if ($existing['status'] === 200 && !empty($existing['data'])) {
            return ['status' => 409, 'error' => 'Driver ID already exists'];
        }
        
        $driverData = [
            'driver_id' => $data['driver_id'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
            'license_number' => $data['license_number'],
            'license_class' => $data['license_class'],
            'license_expiry' => $data['license_expiry'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'hire_date' => date('Y-m-d'),
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'shift' => $data['shift'] ?? 'morning',
            'medical_certificate_expiry' => $data['medical_certificate_expiry'] ?? null,
            'status' => $data['status'] ?? 'pending_invite',
            'defensive_driving_certified' => $data['defensive_driving_certified'] ?? false,
            'hazmat_certified' => $data['hazmat_certified'] ?? false,
            'tanker_certified' => $data['tanker_certified'] ?? false,
            'is_available' => true,
            'safety_score' => 100,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->supabase->insert('drivers', $driverData);
        
        if ($result['status'] === 200 || $result['status'] === 201) {
            return ['status' => 201, 'message' => 'Driver created successfully', 'driver_id' => $data['driver_id']];
        }
        return $result;
    }
    
    public function createInvitation($data) {
        $driver_id = $data['driver_id'];
        $token = $data['token'];
        $email = $data['email'];
        $full_name = $data['full_name'];
        $register_link = $data['register_link'];
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Check if token already exists and delete it
        $existing = $this->supabase->get('invitation_tokens', ['driver_id' => 'eq.' . $driver_id]);
        if ($existing['status'] === 200 && !empty($existing['data'])) {
            $this->supabase->delete('invitation_tokens', $driver_id, 'driver_id');
        }
        
        // Store token in Supabase invitation_tokens table
        $tokenData = [
            'driver_id' => $driver_id,
            'token' => $token,
            'email' => $email,
            'full_name' => $full_name,
            'register_link' => $register_link,
            'expires_at' => $expires_at,
            'is_used' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->supabase->insert('invitation_tokens', $tokenData);
        
        if ($result['status'] !== 200 && $result['status'] !== 201) {
            return ['status' => 500, 'error' => 'Failed to create invitation token'];
        }
        
        // Send email using the email API
        $email_sent = $this->sendInvitationEmail($email, $full_name, $register_link);
        
        if ($email_sent) {
            return ['status' => 200, 'message' => 'Invitation created and email sent successfully to ' . $email];
        } else {
            return ['status' => 200, 'message' => 'Invitation created but email sending failed. Please resend invitation.'];
        }
    }
    
    public function validateInvitation($token, $driver_id) {
        // Check if invitation exists and is not expired and not used
        $result = $this->supabase->get('invitation_tokens', [
            'driver_id' => 'eq.' . $driver_id,
            'token' => 'eq.' . $token,
            'is_used' => 'eq.false'
        ]);
        
        if ($result['status'] !== 200 || empty($result['data'])) {
            // Check if token exists but is used
            $usedResult = $this->supabase->get('invitation_tokens', [
                'driver_id' => 'eq.' . $driver_id,
                'token' => 'eq.' . $token,
                'is_used' => 'eq.true'
            ]);
            
            if ($usedResult['status'] === 200 && !empty($usedResult['data'])) {
                return ['status' => 410, 'error' => 'Invitation already used'];
            }
            
            return ['status' => 410, 'error' => 'Invitation expired or invalid'];
        }
        
        $invitation = $result['data'][0];
        
        // Check if expired
        $expires_at = strtotime($invitation['expires_at']);
        $now = time();
        
        if ($expires_at < $now) {
            return ['status' => 410, 'error' => 'Invitation link has expired'];
        }
        
        // Get driver info
        $driverResult = $this->getDriver($driver_id);
        if ($driverResult['status'] !== 200 || empty($driverResult['data'])) {
            return ['status' => 404, 'error' => 'Driver not found'];
        }
        
        $driver = $driverResult['data'];
        
        return [
            'status' => 200, 
            'data' => [
                'driver_id' => $driver['driver_id'],
                'full_name' => $driver['full_name'],
                'email' => $driver['email'],
                'token' => $token
            ]
        ];
    }
    
    public function completeRegistration($data) {
        $driver_id = $data['driver_id'];
        $token = $data['token'];
        $password = $data['password'];
        
        // Verify invitation token
        $result = $this->supabase->get('invitation_tokens', [
            'driver_id' => 'eq.' . $driver_id,
            'token' => 'eq.' . $token,
            'is_used' => 'eq.false'
        ]);
        
        if ($result['status'] !== 200 || empty($result['data'])) {
            return ['status' => 410, 'error' => 'Invalid or expired invitation'];
        }
        
        $invitation = $result['data'][0];
        
        // Check if expired
        $expires_at = strtotime($invitation['expires_at']);
        $now = time();
        
        if ($expires_at < $now) {
            return ['status' => 410, 'error' => 'Invitation link has expired'];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update driver with password and status
        $updateData = [
            'password_hash' => $hashed_password,
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $updateResult = $this->supabase->update('drivers', $driver_id, $updateData);
        
        if ($updateResult['status'] !== 200) {
            return ['status' => 500, 'error' => 'Failed to update driver account'];
        }
        
        // Mark token as used
        $tokenId = $invitation['id'];
        $this->supabase->update('invitation_tokens', $tokenId, ['is_used' => true], 'id');
        
        return ['status' => 200, 'message' => 'Registration completed successfully'];
    }
    
    private function sendInvitationEmail($to, $full_name, $register_link) {
        // Direct call to the function in mail_sender.php
        $result = sendInvitationEmail($to, $full_name, $register_link);
        return $result['success'];
    }
    
    public function resendInvitation($data) {
        $driver_id = $data['driver_id'];
        $token = $data['token'];
        $email = $data['email'];
        $full_name = $data['full_name'];
        $register_link = $data['register_link'];
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Delete old tokens for this driver
        $this->supabase->delete('invitation_tokens', $driver_id, 'driver_id');
        
        // Create new token
        $tokenData = [
            'driver_id' => $driver_id,
            'token' => $token,
            'email' => $email,
            'full_name' => $full_name,
            'register_link' => $register_link,
            'expires_at' => $expires_at,
            'is_used' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->supabase->insert('invitation_tokens', $tokenData);
        
        if ($result['status'] !== 200 && $result['status'] !== 201) {
            return ['status' => 500, 'error' => 'Failed to create invitation token'];
        }
        
        // Send email
        $email_sent = $this->sendInvitationEmail($email, $full_name, $register_link);
        
        if ($email_sent) {
            return ['status' => 200, 'message' => 'Invitation resent successfully to ' . $email];
        } else {
            return ['status' => 500, 'error' => 'Failed to send email. Please check email configuration.'];
        }
    }
    
    private function generateDriverId() {
        $timestamp = substr(time(), -6);
        $random = rand(100, 999);
        return 'DRV' . $timestamp . $random;
    }
    
    public function updateDriver($driverId, $data) {
        $updateData = [];
        $allowedFields = ['full_name', 'email', 'phone', 'address', 'license_number', 'license_class', 
                          'license_expiry', 'date_of_birth', 'emergency_contact_name', 'emergency_contact_phone',
                          'shift', 'medical_certificate_expiry', 'status', 
                          'defensive_driving_certified', 'hazmat_certified', 'tanker_certified', 'is_available'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            return ['status' => 400, 'error' => 'No fields to update'];
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $result = $this->supabase->update('drivers', $driverId, $updateData);
        
        return ['status' => 200, 'message' => 'Driver updated successfully'];
    }
    
    public function deleteDriver($driverId) {
        $this->supabase->delete('invitation_tokens', $driverId, 'driver_id');
        $result = $this->supabase->delete('drivers', $driverId);
        return ['status' => 200, 'message' => 'Driver deleted successfully'];
    }
    
    public function getDriverTrips($driverId) {
        $result = $this->supabase->get('driver_trips', [
            'driver_id' => 'eq.' . $driverId,
            'order' => 'trip_date.desc',
            'limit' => 50
        ]);
        return $result;
    }
    
    public function exportDrivers() {
        $drivers = $this->getAllDrivers();
        if ($drivers['status'] !== 200) {
            http_response_code(500);
            echo json_encode($drivers);
            exit();
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="drivers_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Driver ID', 'Full Name', 'Email', 'Phone', 'License Number', 'License Class', 
                          'License Expiry', 'Status', 'Safety Score', 'Total Trips', 'Total Distance (km)']);
        
        foreach ($drivers['data'] as $driver) {
            fputcsv($output, [
                $driver['driver_id'],
                $driver['full_name'],
                $driver['email'],
                $driver['phone'],
                $driver['license_number'],
                $driver['license_class'],
                $driver['license_expiry'],
                $driver['status'],
                $driver['safety_score'] ?? 100,
                $driver['total_trips'] ?? 0,
                $driver['total_distance_km'] ?? 0
            ]);
        }
        
        fclose($output);
        exit();
    }
}

$manager = new DriverManager();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$response = [];

switch ($action) {
    case 'getAll':
        $response = $manager->getAllDrivers();
        break;
    case 'get':
        $id = $_GET['id'] ?? null;
        $response = $id ? $manager->getDriver($id) : ['status' => 400, 'error' => 'Driver ID required'];
        break;
    case 'create':
        $response = $manager->createDriver($input);
        break;
    case 'createInvitation':
        $response = $manager->createInvitation($input);
        break;
    case 'resendInvitation':
        $response = $manager->resendInvitation($input);
        break;
    case 'validateInvitation':
        $token = $_GET['token'] ?? '';
        $driver_id = $_GET['driver_id'] ?? '';
        if (!$token || !$driver_id) {
            $response = ['status' => 400, 'error' => 'Missing required parameters'];
        } else {
            $response = $manager->validateInvitation($token, $driver_id);
        }
        break;
    case 'completeRegistration':
        $response = $manager->completeRegistration($input);
        break;
    case 'update':
        $id = $_GET['id'] ?? null;
        $response = $id ? $manager->updateDriver($id, $input) : ['status' => 400, 'error' => 'Driver ID required'];
        break;
    case 'delete':
        $id = $_GET['id'] ?? null;
        $response = $id ? $manager->deleteDriver($id) : ['status' => 400, 'error' => 'Driver ID required'];
        break;
    case 'getTrips':
        $id = $_GET['id'] ?? null;
        $response = $id ? $manager->getDriverTrips($id) : ['status' => 400, 'error' => 'Driver ID required'];
        break;
    case 'export':
        $manager->exportDrivers();
        exit();
    default:
        $response = ['status' => 400, 'error' => 'Invalid action'];
}

http_response_code($response['status'] ?? 200);
echo json_encode($response);
?>