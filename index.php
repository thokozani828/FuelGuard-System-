<?php
header('Content-Type: application/json');
echo json_encode([
    "status" => "online",
    "service" => "FuelGuard Backend API",
    "version" => "1.0.0",
    "message" => "Connection successful. Use /register.php or /login.php for authentication."
]);
?>