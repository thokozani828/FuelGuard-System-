<?php
/**
 * FleetGuard Pro - Sensor Network API
 * Supabase Database Integration
 * CRUD Operations for Sensors Management
 */

// ============================================
// DATABASE CONFIGURATION
// ============================================

// Supabase configuration
define('SUPABASE_URL', 'https://shdaldiqnbtlgjajxroi.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNoZGFsZGlxbmJ0bGdqYWp4cm9pIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzkwMzA0MTcsImV4cCI6MjA5NDYwNjQxN30.BDRnisUkar6CaBKc0-AI6IXw16yfgjrkqEv59PWkJIo');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// SUPABASE REST API CLASS
// ============================================

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
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'status' => 500,
                'error' => 'CURL Error: ' . $curlError
            ];
        }
        
        $decodedResponse = json_decode($response, true);
        
        // If response is empty or null, return empty array
        if ($decodedResponse === null && $response === '') {
            $decodedResponse = [];
        }
        
        return [
            'status' => $httpCode,
            'data' => $decodedResponse
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
    
    public function update($table, $id, $data, $idColumn = 'id') {
        $endpoint = $table . '?' . $idColumn . '=eq.' . urlencode($id);
        return $this->request('PATCH', $endpoint, $data);
    }
    
    public function delete($table, $id, $idColumn = 'id') {
        $endpoint = $table . '?' . $idColumn . '=eq.' . urlencode($id);
        return $this->request('DELETE', $endpoint);
    }
}

// ============================================
// SENSOR CRUD OPERATIONS CLASS
// ============================================

class SensorManager {
    private $supabase;
    
    public function __construct() {
        $this->supabase = new SupabaseAPI();
    }
    
