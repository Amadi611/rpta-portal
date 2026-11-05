<?php
require_once __DIR__.'/config.php';
$authenticated=logged_in();
if($authenticated && role()!=='passenger') redirect('dashboard.php');
$uid=$authenticated?(int)($_SESSION['user']['id']??0):0;
$clientTypes=[
  'bus_owner'=>'Bus Owner',
  'bus_driver'=>'Bus Driver',
  'bus_conductor'=>'Bus Conductor',
  'threewheel_owner'=>'Three-Wheeler Owner',
  'passenger'=>'Passenger',
  'bus_stand'=>'Bus Stand',
  'other'=>'Other'
];
$clientTypeSinhala=[
  'bus_owner'=>'බස් හිමිකරු',
  'bus_driver'=>'බස් රියදුරු',
  'bus_conductor'=>'බස් කොන්දොස්තර',
  'threewheel_owner'=>'ත්‍රිරෝද රථ හිමිකරු',
  'passenger'=>'මගියා',
  'bus_stand'=>'බස් නැවතුම්පොළ',
  'other'=>'වෙනත්'
];
$busTypes=['bus_owner','bus_driver','bus_conductor'];
$profile=[]; $err=''; $ok=''; $formValues=[]; $permitRecord=null;
$departments=['control'=>'Control Section','driving'=>'Driving Section','accounts'=>'Account Section'];
if($authenticated){
  $s=$conn->prepare('SELECT u.full_name,u.username,p.* FROM users u LEFT JOIN passenger_profiles p ON p.user_id=u.id WHERE u.id=? LIMIT 1');
  $s->bind_param('i',$uid); $s->execute(); $profile=$s->get_result()->fetch_assoc() ?: [];
}
$oldTypeMap=['Bus owner'=>'bus_owner','Conductor'=>'bus_conductor','Three-wheeler owner'=>'threewheel_owner'];
$selectedType=trim($_GET['type']??'');
if(!$selectedType && $authenticated){
  $savedType=$profile['customer_type']??'';
  $selectedType=$oldTypeMap[$savedType]??array_search($savedType,$clientTypes,true)?:'';
}
if(!isset($clientTypes[$selectedType])) $selectedType='';
$lookupChoice='';

function findActivePermit($conn,$number){
  $stmt=$conn->prepare("SELECT permit_number,registration_number,owner_name,owner_nic,owner_phone,driver_name,driver_phone,conductor_name,conductor_phone FROM active_permits WHERE permit_status='Active' AND (permit_number=? OR registration_number=?) LIMIT 1");
  if(!$stmt) return null;
  $stmt->bind_param('ss',$number,$number); $stmt->execute();
  return $stmt->get_result()->fetch_assoc() ?: null;
}

