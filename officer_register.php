<?php
require_once __DIR__.'/config.php';
require_login();
if(role()!=='officer_registrar' && !can_manage_accounts()) redirect('dashboard.php');
$departments=[
    'control'=>['label'=>'Control','role'=>'control_admin'],
    'accounts'=>['label'=>'Account','role'=>'accounts_admin'],
    'driving'=>['label'=>'Driving','role'=>'driving_admin']
];
$department=$_POST['department']??($_GET['department']??'');
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
        $check->bind_param('s',$values['username']);
        $check->execute();
        if($check->get_result()->num_rows){
            $error='That username is already in use. Please choose another.';
        } else {
            $conn->begin_transaction();
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $userStmt=$conn->prepare('INSERT INTO users(full_name,username,password_hash,role) VALUES(?,?,?,?)');
            $staffRole=$departments[$department]['role'];
            $userStmt->bind_param('ssss',$values['full_name'],$values['username'],$hash,$staffRole);
            $created=$userStmt->execute();
            $userId=(int)$conn->insert_id;
            if($created){
                $profileStmt=$conn->prepare('INSERT INTO officer_profiles(user_id,nic,phone,email,position,department) VALUES(?,?,?,?,?,?)');
                $email=$values['email']!==''?$values['email']:null;
                $profileStmt->bind_param('isssss',$userId,$values['nic'],$values['phone'],$email,$values['position'],$department);
                $created=$profileStmt->execute();
            }
            if($created){
                $conn->commit();
                if(role()==='officer_registrar') redirect('dashboard.php?officer_created=1');
                redirect('admin/officers.php?created=1');
            }
            $conn->rollback();
            $error='Could not create the officer account. Please try again.';
        }
    }
}
$title='Officer Registration';
include __DIR__.'/includes/header.php';
?>
<div class="page narrow">
    <div class="form-card">
        <div class="section-title"><div><span class="badge">OFFICER REGISTRATION</span><h2>Officer Registration</h2><p class="muted">Create an officer account for the selected service section.</p></div><a class="secondary-btn" href="<?=role()==='officer_registrar'?'dashboard.php':'admin/officers.php'?>">← Back</a></div>
        <?php if($error):?><div class="notice danger" role="alert"><?=h($error)?></div><?php endif;?>
        <form method="post" autocomplete="off">
            <div class="split">
                <div>
                    <label>Section *</label>
                    <select name="department" required><?php foreach($departments as $key=>$section):?><option value="<?=h($key)?>" <?=$department===$key?'selected':''?>><?=h($section['label'])?></option><?php endforeach;?></select>
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
<?php include __DIR__.'/includes/footer.php'; ?>
