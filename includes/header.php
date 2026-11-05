<?php
require_once __DIR__.'/../config.php';
$L=lang();
$title=$title??APP_NAME_EN;
$currentPage=basename($_SERVER['SCRIPT_NAME']??'');
$useSoftTheme=!in_array($currentPage,['index.php','login.php'],true);
?>
<!doctype html>
<html lang="<?= $L==='si'?'si':'en' ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title><?=h($title)?></title>
	<link rel="stylesheet" href="<?=BASE_URL?>/assets/style.css?v=<?=filemtime(__DIR__.'/../assets/style.css')?>">
	<link rel="stylesheet" href="<?=BASE_URL?>/assets/home.css?v=<?=filemtime(__DIR__.'/../assets/home.css')?>">
	<link rel="stylesheet" href="<?=BASE_URL?>/assets/header.css?v=<?=filemtime(__DIR__.'/../assets/header.css')?>">
	<link rel="stylesheet" href="<?=BASE_URL?>/assets/password-toggle.css?v=<?=filemtime(__DIR__.'/../assets/password-toggle.css')?>">
	<?php foreach($pageStyles??[] as $pageStyle):$pageStylePath=__DIR__.'/../'.$pageStyle;$pageStyleVersion=is_file($pageStylePath)?filemtime($pageStylePath):'1';?><link rel="stylesheet" href="<?=BASE_URL?>/<?=h($pageStyle)?>?v=<?=$pageStyleVersion?>"><?php endforeach;?>
	<link rel="stylesheet" href="<?=BASE_URL?>/assets/readability.css?v=<?=filemtime(__DIR__.'/../assets/readability.css')?>">
	<?php if($useSoftTheme):?><link rel="stylesheet" href="<?=BASE_URL?>/assets/site-theme.css?v=<?=filemtime(__DIR__.'/../assets/site-theme.css')?>"><?php endif;?>
</head>
<body class="<?=!empty($hideHeaderNavigation)?'officer-registration-dashboard-page':''?> <?=!empty($pageBodyClass)?h($pageBodyClass):''?> <?=$useSoftTheme?'site-soft-theme':''?>">
<header class="site-header">
	<div class="language-strip">
		<div class="language-strip-inner">
			<span class="header-tagline">Connecting you to transport services</span>
			<div class="header-actions"><a class="lang" href="?lang=si">සිංහල</a><span aria-hidden="true">|</span><a class="lang" href="?lang=en">English</a></div>
		</div>
	</div>
	<div class="topline">
		<a class="brand" href="<?=BASE_URL?>/index.php"><img class="brand-logo" src="<?=h(OFFICIAL_LOGO_URL)?>" onerror="this.onerror=null;this.src='<?=h(OFFICIAL_LOGO_FALLBACK_URL)?>';" alt="Southern Provincial Road Passenger Transport Authority Logo"></a>
		<div class="header-account-actions">
			<?php if(logged_in()):?>
				<a class="header-account-button header-account-logout" href="<?=BASE_URL?>/logout.php">Logout / පිටවීම</a>
			<?php else:?>
				<a class="header-account-button" href="<?=BASE_URL?>/register.php">Register / ලියාපදිංචි වන්න</a>
				<a class="header-account-button" href="<?=BASE_URL?>/login.php">Login / ඇතුල් වන්න</a>
			<?php endif;?>
		</div>
	</div>
	<div class="header-nav-row">
		<nav class="nav">
			<a href="<?=BASE_URL?>/index.php">Home / මුල් පිටුව</a>
			<a href="<?=BASE_URL?>/index.php#services">Our Services / අපගේ සේවා</a>
			<a href="<?=BASE_URL?>/index.php#about">About the Authority / අධිකාරිය පිළිබඳ</a>
			<a href="<?=BASE_URL?>/index.php#help">Help &amp; Guidance / උපදෙස් සහ මඟපෙන්වීම</a>
			<a href="<?=BASE_URL?>/contact.php">Contact Us / සම්බන්ධ වන්න</a>
			<?php if(logged_in()):?>
				<a href="<?=BASE_URL?>/dashboard.php">Dashboard / පුවරුව</a>
				<a href="<?=BASE_URL?>/documents.php">Documents / ලේඛන</a>
				<?php if(role()==='passenger'):?><a href="<?=BASE_URL?>/token.php">Get Token / Token ගන්න</a><?php endif;?>
			<?php endif;?>
		</nav>
	</div>
</header>
<a class="whatsapp-fab" href="https://wa.me/94763299299" target="_blank" rel="noopener noreferrer" aria-label="Chat with the Authority on WhatsApp" title="Chat with us on WhatsApp"><img src="https://cdn.simpleicons.org/whatsapp/ffffff" alt="" aria-hidden="true"></a>
<main>