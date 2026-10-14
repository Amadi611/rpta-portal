
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;

$projectId = 'rpta-portal';

// Firebase service account JSON file location
$keyFile = 'C:/xampp/firebase-keys/rpta-portal-service-account.json';

// Check whether the JSON file exists
if (!is_file($keyFile)) {
    throw new RuntimeException(
        'Firebase service account file not found: ' . $keyFile
    );
}

$scopes = [
    'https://www.googleapis.com/auth/datastore'
];

$credentials = new ServiceAccountCredentials(
    $scopes,
    $keyFile
);


function firestoreRequest(
    string $method,
    string $documentPath,
    ?array $data = null
): array {
    global $projectId, $credentials;

    $tokenData = $credentials->fetchAuthToken();

    if (empty($tokenData['access_token'])) {
        throw new RuntimeException('Unable to get Google access token.');
    }

    $url = 'https://firestore.googleapis.com/v1/projects/'
        . $projectId
        . '/databases/(default)/documents/'
        . ltrim($documentPath, '/');

    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . $tokenData['access_token'],
        'Content-Type: application/json'
    ];

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    if ($data !== null) {
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('cURL error: ' . $error);
    }

    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($status < 200 || $status >= 300) {
        error_log('Firestore HTTP ' . $status . ': ' . $response);
        throw new RuntimeException(
            'Firestore request failed with HTTP ' . $status
        );
    }

    return is_array($decoded) ? $decoded : [];
}
