<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__.'/includes/admin_auth.php';

$accounts = [
    ['username' => 'admin', 'role' => 'admin'],
    ['username' => 'superadmin', 'role' => 'super_admin']
];

foreach ($accounts as $account) {
    $email = admin_auth_recovery_email_for_role($account['role']);
    if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, 'A valid predefined recovery email is required for '.$account['username'].'.'.PHP_EOL);
        exit(1);
    }
}

$pdo = admin_auth_pdo();
$pdo->beginTransaction();
try {
    $accountQuery = $pdo->prepare('SELECT id,role FROM users WHERE username=? LIMIT 1');
    $securityEnsure = $pdo->prepare('INSERT IGNORE INTO admin_account_recovery(user_id) VALUES(?)');
    $contactUpdate = $pdo->prepare('UPDATE admin_account_recovery SET recovery_email=? WHERE user_id=?');
    foreach ($accounts as $accountConfig) {
        $accountQuery->execute([$accountConfig['username']]);
        $account = $accountQuery->fetch();
        if (!$account || $account['role'] !== $accountConfig['role']) {
            throw new RuntimeException('The expected Admin account is missing or has an unexpected role.');
        }
        $email = admin_auth_recovery_email_for_role($accountConfig['role']);
        $securityEnsure->execute([(int)$account['id']]);
        $contactUpdate->execute([$email, (int)$account['id']]);
        if ($contactUpdate->rowCount() === 0) {
            $verifyUpdate = $pdo->prepare('SELECT recovery_email FROM admin_account_recovery WHERE user_id=?');
            $verifyUpdate->execute([(int)$account['id']]);
            if ($verifyUpdate->fetchColumn() !== $email) {
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
