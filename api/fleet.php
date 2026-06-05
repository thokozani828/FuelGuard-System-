<?php
/**
 * FleetGuard Pro - Fleet Management API
 * Complete CRUD Operations for Fleet Management
 * Matches the provided database schema
 */

require_once dirname(__DIR__) . '/config.php';

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
// FLEET MANAGEMENT CLASS
// ============================================

class FleetManager {
    private $supabase;
    
    public function __construct() {
        $this->supabase = new SupabaseAPI();
    }
    
    /**
     * GET ALL VEHICLES
     */
    public function getAllVehicles($filters = []) {
        try {
            $params = ['select' => '*', 'order' => 'truck_id.asc'];
            
            if (isset($filters['status']) && $filters['status']) {
                $params['status'] = 'eq.' . $filters['status'];
            }
            
            $result = $this->supabase->get('trucks', $params);
            
            // Enhance with latest telemetry
            if ($result['status'] === 200 && !empty($result['data'])) {
                foreach ($result['data'] as &$truck) {
                    $latestTelemetry = $this->getLatestTelemetry($truck['truck_id']);
                    if ($latestTelemetry) {
                        $truck['fuel_level'] = $latestTelemetry['fuel_percentage'] ?? $truck['fuel_level'] ?? 0;
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
     * GET SINGLE VEHICLE
     */
    public function getVehicle($vehicleId) {
        try {
            $result = $this->supabase->get('trucks', [
                'truck_id' => 'eq.' . $vehicleId,
                'select' => '*'
            ]);
            
            if ($result['status'] === 200 && !empty($result['data'])) {
                $vehicle = $result['data'][0];
                $latestTelemetry = $this->getLatestTelemetry($vehicleId);
                if ($latestTelemetry) {
                    $vehicle['fuel_level'] = $latestTelemetry['fuel_percentage'];
                }
                $result['data'] = $vehicle;
            }
            
            return $result;
        } catch (Exception $e) {
            return ['status' => 500, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * CREATE NEW VEHICLE
     */
    public function createVehicle($data) {
        try {
            // Validate required fields
            if (empty($data['truck_id'])) {
                return ['status' => 400, 'error' => 'Truck ID is required'];
            }
            if (empty($data['license_plate'])) {
                return ['status' => 400, 'error' => 'License plate is required'];
            }
            
            // Business Logic: Verify Driver existence if provided
            $driverName = $data['driver_name'] ?? 'Unassigned';
            $driverPhone = $data['driver_phone'] ?? null;
            $driverEmail = $data['driver_email'] ?? null;

            if (!empty($data['driver_id'])) {
                // 1. Verify driver exists
                $driverResult = $this->supabase->get('drivers', ['driver_id' => 'eq.' . $data['driver_id']]);
                if ($driverResult['status'] !== 200 || empty($driverResult['data'])) {
                    return ['status' => 400, 'error' => 'Invalid assignment: Driver ID ' . $data['driver_id'] . ' not found in database.'];
                }
                
                // 2. Check if driver is already assigned to another truck (One Driver, One Truck constraint)
                // FIX: Removed urlencode here because get() uses http_build_query which already encodes values.
                $assignmentCheck = $this->supabase->get('trucks', ['driver_name' => 'eq.' . $driverResult['data'][0]['full_name']]);
                if ($assignmentCheck['status'] === 200 && !empty($assignmentCheck['data'])) {
                    return ['status' => 409, 'error' => 'Driver ' . $driverResult['data'][0]['full_name'] . ' is already assigned to truck ' . $assignmentCheck['data'][0]['truck_id']];
                }

                // Use verified driver details
                $driver = $driverResult['data'][0];
                $driverName = $driver['full_name'];
                $driverPhone = $driver['phone'] ?? null;
                $driverEmail = $driver['email'] ?? null;
                $driverId = $driver['driver_id']; // Captured for storage
            } else {
                $driverId = null;
            }
            
            // Check if vehicle already exists
            $existing = $this->getVehicle($data['truck_id']);
            if ($existing['status'] === 200 && !empty($existing['data'])) {
                return ['status' => 409, 'error' => 'Vehicle ID already exists: ' . $data['truck_id']];
            }
            
            $vehicleData = [
                'truck_id' => $data['truck_id'],
                'license_plate' => strtoupper($data['license_plate']),
                'driver_id' => $driverId, // Now storing the ID
                'driver_name' => $driverName,
                'driver_phone' => $driverPhone,
                'driver_email' => $driverEmail,
                'tank_capacity_liters' => $data['tank_capacity_liters'] ?? 300,
                'tank_height_cm' => $data['tank_height_cm'] ?? 120,
                'tank_cross_section_m2' => $data['tank_cross_section_m2'] ?? 2.5,
                'current_mileage_km' => $data['current_mileage_km'] ?? 0,
                'status' => $data['status'] ?? 'ACTIVE',
                'last_maintenance_date' => $data['last_maintenance_date'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->supabase->insert('trucks', $vehicleData);
            
            if ($result['status'] === 200 || $result['status'] === 201) {
                // Create initial telemetry record
                $this->createInitialTelemetry($data['truck_id'], $data['fuel_level'] ?? 65);
                return ['status' => 201, 'message' => 'Vehicle created successfully', 'data' => $vehicleData];
            }
            
            return $result;
        } catch (Exception $e) {
            return ['status' => 500, 'error' => 'Create failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * UPDATE VEHICLE
     */
    public function updateVehicle($vehicleId, $data) {
        try {
            // Check if vehicle exists
            $existing = $this->getVehicle($vehicleId);
            if ($existing['status'] !== 200 || empty($existing['data'])) {
                return ['status' => 404, 'error' => 'Vehicle not found: ' . $vehicleId];
            }
            
            $updateData = [];
            
            if (isset($data['license_plate'])) {
                $updateData['license_plate'] = strtoupper($data['license_plate']);
            }
            
            // Business Logic: Verify Driver existence if provided
            if (!empty($data['driver_id'])) {
                // 1. Verify driver exists
                $driverResult = $this->supabase->get('drivers', ['driver_id' => 'eq.' . $data['driver_id']]);
                if ($driverResult['status'] !== 200 || empty($driverResult['data'])) {
                    return ['status' => 400, 'error' => 'Invalid assignment: Driver ID ' . $data['driver_id'] . ' not found in database.'];
                }

                $driver = $driverResult['data'][0];

                // 2. Check if driver is already assigned to another truck (One Driver, One Truck constraint)
                $assignmentCheck = $this->supabase->get('trucks', ['driver_name' => 'eq.' . urlencode($driver['full_name'])]);
                if ($assignmentCheck['status'] === 200 && !empty($assignmentCheck['data'])) {
                    // If it's already assigned to THIS truck, that's fine. If it's a DIFFERENT truck, block it.
                    if ($assignmentCheck['data'][0]['truck_id'] !== $vehicleId) {
                        return ['status' => 409, 'error' => 'Driver ' . $driver['full_name'] . ' is already assigned to truck ' . $assignmentCheck['data'][0]['truck_id']];
                    }
                }

                $updateData['driver_name'] = $driver['full_name'];
                $updateData['driver_phone'] = $driver['phone'] ?? null;
                $updateData['driver_email'] = $driver['email'] ?? null;
            }
 else {
                if (isset($data['driver_name'])) {
                    $updateData['driver_name'] = $data['driver_name'];
                }
                if (isset($data['driver_phone'])) {
                    $updateData['driver_phone'] = $data['driver_phone'];
                }
                if (isset($data['driver_email'])) {
                    $updateData['driver_email'] = $data['driver_email'];
                }
            }

            if (isset($data['tank_capacity_liters'])) {
                $updateData['tank_capacity_liters'] = floatval($data['tank_capacity_liters']);
            }
            if (isset($data['current_mileage_km'])) {
                $updateData['current_mileage_km'] = floatval($data['current_mileage_km']);
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if (isset($data['last_maintenance_date'])) {
                $updateData['last_maintenance_date'] = $data['last_maintenance_date'];
            }
            
            // Update fuel level if provided
            if (isset($data['fuel_level'])) {
                $this->updateFuelLevel($vehicleId, $data['fuel_level']);
            }
            
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            
            if (empty($updateData)) {
                return ['status' => 400, 'error' => 'No fields to update'];
            }
            
            $result = $this->supabase->update('trucks', $vehicleId, $updateData, 'truck_id');
            
            return ['status' => 200, 'message' => 'Vehicle updated successfully'];
        } catch (Exception $e) {
            return ['status' => 500, 'error' => 'Update failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * DELETE VEHICLE
     */
    public function deleteVehicle($vehicleId) {
        try {
            // Check if vehicle exists
            $existing = $this->getVehicle($vehicleId);
            if ($existing['status'] !== 200 || empty($existing['data'])) {
                return ['status' => 404, 'error' => 'Vehicle not found: ' . $vehicleId];
            }
            
            $result = $this->supabase->delete('trucks', $vehicleId, 'truck_id');
            
            return ['status' => 200, 'message' => 'Vehicle deleted successfully'];
        } catch (Exception $e) {
            return ['status' => 500, 'error' => 'Delete failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * UPDATE FUEL LEVEL ONLY
     */
    public function updateFuelLevel($vehicleId, $fuelLevel) {
        try {
            // Check if vehicle exists
            $vehicle = $this->getVehicle($vehicleId);
            if ($vehicle['status'] !== 200 || empty($vehicle['data'])) {
                return ['status' => 404, 'error' => 'Vehicle not found: ' . $vehicleId];
            }
            
            $fuelLevel = floatval($fuelLevel);
            $truckData = $vehicle['data'];
            $tankCapacity = $truckData['tank_capacity_liters'] ?? 300;
            $fuelVolume = ($fuelLevel / 100) * $tankCapacity;
            $fuelHeight = ($fuelLevel / 100) * ($truckData['tank_height_cm'] ?? 120);
            
            // Create telemetry record
            $telemetryData = [
                'sensor_id' => null,
                'truck_id' => $vehicleId,
                'license_plate' => $truckData['license_plate'],
                'driver_name' => $truckData['driver_name'],
                'sensor_type' => 'manual',
                'raw_distance_cm' => $fuelHeight,
                'fuel_level_cm' => $fuelHeight,
                'fuel_percentage' => $fuelLevel,
                'fuel_volume_liters' => $fuelVolume,
                'tank_capacity_liters' => $tankCapacity,
                'status' => $fuelLevel < 20 ? 'CRITICAL' : ($fuelLevel < 30 ? 'LOW' : 'NORMAL'),
                'alert_level' => $fuelLevel < 20 ? 'CRITICAL' : ($fuelLevel < 30 ? 'WARNING' : 'INFO'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->supabase->insert('fuel_telemetry', $telemetryData);
            
            // Update truck status based on fuel level
            $status = 'ACTIVE';
            if ($fuelLevel < 20) {
                $status = 'CRITICAL';
            } elseif ($fuelLevel < 30) {
                $status = 'WARNING';
            }
            
            $this->supabase->update('trucks', $vehicleId, [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'truck_id');
            
            return ['status' => 200, 'message' => 'Fuel level updated successfully'];
        } catch (Exception $e) {
            return ['status' => 500, 'error' => 'Update failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * GET LATEST TELEMETRY FOR VEHICLE
     */
    private function getLatestTelemetry($truckId) {
        try {
            $result = $this->supabase->get('fuel_telemetry', [
                'truck_id' => 'eq.' . $truckId,
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
     * CREATE INITIAL TELEMETRY RECORD
     */
    private function createInitialTelemetry($truckId, $fuelPercentage) {
        try {
            $vehicle = $this->getVehicle($truckId);
            if ($vehicle['status'] !== 200) {
                return false;
            }
            
            $truckData = $vehicle['data'];
            $tankCapacity = $truckData['tank_capacity_liters'] ?? 300;
            $fuelVolume = ($fuelPercentage / 100) * $tankCapacity;
            $fuelHeight = ($fuelPercentage / 100) * ($truckData['tank_height_cm'] ?? 120);
            
            $telemetryData = [
                'sensor_id' => null,
                'truck_id' => $truckId,
                'license_plate' => $truckData['license_plate'],
                'driver_name' => $truckData['driver_name'],
                'sensor_type' => 'initial',
                'raw_distance_cm' => $fuelHeight,
                'fuel_level_cm' => $fuelHeight,
                'fuel_percentage' => $fuelPercentage,
                'fuel_volume_liters' => $fuelVolume,
                'tank_capacity_liters' => $tankCapacity,
                'status' => $fuelPercentage < 20 ? 'CRITICAL' : ($fuelPercentage < 30 ? 'LOW' : 'NORMAL'),
                'alert_level' => $fuelPercentage < 20 ? 'CRITICAL' : ($fuelPercentage < 30 ? 'WARNING' : 'INFO'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            return $this->supabase->insert('fuel_telemetry', $telemetryData);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * GET FLEET STATISTICS
     */
    public function getStatistics() {
        try {
            $result = $this->getAllVehicles();
            if ($result['status'] !== 200) {
                return $result;
            }
            
            $vehicles = $result['data'];
            $total = count($vehicles);
            
            $fuelLevels = array_map(function($v) {
                $latestTelemetry = $this->getLatestTelemetry($v['truck_id']);
                return $latestTelemetry['fuel_percentage'] ?? 0;
            }, $vehicles);
            
            $avgFuel = $total > 0 ? round(array_sum($fuelLevels) / $total) : 0;
            $critical = count(array_filter($vehicles, function($v) {
                $latestTelemetry = $this->getLatestTelemetry($v['truck_id']);
                $fuelLevel = $latestTelemetry['fuel_percentage'] ?? 0;
                return $fuelLevel < 20 || $v['status'] === 'CRITICAL';
            }));
            $maintenance = count(array_filter($vehicles, function($v) {
                return $v['status'] === 'MAINTENANCE';
            }));
            $active = count(array_filter($vehicles, function($v) {
                $latestTelemetry = $this->getLatestTelemetry($v['truck_id']);
                $fuelLevel = $latestTelemetry['fuel_percentage'] ?? 0;
                return $v['status'] === 'ACTIVE' && $fuelLevel >= 20;
            }));
            
            return [
                'status' => 200,
                'data' => [
                    'total' => $total,
                    'avg_fuel' => $avgFuel,
                    'critical_count' => $critical,
                    'maintenance_count' => $maintenance,
                    'active_count' => $active
                ]
            ];
        } catch (Exception $e) {
            return ['status' => 500, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * EXPORT VEHICLES TO CSV
     */
    public function exportVehicles() {
        $vehicles = $this->getAllVehicles();
        if ($vehicles['status'] !== 200) {
            http_response_code(500);
            echo json_encode($vehicles);
            exit();
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="fleet_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Truck ID', 'License Plate', 'Driver Name', 'Driver Phone', 'Fuel Level (%)', 'Status', 'Tank Capacity (L)', 'Last Maintenance']);
        
        foreach ($vehicles['data'] as $vehicle) {
            $latestTelemetry = $this->getLatestTelemetry($vehicle['truck_id']);
            $fuelLevel = $latestTelemetry['fuel_percentage'] ?? 'N/A';
            
            fputcsv($output, [
                $vehicle['truck_id'],
                $vehicle['license_plate'],
                $vehicle['driver_name'] ?? '',
                $vehicle['driver_phone'] ?? '',
                $fuelLevel,
                $vehicle['status'] ?? '',
                $vehicle['tank_capacity_liters'] ?? '',
                $vehicle['last_maintenance_date'] ?? ''
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

$fleetManager = new FleetManager();
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
        $filters = ['status' => $_GET['status'] ?? null];
        $response = $fleetManager->getAllVehicles($filters);
        break;
        
    case 'get':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $response = ['status' => 400, 'error' => 'Vehicle ID required'];
        } else {
            $response = $fleetManager->getVehicle($id);
        }
        break;
        
    case 'create':
        if ($method !== 'POST') {
            $response = ['status' => 405, 'error' => 'Method not allowed'];
        } else {
            $response = $fleetManager->createVehicle($input);
        }
        break;
        
    case 'update':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $response = ['status' => 400, 'error' => 'Vehicle ID required'];
        } elseif ($method !== 'POST' && $method !== 'PUT') {
            $response = ['status' => 405, 'error' => 'Method not allowed'];
        } else {
            $response = $fleetManager->updateVehicle($id, $input);
        }
        break;
        
    case 'delete':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $response = ['status' => 400, 'error' => 'Vehicle ID required'];
        } elseif ($method !== 'DELETE' && $method !== 'GET') {
            $response = ['status' => 405, 'error' => 'Method not allowed'];
        } else {
            $response = $fleetManager->deleteVehicle($id);
        }
        break;
        
    case 'updateFuel':
        $id = $_GET['id'] ?? ($input['truck_id'] ?? null);
        $fuelLevel = $_GET['fuel'] ?? ($input['fuel_level'] ?? null);
        if (!$id) {
            $response = ['status' => 400, 'error' => 'Vehicle ID required'];
        } elseif ($fuelLevel === null) {
            $response = ['status' => 400, 'error' => 'Fuel level required'];
        } else {
            $response = $fleetManager->updateFuelLevel($id, $fuelLevel);
        }
        break;
        
    case 'statistics':
        $response = $fleetManager->getStatistics();
        break;
        
    case 'export':
        $fleetManager->exportVehicles();
        exit();
        
    case 'test':
        $response = [
            'status' => 200,
            'message' => 'Fleet API is working',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'supabase_url' => SUPABASE_URL,
            'tables' => ['trucks', 'sensors', 'fuel_telemetry', 'sensor_assignments']
        ];
        break;
        
    default:
        $response = ['status' => 400, 'error' => 'Invalid action. Available actions: getAll, get, create, update, delete, updateFuel, statistics, export, test'];
        break;
}

if (!isset($response['status'])) {
    $response = ['status' => 500, 'error' => 'Unknown error occurred'];
}

http_response_code($response['status']);
echo json_encode($response);
?>