if(($_GET['action']??'')==='lookup'){
  header('Content-Type: application/json; charset=utf-8');
  $lookupType=$_GET['type']??''; $number=trim($_GET['number']??'');
  if(!in_array($lookupType,$busTypes,true) || $number==='' || strlen($number)>40){
    http_response_code(400); echo json_encode(['found'=>false,'message'=>'Enter a valid permit or registration number. / වලංගු බලපත්‍ර හෝ ලියාපදිංචි අංකයක් ඇතුළත් කරන්න.']); exit;
  }
  $record=findActivePermit($conn,$number);
  if(!$record){echo json_encode(['found'=>false,'message'=>'No active record was found. Check the number or choose No to enter your details manually. / සක්‍රීය වාර්තාවක් හමු නොවීය. අංකය පරීක්ෂා කරන්න, නැතහොත් ඔබගේ තොරතුරු අතින් ඇතුළත් කිරීමට නැත තෝරන්න.']); exit;}
  $fields=[
    'bus_owner'=>['full_name'=>$record['owner_name'],'nic'=>$record['owner_nic'],'phone'=>$record['owner_phone']],
    'bus_driver'=>['full_name'=>$record['driver_name'],'nic'=>'','phone'=>$record['driver_phone']],
    'bus_conductor'=>['full_name'=>$record['conductor_name'],'nic'=>'','phone'=>$record['conductor_phone']]
  ];
  echo json_encode(['found'=>true,'details'=>$fields[$lookupType],'permit_number'=>$record['permit_number'],'registration_number'=>$record['registration_number']]); exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $selectedType=trim($_POST['client_type']??'');
  if(!isset($clientTypes[$selectedType])) $selectedType='';
  $name=trim($_POST['full_name']??''); $username=trim($_POST['username']??''); $password=$_POST['password']??'';
  $nic=trim($_POST['nic']??''); $phone=trim($_POST['phone']??'');
  $department=trim($_POST['department']??($profile['department']??'control'));
  $lookupChoice=$_POST['lookup_choice']??''; $reference=trim($_POST['reference_number']??'');
  $customerType=$clientTypes[$selectedType]??'';
  $formValues=['full_name'=>$name,'username'=>$username,'nic'=>$nic,'phone'=>$phone,'department'=>$department,'reference_number'=>$reference];
  if(in_array($selectedType,$busTypes,true) && $lookupChoice==='yes'){
    $permitRecord=$reference!==''?findActivePermit($conn,$reference):null;
  }
  if(!$selectedType || !$name || !$username || !$nic || !$phone || !isset($departments[$department]) || (!$authenticated && $password==='')){
    $err='Please complete all required fields / අවශ්‍ය සියලු තොරතුරු පුරවන්න.';
  } elseif(in_array($selectedType,$busTypes,true) && !in_array($lookupChoice,['yes','no'],true)){
    $err='Choose Yes or No for your bus permit or registration number. / ඔබගේ බස් බලපත්‍ර හෝ ලියාපදිංචි අංකය පිළිබඳ ඔව් හෝ නැත තෝරන්න.';
  } elseif(in_array($selectedType,$busTypes,true) && $lookupChoice==='yes' && !$permitRecord){
    $err='No active record was found for that number. Check it or choose No to enter your details manually. / එම අංකයට අදාළ සක්‍රීය වාර්තාවක් හමු නොවීය. අංකය පරීක්ෂා කරන්න, නැතහොත් තොරතුරු අතින් ඇතුළත් කිරීමට නැත තෝරන්න.';
  } elseif(strlen($name)>120 || strlen($username)>80){
    $err='Name or username is too long. / නම හෝ පරිශීලක නාමය දිග වැඩියි.';
  } elseif($password!=='' && !preg_match('/^[0-9]{6}$/',$password)){
    $err='Password must contain exactly 6 digits. / මුරපදය ඉලක්කම් 6කින් සමන්විත විය යුතුය.';
  } else {
    $check=$authenticated?$conn->prepare('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1'):$conn->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
    if($authenticated) $check->bind_param('si',$username,$uid); else $check->bind_param('s',$username);
    $check->execute();
    if($check->get_result()->num_rows){
      $err='That username is already in use. Please choose another. / මෙම පරිශීලක නාමය දැනටමත් භාවිතයේ ඇත. වෙනත් නමක් තෝරන්න.';
    } else {
      $permitNumber=$permitRecord['permit_number']??'';
      $vehicleRegistration=$permitRecord['registration_number']??'';
      $passwordHash=$password!==''?password_hash($password,PASSWORD_DEFAULT):'';
      $conn->begin_transaction();
      if($authenticated){
        $u=$password!==''?$conn->prepare('UPDATE users SET full_name=?,username=?,password_hash=? WHERE id=?'):$conn->prepare('UPDATE users SET full_name=?,username=? WHERE id=?');
        if($password!=='') $u->bind_param('sssi',$name,$username,$passwordHash,$uid); else $u->bind_param('ssi',$name,$username,$uid);
        $saved=$u->execute(); $newUid=$uid;
      } else {
        $u=$conn->prepare("INSERT INTO users(full_name,username,password_hash,role) VALUES(?,?,?,'passenger')");
        $u->bind_param('sss',$name,$username,$passwordHash); $saved=$u->execute(); $newUid=(int)$conn->insert_id;
      }
      if($saved){
        $p=$conn->prepare('INSERT INTO passenger_profiles(user_id,nic,phone,customer_type,department,permit_number,vehicle_registration) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nic=VALUES(nic),phone=VALUES(phone),customer_type=VALUES(customer_type),department=VALUES(department),permit_number=VALUES(permit_number),vehicle_registration=VALUES(vehicle_registration)');
        $p->bind_param('issssss',$newUid,$nic,$phone,$customerType,$department,$permitNumber,$vehicleRegistration); $saved=$p->execute();
      }
      if($saved){
        $conn->commit();
        if($authenticated){
          $_SESSION['user']['full_name']=$name; $_SESSION['user']['username']=$username;
          if($password!=='') $_SESSION['user']['password_hash']=$passwordHash;
          $ok='Registration details saved successfully. / ලියාපදිංචි තොරතුරු සාර්ථකව සුරකින ලදී.';
          $s->execute(); $profile=$s->get_result()->fetch_assoc() ?: [];
          $formValues=[];
        } else {
          $_SESSION['user']=['id'=>$newUid,'full_name'=>$name,'username'=>$username,'password_hash'=>$passwordHash,'role'=>'passenger'];
          $_SESSION['client_registration_success']='Registration successful. Your client account is ready. / ලියාපදිංචිය සාර්ථකයි. ඔබගේ සේවාලාභී ගිණුම දැන් සූදානම්.';
          redirect('dashboard.php');
        }
      } else {
        $conn->rollback(); $err='Could not save your registration details. Please try again. / ලියාපදිංචි තොරතුරු සුරැකීමට නොහැකි විය. නැවත උත්සාහ කරන්න.';
      }
    }
  }
}
$showRegistrationForm=!in_array($selectedType,$busTypes,true) || $lookupChoice==='no' || ($lookupChoice==='yes' && $permitRecord);
$clientTypeIcons=[
  'bus_owner'=>'<svg viewBox="0 0 24 24"><path d="M4 17V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v9"/><path d="M4 12h16M7 17v2m10-2v2M7 9h.01M17 9h.01"/><circle cx="7.5" cy="16" r="1.5"/><circle cx="16.5" cy="16" r="1.5"/></svg>',
  'bus_driver'=>'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="2.5"/><path d="m5.8 6.2 4.4 4.4m7.9-4.4-4.4 4.4m-1.7 4v4.9"/></svg>',
  'bus_conductor'=>'<svg viewBox="0 0 24 24"><path d="M5 7.5A2.5 2.5 0 0 1 7.5 5H19v14H7.5A2.5 2.5 0 0 1 5 16.5z"/><path d="M5 8h14m-10-3v3m0 11v-3m4-5h3"/></svg>',
  'threewheel_owner'=>'<svg viewBox="0 0 24 24"><circle cx="6" cy="17" r="2"/><circle cx="18" cy="17" r="2"/><circle cx="12" cy="18" r="1.5"/><path d="m6 15 2-6h7l3 6M9 9l2-3h4l2 3m-7 0 2 6m5-6-3 6M8 15h10"/></svg>',
  'passenger'=>'<svg viewBox="0 0 24 24"><circle cx="12" cy="7.5" r="3"/><path d="M5.5 20v-1.5a6.5 6.5 0 0 1 13 0V20"/></svg>',
  'bus_stand'=>'<svg viewBox="0 0 24 24"><path d="M4 20V8h13v12M2.5 8h16L16 4H5zM8 12h5m-5 3h5"/><path d="M20 11v9m-1.5-9h3v4h-3z"/></svg>',
  'other'=>'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M8 12h.01m4 0h.01m4 0h.01"/></svg>'
];
$pageStyles=['assets/registration.css'];
$title='Client Registration / සේවාලාභී ලියාපදිංචිය'; include 'includes/header.php';
?>
<div class="page narrow registration-page">
  <div class="registration-heading"><div><span class="badge">CLIENT REGISTRATION / සේවාලාභී ලියාපදිංචිය</span><h2>Choose your client type / ඔබගේ සේවාලාභී වර්ගය තෝරන්න</h2><p class="muted">Select the account type that matches your service. / ඔබට ගැළපෙන සේවා ගිණුම් වර්ගය තෝරන්න.</p></div><a class="secondary-btn" href="<?= $authenticated ? 'dashboard.php' : 'login.php' ?>"><?= $authenticated ? '← Dashboard / පුවරුව' : 'Login / ඇතුල් වන්න' ?></a></div>
  <?php if($err):?><div class="notice danger" role="alert"><?=h($err)?></div><?php endif;?>
  <?php if($ok):?><div class="notice success" role="status"><?=h($ok)?></div><?php endif;?>
  <div class="registration-cards">
    <?php foreach($clientTypes as $typeKey=>$typeLabel):?>
      <a class="registration-card <?=$selectedType===$typeKey?'selected':''?>" href="register.php?type=<?=h($typeKey)?>"><span class="registration-card-mark" aria-hidden="true"><?=$clientTypeIcons[$typeKey]?></span><span class="registration-card-label"><span><?=h($typeLabel)?></span><small><?=h($clientTypeSinhala[$typeKey])?></small></span><span class="registration-card-arrow" aria-hidden="true">→</span></a>
    <?php endforeach;?>
  </div>
  <?php if($selectedType):?>
    <section class="form-card registration-form-card">
      <div class="registration-subheading"><div><span class="badge"><?=h(strtoupper($clientTypes[$selectedType]))?> / <?=h($clientTypeSinhala[$selectedType])?></span><h3><?=h($clientTypes[$selectedType])?> Registration / <?=h($clientTypeSinhala[$selectedType])?> ලියාපදිංචිය</h3></div><a class="secondary-btn" href="register.php">All client types / සියලුම සේවාලාභී වර්ග</a></div>
      <?php if(in_array($selectedType,$busTypes,true)):?>
        <fieldset class="permit-question" id="permitQuestion"><legend>Do you have a bus registration number or bus permit number? / ඔබට බස් ලියාපදිංචි අංකයක් හෝ බස් බලපත්‍ර අංකයක් තිබේද?</legend>
          <div class="choice-options"><label><input type="radio" name="permit_choice" value="yes" <?=$lookupChoice==='yes'?'checked':''?>> Yes / ඔව්</label><label><input type="radio" name="permit_choice" value="no" <?=$lookupChoice==='no'?'checked':''?>> No / නැත</label></div>
        </fieldset>
        <div id="permitLookupPanel" <?=$lookupChoice==='yes'?'':'hidden'?>><form id="permitLookupForm"><label for="referenceInput">Bus registration number or permit number / බස් ලියාපදිංචි අංකය හෝ බලපත්‍ර අංකය</label><div class="permit-search-row"><input id="referenceInput" name="number" value="<?=h($formValues['reference_number']??'')?>" maxlength="40" autocomplete="off"><button class="secondary-btn" type="submit">Find record / වාර්තාව සොයන්න</button></div><p id="lookupMessage" class="muted" role="status"></p></form></div>
      <?php endif;?>
      <form id="registrationForm" method="post" action="register.php?type=<?=h($selectedType)?>" <?=$showRegistrationForm?'':'hidden'?> novalidate>
        <form id="registrationForm" method="post" action="register.php?type=<?=h($selectedType)?>" <?=$showRegistrationForm?'':'hidden'?>>
        <input type="hidden" name="client_type" value="<?=h($selectedType)?>">
        <input type="hidden" name="lookup_choice" id="lookupChoiceValue" value="<?=h($lookupChoice)?>">
        <input type="hidden" name="reference_number" id="referenceNumberValue" value="<?=h($formValues['reference_number']??'')?>">
        <input type="hidden" name="department" value="<?=h($formValues['department']??($profile['department']??'control'))?>">
        <div class="registration-fields">
          <div><label for="fullName">Full Name / සම්පූර්ණ නම *</label><input id="fullName" name="full_name" value="<?=h($formValues['full_name']??($profile['full_name']??''))?>" autocomplete="name" required></div>
          <div><label for="username">Username / පරිශීලක නාමය *</label><input id="username" name="username" value="<?=h($formValues['username']??($profile['username']??''))?>" maxlength="80" autocomplete="username" required></div>
          <div><label for="password">Password / මුරපදය <?= $authenticated ? '(leave blank to keep current / වත්මන් මුරපදය තබා ගැනීමට හිස්ව තබන්න)' : '*' ?></label><input id="password" type="password" name="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" <?= $authenticated ? '' : 'required' ?>><p class="muted">Choose exactly 6 digits / ඉලක්කම් 6ක් තෝරන්න<?= $authenticated ? '; leave blank to keep your current password / වත්මන් මුරපදය තබා ගැනීමට හිස්ව තබන්න.' : '.' ?></p></div>
          <div><label for="nic">NIC / ජාතික හැඳුනුම්පත් අංකය *</label><input id="nic" name="nic" value="<?=h($formValues['nic']??($profile['nic']??''))?>" autocomplete="off" required></div>
          <div><label for="phone">Phone Number / දුරකථන අංකය *</label><input id="phone" name="phone" value="<?=h($formValues['phone']??($profile['phone']??''))?>" autocomplete="tel" required></div>
        </div>
        <div class="btn-row registration-actions"><button class="primary-btn" type="submit"><?= $authenticated ? 'Save Details / තොරතුරු සුරකින්න' : 'Create Client Account / සේවාලාභී ගිණුම සාදන්න' ?></button><?php if($authenticated && ($ok || !empty($profile['nic']))):?><a class="secondary-btn" href="token.php">Get Token / ටෝකනයක් ලබාගන්න</a><?php endif;?></div>
      </form>
    </section>
  <?php endif;?>
