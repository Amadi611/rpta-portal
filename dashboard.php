<?php
require_once __DIR__.'/config.php';
require_login();
$name=$_SESSION['user']['full_name'];
$r=role();
$department=staff_department();
$uid=(int)$_SESSION['user']['id'];
$tokenSql=$r==='passenger'?'SELECT COUNT(*) c FROM tokens WHERE user_id=?':($department!==null?'SELECT COUNT(*) c FROM tokens WHERE department=?':($r==='officer_registrar'?'SELECT COUNT(*) c FROM tokens WHERE 1=0':'SELECT COUNT(*) c FROM tokens'));
$tokenStmt=$conn->prepare($tokenSql);
if($r==='passenger') $tokenStmt->bind_param('i',$uid); elseif($department!==null) $tokenStmt->bind_param('s',$department);
$tokenStmt->execute(); $tokenCount=(int)($tokenStmt->get_result()->fetch_assoc()['c']??0);
$feedbackSql=$department!==null?'SELECT COUNT(*) c FROM feedback f JOIN tokens t ON t.id=f.token_id WHERE t.department=?':'SELECT COUNT(*) c FROM feedback';
$feedbackStmt=$conn->prepare($feedbackSql);
if($department!==null) $feedbackStmt->bind_param('s',$department);
$feedbackStmt->execute(); $feedbackCount=(int)($feedbackStmt->get_result()->fetch_assoc()['c']??0);
$complaintCount=(int)($conn->query('SELECT COUNT(*) c FROM complaints')->fetch_assoc()['c']??0);
$docCount=(int)($conn->query('SELECT COUNT(*) c FROM documents WHERE active=1')->fetch_assoc()['c']??0);
$passSql=$department!==null?'SELECT COUNT(*) c FROM passenger_profiles WHERE department=?':'SELECT COUNT(*) c FROM passenger_profiles';
$passStmt=$conn->prepare($passSql);
if($department!==null) $passStmt->bind_param('s',$department);
$passStmt->execute(); $passCount=(int)($passStmt->get_result()->fetch_assoc()['c']??0);
$vehicleCount=(int)($conn->query('SELECT COUNT(*) c FROM vehicles')->fetch_assoc()['c']??0);
$standCount=(int)($conn->query('SELECT COUNT(*) c FROM bus_stands')->fetch_assoc()['c']??0);
$completedCount=0; $pendingCount=0;
if($department!==null){
	$statusCountStmt=$conn->prepare("SELECT SUM(status='completed') completed_count,SUM(status<>'completed') pending_count FROM tokens WHERE department=?");
	$statusCountStmt->bind_param('s',$department); $statusCountStmt->execute();
	$statusCounts=$statusCountStmt->get_result()->fetch_assoc();
	$completedCount=(int)($statusCounts['completed_count']??0); $pendingCount=(int)($statusCounts['pending_count']??0);
}
$canViewOperationalData=in_array($r,['data_entry','super_admin'],true);
$canViewAdminReports=in_array($r,['admin','super_admin'],true);
$completionNotifications=[];
$clientTasks=[];
$clientPendingCount=0;
$clientCompletedCount=0;
if($r==='passenger'){
	$notificationStmt=$conn->prepare("SELECT n.token_id,n.created_at,t.token_no,t.service_type FROM client_notifications n JOIN tokens t ON t.id=n.token_id WHERE n.user_id=? AND n.notification_type='task_completed' AND t.status='completed' AND NOT EXISTS(SELECT 1 FROM feedback f WHERE f.token_id=t.id AND f.user_id=n.user_id) ORDER BY n.created_at DESC LIMIT 5");
	$notificationStmt->bind_param('i',$uid);
	$notificationStmt->execute();
	$completionNotifications=$notificationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$clientTasksStmt=$conn->prepare('SELECT t.id,t.token_no,t.service_type,t.department,t.preferred_date,t.preferred_time,t.status,EXISTS(SELECT 1 FROM feedback f WHERE f.token_id=t.id AND f.user_id=t.user_id) AS feedback_submitted FROM tokens t WHERE t.user_id=? ORDER BY t.id DESC');
	$clientTasksStmt->bind_param('i',$uid);
	$clientTasksStmt->execute();
	$clientTasks=$clientTasksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
	foreach($clientTasks as $clientTask){
		if(in_array($clientTask['status'],['pending','issued'],true)) $clientPendingCount++;
		if($clientTask['status']==='completed') $clientCompletedCount++;
	}
}
$title=$r==='officer_registrar'?'Officer Registration Dashboard':($department!==null?'Officer Registration - '.ucfirst($department).' Dashboard':'Dashboard / පුවරුව');
$pageStyles=['assets/completion.css','assets/client-status.css'];
include 'includes/header.php';
?>
<?php if($r==='passenger' && $completionNotifications):$popupNotification=$completionNotifications[0];?>
<dialog class="completion-popup" id="completionPopup" aria-labelledby="completionPopupTitle" aria-describedby="completionPopupDescription">
	<div class="completion-popup-header"><span class="badge">TASK COMPLETED</span><button class="completion-popup-close" type="button" data-close-completion aria-label="Close completion message">×</button></div>
	<h2 id="completionPopupTitle">Your task is complete</h2>
	<p id="completionPopupDescription"><strong><?=h($popupNotification['service_type'])?></strong> (<?=h($popupNotification['token_no'])?>) has been completed.</p>
	<p class="muted">Please share your feedback about this service.</p>
	<div class="completion-popup-actions"><a class="primary-btn" href="feedback.php?token=<?=(int)$popupNotification['token_id']?>">Give feedback now</a><button class="secondary-btn" type="button" data-close-completion>Later</button></div>
