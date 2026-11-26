<?php
require_once __DIR__.'/includes/admin_auth.php';

if (logged_in()) redirect('dashboard.php');

$userId = isset($_SESSION['admin_recovery_user_id']) ? (int)$_SESSION['admin_recovery_user_id'] : 0;
if ($userId < 1) redirect('login.php');

$pdo = admin_auth_pdo();
$accountQuery = $pdo->prepare(
    'SELECT u.role,r.recovery_email,r.failed_attempts,r.email_requests,
            r.email_window_started_at,r.otp_hash,r.otp_expires_at,r.otp_attempts,
            r.otp_sent_at,r.reset_token_hash,r.reset_token_expires_at
     FROM users u
     JOIN admin_account_recovery r ON r.user_id=u.id
     WHERE u.id=? LIMIT 1'
);
$accountQuery->execute([$userId]);
$account = $accountQuery->fetch();
if (!$account || !admin_auth_is_target_role($account['role']) || (int)$account['failed_attempts'] < 5) {
    unset(
        $_SESSION['admin_recovery_user_id'],
        $_SESSION['admin_recovery_otp_prompt'],
        $_SESSION['admin_recovery_reset_token']
    );
    redirect('login.php');
}

$notice = '';
$error = '';
$otpPrompt = !empty($_SESSION['admin_recovery_otp_prompt']);
$resetToken = isset($_SESSION['admin_recovery_reset_token'])
    && is_string($_SESSION['admin_recovery_reset_token'])
    ? $_SESSION['admin_recovery_reset_token']
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if (!admin_auth_csrf_valid('admin_recovery_csrf', $_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($action === 'request_otp') {
        $submittedEmail = isset($_POST['email']) && is_string($_POST['email'])
            ? trim($_POST['email'])
            : '';
        $otpToSend = null;
        $otpHashToSend = null;
        try {
            $pdo->beginTransaction();
            $lockedQuery = $pdo->prepare(
                'SELECT recovery_email,failed_attempts,email_requests,email_window_started_at,otp_sent_at
                 FROM admin_account_recovery WHERE user_id=? FOR UPDATE'
            );
            $lockedQuery->execute([$userId]);
            $recovery = $lockedQuery->fetch();
            if (!$recovery || (int)$recovery['failed_attempts'] < 5) {
                $pdo->rollBack();
                unset($_SESSION['admin_recovery_user_id']);
                redirect('login.php');
            }

            $now = time();
            $windowStarted = $recovery['email_window_started_at']
                ? strtotime($recovery['email_window_started_at'].' UTC')
                : false;
            $inRequestWindow = $windowStarted !== false
                && $now >= $windowStarted
                && ($now - $windowStarted) < ADMIN_AUTH_OTP_WINDOW_SECONDS;
            $requestCount = $inRequestWindow ? (int)$recovery['email_requests'] : 0;
            $windowStartedAt = $inRequestWindow
                ? $recovery['email_window_started_at']
                : gmdate('Y-m-d H:i:s', $now);
            $lastSent = $recovery['otp_sent_at']
                ? strtotime($recovery['otp_sent_at'].' UTC')
                : false;
            $cooldownActive = $lastSent !== false
                && $now >= $lastSent
                && ($now - $lastSent) < ADMIN_AUTH_OTP_COOLDOWN_SECONDS;
            $rateLimited = $requestCount >= ADMIN_AUTH_OTP_MAX_REQUESTS || $cooldownActive;

            if (!$rateLimited) {
                $requestCount++;
                $expectedRecoveryEmail = admin_auth_recovery_email_for_role($account['role']);
                $emailMatches = filter_var($submittedEmail, FILTER_VALIDATE_EMAIL)
                    && is_string($recovery['recovery_email'])
                    && is_string($expectedRecoveryEmail)
                    && hash_equals(
                        $expectedRecoveryEmail,
                        strtolower($recovery['recovery_email'])
                    )
                    && hash_equals(
                        $expectedRecoveryEmail,
                        strtolower($submittedEmail)
                    );

                if ($emailMatches) {
                    $otpToSend = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $otpHashToSend = password_hash($otpToSend, PASSWORD_DEFAULT);
                    if ($otpHashToSend === false) {
                        throw new RuntimeException('Could not hash an OTP.');
                    }
                    $expiresAt = gmdate('Y-m-d H:i:s', $now + ADMIN_AUTH_OTP_SECONDS);
                    $update = $pdo->prepare(
                        'UPDATE admin_account_recovery
                         SET email_requests=?,email_window_started_at=?,otp_hash=?,
                             otp_expires_at=?,otp_attempts=0,otp_sent_at=UTC_TIMESTAMP(),
                             reset_token_hash=NULL,reset_token_expires_at=NULL
                         WHERE user_id=?'
                    );
                    $update->execute([
                        $requestCount,
                        $windowStartedAt,
                        $otpHashToSend,
                        $expiresAt,
                        $userId
                    ]);
                } else {
                    $update = $pdo->prepare(
                        'UPDATE admin_account_recovery
                         SET email_requests=?,email_window_started_at=?
                         WHERE user_id=?'
                    );
                    $update->execute([$requestCount, $windowStartedAt, $userId]);
                }
            }
            $pdo->commit();
            $_SESSION['admin_recovery_otp_prompt'] = true;
            session_regenerate_id(true);

            if ($otpToSend !== null) {
                try {
                    send_admin_recovery_otp(
                        $recovery['recovery_email'],
                        $otpToSend,
                        $account['role']
                    );
                } catch (Throwable $mailError) {
                    error_log(
                        'Admin OTP SMTP configuration or delivery failed for user id '
                        .$userId.': '.$mailError->getMessage()
                    );
                    $clearOtp = $pdo->prepare(
                        'UPDATE admin_account_recovery
                         SET otp_hash=NULL,otp_expires_at=NULL
                         WHERE user_id=? AND otp_hash=?'
                    );
                    $clearOtp->execute([$userId, $otpHashToSend]);
                }
            }
            $otpPrompt = true;
            $notice = 'If the email address matches an account, a verification code has been sent. Please check your inbox and spam folder.';
        } catch (Throwable $requestError) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Admin OTP request failed for user id '.$userId.': '.$requestError->getMessage());
            $error = 'Recovery is temporarily unavailable. Please try again later.';
        }
    } elseif ($action === 'verify_otp') {
        $submittedOtp = isset($_POST['otp']) && is_string($_POST['otp']) ? trim($_POST['otp']) : '';
        $otpValid = preg_match('/^[0-9]{6}$/D', $submittedOtp) === 1;
        try {
            $pdo->beginTransaction();
            $otpQuery = $pdo->prepare(
                'SELECT failed_attempts,otp_hash,otp_expires_at,otp_attempts
                 FROM admin_account_recovery WHERE user_id=? FOR UPDATE'
            );
            $otpQuery->execute([$userId]);
            $recovery = $otpQuery->fetch();
            $expiry = $recovery && $recovery['otp_expires_at']
                ? strtotime($recovery['otp_expires_at'].' UTC')
                : false;
            if (
                !$recovery
                || (int)$recovery['failed_attempts'] < 5
                || !$recovery['otp_hash']
                || $expiry === false
                || $expiry <= time()
                || (int)$recovery['otp_attempts'] >= ADMIN_AUTH_OTP_MAX_ATTEMPTS
            ) {
                $invalidate = $pdo->prepare(
                    'UPDATE admin_account_recovery
                     SET otp_hash=NULL,otp_expires_at=NULL,otp_attempts=0
                     WHERE user_id=?'
                );
                $invalidate->execute([$userId]);
                $pdo->commit();
                $error = 'The code is invalid or expired. Request a new code and try again.';
            } elseif (!$otpValid || !password_verify($submittedOtp, $recovery['otp_hash'])) {
                $attempts = (int)$recovery['otp_attempts'] + 1;
                if ($attempts >= ADMIN_AUTH_OTP_MAX_ATTEMPTS) {
                    $badOtp = $pdo->prepare(
                        'UPDATE admin_account_recovery
                         SET otp_hash=NULL,otp_expires_at=NULL,otp_attempts=?
                         WHERE user_id=?'
                    );
                } else {
                    $badOtp = $pdo->prepare(
                        'UPDATE admin_account_recovery SET otp_attempts=? WHERE user_id=?'
                    );
                }
                $badOtp->execute([$attempts, $userId]);
                $pdo->commit();
                $otpPrompt = true;
                $error = 'The code is invalid or expired. Request a new code and try again.';
            } else {
                $resetToken = bin2hex(random_bytes(32));
                $resetTokenHash = hash('sha256', $resetToken);
                $resetExpires = gmdate('Y-m-d H:i:s', time() + ADMIN_AUTH_OTP_SECONDS);
                $verifyUpdate = $pdo->prepare(
                    'UPDATE admin_account_recovery
                     SET otp_hash=NULL,otp_expires_at=NULL,otp_attempts=0,
                         reset_token_hash=?,reset_token_expires_at=?
                     WHERE user_id=?'
                );
                $verifyUpdate->execute([$resetTokenHash, $resetExpires, $userId]);
                $pdo->commit();
                session_regenerate_id(true);
                $_SESSION['admin_recovery_reset_token'] = $resetToken;
                unset($_SESSION['admin_recovery_otp_prompt']);
                redirect('admin_account_recovery.php');
            }
        } catch (Throwable $verifyError) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Admin OTP verification failed for user id '.$userId.': '.$verifyError->getMessage());
            $error = 'Recovery is temporarily unavailable. Please try again later.';
        }
    } elseif ($action === 'reset_password' && $resetToken !== '') {
        $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password'])
            ? $_POST['new_password']
            : '';
        $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
            ? $_POST['confirm_password']
            : '';
        $passwordIsStrong = strlen($newPassword) >= 12
            && strlen($newPassword) <= 72
            && preg_match('/[A-Z]/', $newPassword)
            && preg_match('/[a-z]/', $newPassword)
            && preg_match('/[0-9]/', $newPassword)
            && preg_match('/[^a-zA-Z0-9]/', $newPassword);
        if (!$passwordIsStrong) {
            $error = 'Use 12–72 characters with uppercase and lowercase letters, a number, and a symbol.';
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            $error = 'The passwords do not match.';
        } else {
            try {
                $pdo->beginTransaction();
                $resetQuery = $pdo->prepare(
                    'SELECT u.role,r.reset_token_hash,r.reset_token_expires_at
                     FROM users u
                     JOIN admin_account_recovery r ON r.user_id=u.id
                     WHERE u.id=? FOR UPDATE'
                );
                $resetQuery->execute([$userId]);
                $recovery = $resetQuery->fetch();
                $resetExpiry = $recovery && $recovery['reset_token_expires_at']
                    ? strtotime($recovery['reset_token_expires_at'].' UTC')
                    : false;
                if (
                    !$recovery
                    || !admin_auth_is_target_role($recovery['role'])
                    || !is_string($recovery['reset_token_hash'])
                    || !hash_equals($recovery['reset_token_hash'], hash('sha256', $resetToken))
                    || $resetExpiry === false
                    || $resetExpiry <= time()
                ) {
                    $pdo->rollBack();
                    unset($_SESSION['admin_recovery_reset_token']);
                    $resetToken = '';
                    $error = 'Your recovery session expired. Start the recovery process again.';
                } else {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    if ($passwordHash === false) {
                        throw new RuntimeException('Could not hash the new password.');
                    }
                    $passwordUpdate = $pdo->prepare(
                        'UPDATE users SET password_hash=? WHERE id=? AND role IN (?,?)'
                    );
                    $passwordUpdate->execute([
                        $passwordHash,
                        $userId,
                        'admin',
                        'super_admin'
                    ]);
                    if ($passwordUpdate->rowCount() !== 1) {
                        throw new RuntimeException('The Admin password update did not affect exactly one account.');
                    }
                    admin_auth_reset_state($pdo, $userId);
                    $pdo->commit();
                    session_regenerate_id(true);
                    unset(
                        $_SESSION['admin_recovery_user_id'],
                        $_SESSION['admin_recovery_otp_prompt'],
                        $_SESSION['admin_recovery_reset_token'],
                        $_SESSION['admin_recovery_csrf']
                    );
                    $_SESSION['admin_password_recovered'] = true;
                    redirect('login.php');
                }
            } catch (Throwable $resetError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Admin password reset failed for user id '.$userId.': '.$resetError->getMessage());
                $error = 'Password reset is temporarily unavailable. Please try again later.';
            }
        }
    } else {
        $error = 'Your recovery session expired. Start the recovery process again.';
    }
}

