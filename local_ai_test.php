<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/local_ai_config.php';
header('Content-Type: text/html; charset=utf-8');
$ch = curl_init(LOCAL_AI_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode([
        'model'=>LOCAL_AI_MODEL,
        'prompt'=>'Say: SPRPTA local AI is working.',
        'stream'=>false,
        'options'=>['num_predict'=>40]
    ]),
    CURLOPT_CONNECTTIMEOUT=>3,
    CURLOPT_TIMEOUT=>30
]);
$raw=curl_exec($ch); $err=curl_error($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
$title='SPRPTA Local AI Test';
$pageBodyClass='local-ai-test-page';
include __DIR__.'/includes/header.php';
?>
<style>.local-ai-test-box{max-width:760px;margin:30px auto;background:#fff;padding:25px;border-radius:16px;box-shadow:0 4px 18px #0001;color:#421322}.local-ai-test-box code{background:#f1e7eb;padding:3px 6px;border-radius:5px}</style>
<div class="local-ai-test-box"><h2>SPRPTA Local Generative AI Test</h2>
<p><b>Model:</b> <?=h(LOCAL_AI_MODEL)?></p><p><b>Endpoint:</b> <?=h(LOCAL_AI_URL)?></p>
<?php if($raw!==false && !$err && $http<400): $d=json_decode($raw,true); ?>
<div style="padding:15px;background:#eaf8ef;border-radius:10px">✅ Local AI is working.<br><br><b>Response:</b> <?=h($d['response'] ?? 'No response text')?></div>
<?php else: ?>
<div style="padding:15px;background:#fff0f0;border-radius:10px">❌ Local AI is not available.<br><br>
Install Ollama, then run:<br><code>ollama pull <?=h(LOCAL_AI_MODEL)?></code><br><br>
<?=h($err ?: ('HTTP '.$http.' — '.($raw ?: 'No response')))?>
</div>
<?php endif; ?>
<p><a href="<?=BASE_URL?>/index.php">← Back to website</a></p></div>
<?php include __DIR__.'/includes/footer.php'; ?>
