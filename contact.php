<?php
require_once __DIR__.'/config.php';

$title='Contact Us / සම්බන්ධ වන්න';
$pageStyles=['assets/contact.css'];
if(empty($_SESSION['contact_csrf'])) $_SESSION['contact_csrf']=bin2hex(random_bytes(32));

$name='';
$email='';
$message='';
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $email=isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
    $message=isset($_POST['message']) && is_string($_POST['message']) ? trim($_POST['message']) : '';
    $csrfToken=isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if(!hash_equals($_SESSION['contact_csrf'],$csrfToken)){
        $error='Your session expired. Please refresh the page and try again.';
    } elseif($name==='' || strlen($name)>120){
        $error='Please enter your name (up to 120 characters).';
    } elseif(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254){
        $error='Please enter a valid email address.';
    } elseif($message==='' || strlen($message)>10000){
        $error='Please enter a message of up to 10,000 characters.';
    } else {
        $stmt=$conn->prepare('INSERT INTO contact_messages(name,email,message) VALUES(?,?,?)');
        if($stmt){
            $stmt->bind_param('sss',$name,$email,$message);
            if($stmt->execute()){
                $_SESSION['contact_csrf']=bin2hex(random_bytes(32));
                redirect('contact.php?submitted=1');
            }
            error_log('Contact form insert failed: '.$stmt->error);
        } else {
            error_log('Contact form statement preparation failed: '.$conn->error);
        }
        $error='Your message could not be saved. Please try again later.';
    }
}

include __DIR__.'/includes/header.php';
?>
<section class="contact-page" aria-labelledby="contact-title">
    <div class="contact-page-heading">
        <p class="contact-eyebrow">WE’RE HERE TO HELP</p>
        <h1 id="contact-title">Contact Us</h1>
        <p>Get in touch with the Southern Provincial Road Passenger Transport Authority.</p>
    </div>
    <div class="contact-layout">
        <section class="contact-details" aria-labelledby="contact-details-title">
            <h2 id="contact-details-title">Authority contact details</h2>
            <dl>
                <div class="contact-detail">
                    <dt>Address</dt>
                    <dd>No. 67, Leynbaan Street, Fort, Galle.</dd>
                </div>
                <div class="contact-detail">
                    <dt>Location</dt>
                    <dd><a href="https://www.google.com/maps/search/?api=1&amp;query=No.+67%2C+Leynbaan+Street%2C+Fort%2C+Galle" target="_blank" rel="noopener noreferrer">View on Google Maps <span aria-hidden="true">↗</span></a></dd>
                </div>
                <div class="contact-detail">
                    <dt>Email</dt>
                    <dd><a href="mailto:sprptaho@gmail.com">sprptaho@gmail.com</a></dd>
                </div>
                <div class="contact-detail">
                    <dt>Head office</dt>
                    <dd><a href="tel:0912234237">091 2234237</a></dd>
                </div>
                <div class="contact-detail">
                    <dt>Fax</dt>
                    <dd>091 22 34 237 / 2227073-74</dd>
                </div>
                <div class="contact-detail">
                    <dt>Website</dt>
                    <dd><a href="https://www.sprpta.lk" target="_blank" rel="noopener noreferrer">www.sprpta.lk <span aria-hidden="true">↗</span></a></dd>
                </div>
                <div class="contact-detail">
                    <dt>Facebook</dt>
                    <dd><a href="https://www.facebook.com/share/19aA8VoP96/" target="_blank" rel="noopener noreferrer">Visit our Facebook page <span aria-hidden="true">↗</span></a></dd>
                </div>
            </dl>
        </section>
        <section class="contact-form-panel" aria-labelledby="contact-form-title">
            <h2 id="contact-form-title">Send us a message</h2>
            <p>Your message will be saved for the Authority to review.</p>
            <?php if(isset($_GET['submitted'])):?><div class="notice success" role="status">Thank you. Your message has been submitted.</div><?php endif;?>
            <?php if($error!==''):?><div class="notice danger" role="alert"><?=h($error)?></div><?php endif;?>
            <form method="post" action="<?=BASE_URL?>/contact.php">
                <input type="hidden" name="csrf_token" value="<?=h($_SESSION['contact_csrf'])?>">
                <label for="contact-name">Your name</label>
                <input id="contact-name" name="name" type="text" maxlength="120" autocomplete="name" value="<?=h($name)?>" required>
                <label for="contact-email">Email address</label>
                <input id="contact-email" name="email" type="email" maxlength="254" autocomplete="email" value="<?=h($email)?>" required>
                <label for="contact-message">Message</label>
                <textarea id="contact-message" name="message" rows="6" maxlength="10000" required><?=h($message)?></textarea>
                <button class="primary-btn" type="submit">Send message</button>
            </form>
        </section>
    </div>
</section>
<?php include __DIR__.'/includes/footer.php'; ?>
