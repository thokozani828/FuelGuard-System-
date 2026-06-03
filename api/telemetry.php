<?php
/**
 * FleetGuard Pro - Telemetry API
 * Real-time fuel telemetry data from Supabase
 */

define('SUPABASE_URL', 'https://shdaldiqnbtlgjajxroi.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNoZGFsZGlxbmJ0bGdqYWp4cm9pIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzkwMzA0MTcsImV4cCI6MjA5NDYwNjQxN30.BDRnisUkar6CaBKc0-AI6IXw16yfgjrkqEv59PWkJIo');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
}

class TelemetryManager {
    private $supabase;
    
    public function __construct() {
        $this->supabase = new SupabaseAPI();
    }
    
    public function getLatestTelemetry($limit = 50) {
        try {
            $params = [
                'select' => '*',
                'order' => 'timestamp.desc',
                'limit' => $limit
            ];
            
            $result = $this->supabase->get('fuel_telemetry', $params);
            
            if ($result['status'] === 200) {
                $stats = $this->calculateStats($result['data']);
                return ['status' => 200, 'data' => $result['data'], 'stats' => $stats];
            }
            return $result;
        } catch (Exception $e) {
            return ['status' => 500, 'error' => $e->getMessage()];
        }
    }
    
    public function getHistoricalTelemetry($vehicleId, $limit = 20) {
        try {
            $params = [
                'select' => '*',
                'truck_id' => 'eq.' . $vehicleId,
                'order' => 'timestamp.asc',
                'limit' => $limit
            ];
            
            $result = $this->supabase->get('fuel_telemetry', $params);
            
            if ($result['status'] === 200) {
                return ['status' => 200, 'data' => $result['data']];
            }
            return $result;
        } catch (Exception $e) {
            return ['status' => 500, 'error' => $e->getMessage()];
        }
    }
    
    public function getVehicles() {
        try {
            $result = $this->supabase->get('fuel_telemetry', [
                'select' => 'truck_id',
                'truck_id' => 'not.is.null'
            ]);
            
            $vehicles = [];
            if ($result['status'] === 200 && $result['data']) {
                foreach ($result['data'] as $item) {
                    if (!empty($item['truck_id']) && !in_array($item['truck_id'], $vehicles)) {
                        $vehicles[] = $item['truck_id'];
                    }
                }
            }
            
            return ['status' => 200, 'data' => $vehicles];
        } catch (Exception $e) {
            return ['status' => 500, 'error' => $e->getMessage()];
        }
    }
    
    private function calculateStats($data) {
        if (empty($data)) {
            return [
                'active_vehicles' => 0,
                'avg_fuel' => 0,
                'total_fuel_volume' => 0,
                'critical_count' => 0,
                'low_fuel_count' => 0
            ];
        }
        
        $uniqueVehicles = [];
        $totalFuel = 0;
        $totalVolume = 0;
        $criticalCount = 0;
        $lowFuelCount = 0;
        
        foreach ($data as $record) {
            if (!empty($record['truck_id'])) {
                $uniqueVehicles[$record['truck_id']] = true;
            }
            
            $fuel = floatval($record['fuel_percentage'] ?? 0);
            $totalFuel += $fuel;
            $totalVolume += floatval($record['fuel_volume_liters'] ?? 0);
            
            if ($fuel < 5) $criticalCount++;
            elseif ($fuel < 25) $lowFuelCount++;
        }
        
        return [
            'active_vehicles' => count($uniqueVehicles),
            'avg_fuel' => count($data) > 0 ? $totalFuel / count($data) : 0,
            'total_fuel_volume' => $totalVolume,
            'critical_count' => $criticalCount,
            'low_fuel_count' => $lowFuelCount
        ];
    }
    
    public function exportTelemetry() {
        $result = $this->getLatestTelemetry(1000);
        if ($result['status'] !== 200) {
            http_response_code(500);
            echo json_encode($result);
            exit();
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="telemetry_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Timestamp', 'Device ID', 'Truck ID', 'Driver Name', 'License Plate', 
                          'Fuel Percentage', 'Fuel Volume (L)', 'Fuel Level (cm)', 'Temperature (°C)', 'Status']);
        
        foreach ($result['data'] as $record) {
            fputcsv($output, [
                $record['timestamp'] ?? '',
                $record['device_id'] ?? '',
                $record['truck_id'] ?? '',
                $record['driver_name'] ?? '',
                $record['license_plate'] ?? '',
                $record['fuel_percentage'] ?? 0,
                $record['fuel_volume_liters'] ?? 0,
                $record['fuel_level_cm'] ?? 0,
                $record['temperature_c'] ?? '',
                $record['status'] ?? ''
            ]);
        }
        
        fclose($output);
        exit();
    }
}

$manager = new TelemetryManager();
$action = $_GET['action'] ?? '';

$response = [];

switch ($action) {
    case 'getLatest':
        $limit = $_GET['limit'] ?? 50;
        $response = $manager->getLatestTelemetry($limit);
        break;
        
    case 'getHistorical':
        $vehicleId = $_GET['vehicle'] ?? '';
        if (empty($vehicleId)) {
            $response = ['status' => 400, 'error' => 'Vehicle ID required'];
        } else {
            $limit = $_GET['limit'] ?? 20;
            $response = $manager->getHistoricalTelemetry($vehicleId, $limit);
        }
        break;
        
    case 'getVehicles':
        $response = $manager->getVehicles();
        break;
        
    case 'export':
        $manager->exportTelemetry();
        exit();
        
    default:
        $response = ['status' => 400, 'error' => 'Invalid action. Available: getLatest, getHistorical, getVehicles, export'];
}

http_response_code($response['status'] ?? 200);
echo json_encode($response);
?>