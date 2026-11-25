<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__.'/config.php';

function reset_failed($conn, $message)
{
    $conn->rollback();
    fwrite(STDERR, "Admin password reset failed: ".$message.PHP_EOL);
    exit(1);
}

$accounts = [
    [
        'full_name' => 'Portal Administrator',
        'username' => 'admin',
        'role' => 'admin',
        'password' => rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=')
    ],
    [
        'full_name' => 'System Super Administrator',
        'username' => 'superadmin',
        'role' => 'super_admin',
        'password' => rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=')
    ]
];

if ($accounts[0]['password'] === $accounts[1]['password']) {
    fwrite(STDERR, "Admin password reset failed: generated passwords were not distinct.".PHP_EOL);
    exit(1);
}

if (!$conn->begin_transaction()) {
    fwrite(STDERR, "Admin password reset failed: ".$conn->error.PHP_EOL);
    exit(1);
}

foreach ($accounts as $account) {
    $lookup = $conn->prepare('SELECT id, role FROM users WHERE username=? LIMIT 1');
    if (!$lookup) {
        reset_failed($conn, $conn->error);
    }
    $lookup->bind_param('s', $account['username']);
    if (!$lookup->execute()) {
        reset_failed($conn, $lookup->error);
    }
    $existing = $lookup->get_result()->fetch_assoc();
    $lookup->close();

    $hash = password_hash($account['password'], PASSWORD_DEFAULT);
    if ($hash === false) {
        reset_failed($conn, 'Could not hash a generated password.');
    }

    if ($existing) {
        if ($existing['role'] !== $account['role']) {
            reset_failed($conn, 'The username "'.$account['username'].'" is already assigned another role.');
        }
        $statement = $conn->prepare('UPDATE users SET password_hash=? WHERE id=? AND role=?');
        if (!$statement) {
            reset_failed($conn, $conn->error);
        }
        $statement->bind_param('sis', $hash, $existing['id'], $account['role']);
    } else {
        $statement = $conn->prepare('INSERT INTO users(full_name, username, password_hash, role) VALUES(?,?,?,?)');
        if (!$statement) {
            reset_failed($conn, $conn->error);
        }
        $statement->bind_param('ssss', $account['full_name'], $account['username'], $hash, $account['role']);
    }

    if (!$statement->execute()) {
        reset_failed($conn, $statement->error);
    }
    $statement->close();
}

if (!$conn->commit()) {
    reset_failed($conn, $conn->error);
}

fwrite(STDOUT, "Passwords were reset. Save these now; they will not be shown again.".PHP_EOL);
foreach ($accounts as $account) {
    fwrite(STDOUT, strtoupper(str_replace('_', ' ', $account['role']))." (".$account['username']."): ".$account['password'].PHP_EOL);
}
