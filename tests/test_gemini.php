<?php
$api_key = getenv('GEMINI_API_KEY') ?: '';
if ($api_key === '') {
    fwrite(STDERR, "GEMINI_API_KEY is not configured.\n");
    exit(1);
}
$ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["contents"=>[["parts"=>[["text"=>"Hello"]]]]]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
if(curl_errno($ch)){
    echo "cURL Error: " . curl_error($ch);
} else {
    echo "Response: " . substr($response, 0, 500);
}
curl_close($ch);