</div>
<script>
(()=>{
  const clientType=<?=json_encode($selectedType)?>;
  const lookupTypes=['bus_owner','bus_driver','bus_conductor'];
  const registrationForm=document.getElementById('registrationForm');
  const lookupPanel=document.getElementById('permitLookupPanel');
  const lookupForm=document.getElementById('permitLookupForm');
  const lookupMessage=document.getElementById('lookupMessage');
  const choiceInput=document.getElementById('lookupChoiceValue');
  const referenceValue=document.getElementById('referenceNumberValue');
  const showForm=(show)=>{if(registrationForm) registrationForm.hidden=!show;};
  if(!lookupTypes.includes(clientType)) return;
  const permitQuestion=document.getElementById('permitQuestion');
  if(permitQuestion) requestAnimationFrame(()=>permitQuestion.scrollIntoView({behavior:'smooth',block:'center'}));
  document.querySelectorAll('input[name="permit_choice"]').forEach((radio)=>radio.addEventListener('change',()=>{
    choiceInput.value=radio.value;
    if(lookupPanel) lookupPanel.hidden=radio.value!=='yes';
    showForm(radio.value==='no');
    if(lookupMessage) lookupMessage.textContent='';
  }));
  if(lookupForm) lookupForm.addEventListener('submit',async(event)=>{
    event.preventDefault();
    const number=document.getElementById('referenceInput').value.trim();
    if(!number){lookupMessage.textContent='Enter your bus registration or permit number. / ඔබගේ බස් ලියාපදිංචි හෝ බලපත්‍ර අංකය ඇතුළත් කරන්න.';return;}
    lookupMessage.textContent='Searching... / සොයමින්...';
    try{
      const response=await fetch(`register.php?action=lookup&type=${encodeURIComponent(clientType)}&number=${encodeURIComponent(number)}`);
      const result=await response.json();
      if(!result.found){showForm(false);lookupMessage.textContent=result.message;return;}
      document.getElementById('fullName').value=result.details.full_name||'';
      document.getElementById('nic').value=result.details.nic||'';
      document.getElementById('phone').value=result.details.phone||'';
      referenceValue.value=number;
      lookupMessage.textContent='Record found. Review the details, add your username and password, and complete any missing fields. / වාර්තාව හමු විය. තොරතුරු පරීක්ෂා කර, පරිශීලක නාමය සහ මුරපදය ඇතුළත් කර අවශ්‍ය හිස් තොරතුරු සම්පූර්ණ කරන්න.';
      showForm(true);
    }catch(error){showForm(false);lookupMessage.textContent='Could not search the records. Please try again. / වාර්තා සෙවීමට නොහැකි විය. නැවත උත්සාහ කරන්න.';}
  });
})();
</script>
<?php include 'includes/footer.php'; ?>