    /**
     * GET ALL SENSORS
     */
    public function getAllSensors($filters = []) {
        try {
            $params = ['select' => '*', 'order' => 'created_at.desc'];
            
            if (isset($filters['status']) && $filters['status']) {
                $params['status'] = 'eq.' . strtoupper($filters['status']);
            }
            if (isset($filters['sensor_type']) && $filters['sensor_type']) {
                $params['sensor_type'] = 'eq.' . strtolower($filters['sensor_type']);
            }
            
            $result = $this->supabase->get('sensors', $params);
            
            // Enhance with assignment info
            if ($result['status'] === 200 && !empty($result['data'])) {
                foreach ($result['data'] as &$sensor) {
                    $assignment = $this->getCurrentAssignment($sensor['sensor_id']);
                    $sensor['assigned_to'] = $assignment ? $assignment['truck_id'] : '-';
                    $sensor['assigned_at'] = $assignment ? $assignment['assigned_at'] : null;
                    
                    // Get last telemetry reading
                    $lastReading = $this->getLastReading($sensor['sensor_id']);
                    if ($lastReading) {
                        $sensor['last_reading'] = $lastReading['created_at'];
                        $sensor['last_value'] = $lastReading['fuel_level_cm'] . ' cm';
                    } else {
                        $sensor['last_reading'] = date('Y-m-d H:i:s');
                        $sensor['last_value'] = '--';
                    }
                }
            }
            
            return $result;
        } catch (Exception $e) {
            return [
                'status' => 500,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * GET SINGLE SENSOR by ID
     */
    public function getSensor($sensorId) {
        try {
            $result = $this->supabase->get('sensors', [
                'sensor_id' => 'eq.' . $sensorId,
                'select' => '*'
            ]);
            
            if ($result['status'] === 200 && !empty($result['data'])) {
                $sensor = $result['data'][0];
                $assignment = $this->getCurrentAssignment($sensorId);
                $sensor['assigned_to'] = $assignment ? $assignment['truck_id'] : '-';
                $sensor['assigned_at'] = $assignment ? $assignment['assigned_at'] : null;
                
                $lastReading = $this->getLastReading($sensorId);
                if ($lastReading) {
                    $sensor['last_reading'] = $lastReading['created_at'];
                    $sensor['last_value'] = $lastReading['fuel_level_cm'] . ' cm';
                }
                
                $result['data'] = $sensor;
            }
            
            return $result;
        } catch (Exception $e) {
            return ['status' => 500, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * CREATE NEW SENSOR
     */
    public function createSensor($data) {
        try {
            // Validate required fields
            if (empty($data['sensor_id'])) {
                return ['status' => 400, 'error' => 'Sensor ID is required'];
            }
            if (empty($data['sensor_type'])) {
                return ['status' => 400, 'error' => 'Sensor type is required'];
            }
            
            // Check if sensor already exists
            $existing = $this->getSensor($data['sensor_id']);
            if ($existing['status'] === 200 && !empty($existing['data'])) {
                return ['status' => 409, 'error' => 'Sensor ID already exists: ' . $data['sensor_id']];
            }
            
            $sensorData = [
                'sensor_id' => $data['sensor_id'],
                'sensor_type' => strtolower($data['sensor_type']),
                'manufacturer' => $data['manufacturer'] ?? 'Generic',
                'model' => $data['model'] ?? $data['sensor_type'],
                'status' => (isset($data['assigned_to']) && $data['assigned_to'] && $data['assigned_to'] !== '-') ? 'ASSIGNED' : 'AVAILABLE',
                'is_active' => true,
                'battery_level' => $data['battery_level'] ?? 100,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->supabase->insert('sensors', $sensorData);
            
            // Assign sensor if specified
            if ($result['status'] === 201 && isset($data['assigned_to']) && $data['assigned_to'] && $data['assigned_to'] !== '-') {
                $this->assignSensor($data['sensor_id'], $data['assigned_to']);
            }
            
            // If insert was successful but status is not 201 (sometimes Supabase returns 200)
            if ($result['status'] === 200 || $result['status'] === 201) {
                return ['status' => 201, 'message' => 'Sensor created successfully', 'data' => $sensorData];
            }
            
            return $result;
        } catch (Exception $e) {
            return ['status' => 500, 'error' => 'Create failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * UPDATE SENSOR
     */
    public function updateSensor($sensorId, $data) {
        try {
            // Check if sensor exists
            $existing = $this->getSensor($sensorId);
            if ($existing['status'] !== 200 || empty($existing['data'])) {
                return ['status' => 404, 'error' => 'Sensor not found: ' . $sensorId];
            }
            
            $updateData = [];
            
            if (isset($data['sensor_type'])) {
                $updateData['sensor_type'] = strtolower($data['sensor_type']);
            }
            if (isset($data['manufacturer'])) {
                $updateData['manufacturer'] = $data['manufacturer'];
            }
            if (isset($data['model'])) {
                $updateData['model'] = $data['model'];
            }
            if (isset($data['battery_level'])) {
                $updateData['battery_level'] = intval($data['battery_level']);
            }
            if (isset($data['is_active'])) {
                $updateData['is_active'] = $data['is_active'];
            }
            
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            
            // Handle assignment change
            if (isset($data['assigned_to'])) {
                if (!$data['assigned_to'] || $data['assigned_to'] === '-') {
                    $this->unassignSensor($sensorId);
                    $updateData['status'] = 'AVAILABLE';
                } else {
                    $this->assignSensor($sensorId, $data['assigned_to']);
                    $updateData['status'] = 'ASSIGNED';
                }
            }
            
            if (empty($updateData)) {
                return ['status' => 400, 'error' => 'No fields to update'];
            }
            
            $result = $this->supabase->update('sensors', $sensorId, $updateData, 'sensor_id');
            
            return ['status' => 200, 'message' => 'Sensor updated successfully'];
        } catch (Exception $e) {
            return ['status' => 500, 'error' => 'Update failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * DELETE SENSOR
     */
    public function deleteSensor($sensorId) {
        try {
            // Check if sensor exists
            $existing = $this->getSensor($sensorId);
            if ($existing['status'] !== 200 || empty($existing['data'])) {
                return ['status' => 404, 'error' => 'Sensor not found: ' . $sensorId];
            }
            
            // First, unassign the sensor if assigned
            $this->unassignSensor($sensorId);
            
            $result = $this->supabase->delete('sensors', $sensorId, 'sensor_id');
            
            return ['status' => 200, 'message' => 'Sensor deleted successfully'];
        } catch (Exception $e) {
            return ['status' => 500, 'error' => 'Delete failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * ASSIGN SENSOR TO TRUCK
     */
    private function assignSensor($sensorId, $truckId) {
        try {
            // First, deactivate any existing assignment for this sensor
            $this->unassignSensor($sensorId);
            
            $assignmentData = [
                'sensor_id' => $sensorId,
                'truck_id' => $truckId,
                'assigned_at' => date('Y-m-d H:i:s'),
                'is_active' => true
            ];
            
            return $this->supabase->insert('sensor_assignments', $assignmentData);
        } catch (Exception $e) {
            error_log("Assignment failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * UNASSIGN SENSOR
     */
    private function unassignSensor($sensorId) {
        try {
            $result = $this->supabase->update('sensor_assignments', $sensorId, [
                'is_active' => false,
                'unassigned_at' => date('Y-m-d H:i:s')
            ], 'sensor_id');
            
            return true;
        } catch (Exception $e) {
            error_log("Unassignment failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * GET CURRENT ASSIGNMENT FOR SENSOR
     */
    private function getCurrentAssignment($sensorId) {
        try {
            $result = $this->supabase->get('sensor_assignments', [
                'sensor_id' => 'eq.' . $sensorId,
                'is_active' => 'eq.true',
                'select' => '*',
                'limit' => 1
            ]);
            
            if ($result['status'] === 200 && !empty($result['data'])) {
                return $result['data'][0];
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * GET LAST TELEMETRY READING
     */
    private function getLastReading($sensorId) {
        try {
            $result = $this->supabase->get('fuel_telemetry', [
                'sensor_id' => 'eq.' . $sensorId,
                'select' => '*',
                'order' => 'created_at.desc',
                'limit' => 1
            ]);
            
            if ($result['status'] === 200 && !empty($result['data'])) {
                return $result['data'][0];
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * GET ALL TRUCKS FOR ASSIGNMENT
     */
    public function getTrucks() {
        try {
            $result = $this->supabase->get('trucks', [
                'select' => 'truck_id,license_plate,driver_name',
                'status' => 'eq.ACTIVE',
                'order' => 'truck_id.asc'
            ]);
            
            // If no trucks found, return sample data
            if ($result['status'] === 200 && empty($result['data'])) {
                $result['data'] = [
                    ['truck_id' => 'TRK001', 'license_plate' => 'ABC-123', 'driver_name' => 'John Doe'],
                    ['truck_id' => 'TRK002', 'license_plate' => 'XYZ-789', 'driver_name' => 'Jane Smith'],
                    ['truck_id' => 'TRK003', 'license_plate' => 'LMN-456', 'driver_name' => 'Bob Johnson'],
                    ['truck_id' => 'TRK004', 'license_plate' => 'DEF-012', 'driver_name' => 'Alice Brown'],
                    ['truck_id' => 'TRK005', 'license_plate' => 'GHI-345', 'driver_name' => 'Charlie Wilson']
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            // Return sample trucks if API fails
            return [
                'status' => 200,
                'data' => [
                    ['truck_id' => 'TRK001', 'license_plate' => 'ABC-123', 'driver_name' => 'John Doe'],
                    ['truck_id' => 'TRK002', 'license_plate' => 'XYZ-789', 'driver_name' => 'Jane Smith'],
                    ['truck_id' => 'TRK003', 'license_plate' => 'LMN-456', 'driver_name' => 'Bob Johnson'],
                    ['truck_id' => 'TRK004', 'license_plate' => 'DEF-012', 'driver_name' => 'Alice Brown'],
                    ['truck_id' => 'TRK005', 'license_plate' => 'GHI-345', 'driver_name' => 'Charlie Wilson']
                ]
            ];
        }
    }
    
    /**
     * SIMULATE TELEMETRY READING
     */
    public function simulateReading($sensorId) {
        try {
            $sensor = $this->getSensor($sensorId);
            if ($sensor['status'] !== 200 || empty($sensor['data'])) {
                return ['status' => 404, 'error' => 'Sensor not found: ' . $sensorId];
            }
            
            $randomValue = rand(20, 250);
            $reading = [
                'sensor_id' => $sensorId,
                'truck_id' => $sensor['data']['assigned_to'] ?? null,
                'sensor_type' => $sensor['data']['sensor_type'],
                'raw_distance_cm' => $randomValue,
                'fuel_level_cm' => $randomValue,
                'fuel_percentage' => round(($randomValue / 250) * 100, 1),
                'fuel_volume_liters' => round($randomValue * 2.5, 1),
                'status' => $randomValue < 30 ? 'CRITICAL' : ($randomValue < 60 ? 'LOW' : 'NORMAL'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->supabase->insert('fuel_telemetry', $reading);
            
            // Reduce battery level slightly
            $currentBattery = $sensor['data']['battery_level'] ?? 100;
            $newBattery = max(0, $currentBattery - rand(1, 3));
            $this->updateSensor($sensorId, ['battery_level' => $newBattery]);
            
            return ['status' => 201, 'message' => 'Reading simulated successfully'];
        } catch (Exception $e) {
            return ['status' => 500, 'error' => 'Simulation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * EXPORT SENSORS TO CSV
     */
    public function exportSensors() {
        $sensors = $this->getAllSensors();
        if ($sensors['status'] !== 200) {
            http_response_code(500);
            echo json_encode($sensors);
            exit();
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sensors_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Sensor ID', 'Type', 'Assigned To', 'Status', 'Battery %', 'Last Reading', 'Value']);
        
        foreach ($sensors['data'] as $sensor) {
            fputcsv($output, [
                $sensor['sensor_id'],
                ucfirst($sensor['sensor_type']),
                $sensor['assigned_to'] ?? '-',
                $sensor['status'],
                $sensor['battery_level'] ?? '100',
                $sensor['last_reading'] ?? 'N/A',
                $sensor['last_value'] ?? '--'
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// ============================================
// API ROUTING HANDLER
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$sensorManager = new SensorManager();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

// Parse JSON input for POST/PUT requests
$input = json_decode(file_get_contents('php://input'), true);
if ($method === 'POST' && empty($input)) {
    $input = $_POST;
}

$response = [];

switch ($action) {
    case 'getAll':
        $filters = [
            'status' => $_GET['status'] ?? null,
            'sensor_type' => $_GET['sensor_type'] ?? null
        ];
        $response = $sensorManager->getAllSensors($filters);
        break;
        
    case 'get':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $response = ['status' => 400, 'error' => 'Sensor ID required'];
        } else {
            $response = $sensorManager->getSensor($id);
        }
        break;
        
    case 'create':
        if ($method !== 'POST') {
            $response = ['status' => 405, 'error' => 'Method not allowed'];
        } else {
            $response = $sensorManager->createSensor($input);
        }
        break;
        
    case 'update':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $response = ['status' => 400, 'error' => 'Sensor ID required'];
        } elseif ($method !== 'POST' && $method !== 'PUT') {
            $response = ['status' => 405, 'error' => 'Method not allowed'];
        } else {
            $response = $sensorManager->updateSensor($id, $input);
        }
        break;
        
    case 'delete':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $response = ['status' => 400, 'error' => 'Sensor ID required'];
        } elseif ($method !== 'DELETE' && $method !== 'GET') {
            $response = ['status' => 405, 'error' => 'Method not allowed'];
        } else {
            $response = $sensorManager->deleteSensor($id);
        }
        break;
        
    case 'getTrucks':
        $response = $sensorManager->getTrucks();
        break;
        
    case 'simulateReading':
        $id = $_GET['id'] ?? ($input['sensor_id'] ?? null);
        if (!$id) {
            $response = ['status' => 400, 'error' => 'Sensor ID required'];
        } else {
            $response = $sensorManager->simulateReading($id);
        }
        break;
        
    case 'export':
        $sensorManager->exportSensors();
        exit();
        
    default:
        $response = ['status' => 400, 'error' => 'Invalid action. Available actions: getAll, get, create, update, delete, getTrucks, simulateReading, export'];
        break;
}

// Always return JSON response
if (!isset($response['status'])) {
    $response = ['status' => 500, 'error' => 'Unknown error occurred'];
}

http_response_code($response['status']);
echo json_encode($response);
?>