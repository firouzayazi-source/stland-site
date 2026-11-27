/*!
 * ابزارهای آیفونِ استوک لند — «کارشناسی قیمت»، «سلامت آیفون»، «مقایسه دو آیفون».
 * کدِ ویزاردها عیناً از مینی‌اپِ پروژه‌ی ربات (stockland-bot، app/app.js) آمده — همان مراحل، شرط‌ها و متن‌ها (صاحب فروشگاه،
 * ۶ آذر ۱۴۰۵: «عینِ همان را با زبانِ طراحیِ خودمان بیاور»). این بالا فقط «سازگارکننده» است: پنجره‌ی مینی‌اپ ← کادرِ همین برگه،
 * `/api/v1/...` ← `wp-json/stland/v1/ipt/...`، حسابِ تلگرام ← شناسه‌ی دستگاه در مرورگر، اشتراکِ تلگرام ← اشتراکِ گوشی/کپی.
 * محاسبه‌ی قیمت و سلامت همیشه سمتِ سرور است (includes/iphone-tools.php).
 */
(function(){
'use strict';
var C=window.stlhIpt||{rest:'/wp-json/stland/v1/ipt',tool:'price',urls:{},titles:{},cert:''};
var initData='';
var _FA_DIGITS=['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
window.slIsIPhone=/iPhone|iPod/.test(navigator.userAgent||'');
/* شناسه‌ی ناشناسِ این دستگاه: تاریخچه و مالکیتِ گزارش به همین وصل است (سایت حسابِ تلگرامی ندارد) */
function _iptDev(){
  var k='stlh_ipt_dev',v='';
  try{v=localStorage.getItem(k)||''}catch(e){}
  if(!/^[a-f0-9]{32}$/.test(v)){
    v='';var a=new Uint8Array(16);(window.crypto||window.msCrypto).getRandomValues(a);
    for(var i=0;i<16;i++)v+=('0'+a[i].toString(16)).slice(-2);
    try{localStorage.setItem(k,v)}catch(e){}
  }
  return v;
}
/* `fetch` همین بسته: مسیرِ /api/v1 ربات ← REST وردپرس */
var _realFetch=window.fetch.bind(window);
var fetch=function(u,o){
  o=o||{};
  if(typeof u==='string'&&u.indexOf('/api/v1')===0){
    var p=u.slice(7),q='',qi=p.indexOf('?');
    if(qi>=0){q=p.slice(qi);p=p.slice(0,qi)}
    u=C.rest+p+q;
    var h=o.headers||{};h['X-Stl-Device']=_iptDev();o.headers=h;o.credentials='same-origin';
  }
  return _realFetch(u,o);
};
window._slApp={popup:{open:function(){},close:function(){_iptHome()}},dialog:{alert:function(m){window.alert(m)}}};
window.slHasAccount=function(){return true};
window.slRequireAccount=function(){};
function slRequireLogin(){return Promise.resolve(true)}
function slAuthWall(box,msg){if(box)box.innerHTML=err(msg)}
function slIsAuthErr(){return false}
function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
function fmt(n){return Number(n).toLocaleString('fa-IR')}
function faNum(s){return String(s==null?'':s).replace(/[0-9]/g,function(d){return _FA_DIGITS[+d]})}
function digitsOnly(s){s=String(s==null?'':s);s=s.replace(/[۰-۹]/g,function(d){return String(d.charCodeAt(0)-1776)});s=s.replace(/[٠-٩]/g,function(d){return String(d.charCodeAt(0)-1632)});return s.replace(/[^0-9]/g,'')}
function jalaliDate(s){if(!s)return '—';var d=new Date(String(s).replace(' ','T'));return isNaN(d)?String(s):d.toLocaleDateString('fa-IR')}
function err(m){if(!m||m==='خطا')m='بارگذاری انجام نشد؛ اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.';return '<div class="sl-empty"><span class="sl-empty-e">⚠️</span>'+esc(m)+'</div>'}
function skel(n){var o='';for(var i=0;i<(n||3);i++)o+='<div class="sl-skel"><div class="b w60"></div><div class="b w90"></div><div class="b w40"></div></div>';return o}
function ivImgHtml(){return '<span class="sl-iv-devcard-imgbox"><svg class="sl-iv-devcard-ic" viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2.4"/><path d="M10.4 19h3.2"/></svg></span>'}
var _slWizKey=null;
function _slWizTop(key){if(key===_slWizKey)return;_slWizKey=key;var r=document.querySelector('.stlh-ipt');if(r&&r.getBoundingClientRect().top<0)r.scrollIntoView({block:'start'})}
window._slWizTop=_slWizTop;
/* پنجره‌ی مینی‌اپ ← سرِ همین کادر؛ صفحه‌های فرعی (تاریخچه) دکمه‌ی بازگشت دارند */
function _accPopup(title,html){
  var t=document.getElementById('post-title'),b=document.getElementById('post-body');
  if(!t||!b)return;
  var main=(C.titles&&C.titles[C.tool])||'';
  var sub=title&&['کارشناسی آیفون','سلامت آیفون','مقایسه دو آیفون'].indexOf(title)<0;
  t.innerHTML=sub?'<button type="button" class="stlh-ipt-back">‹ بازگشت</button><span>'+esc(title)+'</span>':'<h1>'+esc(main||title)+'</h1>';
  var bk=t.querySelector('.stlh-ipt-back');if(bk)bk.addEventListener('click',_iptHome);
  b.innerHTML='<div class="sl-acc-page">'+html+'</div>';
  _slWizTop(null);
}
function _accBody(){return document.querySelector('#post-body .sl-acc-page')}
function _ihPost(path,body){
  var h={'Content-Type':'application/json','Accept':'application/json'};
  return fetch('/api/v1'+path,{method:'POST',headers:h,body:JSON.stringify(body||{})})
    .then(function(r){return r.json().then(function(j){if(!r.ok){var e=j||{};try{e.__status=r.status}catch(_){}throw e}return j})});
}
function api(p){
  return fetch('/api/v1'+p,{headers:{'Accept':'application/json'}}).then(function(r){
    if(r.ok)return r.json();
    return r.json().catch(function(){return {}}).then(function(d){var e=new Error((d&&d.detail)||'ارتباط با سرور برقرار نشد؛ دوباره تلاش کنید.');e.status=r.status;throw e});
  });
}
function _iptShare(text,url){
  var full=text+(url?'\n'+url:'');
  if(navigator.share){navigator.share({text:full}).catch(function(){});return}
  try{navigator.clipboard.writeText(full).then(function(){window.alert('متن کپی شد؛ هر جا خواستید بفرستید.')})}catch(e){window.prompt('کپی کنید:',full)}
}
function _iptHome(){
  if(C.tool==='health')return window.openIHWizard();
  if(C.tool==='compare')return window.openCompare();
  return window.openIVWizard();
}

/* ════════════ کدِ مینی‌اپِ ربات (عیناً) ════════════ */
/* ═══ کارشناسی هوشمند قیمت آیفون — مینی‌اپ (بخش ۲۲ CLAUDE.md) ═══
   موتور مرحله‌ای شرطی سمت کلاینت — دقیقاً همون منطق bot.py (_IV_STEP_ORDER +
   _iv_step_skip)، پورت‌شده به JS تا ویزارد به‌جای پیام‌به‌پیام تلگرام، یک تجربهٔ
   تک‌صفحه‌ای/تدریجی و پریمیوم باشه. محاسبهٔ قیمت واقعی همیشه سمت سرور می‌مونه
   (POST /iphone/valuate) — این‌جا فقط پیمایش فرم + رندر نتیجه‌ست. کلید تازهٔ
   iv_colors.hex_color (این نشست) برای سواچ رنگ واقعی استفاده می‌شه. */
/* ⚠️ «reg» (وضعیت رجیستری) عمداً کنار grade و بلافاصله بعد از فاز دستگاه است — چون
   از بخش ۱۲۴ به بعد رجیستری هم مثل رنگ/پارت/سری اصالت یه بعد *ردیف قیمت* است، پس
   باید قبل از resolve پرسیده بشه (بخش ۱۲۴ CLAUDE.md). */
var _IV_STEP_ORDER=["series","model","capacity","color","part","grade","reg",
  "cond","batt","cos","cable","box","feat","repair","defms","repms","city","summary"];
var _IV_PRE_RESOLVE_STEPS={series:1,model:1,capacity:1,color:1,part:1,grade:1,reg:1};
var _IV_COEFF_STEP_MAP={
  grade:["grade","🏷 این آیفون کدوم سری اصالته؟"],
  cond:["condition","🔧 وضعیت کلی دستگاه چطوره؟"],
  batt:["battery","🔋 سلامت باتری چقدره؟"],
  repair:["repair","🛠 وضعیت باز شدن یا تعمیرات؟"],
  reg:["registry","📋 وضعیت مالکیت دستگاه چطوره؟"],
  box:["box","📦 جعبه و لوازم چطوره؟"],
  cos:["cosmetic","✨ وضعیت ظاهری دستگاه؟"],
  cable:["cable","🔌 کابل شارژ چطوره؟"],
};
var _IV_MULTI_STEP_MAP={
  defms:["component","🩹 کدوم قسمت(ها) خرابه؟"],
  repms:["replaced","🔁 کدوم قطعه(ها) قبلاً تعویض شده (غیراصلی ولی سالم)؟"],
};
var _IV_GRADE_STOP_MSG={
  grade_p:"📦 گوشی شما جزو گوشی‌های تغییر داده‌شده و ارتقایافته است.\nقیمت این‌گونه دستگاه‌ها توافقی است.",
  grade_3:"🧪 گوشی شما جزو دستگاه‌های نمونهٔ تست اپل است و امکان مکالمه ندارد.\nقیمت این‌گونه دستگاه‌ها توافقی است.",
  grade_4:"⚠️ گوشی شما از پارت‌های نامشخص و بدون گارانتی اپل است.\nقیمت این‌گونه دستگاه‌ها توافقی است.",
};
var _IV_VERDICT_COLOR={'🟢':'#22C55E','🟡':'#F59E0B','🔴':'#EF4444'};

var _ivS=null;
function _ivFreshState(){
  return {data:null,step:null,history:[],series_id:null,series_name:'',model_id:null,model:null,
    capacity_label:'',capacity_id:null,color:'',part_number:'',selections:{},ms_selected:{},
    features_ok:null,city:''};
}
function _ivColorsFor(modelId){return (_ivS.data.colors[String(modelId)]||[])}
function _ivStoragesFor(modelId){return (_ivS.data.storages[String(modelId)]||[])}
function _ivModelById(id){var l=_ivS.data.models||[];for(var i=0;i<l.length;i++)if(l[i].id===id)return l[i];return null}
function _ivCoeffOptions(cat){return (_ivS.data.coefficients[cat]||[])}
function _ivHaptic(){try{var tg=window._slTg;if(tg&&tg.HapticFeedback&&tg.HapticFeedback.selectionChanged)tg.HapticFeedback.selectionChanged()}catch(e){}}

function _ivStepSkip(stepId){
  var s=_ivS;
  if(stepId==='color')return !_ivColorsFor(s.model_id).length;
  if(stepId==='part'){var m=_ivModelById(s.model_id);return !(m&&(m.dual_sim_parts||'').trim())}
  /* مرحله‌ای که از پل «سلامت آیفون» با جواب مطمئن پر شده — دوباره پرسیده نمی‌شه
     (پورت دقیق همون شرط bot.py:_iv_step_skip روی state["health_prefilled"]). */
  if(s.health_prefilled&&s.health_prefilled[stepId])return true;
  var sel=s.selections||{};
  var newSealed=sel.condition==='cond_new_sealed';
  if(newSealed&&['batt','cos','cable','box','feat','repair','defms','repms'].indexOf(stepId)>=0)return true;
  if(stepId==='repair')return s.features_ok!==false;
  if(stepId==='defms')return s.features_ok!==false||sel.repair!=='repair_none';
  if(stepId==='repms')return s.features_ok!==false||['repair_opened','repair_board'].indexOf(sel.repair)<0;
  if(_IV_COEFF_STEP_MAP[stepId])return !_ivCoeffOptions(_IV_COEFF_STEP_MAP[stepId][0]).length;
  if(_IV_MULTI_STEP_MAP[stepId])return !_ivCoeffOptions(_IV_MULTI_STEP_MAP[stepId][0]).length;
  return false;
}
function _ivPeekNext(stepId){
  var i=_IV_STEP_ORDER.indexOf(stepId),nxt=(i+1<_IV_STEP_ORDER.length)?_IV_STEP_ORDER[i+1]:null;
  while(nxt!==null&&_ivStepSkip(nxt)){
    i=_IV_STEP_ORDER.indexOf(nxt);nxt=(i+1<_IV_STEP_ORDER.length)?_IV_STEP_ORDER[i+1]:null;
  }
  return nxt;
}
function _ivGoto(stepId,fromBack){
  var s=_ivS;if(!s)return;
  while(stepId!==null&&_ivStepSkip(stepId)){
    var i=_IV_STEP_ORDER.indexOf(stepId);stepId=(i+1<_IV_STEP_ORDER.length)?_IV_STEP_ORDER[i+1]:null;
  }
  if(stepId===null)return _ivFinalize();
  if(!fromBack&&s.step)s.history.push(s.step);
  if(!_IV_PRE_RESOLVE_STEPS[stepId]&&!s.capacity_id){
    _ivResolveCapacity(function(ok){if(ok){s.step=stepId;_ivRenderStep(stepId)}});
    return;
  }
  s.step=stepId;
  _ivRenderStep(stepId);
}
function _ivStepDone(){var nxt=_ivPeekNext(_ivS.step);_ivGoto(nxt)}
function _ivBack(){
  var s=_ivS;if(!s||!s.history.length){_closeIVWizard();return}
  var prev=s.history.pop();_ivGoto(prev,true);
}
function _closeIVWizard(){
  var pb=document.getElementById('post-body');if(pb)pb.classList.remove('sl-iv-host');
  window._slApp.popup.close('#post-popup');
}
function _ivResolveCapacity(cb){
  var s=_ivS;
  var q='?model_id='+s.model_id+'&capacity_label='+encodeURIComponent(s.capacity_label)+
    '&part_number='+encodeURIComponent(s.part_number||'')+'&color='+encodeURIComponent(s.color||'')+
    '&grade='+encodeURIComponent((s.selections||{}).grade||'')+
    '&registry='+encodeURIComponent((s.selections||{}).registry||'');
  api('/iphone/resolve'+q,false).then(function(d){
    if(!d||!d.ok||!d.capacity){_ivRenderBlocked((d&&d.error)||'قیمتی برای این ترکیب هنوز در پنل ثبت نشده. با پشتیبانی هماهنگ کن.');cb(false);return}
    s.capacity_id=d.capacity.id;cb(true);
  }).catch(function(){_ivRenderBlocked('خطا در دریافت قیمت — دوباره امتحان کن.');cb(false)});
}
function _ivRenderBlocked(msg){
  var b=_accBody();if(!b)return;
  b.innerHTML='<div class="sl-iv"><div class="sl-iv-body"><div class="sl-empty" style="padding:70px 16px;color:var(--iv-mu,#8E8E93)">'+
    '<span class="sl-empty-e">⚠️</span>'+esc(msg)+'</div>'+
    '<div class="sl-iv-footer"><button class="sl-iv-cta" id="iv-blocked-close">بستن</button></div></div></div>';
  var btn=document.getElementById('iv-blocked-close');if(btn)btn.addEventListener('click',_closeIVWizard);
}

/* ═══ نقطهٔ ورود ═══ */
function openIVWizard(bridge){
  _slWizTop(null);
  /* `bridge` (اختیاری) از پل «سلامت آیفون» میاد: {model_id, capacity_label, prefill}
     — با اون، مرحله‌های دستگاه و هر چیزی که تست سلامت قبلاً مطمئن جوابش رو گرفته
     رد می‌شن. prefill همیشه از سرور محاسبه می‌شه (engine.derive_valuation_prefill)،
     نه سمت کلاینت، تا ربات و مینی‌اپ دقیقاً یک منطق داشته باشن. */
  _accPopup('کارشناسی آیفون',skel(3));
  var pb=document.getElementById('post-body');if(pb)pb.classList.add('sl-iv-host');
  api('/iphone/wizard-data',false).then(function(d){
    if(!d||!d.ok){var b=_accBody();if(b)b.innerHTML=err('خطا در دریافت اطلاعات');return}
    _ivS=_ivFreshState();_ivS.data=d;
    if(bridge&&bridge.model_id){
      var m=_ivModelById(bridge.model_id);
      if(m){
        _ivS.model_id=m.id;_ivS.model=m;_ivS.series_id=m.series_id;
        _ivS.health_prefilled={};
        var carried=[];
        /* حافظه‌ی بی‌قیمت در سایت جابه‌جا نشود؛ آن‌وقت ظرفیت دوباره پرسیده می‌شود (سایت فقط حافظه‌های قیمت‌دار را دارد) */
        if(bridge.capacity_label&&_ivStoragesFor(m.id).some(function(x){return x.label===bridge.capacity_label})){
          _ivS.capacity_label=bridge.capacity_label;
          _ivS.health_prefilled.capacity=1;carried.push('💾 ظرفیت');
        }
        var pf=bridge.prefill||{};
        if(pf.repair){_ivS.selections.repair=pf.repair;_ivS.health_prefilled.repair=1;carried.push('🛠 وضعیت باز شدن/تعمیرات')}
        if(pf.features_ok!=null){_ivS.features_ok=!!pf.features_ok;_ivS.health_prefilled.feat=1;carried.push('📶 سلامت امکانات')}
        _ivS.bridge_note=carried.length?('بر اساس نتیجهٔ تست سلامت، این موارد دوباره پرسیده نمی‌شن: '+carried.join('، ')):'';
        return _ivGoto('capacity');
      }
    }
    _ivGoto('series');
  }).catch(function(){var b=_accBody();if(b)b.innerHTML=err('خطا در دریافت اطلاعات')});
}
window.openIVWizard=openIVWizard;

function _ivShell(title,bodyHtml){
  var s=_ivS,total=_IV_STEP_ORDER.length,idx=_IV_STEP_ORDER.indexOf(s.step);
  var pct=Math.max(6,Math.round(((idx+1)/total)*100));
  var b=_accBody();if(!b)return;
  b.innerHTML='<div class="sl-iv">'+
    '<div class="sl-iv-top">'+
      '<button class="sl-iv-back'+(s.history.length?'':' hide')+'" id="iv-back"><span>مرحله قبل</span><span class="sl-iv-back-ic">›</span></button>'+
      '<div class="sl-iv-bar"><div class="sl-iv-bar-f" style="width:'+pct+'%"></div></div>'+
    '</div>'+
    '<div class="sl-iv-body">'+(title?'<div class="sl-iv-h1">'+esc(title)+'</div>':'')+
      (s.bridge_note?'<div class="sl-ih-ev auto" style="display:flex;margin:0 auto 12px">✅ '+esc(s.bridge_note)+'</div>':'')+
      bodyHtml+'</div>'+
  '</div>';
  var backBtn=document.getElementById('iv-back');if(backBtn)backBtn.addEventListener('click',_ivBack);
  _slWizTop('iv:'+s.step);
}

/* ── مراحل فاز انتخاب دستگاه ── (بازطراحی ظاهری Act 1/2 — کارت‌های .sl-iv-devcard،
   مستقل از .sl-iv-opt که بقیهٔ مراحل ازش استفاده می‌کنن؛ منطق انتخاب/state/ناوبری
   زیر عیناً همون قبلیه، فقط قالب HTML/کلاس‌های نمایشی عوض شده) */
var _IV_DEV_CHECK_SVG='<span class="sl-iv-devcard-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>';
var _IV_DEV_ARROW_SVG='<span class="sl-iv-devcard-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></span>';
function _ivDevCard(item,sel,dataAttr,yr){
  return '<div class="sl-iv-devcard'+(sel?' sel':'')+'" data-'+dataAttr+'="'+item.id+'">'+_IV_DEV_CHECK_SVG+
    ivImgHtml(item)+'<span class="sl-iv-devcard-t">'+esc(item.name)+'</span>'+
    (yr?'<span class="sl-iv-devcard-y">سال '+esc(yr)+'</span>':'')+_IV_DEV_ARROW_SVG+'</div>';
}
function _ivStepSeries(){
  var list=_ivS.data.series||[];
  if(!list.length){_ivShell('','<div class="sl-empty" style="padding:70px 16px"><span class="sl-empty-e">📱</span>هنوز مدل/قیمتی برای کارشناسی در پنل تعریف نشده.</div>');return}
  var html='<div class="sl-iv-headline"><div class="sl-iv-headline-t">کارشناس هوشمند قیمت آیفون</div>'+
    '<div class="sl-iv-headline-s">نسل دستگاهت رو انتخاب کن</div></div>'+
    '<div class="sl-iv-devgrid">'+list.map(function(sr){
      return _ivDevCard(sr,sr.id===_ivS.series_id,'series');
    }).join('')+'</div>';
  _ivShell('',html);
  document.querySelectorAll('[data-series]').forEach(function(el){
    el.addEventListener('click',function(){
      _ivHaptic();
      var sid=+el.dataset.series,sr=list.filter(function(x){return x.id===sid})[0];
      _ivS.series_id=sid;_ivS.series_name=sr?sr.name:'';
      _ivS.model_id=null;_ivS.model=null;_ivS.capacity_label='';_ivS.capacity_id=null;
      _ivS.color='';_ivS.part_number='';_ivS.selections={};_ivS.ms_selected={};_ivS.features_ok=null;
      _ivStepDone();
    });
  });
}
function _ivStepModel(){
  var all=_ivS.data.models||[],list=all.filter(function(m){return m.series_id===_ivS.series_id});
  if(!list.length){_ivShell(_ivS.series_name,'<div class="sl-empty" style="padding:70px 16px"><span class="sl-empty-e">📱</span>برای این نسل هنوز مدلی تعریف نشده.</div>');return}
  var html='<div class="sl-iv-headline">'+
    (_ivS.series_name?'<span class="sl-iv-headline-ctx">'+esc(_ivS.series_name)+'</span>':'')+
    '<div class="sl-iv-headline-t">مدل دستگاهت چیه؟</div>'+
    '<div class="sl-iv-headline-s">مدل دقیق آیفون خودت رو انتخاب کن تا ادامه بدیم.</div></div>'+
    '<div class="sl-iv-devgrid">'+list.map(function(m){
      return _ivDevCard(m,m.id===_ivS.model_id,'model',(m.series||'').trim());
    }).join('')+'</div>';
  _ivShell('',html);
  document.querySelectorAll('[data-model]').forEach(function(el){
    el.addEventListener('click',function(){
      _ivHaptic();
      var mid=+el.dataset.model;
      _ivS.model_id=mid;_ivS.model=list.filter(function(x){return x.id===mid})[0];
      _ivS.capacity_label='';_ivS.capacity_id=null;_ivS.color='';_ivS.part_number='';
      _ivS.selections={};_ivS.ms_selected={};_ivS.features_ok=null;
      _ivStepDone();
    });
  });
}
function _ivStepCapacity(){
  var list=_ivStoragesFor(_ivS.model_id);
  if(!list.length){_ivShell(_ivS.model.name,'<div class="sl-empty" style="padding:70px 16px"><span class="sl-empty-e">💾</span>برای این مدل هنوز ظرفیتی تعریف نشده.</div>');return}
  var html='<div class="sl-iv-sub">💾 ظرفیت حافظه؟</div><div class="sl-iv-grid sl-iv-grid-3">'+
    list.map(function(c){return '<div class="sl-iv-opt sl-iv-pill" data-cap="'+esc(c.label)+'">'+esc(c.label)+'</div>'}).join('')+'</div>';
  _ivShell(_ivS.model.name,html);
  document.querySelectorAll('[data-cap]').forEach(function(el){
    el.addEventListener('click',function(){_ivHaptic();_ivS.capacity_label=el.dataset.cap;_ivS.capacity_id=null;_ivStepDone()});
  });
}
function _ivStepColor(){
  var list=_ivColorsFor(_ivS.model_id);
  var html='<div class="sl-iv-sub">🎨 رنگ دستگاه؟</div><div class="sl-iv-grid sl-iv-grid-3">'+
    list.map(function(c){
      var hex=c.hex_color||'#8E8E93';
      return '<div class="sl-iv-opt sl-iv-color" data-color="'+esc(c.name)+'">'+
        '<span class="sl-iv-color-sw" style="background:'+esc(hex)+'"></span><span class="sl-iv-opt-t">'+esc(c.name)+'</span></div>';
    }).join('')+'</div>';
  _ivShell(_ivS.model.name,html);
  document.querySelectorAll('[data-color]').forEach(function(el){
    el.addEventListener('click',function(){_ivHaptic();_ivS.color=el.dataset.color;_ivS.capacity_id=null;_ivStepDone()});
  });
}
/* ── پارت‌نامبر: سه پارت اول مستقیم، بقیه زیر «سایر» ──
   🔴 گزینهٔ بی‌نام هرگز رندر نمی‌شود؛ یک ردیف خالی در کاتالوگ تولید به‌شکل یک
   دکمهٔ سفید بی‌متن دیده می‌شد. سرور هم همان را فیلتر می‌کند (دفاع در عمق). */
var _IV_PART_PRIMARY=3;
function _ivStepPart(){
  var all=(_ivS.data.parts||[]).filter(function(p){
    return p && String(p.code||'').trim() && String(p.label||'').trim();
  });
  var head=all.slice(0,_IV_PART_PRIMARY),rest=all.slice(_IV_PART_PRIMARY);
  function pick(code){
    _ivHaptic();_ivS.part_number=code;_ivS.capacity_id=null;_ivStepDone();
  }
  function draw(showRest){
    var list=showRest?rest:head;
    var html='<div class="sl-iv-sub">🔠 پارت نامبر دستگاه؟</div>'+
      '<div class="sl-iv-hint">'+(showRest
        ? 'اگر پارت دستگاهت این‌جا هم نیست، «نمی‌دونم» رو بزن.'
        : 'تنظیمات ‹ عمومی ‹ اطلاعات دستگاه، یا پشت جعبه')+'</div>'+
      '<div class="sl-iv-grid sl-iv-grid-3">'+
      list.map(function(p){
        return '<div class="sl-iv-opt sl-iv-pill" data-part="'+esc(p.code)+'">'+esc(p.label)+'</div>';
      }).join('')+
      (showRest?'<div class="sl-iv-opt sl-iv-pill" data-part="">نمی‌دونم</div>':'')+
      '</div>'+
      (showRest
        ? '<button class="sl-iv-cta-ghost" id="iv-part-back" style="width:100%;margin-top:12px">‹ بازگشت</button>'
        : '<button class="sl-iv-cta-ghost" id="iv-part-more" style="width:100%;margin-top:12px">'+
          (rest.length?'سایر پارت‌ها':'نمی‌دونم')+'</button>');
    _ivShell(_ivS.model.name,html);
    document.querySelectorAll('[data-part]').forEach(function(el){
      el.addEventListener('click',function(){pick(el.dataset.part)});
    });
    var more=document.getElementById('iv-part-more');
    if(more)more.addEventListener('click',function(){
      _ivHaptic();
      if(rest.length)draw(true); else pick('');
    });
    var bk=document.getElementById('iv-part-back');
    if(bk)bk.addEventListener('click',function(){_ivHaptic();draw(false)});
  }
  draw(false);
}

/* ── مرحلهٔ «سری اصالت» — تنها مرحله‌ای که گزینه‌هاش از سرور و **per-model** میان ──
   🔴 هیچ سری‌ای صرفاً چون در کاتالوگ سراسری هست نمایش داده نمی‌شه؛ سرور با همون
   resolve_capacity موتور قیمت تصمیم می‌گیره کدوم سری برای این مدل دیتا داره.
   P/۳/۴ (توافقی) همیشه زیر «سری‌های خاص» می‌مونن، ۵ فقط اگه دیتا داشته باشه. */
function _ivStepGrade(){
  var cat='grade';
  _ivShell(_ivS.model?_ivS.model.name:'',skel(2));
  var q='/iphone/grades?model_id='+encodeURIComponent(_ivS.model.id)+
        '&capacity_label='+encodeURIComponent(_ivS.capacity_label||'')+
        '&part_number='+encodeURIComponent(_ivS.part_number||'')+
        '&color='+encodeURIComponent(_ivS.color||'');
  api(q).then(function(d){
    var main=(d&&d.main)||[],special=(d&&d.special)||[];
    function pick(key){
      _ivHaptic();
      _ivS.selections[cat]=key;
      _ivS.capacity_id=null;
      if((_ivS.data.grade_stop_keys||[]).indexOf(key)>=0)return _ivGradeStop(key);
      _ivStepDone();
    }
    function draw(showSpecial){
      var list=showSpecial?special:main;
      var html='<div class="sl-iv-sub">'+
        (showSpecial?'🏷 سری‌های خاص':_IV_COEFF_STEP_MAP.grade[1])+'</div>'+
        (showSpecial?'<div class="sl-iv-hint">این سری‌ها قیمت‌گذاری عادی ندارند و توافقی‌اند.</div>':'')+
        (!showSpecial&&!main.length?'<div class="sl-iv-hint">برای این مدل هنوز قیمت سری‌های اصلی ثبت نشده.</div>':'')+
        '<div class="sl-iv-grid">'+list.map(function(o){
          return '<div class="sl-iv-opt sl-iv-opt-w" data-gopt="'+esc(o.option_key)+'">'+esc(o.option_label)+'</div>';
        }).join('')+'</div>'+
        (showSpecial
          ? '<button class="sl-iv-cta-ghost" id="iv-grade-back" style="width:100%;margin-top:12px">‹ بازگشت</button>'
          : (special.length?'<button class="sl-iv-cta-ghost" id="iv-grade-more" style="width:100%;margin-top:12px">🔻 سری‌های خاص</button>':''));
      _ivShell(_ivS.model?_ivS.model.name:'',html);
      document.querySelectorAll('[data-gopt]').forEach(function(el){
        el.addEventListener('click',function(){pick(el.dataset.gopt)});
      });
      var more=document.getElementById('iv-grade-more');
      if(more)more.addEventListener('click',function(){_ivHaptic();draw(true)});
      var bk=document.getElementById('iv-grade-back');
      if(bk)bk.addEventListener('click',function(){_ivHaptic();draw(false)});
    }
    draw(false);
  }).catch(function(){_ivShell(_ivS.model?_ivS.model.name:'',err('خطا در دریافت سری‌های اصالت'))});
}

/* ── مراحل ضریب تک‌انتخابی (cond/batt/cos/cable/box/reg/repair) ── */
function _ivStepCoeff(stepId){
  if(stepId==='grade')return _ivStepGrade();
  var cat=_IV_COEFF_STEP_MAP[stepId][0],title=_IV_COEFF_STEP_MAP[stepId][1];
  var opts=_ivCoeffOptions(cat);
  var html='<div class="sl-iv-sub">'+title+'</div><div class="sl-iv-grid">'+
    opts.map(function(o){return '<div class="sl-iv-opt sl-iv-opt-w" data-opt="'+esc(o.option_key)+'">'+esc(o.option_label)+'</div>'}).join('')+'</div>';
  _ivShell(_ivS.model?_ivS.model.name:'',html);
  document.querySelectorAll('[data-opt]').forEach(function(el){
    el.addEventListener('click',function(){
      _ivHaptic();
      var key=el.dataset.opt;
      _ivS.selections[cat]=key;
      /* سری اصالت و رجیستری هر دو بعد ردیف قیمت‌ان — انتخابشون capacity_id قبلی رو
         باطل می‌کنه تا با مقدار تازه دوباره resolve بشه. */
      if(stepId==='grade'||stepId==='reg')_ivS.capacity_id=null;
      if(stepId==='grade'&&(_ivS.data.grade_stop_keys||[]).indexOf(key)>=0)return _ivGradeStop(key);
      _ivStepDone();
    });
  });
}
function _ivStepMulti(stepId){
  var cat=_IV_MULTI_STEP_MAP[stepId][0],title=_IV_MULTI_STEP_MAP[stepId][1];
  var opts=_ivCoeffOptions(cat);
  var sel=_ivS.ms_selected[stepId];
  if(!sel){sel=_ivToSet(_ivS.selections[cat]||[]);_ivS.ms_selected[stepId]=sel}
  function draw(){
    var optsHtml=opts.map(function(o){
      var on=_ivSetHas(sel,o.option_key);
      return '<div class="sl-iv-opt sl-iv-opt-w'+(on?' sel':'')+'" data-ms="'+esc(o.option_key)+'"><span class="sl-iv-chk">'+
        (on?'✅':'⬜️')+'</span> '+esc(o.option_label)+'</div>';
    }).join('');
    var html='<div class="sl-iv-sub">'+title+'</div><div class="sl-iv-grid">'+optsHtml+'</div>'+
      '<div class="sl-iv-footer"><button class="sl-iv-cta" id="iv-ms-done">✅ تأیید و ادامه</button></div>';
    _ivShell(_ivS.model?_ivS.model.name:'',html);
    document.getElementById('iv-ms-done').addEventListener('click',function(){_ivS.selections[cat]=_ivSetValues(sel);_ivStepDone()});
    document.querySelectorAll('[data-ms]').forEach(function(el){
      el.addEventListener('click',function(){_ivHaptic();var k=el.dataset.ms;if(_ivSetHas(sel,k))_ivSetDel(sel,k);else _ivSetAdd(sel,k);draw()});
    });
  }
  draw();
}
/* Set جایگزین سبک — برای پشتیبانی موتورهای WebView قدیمی‌تر که Set درست ندارن (احتیاط) */
function _ivToSet(arr){var o={};(arr||[]).forEach(function(k){o[k]=1});return o}
function _ivSetHas(o,k){return !!o[k]}
function _ivSetAdd(o,k){o[k]=1}
function _ivSetDel(o,k){delete o[k]}
function _ivSetValues(o){return Object.keys(o)}

function _ivStepFeat(){
  var html='<div class="sl-iv-sub">📶 همهٔ امکانات دستگاه (Face ID، True Tone، دوربین، اسپیکر، میکروفون، وای‌فای، بلوتوث، NFC، MagSafe، شارژ بی‌سیم) سالمن؟</div>'+
    '<div class="sl-iv-yn"><button class="sl-iv-yn-btn yes" id="iv-feat-yes">✅ بله، همه سالمه</button>'+
    '<button class="sl-iv-yn-btn no" id="iv-feat-no">❌ خیر، مشکل دارم</button></div>';
  _ivShell(_ivS.model?_ivS.model.name:'',html);
  document.getElementById('iv-feat-yes').addEventListener('click',function(){_ivHaptic();_ivS.features_ok=true;_ivStepDone()});
  document.getElementById('iv-feat-no').addEventListener('click',function(){_ivHaptic();_ivS.features_ok=false;_ivStepDone()});
}
function _ivStepCity(){
  var html='<div class="sl-iv-sub">🏙 شهرت رو بنویس (اختیاری)</div>'+
    '<input type="text" class="sl-iv-input" id="iv-city-input" placeholder="مثلاً تهران" value="'+esc(_ivS.city||'')+'">'+
    '<div class="sl-iv-footer"><button class="sl-iv-cta" id="iv-city-go">ادامه</button>'+
    '<button class="sl-iv-skip" id="iv-city-skip">ندارم — رد کن</button></div>';
  _ivShell(_ivS.model?_ivS.model.name:'',html);
  document.getElementById('iv-city-go').addEventListener('click',function(){_ivS.city=(document.getElementById('iv-city-input').value||'').trim();_ivStepDone()});
  document.getElementById('iv-city-skip').addEventListener('click',function(){_ivS.city='';_ivStepDone()});
}
function _ivStepSummary(){
  var html='<div class="sl-iv-summary-ic">📋</div>'+
    '<div class="sl-iv-summary-t">خلاصهٔ کارشناسی آماده است</div>'+
    '<div class="sl-iv-summary-s">برای دیدن قیمت نهایی «محاسبهٔ قیمت» رو بزن.</div>'+
    '<div class="sl-iv-footer" style="margin-top:26px"><button class="sl-iv-cta" id="iv-sum-go">✅ محاسبهٔ قیمت</button>'+
    '<button class="sl-iv-skip" id="iv-sum-cancel">❌ انصراف</button></div>';
  _ivShell('',html);
  document.getElementById('iv-sum-go').addEventListener('click',_ivFinalize);
  document.getElementById('iv-sum-cancel').addEventListener('click',_closeIVWizard);
}
function _ivRenderStep(stepId){
  if(stepId==='series')return _ivStepSeries();
  if(stepId==='model')return _ivStepModel();
  if(stepId==='capacity')return _ivStepCapacity();
  if(stepId==='color')return _ivStepColor();
  if(stepId==='part')return _ivStepPart();
  if(_IV_COEFF_STEP_MAP[stepId])return _ivStepCoeff(stepId);
  if(_IV_MULTI_STEP_MAP[stepId])return _ivStepMulti(stepId);
  if(stepId==='feat')return _ivStepFeat();
  if(stepId==='city')return _ivStepCity();
  if(stepId==='summary')return _ivStepSummary();
}

/* ═══ محاسبهٔ نهایی + Act 3 (Reveal) ═══ */
function _ivActiveSelections(){
  var out={};
  /* ⚠️ استثنای health_prefilled: دسته‌ای که از پل «سلامت آیفون» پر شده همیشه
     در محاسبه لحاظ می‌شه، صرف‌نظر از این‌که skip=true هست (که همیشه هست، چون
     دقیقاً به همین دلیل دیگه پرسیده نمی‌شه). بدون این استثنا، مقدارش بی‌صدا از
     قیمت نهایی حذف می‌شد — همون باگی که سمت ربات هم پیدا و رفع شد. */
  var hp=_ivS.health_prefilled||{};
  Object.keys(_IV_COEFF_STEP_MAP).forEach(function(stepId){
    var cat=_IV_COEFF_STEP_MAP[stepId][0];
    if(_ivS.selections[cat]!=null&&(hp[stepId]||!_ivStepSkip(stepId)))out[cat]=_ivS.selections[cat];
  });
  Object.keys(_IV_MULTI_STEP_MAP).forEach(function(stepId){
    var cat=_IV_MULTI_STEP_MAP[stepId][0];
    if(_ivS.selections[cat]!=null&&!_ivStepSkip(stepId))out[cat]=_ivS.selections[cat];
  });
  return out;
}
function _ivSimType(){
  var m=_ivS.model||{};
  if(m.esim_only)return 'esim';
  var dual=(m.dual_sim_parts||'').split(',').map(function(x){return x.trim().toUpperCase()}).filter(Boolean);
  // dual_sim_parts کد کوتاه («ZA»)، ولی کد پارت کامله («ZA/A») — با پیشوندِ قبل از «/» هم چک کن.
  var pc=(_ivS.part_number||'').toUpperCase();
  if(dual.indexOf(pc)>=0||dual.indexOf(pc.split('/')[0])>=0)return 'dual';
  return 'single';
}
function _ivFinalize(){
  var b=_accBody();if(b)b.innerHTML='<div class="sl-iv"><div class="sl-iv-body" style="padding-top:40px">'+skel(3)+'</div></div>';
  var payload={model_id:_ivS.model_id,capacity_id:_ivS.capacity_id,part_number:_ivS.part_number||'',
    color:_ivS.color||'',selections:_ivActiveSelections(),features_ok:_ivS.features_ok,
    sim_type:_ivSimType(),city:_ivS.city||''};
  var headers={'Content-Type':'application/json'};if(initData)headers['X-Telegram-Init-Data']=initData;
  fetch('/api/v1/iphone/valuate',{method:'POST',headers:headers,body:JSON.stringify(payload)})
    .then(function(r){return r.json()}).then(function(d){
      if(d&&d.ok&&d.result){_ivRenderReveal(d.result);return}
      _ivRenderBlocked((d&&(d.detail||d.error))||'خطا در محاسبهٔ قیمت — دوباره امتحان کن.');
    }).catch(function(){_ivRenderBlocked('خطای شبکه — دوباره امتحان کن.')});
}
var _IV_GRADE_STOP_KEYSET=null;
function _ivGradeStop(gradeKey){
  var msg=_IV_GRADE_STOP_MSG[gradeKey]||'قیمت این دستگاه توافقی است.';
  _ivRenderNegotiated(_ivS.model.name,msg,gradeKey);
}
function _ivRenderNegotiated(modelName,msgText,gradeKey){
  var b=_accBody();if(!b)return;
  b.innerHTML='<div class="sl-iv sl-iv-reveal"><div class="sl-iv-cert" style="text-align:center">'+
    '<div class="sl-iv-cert-glow"></div>'+
    '<div class="sl-iv-cert-brand">Stland · کارشناسی هوشمند</div>'+
    '<div class="sl-iv-cert-model">'+esc(modelName)+'</div>'+
    '<div class="sl-iv-report" style="margin-top:14px;border-top:none;padding-top:0">'+esc(msgText).replace(/\n/g,'<br>')+'</div>'+
    '</div><div class="sl-iv-cta-col">'+
    '<button class="sl-iv-cta primary" id="iv-sell-btn">🤝 می‌خوام بفروشم</button>'+
    '<button class="sl-iv-cta-ghost" id="iv-newcalc-btn">🔄 کارشناسی تازه</button>'+
    '</div></div>';
  document.getElementById('iv-sell-btn').addEventListener('click',function(){
    _ivOpenSellForm({model_name:modelName,fair_price:null,grade_key:gradeKey||''});
  });
  document.getElementById('iv-newcalc-btn').addEventListener('click',openIVWizard);
}
function _ivCountUp(el,target,duration){
  var startTime=null;
  function step(ts){
    if(!startTime)startTime=ts;
    var p=Math.min(1,(ts-startTime)/duration),eased=1-Math.pow(1-p,3);
    el.textContent=fmt(Math.round(target*eased));
    if(p<1)requestAnimationFrame(step);else el.textContent=fmt(target);
  }
  requestAnimationFrame(step);
}
function _ivScoreGaugeSvg(score,color){
  var r=27.5,c=2*Math.PI*r,off=c*(1-Math.max(0,Math.min(100,score))/100);
  return '<svg viewBox="0 0 64 64" class="sl-iv-gauge-svg">'+
    '<circle cx="32" cy="32" r="'+r+'" class="sl-iv-gauge-bg"></circle>'+
    '<circle cx="32" cy="32" r="'+r+'" class="sl-iv-gauge-fg" style="stroke:'+color+';stroke-dasharray:'+c+';stroke-dashoffset:'+c+'" data-target-offset="'+off+'"></circle>'+
    '</svg>';
}
function _ivRenderReveal(result){
  var b=_accBody();if(!b)return;
  var vColor=_IV_VERDICT_COLOR[result.verdict_emoji]||'#38BDF8';
  var simLabels={single:'تک سیم',dual:'دو سیم',esim:'eSIM'};
  var simLabel=simLabels[result.sim_type]||'—';
  var aiHtml='';
  if(result.ai){
    var ai=result.ai,trendLabels={bullish:'📈 صعودی',bearish:'📉 نزولی',stable:'➖ ثابت'},
      riskLabels={low:'🟢 پایین',medium:'🟡 متوسط',high:'🔴 بالا'};
    aiHtml='<div class="sl-iv-ai"><div class="sl-iv-ai-h">🤖 تحلیل هوش مصنوعی</div>'+
      (ai.adjustment_percent?'<div class="sl-iv-ai-row">قیمت اصلاح‌شده: <b>'+fmt(ai.final_price)+'</b> تومان ('+(ai.adjustment_percent>0?'+':'')+faNum(String(ai.adjustment_percent))+'٪)</div>':'')+
      (trendLabels[ai.market_trend]?'<div class="sl-iv-ai-row">روند بازار: '+trendLabels[ai.market_trend]+'</div>':'')+
      (riskLabels[ai.risk]?'<div class="sl-iv-ai-row">ریسک: '+riskLabels[ai.risk]+'</div>':'')+
      (ai.reason?'<div class="sl-iv-ai-row">💬 '+esc(ai.reason)+'</div>':'')+
      ((ai.warnings||[]).map(function(w){return '<div class="sl-iv-ai-warn">⚠️ '+esc(w)+'</div>'}).join(''))+
      '</div>';
  }
  b.innerHTML='<div class="sl-iv sl-iv-reveal">'+
    '<div class="sl-iv-cert" id="iv-cert">'+
      '<div class="sl-iv-cert-glow"></div>'+
      '<div class="sl-iv-cert-brand">Stland · کارشناسی هوشمند</div>'+
      '<div class="sl-iv-cert-model">'+esc(result.model_name)+' '+esc(result.capacity_label)+'</div>'+
      '<div class="sl-iv-cert-meta">'+esc(result.color||'—')+' · '+esc(simLabel)+'</div>'+
      '<div class="sl-iv-tier">'+
        '<div class="sl-iv-tier-l">📊 قیمت مرجع بازار</div>'+
        '<div class="sl-iv-tier-s">دستگاه مشابه، بدون باز شدگی، در حد نو، بالاترین باتری</div>'+
        '<div class="sl-iv-tier-v">💰 <span>'+fmt(result.market_price)+'</span><small>تومان</small></div>'+
      '</div>'+
      '<div class="sl-iv-div"></div>'+
      '<div class="sl-iv-tier main">'+
        '<div class="sl-iv-tier-l">⚖️ قیمت منصفانه (برای دستگاه شما)</div>'+
        '<div class="sl-iv-tier-v big">💰 <span id="iv-fair-num">۰</span><small>تومان</small></div>'+
      '</div>'+
      '<div class="sl-iv-tier offer">'+
        '<div class="sl-iv-tier-l">🏬 پیشنهاد نقدی Stland</div>'+
        '<div class="sl-iv-tier-v">💰 <span id="iv-buy-num">۰</span><small>تومان</small></div>'+
      '</div>'+
      '<div class="sl-iv-score-row">'+
        '<div class="sl-iv-gauge">'+_ivScoreGaugeSvg(result.score,vColor)+'<div class="sl-iv-gauge-num">'+faNum(String(result.score))+'</div></div>'+
        '<div class="sl-iv-verdict" style="color:'+vColor+'">'+result.verdict_emoji+' '+esc(result.verdict_text)+'</div>'+
      '</div>'+
      (result.report_text?'<div class="sl-iv-report">📝 '+esc(result.report_text)+'</div>':'')+
    '</div>'+
    aiHtml+
    '<div class="sl-iv-cta-col">'+
      '<button class="sl-iv-cta primary" id="iv-sell-btn">🤝 می‌خوام بفروشم</button>'+
      '<div class="sl-iv-cta-row2">'+
        '<button class="sl-iv-cta-sm" id="iv-share-btn">🔗 اشتراک‌گذاری</button>'+
        '<button class="sl-iv-cta-sm" id="iv-history-btn">📜 کارشناسی‌های قبلی</button>'+
      '</div>'+
      '<button class="sl-iv-cta-ghost" id="iv-newcalc-btn">🔄 کارشناسی تازه</button>'+
    '</div></div>';
  var fairEl=document.getElementById('iv-fair-num');if(fairEl)_ivCountUp(fairEl,result.fair_price||0,1100);
  var buyEl=document.getElementById('iv-buy-num');if(buyEl)_ivCountUp(buyEl,result.buy_price||0,1100);
  var gaugeFg=document.querySelector('.sl-iv-gauge-fg');
  if(gaugeFg)setTimeout(function(){gaugeFg.style.transition='stroke-dashoffset 1.1s cubic-bezier(.22,1,.36,1)';gaugeFg.style.strokeDashoffset=gaugeFg.dataset.targetOffset},80);
  document.getElementById('iv-sell-btn').addEventListener('click',function(){_ivOpenSellForm(result)});
  document.getElementById('iv-share-btn').addEventListener('click',function(){_ivShareResult(result)});
  document.getElementById('iv-history-btn').addEventListener('click',openIVHistory);
  document.getElementById('iv-newcalc-btn').addEventListener('click',openIVWizard);
}

/* ═══ «می‌خوام بفروشم» — فرم مختصر + POST /iphone/sell-request ═══ */
function _ivOpenSellForm(result){
  var b=_accBody();if(!b)return;
  // خودِ کارشناسی بدون ورود کار می‌کند (عمدی)، ولی ثبت درخواست فروش به حساب
  // واقعی گره می‌خورد — پس گیت دقیقاً همین‌جاست، نه ابتدای ویزارد.
  if(!window.slHasAccount()){window.slRequireAccount(_accBody,'برای ثبت درخواست فروش و تماس ما با شما، ثبت‌نام لازم است.');return}
  var priceLine=result.fair_price?('💰 قیمت تخمینی: '+fmt(result.fair_price)+' تومان'):'💰 قیمت: توافقی';
  b.innerHTML='<div class="sl-iv"><div class="sl-iv-body">'+
    '<div class="sl-iv-sub">🤝 می‌خوام بفروشم</div>'+
    '<div class="sl-iv-hint" style="margin-top:-10px">'+esc(result.model_name||'')+' — '+priceLine+'</div>'+
    '<input type="tel" class="sl-iv-input" id="iv-sell-phone" placeholder="شمارهٔ تماس (مثلاً 0912xxxxxxx)">'+
    '<input type="text" class="sl-iv-input" id="iv-sell-city" placeholder="شهر" value="'+esc(_ivS&&_ivS.city||'')+'">'+
    '<div class="sl-iv-footer"><button class="sl-iv-cta" id="iv-sell-send">ثبت درخواست فروش</button></div>'+
    '</div></div>';
  document.getElementById('iv-sell-send').addEventListener('click',function(){
    var phoneRaw=document.getElementById('iv-sell-phone').value||'';
    var phone=digitsOnly(phoneRaw).replace(/[^0-9+]/g,'');
    var city=(document.getElementById('iv-sell-city').value||'').trim();
    if(phone.length<8){window._slApp.dialog.alert('شمارهٔ تماس معتبر نیست','خطا');return}
    if(!city){window._slApp.dialog.alert('نام شهر الزامی است','خطا');return}
    var btn=document.getElementById('iv-sell-send');btn.disabled=true;btn.textContent='در حال ارسال…';
    var headers={'Content-Type':'application/json'};if(initData)headers['X-Telegram-Init-Data']=initData;
    fetch('/api/v1/iphone/sell-request',{method:'POST',headers:headers,body:JSON.stringify({
      phone:phone,city:city,model_id:(_ivS&&_ivS.model_id)||null,model_name:result.model_name||'',
      /* ردیف قیمتی همین کارشناسی — «ثبت معامله» در پنل باید دقیقاً همین ترکیب
         را هدف بگیرد، وگرنه سیگنال بازار به ترکیب اشتباه می‌چسبد. */
      capacity_id:(_ivS&&_ivS.capacity_id)||result.capacity_id||null,
      grade_key:result.grade_key||((_ivS&&_ivS.selections)||{}).grade||'',summary_text:'',
      estimated_price:result.fair_price||null,
    })}).then(function(r){return r.json()}).then(function(d){
      if(!d||!d.ok){btn.disabled=false;btn.textContent='ثبت درخواست فروش';window._slApp.dialog.alert((d&&d.detail)||'خطا در ثبت درخواست','خطا');return}
      var body=document.querySelector('.sl-iv-body');
      if(body)body.innerHTML='<div class="sl-empty" style="padding:70px 16px;color:var(--iv-mu,#8E8E93)"><span class="sl-empty-e">✅</span>درخواست فروش ثبت شد — همکاران ما به‌زودی باهات تماس می‌گیرن.</div>';
    }).catch(function(){btn.disabled=false;btn.textContent='ثبت درخواست فروش';window._slApp.dialog.alert('خطای شبکه','خطا')});
  });
}
function _ivShareResult(result){
  var text='📱 '+result.model_name+' '+result.capacity_label+'\n'+
    '⚖️ قیمت منصفانه: '+fmt(result.fair_price)+' تومان\n'+
    '🏬 پیشنهاد نقدی Stland: '+fmt(result.buy_price)+' تومان\n'+
    '⭐️ امتیاز Stland: '+faNum(String(result.score))+' از ۱۰۰\n\n'+
    'کارشناسی رایگان قیمت آیفونت رو همین الان بگیر 👇';
  _iptShare(text,(window.stlhIpt&&stlhIpt.urls.price)||location.href);
}

/* ═══ «کارشناسی‌های قبلی من» (GET /iphone/history) ═══ */
function openIVHistory(){
  _accPopup('کارشناسی‌های قبلی من',skel(3));
  /* لایهٔ «سشن»، نه «حساب»: کارشناسی مهمان هم به سشن خودش می‌چسبد
     (بخش ۱۴۸)، پس تاریخچهٔ خودش را بدون ثبت‌نام می‌بیند. */
  slRequireLogin(_accBody,'تاریخچهٔ کارشناسی‌ها به همین دستگاه/حساب وصل است.').then(function(ok){
  if(!ok)return;
  api('/iphone/history',true).then(function(d){
    var b=_accBody();if(!b)return;
    var items=(d&&d.items)||[];
    if(!items.length){b.innerHTML='<div class="sl-empty" style="padding:60px 20px"><span class="sl-empty-e">📜</span>هنوز کارشناسی‌ای ثبت نکردی.</div>';return}
    b.innerHTML='<div class="sl-group">'+items.map(function(r){
      return '<div class="sl-row"><span class="sl-ric" style="background:var(--br)">📱</span>'+
        '<span class="sl-row-grow">'+esc(r.model_name||'—')+' '+esc(r.capacity_label||'')+
        '<div style="font-size:11px;color:var(--mu);margin-top:2px">'+jalaliDate(r.created_at)+'</div></span>'+
        '<span style="text-align:left;font-weight:800;white-space:nowrap">'+(r.fair_price?fmt(r.fair_price)+' <small>تومان</small>':'—')+'</span></div>';
    }).join('')+'</div>';
  }).catch(function(){var b=_accBody();if(b)b.innerHTML=err('خطا در دریافت تاریخچه')});
  });
}
window.openIVHistory=openIVHistory;

/* ═══════════════════════════════════════════════════════════════════════════
   🩺 سلامت آیفون — ویزارد مینی‌اپ/PWA (فاز ۲)
   ───────────────────────────────────────────────────────────────────────────
   تفاوت بنیادی با نسخهٔ ربات: مرورگر واقعاً به لمسگر/ژیروسکوپ/میکروفون/دوربین
   دسترسی داره، پس بخشی از تست‌ها به‌جای «کاربر گفت سالمه» واقعاً *اندازه‌گیری*
   می‌شن. هر پاسخ یه سطح اعتبار (evidence) همراه خودش به سرور می‌فرسته:
     auto     = اپ اندازه گرفت      assisted = اپ محرک داد، کاربر قضاوت کرد
     manual   = فقط اظهار کاربر
   ⚠️ قانون طلایی: هر ویجت خودکار باید مسیر دستی هم داشته باشه. اگه API روی
   دستگاه/وب‌ویو در دسترس نبود یا کاربر اجازه نداد، بی‌صدا به همون سه‌دکمهٔ
   ✅/❌/⏭ برمی‌گرده و evidence=manual می‌فرسته — هیچ‌وقت بن‌بست نمی‌شه.
   کدوم تست کدوم ویجت رو داره، از دیتابیس میاد (ih_tests.web_test_key) و از پنل
   قابل خاموش‌کردنه — بدون دیپلوی.
   ═══════════════════════════════════════════════════════════════════════════ */
var _ihS=null;

function _ihFresh(){return {data:null,report:null,model:null,mode:'quick',tests:[],idx:0,
  answers:{},parts:{},side:'front',busy:false}}

function _ihHaptic(){try{var tg=window._slTg;if(tg&&tg.HapticFeedback&&tg.HapticFeedback.selectionChanged)tg.HapticFeedback.selectionChanged()}catch(e){}}
function _ihImpact(st){try{var tg=window._slTg;if(tg&&tg.HapticFeedback&&tg.HapticFeedback.impactOccurred)tg.HapticFeedback.impactOccurred(st||'soft')}catch(e){}}


/* ─── شماتیک گوشی ────────────────────────────────────────────────────────
   SVG کاملاً داده‌محور از روی iv_models.features — ناچ در برابر آیلند، دکمهٔ
   اکشن در برابر سوییچ بی‌صدا، دو یا سه دوربین، دکمهٔ هوم. یعنی مدل تازه‌ای که
   ادمین اضافه کنه، شماتیکش خودکار درست درمیاد بدون یه خط کد. */
function _ihTags(model){
  var s=(model&&model.features)||'';
  var out={};s.split(',').forEach(function(t){t=t.trim();if(t)out[t]=1});return out;
}

function _ihSchematicSvg(model,side){
  var f=_ihTags(model),g=[];
  var homeBtn=!!f.touch_id&&!f.face_id;
  if(side==='back'){
    g.push('<rect x="4" y="4" width="112" height="232" rx="20" class="sl-ih-body"/>');
    g.push('<rect x="10" y="10" width="100" height="220" rx="16" class="sl-ih-part" data-part="back"/>');
    var lens=f.telephoto?3:2;
    g.push('<rect x="14" y="14" width="'+(lens>2?46:38)+'" height="'+(lens>2?46:38)+'" rx="13" fill="rgba(142,142,147,.28)"/>');
    g.push('<g class="sl-ih-part" data-part="cam-rear">');
    g.push('<circle cx="27" cy="27" r="8"/><circle cx="'+(lens>2?47:47)+'" cy="27" r="8"/>');
    if(lens>2)g.push('<circle cx="27" cy="47" r="8"/>');
    g.push('</g>');
    g.push('<circle cx="'+(lens>2?47:27)+'" cy="'+(lens>2?47:47)+'" r="4.5" class="sl-ih-part" data-part="flash"/>');
    g.push('<rect x="46" y="120" width="28" height="42" rx="6" class="sl-ih-part" data-part="battery" opacity=".85"/>');
    return g.join('');
  }
  g.push('<rect x="4" y="4" width="112" height="232" rx="20" class="sl-ih-part" data-part="frame" fill="none" stroke="currentColor" stroke-width="2.5" opacity=".35"/>');
  g.push('<rect x="4" y="4" width="112" height="232" rx="20" class="sl-ih-body" fill-opacity=".9"/>');
  var scY=homeBtn?20:12,scH=homeBtn?184:216;
  g.push('<rect x="10" y="'+scY+'" width="100" height="'+scH+'" rx="'+(homeBtn?6:13)+'" class="sl-ih-part" data-part="screen" opacity=".55"/>');
  if(f.dynamic_island){
    g.push('<rect x="42" y="17" width="36" height="10" rx="5" fill="#0B0B0F"/>');
    g.push('<rect x="44" y="19" width="14" height="6" rx="3" class="sl-ih-part" data-part="face-id"/>');
    g.push('<circle cx="71" cy="22" r="3" class="sl-ih-part" data-part="cam-front"/>');
    g.push('<rect x="50" y="10" width="20" height="3" rx="1.5" class="sl-ih-part" data-part="earpiece"/>');
  }else if(f.face_id){
    g.push('<path d="M40 12 h40 a0 0 0 0 1 0 0 v6 a7 7 0 0 1 -7 7 h-26 a7 7 0 0 1 -7 -7 z" fill="#0B0B0F"/>');
    g.push('<rect x="46" y="16" width="12" height="5" rx="2.5" class="sl-ih-part" data-part="face-id"/>');
    g.push('<circle cx="71" cy="18" r="3" class="sl-ih-part" data-part="cam-front"/>');
    g.push('<rect x="52" y="9" width="16" height="2.5" rx="1.25" class="sl-ih-part" data-part="earpiece"/>');
  }else{
    g.push('<rect x="50" y="11" width="20" height="3" rx="1.5" class="sl-ih-part" data-part="earpiece"/>');
    g.push('<circle cx="78" cy="12.5" r="2.5" class="sl-ih-part" data-part="cam-front"/>');
  }
  if(homeBtn)g.push('<circle cx="60" cy="217" r="9" class="sl-ih-part" data-part="home" fill="none" stroke="currentColor" stroke-width="2" opacity=".5"/><circle cx="60" cy="217" r="9" class="sl-ih-part" data-part="home"/>');
  g.push('<g class="sl-ih-part" data-part="speaker-bottom">');
  for(var i=0;i<5;i++)g.push('<circle cx="'+(70+i*5)+'" cy="233" r="1.5"/>');
  g.push('</g>');
  g.push('<circle cx="42" cy="233" r="1.6" class="sl-ih-part" data-part="mic"/>');
  g.push('<rect x="52" y="230.5" width="16" height="5" rx="2.5" class="sl-ih-part" data-part="port"/>');
  g.push('<rect x="115" y="70" width="4" height="30" rx="2" class="sl-ih-part" data-part="btn-power"/>');
  g.push('<rect x="1" y="76" width="4" height="18" rx="2" class="sl-ih-part" data-part="btn-volume"/>');
  g.push('<rect x="1" y="100" width="4" height="18" rx="2" class="sl-ih-part" data-part="btn-volume"/>');
  if(f.action_button)g.push('<rect x="1" y="52" width="4" height="14" rx="2" class="sl-ih-part" data-part="btn-action"/>');
  else g.push('<rect x="1" y="54" width="4" height="10" rx="2" class="sl-ih-part" data-part="btn-mute"/>');
  g.push('<rect x="115" y="115" width="4" height="16" rx="2" class="sl-ih-part" data-part="sim"/>');
  return g.join('');
}

function _ihSchematic(small){
  var s=_ihS;
  return '<div class="sl-ih-phone'+(small?' sm':'')+'"><svg viewBox="0 0 120 240" '+
    'style="color:var(--iv-mu)" aria-hidden="true">'+_ihSchematicSvg(s.model,s.side)+'</svg></div>';
}
function _ihPaint(liveParts){
  var s=_ihS;
  document.querySelectorAll('.sl-ih-part').forEach(function(el){
    var p=el.dataset.part;
    el.classList.remove('ok','issue','unknown','live');
    if(liveParts&&liveParts.indexOf(p)>=0){el.classList.add('live');return}
    var st=s.parts[p];if(st)el.classList.add(st);
  });
}

/* ─── پوسته (همون زبان بصری کارشناسی قیمت) ─────────────────────────────── */
function _ihShell(bodyHtml,pct,showBack){
  var b=_accBody();if(!b)return;
  b.innerHTML='<div class="sl-iv sl-ih">'+
    '<div class="sl-iv-top">'+
      '<button class="sl-iv-back'+(showBack?'':' hide')+'" id="ih-back"><span>مرحله قبل</span><span class="sl-iv-back-ic">›</span></button>'+
      '<div class="sl-iv-bar"><div class="sl-iv-bar-f" style="width:'+Math.max(5,pct||0)+'%"></div></div>'+
    '</div><div class="sl-iv-body">'+bodyHtml+'</div></div>';
  var bk=document.getElementById('ih-back');if(bk)bk.addEventListener('click',_ihBack);
  _slWizTop('ih:'+pct);
}

function openIHWizard(){
  /* بخش ۲۹۳: هر راه ورودی (کارت، لینک، کنسول) روی غیرآیفون همین پیام را می‌بیند */
  if(!window.slIsIPhone){
    _accPopup('سلامت آیفون','<div class="sl-empty" style="padding:40px 22px;text-align:center;line-height:2">تست سلامت حسگرها و قطعات <b>خودِ گوشی</b> را می‌سنجد، پس فقط روی آیفون کار می‌کند.<br>همین صفحه را روی آیفونی که می‌خواهید تست شود باز کنید.</div>');
    return;
  }
  _slWizTop(null);
  _accPopup('سلامت آیفون',skel(3));
  var pb=document.getElementById('post-body');if(pb)pb.classList.add('sl-iv-host');
  /* گیت ورود **برداشته شد** (فاز ۱ دسترسی بدون تلگرام): سرور خودش روی
     «شروع تست» یک سشن مهمان می‌سازد، پس کاربری که فقط یک لینک گرفته
     می‌تواند کل تست را بزند. مالکیت گزارش و تاریخچه همچنان سمت سرور
     محافظت می‌شوند — این فقط دیوارِ جلوی در را برداشت، نه قفل‌ها را. */
  api('/health/wizard-data',false).then(function(d){
    if(!d||!d.ok){var b=_accBody();if(b)b.innerHTML=err('خطا در دریافت اطلاعات');return}
    _ihS=_ihFresh();_ihS.data=d;_ihStepSeries();
  }).catch(function(){var b=_accBody();if(b)b.innerHTML=err('خطا در دریافت اطلاعات')});
}
window.openIHWizard=openIHWizard;

/* ─── پردهٔ ۱: شناسایی دستگاه ──────────────────────────────────────────── */
function _ihStepSeries(){
  var list=(_ihS.data.series)||[];
  if(!list.length){_ihShell('<div class="sl-empty" style="padding:70px 16px"><span class="sl-empty-e">📱</span>هنوز مدلی در پنل تعریف نشده.</div>',5,false);return}
  _ihShell('<div class="sl-iv-headline"><div class="sl-iv-headline-t">بررسی سلامت آیفون</div>'+
    '<div class="sl-iv-headline-s">مدل دستگاهت رو انتخاب کن</div></div>'+
    '<div class="sl-iv-devgrid">'+list.map(function(sr){
      return _ivDevCard(sr,sr.id===_ihS.series_id,'ihser');
    }).join('')+'</div>',8,false);
  document.querySelectorAll('[data-ihser]').forEach(function(el){
    el.addEventListener('click',function(){_ihHaptic();_ihS.series_id=+el.dataset.ihser;_ihStepModel()});
  });
}
function _ihStepModel(){
  var models=(_ihS.data.models||[]).filter(function(m){return m.series_id===_ihS.series_id});
  var seriesList=_ihS.data.series||[],seriesName='';
  for(var i=0;i<seriesList.length;i++)if(seriesList[i].id===_ihS.series_id){seriesName=seriesList[i].name;break}
  _ihShell('<div class="sl-iv-headline">'+
    (seriesName?'<span class="sl-iv-headline-ctx">'+esc(seriesName)+'</span>':'')+
    '<div class="sl-iv-headline-t">مدل دستگاهت چیه؟</div>'+
    '<div class="sl-iv-headline-s">مدل دقیق آیفون خودت رو انتخاب کن تا ادامه بدیم.</div></div>'+
    '<div class="sl-iv-devgrid">'+models.map(function(m){
      return _ivDevCard(m,!!(_ihS.model&&_ihS.model.id===m.id),'ihmdl',(m.series||'').trim());
    }).join('')+'</div>',16,true);
  _ihS._back=_ihStepSeries;
  document.querySelectorAll('[data-ihmdl]').forEach(function(el){
    el.addEventListener('click',function(){
      _ihHaptic();
      var mid=+el.dataset.ihmdl;
      _ihS.model=models.filter(function(x){return x.id===mid})[0]||null;
      _ihStepCapacity();
    });
  });
}
function _ihStepCapacity(){
  var caps=(_ihS.data.storages||{})[String(_ihS.model.id)]||[];
  var html=_ihSchematic()+'<div class="sl-iv-sub">💾 ظرفیت حافظه؟</div><div class="sl-iv-grid">'+
    caps.map(function(c){return '<div class="sl-iv-opt sl-iv-opt-w sl-iv-pill" data-ihcap="'+esc(c.label)+'">'+esc(c.label)+'</div>'}).join('')+
    '</div><button class="sl-iv-cta-ghost" id="ih-cap-skip" style="width:100%;margin-top:12px">رد کن، نمی‌دونم</button>';
  _ihShell(html,24,true);
  _ihS._back=_ihStepModel;
  document.querySelectorAll('[data-ihcap]').forEach(function(el){
    el.addEventListener('click',function(){_ihHaptic();_ihS.capacity_label=el.dataset.ihcap;_ihStepMode()});
  });
  var sk=document.getElementById('ih-cap-skip');
  if(sk)sk.addEventListener('click',function(){_ihS.capacity_label='';_ihStepMode()});
}
function _ihStepMode(){
  var html=_ihSchematic()+
    '<div class="sl-iv-sub">چقدر وقت داری؟</div>'+
    '<div class="sl-iv-yn">'+
      '<button class="sl-iv-yn-btn" id="ih-mode-quick">⚡ تست سریع قطعات<br><span style="font-size:11.5px;font-weight:600;color:var(--iv-mu)">حدود ۱ دقیقه — مهم‌ترین بخش‌ها</span></button>'+
      '<button class="sl-iv-yn-btn" id="ih-mode-full">🔬 بررسی کامل<br><span style="font-size:11.5px;font-weight:600;color:var(--iv-mu)">حدود ۱۰ دقیقه — همهٔ قطعات</span></button>'+
    '</div>'+
    '<div class="sl-ih-modenote">⚡ <b>تست سریع قطعات:</b> فقط سخت‌افزار در دسترس — اسپیکرها، '+
      'دوربین‌ها، میکروفون، لمسگر، نمایشگر، سنسورها، دکمه‌ها و پورت.<br>'+
      '🔬 <b>بررسی کامل:</b> همهٔ موارد بالا به‌علاوهٔ اصالت، رجیستری، باتری و بدنه.</div>';
  _ihShell(html,32,true);
  _ihS._back=_ihStepCapacity;
  ['quick','full'].forEach(function(m){
    var el=document.getElementById('ih-mode-'+m);
    if(el)el.addEventListener('click',function(){_ihHaptic();_ihS.mode=m;_ihStart()});
  });
}

/* ─── فیلتر تست‌های اعمال‌پذیر (پورت دقیق applicable_tests سرور) ────────── */
function _ihApplicable(){
  var tags=_ihTags(_ihS.model),quick=_ihS.mode==='quick';
  return (_ihS.data.tests||[]).filter(function(t){
    var req=(t.requires_feature||'').trim(),exc=(t.excludes_feature||'').trim();
    if(req&&!tags[req])return false;
    if(exc&&tags[exc])return false;
    if(quick&&!t.quick_test)return false;
    return true;
  });
}

function _ihStart(){
  _ihShell(skel(2),36,false);
  _ihPost('/health/report',{mode:_ihS.mode,model_id:_ihS.model.id,capacity_label:_ihS.capacity_label||''})
    .then(function(d){
      _ihS.report=d.report;_ihS.tests=_ihApplicable();_ihS.idx=0;
      if(!_ihS.tests.length){_ihShell(err('تستی برای این مدل تعریف نشده'),100,false);return}
      _ihRenderTest();
    }).catch(function(e){
      // سشن وسط کار منقضی شده (یا از ابتدا نبوده) — به‌جای پیام گنگ، راه ورود
      if(slIsAuthErr(e)){_ihShell('',36,false);slAuthWall(_accBody(),'برای شروع تست و ذخیرهٔ شناسنامهٔ سلامت دستگاه، وارد شوید.');return}
      _ihShell(err((e&&e.detail)||'خطا در شروع تست'),36,false);
    });
}

/* ─── پردهٔ ۲: کارت تست ────────────────────────────────────────────────── */
function _ihRenderTest(){
  var s=_ihS,t=s.tests[s.idx];
  if(!t)return _ihFinalize();
  var cats={};(s.data.categories||[]).forEach(function(c){cats[c.id]=c});
  var cat=cats[t.category_id]||{icon:'',title:''};
  var pct=36+Math.round((s.idx/s.tests.length)*58);
  var part=(t.schematic_part||'').trim();
  var partMeta=(s.data.schematic_parts||{})[part];
  /* اگه قطعهٔ این تست روی نمای پشته، شماتیک خودکار می‌چرخه — کاربر همیشه
     همون سمتی رو می‌بینه که داره تستش می‌کنه. */
  s.side=(partMeta&&partMeta.side==='back')?'back':'front';

  var web=(t.web_test_key||'').trim();
  var widget=(web?_ihWidgetHtml(web,t):'')||'';   // کلید ناشناخته → بدون ویجت، نه رشتهٔ undefined
  var evMeta=(s.data.web_tests||{})[web];
  var evLvl=evMeta?evMeta.evidence:'manual';
  var evInfo=(s.data.evidence_levels||{})[evLvl]||{};

  var html=_ihSchematic(true)+
    '<div class="sl-ih-cat">'+esc(cat.icon)+' '+esc(cat.title)+' · <span style="direction:ltr">'+(s.idx+1)+'/'+s.tests.length+'</span></div>'+
    '<div class="sl-ih-q">'+esc(t.title)+'</div>'+
    (web?'<div style="text-align:center"><span class="sl-ih-ev '+(evLvl==='auto'?'auto':'')+'" id="ih-ev">'+esc(evInfo.icon||'')+' '+esc(evInfo.label||'')+'</span></div>':'')+
    (t.guide_text?'<div class="sl-ih-guide">'+t.guide_text+'</div>':'')+
    widget+
    '<div class="sl-ih-ans">'+
      '<button class="sl-ih-ans-btn ok" data-ihans="ok">✅ سالمه</button>'+
      '<button class="sl-ih-ans-btn issue" data-ihans="issue">❌ مشکل داره</button>'+
      '<button class="sl-ih-ans-btn skip" data-ihans="unknown">⏭ نمی‌دونم / نمی‌تونم چک کنم</button>'+
    '</div>';
  _ihShell(html,pct,s.idx>0);
  _ihPaint(part?[part]:null);
  s._back=function(){if(s.idx>0){s.idx--;_ihRenderTest()}};

  /* evidence پیش‌فرض manual می‌مونه تا وقتی ویجت واقعاً اجرا بشه و نتیجه بده */
  s._evidence='manual';s._measurement=null;
  if(web)_ihWidgetInit(web,t);

  document.querySelectorAll('[data-ihans]').forEach(function(el){
    el.addEventListener('click',function(){
      if(s.busy)return;
      _ihHaptic();
      _ihAnswer(t,el.dataset.ihans);
    });
  });
}

function _ihAnswer(t,answer){
  var s=_ihS;s.busy=true;
  var part=(t.schematic_part||'').trim();
  if(part)s.parts[part]=answer;
  s.answers[t.test_key]=answer;
  _ihStopWidget();
  _ihPost('/health/report/'+s.report.id+'/answer',{
    test_id:t.id,answer:answer,evidence:s._evidence||'manual',
    measurement:s._measurement||''
  }).catch(function(){/* شکست شبکه نباید کاربر رو گیر بندازه؛ پاسخ بعدی دوباره تلاش می‌کنه */})
    .then(function(){s.busy=false;s.idx++;_ihRenderTest()});
}

function _ihBack(){var f=_ihS&&_ihS._back;_ihStopWidget();if(f)f();}

/* ─── ویجت‌های تست خودکار ────────────────────────────────────────────────
   قرارداد مشترک هر ویجت:
     _ihWidgetHtml(key,test) → HTML اولیه (قبل از اجرا)
     _ihWidgetInit(key,test) → وایر کردن رویدادها / درخواست اجازه
   و هر ویجت وقتی نتیجه‌ای داد، `_ihS._evidence`/`_ihS._measurement` رو ست
   می‌کنه و (در تست‌های کاملاً خودکار) دکمهٔ پیشنهادی رو هایلایت می‌کنه.
   ⚠️ هیچ ویجتی کاربر رو مجبور نمی‌کنه: سه دکمهٔ ✅/❌/⏭ همیشه پایین صفحه
   فعالن، حتی اگه ویجت اصلاً اجرا نشه (اجازه رد بشه، API نباشه، …). */
var _ihWid={stop:null};
function _ihStopWidget(){try{if(_ihWid.stop)_ihWid.stop()}catch(e){}_ihWid.stop=null}

function _ihWidgetHtml(key,t){
  var label=((_ihS.data.web_tests||{})[key]||{}).label||'';
  if(key==='touch_grid')return '<div class="sl-ih-w"><div class="sl-ih-w-t">تست لمسگر روی <b>کل صفحه</b> اجرا می‌شه — انگشتت رو همه‌جای صفحه بکش تا هیچ نقطهٔ مرده‌ای جا نمونه</div>'+
    '<button class="sl-ih-w-run" id="ih-tg-run">🖐 شروع تست تمام‌صفحه</button>'+
    '<div class="sl-ih-w-res" id="ih-tg-res"></div></div>';
  if(key==='dead_pixel')return '<div class="sl-ih-w"><div class="sl-ih-w-t">'+esc(label)+'</div>'+
    '<button class="sl-ih-w-run" id="ih-dp-run">🎨 شروع تست تمام‌صفحه</button>'+
    '<div class="sl-ih-w-res" id="ih-dp-res"></div></div>';
  if(key==='gyro')return '<div class="sl-ih-w"><div class="sl-ih-w-t">گوشی رو کج کن — حباب باید حرکت کنه</div>'+
    '<div class="sl-ih-lvl"><div class="sl-ih-lvl-x"></div><div class="sl-ih-lvl-b" id="ih-lvl"></div></div>'+
    '<button class="sl-ih-w-run" id="ih-gy-run" style="margin-top:12px">▶️ شروع تست</button>'+
    '<div class="sl-ih-w-res" id="ih-gy-res"></div></div>';
  if(key==='compass')return '<div class="sl-ih-w"><div class="sl-ih-w-t">گوشی رو بچرخون</div>'+
    '<button class="sl-ih-w-run" id="ih-cp-run">🧭 شروع تست</button>'+
    '<div class="sl-ih-w-res" id="ih-cp-res"></div></div>';
  if(key==='mic_level')return '<div class="sl-ih-w"><div class="sl-ih-w-t">بلند حرف بزن — نوارها باید بالا برن</div>'+
    '<div class="sl-ih-mic" id="ih-mic"></div>'+
    '<button class="sl-ih-w-run" id="ih-mic-run">🎙 شروع تست</button>'+
    '<div class="sl-ih-w-res" id="ih-mic-res"></div></div>';
  if(key==='speaker_tone')return '<div class="sl-ih-w"><div class="sl-ih-w-t">'+esc(label)+'</div>'+
    '<button class="sl-ih-w-run" id="ih-sp-run">🔊 پخش صدای تست</button>'+
    '<div class="sl-ih-w-res" id="ih-sp-res"></div></div>';
  if(key==='camera_front'||key==='camera_rear')return '<div class="sl-ih-w"><div class="sl-ih-w-t">'+esc(label)+'</div>'+
    '<video class="sl-ih-cam" id="ih-cam" playsinline muted autoplay style="display:none"></video>'+
    '<button class="sl-ih-w-run" id="ih-cam-run">📷 روشن کردن دوربین</button>'+
    '<div class="sl-ih-w-res" id="ih-cam-res"></div></div>';
  if(key==='haptic')return '<div class="sl-ih-w"><div class="sl-ih-w-t">'+esc(label)+'</div>'+
    '<button class="sl-ih-w-run" id="ih-hp-run">📳 اجرای لرزش</button>'+
    '<div class="sl-ih-w-res" id="ih-hp-res"></div></div>';
  return '';
}

function _ihSetRes(id,txt,cls){var el=document.getElementById(id);if(el){el.textContent=txt;el.className='sl-ih-w-res'+(cls?' '+cls:'')}}
function _ihMarkAuto(measurement){_ihS._evidence='auto';_ihS._measurement=measurement||null}
function _ihMarkAssisted(){_ihS._evidence='assisted'}
/* وقتی API روی این دستگاه نیست: پیام صادقانه + همون مسیر دستی (evidence=manual) */
function _ihUnsupported(resId,why){
  _ihS._evidence='manual';
  _ihSetRes(resId,why||'این تست خودکار روی این دستگاه در دسترس نیست — دستی چک کن.','');
}

function _ihWidgetInit(key,t){
  if(key==='touch_grid')return _ihWTouch();
  if(key==='dead_pixel')return _ihWDeadPixel();
  if(key==='gyro')return _ihWGyro();
  if(key==='compass')return _ihWCompass();
  if(key==='mic_level')return _ihWMic();
  if(key==='speaker_tone')return _ihWSpeaker();
  if(key==='camera_front')return _ihWCamera('user');
  if(key==='camera_rear')return _ihWCamera('environment');
  if(key==='haptic')return _ihWHaptic();
}

/* لمسگر — تنها تستی که واقعاً «نقطهٔ مرده» رو خودش کشف می‌کنه */
/* ⚠️ دو باگ واقعی گزارش‌شدهٔ مالک پروژه (۲۰۲۶-۰۸-۱۸) که این بازنویسی رفعشون می‌کنه:
   ۱) «چپ می‌زنم، راست تست می‌شه» — نسخهٔ قبلی خانهٔ لمس‌شده رو با ریاضی اندیس
      (`cy*COLS+cx` از لبهٔ چپ) پیدا می‌کرد، ولی گرید داخل ظرف `dir="rtl"` چیده
      می‌شد و auto-placement گرید در RTL از **راست به چپ** پر می‌کنه — یعنی
      فرزند اول DOM بصری سمت راست می‌شینه. تأییدشده با اندازه‌گیری واقعی مرورگر:
      لمس نزدیک لبهٔ چپ اندیس ۰ حساب می‌شد ولی خانهٔ واقعی اون نقطه اندیس ۵ بود.
      رفع ریشه‌ای: به‌جای ریاضی اندیس، `document.elementFromPoint` — یعنی همون
      خانه‌ای که واقعاً زیر انگشته، مستقل از direction/چیدمان.
   ۲) «باید تمام‌صفحه باشه» — تست قبلی فقط داخل یه کادر کوچیک وسط صفحه بود، پس
      نقطهٔ مردهٔ گوشه/لبه‌ها اصلاً تست نمی‌شد. حالا یه Overlay تمام‌صفحهٔ واقعی. */
function _ihWTouch(){
  var btn=document.getElementById('ih-tg-run');if(!btn)return;
  btn.addEventListener('click',function(){ _ihTouchFullscreen(); });
}

/* ⚠️ چرا هیچ دکمهٔ «پایان» ثابتی روی سطح تست نیست (بازخورد مالک پروژه با
   اسکرین‌شات): (الف) دکمه روی همون سطحی می‌شینه که باید تست بشه و خونه‌های زیرش
   عملاً غیرقابل‌لمس می‌شن — یعنی رسیدن به ۱۰۰٪ واقعی ناممکن؛ (ب) خودش هم کار
   نمی‌کرد، چون هندلر لمس روی کل Overlay `preventDefault()` می‌زنه (لازم برای
   جلوگیری از اسکرول/بزرگ‌نمایی) و همون، رویداد click دکمه رو در iOS خفه می‌کرد.
   جایگزین حرفه‌ای، سه‌لایه و بدون اشغال هیچ پیکسلی از سطح تست:
     ۱) دکمهٔ بازگشت *بومی* تلگرام (بیرون از صفحه، بالای مینی‌اپ)
     ۲) بازگشت مرورگر/PWA (pushState+popstate) و کلید Escape روی دسکتاپ
     ۳) برگهٔ پایان که فقط بعد از چند لحظه بی‌حرکتی ظاهر می‌شه — حین کشیدن
        انگشت اصلاً وجود نداره، پس هیچ‌وقت جلوی تست رو نمی‌گیره. */
function _ihTouchFullscreen(){
  var ov=document.createElement('div');
  ov.className='sl-ih-tgfs';
  ov.innerHTML='<div class="sl-ih-tgfs-grid" id="ih-tgfs-grid"></div>'+
    '<div class="sl-ih-tgfs-hud"><div class="sl-ih-tgfs-hud-in">'+
      '<b id="ih-tgfs-pct">۰٪</b> انگشتت رو روی کل صفحه بکش'+
      '<small>برای پایان، چند لحظه دست نگه دار یا دکمهٔ بازگشت رو بزن</small>'+
    '</div></div>';
  document.body.appendChild(ov);
  var grid=ov.querySelector('#ih-tgfs-grid');
  var W=ov.clientWidth||window.innerWidth, H=ov.clientHeight||window.innerHeight;
  var COLS=Math.max(4,Math.round(W/46)), ROWS=Math.max(6,Math.round(H/46));
  grid.style.gridTemplateColumns='repeat('+COLS+',1fr)';
  grid.style.gridTemplateRows='repeat('+ROWS+',1fr)';
  var i,d,total=COLS*ROWS;
  /* حالا که هیچ دکمه‌ای روی سطح نیست، *همهٔ* خونه‌ها قابل‌لمس‌ان — پس ۱۰۰٪ واقعاً
     دست‌یافتنیه و درصد پوشش هم عدد صادقانه‌ایه. */
  for(i=0;i<total;i++){d=document.createElement('div');d.className='sl-ih-tgfs-c';grid.appendChild(d)}
  var hit=0,done=false,closed=false,idleT=null,sheet=null;
  function pctNow(){return total?Math.round(hit/total*100):0}

  function hideSheet(){ if(sheet){try{sheet.remove()}catch(e){}sheet=null} }
  function armIdle(ms){
    clearTimeout(idleT);
    idleT=setTimeout(showSheet,ms||4500);
  }
  function showSheet(){
    if(done||closed||sheet)return;
    sheet=document.createElement('div');
    sheet.className='sl-ih-tgfs-sheet';
    sheet.innerHTML='<h4>پوشش فعلی: '+fmt(pctNow())+'٪</h4>'+
      '<p>'+(hit?'اگه جایی از صفحه به لمس جواب نمی‌ده، همون‌جا خاکستری می‌مونه.':
                  'انگشتت رو روی صفحه بکش تا خونه‌ها سبز بشن.')+'</p>'+
      '<button class="sl-ih-tgfs-go">ادامهٔ تست</button>'+
      '<button class="sl-ih-tgfs-end">پایان و ثبت نتیجه</button>';
    ov.appendChild(sheet);
    /* روی خودِ برگه از preventDefault هندلر بیرونی جلوگیری می‌کنیم، وگرنه دقیقاً
       همون باگی که دکمهٔ قبلی رو از کار انداخته بود تکرار می‌شه. */
    sheet.addEventListener('touchstart',function(e){e.stopPropagation()},{passive:true});
    sheet.addEventListener('pointerdown',function(e){e.stopPropagation()});
    sheet.querySelector('.sl-ih-tgfs-go').addEventListener('click',function(e){
      e.stopPropagation();hideSheet();armIdle(6000);
    });
    sheet.querySelector('.sl-ih-tgfs-end').addEventListener('click',function(e){
      e.stopPropagation();close();
    });
  }

  function mark(x,y){
    /* منبع حقیقت: خودِ عنصر زیر انگشت — مستقل از جهت/چیدمان گرید (باگ آینه‌ای).
       HUD چون pointer-events:none داره، این‌جا هم نادیده گرفته می‌شه. */
    var el=document.elementFromPoint(x,y);
    if(!el||el.className!=='sl-ih-tgfs-c')return;
    el.className='sl-ih-tgfs-c hit';hit++;
    var p=pctNow(),lbl=ov.querySelector('#ih-tgfs-pct');
    if(lbl)lbl.textContent=fmt(p)+'٪';
    armIdle();
    if(p>=100&&!done){
      done=true;clearTimeout(idleT);_ihImpact('medium');
      _ihMarkAuto(JSON.stringify({coverage_pct:100,cells:total,fullscreen:true}));
      _ihSetRes('ih-tg-res','✅ کل صفحه پاسخ داد — لمسگر سالمه','ok');
      setTimeout(close,500);
    }
  }
  function onMove(e){
    if(sheet)return;                       // وقتی برگه بازه، سطح تست غیرفعاله
    if(e.touches){for(var k=0;k<e.touches.length;k++)mark(e.touches[k].clientX,e.touches[k].clientY)}
    else mark(e.clientX,e.clientY);
    if(e.cancelable)e.preventDefault();
  }
  function onPointer(e){ if(sheet)return; if(e.type==='pointerdown'||e.buttons)mark(e.clientX,e.clientY); }
  ov.addEventListener('touchstart',onMove,{passive:false});
  ov.addEventListener('touchmove',onMove,{passive:false});
  ov.addEventListener('pointerdown',onPointer);
  ov.addEventListener('pointermove',onPointer);

  // ── لایهٔ ۱ و ۲: بازگشت بومی تلگرام + بازگشت مرورگر/PWA + Escape ──────────
  var tg=window._slTg, hadBack=false, backFn=function(){close()};
  try{
    if(tg&&tg.BackButton&&tg.BackButton.show){
      hadBack=!!tg.BackButton.isVisible;
      tg.BackButton.onClick(backFn);tg.BackButton.show();
    }
  }catch(e){}
  var pushed=false;
  try{history.pushState({slTgfs:1},'');pushed=true}catch(e){}
  function onPop(){ pushed=false;close(); }
  window.addEventListener('popstate',onPop);
  function onKey(e){ if(e.key==='Escape')close(); }
  window.addEventListener('keydown',onKey);
  armIdle(8000);   // حتی اگه کاربر اصلاً لمس نکنه، راه خروج ظرف ۸ ثانیه ظاهر می‌شه

  function close(){
    if(closed)return;closed=true;
    clearTimeout(idleT);hideSheet();
    window.removeEventListener('popstate',onPop);
    window.removeEventListener('keydown',onKey);
    try{
      if(tg&&tg.BackButton&&tg.BackButton.offClick){
        tg.BackButton.offClick(backFn);
        if(!hadBack&&tg.BackButton.hide)tg.BackButton.hide();
      }
    }catch(e){}
    if(pushed){try{history.back()}catch(e){}}
    try{document.body.removeChild(ov)}catch(e){}
    if(!done){
      /* پوشش ناقص هم یه اندازه‌گیری واقعیه — ثبت می‌شه تا در گزارش دیده بشه */
      if(hit>0)_ihMarkAuto(JSON.stringify({coverage_pct:pctNow(),cells:total,fullscreen:true}));
      _ihSetRes('ih-tg-res',hit>0?('پوشش '+fmt(pctNow())+'٪ — نقطهٔ لمس‌نشده باقی موند'):'تست انجام نشد','');
    }
  }
  _ihWid.stop=close;   // خروج از مرحله وسط تست، Overlay رو جا نذاره
}

/* پیکسل مرده — پوشش تمام‌صفحهٔ واقعی با چرخهٔ رنگ */
function _ihWDeadPixel(){
  var btn=document.getElementById('ih-dp-run');if(!btn)return;
  btn.addEventListener('click',function(){
    var colors=[['#FFFFFF','light'],['#000000',''],['#FF0000',''],['#00FF00',''],['#0000FF','']];
    var i=0,ov=document.createElement('div');
    ov.className='sl-ih-fs';
    ov.innerHTML='<div class="sl-ih-fs-h">برای رنگ بعدی بزن — دنبال نقطهٔ ثابت بگرد</div>';
    function paint(){
      ov.style.background=colors[i][0];
      ov.className='sl-ih-fs'+(colors[i][1]?' '+colors[i][1]:'');
      ov.querySelector('.sl-ih-fs-h').textContent=(i+1)+'/'+colors.length+' — برای ادامه بزن';
    }
    paint();
    ov.addEventListener('click',function(){
      i++;
      if(i>=colors.length){
        try{document.body.removeChild(ov)}catch(e){}
        _ihMarkAssisted();
        _ihSetRes('ih-dp-res','✅ همهٔ رنگ‌ها نمایش داده شد — نتیجه رو انتخاب کن','ok');
        _ihImpact('soft');
      }else{paint();_ihHaptic()}
    });
    document.body.appendChild(ov);
    _ihWid.stop=function(){try{document.body.removeChild(ov)}catch(e){}};
  });
}

/* ژیروسکوپ — iOS 13+ اجازهٔ صریح با کلیک کاربر می‌خواد */
function _ihWGyro(){
  var btn=document.getElementById('ih-gy-run'),bub=document.getElementById('ih-lvl');
  if(!btn)return;
  if(typeof DeviceOrientationEvent==='undefined'){btn.disabled=true;return _ihUnsupported('ih-gy-res')}
  btn.addEventListener('click',function(){
    function begin(){
      var seen=0,maxTilt=0,handler=function(ev){
        var b=ev.beta||0,g=ev.gamma||0;
        var x=Math.max(-1,Math.min(1,g/45)),y=Math.max(-1,Math.min(1,b/45));
        if(bub)bub.style.transform='translate('+(x*52)+'px,'+(y*52)+'px)';
        maxTilt=Math.max(maxTilt,Math.abs(b),Math.abs(g));
        if(++seen>8&&maxTilt>12){
          _ihMarkAuto(JSON.stringify({max_tilt_deg:Math.round(maxTilt),samples:seen}));
          _ihSetRes('ih-gy-res','✅ سنسور پاسخ می‌ده (کجی '+fmt(Math.round(maxTilt))+'°)','ok');
        }
      };
      window.addEventListener('deviceorientation',handler);
      _ihWid.stop=function(){window.removeEventListener('deviceorientation',handler)};
      _ihSetRes('ih-gy-res','گوشی رو کج کن…','');
      btn.disabled=true;
      /* اگه بعد از ۴ ثانیه هیچ رویدادی نیومد، یعنی عملاً سنسور در دسترس نیست */
      setTimeout(function(){if(!seen)_ihUnsupported('ih-gy-res','رویدادی از سنسور نرسید — دستی چک کن.')},4000);
    }
    if(DeviceOrientationEvent.requestPermission){
      DeviceOrientationEvent.requestPermission().then(function(st){
        if(st==='granted')begin();else _ihUnsupported('ih-gy-res','اجازهٔ دسترسی به سنسور داده نشد — دستی چک کن.');
      }).catch(function(){_ihUnsupported('ih-gy-res')});
    }else begin();
  });
}

/* قطب‌نما */
function _ihWCompass(){
  var btn=document.getElementById('ih-cp-run');if(!btn)return;
  if(typeof DeviceOrientationEvent==='undefined'){btn.disabled=true;return _ihUnsupported('ih-cp-res')}
  btn.addEventListener('click',function(){
    function begin(){
      var vals=[],handler=function(ev){
        var h=(typeof ev.webkitCompassHeading==='number')?ev.webkitCompassHeading:ev.alpha;
        if(typeof h!=='number')return;
        vals.push(h);
        _ihSetRes('ih-cp-res','جهت فعلی: '+fmt(Math.round(h))+'°','');
        if(vals.length>10&&(Math.max.apply(null,vals)-Math.min.apply(null,vals))>15){
          _ihMarkAuto(JSON.stringify({heading_range:Math.round(Math.max.apply(null,vals)-Math.min.apply(null,vals))}));
          _ihSetRes('ih-cp-res','✅ قطب‌نما به چرخش پاسخ می‌ده','ok');
        }
      };
      window.addEventListener('deviceorientation',handler);
      _ihWid.stop=function(){window.removeEventListener('deviceorientation',handler)};
      btn.disabled=true;
      setTimeout(function(){if(!vals.length)_ihUnsupported('ih-cp-res','داده‌ای از قطب‌نما نرسید — دستی چک کن.')},4000);
    }
    if(DeviceOrientationEvent.requestPermission){
      DeviceOrientationEvent.requestPermission().then(function(st){
        if(st==='granted')begin();else _ihUnsupported('ih-cp-res','اجازه داده نشد — دستی چک کن.');
      }).catch(function(){_ihUnsupported('ih-cp-res')});
    }else begin();
  });
}

/* میکروفون — سطح ورودی واقعاً اندازه گرفته می‌شه */
function _ihWMic(){
  var btn=document.getElementById('ih-mic-run'),host=document.getElementById('ih-mic');
  if(!btn)return;
  if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){btn.disabled=true;return _ihUnsupported('ih-mic-res')}
  var bars=[];
  for(var i=0;i<20;i++){var d=document.createElement('div');d.className='sl-ih-mic-b';d.style.height='4px';if(host)host.appendChild(d);bars.push(d)}
  btn.addEventListener('click',function(){
    btn.disabled=true;
    _ihSetRes('ih-mic-res','در حال گوش دادن…','');
    navigator.mediaDevices.getUserMedia({audio:true}).then(function(stream){
      var AC=window.AudioContext||window.webkitAudioContext;
      if(!AC){stream.getTracks().forEach(function(t){t.stop()});return _ihUnsupported('ih-mic-res')}
      var ctx=new AC(),src=ctx.createMediaStreamSource(stream),an=ctx.createAnalyser();
      an.fftSize=256;src.connect(an);
      var buf=new Uint8Array(an.frequencyBinCount),peak=0,raf=0,alive=true;
      function loop(){
        if(!alive)return;
        an.getByteFrequencyData(buf);
        var sum=0;for(var i=0;i<buf.length;i++)sum+=buf[i];
        var avg=sum/buf.length,lvl=Math.min(1,avg/70);
        peak=Math.max(peak,avg);
        for(var b=0;b<bars.length;b++){
          var on=lvl*bars.length>b;
          bars[b].style.height=(on?(6+lvl*58):4)+'px';
          bars[b].classList.toggle('on',on);
        }
        if(peak>14){
          _ihMarkAuto(JSON.stringify({peak_level:Math.round(peak)}));
          _ihSetRes('ih-mic-res','✅ میکروفون صدا رو دریافت می‌کنه','ok');
        }
        raf=requestAnimationFrame(loop);
      }
      loop();
      _ihWid.stop=function(){
        alive=false;if(raf)cancelAnimationFrame(raf);
        try{stream.getTracks().forEach(function(t){t.stop()})}catch(e){}
        try{ctx.close()}catch(e){}
      };
      setTimeout(function(){if(peak<=14&&alive)_ihSetRes('ih-mic-res','صدایی دریافت نشد — بلندتر حرف بزن یا دستی چک کن','bad')},6000);
    }).catch(function(){_ihUnsupported('ih-mic-res','اجازهٔ میکروفون داده نشد — دستی چک کن.')});
  });
}

/* ═══ اسپیکر — تن تولیدشده با Web Audio ═══
   ⚠️ سه علت واقعی «بعضی وقت‌ها کار می‌کنه» که این بازنویسی رفعشون می‌کنه:
   ۱) **نشتی AudioContext:** نسخهٔ قبلی روی *هر کلیک* یه `new AudioContext()`
      می‌ساخت و هیچ‌وقت نمی‌بستش (فقط آخری از طریق `_ihWid.stop` بسته می‌شد).
      WebKit روی iOS سقف سخت‌گیری برای تعداد AudioContext هم‌زمان داره (حدود ۴)؛
      بعد از چند بار زدن دکمه — یا ترکیب با تست میکروفون و صدای گردونه — ساخت
      Context بعدی بی‌صدا شکست می‌خورد. حالا یه Context مشترک ماژول‌سطح.
   ۲) **حالت suspended:** Context تازه روی iOS ممکنه `suspended` بمونه؛ قبلاً
      هیچ‌وقت `resume()` صدا زده نمی‌شد و زمان‌بندی از `currentTime` منجمد گرفته
      می‌شد → سکوت کامل بدون هیچ خطایی. حالا اول resume، بعد بررسی واقعی
      `state==='running'`، و زمان‌بندی از `currentTime` *بعد از* resume.
   ۳) **کلید بی‌صدای فیزیکی گوشی:** Web Audio روی iOS با کلید Silent قطع می‌شه —
      این از سمت وب اصلاً قابل‌دور‌زدن نیست، پس به‌جای وانمود کردن، صریح به کاربر
      یادآوری می‌شه (شایع‌ترین علت واقعی «صدا نیومد» روی آیفون). */
var _ihAudio=null;
function _ihAudioCtx(){
  var AC=window.AudioContext||window.webkitAudioContext;
  if(!AC)return null;
  if(!_ihAudio||_ihAudio.state==='closed'){try{_ihAudio=new AC()}catch(e){return null}}
  return _ihAudio;
}
function _ihWSpeaker(){
  var btn=document.getElementById('ih-sp-run');if(!btn)return;
  if(!(window.AudioContext||window.webkitAudioContext)){btn.disabled=true;return _ihUnsupported('ih-sp-res')}
  var playing=false;
  btn.addEventListener('click',function(){
    if(playing)return;
    var ctx=_ihAudioCtx();
    if(!ctx)return _ihUnsupported('ih-sp-res');
    playing=true;btn.disabled=true;
    _ihSetRes('ih-sp-res','در حال پخش…','');
    /* resume() باید داخل همین ژست کاربر صدا زده بشه؛ نتیجه‌اش هم واقعاً چک می‌شه */
    Promise.resolve(ctx.state==='suspended'?ctx.resume():null).catch(function(){}).then(function(){
      if(ctx.state!=='running'){
        playing=false;btn.disabled=false;
        _ihUnsupported('ih-sp-res','پخش صدا ممکن نشد — کلید بی‌صدای کنار گوشی رو چک کن و دوباره بزن.');
        return;
      }
      var osc=ctx.createOscillator(),g=ctx.createGain();
      osc.type='sine';osc.connect(g);g.connect(ctx.destination);
      var t=ctx.currentTime+.05, STEP=.55, TONES=[440,660,880], END=t+TONES.length*STEP;
      TONES.forEach(function(f,i){osc.frequency.setValueAtTime(f,t+i*STEP)});
      g.gain.setValueAtTime(.0001,t);
      g.gain.exponentialRampToValueAtTime(.3,t+.06);
      g.gain.setValueAtTime(.3,END-.12);
      g.gain.exponentialRampToValueAtTime(.0001,END);
      osc.start(t);osc.stop(END+.05);
      _ihMarkAssisted();
      _ihSetRes('ih-sp-res','🔊 سه تُن در حال پخشه — می‌شنوی؟','ok');
      osc.onended=function(){
        playing=false;btn.disabled=false;btn.textContent='🔊 پخش دوباره';
        try{osc.disconnect();g.disconnect()}catch(e){}
        _ihSetRes('ih-sp-res','پخش تموم شد — اگه چیزی نشنیدی، کلید بی‌صدا و بلندی صدا رو چک کن و دوباره بزن','ok');
      };
      /* شبکهٔ ایمنی: اگه onended (روی بعضی وب‌ویوها) اصلاً شلیک نشد، دکمه قفل نمونه */
      setTimeout(function(){ if(playing){playing=false;btn.disabled=false;btn.textContent='🔊 پخش دوباره'} },
                 (END-ctx.currentTime)*1000+800);
      /* Context مشترک عمداً بسته نمی‌شه (سقف iOS)؛ فقط نُودها متوقف می‌شن */
      _ihWid.stop=function(){try{osc.stop()}catch(e){}try{osc.disconnect();g.disconnect()}catch(e){}};
    });
  });
}

/* دوربین — پیش‌نمایش زنده */
function _ihWCamera(facing){
  var btn=document.getElementById('ih-cam-run'),vid=document.getElementById('ih-cam');
  if(!btn)return;
  if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){btn.disabled=true;return _ihUnsupported('ih-cam-res')}
  btn.addEventListener('click',function(){
    btn.disabled=true;
    _ihSetRes('ih-cam-res','در حال باز کردن دوربین…','');
    navigator.mediaDevices.getUserMedia({video:{facingMode:facing}}).then(function(stream){
      if(vid){vid.srcObject=stream;vid.style.display='block';try{vid.play()}catch(e){}}
      _ihMarkAssisted();
      _ihSetRes('ih-cam-res','✅ دوربین باز شد — تصویر رو ببین و نتیجه رو بزن','ok');
      _ihWid.stop=function(){try{stream.getTracks().forEach(function(t){t.stop()})}catch(e){}
        if(vid){vid.srcObject=null;vid.style.display='none'}};
    }).catch(function(){btn.disabled=false;_ihUnsupported('ih-cam-res','دسترسی به دوربین ممکن نشد — دستی چک کن.')});
  });
}

/* لرزش — داخل تلگرام از HapticFeedback، بیرونش از navigator.vibrate (iOS نداره) */
function _ihWHaptic(){
  var btn=document.getElementById('ih-hp-run');if(!btn)return;
  var tg=window._slTg,hasTg=!!(tg&&tg.HapticFeedback&&tg.HapticFeedback.impactOccurred);
  var hasVib=typeof navigator.vibrate==='function';
  if(!hasTg&&!hasVib){btn.disabled=true;return _ihUnsupported('ih-hp-res','لرزش از داخل مرورگر روی این دستگاه قابل اجرا نیست — دستی چک کن.')}
  btn.addEventListener('click',function(){
    try{
      if(hasTg){['light','medium','heavy'].forEach(function(st,i){setTimeout(function(){tg.HapticFeedback.impactOccurred(st)},i*260)})}
      else navigator.vibrate([120,90,120,90,200]);
      _ihMarkAssisted();
      _ihSetRes('ih-hp-res','📳 لرزش اجرا شد — حسش کردی؟','ok');
    }catch(e){_ihUnsupported('ih-hp-res')}
  });
}

/* ─── پردهٔ ۳: شناسنامهٔ سلامت ────────────────────────────────────────────
   شماتیک رنگ‌شده وسط = خودِ گزارش. سه سطح اعتبار صریح نمایش داده می‌شن —
   همون چیزی که گزارش رو از «کاربر گفت سالمه» به «سیستم اندازه گرفت» می‌بره. */
function _ihFinalize(){
  _ihStopWidget();
  _ihShell(skel(3),97,false);
  _ihPost('/health/report/'+_ihS.report.id+'/finalize',{}).then(function(d){
    _ihRenderCert(d.result);
  }).catch(function(){_ihShell(err('خطا در محاسبهٔ گزارش'),100,false)});
}

function _ihRenderCert(res){
  var s=_ihS;
  /* part_status از سرور میاد (نه از حافظهٔ کلاینت) — یعنی حتی اگه کاربر وسط کار
     اپ رو بسته و بعد ادامه داده باشه، شماتیک نهایی کامل و درست رنگ می‌شه. */
  s.parts=res.part_status||{};
  s.side='front';
  var v=res.verdict||{},ev=res.evidence_counts||{};
  var evLv=(s.data.evidence_levels)||{};
  var evChips=['auto','assisted','manual'].filter(function(k){return ev[k]>0}).map(function(k){
    var m=evLv[k]||{};
    return '<span class="sl-ih-ev'+(k==='auto'?' auto':'')+'">'+esc(m.icon||'')+' '+fmt(ev[k])+' '+esc(m.label||k)+'</span>';
  }).join('');

  var alerts='';
  (res.dealbreakers||[]).forEach(function(d){
    alerts+='<div class="sl-ih-alert bad">🚨 '+esc(d.title)+'</div>';
  });
  (res.contradictions||[]).forEach(function(c){
    alerts+='<div class="sl-ih-alert warn">⚠️ '+esc(c)+'</div>';
  });

  var cats=(res.category_summary||[]).map(function(c){
    var ic=c.issue>0?'⚠️':'✅';
    return '<div style="display:flex;justify-content:space-between;padding:9px 2px;border-bottom:1px solid var(--iv-line);font-size:12.5px">'+
      '<span>'+esc(c.icon)+' '+esc(c.title)+'</span>'+
      '<span style="font-weight:800;color:'+(c.issue>0?'var(--ih-bad)':'var(--ih-ok)')+'">'+ic+' '+
      (c.issue>0?fmt(c.issue)+' مورد':'سالم')+'</span></div>';
  }).join('');

  var html=
    '<div class="sl-ih-cert">'+
      '<div style="font-size:12.5px;font-weight:800;color:var(--iv-mu)">🛡 شناسنامهٔ سلامت Stland</div>'+
      '<div style="font-size:16px;font-weight:800;margin-top:4px">'+esc((s.model&&s.model.name)||'')+
        (s.capacity_label?' · '+esc(s.capacity_label):'')+'</div>'+
      _ihSchematic()+
      '<div class="sl-ih-legend"><span><i style="background:var(--ih-ok)"></i>سالم</span>'+
        '<span><i style="background:var(--ih-bad)"></i>مشکل</span>'+
        '<span><i style="background:var(--ih-idle)"></i>بررسی‌نشده</span></div>'+
      '<div class="sl-ih-scores">'+
        '<div class="sl-ih-score"><div class="sl-ih-score-n" style="color:'+
          (res.health_score==null?'var(--iv-mu)':(res.health_score>=85?'var(--ih-ok)':(res.health_score>=65?'#FF9F0A':'var(--ih-bad)')))+'">'+
          (res.health_score==null?'—':fmt(res.health_score))+'</div>'+
          '<div class="sl-ih-score-l">امتیاز سلامت</div></div>'+
        '<div class="sl-ih-score"><div class="sl-ih-score-n">'+fmt(res.completeness_pct||0)+'٪</div>'+
          '<div class="sl-ih-score-l">کامل بودن گزارش</div></div>'+
      '</div>'+
      '<div style="font-size:15px;font-weight:800;margin-top:14px">'+esc(v.emoji||'')+' '+esc(v.text||'')+'</div>'+
      (evChips?'<div class="sl-ih-evrow">'+evChips+'</div>':'')+
      '<div class="sl-ih-code">'+esc(res.tracking_code||'')+'</div>'+
    '</div>'+
    alerts+
    (cats?'<div class="sl-ih-w"><div class="sl-ih-w-t">جزئیات فصل‌ها</div>'+cats+'</div>':'')+
    /* یادداشت حالت سریع — متنش از سرور میاد (تک‌منبع مشترک با بات/صفحهٔ عمومی)
       و فقط وقتی گزارش واقعاً quick بوده نشون داده می‌شه (بخش ۹۹.۱۳). */
    (res.quick_note?'<div class="sl-ih-quicknote">ℹ️ '+esc(res.quick_note)+'</div>':'')+
    '<div class="sl-iv-cta-row" style="display:flex;gap:8px;margin-top:6px">'+
      '<button class="sl-iv-cta-sm" id="ih-c-val" style="flex:1">💰 قیمت منصفانه</button>'+
      '<button class="sl-iv-cta-sm" id="ih-c-share" style="flex:1">🔗 اشتراک‌گذاری</button>'+
    '</div>'+
    '<button class="sl-iv-cta-ghost" id="ih-c-hist" style="width:100%;margin-top:10px">📜 گزارش‌های قبلی من</button>'+
    '<button class="sl-iv-cta-ghost" id="ih-c-new" style="width:100%">🔄 تست تازه</button>';

  _ihShell(html,100,false);
  _ihPaint(null);

  var val=document.getElementById('ih-c-val');
  if(val)val.addEventListener('click',function(){
    /* پل به کارشناسی قیمت — prefill از سرور میاد (همون منطقی که ربات استفاده
       می‌کنه، iphone_health.engine.derive_valuation_prefill) تا سؤال‌هایی که
       تست سلامت قبلاً جوابشون رو گرفته دوباره پرسیده نشن. */
    _ihHaptic();
    if(typeof openIVWizard==='function')openIVWizard({
      model_id:s.model&&s.model.id,capacity_label:s.capacity_label||'',prefill:res.prefill||{}
    });
  });
  var sh=document.getElementById('ih-c-share');
  if(sh)sh.addEventListener('click',function(){
    var txt='🛡 شناسنامهٔ سلامت '+((s.model&&s.model.name)||'آیفون')+
      '\nامتیاز سلامت: '+(res.health_score==null?'—':res.health_score)+' از ۱۰۰'+
      '\nکامل بودن گزارش: '+(res.completeness_pct||0)+'٪'+
      '\nکد رهگیری: '+(res.tracking_code||'')+
      /* 🔴 `location.origin` نه دامنهٔ هاردکد: اپ ممکن است روی دامنهٔ
         اختصاصی خودش (app.stland.ir) سرو شود و لینکی که کاربر برای خریدار
         می‌فرستد نباید هاست پنل مدیریت را نشان بدهد. */
      (res.tracking_code?'\nمشاهدهٔ آنلاین: '+stlhIpt.cert+(stlhIpt.cert.indexOf('?')<0?'?':'&')+'code='+res.tracking_code:'')+
      '\n\nتست سلامت آیفون رایگان در Stland';
    _iptShare(txt,'');
  });
  var hs=document.getElementById('ih-c-hist');
  if(hs)hs.addEventListener('click',openIHHistory);
  var nw=document.getElementById('ih-c-new');
  if(nw)nw.addEventListener('click',function(){_ihS=_ihFresh();_ihS.data=s.data;_ihStepSeries()});
}

function openIHHistory(){
  _accPopup('گزارش‌های سلامت من',skel(3));
  var pb=document.getElementById('post-body');if(pb)pb.classList.add('sl-iv-host');
  slRequireLogin(_accBody,'گزارش‌های سلامت به همین دستگاه/حساب وصل است.').then(function(ok){
  if(!ok)return;
  api('/health/history',true).then(function(d){
    var b=_accBody();if(!b)return;
    var items=(d&&d.items)||[];
    if(!items.length){b.innerHTML='<div class="sl-iv sl-ih"><div class="sl-empty" style="padding:70px 16px"><span class="sl-empty-e">📜</span>هنوز گزارشی ثبت نکردی</div></div>';return}
    b.innerHTML='<div class="sl-iv sl-ih"><div class="sl-iv-body">'+items.map(function(r){
      return '<div class="sl-ih-w" style="text-align:right">'+
        '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px">'+
          '<span style="font-size:13px;font-weight:800">'+esc(r.model_name||'—')+'</span>'+
          '<span class="sl-ih-code" style="margin:0">'+esc(r.tracking_code)+'</span></div>'+
        '<div style="font-size:12px;color:var(--iv-mu);margin-top:8px;line-height:1.9">'+
          esc(r.verdict||'')+'<br>امتیاز: <b>'+(r.health_score==null?'—':fmt(r.health_score))+'</b>'+
          ' · کامل بودن: <b>'+fmt(r.completeness_pct||0)+'٪</b></div></div>';
    }).join('')+'</div></div>';
  }).catch(function(){var b=_accBody();if(b)b.innerHTML=err('خطا در دریافت تاریخچه')});
  });
}
window.openIHHistory=openIHHistory;

/* ═══ مقایسهٔ دو آیفون (تب «ابزار مفید») ═══
   مرجع داده مشخصات رسمی اپل است (iv_specs؛ seed + ایمپورت سروری + ویرایش پنل).
   A همیشه اولین عنصر DOM است، پس در RTL سمت راست می‌نشیند — هم در سربرگ هم در
   ردیف‌ها یکی است تا ستون‌ها روی هم بیفتند. رقم فارسی مرکزی است (قاعدهٔ ۵۱) و
   نام مدل/تراشه که حرف لاتین دارند لاتین می‌مانند — این‌جا تبدیل دستی نمی‌کنیم. */
var _cmpModels=null;
function _cmpImg(m){
  if(m&&m.image_url)return '<img class="sl-cmp-img" src="'+esc((window._slThumb||function(u){return u;})(m.image_url,320))+'" alt="" loading="lazy">';
  return '<div class="sl-cmp-img sl-cmp-img-ph" aria-hidden="true">📱</div>';
}
function _cmpRender(d){
  var head='<div class="sl-cmp-head">'+
    '<div class="sl-cmp-col">'+_cmpImg(d.a)+'<div class="sl-cmp-name no-fa">'+esc(d.a.name)+'</div></div>'+
    '<div class="sl-cmp-col">'+_cmpImg(d.b)+'<div class="sl-cmp-name no-fa">'+esc(d.b.name)+'</div></div>'+
  '</div>';
  var rows=(d.fields||[]).map(function(f){
    return '<div class="sl-cmp-row'+(f.diff?' is-diff':'')+'">'+
      '<div class="sl-cmp-lbl">'+esc(f.label)+'</div>'+
      '<div class="sl-cmp-vals">'+
        /* مشخصات فنی (Hz/MP/ppi) یک‌دست لاتین — بخش ۲۹۱: «تطبیقی ۱-120Hz» قاطی می‌شد */
        '<div class="sl-cmp-v no-fa" dir="auto">'+(f.a?esc(f.a):'—')+'</div>'+
        '<div class="sl-cmp-v no-fa" dir="auto">'+(f.b?esc(f.b):'—')+'</div>'+
      '</div></div>';
  }).join('');
  return head+'<div class="sl-cmp-rows">'+rows+'</div>'+
    '<div class="sl-cmp-src no-fa">مرجع مشخصات: Apple</div>';
}
function _cmpRun(){
  var a=document.getElementById('cmp-a'),b=document.getElementById('cmp-b'),
      out=document.getElementById('cmp-out');
  if(!a||!b||!out)return;
  if(a.value===b.value){out.innerHTML='<div class="sl-cmp-hint">دو مدل متفاوت انتخاب کنید.</div>';return;}
  out.innerHTML='<div class="sl-cmp-hint">در حال مقایسه…</div>';
  api('/iphone/compare?a='+encodeURIComponent(a.value)+'&b='+encodeURIComponent(b.value))
    .then(function(d){out.innerHTML=(d&&d.ok)?_cmpRender(d):'<div class="sl-cmp-hint">خطا در دریافت مقایسه.</div>';})
    .catch(function(){out.innerHTML='<div class="sl-cmp-hint">خطا در دریافت مقایسه.</div>';});
}
function openCompare(){
  _accPopup('مقایسه دو آیفون',
    '<div class="sl-cmp">'+
      '<div class="sl-cmp-pick">'+
        '<select class="sl-cmp-sel no-fa" id="cmp-a"></select>'+
        '<span class="sl-cmp-vs">در برابر</span>'+
        '<select class="sl-cmp-sel no-fa" id="cmp-b"></select>'+
      '</div>'+
      '<div id="cmp-out" class="sl-cmp-out">'+(window._slSkel?window._slSkel(2):'')+'</div>'+
    '</div>');
  var fill=function(){
    var ms=_cmpModels||[],out=document.getElementById('cmp-out');
    if(ms.length<2){if(out)out.innerHTML='<div class="sl-cmp-hint">فعلاً مدلی برای مقایسه ثبت نشده است.</div>';return;}
    var opts=ms.map(function(m){return '<option value="'+m.id+'">'+esc(m.name)+'</option>';}).join('');
    var a=document.getElementById('cmp-a'),b=document.getElementById('cmp-b');
    if(!a||!b)return;
    a.innerHTML=opts;b.innerHTML=opts;a.selectedIndex=0;b.selectedIndex=1;
    /* سایت: از صفحه‌ی هر آیفون با ?a=<نامِ مدل> همان مدل از پیش انتخاب است، در برابرِ مدلِ کناری‌اش */
    try{var want=(new URLSearchParams(location.search).get('a')||'').toLowerCase();
      for(var wi=0;want&&wi<ms.length;wi++){if(String(ms[wi].name).toLowerCase()===want){a.selectedIndex=wi;b.selectedIndex=wi+1<ms.length?wi+1:wi-1;break;}}}catch(e){}
    a.onchange=b.onchange=_cmpRun;
    _cmpRun();
  };
  if(_cmpModels){fill();return;}
  api('/iphone/spec-models').then(function(d){_cmpModels=(d&&d.models)||[];fill();})
    .catch(function(){var o=document.getElementById('cmp-out');if(o)o.innerHTML='<div class="sl-cmp-hint">خطا در بارگذاری مدل‌ها.</div>';});
}

window.openCompare=openCompare;
/* «مشاهدهٔ آنلاین»ِ شناسنامه و پیوندهای دیگر صفحه‌ها همین ابزارها را باز می‌کنند */
function _iptBoot(){
  if(!document.getElementById('post-body'))return;
  _iptHome();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',_iptBoot);else _iptBoot();
})();
