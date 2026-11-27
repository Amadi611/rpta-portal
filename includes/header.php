<?php
require_once __DIR__.'/../config.php';
$L=lang();
$headerCopy=[
	'si'=>[
		'language'=>'භාෂාව',
		'tagline'=>'ඔබව ප්‍රවාහන සේවාවන් වෙත සම්බන්ධ කරයි',
		'brand_alt'=>'දකුණු පළාත් මාර්ගස්ථ මගී ප්‍රවාහන අධිකාරියේ ලාංඡනය',
		'register'=>'ලියාපදිංචි වන්න',
		'login'=>'ඇතුළු වන්න',
		'logout'=>'පිටවන්න',
		'home'=>'මුල් පිටුව',
		'services'=>'අපගේ සේවා',
		'about'=>'අධිකාරිය පිළිබඳව',
		'help'=>'උදවු සහ මඟපෙන්වීම',
		'contact'=>'අප අමතන්න',
		'dashboard'=>'පාලක පුවරුව',
		'documents'=>'ලේඛන',
		'token'=>'ටෝකනයක් ලබාගන්න'
	],
	'en'=>[
		'language'=>'Language',
		'tagline'=>'Connecting you to transport services',
		'brand_alt'=>'Southern Provincial Road Passenger Transport Authority Logo',
		'register'=>'Register',
		'login'=>'Login',
		'logout'=>'Logout',
		'home'=>'Home',
		'services'=>'Our Services',
		'about'=>'About the Authority',
		'help'=>'Help & Guidance',
		'contact'=>'Contact Us',
		'dashboard'=>'Dashboard',
		'documents'=>'Documents',
		'token'=>'Get Token'
	],
	'ta'=>[
		'language'=>'மொழி',
		'tagline'=>'போக்குவரத்து சேவைகளுடன் உங்களை இணைக்கிறோம்',
		'brand_alt'=>'தென் மாகாண வீதிப் பயணிகள் போக்குவரத்து அதிகாரசபையின் சின்னம்',
		'register'=>'பதிவு செய்க',
		'login'=>'உள்நுழைக',
		'logout'=>'வெளியேறு',
		'home'=>'முகப்பு',
		'services'=>'எங்கள் சேவைகள்',
		'about'=>'அதிகாரசபை பற்றி',
		'help'=>'உதவி மற்றும் வழிகாட்டுதல்',
		'contact'=>'எங்களைத் தொடர்புகொள்ள',
		'dashboard'=>'கட்டுப்பாட்டுப் பலகை',
		'documents'=>'ஆவணங்கள்',
		'token'=>'டோக்கன் பெறுக'
	]
];
$copy=$headerCopy[$L];
$languagePath=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if(!is_string($languagePath)||$languagePath==='') $languagePath=BASE_URL.'/index.php';
$languageUrls=[];
foreach(['si','en','ta'] as $languageCode){
	$languageQuery=$_GET;
	$languageQuery['lang']=$languageCode;
	$languageUrls[$languageCode]=$languagePath.'?'.http_build_query($languageQuery);
}
$title=$title??APP_NAME_EN;
$currentPage=basename($_SERVER['SCRIPT_NAME']??'');
$useSoftTheme=!in_array($currentPage,['index.php','login.php'],true);
?>
<!doctype html>
<html lang="<?=h($L)?>">
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
			<span class="header-tagline"><?=h($copy['tagline'])?></span>
			<nav class="header-actions" aria-label="<?=h($copy['language'])?>">
				<a class="lang" lang="si" hreflang="si" href="<?=h($languageUrls['si'])?>"<?=$L==='si'?' aria-current="true"':''?>>සිංහල</a>
				<a class="lang" lang="en" hreflang="en" href="<?=h($languageUrls['en'])?>"<?=$L==='en'?' aria-current="true"':''?>>English</a>
				<a class="lang" lang="ta" hreflang="ta" href="<?=h($languageUrls['ta'])?>"<?=$L==='ta'?' aria-current="true"':''?>>தமிழ்</a>
			</nav>
		</div>
	</div>
	<div class="topline">
		<a class="brand" href="<?=BASE_URL?>/index.php"><img class="brand-logo" src="<?=h(OFFICIAL_LOGO_URL)?>" onerror="this.onerror=null;this.src='<?=h(OFFICIAL_LOGO_FALLBACK_URL)?>';" alt="<?=h($copy['brand_alt'])?>"></a>
		<div class="header-account-actions">
			<?php if(logged_in()):?>
				<a class="header-account-button header-account-logout" href="<?=BASE_URL?>/logout.php"><?=h($copy['logout'])?></a>
			<?php else:?>
				<a class="header-account-button" href="<?=BASE_URL?>/register.php"><?=h($copy['register'])?></a>
				<a class="header-account-button" href="<?=BASE_URL?>/login.php"><?=h($copy['login'])?></a>
			<?php endif;?>
		</div>
	</div>
	<div class="header-nav-row">
		<nav class="nav">
			<a href="<?=BASE_URL?>/index.php"><?=h($copy['home'])?></a>
			<a href="<?=BASE_URL?>/index.php#services"><?=h($copy['services'])?></a>
			<a href="<?=BASE_URL?>/index.php#about"><?=h($copy['about'])?></a>
			<a href="<?=BASE_URL?>/index.php#help"><?=h($copy['help'])?></a>
			<a href="<?=BASE_URL?>/contact.php"><?=h($copy['contact'])?></a>
			<?php if(logged_in()):?>
				<a href="<?=BASE_URL?>/dashboard.php"><?=h($copy['dashboard'])?></a>
				<a href="<?=BASE_URL?>/documents.php"><?=h($copy['documents'])?></a>
				<?php if(role()==='passenger'):?><a href="<?=BASE_URL?>/token.php"><?=h($copy['token'])?></a><?php endif;?>
			<?php endif;?>
		</nav>
	</div>
</header>
<a class="whatsapp-fab" href="https://wa.me/94763299299" target="_blank" rel="noopener noreferrer" aria-label="<?=h($copy['contact'])?>" title="<?=h($copy['contact'])?>"><img src="https://cdn.simpleicons.org/whatsapp/ffffff" alt="" aria-hidden="true"></a>
<main>