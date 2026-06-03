<?php
/**
 * FleetGuard Pro - Dashboard API
 * Aggregates data from all tables for the overview page
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config.php';
validateSession();

function supabaseRequest($method, $table, $params = []) {
    $url = SUPABASE_URL . '/rest/v1/' . $table;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'summary':
        $response = [
            'status' => 200,
            'data' => [
                'trucks' => supabaseRequest('GET', 'trucks', ['select' => '*', 'limit' => 1000]),
                'alerts' => supabaseRequest('GET', 'alerts', ['select' => '*', 'order' => 'created_at.desc', 'limit' => 50]),
                'telemetry' => supabaseRequest('GET', 'fuel_telemetry', ['select' => '*', 'order' => 'created_at.desc', 'limit' => 200]),
                'maintenance' => supabaseRequest('GET', 'maintenance_logs', ['select' => '*', 'order' => 'performed_at.desc', 'limit' => 100]),
                'refueling' => supabaseRequest('GET', 'refueling_events', ['select' => '*', 'order' => 'created_at.desc', 'limit' => 100])
            ]
        ];
        break;
        
    default:
        $response = ['status' => 400, 'error' => 'Invalid action'];
}

echo json_encode($response);
?>