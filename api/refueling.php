<?php
/**
 * FleetGuard Pro - Refueling Management API
 * Manages refueling_events table
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

// Get all refueling events
function getAllRefuelingEvents($filters = []) {
    $params = ['order' => 'created_at.desc', 'limit' => 200];
    
    // Truck filter
    if (isset($filters['truck_id']) && !empty($filters['truck_id'])) {
        $params['truck_id'] = 'eq.' . $filters['truck_id'];
    }
    
    // Date range filter
    if (isset($filters['start_date']) && !empty($filters['start_date'])) {
        $params['created_at'] = 'gte.' . $filters['start_date'] . ' 00:00:00';
    }
    if (isset($filters['end_date']) && !empty($filters['end_date'])) {
        if (isset($params['created_at'])) {
            $params['created_at'] = 'and(' . $params['created_at'] . ',lte.' . $filters['end_date'] . ' 23:59:59)';
        } else {
            $params['created_at'] = 'lte.' . $filters['end_date'] . ' 23:59:59';
        }
    }
    
    $result = supabaseRequest('GET', 'refueling_events', $params);
    
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

// Get single refueling event
function getRefuelingEvent($id) {
    $result = supabaseRequest('GET', 'refueling_events', ['id' => 'eq.' . $id]);
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

// Create refueling event
function createRefuelingEvent($data) {
    try {
        $eventData = [
            'truck_id' => $data['truck_id'],
            'sensor_id' => $data['sensor_id'] ?? null,
            'amount_liters' => floatval($data['amount_liters']),
            'cost' => floatval($data['cost'] ?? 0),
            'location' => $data['location'] ?? null,
            'odometer_km' => floatval($data['odometer_km'] ?? 0),
            'refueled_by' => $data['refueled_by'] ?? null,
            'previous_fuel_level' => isset($data['previous_fuel_level']) ? floatval($data['previous_fuel_level']) : null,
            'new_fuel_level' => isset($data['new_fuel_level']) ? floatval($data['new_fuel_level']) : null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = supabaseRequest('POST', 'refueling_events', [], $eventData);
        
        if ($result['status'] === 200 || $result['status'] === 201) {
            // Create an alert if fuel level was critically low before refueling
            if ($eventData['previous_fuel_level'] && $eventData['previous_fuel_level'] < 15) {
                $alertData = [
                    'truck_id' => $data['truck_id'],
                    'alert_type' => 'LOW_FUEL',
                    'severity' => 'HIGH',
                    'message' => "Vehicle was refueled from critically low level ({$eventData['previous_fuel_level']}%)",
                    'created_at' => date('Y-m-d H:i:s')
                ];
                supabaseRequest('POST', 'alerts', [], $alertData);
            }
            
            return ['status' => 201, 'message' => 'Refueling event recorded successfully'];
        }
        return $result;
    } catch (Exception $e) {
        return ['status' => 500, 'error' => 'Create failed: ' . $e->getMessage()];
    }
}

// Update refueling event
function updateRefuelingEvent($id, $data) {
    try {
        $updateData = [];
        
        if (isset($data['truck_id'])) $updateData['truck_id'] = $data['truck_id'];
        if (isset($data['sensor_id'])) $updateData['sensor_id'] = $data['sensor_id'];
        if (isset($data['amount_liters'])) $updateData['amount_liters'] = floatval($data['amount_liters']);
        if (isset($data['cost'])) $updateData['cost'] = floatval($data['cost']);
        if (isset($data['location'])) $updateData['location'] = $data['location'];
        if (isset($data['odometer_km'])) $updateData['odometer_km'] = floatval($data['odometer_km']);
        if (isset($data['refueled_by'])) $updateData['refueled_by'] = $data['refueled_by'];
        if (isset($data['previous_fuel_level'])) $updateData['previous_fuel_level'] = floatval($data['previous_fuel_level']);
        if (isset($data['new_fuel_level'])) $updateData['new_fuel_level'] = floatval($data['new_fuel_level']);
        
        if (empty($updateData)) {
            return ['status' => 400, 'error' => 'No fields to update'];
        }
        
        $result = supabaseRequest('PATCH', 'refueling_events', ['id' => 'eq.' . $id], $updateData);
        
        return ['status' => 200, 'message' => 'Refueling event updated successfully'];
    } catch (Exception $e) {
        return ['status' => 500, 'error' => 'Update failed: ' . $e->getMessage()];
    }
}

// Delete refueling event
function deleteRefuelingEvent($id) {
    try {
        $result = supabaseRequest('DELETE', 'refueling_events', ['id' => 'eq.' . $id]);
        return ['status' => 200, 'message' => 'Refueling event deleted successfully'];
    } catch (Exception $e) {
        return ['status' => 500, 'error' => 'Delete failed: ' . $e->getMessage()];
    }
}

// Get all trucks for dropdown
function getTrucks() {
    return supabaseRequest('GET', 'trucks', ['select' => 'truck_id,license_plate,driver_name', 'order' => 'truck_id.asc']);
}

// Get all sensors for dropdown
function getSensors() {
    return supabaseRequest('GET', 'sensors', ['select' => 'sensor_id,sensor_type', 'status' => 'eq.ASSIGNED', 'limit' => 100]);
}

// Calculate statistics
function calculateStats($records) {
    if (empty($records)) {
        return [
            'total_refuels' => 0,
            'total_liters' => 0,
            'total_cost' => 0,
            'avg_cost_per_liter' => 0,
            'avg_liters_per_refuel' => 0,
            'unique_trucks' => 0,
            'total_distance' => 0
        ];
    }
    
    $totalLiters = 0;
    $totalCost = 0;
    $totalDistance = 0;
    $uniqueTrucks = [];
    
    foreach ($records as $record) {
        $totalLiters += floatval($record['amount_liters'] ?? 0);
        $totalCost += floatval($record['cost'] ?? 0);
        $totalDistance += floatval($record['odometer_km'] ?? 0);
        if (!in_array($record['truck_id'], $uniqueTrucks)) {
            $uniqueTrucks[] = $record['truck_id'];
        }
    }
    
    return [
        'total_refuels' => count($records),
        'total_liters' => round($totalLiters, 2),
        'total_cost' => round($totalCost, 2),
        'avg_cost_per_liter' => $totalLiters > 0 ? round($totalCost / $totalLiters, 2) : 0,
        'avg_liters_per_refuel' => count($records) > 0 ? round($totalLiters / count($records), 2) : 0,
        'unique_trucks' => count($uniqueTrucks),
        'total_distance' => round($totalDistance, 2)
    ];
}

// Export to CSV
function exportRefuelingEvents($filters = []) {
    $result = getAllRefuelingEvents($filters);
    if ($result['status'] !== 200 || empty($result['data'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="refueling_export_empty.csv"');
        echo "No refueling records found";
        exit();
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="refueling_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Truck ID', 'License Plate', 'Driver', 'Amount (L)', 'Cost ($)', 'Location', 'Odometer (km)', 'Refueled By', 'Previous Fuel %', 'New Fuel %']);
    
    foreach ($result['data'] as $record) {
        fputcsv($output, [
            $record['id'],
            $record['created_at'] ?? '',
            $record['truck_id'],
            $record['license_plate'] ?? '',
            $record['driver_name'] ?? '',
            $record['amount_liters'] ?? 0,
            $record['cost'] ?? 0,
            $record['location'] ?? '',
            $record['odometer_km'] ?? 0,
            $record['refueled_by'] ?? '',
            $record['previous_fuel_level'] ?? '',
            $record['new_fuel_level'] ?? ''
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
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null
            ];
            $result = getAllRefuelingEvents($filters);
            
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
                $response = getRefuelingEvent($id);
            }
            break;
            
        case 'create':
            if ($method !== 'POST') {
                $response = ['status' => 405, 'error' => 'Method not allowed'];
            } else {
                $response = createRefuelingEvent($input);
            }
            break;
            
        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                $response = ['status' => 400, 'error' => 'Record ID required'];
            } elseif ($method !== 'POST' && $method !== 'PUT') {
                $response = ['status' => 405, 'error' => 'Method not allowed'];
            } else {
                $response = updateRefuelingEvent($id, $input);
            }
            break;
            
        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                $response = ['status' => 400, 'error' => 'Record ID required'];
            } elseif ($method !== 'DELETE') {
                $response = ['status' => 405, 'error' => 'Method not allowed'];
            } else {
                $response = deleteRefuelingEvent($id);
            }
            break;
            
        case 'getTrucks':
            $response = getTrucks();
            break;
            
        case 'getSensors':
            $response = getSensors();
            break;
            
        case 'export':
            $filters = [
                'truck_id' => $_GET['truck_id'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null
            ];
            exportRefuelingEvents($filters);
            exit();
            
        case 'test':
            $response = [
                'status' => 200,
                'message' => 'Refueling API is working',
                'timestamp' => date('Y-m-d H:i:s'),
                'supabase_url' => SUPABASE_URL
            ];
            break;
            
        default:
            $response = ['status' => 400, 'error' => 'Invalid action. Available: getAll, get, create, update, delete, getTrucks, getSensors, export, test'];
    }
} catch (Exception $e) {
    $response = ['status' => 500, 'error' => 'Server error: ' . $e->getMessage()];
}

http_response_code($response['status'] ?? 200);
echo json_encode($response);
?>