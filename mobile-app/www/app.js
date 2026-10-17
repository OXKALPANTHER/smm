(() => {
  const LIVE_SITE = 'https://royal.t20tech.site';
  const keys = { state: 'royal-smm-mobile-state-v1', offline: 'royal-smm-offline-enabled-v1' };
  const defaultServices = [
    {id:'fb-likes',platform:'Facebook',name:'Facebook Post Likes',price:35,min:10,max:100000},
    {id:'ig-likes',platform:'Instagram',name:'Instagram Post Likes',price:40,min:10,max:100000},
    {id:'ig-followers',platform:'Instagram',name:'Instagram Followers',price:210,min:10,max:50000},
    {id:'tt-likes',platform:'TikTok',name:'TikTok Video Likes',price:32,min:10,max:100000},
    {id:'yt-views',platform:'YouTube',name:'YouTube Video Views',price:70,min:100,max:1000000},
    {id:'wa-members',platform:'WhatsApp',name:'WhatsApp Channel Members',price:120,min:10,max:100000},
    {id:'tg-members',platform:'Telegram',name:'Telegram Channel Members',price:90,min:10,max:100000},
    {id:'x-followers',platform:'Twitter/X',name:'Twitter/X Followers',price:150,min:10,max:50000}
  ];
  const state = loadState();
  let currentScreen = 'dashboard';
  let selectedPlatform = 'All';

  function loadState() {
    try { return Object.assign({balance:0, orders:[], services:defaultServices, username:'Royal customer', queue:[]}, JSON.parse(localStorage.getItem(keys.state) || '{}')); }
    catch (_) { return {balance:0,orders:[],services:defaultServices,username:'Royal customer',queue:[]}; }
  }
  function saveState() { localStorage.setItem(keys.state, JSON.stringify(state)); }
  function money(n) { return `${Number(n || 0).toLocaleString()} TZS`; }
  function esc(v) { return String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])); }
  function toast(message) { const el = document.getElementById('toast'); el.textContent = message; el.classList.add('show'); setTimeout(() => el.classList.remove('show'), 2400); }
  function render() { renderDashboard(); renderServices(); renderOrderForm(); renderOrders(); renderWallet(); renderAccount(); }
  function renderDashboard() {
    document.getElementById('balanceAmount').textContent = money(state.balance);
    document.getElementById('walletBalance').textContent = money(state.balance);
    document.getElementById('orderCount').textContent = state.orders.length;
    document.getElementById('pendingCount').textContent = state.orders.filter(o => o.status === 'Pending').length;
    document.getElementById('doneCount').textContent = state.orders.filter(o => o.status === 'Completed').length;
    document.getElementById('notificationBadge').textContent = state.queue.length ? state.queue.length : '1';
  }
  function renderServices() {
    const platforms = ['All', ...new Set(state.services.map(s => s.platform))];
    document.getElementById('platformChips').innerHTML = platforms.map(p => `<button class="chip ${selectedPlatform === p ? 'active' : ''}" data-platform="${esc(p)}">${esc(p)}</button>`).join('');
    const query = (document.getElementById('serviceSearch')?.value || '').toLowerCase();
    const list = state.services.filter(s => (selectedPlatform === 'All' || s.platform === selectedPlatform) && `${s.name} ${s.id} ${s.platform}`.toLowerCase().includes(query));
    document.getElementById('serviceList').innerHTML = list.length ? list.map(s => `<div class="service"><div><div class="service-name">${esc(s.name)}</div><div class="service-meta">${esc(s.platform)} · ID ${esc(s.id)} · ${s.min.toLocaleString()}–${s.max.toLocaleString()}</div></div><div class="service-price">${money(s.price)}/1k</div></div>`).join('') : '<div class="subtitle" style="margin:0">No matching services in offline catalogue.</div>';
  }
  function renderOrderForm() {
    const platform = document.getElementById('orderPlatform');
    const service = document.getElementById('orderService');
    if (!platform || !service) return;
    const platforms = [...new Set(state.services.map(s => s.platform))];
    const oldPlatform = platform.value || platforms[0];
    platform.innerHTML = platforms.map(p => `<option>${esc(p)}</option>`).join('');
    platform.value = platforms.includes(oldPlatform) ? oldPlatform : platforms[0];
    const matches = state.services.filter(s => s.platform === platform.value);
    const oldService = service.value;
    service.innerHTML = matches.map(s => `<option value="${esc(s.id)}">${esc(s.name)} — ${money(s.price)}/1k</option>`).join('');
    if (matches.some(s => s.id === oldService)) service.value = oldService;
    updateEstimate();
  }
  function updateEstimate() { const service = state.services.find(s => s.id === document.getElementById('orderService')?.value); const quantity = Number(document.getElementById('orderQuantity')?.value || 0); document.getElementById('estimate').textContent = money(service ? Math.ceil(service.price * quantity / 1000) : 0); }
  function renderOrders() {
    const el = document.getElementById('ordersList');
    if (!state.orders.length) { el.innerHTML = '<div class="subtitle" style="margin:0">No orders saved on this device yet.</div>'; return; }
    el.innerHTML = [...state.orders].reverse().map(o => `<div class="order-row"><div class="order-icon">✓</div><div class="order-main"><strong>${esc(o.service)}</strong><p>${esc(o.link)} · ${o.quantity.toLocaleString()} · ${money(o.price)}</p></div><span class="status ${o.status === 'Pending' ? 'pending' : o.status === 'Failed' ? 'failed' : ''}">${esc(o.status)}</span></div>`).join('');
  }
  function renderWallet() { document.getElementById('walletBalance').textContent = money(state.balance); }
  function renderAccount() { const name = state.username || 'Royal customer'; document.getElementById('accountName').textContent = name; document.getElementById('avatar').textContent = name[0].toUpperCase(); }
  function showScreen(name) {
    currentScreen = name;
    document.querySelectorAll('.screen').forEach(el => el.classList.toggle('active', el.id === `screen-${name}`));
    document.querySelectorAll('.bottom button').forEach(el => el.classList.toggle('active', el.dataset.screen === name));
    window.scrollTo({top:0, behavior:'instant'});
    if (name === 'services') renderServices();
    if (name === 'orders') renderOrders();
  }
  function setOnlineNotice() { const offline = !navigator.onLine; document.getElementById('onlineNotice').classList.toggle('show', offline); document.getElementById('syncStatus').textContent = offline ? 'Offline. Changes will sync when online.' : 'Online. Local data is ready to sync.'; }
  function queueOrder(order) { state.orders.push(order); state.queue.push({type:'order',payload:order,createdAt:new Date().toISOString()}); saveState(); render(); toast(navigator.onLine ? 'Order saved locally and queued for sync.' : 'Offline order saved. It will sync when online.'); }
  async function syncQueue() {
    if (!navigator.onLine || !state.queue.length) return;
    // The live PHP site remains the source of truth. The queue is intentionally
    // retained until the user signs in through the live site, preventing offline
    // orders from being lost or falsely marked as submitted.
    document.getElementById('syncStatus').textContent = `${state.queue.length} change(s) ready to sync through the live site.`;
    toast('Connection restored. Open the live site to sync queued orders.');
  }
  function openLiveSite() { window.open(`${LIVE_SITE}/login.php?source=app`, '_blank'); }
  document.addEventListener('click', e => { const screen = e.target.closest('[data-screen]')?.dataset.screen; if (screen) { e.preventDefault(); showScreen(screen); } const chip = e.target.closest('[data-platform]'); if (chip) { selectedPlatform = chip.dataset.platform; renderServices(); } });
  document.getElementById('menuBtn').addEventListener('click', () => showScreen('account'));
  document.getElementById('serviceSearch').addEventListener('input', renderServices);
  document.getElementById('orderPlatform').addEventListener('change', renderOrderForm);
  document.getElementById('orderService').addEventListener('change', updateEstimate);
  document.getElementById('orderQuantity').addEventListener('input', updateEstimate);
  document.getElementById('orderForm').addEventListener('submit', e => { e.preventDefault(); const service = state.services.find(s => s.id === document.getElementById('orderService').value); const quantity = Number(document.getElementById('orderQuantity').value); const link = document.getElementById('orderLink').value.trim(); if (!service || !link || quantity < service.min || quantity > service.max) { toast(`Quantity must be between ${service?.min || 1} and ${service?.max || 100000}.`); return; } queueOrder({id:`offline-${Date.now()}`,service:service.name,platform:service.platform,link,quantity,price:Math.ceil(service.price * quantity / 1000),status:'Pending',createdAt:new Date().toISOString()}); e.target.reset(); renderOrderForm(); showScreen('orders'); });
  document.getElementById('topupBtn').addEventListener('click', () => toast(navigator.onLine ? 'Open the live site to complete mobile-money top up.' : 'Top up requests require an internet connection.'));
  document.getElementById('openSite').addEventListener('click', openLiveSite);
  document.getElementById('supportBtn').addEventListener('click', () => window.open('https://wa.me/255627417402', '_blank'));
  document.getElementById('syncBtn').addEventListener('click', syncQueue);
  document.getElementById('signOut').addEventListener('click', () => { localStorage.removeItem(keys.state); location.reload(); });
  window.addEventListener('online', () => { setOnlineNotice(); syncQueue(); });
  window.addEventListener('offline', setOnlineNotice);
  render(); setOnlineNotice();
})();
