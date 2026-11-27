<?php
require_once 'config.php';
require_login();

$no=isset($_GET['token'])&&is_string($_GET['token'])?$_GET['token']:'';
$stmt=$conn->prepare('SELECT * FROM tokens WHERE token_no=?');
if(!$stmt){
    error_log('Could not prepare token PDF lookup: '.$conn->error);
    http_response_code(500);
    exit('Token lookup failed');
}
$stmt->bind_param('s',$no);
if(!$stmt->execute()){
    error_log('Could not look up token PDF: '.$stmt->error);
    http_response_code(500);
    exit('Token lookup failed');
}
$token=$stmt->get_result()->fetch_assoc();
if(!$token){
    http_response_code(404);
    exit('Token not found');
}

$userId=(int)($_SESSION['user']['id']??0);
if(role()==='passenger'&&(int)$token['user_id']!==$userId){
    http_response_code(403);
    exit('You are not allowed to download this token.');
}

$file=tempnam(sys_get_temp_dir(),'sprpta_token_');
if($file===false){
    error_log('Could not create temporary file for token PDF.');
    http_response_code(500);
    exit('PDF generation failed');
}

require_once __DIR__.'/lib_pdf.php';
try{
    make_token_pdf($file,$token);
}catch(Throwable $error){
    if(is_file($file)) unlink($file);
    error_log('Token PDF generation failed: '.$error->getMessage());
    http_response_code(500);
    exit('PDF generation failed');
}
$fileSize=is_file($file)?filesize($file):false;
if($fileSize===false||$fileSize===0){
    if(is_file($file)) unlink($file);
    error_log('Token PDF generation produced an empty file.');
    http_response_code(500);
    exit('PDF generation failed');
}

$safeTokenNo=preg_replace('/[^A-Za-z0-9_-]/','_',(string)$token['token_no']);
$disposition=isset($_GET['download'])?'attachment':'inline';
header('Content-Type: application/pdf');
header('Content-Disposition: '.$disposition.'; filename="SPRPTA_Token_'.$safeTokenNo.'.pdf"');
header('Content-Length: '.$fileSize);
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($file);
if(!unlink($file)){
    error_log('Could not remove temporary token PDF: '.$file);
}
exit;
?>
