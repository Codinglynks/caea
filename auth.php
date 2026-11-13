<?php
require 'vendor/autoload.php';

header('Content-Type: application/json');

$WEB_CLIENT_ID = '196751137283-vg0urefv51ntaokjckck5qjki6lk966d.apps.googleusercontent.com';
$domains = "ecole-alsacienne.org";

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
$payload = $client->verifyIdToken($id_token);

if (!$payload) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid ID token']);
    exit;
}

// Optionnel : vérification du domaine (décommenter si besoin)

if (!isset($payload['hd']) || $payload['hd'] !== $domains) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied: Invalid domain']);
    exit;
}


// Assignation de la variable recherchée
$targetEmail = trim($payload['email'] ?? '');

// Ouverture du fichier CSV
$handle = fopen("dataset.csv", "r");
if (!$handle) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to open the database']);
    exit;
}

$headers = fgetcsv($handle, 1000, ',');
$emailIndex = array_search('Email', $headers);
$websiteIndex = array_search('Website', $headers);

if ($emailIndex === false || $websiteIndex === false) {
    fclose($handle);
    http_response_code(500);
    echo json_encode(['error' => 'Required columns not found in CSV']);
    exit;
}

$website = null;

// Parcours du fichier CSV
while (($data = fgetcsv($handle, 1000, ',')) !== false) {
    if (isset($data[$emailIndex]) && trim($data[$emailIndex]) === $targetEmail) {
        $website = $data[$websiteIndex] ?? 'Non renseigné';
        break;
    }
}

fclose($handle);

// Envoi de la réponse JSON finale
if ($website !== null) {
    echo json_encode([
        'status' => 'success',
        'email' => $targetEmail,
        'website' => $website
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        'error' => 'No data found for this email',
        'email' => $targetEmail
    ]);
}