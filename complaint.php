<?php
require_once __DIR__.'/config.php';
require_login();
if(role()!=='passenger') redirect('dashboard.php');

$title='Make a Complaint / පැමිණිල්ලක්';
$categories=[
    'bus_service'=>'Bus service / බස් සේවාව',
    'driver_conductor'=>'Driver or conductor / රියදුරු හෝ කොන්දොස්තර',
    'overcharging'=>'Fare or overcharging / ගාස්තු හෝ වැඩිපුර අය කිරීම',
    'delay'=>'Delay or missed service / ප්‍රමාදය හෝ සේවාව නොපැමිණීම',
    'route'=>'Route or bus stop / මාර්ගය හෝ බස් නැවතුම',
    'safety'=>'Safety / ආරක්ෂාව',
    'other'=>'Other / වෙනත්'
];
$userId=(int)$_SESSION['user']['id'];
$profileStmt=$conn->prepare('SELECT phone,email FROM passenger_profiles WHERE user_id=? LIMIT 1');
$profileStmt->bind_param('i',$userId);
$profileStmt->execute();
$profile=$profileStmt->get_result()->fetch_assoc()?:[];
$posted=static function($key,$default=''){
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
};
$category=$posted('category');
$busNumber=$posted('bus_number');
$route=$posted('route');
$serviceDate=$posted('service_date');
$serviceTime=$posted('service_time');
$location=$posted('location');
$phone=$posted('contact_phone',(string)($profile['phone']??''));
$email=$posted('contact_email',(string)($profile['email']??''));
$description=$posted('description');
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!isset($categories[$category])) $error='Please select a complaint category.';
    elseif($serviceDate==='' || !DateTime::createFromFormat('!Y-m-d',$serviceDate) || DateTime::createFromFormat('!Y-m-d',$serviceDate)->format('Y-m-d')!==$serviceDate) $error='Please enter the date of the incident.';
    elseif($phone==='') $error='Please provide a contact phone number.';
    elseif($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Please enter a valid email address.';
    elseif(strlen($description)<10) $error='Please describe the issue in at least 10 characters.';
    elseif(strlen($description)>10000) $error='The complaint description is too long.';
    else{
        $busNumberValue=$busNumber!==''?$busNumber:null;
        $routeValue=$route!==''?$route:null;
        $timeValue=$serviceTime!==''?$serviceTime:null;
        $locationValue=$location!==''?$location:null;
        $emailValue=$email!==''?$email:null;
        $stmt=$conn->prepare('INSERT INTO complaints(user_id,category,bus_number,route,service_date,service_time,location,contact_phone,contact_email,description) VALUES(?,?,?,?,?,?,?,?,?,?)');
        if($stmt){
            $stmt->bind_param('isssssssss',$userId,$category,$busNumberValue,$routeValue,$serviceDate,$timeValue,$locationValue,$phone,$emailValue,$description);
            if($stmt->execute()) redirect('complaint.php?submitted=1');
        }
        $error='Your complaint could not be saved. Please try again.';
    }
}

include 'includes/header.php';
?>
<div class="page narrow">
    <div class="section-title">
        <div><span class="badge">PASSENGER</span><h2>Make a Complaint / පැමිණිල්ලක්</h2><p class="muted">Provide journey details so the authority can review your complaint.</p></div>
        <a class="secondary-btn" href="dashboard.php">← Dashboard</a>
    </div>
    <?php if(isset($_GET['submitted'])):?><div class="feedback-success" role="status">Your complaint has been submitted for review. / ඔබගේ පැමිණිල්ල සමාලෝචනය සඳහා යොමු කර ඇත.</div><?php endif;?>
    <?php if($error!==''):?><div class="feedback-error" role="alert"><?=h($error)?></div><?php endif;?>
    <form class="form-card complaint-form" method="post">
        <h3>Complaint details / පැමිණිල්ලේ විස්තර</h3>
        <label for="category">Complaint type / පැමිණිලි වර්ගය</label>
        <select id="category" name="category" required>
            <option value="">Select a category / වර්ගයක් තෝරන්න</option>
            <?php foreach($categories as $value=>$label):?><option value="<?=h($value)?>" <?=$category===$value?'selected':''?>><?=h($label)?></option><?php endforeach;?>
        </select>
        <div class="split">
            <div><label for="service_date">Date of incident / සිදුවීම වූ දිනය</label><input id="service_date" name="service_date" type="date" value="<?=h($serviceDate)?>" required></div>
            <div><label for="service_time">Approximate time / ආසන්න වේලාව</label><input id="service_time" name="service_time" type="time" value="<?=h($serviceTime)?>"></div>
        </div>
        <div class="split">
            <div><label for="bus_number">Bus number / බස් අංකය</label><input id="bus_number" name="bus_number" maxlength="40" value="<?=h($busNumber)?>" placeholder="If known / දන්නේ නම්"></div>
            <div><label for="route">Route / මාර්ගය</label><input id="route" name="route" maxlength="160" value="<?=h($route)?>" placeholder="Route or destination / මාර්ගය හෝ ගමනාන්තය"></div>
        </div>
        <label for="location">Location / ස්ථානය</label>
        <input id="location" name="location" maxlength="200" value="<?=h($location)?>" placeholder="Bus stop, town or landmark / බස් නැවතුම, නගරය හෝ ස්ථානය">
        <label for="description">What happened? / සිදු වූයේ කුමක්ද?</label>
        <textarea id="description" name="description" rows="6" maxlength="10000" required placeholder="Describe the issue and any relevant details. / ගැටලුව සහ අදාළ විස්තර පැහැදිලිව සඳහන් කරන්න."><?=h($description)?></textarea>
        <h3 class="contact-heading">Contact details / සම්බන්ධ කරගැනීමේ විස්තර</h3>
        <div class="split">
            <div><label for="contact_phone">Phone / දුරකථන අංකය</label><input id="contact_phone" name="contact_phone" type="tel" maxlength="30" value="<?=h($phone)?>" required></div>
            <div><label for="contact_email">Email / විද්‍යුත් තැපෑල</label><input id="contact_email" name="contact_email" type="email" maxlength="120" value="<?=h($email)?>"></div>
        </div>
        <div class="form-actions"><button class="primary-btn" type="submit">Submit Complaint / පැමිණිල්ල යවන්න</button></div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>