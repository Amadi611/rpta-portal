<?php
require_once __DIR__.'/config.php';
$homeEvents=[];
$eventResult=$conn->query("SELECT title,description,event_date,image_path FROM events WHERE active=1 AND (event_date IS NULL OR event_date>=CURDATE()) ORDER BY event_date IS NULL ASC,event_date ASC,id DESC LIMIT 6");
if($eventResult){while($event=$eventResult->fetch_assoc()) $homeEvents[]=$event;}
$title='Passenger Transport Services / මගී ප්‍රවාහන සේවා';
include __DIR__.'/includes/header.php';
$serviceHref=role()==='passenger'?'token.php':(logged_in()?'dashboard.php':'login.php');
?>
<div class="home-shell">
  <section class="home-hero">
    <div class="home-copy">
      <p class="home-eyebrow">Southern Province / Sri Lanka</p>
      <h1><?=h(APP_NAME_EN)?></h1>
      <p class="home-service-title">Passenger Transport Services</p>
      <p class="home-sinhala"><?=h(APP_NAME_SI)?></p>
      <div class="home-actions">
        <?php if(logged_in()):?>
          <a class="home-button home-button-primary" href="<?=role()==='passenger'?'token.php':'dashboard.php'?>"><?=role()==='passenger'?'Request a service token':'Open dashboard'?></a>
        <?php else:?>
          <a class="home-button home-button-primary" href="register.php">Register as a Client</a>
          <a class="home-button home-button-secondary" href="login.php">Sign in</a>
        <?php endif;?>
      </div>
      <div class="home-contact"><span>General Contact</span><a href="tel:<?=h(CONTACT_PHONE)?>"><?=h(CONTACT_PHONE)?></a></div>
    </div>
    <figure class="home-photo">
      <button class="home-photo-button" id="homePhotoButton" type="button" aria-label="View the Galle bus stand photo full size">
        <img src="<?=BASE_URL?>/assets/images/image1.png" alt="Air-conditioned bus at Galle Bus Stand, Sri Lanka" fetchpriority="high">
        <span class="home-photo-cue">View full photo <span aria-hidden="true">↗</span></span>
      </button>
    </figure>
  </section>
  <dialog class="home-photo-dialog" id="homePhotoDialog" aria-label="Galle bus stand with an air-conditioned bus">
    <button class="home-photo-close" id="closeHomePhoto" type="button" aria-label="Close photo">×</button>
    <img src="<?=BASE_URL?>/assets/images/image1.png" alt="Air-conditioned bus at Galle Bus Stand, Sri Lanka">
  </dialog>
  <script>
    const homePhotoButton=document.getElementById('homePhotoButton');
    const homePhotoDialog=document.getElementById('homePhotoDialog');
    const closeHomePhoto=document.getElementById('closeHomePhoto');
    homePhotoButton?.addEventListener('click',()=>homePhotoDialog.showModal());
    closeHomePhoto?.addEventListener('click',()=>homePhotoDialog.close());
    homePhotoDialog?.addEventListener('click',event=>{if(event.target===homePhotoDialog)homePhotoDialog.close()});
  </script>
  <section class="home-events" id="events" aria-labelledby="home-events-title">
    <div class="home-events-inner">
      <div class="home-section-heading">
        <p class="home-eyebrow">UPDATES</p>
        <h2 id="home-events-title">Events</h2>
      </div>
      <?php if($homeEvents):?><div class="home-event-grid">
        <?php foreach($homeEvents as $event):?><article class="home-event-card">
          <img src="<?=BASE_URL?>/<?=h($event['image_path'])?>" alt="Event photo: <?=h($event['title'])?>" loading="lazy">
          <div class="home-event-copy">
            <?php if($event['event_date']):?><time datetime="<?=h($event['event_date'])?>"><?=h(date('M j, Y',strtotime($event['event_date'])))?></time><?php endif;?>
            <h3><?=h($event['title'])?></h3>
            <?php if(trim($event['description']??'')!==''):?><p><?=h($event['description'])?></p><?php endif;?>
          </div>
        </article><?php endforeach;?>
      </div><?php else:?><div class="home-events-empty" role="status">
        <strong>No upcoming events</strong>
        <span>New event announcements will appear here.</span>
      </div><?php endif;?>
    </div>
  </section>
  <section class="home-services" aria-labelledby="home-services-title">
    <div class="home-services-inner">
      <div class="home-section-heading">
        <p class="home-eyebrow">RPTA PORTAL</p>
        <h2 id="home-services-title">Services</h2>
      </div>
      <div class="home-service-grid">
        <a class="home-service" href="<?=h($serviceHref)?>">
          <span class="home-service-kicker">01 / PASSENGERS</span>
          <h3>Passenger services</h3>
          <span class="home-service-action"><?=role()==='passenger'?'Request a token':'Continue'?> <span aria-hidden="true">→</span></span>
        </a>
        <a class="home-service" href="<?=logged_in()?'documents.php':'login.php'?>">
          <span class="home-service-kicker">02 / DOCUMENTS</span>
          <h3>Forms and documents</h3>
          <span class="home-service-action"><?=logged_in()?'Open documents':'Sign in to view'?> <span aria-hidden="true">→</span></span>
        </a>
        <button class="home-service home-service-button" id="homeAssistant" type="button" onclick="document.getElementById('chatBox').classList.add('open');document.getElementById('chatInput').focus()">
          <span class="home-service-kicker">03 / ASSISTANT</span>
          <h3>Ask the RPTA Assistant</h3>
          <span class="home-service-action">Start a conversation <span aria-hidden="true">→</span></span>
        </button>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>