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
$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
	if(!$canUpdateStatus){http_response_code(403);exit('You are not allowed to update token status.');}
	$id=(int)($_POST['token_id']??0);
	$status=$_POST['status']??'';
	if($id>0 && in_array($status,['pending','completed'],true)){
		$conn->begin_transaction();
		try{
			$sql='SELECT user_id,status FROM tokens WHERE id=?';
			if($department!==null) $sql.=' AND department=?';
			$sql.=' FOR UPDATE';
			$taskStmt=$conn->prepare($sql);
			if($department!==null) $taskStmt->bind_param('is',$id,$department); else $taskStmt->bind_param('i',$id);
			$taskStmt->execute();
			$task=$taskStmt->get_result()->fetch_assoc();
			if(!$task){
				$conn->rollback();
				$error='Task not found in your section.';
			}else{
				$updateSql='UPDATE tokens SET status=? WHERE id=?';
				if($department!==null) $updateSql.=' AND department=?';
				$updateStmt=$conn->prepare($updateSql);
				if($department!==null) $updateStmt->bind_param('sis',$status,$id,$department); else $updateStmt->bind_param('si',$status,$id);
				$updateStmt->execute();
				if($status==='completed' && $task['status']!=='completed' && (int)$task['user_id']>0){
					$clientId=(int)$task['user_id'];
					$notificationMessage='Your requested service task has been completed.';
					$notificationStmt=$conn->prepare("INSERT INTO client_notifications(user_id,token_id,notification_type,message) VALUES(?,?,'task_completed',?) ON DUPLICATE KEY UPDATE created_at=CURRENT_TIMESTAMP,is_read=0,message=VALUES(message)");
					if(!$notificationStmt) throw new RuntimeException('Could not prepare task completion notification: '.$conn->error);
					$notificationStmt->bind_param('iis',$clientId,$id,$notificationMessage);
					if(!$notificationStmt->execute()) throw new RuntimeException('Could not save task completion notification: '.$notificationStmt->error);
				}
				$conn->commit();
				$success='Task status saved.';
			}
		}catch(Throwable $exception){
			$conn->rollback();
			error_log('Token status update failed: '.$exception->getMessage());
			$error='Could not save the task status. Please try again.';
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
	<div class="section-title"><div><h2><?=h($department?ucfirst($department).' Section Tasks':"Client's Tokens")?><?= $statusLabel?' / '.h($statusLabel):'' ?> / සේවාලාභියාගේ ටෝකන</h2><p class="muted">Review client requests assigned to this section and update their progress.</p></div><a class="secondary-btn" href="../dashboard.php">← Dashboard</a></div>
	<?php if($error):?><div class="notice danger"><?=h($error)?></div><?php endif;?>
	<?php if($success):?><div class="notice success" role="status"><?=h($success)?></div><?php endif;?>
	<div class="table-wrap"><table class="table"><thead><tr><th>Token</th><th>Client</th><th>NIC</th><th>Phone</th><th>Section</th><th>Task</th><th>Submitted at</th><th>Preferred date</th><th>Status</th><?php if($canUpdateStatus):?><th>Update</th><?php endif;?><th>PDF</th></tr></thead><tbody>
	<?php while($x=$rows->fetch_assoc()):$submittedAt=$x['created_at']?date('Y-m-d H:i',strtotime($x['created_at'])):'—';?><tr><td><?=h($x['token_no'])?></td><td><?=h($x['full_name'])?></td><td><?=h($x['nic'])?></td><td><?=h($x['phone'])?></td><td><?=h(ucfirst($x['department']??''))?></td><td><?=h($x['service_type'])?></td><td><?=h($submittedAt)?></td><td><?=h($x['preferred_date'])?></td><td><span class="badge"><?=h($x['status']==='completed'?'Completed':'Pending')?></span></td><?php if($canUpdateStatus):?><td><form method="post" class="token-status-form"><input type="hidden" name="token_id" value="<?=(int)$x['id']?>"><select name="status" aria-label="Task status"><option value="pending" <?=$x['status']!=='completed'?'selected':''?>>Pending</option><option value="completed" <?=$x['status']==='completed'?'selected':''?>>Completed</option></select><button class="soft-btn" type="submit">Save</button></form></td><?php endif;?><td><a class="table-link" href="../token_pdf.php?token=<?=urlencode($x['token_no'])?>">PDF</a></td></tr><?php endwhile;?>
	</tbody></table></div>
</div>
<?php include '../includes/footer.php'; ?>