<?php
require_once __DIR__.'/../config.php';
require_login();
if(!in_array(role(),['admin','control_admin','driving_admin','accounts_admin','super_admin','data_entry'],true)) redirect('../dashboard.php');
$department=staff_department();
$sql='SELECT u.full_name,u.username,u.role,u.created_at,o.nic,o.phone,o.email,o.position,o.department FROM users u JOIN officer_profiles o ON o.user_id=u.id';
if($department!==null) $sql.=' WHERE o.department=?';
$sql.=' ORDER BY o.department,u.full_name';
$stmt=$conn->prepare($sql);
if($department!==null) $stmt->bind_param('s',$department);
$stmt->execute();
$rows=$stmt->get_result();
$sectionNames=['control'=>'Control','accounts'=>'Account','driving'=>'Driving'];
$title='Officer Records';
include '../includes/header.php';
?>
<div class="page">
    <div class="section-title"><div><h2><?=h($department?$sectionNames[$department].' Section Officers':'Officer Records')?> / Officer Registration</h2><p class="muted">Officer identities and account access for each service section.</p></div><a class="secondary-btn" href="../dashboard.php">← Dashboard</a></div>
    <?php if(isset($_GET['created'])):?><div class="notice success" role="status">Officer account created successfully.</div><?php endif;?>
    <?php if(can_manage_accounts()):?><div class="admin-nav"><a href="../officer_register.php?department=control">+ Control Officer</a><a href="../officer_register.php?department=accounts">+ Account Officer</a><a href="../officer_register.php?department=driving">+ Driving Officer</a></div><?php endif;?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Username</th><th>Position</th><th>Section</th><th>NIC</th><th>Phone</th><th>Email</th><th>Registered</th></tr></thead><tbody>
        <?php while($officer=$rows->fetch_assoc()):?><tr><td><?=h($officer['full_name'])?></td><td><?=h($officer['username'])?></td><td><?=h($officer['position'])?></td><td><?=h($sectionNames[$officer['department']]??$officer['department'])?></td><td><?=h($officer['nic'])?></td><td><?=h($officer['phone'])?></td><td><?=h($officer['email'])?></td><td><?=h($officer['created_at'])?></td></tr><?php endwhile;?>
    </tbody></table></div>
</div>
<?php include '../includes/footer.php'; ?>
