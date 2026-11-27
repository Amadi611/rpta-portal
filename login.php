<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/admin_auth.php';
if(logged_in()) redirect('dashboard.php');
$err='';
$recovered=!empty($_SESSION['admin_password_recovered']);
unset($_SESSION['admin_password_recovered']);
if($_SERVER['REQUEST_METHOD']==='POST'){
	unset($_SESSION['user']);
	session_regenerate_id(true);
	$username=isset($_POST['username'])&&is_string($_POST['username'])?trim($_POST['username']):'';
	$password=isset($_POST['password'])&&is_string($_POST['password'])?$_POST['password']:'';
	if(!admin_auth_csrf_valid('login_csrf',$_POST['csrf_token']??null)){
		$err='Invalid username or password / Username හෝ password වැරදියි';
	}else{
		$pdo=null;
		try{
			$pdo=admin_auth_pdo();
			$userQuery=$pdo->prepare('SELECT id,full_name,username,password_hash,role,created_at FROM users WHERE username=? LIMIT 1');
			$userQuery->execute([$username]);
			$user=$userQuery->fetch();
			if($user&&admin_auth_is_target_role($user['role'])){
				$pdo->beginTransaction();
				$ensureSecurity=$pdo->prepare('INSERT IGNORE INTO admin_account_recovery(user_id) VALUES(?)');
				$ensureSecurity->execute([(int)$user['id']]);
				$securityQuery=$pdo->prepare('SELECT failed_attempts,locked_until FROM admin_account_recovery WHERE user_id=? FOR UPDATE');
				$securityQuery->execute([(int)$user['id']]);
				$security=$securityQuery->fetch();
				$lockedUntil=$security['locked_until']??null;
				if($lockedUntil!==null&&new DateTimeImmutable($lockedUntil,new DateTimeZone('UTC'))>new DateTimeImmutable('now',new DateTimeZone('UTC'))){
					$pdo->commit();
					$_SESSION['admin_recovery_user_id']=(int)$user['id'];
					session_regenerate_id(true);
					redirect('admin_account_recovery.php');
				}
				if($lockedUntil!==null){
					admin_auth_reset_state($pdo,(int)$user['id']);
					$security['failed_attempts']=0;
				}
				if(password_verify($password,$user['password_hash'])){
					admin_auth_reset_state($pdo,(int)$user['id']);
					$pdo->commit();
					$_SESSION['user']=[
						'id'=>(int)$user['id'],
						'full_name'=>$user['full_name'],
						'username'=>$user['username'],
						'password_hash'=>$user['password_hash'],
						'role'=>$user['role'],
						'created_at'=>$user['created_at']
					];
					unset($_SESSION['admin_recovery_user_id'],$_SESSION['admin_recovery_otp_prompt'],$_SESSION['admin_recovery_reset_token']);
					redirect('dashboard.php');
				}
				$failedAttempts=min(5,(int)$security['failed_attempts']+1);
				if($failedAttempts>=5){
					$lockUntil=gmdate('Y-m-d H:i:s',time()+ADMIN_AUTH_LOCKOUT_SECONDS);
					$failedUpdate=$pdo->prepare('UPDATE admin_account_recovery SET failed_attempts=?,locked_until=?,email_requests=0,email_window_started_at=NULL,otp_hash=NULL,otp_expires_at=NULL,otp_attempts=0,otp_sent_at=NULL,reset_token_hash=NULL,reset_token_expires_at=NULL WHERE user_id=?');
					$failedUpdate->execute([$failedAttempts,$lockUntil,(int)$user['id']]);
				}else{
					$failedUpdate=$pdo->prepare('UPDATE admin_account_recovery SET failed_attempts=? WHERE user_id=?');
					$failedUpdate->execute([$failedAttempts,(int)$user['id']]);
				}
				$pdo->commit();
				if($failedAttempts>=5){
					$_SESSION['admin_recovery_user_id']=(int)$user['id'];
					session_regenerate_id(true);
					redirect('admin_account_recovery.php');
				}
			}elseif($user&&password_verify($password,$user['password_hash'])){
				$_SESSION['user']=[
					'id'=>(int)$user['id'],
					'full_name'=>$user['full_name'],
					'username'=>$user['username'],
					'password_hash'=>$user['password_hash'],
					'role'=>$user['role'],
					'created_at'=>$user['created_at']
				];
				unset($_SESSION['admin_recovery_user_id'],$_SESSION['admin_recovery_otp_prompt'],$_SESSION['admin_recovery_reset_token']);
				redirect('dashboard.php');
			}
		}catch(Throwable $exception){
			if($pdo instanceof PDO&&$pdo->inTransaction()) $pdo->rollBack();
			error_log('Login authentication failed: '.$exception->getMessage());
			$err='Login is temporarily unavailable. Please try again later.';
		}
	}
	if($err==='') $err='Invalid username or password / Username හෝ password වැරදියි';
}
$loginCsrfToken=admin_auth_csrf_token('login_csrf');
$title='Login / ඇතුල් වන්න';
$pageStyles=['assets/login.css'];
include __DIR__.'/includes/header.php';
?>
<section class="login-first">
	<div class="login-photo">
		<img src="<?=h(BUS_PHOTO_URL)?>" alt="Sri Lanka public transport bus">
		<div class="login-photo-overlay">
			<span>🇱🇰 Southern Province / දකුණු පළාත</span>
			<h1><?=h(APP_NAME_EN)?></h1>
			<p><?=h(APP_NAME_SI)?></p>
			<small>Passenger Transport Digital Service Portal</small>
		</div>
	</div>
	<div class="login-panel">
		<img class="login-logo" src="<?=h(OFFICIAL_LOGO_URL)?>" onerror="this.onerror=null;this.src='<?=h(OFFICIAL_LOGO_FALLBACK_URL)?>';" alt="Southern Provincial Road Passenger Transport Authority Logo" referrerpolicy="no-referrer">
		<h2>Login / ඇතුල් වන්න</h2>
		<p class="muted">Please sign in to access the passenger transport services.</p>
		<?php if($recovered):?><div class="notice success" role="status">Your password has been reset. Please sign in with your new password.</div><?php endif;?>
		<?php if($err):?><div class="notice danger" role="alert"><?=h($err)?></div><?php endif;?>
		<form method="post" autocomplete="off">
			<input type="hidden" name="csrf_token" value="<?=h($loginCsrfToken)?>">
			<label>Username / පරිශීලක නාමය</label><input name="username" placeholder="Enter username" autocomplete="username" required>
			<label>Password / මුරපදය</label><input type="password" name="password" placeholder="Enter password" autocomplete="current-password" required>
			<div class="login-forgot"><a href="forgot_password.php">Forgot password?</a></div>
			<button class="primary-btn login-main-btn" type="submit">🔐 Login / ඇතුල් වන්න</button>
		</form>
		<div class="login-note" style="margin-top:12px"><a href="register.php">Register as a Client / සේවාලාභියෙකු ලෙස ලියාපදිංචි වන්න</a></div>
		<div class="login-note">General Contact / සාමාන්‍ය විමසීම්: <b><?=h(CONTACT_PHONE)?></b></div>
	</div>
</section>
<?php include __DIR__.'/includes/footer.php'; ?>