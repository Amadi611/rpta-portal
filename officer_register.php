<?php
require_once __DIR__.'/config.php';
require_login();
if(!can_register_officers()) redirect('dashboard.php');
$departments=[
    'control'=>['label'=>'Admin','role'=>'control_admin'],
    'accounts'=>['label'=>'Finance','role'=>'accounts_admin'],
    'driving'=>['label'=>'Operation','role'=>'driving_admin']
];
$department=$_POST['department']??($_GET['department']??'');
$showDepartmentChooser=$department==='' && $_SERVER['REQUEST_METHOD']!=='POST';
$error='';
$values=['full_name'=>'','username'=>'','nic'=>'','phone'=>'','email'=>'','position'=>''];
if($_SERVER['REQUEST_METHOD']==='POST'){
    foreach($values as $field=>$value) $values[$field]=trim($_POST[$field]??'');
    $password=$_POST['password']??'';
    if(!isset($departments[$department]) || !$values['full_name'] || !$values['username'] || !$values['nic'] || !$values['phone'] || !$values['position'] || $password===''){
        $error='Please complete all required fields.';
    } elseif(strlen($values['full_name'])>120 || strlen($values['username'])>80 || strlen($values['position'])>120){
        $error='A name, username, or position is too long.';
    } elseif(strlen($password)<6){
        $error='Password must contain at least 6 characters.';
    } elseif($values['email']!=='' && !filter_var($values['email'],FILTER_VALIDATE_EMAIL)){
        $error='Enter a valid email address.';
    } else {
        $check=$conn->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        if(!$check){
            error_log('Officer registration username check prepare failed: '.$conn->error);
            $error='Could not verify the username. Please try again.';
        } elseif(!$check->bind_param('s',$values['username']) || !$check->execute()){
            error_log('Officer registration username check failed: '.$check->error);
            $error='Could not verify the username. Please try again.';
        } elseif($check->get_result()->num_rows){
            $error='That username is already in use. Please choose another.';
        } else {
            if(!$conn->begin_transaction()){
                error_log('Officer registration transaction start failed: '.$conn->error);
                $error='Could not create the officer account. Please try again.';
            } else {
                $hash=password_hash($password,PASSWORD_DEFAULT);
                $userStmt=$conn->prepare('INSERT INTO users(full_name,username,password_hash,role) VALUES(?,?,?,?)');
                $staffRole=$departments[$department]['role'];
                $created=false;
                if(!$userStmt){
                    error_log('Officer registration user insert prepare failed: '.$conn->error);
                } elseif(!$userStmt->bind_param('ssss',$values['full_name'],$values['username'],$hash,$staffRole)){
                    error_log('Officer registration user insert bind failed: '.$userStmt->error);
                } elseif(!$userStmt->execute()){
                    error_log('Officer registration user insert failed: '.$userStmt->error);
                } else {
                    $created=true;
                    $userId=(int)$conn->insert_id;
                    $profileStmt=$conn->prepare('INSERT INTO officer_profiles(user_id,nic,phone,email,position,department) VALUES(?,?,?,?,?,?)');
                    $email=$values['email']!==''?$values['email']:null;
                    if(!$profileStmt){
                        error_log('Officer registration profile insert prepare failed: '.$conn->error);
                        $created=false;
                    } elseif(!$profileStmt->bind_param('isssss',$userId,$values['nic'],$values['phone'],$email,$values['position'],$department)){
                        error_log('Officer registration profile insert bind failed: '.$profileStmt->error);
                        $created=false;
                    } elseif(!$profileStmt->execute()){
                        error_log('Officer registration profile insert failed: '.$profileStmt->error);
                        $created=false;
                    }
                }
                if($created){
                    if($conn->commit()){
                        redirect('admin/officers.php?created=1');
                    }
                    error_log('Officer registration transaction commit failed: '.$conn->error);
                }
                $conn->rollback();
                $error='Could not create the officer account. Please try again.';
            }
        }
    }
}
$title='Officer Registration';
$pageStyles=['assets/dashboard.css'];
if($showDepartmentChooser) $pageBodyClass='officer-registration-dashboard-page';
include __DIR__.'/includes/header.php';
?>
<?php if($showDepartmentChooser):?>
<div class="page officer-registration-picker">
    <div class="section-title"><div><span class="badge">OFFICER REGISTRATION</span><h2>Officer Registration</h2></div><a class="secondary-btn" href="dashboard.php">← Dashboard</a></div>
    <section class="dashboard-page dashboard-page--officer-registration" aria-label="Officer registration sections">
        <div class="admin-dashboard-layout officer-registrar-dashboard">
            <aside class="admin-dashboard-sidebar">
                <div class="officer-navigation-heading"><p>Choose a service section below to create an officer account.</p><span class="officer-section-count">3 service sections</span></div>
                <nav>
                    <a class="officer-registration-card officer-registration-card--control" href="officer_register.php?department=control" aria-label="Register an Admin section officer">
                        <svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5 20v-1a7 7 0 0 1 14 0v1"/></svg>
                        <span class="officer-card-label">SERVICE SECTION 01</span>
                        <strong>Admin Officer</strong>
                        <small>Create an account for the Admin section.</small>
                        <span class="officer-card-action">Register officer <b aria-hidden="true">→</b></span>
                    </a>
                    <a class="officer-registration-card officer-registration-card--accounts" href="officer_register.php?department=accounts" aria-label="Register a Finance section officer">
                        <svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="7" width="16" height="13" rx="2"/><path d="M9 7V5h6v2M4 11h16m-9 4h2"/></svg>
                        <span class="officer-card-label">SERVICE SECTION 02</span>
                        <strong>Finance Officer</strong>
                        <small>Create an account for the Finance section.</small>
                        <span class="officer-card-action">Register officer <b aria-hidden="true">→</b></span>
                    </a>
                    <a class="officer-registration-card officer-registration-card--driving" href="officer_register.php?department=driving" aria-label="Register an Operation section officer">
                        <svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 11 1.5-5h11L19 11m1 0H4a2 2 0 0 0-2 2v4h20v-4a2 2 0 0 0-2-2Z"/><path d="M6 17v2m12-2v2M7 14h.01M17 14h.01"/></svg>
                        <span class="officer-card-label">SERVICE SECTION 03</span>
                        <strong>Operation Officer</strong>
                        <small>Create an account for the Operation section.</small>
                        <span class="officer-card-action">Register officer <b aria-hidden="true">→</b></span>
                    </a>
                </nav>
            </aside>
        </div>
    </section>
