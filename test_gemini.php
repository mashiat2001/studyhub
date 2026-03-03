<?php
$api_key = 'AIzaSyCfNplW7PiCLDkGjn-ucr5AJ5N8uLuKNrQ'; // Your key
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Say hello in one word']
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code<br>";
if ($curl_error) echo "cURL Error: $curl_error<br>";
echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";
?>