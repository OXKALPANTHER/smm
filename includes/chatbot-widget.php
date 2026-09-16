<?php
if (!function_exists('renderChatbotWidget')) {
    function renderChatbotWidget()
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }
        $supportPhone = defined('WHATSAPP_SUPPORT_PHONE') ? WHATSAPP_SUPPORT_PHONE : '';
        $whatsappUrl = $supportPhone !== '' ? 'https://wa.me/' . rawurlencode($supportPhone) : '#';
        echo <<<HTML
<style>
#royalChatToggle{position:fixed;right:18px;bottom:22px;z-index:1800;width:58px;height:58px;border:0;border-radius:20px;background:linear-gradient(145deg,#00a8a3,#087f8b);color:#fff;box-shadow:0 14px 30px rgba(0,126,137,.34);font-size:1.35rem;transition:transform .2s,box-shadow .2s;}
#royalChatToggle:hover{transform:translateY(-3px);box-shadow:0 18px 34px rgba(0,126,137,.42);}
#royalChatPanel{position:fixed;right:18px;bottom:92px;z-index:1799;width:min(390px,calc(100vw - 28px));height:min(600px,calc(100vh - 125px));display:flex;flex-direction:column;overflow:hidden;background:#fff;border:1px solid rgba(43,54,116,.1);border-radius:24px;box-shadow:0 24px 70px rgba(25,36,79,.25);opacity:0;visibility:hidden;transform:translateY(14px) scale(.97);transform-origin:bottom right;transition:.22s ease;}
#royalChatPanel.open{opacity:1;visibility:visible;transform:none;}
.royal-chat-head{padding:16px 17px;color:#fff;background:linear-gradient(135deg,#102a43,#087f8b);display:flex;align-items:center;gap:11px;}
.royal-chat-avatar{width:38px;height:38px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.18);font-size:1.15rem;}
.royal-chat-head h6{margin:0;font-weight:800}.royal-chat-head small{display:block;opacity:.78;font-size:.68rem;margin-top:2px}.royal-chat-close{margin-left:auto;border:0;background:rgba(255,255,255,.13);color:#fff;width:31px;height:31px;border-radius:10px;}
.royal-chat-messages{flex:1;overflow-y:auto;padding:15px;background:linear-gradient(180deg,#f6fbfb,#f8f9ff);display:flex;flex-direction:column;gap:10px;}
.royal-chat-msg{max-width:87%;padding:10px 12px;border-radius:15px;font-size:.79rem;line-height:1.55;white-space:pre-wrap;word-break:break-word;}
.royal-chat-msg.bot{align-self:flex-start;background:#fff;color:#263653;border:1px solid #e8edf5;border-bottom-left-radius:5px;}.royal-chat-msg.user{align-self:flex-end;background:#087f8b;color:#fff;border-bottom-right-radius:5px;}
.royal-chat-msg.system{align-self:center;max-width:100%;font-size:.68rem;text-align:center;color:#738096;background:transparent;padding:2px 8px;}
.royal-chat-quick{display:flex;gap:6px;overflow-x:auto;padding:9px 11px 0;background:#fff;}.royal-chat-quick button{border:1px solid #dcebed;background:#f3fbfb;color:#087f8b;border-radius:999px;white-space:nowrap;padding:6px 9px;font-size:.68rem;font-weight:700;}
.royal-chat-form{display:flex;gap:7px;padding:10px 11px;background:#fff;border-top:1px solid #edf0f6}.royal-chat-form textarea{resize:none;height:42px;flex:1;border:1px solid #e1e7f0;border-radius:13px;padding:10px;font:inherit;font-size:.78rem;outline:0}.royal-chat-form textarea:focus{border-color:#087f8b;box-shadow:0 0 0 3px rgba(8,127,139,.1)}.royal-chat-send{width:43px;border:0;border-radius:13px;background:#087f8b;color:#fff;font-size:1rem}.royal-chat-send:disabled{opacity:.5}
.royal-chat-foot{padding:0 12px 9px;background:#fff;color:#9aa4b5;font-size:.61rem}.royal-chat-foot a{color:#087f8b;font-weight:700}
@media(max-width:540px){#royalChatToggle{right:14px;bottom:16px}#royalChatPanel{right:10px;bottom:84px;width:calc(100vw - 20px);height:min(620px,calc(100vh - 105px));}}
</style>
<button id="royalChatToggle" type="button" aria-label="Open live support chat" title="Live support"><i class="bi bi-chat-heart-fill"></i></button>
<section id="royalChatPanel" aria-label="Royal live support chat">
  <header class="royal-chat-head"><div class="royal-chat-avatar"><i class="bi bi-stars"></i></div><div><h6>Royal live support</h6><small>English + Kiswahili · real AI assistance</small></div><button class="royal-chat-close" type="button" aria-label="Close chat"><i class="bi bi-x-lg"></i></button></header>
  <div class="royal-chat-messages" id="royalChatMessages"><div class="royal-chat-msg bot">Habari! I can help you use Royal, place orders, top up, check order guidance, and use the API. Ask in English or Kiswahili.</div></div>
  <div class="royal-chat-quick"><button type="button" data-chat-prompt="How do I place an order?">Place an order</button><button type="button" data-chat-prompt="Ninawezaje kuongeza salio?">Ongeza salio</button><button type="button" data-chat-prompt="How can I check my order status?">Order status</button></div>
  <form class="royal-chat-form" id="royalChatForm"><textarea id="royalChatInput" maxlength="1500" placeholder="Ask in English or Kiswahili..." aria-label="Chat message"></textarea><button class="royal-chat-send" type="submit" aria-label="Send message"><i class="bi bi-send-fill"></i></button></form>
  <div class="royal-chat-foot">AI support can explain and guide. For payment disputes or account actions, <a href="{$whatsappUrl}" target="_blank" rel="noopener">contact a human on WhatsApp</a>.</div>
</section>
<script>
(function(){
  const toggle=document.getElementById('royalChatToggle'),panel=document.getElementById('royalChatPanel'),close=panel.querySelector('.royal-chat-close'),form=document.getElementById('royalChatForm'),input=document.getElementById('royalChatInput'),messages=document.getElementById('royalChatMessages'),send=form.querySelector('.royal-chat-send');
  const history=[];
  function open(){panel.classList.add('open');input.focus();}
  function addMessage(text,role){const el=document.createElement('div');el.className='royal-chat-msg '+role;el.textContent=text;messages.appendChild(el);messages.scrollTop=messages.scrollHeight;}
  function addSystem(text){const el=document.createElement('div');el.className='royal-chat-msg system';el.textContent=text;messages.appendChild(el);messages.scrollTop=messages.scrollHeight;return el;}
  async function ask(text){text=text.trim();if(!text||send.disabled)return;addMessage(text,'user');history.push({role:'user',content:text});input.value='';send.disabled=true;const loading=addSystem('Thinking...');try{const response=await fetch('chatbot.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({messages:history})});const raw=await response.text();let data;try{data=JSON.parse(raw);}catch(error){data={success:false,message:'The server returned an invalid response. Check the Render logs for chatbot.php.'};}loading.remove();if(!response.ok||!data.success){addMessage(data.message||'Live support is temporarily unavailable.','bot');return;}addMessage(data.message,'bot');history.push({role:'assistant',content:data.message});}catch(error){loading.remove();addMessage('The chatbot request could not reach this website. Check your connection and try again.','bot');}finally{send.disabled=false;input.focus();}}
  toggle.addEventListener('click',open);close.addEventListener('click',()=>panel.classList.remove('open'));form.addEventListener('submit',e=>{e.preventDefault();ask(input.value);});input.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();ask(input.value);}});document.querySelectorAll('[data-chat-prompt]').forEach(button=>button.addEventListener('click',()=>ask(button.dataset.chatPrompt)));
})();
</script>
HTML;
    }
}
