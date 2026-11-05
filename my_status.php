<?php
require_once __DIR__.'/config.php';
require_login();
if(role()!=='passenger') redirect('dashboard.php');

$userId=(int)$_SESSION['user']['id'];
$stmt=$conn->prepare('SELECT t.id,t.token_no,t.service_type,t.department,t.preferred_date,t.preferred_time,t.status,EXISTS(SELECT 1 FROM feedback f WHERE f.token_id=t.id AND f.user_id=t.user_id) AS feedback_submitted FROM tokens t WHERE t.user_id=? ORDER BY t.id DESC');
$stmt->bind_param('i',$userId);
$stmt->execute();
$tasks=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pendingCount=0;
$completedCount=0;
foreach($tasks as $task){
    if(in_array($task['status'],['pending','issued'],true)) $pendingCount++;
    if($task['status']==='completed') $completedCount++;
}

$title='My Status / මගේ තත්ත්වය';
include __DIR__.'/includes/header.php';
?>
<div class="page">
    <div class="section-title">
        <div><span class="badge">CLIENT TASKS</span><h2>My Status / මගේ තත්ත්වය</h2><p class="muted">Track your pending and completed tasks.</p></div>
        <a class="secondary-btn" href="dashboard.php">← Dashboard</a>
    </div>
    <div class="dashboard-grid">
        <div class="dash-card"><div class="muted">Pending / In progress</div><strong><?=$pendingCount?></strong></div>
        <div class="dash-card"><div class="muted">Completed</div><strong><?=$completedCount?></strong></div>
    </div>
    <?php if(!$tasks):?>
        <div class="notice info" role="status">You have no tasks yet. Create a token to request a service.</div>
    <?php else:?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Token</th><th>Task</th><th>Section</th><th>Preferred date</th><th>Preferred time</th><th>Status</th><th>Feedback</th></tr></thead>
            <tbody><?php foreach($tasks as $task):
                $statusLabel=$task['status']==='completed'?'Completed':($task['status']==='cancelled'?'Cancelled':($task['status']==='issued'?'In progress':'Pending'));
            ?><tr>
                <td><strong><?=h($task['token_no'])?></strong></td>
                <td><?=h($task['service_type'])?></td>
                <td><?=h(ucfirst($task['department']??''))?></td>
                <td><?=h($task['preferred_date'])?></td>
                <td><?=h($task['preferred_time'])?></td>
                <td><span class="badge"><?=h($statusLabel)?></span></td>
                <td><?php if($task['status']==='completed' && !$task['feedback_submitted']):?><a class="table-link" href="feedback.php?token=<?=(int)$task['id']?>">Give feedback</a><?php elseif($task['feedback_submitted']):?><span class="muted">Submitted</span><?php else:?><span class="muted">Available after completion</span><?php endif;?></td>
            </tr><?php endforeach;?></tbody>
        </table></div>
    <?php endif;?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
