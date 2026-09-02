<?php
header("Access-Control-Allow-Origin: https://kamelking2028-coder.github.io");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$config = include("../config.php");
$HF_KEY = $config["HF_KEY"] ?? null;

if (!$HF_KEY) {
    echo json_encode(["error" => "missing HF_KEY"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$prompt = $input["prompt"] ?? null;
$model  = $input["model"] ?? "sd15";

if (!$prompt) {
    echo json_encode(["error" => "no prompt"]);
    exit;
}

switch ($model) {
    case "openjourney":
        $api_url = "https://api-inference.huggingface.co/models/prompthero/openjourney-v4";
        $response_type = "binary";
        break;

    case "sd3":
        $api_url = "https://api-inference.huggingface.co/models/stabilityai/stable-diffusion-3-medium";
        $response_type = "json";
        break;

    case "sd15":
    default:
        $api_url = "https://api-inference.huggingface.co/models/runwayml/stable-diffusion-v1-5";
        $response_type = "binary";
        break;
}

$headers = [
    "Authorization: Bearer $HF_KEY",
    "Content-Type: application/json"
];

$data = [
    "inputs" => $prompt,
    "options" => ["use_cache" => false]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$result   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($result === false) {
    echo json_encode(["error" => curl_error($ch)]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode([
        "error" => "HuggingFace error",
        "code"  => $httpCode,
        "raw"   => $result
    ]);
    exit;
}

if ($response_type === "binary") {
    $image_base64 = base64_encode($result);
} else {
    $json = json_decode($result, true);
    $image_base64 = $json[0]["generated_image"] ?? null;
}

echo json_encode([
    "model"        => $model,
    "image_base64" => $image_base64
]);
exit;
