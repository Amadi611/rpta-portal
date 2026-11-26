<?php
require_once __DIR__.'/../config.php';

const ADMIN_AUTH_LOCKOUT_SECONDS = 120;
const ADMIN_AUTH_OTP_SECONDS = 300;
const ADMIN_AUTH_OTP_COOLDOWN_SECONDS = 60;
const ADMIN_AUTH_OTP_WINDOW_SECONDS = 900;
const ADMIN_AUTH_OTP_MAX_REQUESTS = 3;
const ADMIN_AUTH_OTP_MAX_ATTEMPTS = 5;

function admin_auth_pdo()
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
    return $pdo;
}

function admin_auth_is_target_role($role)
{
    return in_array($role, ['admin', 'super_admin'], true);
}

function admin_auth_recovery_email_for_role($role)
{
    if (!is_string($role)) {
        return null;
    }

    $recoveryEmails = [
        'admin' => 'amadinethsarani611@gmail.com',
        'super_admin' => 'aktarushika@gmail.com'
    ];
    return $recoveryEmails[$role] ?? null;
}

function admin_auth_csrf_token($sessionKey)
{
    if (!isset($_SESSION[$sessionKey]) || !is_string($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$sessionKey];
}

function admin_auth_csrf_valid($sessionKey, $submittedToken)
{
    return isset($_SESSION[$sessionKey])
        && is_string($_SESSION[$sessionKey])
        && is_string($submittedToken)
        && hash_equals($_SESSION[$sessionKey], $submittedToken);
}

function admin_auth_reset_state(PDO $pdo, $userId)
{
    $statement = $pdo->prepare(
        'UPDATE admin_account_recovery
         SET failed_attempts=0, locked_until=NULL, email_requests=0,
             email_window_started_at=NULL, otp_hash=NULL, otp_expires_at=NULL,
             otp_attempts=0, otp_sent_at=NULL, reset_token_hash=NULL,
             reset_token_expires_at=NULL
         WHERE user_id=?'
    );
    $statement->execute([(int)$userId]);
}

function send_admin_recovery_otp($recipient, $otp, $role)
{
    require_once __DIR__.'/../vendor/autoload.php';

    $expectedRecipient = admin_auth_recovery_email_for_role($role);
    if (
        !is_string($recipient)
        || !is_string($expectedRecipient)
        || !hash_equals($expectedRecipient, strtolower(trim($recipient)))
    ) {
        throw new RuntimeException('Recovery email recipient is not permitted for this account role.');
    }
    if (!is_string($otp) || preg_match('/^[0-9]{6}$/D', $otp) !== 1) {
        throw new RuntimeException('Recovery code format is invalid.');
    }

    $username = getenv('RPTA_SMTP_USERNAME');
    $password = getenv('RPTA_SMTP_PASSWORD');
    if ($username === false || trim($username) === '') {
        throw new RuntimeException('Missing required SMTP environment setting: RPTA_SMTP_USERNAME.');
    }
    if ($password === false || trim($password) === '') {
        throw new RuntimeException('Missing required SMTP environment setting: RPTA_SMTP_PASSWORD.');
    }
    $username = trim($username);
    $password = preg_replace('/\s+/', '', trim($password));
    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('RPTA_SMTP_USERNAME must be a valid Gmail sender address.');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
    $mail->Timeout = 15;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom(
        $username,
        getenv('RPTA_SMTP_FROM_NAME') ?: APP_NAME_EN
    );
    $mail->addAddress($recipient);
    $mail->Subject = 'SPRPTA account recovery code';
    $mail->Body = "Your one-time account recovery code is: ".$otp
        ."\n\nThis code expires in five minutes. If you did not request it, ignore this message.";
    $mail->send();
}
