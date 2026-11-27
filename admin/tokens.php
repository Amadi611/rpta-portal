<?php
require_once __DIR__.'/../config.php';
require_login();
if(!in_array(role(),['admin','control_admin','driving_admin','accounts_admin','super_admin','data_entry'],true)) redirect('dashboard.php');
$department=staff_department();
$requestedStatus=$_GET['status']??'';
$statusFilter=in_array($requestedStatus,['pending','completed'],true)?$requestedStatus:'';
$statusLabel=$statusFilter==='completed'?'Completed List':($statusFilter==='pending'?'Pending List':'');
$canUpdateStatus=in_array(role(),['control_admin','driving_admin','accounts_admin'],true);
$error='';
$success=isset($_GET['updated'])&&$_GET['updated']==='1'?'Task status saved.':'';
if($_SERVER['REQUEST_METHOD']==='POST'){
	if(!$canUpdateStatus){http_response_code(403);exit('You are not allowed to update token status.');}
	$postedId=$_POST['token_id']??'';
	$id=is_string($postedId)&&ctype_digit($postedId)?(int)$postedId:0;
	$status=isset($_POST['status'])&&is_string($_POST['status'])?$_POST['status']:'';
	$statusComment=isset($_POST['status_comment'])&&is_string($_POST['status_comment'])?trim($_POST['status_comment']):'';
	$statusCommentLength=preg_match_all('/./us',$statusComment);
	if($statusCommentLength===false||$statusCommentLength>2000){
		$error='Comment must be 2000 characters or fewer.';
	}elseif($id>0 && in_array($status,['pending','completed'],true)){
		if(!$conn->begin_transaction()){
			error_log('Could not start token status update transaction: '.$conn->error);
			$error='Could not save the task status. Please try again.';
		}else{
		try{
			$sql='SELECT user_id,status,status_comment FROM tokens WHERE id=?';
			if($department!==null) $sql.=' AND department=?';
			$sql.=' FOR UPDATE';
			$taskStmt=$conn->prepare($sql);
			if(!$taskStmt) throw new RuntimeException('Could not prepare token status lookup: '.$conn->error);
			if($department!==null){
				if(!$taskStmt->bind_param('is',$id,$department)) throw new RuntimeException('Could not bind token status lookup: '.$taskStmt->error);
			}elseif(!$taskStmt->bind_param('i',$id)){
				throw new RuntimeException('Could not bind token status lookup: '.$taskStmt->error);
			}
			if(!$taskStmt->execute()) throw new RuntimeException('Could not execute token status lookup: '.$taskStmt->error);
			$task=$taskStmt->get_result()->fetch_assoc();
			if(!$task){
				if(!$conn->rollback()) error_log('Could not roll back missing token status update: '.$conn->error);
				$error='Task not found in your section.';
			}else{
				$commentToSave=$statusComment!==''?$statusComment:(string)($task['status_comment']??'');
				$updateSql='UPDATE tokens SET status=?,status_comment=? WHERE id=?';
				if($department!==null) $updateSql.=' AND department=?';
				$updateStmt=$conn->prepare($updateSql);
				if(!$updateStmt) throw new RuntimeException('Could not prepare token status update: '.$conn->error);
				if($department!==null){
					if(!$updateStmt->bind_param('ssis',$status,$commentToSave,$id,$department)) throw new RuntimeException('Could not bind token status update: '.$updateStmt->error);
				}elseif(!$updateStmt->bind_param('ssi',$status,$commentToSave,$id)){
					throw new RuntimeException('Could not bind token status update: '.$updateStmt->error);
				}
				if(!$updateStmt->execute()) throw new RuntimeException('Could not execute token status update: '.$updateStmt->error);
				if($status==='completed' && $task['status']!=='completed' && (int)$task['user_id']>0){
					$clientId=(int)$task['user_id'];
					$notificationMessage='Your requested service task has been completed.';
					$notificationStmt=$conn->prepare("INSERT INTO client_notifications(user_id,token_id,notification_type,message) VALUES(?,?,'task_completed',?) ON DUPLICATE KEY UPDATE created_at=CURRENT_TIMESTAMP,is_read=0,message=VALUES(message)");
					if(!$notificationStmt) throw new RuntimeException('Could not prepare task completion notification: '.$conn->error);
					if(!$notificationStmt->bind_param('iis',$clientId,$id,$notificationMessage)) throw new RuntimeException('Could not bind task completion notification: '.$notificationStmt->error);
					if(!$notificationStmt->execute()) throw new RuntimeException('Could not save task completion notification: '.$notificationStmt->error);
				}
				if(!$conn->commit()) throw new RuntimeException('Could not commit token status update: '.$conn->error);
				if(isset($_POST['return_to']) && $_POST['return_to']==='dashboard'){
					header('Location: '.BASE_URL.'/dashboard.php?task_updated=1');
				}else{
					header('Location: '.BASE_URL.'/admin/tokens.php?status='.rawurlencode($status).'&updated=1');
				}
				exit;
			}
		}catch(Throwable $exception){
			if(!$conn->rollback()) error_log('Could not roll back token status update: '.$conn->error);
			error_log('Token status update failed: '.$exception->getMessage());
			$error='Could not save the task status. Please try again.';
		}
		}
	}else $error='Choose a valid task status.';
}
$conditions=[];
if($department!==null) $conditions[]='department=?';
if($statusFilter==='completed') $conditions[]="status='completed'";
elseif($statusFilter==='pending') $conditions[]="status<>'completed'";
$where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
$stmt=$conn->prepare('SELECT * FROM tokens'.$where.' ORDER BY id DESC');
if($department!==null) $stmt->bind_param('s',$department);
$stmt->execute();
$rows=$stmt->get_result();
$title='Department Tasks';
$pageBodyClass='tokens-page';
$pageStyles=['assets/tokens.css'];
include '../includes/header.php';
?>
<div class="page">
	<div class="section-title"><div><h2><span><?=h($department?department_label($department).' Section Tasks':"Client's Tokens")?></span><?= $statusLabel?' / <span>'.h($statusLabel).'</span>':'' ?></h2><p class="muted">Review client requests assigned to this section and update their progress.</p></div><a class="secondary-btn" href="../dashboard.php">← Dashboard</a></div>
	<?php if($error):?><div class="notice danger"><?=h($error)?></div><?php endif;?>
	<?php if($success):?><div class="notice success" role="status"><?=h($success)?></div><?php endif;?>
	<div class="token-table-toolbar">
		<div><label for="token-table-search">Search tokens</label><input id="token-table-search" type="search" placeholder="Search tokens..." autocomplete="off"></div>
		<div><label for="token-table-page-size">Rows per page</label><select id="token-table-page-size"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></div>
	</div>
	<div class="table-wrap"><table class="table token-records-table"><colgroup>
		<?php if($canUpdateStatus):?>
		<col style="width:10%"><col style="width:17%"><col style="width:13%"><col style="width:12%"><col style="width:12%"><col style="width:10%"><col style="width:16%"><col style="width:10%">
		<?php else:?>
		<col style="width:11%"><col style="width:20%"><col style="width:16%"><col style="width:14%"><col style="width:14%"><col style="width:9%"><col style="width:16%">
		<?php endif;?>
	</colgroup><thead><tr>
		<th aria-sort="none"><button class="token-sort-button" type="button" data-sort-column="0">Token</button></th>
		<th aria-sort="none"><button class="token-sort-button" type="button" data-sort-column="1">Client</button></th>
		<th aria-sort="none"><button class="token-sort-button" type="button" data-sort-column="2">Section and task</button></th>
		<th aria-sort="none"><button class="token-sort-button" type="button" data-sort-column="3">Token issued at</button></th>
		<th aria-sort="none"><button class="token-sort-button" type="button" data-sort-column="4">Appointment</button></th>
		<th aria-sort="none"><button class="token-sort-button" type="button" data-sort-column="5">Status</button></th>
		<th aria-sort="none"><button class="token-sort-button" type="button" data-sort-column="6">Officer comment</button></th>
		<?php if($canUpdateStatus):?><th>Update</th><?php endif;?>
	</tr></thead><tbody>
	<?php while($x=$rows->fetch_assoc()):$issuedAt=$x['created_at']?date('Y-m-d H:i',strtotime($x['created_at'])):'—';$rowId=(int)$x['id'];$statusFormId='status-update-'.$rowId;?>
	<tr>
		<td><strong><?=h($x['token_no'])?></strong><a class="token-pdf-link" href="../token_pdf.php?token=<?=rawurlencode($x['token_no'])?>">PDF</a></td>
		<td><strong data-user-content><?=h($x['full_name'])?></strong><small><span>NIC</span>: <span data-user-content><?=h($x['nic'])?></span></small><small><span>Phone</span>: <span data-user-content><?=h($x['phone'])?></span></small></td>
		<td><strong><?=h(department_label($x['department']??''))?></strong><small><?=h($x['service_type'])?></small></td>
		<td><?=h($issuedAt)?></td>
		<td><strong><?=h($x['preferred_date']??'—')?></strong><small><?=h($x['preferred_time']??'—')?></small></td>
		<td><span class="badge"><?=h($x['status']==='completed'?'Completed':'Pending')?></span></td>
		<td>
			<div class="token-saved-comment" data-user-content><?=trim((string)($x['status_comment']??''))!==''?nl2br(h($x['status_comment'])):'—'?></div>
			<?php if($canUpdateStatus):?><form id="<?=h($statusFormId)?>" method="post" class="token-status-form">
				<input type="hidden" name="token_id" value="<?=$rowId?>">
				<label for="status-comment-<?=$rowId?>">Comment (optional)</label>
				<textarea id="status-comment-<?=$rowId?>" name="status_comment" maxlength="2000" rows="2" placeholder="Add a comment (optional)"></textarea>
			</form><?php endif;?>
		</td>
		<?php if($canUpdateStatus):?><td><div class="token-status-controls">
			<select name="status" form="<?=h($statusFormId)?>" aria-label="Task status"><option value="pending" <?=$x['status']!=='completed'?'selected':''?>>Pending</option><option value="completed" <?=$x['status']==='completed'?'selected':''?>>Completed</option></select>
			<button class="soft-btn" type="submit" form="<?=h($statusFormId)?>">Save</button>
		</div></td><?php endif;?>
	</tr><?php endwhile;?>
	</tbody></table></div>
	<div id="token-table-empty" class="notice info" role="status" hidden>No matching tokens.</div>
	<div class="token-table-pagination" aria-label="Token table pagination">
		<p aria-live="polite"><strong id="token-table-range-start">0</strong>–<strong id="token-table-range-end">0</strong> / <strong id="token-table-total">0</strong> <span>results</span></p>
		<div><button id="token-table-previous" class="secondary-btn" type="button">Previous</button><span><span>Page</span> <strong id="token-table-current-page">1</strong> / <strong id="token-table-total-pages">1</strong></span><button id="token-table-next" class="secondary-btn" type="button">Next</button></div>
	</div>
</div>
<script src="<?=BASE_URL?>/assets/token-table.js?v=<?=filemtime(__DIR__.'/../assets/token-table.js')?>"></script>
<?php include '../includes/footer.php'; ?>