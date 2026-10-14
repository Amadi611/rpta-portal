<?php
require_once __DIR__.'/config.php';
if(logged_in()) redirect('dashboard.php');
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
	unset($_SESSION['user']);
	session_regenerate_id(true);
	$username=trim($_POST['username']??'');
	$password=$_POST['password']??'';
	$stmt=$conn->prepare('SELECT * FROM users WHERE username=? LIMIT 1');
	if($stmt){
		$stmt->bind_param('s',$username);
		$stmt->execute();
		$user=$stmt->get_result()->fetch_assoc();
		if($user && password_verify($password,$user['password_hash'])){
			$_SESSION['user']=[
				'id'=>(int)$user['id'],
				'full_name'=>$user['full_name'],
				'username'=>$user['username'],
				'password_hash'=>$user['password_hash'],
				'role'=>$user['role'],
				'created_at'=>$user['created_at']
			];
			redirect('dashboard.php');
		}
	}
	$err='Invalid username or password / Username හෝ password වැරදියි';
}
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
		<p class="muted">මගී ප්‍රවාහන සේවා භාවිතා කිරීමට පද්ධතියට ඇතුල් වන්න.</p>
		<?php if($err):?><div class="notice danger" role="alert"><?=h($err)?></div><?php endif;?>
		<form method="post" autocomplete="off">
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