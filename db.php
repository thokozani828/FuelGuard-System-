<?php
require_once __DIR__ . '/config.php';

$host = DB_HOST;
$port = DB_PORT;
$dbname = DB_NAME;
$user = DB_USER;
$password = DB_PASS;

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Removed "Connected successfully" echo to prevent JSON corruption in API calls
} catch (PDOException $e) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $e->getMessage()]));
}
?>