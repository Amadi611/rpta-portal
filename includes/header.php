<?php
require_once __DIR__.'/../config.php';
$L=lang();
$title=$title??APP_NAME_EN;
?>
<!doctype html>
<html lang="<?= $L==='si'?'si':'en' ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title><?=h($title)?></title>
	<link rel="stylesheet" href="<?=BASE_URL?>/assets/style.css">
	<link rel="stylesheet" href="<?=BASE_URL?>/assets/home.css">
	<?php foreach($pageStyles??[] as $pageStyle):?><link rel="stylesheet" href="<?=BASE_URL?>/<?=h($pageStyle)?>"><?php endforeach;?>
</head>
<body>
<header class="site-header">
	<div class="topline">
		<a class="brand" href="<?=BASE_URL?>/index.php"><img class="brand-logo" src="<?=h(OFFICIAL_LOGO_URL)?>" onerror="this.onerror=null;this.src='<?=h(OFFICIAL_LOGO_FALLBACK_URL)?>';" alt="Southern Provincial Road Passenger Transport Authority Logo"></a>
		<div class="header-actions"><a class="lang" href="?lang=si">සිංහල</a><span>|</span><a class="lang" href="?lang=en">English</a></div>
	</div>
	<nav class="nav">
		<a href="<?=BASE_URL?>/index.php">Home / මුල් පිටුව</a>
		<?php if(logged_in()):?>
			<a href="<?=BASE_URL?>/dashboard.php">Dashboard / පුවරුව</a>
			<a href="<?=BASE_URL?>/documents.php">Documents / ලේඛන</a>
			<?php if(in_array(role(),['admin','data_entry','super_admin'],true)):?><a href="<?=BASE_URL?>/admin/events.php">Events / Upload Photos</a><?php endif;?>
			<?php if(role()==='passenger'):?><a href="<?=BASE_URL?>/token.php">Get Token / Token ගන්න</a><?php endif;?>
			<a class="nav-logout" href="<?=BASE_URL?>/logout.php">Logout / පිටවීම</a>
		<?php else:?>
			<a class="nav-register" href="<?=BASE_URL?>/register.php">Register / ලියාපදිංචි වන්න</a>
			<a class="nav-login" href="<?=BASE_URL?>/login.php">Login / ඇතුල් වන්න</a>
		<?php endif;?>
	</nav>
</header>
<a class="whatsapp-fab" href="https://wa.me/94763299299" target="_blank" rel="noopener noreferrer" aria-label="Chat with the Authority on WhatsApp" title="Chat with us on WhatsApp"><img src="https://cdn.simpleicons.org/whatsapp/ffffff" alt="" aria-hidden="true"></a>
<main>