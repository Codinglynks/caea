<?php
require 'vendor/autoload.php';
$WEB_CLIENT_ID = '196751137283-vg0urefv51ntaokjckck5qjki6lk966d.apps.googleusercontent.com';
// Get $id_token via HTTPS POST.
header('Content-Type: application/json');
// Read JSON input or fallback to standard form POST data
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
    // This ID is unique to each Google Account, making it suitable for use as a primary key
    // during account lookup. Email is not a good choice because it can be changed by the user.
    echo json_encode(['message' => 'ID token is valid', 'email' => $payload['email']]);
    $file = fopen("test.csv","r");
    print_r(fgetcsv($file));
    fclose($file);
    // If the request specified a Google Workspace domain
    //$domain = $payload['hd'];
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid ID token']);
    exit;
}


