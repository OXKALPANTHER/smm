<?php
require_once 'config.php';
require_once 'includes/ui.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT username, balance FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];
$platformConfig = json_decode(PLATFORMS, true) ?: [];

ui_head('Huduma — ' . APP_NAME, 'app');
ui_nav('services', ['balance' => (float) ($user['balance'] ?? 0)]);
?>
<style>
.catalogue-card{background:#fff;border-radius:22px;padding:1rem;box-shadow:0 14px 34px rgba(43,54,116,.07);border:1px solid rgba(43,54,116,.05);margin-bottom:.75rem}.catalogue-card h6{font-weight:700;margin:0 0 .25rem}.catalogue-meta{font-size:.75rem;color:#8a93b2}.catalogue-price{font-weight:800;color:#4834d4;font-size:1rem}.id-chip{font-size:.68rem;background:#f0f2fb;color:#5a6a85;border-radius:999px;padding:.25rem .55rem;font-weight:700}.result-count{font-size:.75rem;color:#8a93b2}.catalogue-tabs{display:flex;gap:.5rem;overflow-x:auto;padding:.15rem .1rem .65rem;scrollbar-width:thin}.catalogue-tabs::-webkit-scrollbar{height:4px}.catalogue-tabs::-webkit-scrollbar-thumb{background:#dfe4f2;border-radius:99px}.catalogue-tab{border:1px solid #e5e9f3;background:#fff;color:#5a6a85;border-radius:999px;padding:.55rem .85rem;white-space:nowrap;font-size:.76rem;font-weight:700;cursor:pointer;transition:.15s}.catalogue-tab.active{background:linear-gradient(135deg,#6c5ce7,#4834d4);border-color:#6c5ce7;color:#fff;box-shadow:0 8px 18px rgba(72,52,212,.2)}.category-wrap{display:flex;flex-wrap:wrap;gap:.45rem;margin:.15rem 0 1rem}.category-tab{border:1px solid #e7eaf3;background:#f8f9fd;color:#65718d;border-radius:12px;padding:.48rem .7rem;font-size:.72rem;font-weight:700;cursor:pointer}.category-tab.active{background:#e9e6ff;border-color:#b8b0ff;color:#4834d4}.catalogue-empty{padding:1.3rem;text-align:center;color:#8a93b2}
</style>
<div class="container px-3" style="padding-top:4.5rem;">
  <div class="hero mb-3"><div class="d-flex align-items-center gap-2"><i class="bi bi-list-stars fs-3"></i><div><h4 class="fw-bold mb-1">Huduma zote</h4><p class="mb-0 small opacity-75">Chagua platform na category kuona huduma zote pamoja na gharama zake za TSh.</p></div></div></div>
  <div class="card-soft mb-3">
    <label class="form-label" for="serviceSearch">Tafuta huduma au Service ID</label>
    <div class="input-group"><span class="input-group-text bg-white border-end-0" style="border-radius:15px 0 0 15px"><i class="bi bi-search"></i></span><input id="serviceSearch" class="form-control border-start-0" placeholder="Mfano: 5557 au Followers" autocomplete="off"><button id="clearSearch" class="btn btn-light" type="button">Clear</button></div>
    <div class="d-flex justify-content-between mt-2"><span class="result-count" id="resultCount">Inapakia...</span><span class="result-count">Bei za mteja · TSh</span></div>
  </div>
  <div class="catalogue-tabs" id="platformTabs" aria-label="Platforms"></div>
  <div class="category-wrap" id="categoryTabs" aria-label="Service categories"></div>
  <div id="serviceList"><div class="card-soft text-center text-muted">Inapakia huduma...</div></div>
</div>
<script>
(function(){
  const input=document.getElementById('serviceSearch'),list=document.getElementById('serviceList'),count=document.getElementById('resultCount'),platformTabs=document.getElementById('platformTabs'),categoryTabs=document.getElementById('categoryTabs');
  const configured=<?= json_encode($platformConfig, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  const iconMap={instagram:'bi-instagram',tiktok:'bi-music-note-beamed',facebook:'bi-facebook',youtube:'bi-youtube',twitter:'bi-twitter-x',telegram:'bi-telegram',whatsapp:'bi-whatsapp',spotify:'bi-spotify',threads:'bi-threads',snapchat:'bi-snapchat',linkedin:'bi-linkedin',pinterest:'bi-pinterest',discord:'bi-discord',twitch:'bi-twitch',reddit:'bi-reddit',google:'bi-google',soundcloud:'bi-soundwave',kick:'bi-broadcast',audiomack:'bi-music-note',shazam:'bi-music-note'};
  let allServices=[],selectedPlatform='all',selectedCategory='all',timer;
  function esc(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML}
  function words(v){return String(v||'').toLowerCase().replace(/twitter\s*\/\s*x/g,'twitter').replace(/[^a-z0-9]+/g,' ').trim()}
  function platformOf(s){
    const text=words((s.name||'')+' '+(s.category||''));
    const keys=Object.keys(configured);
    return keys.find(k=>new RegExp('(^| )'+k.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'( |$)','i').test(text)) || 'other';
  }
  function platformLabel(key){return configured[key]?.name || (key==='other'?'Other':key.charAt(0).toUpperCase()+key.slice(1))}
  function categoryOf(s){
    const raw=String(s.category||'General').trim();
    const platform=platformLabel(platformOf(s));
    let cleaned=raw.replace(new RegExp('(^|[|:/\\-])\\s*'+platform.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'\\s*([|:/\\-]|$)','ig'),' ').trim();
    cleaned=cleaned.replace(/^(instagram|tiktok|facebook|youtube|twitter|telegram|whatsapp|spotify|threads|snapchat|linkedin|pinterest|discord|twitch|reddit|google|soundcloud|kick|audiomack|shazam)\s*[-:|/]?\s*/i,'').trim();
    return cleaned || 'General';
  }
  function decorate(s){return {...s,_platform:platformOf(s),_category:categoryOf(s)}}
  function escReg(v){return String(v).replace(/[.*+?^${}()|[\]\\]/g,'\\$&')}
  function renderPlatformTabs(){
    const available=new Set(allServices.map(s=>s._platform));
    const keys=Object.keys(configured).filter(k=>available.has(k));
    if(available.has('other'))keys.push('other');
    platformTabs.innerHTML='<button class="catalogue-tab '+(selectedPlatform==='all'?'active':'')+'" data-platform="all"><i class="bi bi-grid-3x3-gap me-1"></i>All platforms <span class="opacity-75">('+allServices.length+')</span></button>'+keys.map(k=>'<button class="catalogue-tab '+(selectedPlatform===k?'active':'')+'" data-platform="'+esc(k)+'"><i class="bi '+(iconMap[k]||'bi-globe2')+' me-1"></i>'+esc(platformLabel(k))+' <span class="opacity-75">('+allServices.filter(s=>s._platform===k).length+')</span></button>').join('');
    platformTabs.querySelectorAll('[data-platform]').forEach(b=>b.addEventListener('click',()=>{selectedPlatform=b.dataset.platform;selectedCategory='all';renderPlatformTabs();renderCategories();render();}));
  }
  function visible(){return allServices.filter(s=>selectedPlatform==='all'||s._platform===selectedPlatform)}
  function renderCategories(){
    const source=visible(),cats=[...new Set(source.map(s=>s._category).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
    if(!cats.includes(selectedCategory))selectedCategory='all';
    categoryTabs.innerHTML='<button class="category-tab '+(selectedCategory==='all'?'active':'')+'" data-category="all">All categories <span class="opacity-75">('+source.length+')</span></button>'+cats.map(c=>'<button class="category-tab '+(selectedCategory===c?'active':'')+'" data-category="'+esc(c)+'">'+esc(c)+' <span class="opacity-75">('+source.filter(s=>s._category===c).length+')</span></button>').join('');
    categoryTabs.querySelectorAll('[data-category]').forEach(b=>b.addEventListener('click',()=>{selectedCategory=b.dataset.category;renderCategories();render();}));
  }
  function render(){
    const q=input.value.trim().toLowerCase(),source=visible();
    const items=source.filter(s=>(selectedCategory==='all'||s._category===selectedCategory)&&(!q||String(s.id).includes(q)||String(s.name||'').toLowerCase().includes(q)||String(s._category||'').toLowerCase().includes(q)));
    count.textContent=items.length+' huduma'+(selectedCategory!=='all'?' · '+selectedCategory:'');
    list.innerHTML=items.length?items.map(s=>`<div class="catalogue-card"><div class="d-flex justify-content-between gap-2"><div><span class="id-chip">ID ${esc(s.id)}</span><h6 class="mt-2">${esc(s.name)}</h6><div class="catalogue-meta">${esc(platformLabel(s._platform))} · ${esc(s._category)} · Min ${Number(s.min||1).toLocaleString()} · Max ${Number(s.max||0).toLocaleString()}</div></div><div class="text-end"><div class="catalogue-price">${Number(s.price_per_1000||0).toLocaleString()} TSh / 1K</div><div class="catalogue-meta mt-1">${s.refill?'Refill · ':''}${s.cancel?'Cancel':''}</div><a class="btn btn-sm btn-grad mt-2" style="width:auto;padding:.45rem .7rem" href="index.php?service_id=${encodeURIComponent(s.id)}">Order</a></div></div></div>`).join(''):'<div class="card-soft catalogue-empty">Hakuna huduma kwenye category hii.</div>';
  }
  async function load(){try{const url=new URL('api-services.php',location.href);url.searchParams.set('platform','all');url.searchParams.set('refresh','false');const r=await fetch(url),j=await r.json();if(!j.success)throw Error(j.error||'error');allServices=(j.data||[]).map(decorate);renderPlatformTabs();renderCategories();render();}catch(e){list.innerHTML='<div class="card-soft text-center text-danger">Huduma hazikupatikana kwa sasa.</div>';count.textContent=''}}
  input.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(render,150)});document.getElementById('clearSearch').addEventListener('click',()=>{input.value='';render()});load();
})();
</script>
<?php ui_bottom_nav('services'); ui_foot(); ?>