</div>
<?php else:?>
<div class="page narrow officer-registration-page<?=isset($departments[$department])?' officer-registration-page--'.h($department):''?>">
    <div class="form-card">
        <div class="section-title"><div><span class="badge">OFFICER REGISTRATION</span><h2>Officer Registration</h2><p class="muted">Create an officer account for the selected service section.</p></div><a class="secondary-btn" href="admin/officers.php">← Back</a></div>
        <?php if($error):?><div class="notice danger" role="alert"><?=h($error)?></div><?php endif;?>
        <form method="post" autocomplete="off">
            <div class="split">
                <div>
                    <label>Section *</label>
                    <select name="department" required><option value="" <?=$department===''?'selected':''?>>Select Section / අංශය තෝරන්න</option><?php foreach($departments as $key=>$section):?><option value="<?=h($key)?>" <?=$department===$key?'selected':''?>><?=h($section['label'])?></option><?php endforeach;?></select>
                    <label>Full name *</label><input name="full_name" value="<?=h($values['full_name'])?>" maxlength="120" autocomplete="name" required>
                    <label>Position *</label><input name="position" value="<?=h($values['position'])?>" maxlength="120" placeholder="Officer / Senior Officer" required>
                    <label>NIC *</label><input name="nic" value="<?=h($values['nic'])?>" maxlength="30" required>
                    <label>Phone *</label><input name="phone" value="<?=h($values['phone'])?>" maxlength="30" autocomplete="tel" required>
                </div>
                <div>
                    <label>Email</label><input type="email" name="email" value="<?=h($values['email'])?>" maxlength="120" autocomplete="email">
                    <label>Username *</label><input name="username" value="<?=h($values['username'])?>" maxlength="80" autocomplete="username" required>
                    <label>Password *</label><input type="password" name="password" minlength="6" autocomplete="new-password" required><p class="muted">Use at least 6 characters.</p>
                </div>
            </div>
            <div class="btn-row"><button class="primary-btn" type="submit">Create Officer Account</button></div>
        </form>
    </div>
</div>
<?php endif;?>
<?php include __DIR__.'/includes/footer.php'; ?>
