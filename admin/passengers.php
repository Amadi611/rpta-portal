<?php
require_once __DIR__.'/../config.php';
require_login();
if(!in_array(role(),['admin','control_admin','driving_admin','accounts_admin','super_admin','data_entry'],true)) redirect('../dashboard.php');
$department=staff_department();
$canManage=can_manage_accounts();
$error='';
$customerTypes=['Bus Owner','Bus Driver','Bus Conductor','Three-Wheeler Owner','Passenger','Bus Stand','Other'];
$clientCategories=[
	'bus_owner'=>'Bus Owner',
	'bus_driver'=>'Bus Driver',
	'bus_conductor'=>'Bus Conductor',
	'threewheel_owner'=>'Three-Wheeler Owner',
	'passenger'=>'Passenger',
	'bus_stand'=>'Bus Stand',
	'other'=>'Other'
];
if($_SERVER['REQUEST_METHOD']==='POST'){
	if(!$canManage){http_response_code(403);exit('You are not allowed to create client accounts.');}
	$name=trim($_POST['full_name']??''); $username=trim($_POST['username']??''); $password=$_POST['password']??'';
	$nic=trim($_POST['nic']??''); $phone=trim($_POST['phone']??'');
	$customerType=trim($_POST['customer_type']??''); $customerDepartment=$department??'control';
	if(!$name || !$username || !$password || !$nic || !$phone || !in_array($customerType,$customerTypes,true)){
		$error='Please complete all required fields.';
	}elseif(strlen($name)>120 || strlen($username)>80){
		$error='Name or username is too long.';
	}elseif(preg_match('/^.{6}$/us',$password)!==1){
		$error='Password must contain exactly 6 characters.';
	}else{
		$check=$conn->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
		$check->bind_param('s',$username); $check->execute();
		if($check->get_result()->num_rows){
			$error='That username is already in use. Please choose another.';
		}else{
			$conn->begin_transaction();
			$hash=password_hash($password,PASSWORD_DEFAULT);
			$userStmt=$conn->prepare("INSERT INTO users(full_name,username,password_hash,role) VALUES(?,?,?,'passenger')");
			$userStmt->bind_param('sss',$name,$username,$hash);
			$created=$userStmt->execute(); $newUserId=(int)$conn->insert_id;
			if($created){
				$profileStmt=$conn->prepare('INSERT INTO passenger_profiles(user_id,nic,phone,customer_type,department) VALUES(?,?,?,?,?)');
				$profileStmt->bind_param('issss',$newUserId,$nic,$phone,$customerType,$customerDepartment);
				$created=$profileStmt->execute();
			}
			if($created){$conn->commit();redirect('admin/passengers.php?created=1');}
			$conn->rollback(); $error='Could not create the client account. Please try again.';
		}
	}
}
$sql='SELECT u.id,u.full_name,u.username,p.nic,p.phone,p.customer_type,u.created_at FROM users u JOIN passenger_profiles p ON p.user_id=u.id WHERE u.role="passenger"';
if($department!==null) $sql.=' AND p.department=?';
$sql.=' ORDER BY u.id DESC';
$stmt=$conn->prepare($sql);
if($department!==null) $stmt->bind_param('s',$department);
$stmt->execute();
$clientsByType=array_fill_keys(array_keys($clientCategories),[]);
$clientTypeMap=[
	'bus owner'=>'bus_owner',
	'bus driver'=>'bus_driver',
	'bus conductor'=>'bus_conductor',
	'conductor'=>'bus_conductor',
	'three-wheeler owner'=>'threewheel_owner',
	'three wheeler owner'=>'threewheel_owner',
	'passenger'=>'passenger',
	'bus stand'=>'bus_stand',
	'other'=>'other',
	''=>'other'
];
$rows=$stmt->get_result();
while($client=$rows->fetch_assoc()){
	$normalizedClientType=strtolower(trim($client['customer_type']??''));
	$typeKey=$clientTypeMap[$normalizedClientType]??'other';
	if($normalizedClientType==='' || !isset($clientTypeMap[$normalizedClientType])) $client['customer_type']='Other';
	$clientsByType[$typeKey][]=$client;
}
$selectedType=$_GET['type']??'bus_owner';
if(!isset($clientCategories[$selectedType])) $selectedType='bus_owner';
$pageStyles=['assets/client-records.css'];
$title='Clients/Add Client';
include '../includes/header.php';
?>
<div class="page">
	<div class="section-title"><div><h2><?=h($department?ucfirst($department).' Section ':'')?>Clients/Add Client</h2><p class="muted">Browse clients by account type.</p></div><a class="secondary-btn" href="../dashboard.php">← Dashboard</a></div>
	<?php if(isset($_GET['created'])):?><div class="notice success" role="status">Client account created successfully.</div><?php endif;?>
	<?php if($error):?><div class="notice danger" role="alert"><?=h($error)?></div><?php endif;?>
	<nav class="client-type-cards" aria-label="Filter clients by type">
		<?php foreach($clientCategories as $typeKey=>$typeLabel):$count=count($clientsByType[$typeKey]);?>
			<a class="client-type-card <?=$selectedType===$typeKey?'selected':''?>" href="?type=<?=h($typeKey)?>" <?=$selectedType===$typeKey?'aria-current="page"':''?>><span><?=h($typeLabel)?></span><strong><?=$count?></strong><small><?=$count===1?'client':'clients'?></small></a>
		<?php endforeach;?>
	</nav>
	<section class="client-records-panel" aria-labelledby="client-records-title">
		<h3 id="client-records-title"><?=h($clientCategories[$selectedType])?> Clients <span><?=count($clientsByType[$selectedType])?></span></h3>
		<div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Username</th><th>Client type</th><th>NIC</th><th>Phone</th><th>Registered</th></tr></thead><tbody>
			<?php if(!$clientsByType[$selectedType]):?><tr><td colspan="6">No clients registered for this type.</td></tr><?php else:foreach($clientsByType[$selectedType] as $x):?><tr><td><?=h($x['full_name'])?></td><td><?=h($x['username'])?></td><td><?=h($x['customer_type'])?></td><td><?=h($x['nic'])?></td><td><?=h($x['phone'])?></td><td><?=h($x['created_at'])?></td></tr><?php endforeach;endif;?>
		</tbody></table></div>
	</section>
	<?php if($canManage):?><div class="form-card add-client-form"><h3>Add Client</h3><form method="post" autocomplete="off"><div class="split"><div><label>Full name *</label><input name="full_name" maxlength="120" required><label>Username *</label><input name="username" maxlength="80" autocomplete="username" required><label>Password *</label><input type="password" name="password" minlength="6" maxlength="6" pattern=".{6}" title="Use exactly six characters." autocomplete="new-password" required></div><div><label>NIC *</label><input name="nic" maxlength="30" required><label>Phone *</label><input name="phone" maxlength="30" autocomplete="tel" required><label>Client type *</label><select name="customer_type" required><option value="">Select type</option><?php foreach($customerTypes as $type):?><option value="<?=h($type)?>"><?=h($type)?></option><?php endforeach;?></select></div></div><div class="btn-row"><button class="primary-btn" type="submit">Create Client Account</button></div></form></div><?php endif;?>
</div>
<?php include '../includes/footer.php'; ?>