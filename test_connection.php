<?php
define('SUPABASE_URL', 'https://shdaldiqnbtlgjajxroi.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNoZGFsZGlxbmJ0bGdqYWp4cm9pIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3OTAzMDQxNywiZXhwIjoyMDk0NjA2NDE3fQ.Skqplb1v6dMHGMUl0vnigT2QVyWpRq_7e4azjWbAfxk');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/rest/v1/fuelguard_users?limit=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: " . $httpCode . "<br>";
if ($httpCode === 200) {
    echo "✅ Connection successful!<br>";
    echo "Response: " . $response;
} else {
    echo "❌ Error: " . $response;
}
?>