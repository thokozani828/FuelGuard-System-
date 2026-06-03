<?php
/**
 * FleetGuard Pro - Maintenance Management API
 * Matches your database schema with maintenance_logs table
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

// Get all maintenance records
function getAllMaintenance($filters = []) {
    $params = ['order' => 'performed_at.desc', 'limit' => 200];
    
    // Truck filter
    if (isset($filters['truck_id']) && !empty($filters['truck_id'])) {
        $params['truck_id'] = 'eq.' . $filters['truck_id'];
    }
    
    // Maintenance type filter
    if (isset($filters['maintenance_type']) && !empty($filters['maintenance_type'])) {
        $params['maintenance_type'] = 'eq.' . $filters['maintenance_type'];
    }
    
    $result = supabaseRequest('GET', 'maintenance_logs', $params);
    
    // Add truck license plate info
    if ($result['status'] === 200 && !empty($result['data'])) {
        $truckIds = array_unique(array_column($result['data'], 'truck_id'));
        if (!empty($truckIds)) {
            $trucks = supabaseRequest('GET', 'trucks', [
                'truck_id' => 'in.' . implode(',', $truckIds),
                'select' => 'truck_id,license_plate,driver_name'
            ]);
            
            if ($trucks['status'] === 200 && !empty($trucks['data'])) {
                $truckMap = [];
                foreach ($trucks['data'] as $t) {
                    $truckMap[$t['truck_id']] = $t;
                }
                
                foreach ($result['data'] as &$record) {
                    if (isset($truckMap[$record['truck_id']])) {
                        $record['license_plate'] = $truckMap[$record['truck_id']]['license_plate'];
                        $record['driver_name'] = $truckMap[$record['truck_id']]['driver_name'];
                    }
                }
            }
        }
    }
    
    return $result;
}

// Get single maintenance record
function getMaintenance($id) {
    $result = supabaseRequest('GET', 'maintenance_logs', ['id' => 'eq.' . $id]);
    if ($result['status'] === 200 && !empty($result['data'])) {
        $record = $result['data'][0];
        // Get truck info
        $truck = supabaseRequest('GET', 'trucks', [
            'truck_id' => 'eq.' . $record['truck_id'],
            'select' => 'truck_id,license_plate,driver_name'
        ]);
        if ($truck['status'] === 200 && !empty($truck['data'])) {
            $record['license_plate'] = $truck['data'][0]['license_plate'];
            $record['driver_name'] = $truck['data'][0]['driver_name'];
        }
        return ['status' => 200, 'data' => $record];
    }
    return $result;
}

// Create maintenance record
function createMaintenance($data) {
    try {
        $maintenanceData = [
            'truck_id' => $data['truck_id'],
            'maintenance_type' => $data['maintenance_type'],
            'description' => $data['description'] ?? '',
            'cost' => floatval($data['cost'] ?? 0),
            'performed_by' => $data['performed_by'] ?? 'System',
            'performed_at' => $data['performed_at'] ?? date('Y-m-d H:i:s'),
            'next_maintenance_due' => $data['next_maintenance_due'] ?? null
        ];
        
        $result = supabaseRequest('POST', 'maintenance_logs', [], $maintenanceData);
        
        if ($result['status'] === 200 || $result['status'] === 201) {
            // Also update truck's last_maintenance_date
            supabaseRequest('PATCH', 'trucks', ['truck_id' => 'eq.' . $data['truck_id']], [
                'last_maintenance_date' => date('Y-m-d'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            return ['status' => 201, 'message' => 'Maintenance record created successfully'];
        }
        return $result;
    } catch (Exception $e) {
        return ['status' => 500, 'error' => 'Create failed: ' . $e->getMessage()];
    }
}

// Update maintenance record
function updateMaintenance($id, $data) {
    try {
        $updateData = [];
        
        if (isset($data['maintenance_type'])) $updateData['maintenance_type'] = $data['maintenance_type'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['cost'])) $updateData['cost'] = floatval($data['cost']);
        if (isset($data['performed_by'])) $updateData['performed_by'] = $data['performed_by'];
        if (isset($data['performed_at'])) $updateData['performed_at'] = $data['performed_at'];
        if (isset($data['next_maintenance_due'])) $updateData['next_maintenance_due'] = $data['next_maintenance_due'];
        
        if (empty($updateData)) {
            return ['status' => 400, 'error' => 'No fields to update'];
        }
        
        $result = supabaseRequest('PATCH', 'maintenance_logs', ['id' => 'eq.' . $id], $updateData);
        
        return ['status' => 200, 'message' => 'Maintenance record updated successfully'];
    } catch (Exception $e) {
        return ['status' => 500, 'error' => 'Update failed: ' . $e->getMessage()];
    }
}

// Delete maintenance record
function deleteMaintenance($id) {
    try {
        $result = supabaseRequest('DELETE', 'maintenance_logs', ['id' => 'eq.' . $id]);
        return ['status' => 200, 'message' => 'Maintenance record deleted successfully'];
    } catch (Exception $e) {
        return ['status' => 500, 'error' => 'Delete failed: ' . $e->getMessage()];
    }
}

// Get all trucks for dropdown
function getTrucks() {
    return supabaseRequest('GET', 'trucks', ['select' => 'truck_id,license_plate,driver_name', 'order' => 'truck_id.asc']);
}

// Get upcoming maintenance (based on next_maintenance_due)
function getUpcomingMaintenance() {
    $today = date('Y-m-d');
    $params = [
        'next_maintenance_due' => 'gte.' . $today,
        'order' => 'next_maintenance_due.asc',
        'limit' => 20
    ];
    
    $result = supabaseRequest('GET', 'maintenance_logs', $params);
    
    if ($result['status'] === 200 && !empty($result['data'])) {
        $truckIds = array_unique(array_column($result['data'], 'truck_id'));
        if (!empty($truckIds)) {
            $trucks = supabaseRequest('GET', 'trucks', [
                'truck_id' => 'in.' . implode(',', $truckIds),
                'select' => 'truck_id,license_plate'
            ]);
            
            if ($trucks['status'] === 200 && !empty($trucks['data'])) {
                $truckMap = [];
                foreach ($trucks['data'] as $t) {
                    $truckMap[$t['truck_id']] = $t;
                }
                
                foreach ($result['data'] as &$record) {
                    if (isset($truckMap[$record['truck_id']])) {
                        $record['license_plate'] = $truckMap[$record['truck_id']]['license_plate'];
                    }
                }
            }
        }
    }
    
    return $result;
}

// Calculate statistics
function calculateStats($records) {
    if (empty($records)) {
        return [
            'total' => 0,
            'total_cost' => 0,
            'unique_trucks' => 0
        ];
    }
    
    $totalCost = 0;
    $uniqueTrucks = [];
    
    foreach ($records as $record) {
        $totalCost += floatval($record['cost'] ?? 0);
        if (!in_array($record['truck_id'], $uniqueTrucks)) {
            $uniqueTrucks[] = $record['truck_id'];
        }
    }
    
    return [
        'total' => count($records),
        'total_cost' => $totalCost,
        'unique_trucks' => count($uniqueTrucks)
    ];
}

// Export to CSV
function exportMaintenance($filters = []) {
    $result = getAllMaintenance($filters);
    if ($result['status'] !== 200 || empty($result['data'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="maintenance_export_empty.csv"');
        echo "No maintenance records found";
        exit();
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="maintenance_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Truck ID', 'License Plate', 'Maintenance Type', 'Description', 'Cost', 'Performed By', 'Next Maintenance Due']);
    
    foreach ($result['data'] as $record) {
        fputcsv($output, [
            $record['id'],
            $record['performed_at'] ?? '',
            $record['truck_id'],
            $record['license_plate'] ?? '',
            $record['maintenance_type'] ?? '',
            $record['description'] ?? '',
            $record['cost'] ?? 0,
            $record['performed_by'] ?? '',
            $record['next_maintenance_due'] ?? ''
        ]);
    }
    
    fclose($output);
    exit();
}

// Main routing
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$response = [];

try {
    switch ($action) {
        case 'getAll':
            $filters = [
                'truck_id' => $_GET['truck_id'] ?? null,
                'maintenance_type' => $_GET['maintenance_type'] ?? null
            ];
            $result = getAllMaintenance($filters);
            
            if ($result['status'] === 200) {
                $stats = calculateStats($result['data']);
                $response = ['status' => 200, 'data' => $result['data'], 'stats' => $stats];
            } else {
                $response = ['status' => $result['status'], 'error' => 'Failed to fetch records'];
            }
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                $response = ['status' => 400, 'error' => 'Record ID required'];
            } else {
                $response = getMaintenance($id);
            }
            break;
            
        case 'create':
            if ($method !== 'POST') {
                $response = ['status' => 405, 'error' => 'Method not allowed'];
            } else {
                $response = createMaintenance($input);
            }
            break;
            
        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                $response = ['status' => 400, 'error' => 'Record ID required'];
            } elseif ($method !== 'POST' && $method !== 'PUT') {
                $response = ['status' => 405, 'error' => 'Method not allowed'];
            } else {
                $response = updateMaintenance($id, $input);
            }
            break;
            
        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                $response = ['status' => 400, 'error' => 'Record ID required'];
            } elseif ($method !== 'DELETE') {
                $response = ['status' => 405, 'error' => 'Method not allowed'];
            } else {
                $response = deleteMaintenance($id);
            }
            break;
            
        case 'getTrucks':
            $response = getTrucks();
            break;
            
        case 'getUpcoming':
            $response = getUpcomingMaintenance();
            break;
            
        case 'export':
            $filters = [
                'truck_id' => $_GET['truck_id'] ?? null,
                'maintenance_type' => $_GET['maintenance_type'] ?? null
            ];
            exportMaintenance($filters);
            exit();
            
        case 'test':
            $response = [
                'status' => 200,
                'message' => 'Maintenance API is working',
                'timestamp' => date('Y-m-d H:i:s'),
                'supabase_url' => SUPABASE_URL
            ];
            break;
            
        default:
            $response = ['status' => 400, 'error' => 'Invalid action. Available: getAll, get, create, update, delete, getTrucks, getUpcoming, export, test'];
    }
} catch (Exception $e) {
    $response = ['status' => 500, 'error' => 'Server error: ' . $e->getMessage()];
}

http_response_code($response['status'] ?? 200);
echo json_encode($response);
?>