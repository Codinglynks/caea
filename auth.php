<?php
require 'vendor/autoload.php';

header('Content-Type: application/json');

$WEB_CLIENT_ID = '196751137283-vg0urefv51ntaokjckck5qjki6lk966d.apps.googleusercontent.com';
$domains = "eleves-alsacienne.org";

// Récupération du token
$input = json_decode(file_get_contents('php://input'), true);
$id_token = $input['token'] ?? $_POST['token'] ?? null;

if (!$id_token) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ID token']);
    exit;
}

// Vérification du token Google
$client = new Google_Client(['client_id' => $WEB_CLIENT_ID]);
try {
    $payload = $client->verifyIdToken($id_token);
} catch (Exception $e) {
    error_log('Token verification failed: ' . $e->getMessage());
    http_response_code(401);
    echo json_encode(['error' => 'Invalid ID token']);
    exit;
}

if (!$payload) {
    http_response_code(401);
    echo json_encode(['error' => 'No payload found in ID token']);
    exit;
}

if (!isset($payload['hd']) || $payload['hd'] !== $domains) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied: Invalid domain']);
    exit;
}

// L'email sert de clé de recherche : refuser s'il n'est pas vérifié par Google.
if (empty($payload['email_verified'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied: Email not verified']);
    exit;
}


// Assignation de la variable recherchée
$targetEmail = trim($payload['email'] ?? '');

// Ouverture du fichier CSV
$handle = fopen("results.csv", "r");
if (!$handle) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to open the database']);
    exit;
}

$headers = fgetcsv($handle, 1000, ',');
$emailIndex = array_search('Nom et prénom', $headers);
$websiteIndex = array_search('Score', $headers);
$fluidIndex = array_search('Fluidité', $headers);

if ($emailIndex === false || $websiteIndex === false || $fluidIndex === false) {
    fclose($handle);
    http_response_code(500);
    echo json_encode(['error' => 'Required columns not found in CSV']);
    exit;
}

$points = null;
$fluid = null;

// Parcours du fichier CSV
while (($data = fgetcsv($handle, 1000)) !== false) {
    if (isset($data[$emailIndex]) && trim($data[$emailIndex]) === $targetEmail) {
        $points = $data[$websiteIndex] ?? null;
        $fluid = $data[$fluidIndex] ?? null ;
        break;
    }
}

fclose($handle);

// Envoi de la réponse JSON finale
if ($points !== null) {
    echo json_encode([
        'status' => 'success',
        'email' => $targetEmail,
        'points' => $points,
        'fluid' => $fluid
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        'error' => 'No data found for this email',
        'email' => $targetEmail
    ]);
}