<?php
$api_key = 'AIzaSyCfNplW7PiCLDkGjn-ucr5AJ5N8uLuKNrQ';
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code<br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";
?>