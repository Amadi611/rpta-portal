<?php
require_once __DIR__.'/config.php';
require_login();
if(!is_staff()) redirect('dashboard.php');

$tokenNo=trim($_GET['token']??'');
$stmt=$conn->prepare('SELECT t.token_no,t.full_name,COALESCE(NULLIF(t.address,""),p.address) AS address,COALESCE(NULLIF(t.district,""),p.district) AS district,t.service_type,t.preferred_date,t.preferred_time,t.status,t.created_at FROM tokens t LEFT JOIN passenger_profiles p ON p.user_id=t.user_id WHERE t.token_no=? LIMIT 1');
$stmt->bind_param('s',$tokenNo);
$stmt->execute();
$token=$stmt->get_result()->fetch_assoc();
if(!$token){
    http_response_code(404);
    $title='Token Not Found';
    include 'includes/header.php';
    echo '<div class="page"><div class="notice danger">Token not found / Token එක හමු නොවීය.</div><a class="secondary-btn" href="admin/tokens.php">Token Records</a></div>';
    include 'includes/footer.php';
    exit;
}

$title='Scanned Token / පරීක්ෂා කළ Token';
include 'includes/header.php';
?>
<div class="page narrow">
    <div class="section-title">
        <div><span class="badge">PASSENGER TOKEN</span><h2>Scanned Token / පරීක්ෂා කළ Token</h2><p class="muted">Verified token details / තහවුරු කළ Token විස්තර</p></div>
        <a class="secondary-btn" href="admin/tokens.php">← Token Records</a>
    </div>
    <div class="card token-scan-detail">
        <div class="token-number"><?=h($token['token_no'])?></div>
        <div class="split">
            <div><strong>Passenger / මගියා</strong><p><?=h($token['full_name'])?></p></div>
            <div><strong>Service / සේවාව</strong><p><?=h($token['service_type'])?></p></div>
            <div><strong>Address / ලිපිනය</strong><p><?=h($token['address']??'')?></p></div>
            <div><strong>District / දිස්ත්‍රික්කය</strong><p><?=h($token['district']??'')?></p></div>
            <div><strong>Preferred date / දිනය</strong><p><?=h($token['preferred_date'])?></p></div>
            <div><strong>Preferred time / වේලාව</strong><p><?=h($token['preferred_time'])?></p></div>
            <div><strong>Status / තත්ත්වය</strong><p><span class="badge"><?=h($token['status'])?></span></p></div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>