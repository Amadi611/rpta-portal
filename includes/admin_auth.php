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

function send_admin_recovery_otp($recipient, $otp)
{
    require_once __DIR__.'/../vendor/autoload.php';

    $requiredSettings = [
        'RPTA_SMTP_HOST',
        'RPTA_SMTP_PORT',
        'RPTA_SMTP_USERNAME',
        'RPTA_SMTP_PASSWORD',
        'RPTA_SMTP_ENCRYPTION',
        'RPTA_SMTP_FROM_ADDRESS'
    ];
    $settings = [];
    foreach ($requiredSettings as $setting) {
        $value = getenv($setting);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException('Missing required SMTP environment setting: '.$setting);
        }
        $settings[$setting] = trim($value);
    }

    $port = filter_var($settings['RPTA_SMTP_PORT'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535]
    ]);
    if ($port === false) {
        throw new RuntimeException('RPTA_SMTP_PORT must be between 1 and 65535.');
    }
    if (!filter_var($settings['RPTA_SMTP_FROM_ADDRESS'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('RPTA_SMTP_FROM_ADDRESS is not a valid email address.');
    }

    $encryption = strtolower($settings['RPTA_SMTP_ENCRYPTION']);
    if (!in_array($encryption, ['tls', 'ssl'], true)) {
        throw new RuntimeException('RPTA_SMTP_ENCRYPTION must be tls or ssl.');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $settings['RPTA_SMTP_HOST'];
    $mail->Port = $port;
    $mail->SMTPAuth = true;
    $mail->Username = $settings['RPTA_SMTP_USERNAME'];
    $mail->Password = $settings['RPTA_SMTP_PASSWORD'];
    $mail->SMTPSecure = $encryption === 'ssl'
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout = 10;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom(
        $settings['RPTA_SMTP_FROM_ADDRESS'],
        getenv('RPTA_SMTP_FROM_NAME') ?: APP_NAME_EN
    );
    $mail->addAddress($recipient);
    $mail->Subject = 'SPRPTA account recovery code';
    $mail->Body = "Your one-time account recovery code is: ".$otp
        ."\n\nThis code expires in five minutes. If you did not request it, ignore this message.";
    $mail->send();
}
