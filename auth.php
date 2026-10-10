<?php
require 'vendor/autoload.php';
$WEB_CLIENT_ID = '196751137283-vg0urefv51ntaokjckck5qjki6lk966d.apps.googleusercontent.com';
// Get $id_token via HTTPS POST.
header('Content-Type: application/json');
// Read JSON input or fallback to standard form POST data
$domains = "ecole-alsacienne.org";
$input = json_decode(file_get_contents('php://input'), true);
$id_token = $input['token'] ?? $_POST['token'] ?? null;

if (!$id_token) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ID token']);
    exit;
}

$client = new Google_Client(['client_id' => $WEB_CLIENT_ID]);  // Specify the WEB_CLIENT_ID of the app that accesses the backend
$payload = $client->verifyIdToken($id_token);
if ($payload) {
    /*if (!$payload || $payload['hd'] !== $domains) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied: Invalid domain']);
        exit;
    }*/
    echo json_encode(['message' => 'ID token is valid', 'email' => $payload['email']]);
    $handle = fopen("customers-100.csv", "r");
    if (!$handle) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to open the database, this is on our side']);
    }
    else {

    }



    // If the request specified a Google Workspace domain
    //$domain = $pa

} else {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid ID token']);
    exit;
}


