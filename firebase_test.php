
<?php

require_once __DIR__ . '/firebase_config.php';

try {
    $result = firestoreRequest(
        'PATCH',
        'connection_tests/test1',
        [
            'fields' => [
                'message' => [
                    'stringValue' => 'RPTA Portal Firebase Connected'
                ],
                'created_at' => [
                    'stringValue' => date('c')
                ]
            ]
        ]
    );

    echo 'Firebase connection successful!';
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->__toString());

    echo 'Firebase connection failed. ';
    echo 'Please check the PHP error log.';
}
