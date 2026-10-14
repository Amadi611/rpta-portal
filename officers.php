<?php
require_once __DIR__.'/config.php';
$sectionNames=['control'=>'Control','accounts'=>'Account','driving'=>'Driving'];
$rows=$conn->query("SELECT u.full_name,o.position,o.department,o.phone,o.email FROM users u JOIN officer_profiles o ON o.user_id=u.id ORDER BY o.department,u.full_name");
$title='Officer Directory';
include __DIR__.'/includes/header.php';
?>
<div class="page">
    <div class="section-title"><div><h2>Officer Directory</h2><p class="muted">Contact officers by service section.</p></div><?php if(logged_in()):?><a class="secondary-btn" href="dashboard.php">← Dashboard</a><?php else:?><a class="secondary-btn" href="login.php">Login</a><?php endif;?></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Position</th><th>Section</th><th>Phone</th><th>Email</th></tr></thead><tbody>
        <?php while($officer=$rows->fetch_assoc()):?><tr><td><?=h($officer['full_name'])?></td><td><?=h($officer['position'])?></td><td><?=h($sectionNames[$officer['department']]??$officer['department'])?></td><td><?=h($officer['phone'])?></td><td><?=h($officer['email'])?></td></tr><?php endwhile;?>
    </tbody></table></div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
