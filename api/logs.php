<?php

header("Content-Type: application/json");

include "../db.php";

$data = json_decode(file_get_contents("php://input"), true);

$vehicle_id = $data['vehicle_id'];
$distance = $data['sensor_distance_cm'];

$tank_height = 100; // cm
$tank_capacity = 80; // litres

$fuel_height = $tank_height - $distance;
$fuel_percent = ($fuel_height / $tank_height) * 100;
$fuel_volume = ($fuel_percent / 100) * $tank_capacity;

$sql = "INSERT INTO telemetry_logs
(vehicle_id, sensor_distance_cm, fuel_level_percent, fuel_volume_litres)
VALUES (:vehicle_id, :distance, :percent, :volume)";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':vehicle_id' => $vehicle_id,
    ':distance' => $distance,
    ':percent' => $fuel_percent,
    ':volume' => $fuel_volume
]);

echo json_encode([
    "message" => "Telemetry log saved successfully",
    "fuel_level_percent" => round($fuel_percent, 2),
    "fuel_volume_litres" => round($fuel_volume, 2)
]);

?>