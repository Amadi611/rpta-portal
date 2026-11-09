<?php
require_once __DIR__.'/config.php';
require_login();
$name=$_SESSION['user']['full_name'];
$r=role();
$clientRegistrationNotice='';
if($r==='passenger' && isset($_SESSION['client_registration_success'])){
	$clientRegistrationNotice=(string)$_SESSION['client_registration_success'];
	unset($_SESSION['client_registration_success']);
}
$showStaffNavigation=is_staff() || $r==='officer_registrar';
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
$notificationError='';
$clientTasks=[];
$clientPendingCount=0;
$clientCompletedCount=0;
if($r==='passenger'){
	$notificationStmt=$conn->prepare("SELECT n.token_id,n.created_at,t.token_no,t.service_type FROM client_notifications n JOIN tokens t ON t.id=n.token_id WHERE n.user_id=? AND n.notification_type='task_completed' AND t.status='completed' AND NOT EXISTS(SELECT 1 FROM feedback f WHERE f.token_id=t.id AND f.user_id=n.user_id) ORDER BY n.created_at DESC LIMIT 5");
	if(!$notificationStmt){
		error_log('Could not prepare dashboard completion notification query: '.$conn->error);
		$notificationError='Could not load task completion notifications. Please contact support. / කාර්යය සම්පූර්ණ කිරීමේ දැනුම්දීම් ලබාගත නොහැක. සහාය අමතන්න.';
	}else{
		$notificationStmt->bind_param('i',$uid);
		$notificationStmt->execute();
		$completionNotifications=$notificationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
	}
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
$hideHeaderNavigation=$r==='officer_registrar';
$pageStyles=['assets/completion.css','assets/client-status.css','assets/access-card.css','assets/dashboard.css'];
if($r==='passenger') $pageStyles[]='assets/client-theme.css';
if($showStaffNavigation) $pageStyles[]='assets/admin-dashboard.css';
include 'includes/header.php';
?>
<?php if($r==='passenger' && $completionNotifications):$popupNotification=$completionNotifications[0];?>
<dialog class="completion-popup" id="completionPopup" aria-labelledby="completionPopupTitle" aria-describedby="completionPopupDescription">
	<div class="completion-popup-header"><span class="badge">TASK COMPLETED / කාර්යය සම්පූර්ණයි</span><button class="completion-popup-close" type="button" data-close-completion aria-label="Close completion message / සම්පූර්ණ කිරීමේ පණිවිඩය වසන්න">×</button></div>
	<h2 id="completionPopupTitle">Your task is complete / ඔබගේ කාර්යය සම්පූර්ණයි</h2>
	<p id="completionPopupDescription"><strong><?=h($popupNotification['service_type'])?></strong> (<?=h($popupNotification['token_no'])?>) has been completed. / සම්පූර්ණ කර ඇත.</p>
	<p class="muted">Please share your feedback about this service. / මෙම සේවාව පිළිබඳ ඔබගේ අදහස් ලබා දෙන්න.</p>
	<div class="completion-popup-actions"><a class="primary-btn" href="feedback.php?token=<?=(int)$popupNotification['token_id']?>">Give feedback now / දැන් ප්‍රතිචාර ලබා දෙන්න</a><button class="secondary-btn" type="button" data-close-completion>Later / පසුව</button></div>
</dialog>
<script>
const completionPopup=document.getElementById('completionPopup');
completionPopup?.querySelectorAll('[data-close-completion]').forEach(button=>button.addEventListener('click',()=>completionPopup.close()));
completionPopup?.addEventListener('click',event=>{if(event.target===completionPopup)completionPopup.close()});
if(completionPopup?.showModal)completionPopup.showModal();
</script>
<?php endif;?>
<div class="page dashboard-page<?=$r==='passenger'?' dashboard-page--client':($r==='officer_registrar'?' dashboard-page--officer-registration':($department!==null?' dashboard-page--'.h($department):''))?>"><div class="section-title"><div><span class="badge"><?=$r==='passenger'?'CLIENT / සේවාලාභියා':h(strtoupper($r))?></span><h2><?=h($title)?></h2><p class="muted">Welcome, <?=h($name)?>. / සාදරයෙන් පිළිගනිමු, <?=h($name)?>.</p></div><a class="danger-btn" href="logout.php">Logout / පිටවීම</a></div>
<?php if(isset($_GET['officer_created'])):?><div class="notice success" role="status">Officer account created successfully.</div><?php endif;?>
<?php if($notificationError!==''):?><div class="notice danger" role="alert"><?=h($notificationError)?></div><?php endif;?>
<?php foreach($completionNotifications as $notification):?><div class="notice success" role="status"><strong>Task completed / කාර්යය සම්පූර්ණයි:</strong> <?=h($notification['service_type'])?> (<?=h($notification['token_no'])?>). <a class="table-link" href="feedback.php?token=<?=(int)$notification['token_id']?>">Give feedback now / දැන් ප්‍රතිචාර ලබා දෙන්න</a></div><?php endforeach;?>
<section class="dashboard-welcome" aria-label="Dashboard overview">
	<div class="dashboard-welcome-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5v8a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19.5z"/><path d="M9 21v-7h6v7"/></svg></div>
	<div><span class="dashboard-welcome-label"><?=$r==='passenger'?'YOUR RPTA WORKSPACE / ඔබගේ RPTA සේවා පුවරුව':'YOUR RPTA WORKSPACE'?></span><h3><?= $r==='passenger'?'Your transport services, all in one place / ඔබගේ සියලු ප්‍රවාහන සේවා එකම ස්ථානයක':($r==='officer_registrar'?'Officer account registration':($department!==null?ucfirst($department).' section overview':'Authority management overview'))?></h3><p><?php if($r==='passenger'):?>Track service requests, view updates, and share feedback from your dashboard. / ඔබගේ සේවා ඉල්ලීම් සහ යාවත්කාලීන තොරතුරු බලමින් ප්‍රතිචාර ලබා දෙන්න.<?php elseif($r==='officer_registrar'):?>Choose a service section below to create an officer account.<?php elseif($department!==null):?>Review section requests, keep client records up to date, and follow task progress.<?php elseif($r==='data_entry'):?>Manage authority records and keep transport service information current.<?php else:?>Use your dashboard shortcuts to oversee service requests, accounts, and authority operations.<?php endif;?></p></div>
</section>
<?php if($clientRegistrationNotice!==''):?><div class="notice success" role="status"><?=h($clientRegistrationNotice)?></div><?php endif;?>
<?php if($showStaffNavigation):?>
<div class="admin-dashboard-layout<?=$r==='officer_registrar'?' officer-registrar-dashboard':''?>">
	<aside class="admin-dashboard-sidebar" aria-label="Staff dashboard navigation">
		<?php if($r==='officer_registrar'):?>
		<div class="officer-navigation-heading"><p>Choose a service section below to create an officer account.</p><span class="officer-section-count">3 service sections</span></div>
		<?php else:?>
		<div class="admin-sidebar-header">
			<a class="admin-sidebar-brand" href="index.php"><span aria-hidden="true">SP</span> RPTA</a>
			<span class="admin-sidebar-menu-label">Menu <span aria-hidden="true">⌄</span></span>
		</div>
		<?php endif;?>
		<nav>
			<?php if($r==='officer_registrar'):?>
				<a class="officer-registration-card officer-registration-card--control" href="officer_register.php?department=control" aria-label="Register a Control section officer">
					<svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5 20v-1a7 7 0 0 1 14 0v1"/></svg>
					<span class="officer-card-label">SERVICE SECTION 01</span>
					<strong>Control Officer</strong>
					<small>Create an account for the Control section.</small>
					<span class="officer-card-action">Register officer <b aria-hidden="true">→</b></span>
				</a>
				<a class="officer-registration-card officer-registration-card--accounts" href="officer_register.php?department=accounts" aria-label="Register an Accounts section officer">
					<svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="7" width="16" height="13" rx="2"/><path d="M9 7V5h6v2M4 11h16m-9 4h2"/></svg>
					<span class="officer-card-label">SERVICE SECTION 02</span>
					<strong>Accounts Officer</strong>
					<small>Create an account for the Accounts section.</small>
					<span class="officer-card-action">Register officer <b aria-hidden="true">→</b></span>
				</a>
				<a class="officer-registration-card officer-registration-card--driving" href="officer_register.php?department=driving" aria-label="Register a Driving section officer">
					<svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 11 1.5-5h11L19 11m1 0H4a2 2 0 0 0-2 2v4h20v-4a2 2 0 0 0-2-2Z"/><path d="M6 17v2m12-2v2M7 14h.01M17 14h.01"/></svg>
					<span class="officer-card-label">SERVICE SECTION 03</span>
					<strong>Driving Officer</strong>
					<small>Create an account for the Driving section.</small>
					<span class="officer-card-action">Register officer <b aria-hidden="true">→</b></span>
				</a>
			<?php else:?>
			<a href="admin/tokens.php"><svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H20v14H6.5A2.5 2.5 0 0 1 4 16.5z"/><path d="M4 8h16M8 5v3m0 11v-3m4-6h4"/></svg><span>Token Record</span></a>
			<?php if(in_array($r,['admin','super_admin'],true)):?>
				<a href="admin/feedback.php"><svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-4 2 1.5-4A7.5 7.5 0 1 1 20 11.5Z"/><path d="m12.5 7 .9 2.1 2.3.2-1.8 1.4.6 2.2-2-1.2-2 1.2.6-2.2-1.8-1.4 2.3-.2z"/></svg><span>Feedback</span></a>
				<a href="admin/complaints.php"><svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg><span>Complaint</span></a>
			<?php endif;?>
			<a href="admin/passengers.php"><svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1zm13-9a3 3 0 1 0 0-6m1 9a5 5 0 0 1 4 5"/></svg><span>Client</span></a>
			<a href="admin/officers.php"><svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5 20v-1a7 7 0 0 1 14 0v1zm4-12V5l3-2 3 2v3"/></svg><span>Officer Registration</span></a>
			<?php if(in_array($r,['admin','data_entry','super_admin'],true)):?>
				<?php if(in_array($r,['data_entry','super_admin'],true)):?><a href="admin/data_entry.php"><svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19.5V21h1.5L19 7.5 16.5 5 4 17.5z"/><path d="m14.5 7 2.5 2.5"/></svg><span>Data Entry</span></a><?php endif;?>
				<a href="admin/data_entry.php?tab=documents"><svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"/><path d="M13 3v5h5m-5-5 5 5m-2 8 3-3 2 2-3 3-3 1z"/></svg><span>Edit Document</span></a>
			<?php endif;?>
			<a href="documents.php"><svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l5 5v13H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v6h5M8 13h8m-8 4h8"/></svg><span>Document</span></a>
			<?php if($r==='super_admin'):?><a href="admin/faqs.php"><svg class="admin-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.6 2.6 0 1 1 4.5 1.8c-1.2 1.1-2 1.3-2 3m0 3h.01"/></svg><span>FAQ / AI Knowledge</span></a><?php endif;?>
			<?php endif;?>
		</nav>
	</aside>
	<div class="admin-dashboard-main">
<?php endif;?>
<?php if($r==='passenger'):?>
<section class="client-dashboard-tools" aria-label="Client services / සේවාලාභී සේවා">
	<div class="client-dashboard-primary">
		<nav class="client-dashboard-actions" aria-label="Client shortcuts / සේවාලාභී කෙටිමං">
			<a class="client-dashboard-action client-dashboard-action-token" href="token.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8m-8 4h8m-8 4h4"/></svg><span>Get Token / ටෝකනයක් ලබාගන්න</span></a>
			<a class="client-dashboard-action client-dashboard-action-documents" href="documents.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l5 5v13H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v6h5M8 13h8m-8 4h8"/></svg><span>Documents / ලිපි ලේඛන</span><small>ඔබට අවශ්‍ය ලිපි ලේඛන</small></a>
			<a class="client-dashboard-action client-dashboard-action-officers" href="officers.php"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1m1-9a3 3 0 1 0 0-6m1 9a5 5 0 0 1 4 5"/></svg><span>Officer Directory / නිලධාරී නාමාවලිය</span></a>
			<a class="client-dashboard-action client-dashboard-action-complaint" href="complaint.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg><span>Make a Complaint / පැමිණිල්ලක් ඉදිරිපත් කරන්න</span></a>
		</nav>
	</div>
</section>
<?php endif;?>
<?php if($r==='passenger'):?>
<section class="card client-status-card" aria-labelledby="client-status-title">
	<h3 id="client-status-title">My Tasks / My Status / මගේ කාර්යයන් / මගේ තත්ත්වය</h3>
	<div class="client-status-counts" aria-label="Filter tasks by status / තත්ත්වය අනුව කාර්යයන් වෙන් කරන්න">
		<button class="client-status-filter client-status-filter-pending" type="button" data-status-filter="pending" aria-pressed="false" <?=$clientPendingCount===0?'disabled':''?>>
			<span>Pending / In progress / පොරොත්තුවෙන් / ක්‍රියාත්මකයි</span><strong><?=$clientPendingCount?></strong>
		</button>
		<button class="client-status-filter client-status-filter-completed" type="button" data-status-filter="completed" aria-pressed="false" <?=$clientCompletedCount===0?'disabled':''?>>
			<span>Completed / සම්පූර්ණයි</span><strong><?=$clientCompletedCount?></strong>
		</button>
	</div>
	<?php if(!$clientTasks):?><div class="notice info" role="status">You have no tasks yet. Create a token to request a service. / ඔබට තවම කාර්යයන් නොමැත. සේවාවක් ඉල්ලා සිටීමට ටෝකනයක් සාදන්න.</div><?php else:?>
	<div class="table-wrap"><table class="table" id="clientTasksTable">
		<thead><tr><th>Token / ටෝකනය</th><th>Task / කාර්යය</th><th>Section / අංශය</th><th>Preferred date / කැමති දිනය</th><th>Preferred time / කැමති වේලාව</th><th>Status / තත්ත්වය</th><th>Feedback / ප්‍රතිචාර</th></tr></thead>
		<tbody><?php foreach($clientTasks as $clientTask):$taskStatusLabel=$clientTask['status']==='completed'?'Completed / සම්පූර්ණයි':($clientTask['status']==='cancelled'?'Cancelled / අවලංගුයි':($clientTask['status']==='issued'?'In progress / ක්‍රියාත්මකයි':'Pending / පොරොත්තුවෙන්'));?><tr data-client-task-status="<?=h($clientTask['status'])?>">
			<td><strong><?=h($clientTask['token_no'])?></strong></td>
			<td><?=h($clientTask['service_type'])?></td>
			<td><?=h(ucfirst($clientTask['department']??''))?></td>
			<td><?=h($clientTask['preferred_date'])?></td>
			<td><?=h($clientTask['preferred_time'])?></td>
			<td><span class="badge"><?=h($taskStatusLabel)?></span></td>
			<td><?php if($clientTask['status']==='completed' && !$clientTask['feedback_submitted']):?><a class="table-link" href="feedback.php?token=<?=(int)$clientTask['id']?>">Give feedback / ප්‍රතිචාර ලබා දෙන්න</a><?php elseif($clientTask['feedback_submitted']):?><span class="muted">Submitted / ඉදිරිපත් කර ඇත</span><?php else:?><span class="muted">Available after completion / සම්පූර්ණ වූ පසු ලබාගත හැක</span><?php endif;?></td>
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
<?php if(!in_array($r,['officer_registrar','passenger'],true)):?><div class="dashboard-grid">
	<?php if($r!=='officer_registrar'):?><a class="dash-card dashboard-card-link dash-card-tokens" href="<?=$r==='passenger'?'token.php':'admin/tokens.php'?>" aria-label="Open token records"><div class="muted">Client's Token</div><strong><?=$tokenCount?></strong></a><?php endif;?>
	<?php if($department!==null):?><a class="dash-card dashboard-card-link dash-card-completed" href="admin/tokens.php?status=completed" aria-label="Open completed list"><div class="muted">Completed List</div><strong><?=$completedCount?></strong></a><a class="dash-card dashboard-card-link dash-card-pending" href="admin/tokens.php?status=pending" aria-label="Open pending list"><div class="muted">Pending List</div><strong><?=$pendingCount?></strong></a><?php endif;?>
	<?php if($r!=='officer_registrar'):?><a class="dash-card dashboard-card-link dash-card-documents" href="documents.php" aria-label="Open documents"><div class="muted">Documents</div><strong><?=$docCount?></strong></a><?php endif;?>
	<?php if(is_staff()):?><a class="dash-card dashboard-card-link dash-card-clients" href="admin/passengers.php" aria-label="Open client records"><div class="muted">Clients</div><strong><?=$passCount?></strong></a><?php if($canViewOperationalData):?><a class="dash-card dashboard-card-link dash-card-vehicles" href="admin/data_entry.php?tab=vehicles" aria-label="Open vehicle records"><div class="muted">Vehicles</div><strong><?=$vehicleCount?></strong></a><a class="dash-card dashboard-card-link dash-card-stands" href="admin/data_entry.php?tab=stands" aria-label="Open bus stand records"><div class="muted">Bus Stands</div><strong><?=$standCount?></strong></a><?php endif;?><?php if($canViewAdminReports):?><a class="dash-card dashboard-card-link dash-card-feedback" href="admin/feedback.php" aria-label="Open feedback records"><div class="muted">Feedback</div><strong><?=$feedbackCount?></strong></a><a class="dash-card dashboard-card-link dash-card-complaints" href="admin/complaints.php" aria-label="Open complaint records"><div class="muted">Complaints</div><strong><?=$complaintCount?></strong></a><?php endif;?><?php endif;?>
</div><?php endif;?>
<?php if($department!==null):$taskQuery=$conn->prepare('SELECT token_no,full_name,service_type,preferred_date,status FROM tokens WHERE department=? ORDER BY id DESC LIMIT 8');$taskQuery->bind_param('s',$department);$taskQuery->execute();$departmentTasksRows=$taskQuery->get_result();?><div class="card department-dashboard department-task-card"><h3><?=h(ucfirst($department))?> Section Tasks</h3><div class="table-wrap"><table class="table"><thead><tr><th>Token</th><th>Client</th><th>Task</th><th>Date</th><th>Status</th></tr></thead><tbody><?php while($task=$departmentTasksRows->fetch_assoc()):?><tr><td><?=h($task['token_no'])?></td><td><?=h($task['full_name'])?></td><td><?=h($task['service_type'])?></td><td><?=h($task['preferred_date'])?></td><td><?=h($task['status']==='completed'?'Completed':'Pending')?></td></tr><?php endwhile;?></tbody></table></div><a class="table-link" href="admin/tokens.php">Open section task manager</a></div><?php $customerQuery=$conn->prepare('SELECT u.full_name,p.customer_type,p.nic,p.phone FROM users u JOIN passenger_profiles p ON p.user_id=u.id WHERE p.department=? ORDER BY u.id DESC LIMIT 8');$customerQuery->bind_param('s',$department);$customerQuery->execute();$sectionClients=$customerQuery->get_result();?><div class="card department-dashboard department-client-card"><h3><?=h(ucfirst($department))?> Section Clients</h3><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Client type</th><th>NIC</th><th>Phone</th></tr></thead><tbody><?php while($customer=$sectionClients->fetch_assoc()):?><tr><td><?=h($customer['full_name'])?></td><td><?=h($customer['customer_type'])?></td><td><?=h($customer['nic'])?></td><td><?=h($customer['phone'])?></td></tr><?php endwhile;?></tbody></table></div><a class="table-link" href="admin/passengers.php">Open section customer records</a></div><?php endif;?>
<section class="access-card" aria-labelledby="access-card-title">
	<div class="access-card-icon" aria-hidden="true">
		<svg viewBox="0 0 24 24"><path d="M12 3 4.5 6v5.2c0 4.6 3.2 7.8 7.5 9.8 4.3-2 7.5-5.2 7.5-9.8V6L12 3Z"/><path d="m9 12 2 2 4-4"/></svg>
	</div>
	<div class="access-card-content">
		<div class="access-card-heading">
				<div><span class="access-card-eyebrow"><?=$r==='passenger'?'YOUR WORKSPACE / ඔබගේ සේවා පුවරුව':'YOUR WORKSPACE'?></span><h3 id="access-card-title">Access <span>/ ප්‍රවේශය</span></h3></div>
				<span class="access-card-role"><?=$r==='passenger'?'CLIENT / සේවාලාභියා':h(strtoupper(str_replace('_',' ',$r)))?></span>
		</div>
		<p><?php if($r==='officer_registrar'):?>Officer registration access for the Control, Account, and Driving sections.<?php elseif($r==='super_admin'):?>Full system access: token records, feedback, passengers, officer accounts, data entry, FAQ/AI knowledge and documents.<?php elseif($r==='admin'):?>Administrative access: token records, passenger and officer accounts, feedback and documents.<?php elseif($r==='data_entry'):?>Data Entry access: enter, edit and delete operational data, passenger and officer records, vehicles, bus stands, services and FAQ knowledge.<?php elseif($department!==null):?><?=h(ucfirst($department))?> section access: review assigned token requests, manage client and officer records, and view documents.<?php else:?>Client access: registration, token requests, documents, officer directory and RPTA support. / සේවාලාභී ප්‍රවේශය: ලියාපදිංචිය, ටෝකන් ඉල්ලීම්, ලිපි ලේඛන, නිලධාරී නාමාවලිය සහ RPTA සහාය.<?php endif;?></p>
	</div>
</section><?php if($showStaffNavigation):?></div></div><?php endif;?></div><?php include 'includes/footer.php'; ?>