</dialog>
<script>
const completionPopup=document.getElementById('completionPopup');
completionPopup?.querySelectorAll('[data-close-completion]').forEach(button=>button.addEventListener('click',()=>completionPopup.close()));
completionPopup?.addEventListener('click',event=>{if(event.target===completionPopup)completionPopup.close()});
if(completionPopup?.showModal)completionPopup.showModal();
</script>
<?php endif;?>
<div class="page"><div class="section-title"><div><span class="badge"><?=h(strtoupper($r))?></span><h2><?=h($title)?></h2><p class="muted">Welcome, <?=h($name)?>.</p></div><a class="danger-btn" href="logout.php">Logout / පිටවීම</a></div>
<?php if(isset($_GET['officer_created'])):?><div class="notice success" role="status">Officer account created successfully.</div><?php endif;?>
<?php foreach($completionNotifications as $notification):?><div class="notice success" role="status"><strong>Task completed:</strong> <?=h($notification['service_type'])?> (<?=h($notification['token_no'])?>). <a class="table-link" href="feedback.php?token=<?=(int)$notification['token_id']?>">Give feedback now</a></div><?php endforeach;?>
<?php if($r==='passenger'):?>
<section class="card client-status-card" aria-labelledby="client-status-title">
	<h3 id="client-status-title">My Tasks / My Status</h3>
	<div class="client-status-counts" aria-label="Filter tasks by status">
		<button class="client-status-filter client-status-filter-pending" type="button" data-status-filter="pending" aria-pressed="false" <?=$clientPendingCount===0?'disabled':''?>>
			<span>Pending / In progress</span><strong><?=$clientPendingCount?></strong>
		</button>
		<button class="client-status-filter client-status-filter-completed" type="button" data-status-filter="completed" aria-pressed="false" <?=$clientCompletedCount===0?'disabled':''?>>
			<span>Completed</span><strong><?=$clientCompletedCount?></strong>
		</button>
	</div>
	<?php if(!$clientTasks):?><div class="notice info" role="status">You have no tasks yet. Create a token to request a service.</div><?php else:?>
	<div class="table-wrap"><table class="table" id="clientTasksTable">
		<thead><tr><th>Token</th><th>Task</th><th>Section</th><th>Preferred date</th><th>Preferred time</th><th>Status</th><th>Feedback</th></tr></thead>
		<tbody><?php foreach($clientTasks as $clientTask):$taskStatusLabel=$clientTask['status']==='completed'?'Completed':($clientTask['status']==='cancelled'?'Cancelled':($clientTask['status']==='issued'?'In progress':'Pending'));?><tr data-client-task-status="<?=h($clientTask['status'])?>">
			<td><strong><?=h($clientTask['token_no'])?></strong></td>
			<td><?=h($clientTask['service_type'])?></td>
			<td><?=h(ucfirst($clientTask['department']??''))?></td>
			<td><?=h($clientTask['preferred_date'])?></td>
			<td><?=h($clientTask['preferred_time'])?></td>
			<td><span class="badge"><?=h($taskStatusLabel)?></span></td>
			<td><?php if($clientTask['status']==='completed' && !$clientTask['feedback_submitted']):?><a class="table-link" href="feedback.php?token=<?=(int)$clientTask['id']?>">Give feedback</a><?php elseif($clientTask['feedback_submitted']):?><span class="muted">Submitted</span><?php else:?><span class="muted">Available after completion</span><?php endif;?></td>
		</tr><?php endforeach;?></tbody>
	</table></div>
	<?php endif;?>
	<script>
	const statusFilters=document.querySelectorAll('[data-status-filter]');
	const clientTaskRows=document.querySelectorAll('[data-client-task-status]');
	statusFilters.forEach(button=>button.addEventListener('click',()=>{
		const clearFilter=button.getAttribute('aria-pressed')==='true';
		const selectedStatus=clearFilter?'':button.dataset.statusFilter;
		statusFilters.forEach(filter=>filter.setAttribute('aria-pressed',String(filter===button&&!clearFilter)));
		clientTaskRows.forEach(row=>{
			const rowStatus=row.dataset.clientTaskStatus;
			const matches=selectedStatus===''||(selectedStatus==='pending'&&['pending','issued'].includes(rowStatus))||(selectedStatus==='completed'&&rowStatus==='completed');
			row.hidden=!matches;
		});
	}));
	</script>
