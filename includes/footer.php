</main>
<footer class="footer">
	<div class="footer-inner">
		<div class="footer-brand">
			<a class="footer-brand-heading" href="<?=BASE_URL?>/index.php">
				<img class="footer-logo" src="<?=h(OFFICIAL_LOGO_URL)?>" onerror="this.onerror=null;this.src='<?=h(OFFICIAL_LOGO_FALLBACK_URL)?>';" alt="Southern Provincial Road Passenger Transport Authority logo">
				<span class="footer-brand-copy">
					<strong><?=h(APP_NAME_EN)?></strong>
					<small>SOUTHERN PROVINCE · SRI LANKA</small>
				</span>
			</a>
			<p>Your starting point for road passenger transport services in the Southern Province of Sri Lanka.</p>
		</div>
		<nav class="footer-links" aria-label="Quick links">
			<h2>Quick links</h2>
			<a href="<?=BASE_URL?>/index.php#services">Our services</a>
			<a href="<?=BASE_URL?>/register.php">Client registration</a>
			<a href="<?=BASE_URL?>/login.php">Login</a>
		</nav>
		<nav class="footer-links" aria-label="Here to help">
			<h2>Here to help</h2>
			<a href="<?=BASE_URL?>/index.php#help">Help &amp; guidance</a>
			<a href="<?=BASE_URL?>/contact.php#contact-details-title">Contact us</a>
			<a href="<?=BASE_URL?>/contact.php#contact-form-title">General enquiries: <?=h(CONTACT_PHONE)?></a>
			<a class="footer-social-link" href="mailto:sprptaho@gmail.com">
				<svg class="footer-social-icon footer-email-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285f4" d="M3 6.7v11.6h3.5V10.1z"/><path fill="#34a853" d="M17.5 10.1v8.2H21V6.7z"/><path fill="#ea4335" d="M3 6.7l1.8-1.4a2 2 0 0 1 2.5 0L12 8.9l4.7-3.6a2 2 0 0 1 2.5 0L21 6.7l-9 7z"/><path fill="#c5221f" d="M3 18.3V6.7l3.5 2.7v8.9z"/><path fill="#fbbc04" d="M17.5 18.3V9.4L21 6.7v11.6z"/></svg>
				<span>sprptaho@gmail.com</span>
			</a>
			<a class="footer-social-link" href="https://www.facebook.com/share/19aA8VoP96/" target="_blank" rel="noopener noreferrer">
				<svg class="footer-social-icon footer-facebook-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#1877f2"/><path fill="#fff" d="M13.5 21v-8h2.7l.4-3h-3.1V8.1c0-.9.3-1.5 1.6-1.5h1.7V3.9c-.8-.1-1.6-.2-2.4-.2-2.4 0-4.1 1.5-4.1 4.2V10H7.5v3h2.8v8z"/></svg>
				<span>Facebook</span>
			</a>
		</nav>
	</div>
	<div class="footer-bottom">
		<span><?=h(APP_NAME_EN)?> · Sri Lanka</span>
		<span>SPRPTA</span>
	</div>
</footer>
<button id="chatFab" class="chatfab" aria-label="Chat with Assistant">💬</button>
<div id="chatBox" class="chatbox">
	<div class="chat-head">
		<div><b>Chat with RPTA Assistant</b><small>SPRPTA chatbot</small></div>
		<button id="chatClose" aria-label="Close chat">×</button>
	</div>
	<div class="chat-inner">
		<div class="quick-title">Quick questions / ඉක්මන් ප්‍රශ්න</div>
		<div id="quickQuestions" class="quick-grid"></div>
		<div id="chatBody" class="chat-body" style="overflow-y:scroll;scrollbar-width:thin;scrollbar-color:#8e1937 #f4e5ea"><div class="bubble bot">ආයුබෝවන්! RPTA සේවා, Token, ලේඛන, බස් මාර්ග හෝ පැමිණිලි ගැන අහන්න. / Hello! Ask about RPTA services, tokens, documents, routes or complaints.</div></div>
		<div id="chatDocs" class="chat-docs"></div>
		<div class="chat-end"><button id="endChat" class="soft-btn">⭐ End chat &amp; feedback / අවසන් කර Feedback</button></div>
		<div id="feedbackPanel" class="feedback-box" style="display:none">
			<b>How was your experience? / ඔබගේ අත්දැකීම කොහොමද?</b>
			<div class="rating-row" style="margin-top:8px"><button class="rate" data-rate="1">😡</button><button class="rate" data-rate="2">😕</button><button class="rate" data-rate="3">😐</button><button class="rate" data-rate="4">🙂</button><button class="rate" data-rate="5">😍</button></div>
			<textarea id="feedbackNote" placeholder="Optional short note / කැමති නම් කෙටි සටහනක්"></textarea>
			<button id="sendFeedback" class="primary-btn" style="margin-top:8px">Submit Feedback / Feedback යවන්න</button>
		</div>
		<div class="chat-input"><input id="chatInput" placeholder="Type a question / ප්‍රශ්නයක් ලියන්න"><button id="sendChat" class="primary-btn">Send</button></div>
	</div>
</div>
<script src="<?=BASE_URL?>/assets/app.js?v=<?=filemtime(__DIR__.'/../assets/app.js')?>"></script></body></html>