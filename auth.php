<?php
/**
 * auth.php — Back end of the CAEA site.
 *
 * Receives a Google ID token from the front end (index.html), checks with
 * Google that the token is genuine, then looks up the signed-in user's email
 * in the CSV "database" and answers with a JSON object.
 *
 * Responses:
 *   200 {"status":"success","email":"…","website":"…"}  user found
 *   400 {"error":"Missing ID token"}                     no token sent
 *   401 {"error":"Invalid ID token"}                     token rejected by Google
 *   403 {"error":"Access denied: Invalid domain"}        wrong domain (check currently disabled)
 *   404 {"error":"No website found …","email":"…"}       email not in the CSV
 *   500 {"error":"…"}                                    CSV missing or malformed
 *
 * See DOCUMENTATION.md for the full flow and known issues.
 */

// Load the Composer autoloader so the Google API client library (google/apiclient) is available.
require 'vendor/autoload.php';

// Tell the browser that everything this script prints is JSON.
header('Content-Type: application/json');

// OAuth client ID from the Google Cloud console; must match data-client_id in index.html.
$WEB_CLIENT_ID = '196751137283-vg0urefv51ntaokjckck5qjki6lk966d.apps.googleusercontent.com';
// Google Workspace domain allowed to sign in (used by the domain check below).
$domains = "ecole-alsacienne.org";

// Récupération du token
// Read the raw request body and decode it as JSON into an associative array (index.html sends {"token": "…"}).
$input = json_decode(file_get_contents('php://input'), true);
// Take the token from the JSON body, or fall back to a classic form POST field, or null if neither exists.
$id_token = $input['token'] ?? $_POST['token'] ?? null;

// No token at all: the request is malformed.
if (!$id_token) {
    // 400 Bad Request.
    http_response_code(400);
    // Explain the problem to the front end.
    echo json_encode(['error' => 'Missing ID token']);
    // Stop the script here.
    exit;
}

// Vérification du token Google
// Create a Google API client configured with our client ID (the expected "audience" of the token).
$client = new Google_Client(['client_id' => $WEB_CLIENT_ID]);
// Verify the token's signature, expiry, issuer and audience; returns the token's claims as an array, or false.
// NOTE: a token that isn't JWT-shaped (e.g. "test") throws an exception here instead of returning false,
// which ends the script with an empty 500 response. See DOCUMENTATION.md, "Known issues".
$payload = $client->verifyIdToken($id_token);

// Google rejected the token (forged, expired, or issued for another app).
if (!$payload) {
    // 401 Unauthorized.
    http_response_code(401);
    // Explain the problem to the front end.
    echo json_encode(['error' => 'Invalid ID token']);
    // Stop the script here.
    exit;
}

// Optionnel : vérification du domaine (décommenter si besoin)
// Domain check: only accounts from the school's Google Workspace pass. It is currently commented out,
// so ANY Google account is accepted. Remove the /* and */ lines below to enable it.
/*
if (!isset($payload['hd']) || $payload['hd'] !== $domains) { // "hd" (hosted domain) is set by Google for Workspace accounts only.
    http_response_code(403);                                 // 403 Forbidden.
    echo json_encode(['error' => 'Access denied: Invalid domain']); // Explain the problem to the front end.
    exit;                                                    // Stop the script here.
}
*/

// Assignation de la variable recherchée
// The verified email address of the signed-in user, with surrounding spaces removed ('' if missing).
$targetEmail = trim($payload['email'] ?? '');

// Ouverture du fichier CSV
// Open the CSV file read-only. The path is relative to the folder PHP runs the script from.
$handle = fopen("customers-100.csv", "r");
// The file couldn't be opened (missing, wrong path or no permission).
if (!$handle) {
    // 500 Internal Server Error.
    http_response_code(500);
    // Explain the problem to the front end.
    echo json_encode(['error' => 'Unable to open the database']);
    // Stop the script here.
    exit;
}

// Read the first line of the CSV: the column names (Index, Customer Id, …, Email, …, Website).
$headers = fgetcsv($handle, 1000, ',');
// Find the position of the "Email" column (false if it doesn't exist).
$emailIndex = array_search('Email', $headers);
// Find the position of the "Website" column (false if it doesn't exist).
$websiteIndex = array_search('Website', $headers);

// One of the two required columns is missing: the CSV isn't in the expected format.
if ($emailIndex === false || $websiteIndex === false) {
    // Close the file before leaving.
    fclose($handle);
    // 500 Internal Server Error.
    http_response_code(500);
    // Explain the problem to the front end.
    echo json_encode(['error' => 'Required columns not found in CSV']);
    // Stop the script here.
    exit;
}

// Result of the search; stays null if the email is never found.
$website = null;

// Parcours du fichier CSV
// Read the CSV one row at a time until the end of the file (fgetcsv returns false at the end).
while (($data = fgetcsv($handle, 1000, ',')) !== false) {
    // The row has an Email cell and it matches the signed-in user's email (exact, case-sensitive match).
    if (isset($data[$emailIndex]) && trim($data[$emailIndex]) === $targetEmail) {
        // Keep that row's website, or "Non renseigné" (not provided) if the cell is missing.
        $website = $data[$websiteIndex] ?? 'Non renseigné';
        // Found it: stop reading the file.
        break;
    }
}

// Close the CSV file.
fclose($handle);

// Envoi de la réponse JSON finale
// The email was found in the CSV.
if ($website !== null) {
    // Send the success response (HTTP 200 by default).
    echo json_encode([
        'status' => 'success',   // Lets the front end recognise a successful lookup.
        'email' => $targetEmail, // The signed-in user's email.
        'website' => $website    // The value found in the "Website" column.
    ]);
// The email isn't in the CSV.
} else {
    // 404 Not Found.
    http_response_code(404);
    // Tell the front end nothing was found, and for which email.
    echo json_encode([
        'error' => 'No website found for this email', // Error message.
        'email' => $targetEmail                       // The email that was searched for.
    ]);
}
