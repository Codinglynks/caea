<?php
require 'vendor/autoload.php';
$WEB_CLIENT_ID = '196751137283-vg0urefv51ntaokjckck5qjki6lk966d.apps.googleusercontent.com';
// Get $id_token via HTTPS POST.

$client = new Google_Client(['client_id' => $WEB_CLIENT_ID]);  // Specify the WEB_CLIENT_ID of the app that accesses the backend
$payload = $client->verifyIdToken($id_token);
if ($payload) {
    // This ID is unique to each Google Account, making it suitable for use as a primary key
    // during account lookup. Email is not a good choice because it can be changed by the user.
    $userid = $payload['sub'];
    // If the request specified a Google Workspace domain
    //$domain = $payload['hd'];
} else {
    // Invalid ID token
}

