<?php
/**
 * FleetGuard Pro - Alerts Management API
 * Using trucks table
 */

// Enable error reporting for debugging
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
validateSession();

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

// Get alerts with filters and join with trucks
function getAlerts($filters = []) {
    $params = ['order' => 'alert_date.desc', 'limit' => 100];
    
    // Date filter
    $days = isset($filters['days']) ? intval($filters['days']) : 30;
    if ($days > 0) {
        $params['alert_date'] = 'gte.' . date('Y-m-d H:i:s', strtotime("-{$days} days"));
    }
    
    // Status filter
    if (isset($filters['status']) && !empty($filters['status'])) {
        $params['status'] = 'eq.' . $filters['status'];
    }
    
    // Truck filter
    if (isset($filters['truck_id']) && !empty($filters['truck_id'])) {
        $params['truck_id'] = 'eq.' . $filters['truck_id'];
    }
    
    // Alert type filter
    if (isset($filters['alert_type']) && !empty($filters['alert_type'])) {
        $params['alert_type'] = 'eq.' . $filters['alert_type'];
    }
    
    $result = supabaseRequest('GET', 'alerts', $params);
    
    // If we have alerts, fetch truck details
    if ($result['status'] === 200 && !empty($result['data'])) {
        // Get unique truck IDs
        $truckIds = array_unique(array_column($result['data'], 'truck_id'));
        if (!empty($truckIds)) {
            $trucks = supabaseRequest('GET', 'trucks', [
                'truck_id' => 'in.' . implode(',', $truckIds),
                'select' => 'truck_id,license_plate,driver_name,status'
            ]);
            
            if ($trucks['status'] === 200 && !empty($trucks['data'])) {
                $truckMap = [];
                foreach ($trucks['data'] as $t) {
                    $truckMap[$t['truck_id']] = $t;
                }
                
                // Add truck info to each alert
                foreach ($result['data'] as &$alert) {
                    if (isset($truckMap[$alert['truck_id']])) {
                        $alert['license_plate'] = $truckMap[$alert['truck_id']]['license_plate'];
                        $alert['driver_name'] = $truckMap[$alert['truck_id']]['driver_name'];
                        $alert['truck_status'] = $truckMap[$alert['truck_id']]['status'];
                    }
                }
            }
        }
    }
    
    return $result;
}

// Get single alert
function getAlert($id) {
    $result = supabaseRequest('GET', 'alerts', ['alert_id' => 'eq.' . $id]);
    if ($result['status'] === 200 && !empty($result['data'])) {
        $alert = $result['data'][0];
        // Get truck info
        $truck = supabaseRequest('GET', 'trucks', [
            'truck_id' => 'eq.' . $alert['truck_id'],
            'select' => 'truck_id,license_plate,driver_name,status'
        ]);
        if ($truck['status'] === 200 && !empty($truck['data'])) {
            $alert['license_plate'] = $truck['data'][0]['license_plate'];
            $alert['driver_name'] = $truck['data'][0]['driver_name'];
            $alert['truck_status'] = $truck['data'][0]['status'];
        }
        return ['status' => 200, 'data' => $alert];
    }
    return $result;
}

// Resolve alert
function resolveAlert($id) {
    return supabaseRequest('PATCH', 'alerts', ['alert_id' => 'eq.' . $id], ['status' => 'Resolved']);
}

// Resolve all pending alerts
function resolveAllAlerts() {
    $alerts = supabaseRequest('GET', 'alerts', ['status' => 'eq.Pending', 'select' => 'alert_id']);
    
    $resolvedCount = 0;
    if ($alerts['status'] === 200 && !empty($alerts['data'])) {
        foreach ($alerts['data'] as $alert) {
            resolveAlert($alert['alert_id']);
            $resolvedCount++;
        }
    }
    
    return ['status' => 200, 'resolved_count' => $resolvedCount];
}

// Get trucks for dropdown
function getTrucks() {
    return supabaseRequest('GET', 'trucks', ['select' => 'truck_id,license_plate,driver_name', 'order' => 'license_plate.asc']);
}

