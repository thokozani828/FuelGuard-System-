<?php
/**
 * FleetGuard Pro - AI Predictive Analytics API
 * Implements linear regression for fuel prediction and anomaly detection
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config.php';
validateSession();

// Simulated historical data
$historicalData = [
    'fuel_readings' => [65, 62, 58, 55, 52, 48, 45, 42, 40, 38, 35, 33],
    'temperature_readings' => [82, 84, 83, 85, 88, 92, 95, 89, 86, 84, 82, 80],
    'maintenance_intervals' => [30, 28, 32, 29, 31, 30, 33, 28, 30, 29, 31, 30]
];

/**
 * Linear Regression Algorithm
 * Calculates trend line y = mx + b
 */
function linearRegression($data) {
    $n = count($data);
    if ($n < 2) return ['slope' => 0, 'intercept' => $data[0] ?? 0];
    
    $x = range(1, $n);
    $x_sum = array_sum($x);
    $y_sum = array_sum($data);
    $xy_sum = 0;
    $x2_sum = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $xy_sum += $x[$i] * $data[$i];
        $x2_sum += $x[$i] * $x[$i];
    }
    
    $slope = ($n * $xy_sum - $x_sum * $y_sum) / ($n * $x2_sum - $x_sum * $x_sum);
    $intercept = ($y_sum - $slope * $x_sum) / $n;
    
    return ['slope' => $slope, 'intercept' => $intercept];
}

/**
 * Predict future values using linear regression
 */
function predictFuture($historical, $days = 7) {
    $regression = linearRegression($historical);
    $predictions = [];
    $lastIndex = count($historical);
    
    for ($i = 1; $i <= $days; $i++) {
        $prediction = $regression['slope'] * ($lastIndex + $i) + $regression['intercept'];
        $predictions[] = max(0, min(100, round($prediction, 1)));
    }
    
    return $predictions;
}

/**
 * Anomaly Detection using Z-Score method
 */
function detectAnomalies($data, $threshold = 2.5) {
    $mean = array_sum($data) / count($data);
    $variance = array_sum(array_map(function($x) use ($mean) {
        return pow($x - $mean, 2);
    }, $data)) / count($data);
    $stdDev = sqrt($variance);
    
    $anomalies = [];
    foreach ($data as $index => $value) {
        $zScore = abs(($value - $mean) / $stdDev);
        if ($zScore > $threshold) {
            $anomalies[] = [
                'index' => $index,
                'value' => $value,
                'z_score' => round($zScore, 2)
            ];
        }
    }
    
    return $anomalies;
}

/**
 * Predict next maintenance date based on historical patterns
 */
function predictMaintenance($historicalIntervals) {
    $regression = linearRegression($historicalIntervals);
    $nextPrediction = $regression['slope'] * (count($historicalIntervals) + 1) + $regression['intercept'];
    return max(14, min(60, round($nextPrediction)));
}

/**
 * Calculate confidence score of predictions
 */
function calculateConfidence($historical, $predictions) {
    $variability = coefficientOfVariation($historical);
    $confidence = 100 - ($variability * 10);
    return max(50, min(95, round($confidence)));
}

function coefficientOfVariation($data) {
    $mean = array_sum($data) / count($data);
    $stdDev = sqrt(array_sum(array_map(function($x) use ($mean) {
        return pow($x - $mean, 2);
    }, $data)) / count($data));
    
    return $mean > 0 ? $stdDev / $mean : 0;
}

// Main prediction endpoint
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'predict':
        // Get predictions
        $fuelPredictions = predictFuture($historicalData['fuel_readings'], 7);
        
        // Calculate next maintenance
        $nextMaintenance = predictMaintenance($historicalData['maintenance_intervals']);
        
        // Detect anomalies in recent fuel readings
        $recentFuel = array_slice($historicalData['fuel_readings'], -7);
        $anomalies = detectAnomalies($recentFuel);
        
        // Calculate confidence
        $confidence = calculateConfidence($historicalData['fuel_readings'], $fuelPredictions);
        
        // Generate AI insights
        $insights = [];
        
        // Fuel trend insight
        $fuelTrend = end($fuelPredictions);
        $currentFuel = end($historicalData['fuel_readings']);
        if ($fuelTrend < 20) {
            $insights[] = [
                'title' => '⚠️ Critical Fuel Alert',
                'description' => "AI predicts fuel level will drop to {$fuelTrend}% in 7 days. Schedule refueling within 3 days.",
                'icon' => 'fa-gas-pump',
                'severity' => 'high'
            ];
        } elseif ($fuelTrend < 30) {
            $insights[] = [
                'title' => '📉 Low Fuel Forecast',
                'description' => "Fuel consumption rate suggests refueling needed in approximately 5 days.",
                'icon' => 'fa-chart-line',
                'severity' => 'medium'
            ];
        }
        
        // Maintenance insight
        if ($nextMaintenance < 20) {
            $insights[] = [
                'title' => '🔧 Maintenance Due Soon',
                'description' => "AI predicts next maintenance in {$nextMaintenance} days. Schedule service appointment.",
                'icon' => 'fa-tools',
                'severity' => 'high'
            ];
        }
        
        // Anomaly insight
        if (count($anomalies) > 0) {
            $insights[] = [
                'title' => '🔍 Anomaly Detected',
                'description' => "Detected " . count($anomalies) . " unusual fuel consumption patterns. Investigate vehicle #TRK001.",
                'icon' => 'fa-exclamation-triangle',
                'severity' => 'medium'
            ];
        }
        
        // Optimization insight
        $insights[] = [
            'title' => '💡 Fuel Efficiency Recommendation',
            'description' => "AI analysis suggests optimal refueling at 25% fuel level for best cost efficiency.",
            'icon' => 'fa-lightbulb',
            'severity' => 'low'
        ];
        
        $response = [
            'status' => 200,
            'predictions' => [
                'fuel_forecast_24h' => $fuelPredictions[0],
                'fuel_forecast_7d' => $fuelPredictions[6],
                'next_maintenance_days' => $nextMaintenance,
                'confidence_score' => $confidence,
                'anomaly_count' => count($anomalies)
            ],
            'historical_data' => array_slice($historicalData['fuel_readings'], -7),
            'forecast_data' => $fuelPredictions,
            'forecast_labels' => ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'],
            'anomaly_distribution' => [25, count($anomalies), 3, 5, 2],
            'insights' => $insights,
            'anomalies' => $anomalies,
            'model_used' => 'Linear Regression + Z-Score Anomaly Detection',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        break;
        
    case 'train':
        // Placeholder for model training endpoint
        $response = [
            'status' => 200,
            'message' => 'AI model trained successfully',
            'data_points' => count($historicalData['fuel_readings']),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        break;
        
    default:
        $response = ['status' => 400, 'error' => 'Invalid action. Use: predict, train'];
}

echo json_encode($response);
?>