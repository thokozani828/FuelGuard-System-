<?php
require_once __DIR__ . '/config.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/rest/v1/fuelguard_users?limit=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>Supabase Connection Test</h2>";
echo "URL: " . SUPABASE_URL . "<br>";
echo "HTTP Code: " . $httpCode . "<br>";
echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";
?>