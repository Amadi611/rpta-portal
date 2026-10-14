<?php
require_once __DIR__.'/../config.php';
require_login();
if(!in_array(role(),['admin','super_admin'],true)) redirect('dashboard.php');
$title='Passenger Complaints';
$categoryLabels=['bus_service'=>'Bus service','driver_conductor'=>'Driver or conductor','overcharging'=>'Fare or overcharging','delay'=>'Delay or missed service','route'=>'Route or bus stop','safety'=>'Safety','other'=>'Other'];
include '../includes/header.php';
$rows=$conn->query('SELECT c.*,u.username FROM complaints c LEFT JOIN users u ON u.id=c.user_id ORDER BY c.id DESC');
?>
<div class="page">
    <div class="section-title">
        <div><h2>Passenger Complaints / මගී පැමිණිලි</h2><p class="muted">Complaints submitted through the passenger portal.</p></div>
        <a class="secondary-btn" href="../dashboard.php">← Dashboard</a>
    </div>
    <div class="table-wrap"><table class="table">
        <tr><th>Type</th><th>Incident</th><th>Journey</th><th>Details</th><th>Contact</th><th>Passenger</th><th>Status</th><th>Submitted</th></tr>
        <?php while($item=$rows->fetch_assoc()):?>
            <tr>
                <td><?=h($categoryLabels[$item['category']]??$item['category'])?></td>
                <td><?=h($item['service_date'])?><?=!empty($item['service_time'])?' '.h(substr($item['service_time'],0,5)):''?><br><?=h($item['location']??'')?></td>
                <td>Bus: <?=h($item['bus_number']??'-')?><br>Route: <?=h($item['route']??'-')?></td>
                <td class="complaint-description"><?=nl2br(h($item['description']))?></td>
                <td><?=h($item['contact_phone'])?><br><?=h($item['contact_email']??'')?></td>
                <td><?=h($item['username']??'Passenger')?></td>
                <td><span class="badge"><?=h($item['status'])?></span></td>
                <td><?=h($item['created_at'])?></td>
            </tr>
        <?php endwhile;?>
    </table></div>
</div>
<?php include '../includes/footer.php'; ?>