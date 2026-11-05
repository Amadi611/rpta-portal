
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;

$firestore = new FirestoreClient([
    'projectId' => 'rpta-portal',
    'keyFilePath' => 'Downloads',
]);
