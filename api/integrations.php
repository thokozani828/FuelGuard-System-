<?php
/**
 * FleetGuard Pro - Integrations API
 * Manages third-party integrations and webhooks
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config.php';

function supabaseRequest($method, $table, $params = [], $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $table;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json'
    ];
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Create integrations table if not exists
function setupIntegrationsTable() {
    $sql = "
    CREATE TABLE IF NOT EXISTS integrations (
        id BIGSERIAL PRIMARY KEY,
        integration_id TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        config JSONB,
        status TEXT DEFAULT 'disconnected',
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP DEFAULT NOW()
    );
    
    CREATE TABLE IF NOT EXISTS webhooks (
        id BIGSERIAL PRIMARY KEY,
        url TEXT NOT NULL,
        events TEXT,
        is_active BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT NOW()
    );
    ";
    // Note: This SQL needs to be run manually in Supabase SQL editor
    return true;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$response = [];

switch ($action) {
    case 'getIntegrations':
        $result = supabaseRequest('GET', 'integrations');
        $response = ['status' => 200, 'data' => $result];
        break;
        
    case 'saveIntegration':
        if ($method !== 'POST') {
            $response = ['status' => 405, 'error' => 'Method not allowed'];
        } else {
            $integrationId = $input['integration_id'] ?? null;
            $existing = supabaseRequest('GET', 'integrations', ['integration_id' => 'eq.' . $integrationId]);
            
            if (!empty($existing)) {
                $result = supabaseRequest('PATCH', 'integrations', ['integration_id' => 'eq.' . $integrationId], [
                    'config' => $input['config'],
                    'status' => $input['status'] ?? 'connected',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                $result = supabaseRequest('POST', 'integrations', [], [
                    'integration_id' => $integrationId,
                    'name' => $input['name'],
                    'config' => $input['config'],
                    'status' => 'connected'
                ]);
            }
            $response = ['status' => 200, 'message' => 'Integration saved successfully'];
        }
        break;
        
    case 'getWebhooks':
        $result = supabaseRequest('GET', 'webhooks');
        $response = ['status' => 200, 'data' => $result];
        break;
        
    case 'addWebhook':
        if ($method !== 'POST') {
            $response = ['status' => 405, 'error' => 'Method not allowed'];
        } else {
            $result = supabaseRequest('POST', 'webhooks', [], [
                'url' => $input['url'],
                'events' => $input['events'] ?? 'all'
            ]);
            $response = ['status' => 201, 'message' => 'Webhook added successfully'];
        }
        break;
        
    case 'deleteWebhook':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $response = ['status' => 400, 'error' => 'Webhook ID required'];
        } else {
            $result = supabaseRequest('DELETE', 'webhooks', ['id' => 'eq.' . $id]);
            $response = ['status' => 200, 'message' => 'Webhook deleted successfully'];
        }
        break;
        
    case 'test':
        $response = [
            'status' => 200,
            'message' => 'Integrations API is working',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        break;
        
    default:
        $response = ['status' => 400, 'error' => 'Invalid action'];
}

echo json_encode($response);
?>