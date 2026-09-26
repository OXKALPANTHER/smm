<?php
require_once 'config.php';
require_once 'includes/ui.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT username, balance FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];

ui_head('Huduma — ' . APP_NAME, 'app');
ui_nav('services', ['balance' => (float) ($user['balance'] ?? 0)]);
?>
<style>
.catalogue-card{background:#fff;border-radius:22px;padding:1rem;box-shadow:0 14px 34px rgba(43,54,116,.07);border:1px solid rgba(43,54,116,.05);margin-bottom:.75rem}.catalogue-card h6{font-weight:700;margin:0 0 .25rem}.catalogue-meta{font-size:.75rem;color:#8a93b2}.catalogue-price{font-weight:800;color:#4834d4;font-size:1rem}.id-chip{font-size:.68rem;background:#f0f2fb;color:#5a6a85;border-radius:999px;padding:.25rem .55rem;font-weight:700}.result-count{font-size:.75rem;color:#8a93b2}
</style>
<div class="container px-3" style="padding-top:4.5rem;">
  <div class="hero mb-3"><div class="d-flex align-items-center gap-2"><i class="bi bi-list-stars fs-3"></i><div><h4 class="fw-bold mb-1">Huduma zote</h4><p class="mb-0 small opacity-75">Tafuta kwa jina, category au Service ID. Bei zote ni za mteja kwa TSh.</p></div></div></div>
  <div class="card-soft mb-3">
    <label class="form-label" for="serviceSearch">Tafuta huduma</label>
    <div class="input-group"><span class="input-group-text bg-white border-end-0" style="border-radius:15px 0 0 15px"><i class="bi bi-search"></i></span><input id="serviceSearch" class="form-control border-start-0" placeholder="Mfano: 5557 au Followers" autocomplete="off"><button id="clearSearch" class="btn btn-light" type="button">Clear</button></div>
    <div class="d-flex justify-content-between mt-2"><span class="result-count" id="resultCount">Inapakia...</span><span class="result-count">Currency: TSh</span></div>
  </div>
  <div id="serviceList"><div class="card-soft text-center text-muted">Inapakia huduma...</div></div>
</div>
<script>
(function(){const input=document.getElementById('serviceSearch'),list=document.getElementById('serviceList'),count=document.getElementById('resultCount');let timer;
function esc(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML}
function render(items,total){count.textContent=`${total||items.length} huduma`;list.innerHTML=items.length?items.map(s=>`<div class="catalogue-card"><div class="d-flex justify-content-between gap-2"><div><span class="id-chip">ID ${esc(s.id)}</span><h6 class="mt-2">${esc(s.name)}</h6><div class="catalogue-meta">${esc(s.category)} · Min ${Number(s.min||1).toLocaleString()} · Max ${Number(s.max||0).toLocaleString()}</div></div><div class="text-end"><div class="catalogue-price">${Number(s.price_per_1000||0).toLocaleString()} TSh / 1K</div><div class="catalogue-meta mt-1">${s.refill?'Refill · ':''}${s.cancel?'Cancel':''}</div><a class="btn btn-sm btn-grad mt-2" style="width:auto;padding:.45rem .7rem" href="index.php?service_id=${encodeURIComponent(s.id)}">Order</a></div></div></div>`).join(''):'<div class="card-soft text-center text-muted">Hakuna huduma iliyopatikana.</div>'}
async function load(){const q=input.value.trim(),url=new URL('api-services.php',location.href);if(q){if(/^\d+$/.test(q))url.searchParams.set('service_id',q);else url.searchParams.set('q',q)}url.searchParams.set('platform','all');try{const r=await fetch(url),j=await r.json();if(!j.success)throw Error(j.error||'error');render(j.data||[],j.total||j.count||0)}catch(e){list.innerHTML='<div class="card-soft text-center text-danger">Huduma hazikupatikana kwa sasa.</div>';count.textContent=''}}
input.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(load,250)});document.getElementById('clearSearch').addEventListener('click',()=>{input.value='';load()});load();})();
</script>
<?php ui_bottom_nav('services'); ui_foot(); ?>
