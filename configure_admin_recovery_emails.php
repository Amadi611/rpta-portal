<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__.'/includes/admin_auth.php';

$contacts = [
    'admin' => getenv('RPTA_ADMIN_RECOVERY_EMAIL'),
    'superadmin' => getenv('RPTA_SUPERADMIN_RECOVERY_EMAIL')
];

foreach ($contacts as $username => $email) {
    if ($email === false || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, 'Set a valid recovery email in the environment variable for '.$username.'.'.PHP_EOL);
        exit(1);
    }
}

$pdo = admin_auth_pdo();
$pdo->beginTransaction();
try {
    $accountQuery = $pdo->prepare('SELECT id,role FROM users WHERE username=? LIMIT 1');
    $securityEnsure = $pdo->prepare('INSERT IGNORE INTO admin_account_recovery(user_id) VALUES(?)');
    $contactUpdate = $pdo->prepare('UPDATE admin_account_recovery SET recovery_email=? WHERE user_id=?');
    foreach ($contacts as $username => $email) {
        $accountQuery->execute([$username]);
        $account = $accountQuery->fetch();
        $expectedRole = $username === 'admin' ? 'admin' : 'super_admin';
        if (!$account || $account['role'] !== $expectedRole) {
            throw new RuntimeException('The expected Admin account is missing or has an unexpected role.');
        }
        $securityEnsure->execute([(int)$account['id']]);
        $contactUpdate->execute([trim($email), (int)$account['id']]);
        if ($contactUpdate->rowCount() === 0) {
            $verifyUpdate = $pdo->prepare('SELECT recovery_email FROM admin_account_recovery WHERE user_id=?');
            $verifyUpdate->execute([(int)$account['id']]);
            if ($verifyUpdate->fetchColumn() !== trim($email)) {
                throw new RuntimeException('Could not save a recovery email for an Admin account.');
            }
        }
    }
    $pdo->commit();
    fwrite(STDOUT, "Admin and Super Admin recovery emails were saved. Addresses were not printed.".PHP_EOL);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Could not configure recovery emails: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
