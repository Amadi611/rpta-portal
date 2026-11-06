<?php
require_once __DIR__.'/config.php';
$title='Passenger Transport Services / මගී ප්‍රවාහන සේවා';
$pageStyles=['assets/home-background.css'];
$homeSlides=[
  ['src'=>BASE_URL.'/assets/images/image1.png','alt'=>'Public bus at Galle Bus Stand, Sri Lanka','caption'=>'Public transport at Galle Bus Stand'],
  ['src'=>BASE_URL.'/assets/images/home-slide-1.png','alt'=>'Southern Provincial Road Passenger Transport Authority building at night','caption'=>'Southern Provincial Road Passenger Transport Authority'],
  ['src'=>BASE_URL.'/assets/images/home-slide-2.png','alt'=>'Roadside office building in the Southern Province','caption'=>'Serving communities across the Southern Province'],
  ['src'=>BASE_URL.'/assets/images/home-slide-4.png','alt'=>'Buses and vehicles outside a transport terminal','caption'=>'Connecting passengers throughout the province']
];
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
          <a class="home-button home-button-secondary" href="login.php">Login</a>
        <?php endif;?>
      </div>
      <div class="home-contact"><span>General Contact</span><a href="tel:<?=h(CONTACT_PHONE)?>"><?=h(CONTACT_PHONE)?></a></div>
    </div>
    <figure class="home-photo">
      <div class="home-slideshow" id="homeSlideshow" role="region" aria-roledescription="carousel" aria-label="Southern Province transport photos">
        <div class="home-photo-frame">
          <button class="home-photo-button" id="homePhotoButton" type="button" aria-label="View photo full size">
            <img id="homePhotoImage" src="<?=h($homeSlides[0]['src'])?>" alt="<?=h($homeSlides[0]['alt'])?>" fetchpriority="high">
          </button>
          <button class="home-slide-arrow home-slide-previous" id="homeSlidePrevious" type="button" aria-label="Previous photo">‹</button>
          <button class="home-slide-arrow home-slide-next" id="homeSlideNext" type="button" aria-label="Next photo">›</button>
        </div>
        <figcaption class="home-slide-footer">
          <span id="homeSlideCaption" aria-live="polite"><?=h($homeSlides[0]['caption'])?></span>
          <div class="home-slide-dots" aria-label="Choose a photo">
            <?php foreach($homeSlides as $index=>$slide):?>
              <button class="home-slide-dot" type="button" data-slide-index="<?=$index?>" aria-label="Show photo <?=$index+1?> of <?=count($homeSlides)?>" <?=$index===0?'aria-current="true"':''?>></button>
            <?php endforeach;?>
          </div>
        </figcaption>
      </div>
    </figure>
  </section>
  <dialog class="home-photo-dialog" id="homePhotoDialog" aria-label="Homepage photo">
    <button class="home-photo-close" id="closeHomePhoto" type="button" aria-label="Close photo">×</button>
    <img id="homePhotoDialogImage" src="<?=h($homeSlides[0]['src'])?>" alt="<?=h($homeSlides[0]['alt'])?>">
  </dialog>

  <script>
    const homeSlides=<?=json_encode($homeSlides,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
    const homeSlideshow=document.getElementById('homeSlideshow');
    const homePhotoButton=document.getElementById('homePhotoButton');
    const homePhotoImage=document.getElementById('homePhotoImage');
    const homePhotoDialog=document.getElementById('homePhotoDialog');
    const homePhotoDialogImage=document.getElementById('homePhotoDialogImage');
    const homeSlideCaption=document.getElementById('homeSlideCaption');
    const homeSlideDots=[...document.querySelectorAll('.home-slide-dot')];
    const closeHomePhoto=document.getElementById('closeHomePhoto');
    let currentHomeSlide=0;
    let homeSlideTimer;
    const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function showHomeSlide(index){
      currentHomeSlide=(index+homeSlides.length)%homeSlides.length;
      const slide=homeSlides[currentHomeSlide];
      homePhotoImage.src=slide.src;
      homePhotoImage.alt=slide.alt;
      homePhotoButton.setAttribute('aria-label','View photo full size: '+slide.caption);
      homeSlideCaption.textContent=slide.caption;
      homeSlideDots.forEach((dot,dotIndex)=>{
        if(dotIndex===currentHomeSlide) dot.setAttribute('aria-current','true');
        else dot.removeAttribute('aria-current');
      });
    }
    function stopHomeSlideshow(){window.clearInterval(homeSlideTimer)}
    function startHomeSlideshow(){
      stopHomeSlideshow();
      if(!reducedMotion && !document.hidden && !homePhotoDialog.open && !homeSlideshow.matches(':hover') && !homeSlideshow.contains(document.activeElement)) homeSlideTimer=window.setInterval(()=>showHomeSlide(currentHomeSlide+1),5000);
    }
    document.getElementById('homeSlidePrevious').addEventListener('click',()=>{showHomeSlide(currentHomeSlide-1);startHomeSlideshow()});
    document.getElementById('homeSlideNext').addEventListener('click',()=>{showHomeSlide(currentHomeSlide+1);startHomeSlideshow()});
    homeSlideDots.forEach(dot=>dot.addEventListener('click',()=>{showHomeSlide(Number(dot.dataset.slideIndex));startHomeSlideshow()}));
    homeSlideshow.addEventListener('mouseenter',stopHomeSlideshow);
    homeSlideshow.addEventListener('mouseleave',startHomeSlideshow);
    homeSlideshow.addEventListener('focusin',stopHomeSlideshow);
    homeSlideshow.addEventListener('focusout',event=>{if(!homeSlideshow.contains(event.relatedTarget))startHomeSlideshow()});
    document.addEventListener('visibilitychange',startHomeSlideshow);
    homePhotoButton.addEventListener('click',()=>{
      homePhotoDialogImage.src=homeSlides[currentHomeSlide].src;
      homePhotoDialogImage.alt=homeSlides[currentHomeSlide].alt;
      stopHomeSlideshow();
      homePhotoDialog.showModal();
    });
    closeHomePhoto.addEventListener('click',()=>homePhotoDialog.close());
    homePhotoDialog.addEventListener('click',event=>{if(event.target===homePhotoDialog)homePhotoDialog.close()});
    homePhotoDialog.addEventListener('close',startHomeSlideshow);
    startHomeSlideshow();
    //SCRIPT: HOME ASSISTANT
  </script>
  <section class="home-about" id="about" aria-labelledby="home-about-title">
    <div class="home-services-inner">
      <div class="home-section-heading">
        <h2 id="home-about-title">About the Authority</h2>
      </div>
      <div class="home-info-grid">
        <div class="home-info-panel">
          <p>The Southern Provincial Road Passenger Transport Authority is dedicated to delivering safe, reliable, and efficient public transport services across the province. We work closely with operators, commuters, and service providers to maintain standards, improve access, and support a modern transportation network.</p>
        </div>
        <div class="home-info-panel home-info-points">
          <ul>
            <li>Passenger transport regulation and monitoring</li>
            <li>Service token management and public access</li>
            <li>Documentation, forms, and guidance for commuters</li>
          </ul>
        </div>
      </div>
    </div>
  </section>
  <section class="home-services" id="services" aria-labelledby="home-services-title">
    <div class="home-services-inner">
      <div class="home-services-header">
        <h2 id="home-services-title">How can we help you today?</h2>
        <span>Choose a service to get started</span>
      </div>
      <div class="home-service-grid home-service-grid-portal">
        <a class="home-service home-service-card" href="<?=BASE_URL?>/register.php">
          <span class="home-service-icon home-service-icon-register" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.8"/>
              <path d="M5.5 18.2a6.5 6.5 0 0 1 13 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              <path d="M18.8 5.5v5.2M16.2 8h5.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <h3>Client registration</h3>
          <p>New to the portal? Start here to create your client account.</p>
          <span class="home-service-action">Start registration <span aria-hidden="true">→</span></span>
        </a>
        <a class="home-service home-service-card" href="<?=logged_in() ? BASE_URL.'/dashboard.php' : BASE_URL.'/login.php'?>">
          <span class="home-service-icon home-service-icon-account" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M7 6.5h10a2.5 2.5 0 0 1 2.5 2.5v8A2.5 2.5 0 0 1 17 19.5H7A2.5 2.5 0 0 1 4.5 17V9A2.5 2.5 0 0 1 7 6.5Z" stroke="currentColor" stroke-width="1.8"/>
              <path d="M8 10h8M8 13.5h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              <path d="M15.8 17.2 18.5 14.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              <path d="M15.8 14.5 18.5 17.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <h3>Your online account</h3>
          <p>Already registered? Sign in to continue to your account.</p>
          <span class="home-service-action"><?=logged_in() ? 'Open dashboard' : 'Login to your account'?> <span aria-hidden="true">→</span></span>
        </a>
        <button class="home-service home-service-card home-service-button" id="homeAssistant" type="button" onclick="document.getElementById('chatBox').classList.add('open');document.getElementById('chatInput').focus()">
          <span class="home-service-icon home-service-icon-help" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M7 6.5h10a2.5 2.5 0 0 1 2.5 2.5v6A2.5 2.5 0 0 1 17 17.5h-7L6 20v-2.5A2.5 2.5 0 0 1 3.5 15V9A2.5 2.5 0 0 1 6 6.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
              <path d="M10.2 10.3a2.2 2.2 0 0 1 4.1 1.4c0 1.3-1.3 1.7-1.8 2.2-.4.4-.5.8-.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="12" cy="15.7" r="1" fill="currentColor"/>
            </svg>
          </span>
          <h3>Help &amp; enquiries</h3>
          <p>Not sure where to begin? Get in touch for help with your next step.</p>
          <span class="home-service-action">Find support <span aria-hidden="true">→</span></span>
        </button>
      </div>
    </div>
  </section>
  <section class="home-next-step home-purpose" aria-labelledby="home-purpose-title">
    <div class="home-next-step-inner">
      <div class="home-purpose-heading">
        <p class="home-eyebrow home-eyebrow-alt">OUR PURPOSE</p>
        <h2 id="home-purpose-title">Vision &amp; Mission</h2>
        <p>Our commitment to passenger transport across the Southern Province.</p>
      </div>
      <div class="home-purpose-grid">
        <article class="home-purpose-card home-purpose-vision">
          <span class="home-purpose-label">OUR VISION</span>
          <h3>To be the Premier Passenger Transport Services Facilitator in Sri Lanka</h3>
        </article>
        <article class="home-purpose-card home-purpose-mission">
          <span class="home-purpose-label">OUR MISSION</span>
          <p>To promote the development of passenger transport service with efficiency, quality and quantity to meet the transport needs of the passengers and to provide equitable distribution of the Passenger service in southern province on the basis of social needs in friendly manner.</p>
        </article>
      </div>
    </div>
  </section>
  <section class="home-help" id="help" aria-labelledby="home-help-title">
    <div class="home-services-inner">
      <div class="home-section-heading">
        <p class="home-eyebrow home-eyebrow-alt">SUPPORT FOR EVERY STEP</p>
        <h2 id="home-help-title">Help &amp; Guidance</h2>
        <p class="home-help-intro">Choose a topic below to go straight to the right place.</p>
      </div>
      <div class="home-help-grid">
        <div class="home-help-summary">
          <span class="home-help-summary-icon" aria-hidden="true">?</span>
          <h3>How can we help?</h3>
          <p>Use the portal to register, access documents, request a service token, and track your applications. Our team is ready to help if you need more support.</p>
        </div>
        <nav class="home-help-links" aria-label="Help topics">
          <a class="home-help-link" href="<?=BASE_URL?>/index.php#services">
            <span class="home-help-link-icon" aria-hidden="true">01</span>
            <span class="home-help-link-copy"><strong>Register or sign in</strong><small>Choose registration or sign in to your account.</small></span>
            <span class="home-help-link-arrow" aria-hidden="true">→</span>
          </a>
          <a class="home-help-link" href="<?=BASE_URL?>/documents.php">
            <span class="home-help-link-icon" aria-hidden="true">02</span>
            <span class="home-help-link-copy"><strong>Documents and forms</strong><small>Find and download available portal documents.</small></span>
            <span class="home-help-link-arrow" aria-hidden="true">→</span>
          </a>
          <a class="home-help-link" href="<?=BASE_URL?>/contact.php">
            <span class="home-help-link-icon" aria-hidden="true">03</span>
            <span class="home-help-link-copy"><strong>Complaints and service requests</strong><small>Send an inquiry or get in touch with the Authority.</small></span>
            <span class="home-help-link-arrow" aria-hidden="true">→</span>
          </a>
        </nav>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>