<?php
/**
 * AI Model Training - Historical data collector
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// In production, this would fetch from your database
// For demo, using simulated training data

$trainingData = [
    'fuel_telemetry' => [
        ['timestamp' => '2026-05-20', 'fuel_percentage' => 85],
        ['timestamp' => '2026-05-21', 'fuel_percentage' => 82],
        ['timestamp' => '2026-05-22', 'fuel_percentage' => 78],
        ['timestamp' => '2026-05-23', 'fuel_percentage' => 75],
        ['timestamp' => '2026-05-24', 'fuel_percentage' => 72],
        ['timestamp' => '2026-05-25', 'fuel_percentage' => 68],
        ['timestamp' => '2026-05-26', 'fuel_percentage' => 65],
        ['timestamp' => '2026-05-27', 'fuel_percentage' => 62]
    ],
    'maintenance_logs' => [
        ['vehicle_id' => 'TRK001', 'last_service' => '2026-05-01', 'service_type' => 'Oil Change'],
        ['vehicle_id' => 'TRK002', 'last_service' => '2026-04-15', 'service_type' => 'Brake Service'],
        ['vehicle_id' => 'TRK003', 'last_service' => '2026-05-10', 'service_type' => 'Tire Rotation']
    ]
];

echo json_encode([
    'status' => 200,
    'message' => 'Training data retrieved successfully',
    'data' => $trainingData,
    'data_points' => count($trainingData['fuel_telemetry']),
    'features' => ['fuel_percentage', 'timestamp', 'vehicle_id', 'service_type']
]);
?>