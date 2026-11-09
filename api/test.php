<?php

header("Content-Type: application/json");

$response = [
    "success" => true,
    "message" => "API is working!"
];

echo json_encode($response);

?>