<?php
require_once __DIR__.'/config.php';
require_login();

$tokenNo=isset($_GET['token'])&&is_string($_GET['token'])?trim($_GET['token']):'';
if($tokenNo===''){
	http_response_code(400);
	exit('A token number is required.');
}

$stmt=$conn->prepare('SELECT user_id FROM tokens WHERE token_no=? LIMIT 1');
if(!$stmt){
	error_log('Could not prepare token PDF viewer lookup: '.$conn->error);
	http_response_code(500);
	exit('Token lookup failed');
}
if(!$stmt->bind_param('s',$tokenNo)||!$stmt->execute()){
	error_log('Could not look up token PDF viewer: '.$stmt->error);
	http_response_code(500);
	exit('Token lookup failed');
}
$token=$stmt->get_result()->fetch_assoc();
if(!$token){
	http_response_code(404);
	exit('Token not found');
}
if(role()==='passenger'&&(int)$token['user_id']!==(int)($_SESSION['user']['id']??0)){
	http_response_code(403);
	exit('You are not allowed to view this token.');
}

$copy=[
	'si'=>[
		'title'=>'ටෝකන PDF',
		'description'=>'ඔබගේ ටෝකන ලේඛනය මෙහි බලන්න, බාගන්න හෝ මුද්‍රණය කරන්න.',
		'download'=>'PDF බාගන්න',
		'print'=>'PDF මුද්‍රණය කරන්න',
		'back'=>'ටෝකන වෙත ආපසු',
		'preview'=>'ටෝකන PDF පෙරදසුන'
	],
	'en'=>[
		'title'=>'Token PDF',
		'description'=>'View, download, or print your token document here.',
		'download'=>'Download PDF',
		'print'=>'Print PDF',
		'back'=>'Back to tokens',
		'preview'=>'Token PDF preview'
	],
	'ta'=>[
		'title'=>'டோக்கன் PDF',
		'description'=>'உங்கள் டோக்கன் ஆவணத்தை இங்கே பார்க்கவும், பதிவிறக்கவும் அல்லது அச்சிடவும்.',
		'download'=>'PDF பதிவிறக்கவும்',
		'print'=>'PDF அச்சிடவும்',
		'back'=>'டோக்கன்களுக்குத் திரும்பு',
		'preview'=>'டோக்கன் PDF முன்னோட்டம்'
	]
];
$viewerCopy=$copy[lang()]??$copy['si'];
$title=$viewerCopy['title'].' | SPRPTA';
$pageStyles=['assets/completion.css'];
$pdfUrl=BASE_URL.'/token_pdf.php?token='.rawurlencode($tokenNo);
$downloadUrl=$pdfUrl.'&download=1';
include __DIR__.'/includes/header.php';
?>
<div class="page token-pdf-view-page">
	<section class="card token-pdf-view-card">
		<div class="section-title">
			<div>
				<span class="badge"><?=h($tokenNo)?></span>
				<h2><?=h($viewerCopy['title'])?></h2>
				<p class="muted"><?=h($viewerCopy['description'])?></p>
			</div>
			<div class="token-pdf-toolbar">
				<a class="primary-btn" href="<?=h($downloadUrl)?>"><?=h($viewerCopy['download'])?></a>
				<button class="secondary-btn" id="printTokenPdf" type="button"><?=h($viewerCopy['print'])?></button>
				<a class="secondary-btn" href="<?=h(BASE_URL)?>/token.php"><?=h($viewerCopy['back'])?></a>
			</div>
		</div>
		<iframe class="token-pdf-preview" id="tokenPdfPreview" src="<?=h($pdfUrl)?>" title="<?=h($viewerCopy['preview'])?>"></iframe>
	</section>
</div>
<script>
document.getElementById('printTokenPdf').addEventListener('click',function(){
	const preview=document.getElementById('tokenPdfPreview');
	preview.contentWindow.focus();
	preview.contentWindow.print();
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