$csrfToken = admin_auth_csrf_token('admin_recovery_csrf');
$title = 'Admin Account Recovery';
$pageStyles = ['assets/account-recovery.css'];
include __DIR__.'/includes/header.php';
?>
<div class="page narrow">
	<div class="form-card">
		<div class="section-title"><div><h2>Admin account recovery</h2><p class="muted">Verify your registered email to reset your password.</p></div><a class="secondary-btn" href="login.php">Back to login</a></div>
		<?php if($notice!==''):?><div class="notice info" role="status"><?=h($notice)?></div><?php endif;?>
		<?php if($error!==''):?><div class="notice danger" role="alert"><?=h($error)?></div><?php endif;?>
		<?php if($resetToken!==''):?>
		<form method="post" autocomplete="off">
			<input type="hidden" name="csrf_token" value="<?=h($csrfToken)?>">
			<input type="hidden" name="action" value="reset_password">
			<label for="new-password">New password</label>
			<input id="new-password" type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password" required>
			<label for="confirm-password">Confirm new password</label>
			<input id="confirm-password" type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required>
			<p class="muted">Use 12–72 characters with uppercase and lowercase letters, a number, and a symbol.</p>
			<button class="primary-btn" type="submit">Reset password and unlock</button>
		</form>
		<?php else:?>
		<form method="post" autocomplete="off">
			<input type="hidden" name="csrf_token" value="<?=h($csrfToken)?>">
			<input type="hidden" name="action" value="request_otp">
			<label for="recovery-email">Registered email address</label>
			<input id="recovery-email" type="email" name="email" autocomplete="email" maxlength="254" required>
			<button class="primary-btn" type="submit">Send recovery code</button>
		</form>
		<?php if($otpPrompt):?>
		<section class="recovery-otp" aria-labelledby="recovery-otp-title">
			<h3 id="recovery-otp-title">Enter verification code</h3>
			<p class="muted">Enter the six-digit code to continue to password reset.</p>
			<form method="post" autocomplete="off">
				<input type="hidden" name="csrf_token" value="<?=h($csrfToken)?>">
				<input type="hidden" name="action" value="verify_otp">
				<label for="recovery-otp">Six-digit recovery code</label>
				<input id="recovery-otp" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required>
				<button class="primary-btn" type="submit">Verify code</button>
			</form>
		</section>
		<?php endif;?>
		<?php endif;?>
	</div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
