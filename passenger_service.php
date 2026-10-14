<?php
require_once __DIR__.'/config.php';
require_login();
if(role()!=='passenger') redirect('dashboard.php');
$uid=(int)($_SESSION['user']['id']??0);
$s=$conn->prepare('SELECT nic,phone FROM passenger_profiles WHERE user_id=? LIMIT 1');
$s->bind_param('i',$uid); $s->execute(); $p=$s->get_result()->fetch_assoc();
if($p && !empty($p['nic']) && !empty($p['phone'])) redirect('token.php');
redirect('register.php');
