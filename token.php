<?php
require_once __DIR__.'/config.php'; require_login();
if(role()!=='passenger') redirect('dashboard.php');
require_once __DIR__.'/includes/token_qr.php';
$title='Get Token / Token ගන්න'; $err=''; $uid=(int)($_SESSION['user']['id']??0);
$profile=null;
$p=$conn->prepare('SELECT u.full_name,p.* FROM users u LEFT JOIN passenger_profiles p ON p.user_id=u.id WHERE u.id=? LIMIT 1');
$p->bind_param('i',$uid); $p->execute(); $profile=$p->get_result()->fetch_assoc();
$departments=['control'=>'Control Section / පාලන අංශය','driving'=>'Driving / Operation Section / ධාවන / මෙහෙයුම් අංශය','accounts'=>'Accounts Section / ගිණුම් අංශය'];
$taskOptionsByDepartment=department_tasks();
$selectedDepartment=$profile['department']??'';
if(!isset($departments[$selectedDepartment])) $selectedDepartment='';
$taskOptions=$taskOptionsByDepartment[$selectedDepartment]??[];
$taskLabels=[];
foreach($taskOptionsByDepartment as $departmentTasks){
 foreach($departmentTasks as $task) $taskLabels[$task[0]]=$task[0].' / '.$task[1];
}
$tokenStatusLabels=['pending'=>'Pending / පොරොත්තුවෙන්','issued'=>'Issued / නිකුත් කර ඇත','completed'=>'Completed / සම්පූර්ණයි','cancelled'=>'Cancelled / අවලංගුයි'];
// Ensure lookup tables exist even when an older RPTA database is being used.
$conn->query("CREATE TABLE IF NOT EXISTS bus_stands (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL, district VARCHAR(80) NOT NULL, address VARCHAR(255) NULL, status VARCHAR(40) DEFAULT 'Active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$conn->query("CREATE TABLE IF NOT EXISTS vehicles (id INT AUTO_INCREMENT PRIMARY KEY, vehicle_type VARCHAR(80) NOT NULL, registration_no VARCHAR(40) NOT NULL, operator_name VARCHAR(150) NULL, route VARCHAR(160) NULL, status VARCHAR(40) DEFAULT 'Active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
// Repair older tables that may not have the status column.
@$conn->query("ALTER TABLE bus_stands ADD COLUMN status VARCHAR(40) DEFAULT 'Active'");
@$conn->query("ALTER TABLE vehicles ADD COLUMN status VARCHAR(40) DEFAULT 'Active'");
// Seed a few relevant Southern Province locations/vehicle records if the tables are empty.
$bsCount=$conn->query("SELECT COUNT(*) AS c FROM bus_stands");
if($bsCount && (int)$bsCount->fetch_assoc()['c']===0){
  $seed=[['Galle Main Bus Stand','Galle'],['Matara Bus Stand','Matara'],['Weligama Bus Stand','Matara'],['Akuressa Bus Stand','Matara'],['Tangalle Bus Stand','Hambantota'],['Ambalangoda Bus Stand','Galle']];
  $ins=$conn->prepare("INSERT INTO bus_stands(name,district) VALUES(?,?)");
  foreach($seed as $x){$ins->bind_param('ss',$x[0],$x[1]);$ins->execute();}
}
$vhCount=$conn->query("SELECT COUNT(*) AS c FROM vehicles");
if($vhCount && (int)$vhCount->fetch_assoc()['c']===0){
  $seedV=[['SLTB Bus / CTB','SP-CTB-001','SLTB','Matara - Galle'],['Private Bus','SP-PRV-001','Private Operator','Matara - Galle'],['School Van / School Transport','SP-SCH-001','School Transport','Southern Province'],['Three-Wheeler','SP-TW-001','Passenger Service','Southern Province']];
  $ins=$conn->prepare("INSERT INTO vehicles(vehicle_type,registration_no,operator_name,route) VALUES(?,?,?,?)");
  foreach($seedV as $x){$ins->bind_param('ssss',$x[0],$x[1],$x[2],$x[3]);$ins->execute();}
}
$stands=$conn->query("SELECT * FROM bus_stands WHERE status='Active' ORDER BY district,name");
$vehicles=$conn->query("SELECT * FROM vehicles WHERE status='Active' ORDER BY vehicle_type,registration_no");
// Never let a lookup-query failure crash the token form.
if(!$stands){ $stands=$conn->query("SELECT * FROM bus_stands ORDER BY district,name"); }
if(!$vehicles){ $vehicles=$conn->query("SELECT * FROM vehicles ORDER BY vehicle_type,registration_no"); }
if($_SERVER['REQUEST_METHOD']==='POST'){
 $full=trim($_POST['full_name']??'');
 $nic=trim($_POST['nic']??'');
 $phone=trim($_POST['phone']??'');
 $postedDepartment=trim($_POST['department']??'');
 if(isset($departments[$postedDepartment])) $selectedDepartment=$postedDepartment;
 $taskOptions=$taskOptionsByDepartment[$selectedDepartment]??[];
 $service=trim($_POST['service_type']??''); $date=$_POST['preferred_date']??''; $time=$_POST['preferred_time']??'';
 if(!$full||!$nic||!$phone||!isset($departments[$postedDepartment])||!$service||!in_array($service,array_column($taskOptions,0),true)||!$date||!$time) $err='Please fill in your name, NIC and phone, then select a section, task, preferred date and time. / කරුණාකර ඔබගේ නම, ජාතික හැඳුනුම්පත් අංකය සහ දුරකථන අංකය ඇතුළත් කර අංශය, කාර්යය, කැමති දිනය සහ වේලාව තෝරන්න.';
 else {
  $no='RPTA-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,6));
  $s=$conn->prepare("INSERT INTO tokens(token_no,user_id,full_name,nic,phone,service_type,department,preferred_date,preferred_time,status) VALUES(?,?,?,?,?,?,?,?,?,'pending')");
  $s->bind_param('sisssssss',$no,$uid,$full,$nic,$phone,$service,$selectedDepartment,$date,$time);
  if($s->execute()) { $createdNo=$no; $q=$conn->prepare('SELECT * FROM tokens WHERE token_no=? AND user_id=? LIMIT 1'); $q->bind_param('si',$createdNo,$uid); $q->execute(); $created=$q->get_result()->fetch_assoc(); }
  else $err='Could not save the token. Please try again. / ටෝකනය සුරැකීමට නොහැකි විය. නැවත උත්සාහ කරන්න.';
 }
}
$department=$selectedDepartment;
$createdNo=trim($_GET['created']??''); if(!isset($created)) $created=null;
if($createdNo && !$created){
 $q=$conn->prepare('SELECT * FROM tokens WHERE token_no=? AND user_id=? LIMIT 1'); $q->bind_param('si',$createdNo,$uid); $q->execute(); $created=$q->get_result()->fetch_assoc();
}
$recent=[]; $rt=$conn->prepare('SELECT token_no,service_type,preferred_date,preferred_time,status,created_at FROM tokens WHERE user_id=? ORDER BY id DESC LIMIT 10');
$rt->bind_param('i',$uid); $rt->execute(); $recent=$rt->get_result()->fetch_all(MYSQLI_ASSOC);
$completionNotifications=[];
$notificationStmt=$conn->prepare("SELECT n.token_id,t.token_no,t.service_type FROM client_notifications n JOIN tokens t ON t.id=n.token_id WHERE n.user_id=? AND n.notification_type='task_completed' AND t.status='completed' AND NOT EXISTS(SELECT 1 FROM feedback f WHERE f.token_id=t.id AND f.user_id=n.user_id) ORDER BY n.created_at DESC LIMIT 5");
if(!$notificationStmt){
 error_log('Could not prepare token completion notification query: '.$conn->error);
 $err='Could not load task completion notifications. Please contact support. / කාර්යය සම්පූර්ණ කිරීමේ දැනුම්දීම් ලබාගත නොහැක. සහාය සඳහා අමතන්න.';
} else {
 $notificationStmt->bind_param('i',$uid); $notificationStmt->execute(); $completionNotifications=$notificationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
include 'includes/header.php';
?>
<div class="page">
<div class="section-title"><div><span class="badge">CLIENT TOKEN / සේවාලාභී ටෝකනය</span><h2>🎫 Get Client Token / සේවාලාභී ටෝකනය ලබාගන්න</h2><p class="muted">Choose a section and a task to request a token. / ටෝකනයක් ඉල්ලීමට අංශයක් සහ කාර්යයක් තෝරන්න.</p></div><a class="secondary-btn" href="<?=h(BASE_URL)?>/dashboard.php">← Dashboard / පුවරුව</a></div>
 <?php if($err):?><div class="notice danger"><?=h($err)?></div><?php endif;?>
 <?php foreach($completionNotifications as $notification):?><div class="notice success" role="status"><strong>Task completed / කාර්යය සම්පූර්ණයි:</strong> <?=h($taskLabels[$notification['service_type']]??$notification['service_type'])?> (<?=h($notification['token_no'])?>). <a class="table-link" href="<?=h(BASE_URL)?>/feedback.php?token=<?=(int)$notification['token_id']?>">Give feedback now / දැන් ප්‍රතිචාර ලබා දෙන්න</a></div><?php endforeach;?>
 <?php if($created):?>
   <div class="notice success">✅ Token created successfully! / ටෝකනය සාර්ථකව නිකුත් කරන ලදී!</div>
 <?php endif;?>
 <div class="form-card token-form-card">
  <div class="notice info">Client type / සේවාලාභී වර්ගය: <?=h($profile['customer_type']??'Client')?></div>
  <form method="post">
  <h3>Client Details / සේවාලාභී විස්තර</h3>
  <div class="split"><div><label for="token-full-name">Full Name / සම්පූර්ණ නම *</label><input id="token-full-name" name="full_name" value="<?=h($_POST['full_name']??$profile['full_name']??'')?>" autocomplete="name" required><label for="token-nic">NIC Number / ජාතික හැඳුනුම්පත් අංකය *</label><input id="token-nic" name="nic" value="<?=h($_POST['nic']??$profile['nic']??'')?>" required><label for="token-phone">Phone Number / දුරකථන අංකය *</label><input id="token-phone" name="phone" type="tel" value="<?=h($_POST['phone']??$profile['phone']??'')?>" autocomplete="tel" required></div><div><label for="token-department">Select Section / අංශය තෝරන්න *</label><select name="department" id="token-department" required><option value="" <?=$selectedDepartment===''?'selected':''?>>Select a section / අංශයක් තෝරන්න</option><?php foreach($departments as $key=>$label):?><option value="<?=h($key)?>" <?=$selectedDepartment===$key?'selected':''?>><?=h($label)?></option><?php endforeach;?></select><label for="token-service">Select task / කාර්යය තෝරන්න *</label><select name="service_type" id="token-service" required><option value="">Select a section first / පළමුව අංශයක් තෝරන්න</option><?php foreach($taskOptions as $task):?><option value="<?=h($task[0])?>" <?=($_POST['service_type']??'')===$task[0]?'selected':''?>><?=h($task[0])?> / <?=h($task[1])?></option><?php endforeach;?></select></div></div>
  <?php $currentSriLankaTime=new DateTimeImmutable('now',new DateTimeZone('Asia/Colombo')); ?>
  <hr><div class="split"><div><label>Preferred Date / දිනය *</label><input type="date" name="preferred_date" value="<?=h($_POST['preferred_date']??$currentSriLankaTime->format('Y-m-d'))?>" min="<?=h($currentSriLankaTime->format('Y-m-d'))?>" required></div><div><label>Preferred Time / වේලාව *</label><input type="time" name="preferred_time" value="<?=h($_POST['preferred_time']??$currentSriLankaTime->format('H:i'))?>" required></div></div>
   <div class="token-final-action">
    <div class="token-final-title">🎫 Create a Token / ටෝකනයක් සාදන්න</div>
    <p class="muted">Fill in the details above, then create your token QR code. / ඉහත විස්තර පුරවා ඔබගේ ටෝකන් QR කේතය සාදන්න.</p>
    <button class="primary-btn token-download-final" type="submit">CREATE TOKEN QR / ටෝකන් QR සාදන්න</button>
   </div>
  <?php if($created): ?>
  <div id="token-download" class="token-download-box token-qr-box">
    <div><div class="badge">TOKEN READY / ටෝකනය සූදානම්</div><h3>Token No / ටෝකන් අංකය: <?=h($created['token_no'])?></h3><p><b><?=h($created['full_name'])?></b> / <?=h($taskLabels[$created['service_type']]??$created['service_type'])?></p><p class="muted">Date / දිනය: <?=h($created['preferred_date'])?> &nbsp; Time / වේලාව: <?=h($created['preferred_time'])?></p><a class="primary-btn token-download-btn" href="<?=h(token_qr_data_uri($created['token_no']))?>" download="<?=h(preg_replace('/[^A-Za-z0-9_-]/','_',$created['token_no']))?>.svg">Download Token QR / ටෝකන් QR බාගත කරන්න</a><p><a class="secondary-btn" href="<?=h(BASE_URL)?>/feedback.php">Give Feedback / ප්‍රතිචාර ලබා දෙන්න</a></p></div>
    <img class="token-qr-image" src="<?=h(token_qr_data_uri($created['token_no']))?>" alt="QR code for token <?=h($created['token_no'])?>">
  </div>
  <?php endif; ?>
  </form>
  <script>
   const taskOptionsByDepartment=<?=json_encode($taskOptionsByDepartment,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;
   const departmentSelect=document.getElementById('token-department');
   const serviceSelect=document.getElementById('token-service');
   departmentSelect.addEventListener('change',function(){
    serviceSelect.replaceChildren(new Option('Select a task / කාර්යයක් තෝරන්න',''));
    (taskOptionsByDepartment[departmentSelect.value]||[]).forEach(function(task){
     serviceSelect.add(new Option(task[0]+' / '+task[1],task[0]));
    });
   });
  </script>
 </div>
 <?php if($recent):?><div class="card recent-token-card"><h3>My Recent Tokens / මගේ මෑත ටෝකන</h3><div class="table-wrap"><table class="table"><thead><tr><th>Token / ටෝකනය</th><th>Service / සේවාව</th><th>Date / දිනය</th><th>Time / වේලාව</th><th>Status / තත්ත්වය</th><th>QR Code / QR කේතය</th></tr></thead><tbody><?php foreach($recent as $rt):?><tr><td><b><?=h($rt['token_no'])?></b></td><td><?=h($taskLabels[$rt['service_type']]??$rt['service_type'])?></td><td><?=h($rt['preferred_date'])?></td><td><?=h($rt['preferred_time'])?></td><td><?=h($tokenStatusLabels[$rt['status']]??$rt['status'])?></td><td><a href="<?=h(token_qr_data_uri($rt['token_no']))?>" download="<?=h(preg_replace('/[^A-Za-z0-9_-]/','_',$rt['token_no']))?>.svg"><img class="token-qr-thumb" src="<?=h(token_qr_data_uri($rt['token_no']))?>" alt="QR code / QR කේතය: <?=h($rt['token_no'])?>"></a></td></tr><?php endforeach;?></tbody></table></div></div><?php endif;?>
</div>
<?php if($created): ?><script>window.addEventListener('load',function(){var x=document.getElementById('token-download');if(x)x.scrollIntoView({behavior:'smooth',block:'center'});});</script><?php endif; ?>
<?php include 'includes/footer.php'; ?>
