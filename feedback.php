<?php
require_once __DIR__.'/config.php';
require_login();
if(role()!=='passenger') redirect('dashboard.php');

$title='Client Feedback / සේවාලාභී ප්‍රතිචාර';
$userId=(int)$_SESSION['user']['id'];
$requestedTokenId=(int)($_GET['token']??0);
if($requestedTokenId>0){
    $tokenStmt=$conn->prepare('SELECT id,token_no,service_type,preferred_date FROM tokens WHERE user_id=? AND id=? LIMIT 1');
    $tokenStmt->bind_param('ii',$userId,$requestedTokenId);
}else{
    $tokenStmt=$conn->prepare('SELECT id,token_no,service_type,preferred_date FROM tokens WHERE user_id=? ORDER BY id DESC LIMIT 1');
    $tokenStmt->bind_param('i',$userId);
}
$tokenStmt->execute();
$token=$tokenStmt->get_result()->fetch_assoc();
if(!$token) redirect('token.php');
$tokenId=(int)$token['id'];
$existingFeedbackStmt=$conn->prepare('SELECT id FROM feedback WHERE user_id=? AND token_id=? LIMIT 1');
$existingFeedbackStmt->bind_param('ii',$userId,$tokenId);
$existingFeedbackStmt->execute();
$alreadySubmitted=(bool)$existingFeedbackStmt->get_result()->fetch_assoc();
$posted=static function($key,$default=''){
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
};
$rating=$posted('rating');
$satisfied=$posted('satisfied');
$opinion=$posted('opinion');
$suggestions=$posted('suggestions');
$error='';

if($_SERVER['REQUEST_METHOD']==='POST' && !$alreadySubmitted){
    if(!in_array($rating,['1','2','3','4'],true)) $error='Please choose a service rating.';
    elseif(!in_array($satisfied,['yes','no'],true)) $error='Please tell us whether you were satisfied.';
    elseif(strlen($opinion)>210 || strlen($suggestions)>210) $error='Please keep each response under 210 characters.';
    else{
        $ratingValue=(int)$rating;
        $noteValue=trim('Institution opinion: '.$opinion.' | Suggestions: '.$suggestions);
        $service=$token['service_type'];
        $serviceDate=$token['preferred_date'];
        $stmt=$conn->prepare('INSERT INTO feedback(user_id,token_id,rating,note,service_area,recommend,service_date) VALUES(?,?,?,?,?,?,?)');
        if($stmt){
            $stmt->bind_param('iiissss',$userId,$tokenId,$ratingValue,$noteValue,$service,$satisfied,$serviceDate);
            if($stmt->execute()) redirect('feedback.php?token='.$tokenId.'&submitted=1');
        }
        $error='Your feedback could not be saved. Please try again.';
    }
}

include 'includes/header.php';
?>
<div class="page narrow">
    <div class="section-title">
        <div><span class="badge">CLIENT</span><h2>Feedback / ප්‍රතිචාර</h2><p class="muted">Tell us about your experience with the service.</p></div>
        <a class="secondary-btn" href="dashboard.php">← Dashboard</a>
    </div>
    <?php if(isset($_GET['submitted'])):?><div class="feedback-success" role="status">Thank you. Your feedback has been recorded. / ස්තුතියි. ඔබගේ ප්‍රතිචාරය සටහන් කර ඇත.</div><?php elseif($alreadySubmitted):?><div class="feedback-success" role="status">You have already submitted feedback for this task. / මෙම කාර්යය සඳහා ඔබ ප්‍රතිචාර ලබා දී ඇත.</div><?php endif;?>
    <?php if($error!==''):?><div class="feedback-error" role="alert"><?=h($error)?></div><?php endif;?>
    <?php if(!$alreadySubmitted):?>
    <form class="form-card feedback-form" method="post">
        <h3>Service experience / සේවා අත්දැකීම</h3>
        <div class="notice info"><strong>Service:</strong> <?=h($token['service_type'])?> <span class="muted">| <?=h($token['preferred_date'])?> | <?=h($token['token_no'])?></span></div>

        <fieldset class="survey-group">
            <legend>How was the service? / සේවාව පිළිබඳ ඔබගේ අදහස</legend>
            <div class="rating-options">
                <?php foreach([4=>'Very good / ඉතා හොඳයි',3=>'Good / හොඳයි',2=>'Fair / සාමාන්‍යයි',1=>'Poor / දුර්වලයි'] as $value=>$label):?>
                    <label class="rating-option"><input type="radio" name="rating" value="<?=$value?>" <?=$rating===(string)$value?'checked':''?> required><span class="rating-face" aria-hidden="true"><?=['','☹','😐','🙂','😊'][$value]?></span><span><?=h($label)?></span></label>
                <?php endforeach;?>
            </div>
        </fieldset>

        <fieldset class="survey-group">
            <legend>Were you satisfied with this service? / මෙම සේවාව පිළිබඳ ඔබ සෑහීමකට පත්වුණාද?</legend>
            <div class="choice-options">
                <label><input type="radio" name="satisfied" value="yes" <?=$satisfied==='yes'?'checked':''?> required> Yes / ඔව්</label>
                <label><input type="radio" name="satisfied" value="no" <?=$satisfied==='no'?'checked':''?> required> No / නැත</label>
            </div>
        </fieldset>

        <label for="opinion">Your opinion about this institution and its services / ආයතනය සහ සේවා පිළිබඳ ඔබගේ අදහස</label>
        <textarea id="opinion" name="opinion" rows="4" maxlength="210" placeholder="Share your opinion."><?=h($opinion)?></textarea>
        <label for="suggestions">New suggestions / නව යෝජනා</label>
        <textarea id="suggestions" name="suggestions" rows="4" maxlength="210" placeholder="Share a suggestion for improvement."><?=h($suggestions)?></textarea>
        <div class="form-actions"><button class="primary-btn" type="submit">Submit Feedback / ප්‍රතිචාරය යවන්න</button></div>
    </form>
    <?php endif;?>
</div>
<?php include 'includes/footer.php'; ?>