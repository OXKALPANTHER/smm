<?php
/** Public, read-only starting-price snapshot. Orders remain available only in the authenticated catalogue. */
function renderPublicPriceTable(): void
{
?>
<style>
.public-price-tools{display:grid;grid-template-columns:minmax(0,1fr) minmax(150px,.45fr);gap:.7rem;margin-bottom:1rem}.public-price-tools input,.public-price-tools select{min-height:48px;font-size:16px}.public-price-wrap{overflow:auto;border:1px solid #e9edf7;border-radius:16px;background:#fff}.public-price-table{width:100%;min-width:680px;border-collapse:collapse}.public-price-table th,.public-price-table td{padding:.8rem .9rem;text-align:left;border-bottom:1px solid #edf0f7;font-size:.84rem;vertical-align:middle}.public-price-table th{font-size:.73rem;color:#68718d;text-transform:uppercase;letter-spacing:.04em;background:#f8f9fd}.public-price-table tr:last-child td{border-bottom:0}.public-price-empty{padding:1.1rem;color:#68718d}.public-price-note{font-size:.78rem;color:#68718d;margin-top:.65rem}@media(max-width:560px){.public-price-tools{grid-template-columns:1fr}}@media(prefers-color-scheme:dark){.public-price-tools input,.public-price-tools select{background:#141c38;color:#f1f4ff;border-color:#35416c}.public-price-tools input::placeholder{color:#b3bfd9;opacity:1}.public-price-wrap{background:#111832;border-color:#35416c}.public-price-table th,.public-price-table td{color:#e7edff;border-color:#303b64}.public-price-table th{background:#202a4b;color:#d6def3}.public-price-empty{color:#b9c4de}}
</style>
<div class="public-price-tools" role="search" aria-label="Search public service prices">
  <input id="publicPriceSearch" type="search" aria-label="Search platform or service name" placeholder="Search platform or service name">
  <select id="publicPricePlatform" aria-label="Filter prices by platform"><option value="">All platforms</option></select>
</div>
<div class="public-price-wrap"><table class="public-price-table"><thead><tr><th>Platform</th><th>Service</th><th>Rate / 1,000</th><th>Min</th><th>Max</th><th>Refill</th></tr></thead><tbody id="publicPriceRows"><tr><td colspan="6" class="public-price-empty">Loading current catalogue rates…</td></tr></tbody></table></div>
<p class="public-price-note">Public starting rates use the standard price tier and match the 1,000-unit order calculation. Rates and availability can change; account-specific prices may differ.</p>
<script>
(function(){
 const rows=document.getElementById('publicPriceRows'),search=document.getElementById('publicPriceSearch'),filter=document.getElementById('publicPricePlatform');
 let data=[];
 const safe=v=>{const e=document.createElement('span');e.textContent=String(v??'');return e.innerHTML};
 const render=()=>{const q=search.value.trim().toLowerCase(),platform=filter.value;const visible=data.filter(x=>(!platform||x.platform===platform)&&(!q||String(x.platform+' '+x.name).toLowerCase().includes(q)));rows.innerHTML=visible.length?visible.map(x=>`<tr><td>${safe(x.platform)}</td><td>${safe(x.name||'Catalogue service')}</td><td>${Number(x.price).toLocaleString()} ${safe(x.currency)} / 1,000</td><td>${Number(x.min||0).toLocaleString()}</td><td>${Number(x.max||0).toLocaleString()}</td><td>${x.refill?'Yes':'No'}</td></tr>`).join(''):'<tr><td colspan="6" class="public-price-empty">No matching catalogue rates.</td></tr>';};
 fetch('public-prices.php',{headers:{Accept:'application/json'}}).then(r=>{if(!r.ok)throw Error('Catalogue unavailable');return r.json();}).then(j=>{if(!j.success||!Array.isArray(j.data))throw Error('Catalogue unavailable');data=j.data;[...new Set(data.map(x=>x.platform))].sort().forEach(p=>{const o=document.createElement('option');o.value=p;o.textContent=p;filter.appendChild(o);});render();}).catch(()=>{rows.innerHTML='<tr><td colspan="6" class="public-price-empty">Current rates are temporarily unavailable. Please try again later.</td></tr>';});
 search.addEventListener('input',render);filter.addEventListener('change',render);
})();
</script>
<?php
}