</section>
<?php endif;?>
<div class="dashboard-grid">
	<?php if($r!=='officer_registrar'):?><a class="dash-card dashboard-card-link" href="<?=$r==='passenger'?'token.php':'admin/tokens.php'?>" aria-label="Open token records"><div class="muted">My Tokens / Token</div><strong><?=$tokenCount?></strong></a><?php endif;?>
	<?php if($department!==null):?><a class="dash-card dashboard-card-link" href="admin/tokens.php?status=completed" aria-label="Open completed list"><div class="muted">Completed List</div><strong><?=$completedCount?></strong></a><a class="dash-card dashboard-card-link" href="admin/tokens.php?status=pending" aria-label="Open pending list"><div class="muted">Pending List</div><strong><?=$pendingCount?></strong></a><?php endif;?>
	<a class="dash-card dashboard-card-link" href="documents.php" aria-label="Open documents"><div class="muted">Documents</div><strong><?=$docCount?></strong></a>
	<?php if(is_staff()):?><a class="dash-card dashboard-card-link" href="admin/passengers.php" aria-label="Open client records"><div class="muted">Clients</div><strong><?=$passCount?></strong></a><?php if($canViewOperationalData):?><a class="dash-card dashboard-card-link" href="admin/data_entry.php?tab=vehicles" aria-label="Open vehicle records"><div class="muted">Vehicles</div><strong><?=$vehicleCount?></strong></a><a class="dash-card dashboard-card-link" href="admin/data_entry.php?tab=stands" aria-label="Open bus stand records"><div class="muted">Bus Stands</div><strong><?=$standCount?></strong></a><?php endif;?><?php if($canViewAdminReports):?><a class="dash-card dashboard-card-link" href="admin/feedback.php" aria-label="Open feedback records"><div class="muted">Feedback</div><strong><?=$feedbackCount?></strong></a><a class="dash-card dashboard-card-link" href="admin/complaints.php" aria-label="Open complaint records"><div class="muted">Complaints</div><strong><?=$complaintCount?></strong></a><?php endif;?><?php endif;?>