// Calculate statistics
function calculateStats($alerts) {
    if (empty($alerts)) {
        return [
            'total' => 0,
            'pending_count' => 0,
            'resolved_count' => 0,
            'unique_trucks' => 0,
            'this_month' => 0
        ];
    }
    
    $pending = 0;
    $resolved = 0;
    $uniqueTrucks = [];
    $thisMonth = 0;
    $currentMonth = date('Y-m');
    
    foreach ($alerts as $alert) {
        if ($alert['status'] === 'Pending') $pending++;
        elseif ($alert['status'] === 'Resolved') $resolved++;
        
        if (!in_array($alert['truck_id'], $uniqueTrucks)) {
            $uniqueTrucks[] = $alert['truck_id'];
        }
        
        if (isset($alert['alert_date'])) {
            $alertDate = substr($alert['alert_date'], 0, 7);
            if ($alertDate === $currentMonth) $thisMonth++;
        }
    }
    
    return [
        'total' => count($alerts),
        'pending_count' => $pending,
        'resolved_count' => $resolved,
        'unique_trucks' => count($uniqueTrucks),
        'this_month' => $thisMonth
    ];
}

// Export to CSV
function exportAlerts($filters = []) {
    $result = getAlerts($filters);
    if ($result['status'] !== 200 || empty($result['data'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="alerts_export_empty.csv"');
        echo "No alerts found";
        exit();
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="alerts_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Alert ID', 'Date', 'Truck ID', 'License Plate', 'Alert Type', 'Message', 'Status']);
    
    foreach ($result['data'] as $alert) {
        $licensePlate = $alert['license_plate'] ?? $alert['truck_id'];
        fputcsv($output, [
            $alert['alert_id'],
            $alert['alert_date'] ?? '',
            $alert['truck_id'],
            $licensePlate,
            $alert['alert_type'] ?? '',
            $alert['alert_message'] ?? '',
            $alert['status'] ?? ''
        ]);
    }
    
    fclose($output);
    exit();
}

// Main routing
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$response = [];

try {
    switch ($action) {
        case 'getAll':
            $filters = [
                'days' => $_GET['days'] ?? 30,
                'status' => $_GET['status'] ?? null,
                'truck_id' => $_GET['truck_id'] ?? null,
                'alert_type' => $_GET['alert_type'] ?? null
            ];
            $result = getAlerts($filters);
            
            if ($result['status'] === 200) {
                $stats = calculateStats($result['data']);
                $response = ['status' => 200, 'data' => $result['data'], 'stats' => $stats];
            } else {
                $response = ['status' => $result['status'], 'error' => 'Failed to fetch alerts'];
            }
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                $response = ['status' => 400, 'error' => 'Alert ID required'];
            } else {
                $response = getAlert($id);
            }
            break;
            
        case 'resolve':
            if ($method !== 'POST') {
                $response = ['status' => 405, 'error' => 'Method not allowed'];
            } else {
                $input = json_decode(file_get_contents('php://input'), true);
                $id = $input['id'] ?? null;
                if (!$id) {
                    $response = ['status' => 400, 'error' => 'Alert ID required'];
                } else {
                    $result = resolveAlert($id);
                    if ($result['status'] === 200) {
                        $response = ['status' => 200, 'message' => 'Alert resolved successfully'];
                    } else {
                        $response = ['status' => $result['status'], 'error' => 'Failed to resolve alert'];
                    }
                }
            }
            break;
            
        case 'resolveAll':
            if ($method !== 'POST') {
                $response = ['status' => 405, 'error' => 'Method not allowed'];
            } else {
                $response = resolveAllAlerts();
            }
            break;
            
        case 'getTrucks':
            $response = getTrucks();
            break;
            
        case 'export':
            $filters = [
                'days' => $_GET['days'] ?? 30,
                'status' => $_GET['status'] ?? null,
                'truck_id' => $_GET['truck_id'] ?? null,
                'alert_type' => $_GET['alert_type'] ?? null
            ];
            exportAlerts($filters);
            exit();
            
        case 'test':
            $response = [
                'status' => 200,
                'message' => 'API is working',
                'timestamp' => date('Y-m-d H:i:s'),
                'supabase_url' => SUPABASE_URL
            ];
            break;
            
        default:
            $response = ['status' => 400, 'error' => 'Invalid action. Available: getAll, get, resolve, resolveAll, getTrucks, export, test'];
    }
} catch (Exception $e) {
    $response = ['status' => 500, 'error' => 'Server error: ' . $e->getMessage()];
}

http_response_code($response['status'] ?? 200);
echo json_encode($response);
?>