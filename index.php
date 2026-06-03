<?php
/**
 * FleetGuard Pro API Router
 * Handles all API requests and routes to appropriate endpoints
 */

// Set CORS headers for cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the request path
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Remove the script name from the URI to get the API path
$api_path = str_replace($script_name, '', $request_uri);
$api_path = strtok($api_path, '?'); // Remove query string parameters
$api_path = trim($api_path, '/');

// Split the path into segments
$segments = empty($api_path) ? [] : explode('/', $api_path);

// Debug logging (remove in production)
error_log("API Path: " . $api_path);
error_log("Segments: " . print_r($segments, true));

// Initialize response array
$response = [];

try {
    // Route based on the first path segment
    if (empty($segments)) {
        // API root endpoint - return available endpoints
        $response = [
            'success' => true,
            'message' => 'FleetGuard Pro API v1.3.1',
            'version' => '1.3.1',
            'timestamp' => date('c'),
            'endpoints' => [
                'GET /trucks' => 'Get all trucks',
                'POST /trucks' => 'Create new truck',
                'GET /trucks/{id}' => 'Get single truck',
                'PUT /trucks/{id}' => 'Update truck',
                'DELETE /trucks/{id}' => 'Delete truck',
                'GET /dashboard' => 'Dashboard statistics',
                'GET /health' => 'Health check',
                'GET /sensors' => 'Get all sensors',
                'GET /alerts' => 'Get all alerts',
                'GET /refueling' => 'Get refueling logs',
                'GET /maintenance' => 'Get maintenance logs',
                'GET /telemetry' => 'Get telemetry data'
            ]
        ];
    } else {
        $resource = $segments[0];
        $id = isset($segments[1]) ? $segments[1] : null;
        $sub_resource = isset($segments[2]) ? $segments[2] : null;
        
        // Route to appropriate handler based on resource
        switch ($resource) {
            case 'trucks':
                if (file_exists(__DIR__ . '/api/trucks.php')) {
                    require_once __DIR__ . '/api/trucks.php';
                    $response = handleTrucksRequest($_SERVER['REQUEST_METHOD'], $id, $sub_resource);
                } else {
                    $response = [
                        'success' => true, 
                        'data' => getSampleTrucks(), 
                        'count' => count(getSampleTrucks()),
                        'message' => 'Using sample data (api/trucks.php not found)'
                    ];
                }
                break;
                
            case 'dashboard':
                if (file_exists(__DIR__ . '/api/dashboard.php')) {
                    require_once __DIR__ . '/api/dashboard.php';
                    $response = handleDashboardRequest($_SERVER['REQUEST_METHOD']);
                } else {
                    $response = [
                        'success' => true, 
                        'data' => [
                            'total_trucks' => 5,
                            'active_trucks' => 4,
                            'inactive_trucks' => 1,
                            'active_alerts' => 2,
                            'total_refueling_today' => 3,
                            'api_requests' => 847,
                            'uptime_percentage' => 99.9,
                            'last_updated' => date('c')
                        ],
                        'message' => 'Using sample dashboard data'
                    ];
                }
                break;
                
            case 'sensors':
                if (file_exists(__DIR__ . '/api/sensors.php')) {
                    require_once __DIR__ . '/api/sensors.php';
                    $response = handleSensorsRequest($_SERVER['REQUEST_METHOD'], $id);
                } else {
                    $response = [
                        'success' => true, 
                        'data' => getSampleSensors(), 
                        'count' => count(getSampleSensors()),
                        'message' => 'Using sample sensor data'
                    ];
                }
                break;
                
            case 'alerts':
                if (file_exists(__DIR__ . '/api/alerts.php')) {
                    require_once __DIR__ . '/api/alerts.php';
                    $response = handleAlertsRequest($_SERVER['REQUEST_METHOD'], $id);
                } else {
                    $response = [
                        'success' => true, 
                        'data' => getSampleAlerts(), 
                        'count' => count(getSampleAlerts()),
                        'message' => 'Using sample alert data'
                    ];
                }
                break;
                
            case 'refueling':
                if (file_exists(__DIR__ . '/api/refueling.php')) {
                    require_once __DIR__ . '/api/refueling.php';
                    $response = handleRefuelingRequest($_SERVER['REQUEST_METHOD'], $id);
                } else {
                    $response = [
                        'success' => true, 
                        'data' => getSampleRefueling(), 
                        'count' => count(getSampleRefueling()),
                        'message' => 'Using sample refueling data'
                    ];
                }
                break;
                
            case 'maintenance':
                if (file_exists(__DIR__ . '/api/maintenance.php')) {
                    require_once __DIR__ . '/api/maintenance.php';
                    $response = handleMaintenanceRequest($_SERVER['REQUEST_METHOD'], $id);
                } else {
                    $response = [
                        'success' => true, 
                        'data' => getSampleMaintenance(), 
                        'count' => count(getSampleMaintenance()),
                        'message' => 'Using sample maintenance data'
                    ];
                }
                break;
                
            case 'telemetry':
                if (file_exists(__DIR__ . '/api/telemetry.php')) {
                    require_once __DIR__ . '/api/telemetry.php';
                    $response = handleTelemetryRequest($_SERVER['REQUEST_METHOD'], $id);
                } else {
                    $response = [
                        'success' => true, 
                        'data' => [],
                        'message' => 'Telemetry endpoint (no sample data available)'
                    ];
                }
                break;
                
            case 'health':
                $response = [
                    'success' => true, 
                    'status' => 'healthy', 
                    'timestamp' => date('c'),
                    'uptime_seconds' => $_SERVER['REQUEST_TIME'] ?? null,
                    'php_version' => phpversion()
                ];
                break;
                
            default:
                http_response_code(404);
                $response = [
                    'success' => false, 
                    'error' => "Endpoint not found: {$resource}",
                    'available_endpoints' => [
                        'trucks', 'dashboard', 'sensors', 'alerts', 
                        'refueling', 'maintenance', 'telemetry', 'health'
                    ]
                ];
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false, 
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(),
        'trace' => defined('DEBUG') && DEBUG ? $e->getTraceAsString() : null
    ];
}

// Output the JSON response
echo json_encode($response);

// ----------------------------------------------------------------------------
// Sample Data Functions (Fallback when API files don't exist)
// ----------------------------------------------------------------------------

/**
 * Get sample truck data
 */
function getSampleTrucks() {
    return [
        [
            'truck_id' => 'TRK001', 
            'license_plate' => 'ABC-1234', 
            'driver_name' => 'John Smith', 
            'fuel_percentage' => 75, 
            'status' => 'ACTIVE',
            'last_maintenance' => '2024-12-15'
        ],
        [
            'truck_id' => 'TRK002', 
            'license_plate' => 'XYZ-5678', 
            'driver_name' => 'Sarah Johnson', 
            'fuel_percentage' => 60, 
            'status' => 'ACTIVE',
            'last_maintenance' => '2025-01-10'
        ],
        [
            'truck_id' => 'TRK003', 
            'license_plate' => 'DEF-9012', 
            'driver_name' => 'Mike Wilson', 
            'fuel_percentage' => 30, 
            'status' => 'MAINTENANCE',
            'last_maintenance' => '2025-01-20'
        ]
    ];
}

/**
 * Get sample sensor data
 */
function getSampleSensors() {
    return [
        [
            'sensor_id' => 'SENSOR_001', 
            'sensor_type' => 'Ultrasonic', 
            'assigned_to' => 'TRK001', 
            'status' => 'ASSIGNED',
            'battery_level' => 85,
            'last_reading' => date('c')
        ],
        [
            'sensor_id' => 'SENSOR_002', 
            'sensor_type' => 'Ultrasonic', 
            'assigned_to' => 'TRK002', 
            'status' => 'ASSIGNED',
            'battery_level' => 92,
            'last_reading' => date('c')
        ],
        [
            'sensor_id' => 'SENSOR_003', 
            'sensor_type' => 'Radar', 
            'assigned_to' => null, 
            'status' => 'AVAILABLE',
            'battery_level' => 100,
            'last_reading' => null
        ]
    ];
}

/**
 * Get sample alert data
 */
function getSampleAlerts() {
    return [
        [
            'id' => 1, 
            'alert_type' => 'critical', 
            'title' => 'Low Fuel Alert', 
            'message' => 'TRK001 fuel level at 15% - Immediate refueling recommended', 
            'truck_id' => 'TRK001', 
            'is_resolved' => false,
            'created_at' => date('c', strtotime('-2 hours'))
        ],
        [
            'id' => 2, 
            'alert_type' => 'warning', 
            'title' => 'Maintenance Due', 
            'message' => 'TRK002 is due for scheduled maintenance in 3 days', 
            'truck_id' => 'TRK002', 
            'is_resolved' => false,
            'created_at' => date('c', strtotime('-1 day'))
        ]
    ];
}

/**
 * Get sample refueling data
 */
function getSampleRefueling() {
    return [
        [
            'id' => 1,
            'date' => '2025-01-15', 
            'truck_id' => 'TRK001', 
            'amount_liters' => 280, 
            'cost' => 420.00,
            'location' => 'Johannesburg Depot'
        ],
        [
            'id' => 2,
            'date' => '2025-01-20', 
            'truck_id' => 'TRK002', 
            'amount_liters' => 350, 
            'cost' => 525.00,
            'location' => 'Cape Town Depot'
        ]
    ];
}

/**
 * Get sample maintenance data
 */
function getSampleMaintenance() {
    return [
        [
            'id' => 1,
            'date' => '2025-01-10', 
            'truck_id' => 'TRK003', 
            'maintenance_type' => 'Oil Change', 
            'cost' => 150.00, 
            'next_due_date' => '2025-04-10',
            'mechanic' => 'AutoCare Services'
        ],
        [
            'id' => 2,
            'date' => '2025-01-05', 
            'truck_id' => 'TRK001', 
            'maintenance_type' => 'Tire Rotation', 
            'cost' => 80.00, 
            'next_due_date' => '2025-04-05',
            'mechanic' => 'Fleet Maintenance Co.'
        ]
    ];
}
?>