const BASE='/rpta_portal';
const fab=document.getElementById('chatFab'),box=document.getElementById('chatBox'),closeBtn=document.getElementById('chatClose'),body=document.getElementById('chatBody'),input=document.getElementById('chatInput'),send=document.getElementById('sendChat');
let sessionKey='web-'+Date.now()+'-'+Math.random().toString(36).slice(2);
const quick=['How can I get a token?','Token එකක් ගන්නේ කොහොමද?','What documents can I download?','ලේඛන download කරන්නේ කොහොමද?','How do I make a complaint?','පැමිණිල්ලක් දාන්නේ කොහොමද?','What is the contact number?','බස් මාර්ග තොරතුරු කොහෙන්ද?','What are bus stand services?','බස් නැවතුම් ගැන තොරතුරු?','What about private buses?','SLTB / CTB බස් ගැන?','Three-wheeler passenger permit?','බස් ගාස්තු ගැන තොරතුරු?','What are office hours?','Token එක PDF එකක් විදිහට ගන්න පුළුවන්ද?','How do I register?','Feedback දෙන්නේ කොහොමද?'];
function addMsg(text,who='bot'){const d=document.createElement('div');d.className='bubble '+who;d.innerHTML=text;body.appendChild(d);body.scrollTop=body.scrollHeight}
function renderQuick(){const q=document.getElementById('quickQuestions');if(!q)return;q.innerHTML='';quick.forEach(x=>{const b=document.createElement('button');b.className='quick-q';b.textContent=x;b.onclick=()=>{input.value=x;ask()};q.appendChild(b)})}
async function ask(){const q=(input?.value||'').trim();if(!q)return;addMsg(q,'user');input.value='';try{const r=await fetch(BASE+'/chatbot.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'ask',question:q,session_key:sessionKey})});const j=await r.json();if(j.error)addMsg('⚠️ <b>Assistant:</b><br>'+String(j.error).replace(/\n/g,'<br>'));else addMsg(j.answer||'Please contact the authority for assistance.');if(j.documents?.length){const c=document.getElementById('chatDocs');c.innerHTML='';j.documents.forEach(d=>{const el=document.createElement('div');el.className='chat-doc';const link=document.createElement('a');link.href=d.url;link.download='';link.textContent=d.title;el.append('📄 ',link);c.appendChild(el)})}}catch(e){addMsg('Sorry, the Assistant is temporarily unavailable. / සහායක තාවකාලිකව ලබාගත නොහැක.')}}
if(fab)fab.onclick=()=>box.classList.toggle('open');if(closeBtn)closeBtn.onclick=()=>box.classList.remove('open');const homeAssistant=document.getElementById('homeAssistant');if(homeAssistant)homeAssistant.onclick=()=>{box.classList.add('open');input?.focus()};if(send)send.onclick=ask;if(input)input.addEventListener('keydown',e=>{if(e.key==='Enter')ask()});renderQuick();
const end=document.getElementById('endChat'),panel=document.getElementById('feedbackPanel');let selectedRating=5;document.querySelectorAll('.rate').forEach(b=>b.addEventListener('click',()=>{selectedRating=Number(b.dataset.rate);document.querySelectorAll('.rate').forEach(x=>x.classList.remove('selected'));b.classList.add('selected')}));if(end)end.onclick=()=>{if(panel){panel.style.display='block';panel.scrollIntoView({behavior:'smooth',block:'nearest'})}};const sendFb=document.getElementById('sendFeedback');if(sendFb)sendFb.onclick=()=>{const note=document.getElementById('feedbackNote')?.value||'';fetch(BASE+'/chatbot.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'feedback',session_key:sessionKey,rating:selectedRating,note})}).then(()=>{addMsg('Thank you for your feedback! 😊 / ඔබගේ Feedback එකට ස්තුතියි! 😊');if(panel)panel.style.display='none'})};

document.querySelectorAll('input[type="password"]').forEach(passwordInput=>{
	const field=document.createElement('span');
	field.className='password-field';
	passwordInput.parentNode.insertBefore(field,passwordInput);
	field.appendChild(passwordInput);

	const toggle=document.createElement('button');
	toggle.type='button';
	toggle.className='password-toggle';
	toggle.setAttribute('aria-label','Show password');
	toggle.setAttribute('aria-pressed','false');
	toggle.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>';
	toggle.addEventListener('click',()=>{
		const showPassword=passwordInput.type==='password';
		passwordInput.type=showPassword?'text':'password';
		toggle.setAttribute('aria-label',showPassword?'Hide password':'Show password');
		toggle.setAttribute('aria-pressed',String(showPassword));
		toggle.innerHTML=showPassword
			?'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A9.8 9.8 0 0 1 12 6c6.1 0 9.5 6 9.5 6a16 16 0 0 1-3.1 3.6M6.2 6.3C3.8 7.8 2.5 12 2.5 12s3.4 6 9.5 6c1.1 0 2.1-.2 3-.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>'
			:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>';
	});
	field.appendChild(toggle);
});