<?php
// test.php - Place this in your fuelgued folder
header("Content-Type: application/json");
echo json_encode([
    'success' => true,
    'message' => 'PHP backend is working!',
    'timestamp' => date('c')
]);