</div>
<div class="admin-nav"><?php if($r==='passenger'):?><a class="passenger-token-action" href="token.php">🎫 Get Token / Token ගන්න</a><?php if($tokenCount>0):?><a class="passenger-feedback-action" href="feedback.php">⭐ Feedback / ප්‍රතිචාර</a><?php endif;?><a href="documents.php">📄 Documents</a><a href="officers.php">👥 Officer Directory</a><a class="passenger-complaint-action" href="complaint.php">⚑ Make a Complaint / පැමිණිල්ලක්</a><?php elseif($r==='officer_registrar'):?><a href="officer_register.php?department=control">Control</a><a href="officer_register.php?department=accounts">Account</a><a href="officer_register.php?department=driving">Driving</a><?php elseif($department!==null):?><a href="admin/tokens.php">🎫 <?=h(ucfirst($department))?> Section Tasks</a><a href="admin/passengers.php">👥 Section Clients</a><a href="admin/officers.php">Officer Records</a><a href="documents.php">📄 Documents</a><?php elseif($r==='admin'):?><a href="admin/tokens.php">🎫 Token Records</a><a href="admin/feedback.php">⭐ Feedback</a><a href="admin/complaints.php">⚑ Complaints</a><a href="admin/passengers.php">👥 Clients / Add Client</a><a href="admin/officers.php">Officer Registration / Add Officer</a><a href="admin/data_entry.php?tab=documents">📑 Edit Documents</a><a href="documents.php">📄 Documents</a><?php elseif($r==='data_entry'):?><a href="admin/data_entry.php">📝 Data Entry Management</a><a href="admin/data_entry.php?tab=documents">📑 Edit Documents</a><a href="admin/tokens.php">🎫 Token Records</a><a href="admin/passengers.php">👥 Clients / Add Client</a><a href="admin/officers.php">Officer Registration / Add Officer</a><a href="documents.php">📄 Documents</a><?php elseif($r==='super_admin'):?><a href="admin/tokens.php">🎫 All Section Tasks</a><a href="admin/feedback.php">⭐ Feedback</a><a href="admin/complaints.php">⚑ Complaints</a><a href="admin/passengers.php">👥 All Clients / Add Client</a><a href="admin/officers.php">Officer Registration / Add Officer</a><a href="admin/data_entry.php">📝 Data Entry</a><a href="admin/data_entry.php?tab=documents">📑 Edit Documents</a><a href="admin/faqs.php">💬 FAQ / AI Knowledge</a><a href="documents.php">📄 Documents</a><?php endif;?></div>
<?php if($department!==null):$taskQuery=$conn->prepare('SELECT token_no,full_name,service_type,preferred_date,status FROM tokens WHERE department=? ORDER BY id DESC LIMIT 8');$taskQuery->bind_param('s',$department);$taskQuery->execute();$departmentTasksRows=$taskQuery->get_result();?><div class="card department-dashboard"><h3><?=h(ucfirst($department))?> Section Tasks</h3><div class="table-wrap"><table class="table"><thead><tr><th>Token</th><th>Client</th><th>Task</th><th>Date</th><th>Status</th></tr></thead><tbody><?php while($task=$departmentTasksRows->fetch_assoc()):?><tr><td><?=h($task['token_no'])?></td><td><?=h($task['full_name'])?></td><td><?=h($task['service_type'])?></td><td><?=h($task['preferred_date'])?></td><td><?=h($task['status']==='completed'?'Completed':'Pending')?></td></tr><?php endwhile;?></tbody></table></div><a class="table-link" href="admin/tokens.php">Open section task manager</a></div><?php $customerQuery=$conn->prepare('SELECT u.full_name,p.customer_type,p.nic,p.phone FROM users u JOIN passenger_profiles p ON p.user_id=u.id WHERE p.department=? ORDER BY u.id DESC LIMIT 8');$customerQuery->bind_param('s',$department);$customerQuery->execute();$sectionClients=$customerQuery->get_result();?><div class="card department-dashboard"><h3><?=h(ucfirst($department))?> Section Clients</h3><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Client type</th><th>NIC</th><th>Phone</th></tr></thead><tbody><?php while($customer=$sectionClients->fetch_assoc()):?><tr><td><?=h($customer['full_name'])?></td><td><?=h($customer['customer_type'])?></td><td><?=h($customer['nic'])?></td><td><?=h($customer['phone'])?></td></tr><?php endwhile;?></tbody></table></div><a class="table-link" href="admin/passengers.php">Open section customer records</a></div><?php endif;?>
<div class="card access-card"><h3>Access / ප්‍රවේශය</h3><?php if($r==='officer_registrar'):?><p>Officer registration access for the Control, Account, and Driving sections.</p><?php elseif($r==='super_admin'):?><p>Full system access: token records, feedback, passengers, officer accounts, data entry, FAQ/AI knowledge and documents.</p><?php elseif($r==='admin'):?><p>Administrative access: token records, passenger and officer accounts, feedback and documents.</p><?php elseif($r==='data_entry'):?><p>Data Entry access: enter, edit and delete operational data, passenger and officer records, vehicles, bus stands, services and FAQ knowledge.</p><?php else:?><p>Passenger access: your registration, token service, documents, officer directory and RPTA Assistant.</p><?php endif;?></div></div><?php include 'includes/footer.php'; ?>