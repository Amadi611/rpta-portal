<?php
require_once __DIR__.'/config.php';
if(logged_in()) redirect('dashboard.php');
$error='';
$updated=isset($_GET['updated']);
if($_SERVER['REQUEST_METHOD']==='POST'){
    $username=trim($_POST['username']??'');
    $nic=trim($_POST['nic']??'');
    $password=$_POST['password']??'';
    if(!$username||!$nic||!preg_match('/^[0-9]{6}$/',$password)){
        $error='Enter your username, registered NIC, and a new 6-digit password.';
    }else{
        $stmt=$conn->prepare('SELECT u.id FROM users u JOIN passenger_profiles p ON p.user_id=u.id WHERE u.username=? AND p.nic=? AND u.role="passenger" LIMIT 1');
        if($stmt){
            $stmt->bind_param('ss',$username,$nic);
            $stmt->execute();
            $account=$stmt->get_result()->fetch_assoc();
            if($account){
                $hash=password_hash($password,PASSWORD_DEFAULT);
                $update=$conn->prepare('UPDATE users SET password_hash=? WHERE id=?');
                $update->bind_param('si',$hash,$account['id']);
                if($update->execute()) redirect('forgot_password.php?updated=1');
            }
        }
        $error='The account details did not match. Check your entries and try again.';
    }
}
$title='Forgot Password';
include __DIR__.'/includes/header.php';
?>
<div class="page narrow">
    <div class="form-card">
        <div class="section-title"><div><h2>Forgot password?</h2><p class="muted">Verify your registered details to set a new password.</p></div><a class="secondary-btn" href="login.php">Back to login</a></div>
        <?php if($updated):?><div class="notice success">Password updated. You can now sign in.</div><?php endif;?>
        <?php if($error):?><div class="notice danger"><?=h($error)?></div><?php endif;?>
        <form method="post" autocomplete="off">
            <label>Username</label><input name="username" autocomplete="username" required>
            <label>Registered NIC</label><input name="nic" required>
            <label>New password</label><input type="password" name="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required>
            <button class="primary-btn" type="submit">Reset password</button>
        </form>
    </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
