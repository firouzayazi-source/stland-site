/*!
 * گردونه‌ی شانسِ استوک لند — کدِ گردونه و «جوایز من» عیناً از مینی‌اپِ پروژه‌ی ربات (stockland-bot، app/app.js)؛ همان چیدمان،
 * انیمیشن، صدا و متن‌ها (صاحب فروشگاه، ۶ آذر ۱۴۰۵: «گردونه شانس را با تمامِ متعلقاتش بیار»). این بالا فقط «سازگارکننده» است:
 * پنجره‌ی مینی‌اپ ← کادرِ همین برگه، `/api/v1/...` ← `wp-json/stland/v1/wheel/...` با نشستِ وردپرس، حسابِ تلگرام ← حسابِ سایت،
 * لرزشِ تلگرام ← لرزشِ گوشی. انتخابِ جایزه ۱۰۰٪ سمتِ سرور است (includes/wheel.php).
 */
(function(){
'use strict';
var C=window.stlhWheel||{rest:'/wp-json/stland/v1/wheel',nonce:'',login:'/my-account/',home:'/',title:'گردونه شانس',logged:false};
var initData='';
var _FA_DIGITS=['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
/* `fetch` همین بسته: مسیرِ /api/v1 ربات ← REST وردپرس با نشانه‌ی نشست؛ نشانه‌ی کهنه یک بار تازه می‌شود */
var _realFetch=window.fetch.bind(window);
function _wpUrl(u){var p=u.slice(7);if(p.indexOf('/wheel/')===0)p=p.slice(6);return C.rest+p}
function _freshNonce(){return _realFetch((C.ajax||'/wp-admin/admin-ajax.php')+'?action=rest-nonce',{credentials:'same-origin'}).then(function(r){return r.ok?r.text():''}).then(function(t){if(/^[a-f0-9]{6,}$/.test(t))C.nonce=t})}
var fetch=function(u,o){
  if(typeof u!=='string'||u.indexOf('/api/v1')!==0)return _realFetch(u,o);
  o=o||{};var url=_wpUrl(u);
  var go=function(){var h=Object.assign({},o.headers||{});h['X-WP-Nonce']=C.nonce;return _realFetch(url,Object.assign({},o,{headers:h,credentials:'same-origin'}))};
  return go().then(function(r){
    if(r.status!==403)return r;
    return r.clone().json().catch(function(){return {}}).then(function(j){
      if(j&&j.code==='rest_cookie_invalid_nonce')return _freshNonce().then(go);
      return r;
    });
  });
};
window._slTg={HapticFeedback:{selectionChanged:function(){try{navigator.vibrate&&navigator.vibrate(4)}catch(e){}},impactOccurred:function(){try{navigator.vibrate&&navigator.vibrate(18)}catch(e){}}}};
window._slApp={popup:{open:function(){},close:function(){window.openWheel()}},dialog:{alert:function(m){window.alert(m)}}};
function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
function fmt(n){return Number(n).toLocaleString('fa-IR')}
function faNum(s){return String(s==null?'':s).replace(/[0-9]/g,function(d){return _FA_DIGITS[+d]})}
function jalaliDate(s){if(!s)return '—';var str=String(s).trim().replace(' ','T');if(!/(?:[zZ]|[+\-]\d{2}:?\d{2})$/.test(str))str+='Z';var d=new Date(str);return isNaN(d)?String(s):d.toLocaleDateString('fa-IR',{timeZone:'Asia/Tehran'})}
function err(m){if(!m||m==='خطا')m='بارگذاری انجام نشد؛ اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.';return '<div class="sl-empty"><span class="sl-empty-e">⚠️</span>'+esc(m)+'</div>'}
function skel(n){var o='';for(var i=0;i<(n||3);i++)o+='<div class="sl-skel"><div class="b w60"></div><div class="b w90"></div><div class="b w40"></div></div>';return o}
function thumb(u){return u}
function openP(pid){location.href=C.home+'?post_type=product&p='+encodeURIComponent(pid)}
function openC(){location.href=C.home}
/* پنجره‌ی مینی‌اپ ← سرِ همین کادر؛ «جوایز من» دکمه‌ی بازگشت به گردونه دارد */
function _accPopup(title,html){
  var t=document.getElementById('post-title'),b=document.getElementById('post-body');
  if(!t||!b)return;
  var sub=title&&title!=='گردونهٔ شانس';
  t.innerHTML=sub?'<button type="button" class="stlh-wh-back">‹ بازگشت به گردونه</button><span>'+esc(title)+'</span>':'<h1>'+esc(C.title||title)+'</h1>';
  var bk=t.querySelector('.stlh-wh-back');if(bk)bk.addEventListener('click',function(){window.openWheel()});
  b.className='stlh-wh-body';
  b.innerHTML='<div class="sl-acc-page">'+html+'</div>';
  var r=document.querySelector('.stlh-wheel');if(r&&r.getBoundingClientRect().top<0)r.scrollIntoView({block:'start'});
}
function _accBody(){return document.querySelector('#post-body .sl-acc-page')}
function api(p){
  return fetch('/api/v1'+p,{headers:{'Accept':'application/json'}}).then(function(r){
    if(r.ok)return r.json();
    return r.json().catch(function(){return {}}).then(function(d){var e=new Error((d&&(d.detail||d.error))||'ارتباط با سرور برقرار نشد؛ دوباره تلاش کنید.');e.status=r.status;throw e});
  });
}
/* «اول وارد شوید» — گردونه فقط برای حسابِ کاربریِ سایت است (جایزه به همان حساب می‌چسبد) */
function slRequireAccount(box){
  if(C.logged)return Promise.resolve(true);
  if(typeof box==='function')box=box();
  if(box)box.innerHTML='<div class="sl-wg"><div class="sl-wg-empty stlh-wh-login"><div class="stlh-wh-login-ic">🎡</div>'+
    '<b>برای چرخاندنِ گردونه وارد حسابِ کاربری‌تان شوید</b>'+
    '<span>جایزه‌ها (کدِ تخفیف و هدیه) به حسابِ شما می‌چسبد و در خرید از همین سایت استفاده می‌شود.</span>'+
    '<a class="sl-wg-cta stlh-wh-login-btn" href="'+esc(C.login)+'">ورود یا ثبت‌نام</a></div></div>';
  return Promise.resolve(false);
}

/* ════════════ کدِ مینی‌اپِ ربات (عیناً) ════════════ */
/* ─── صدا (WebAudio تولیدشده، بدون فایل باینری/CDN خارجی) ───
   بازطراحی کامل (بخش «تجربهٔ Spin») — دیگه هیچ صدای Game Show/فانفار جایزه نیست؛
   فقط یه Tick فوق‌ریز هم‌زمان با هر عبور از Segment (دقیقاً حس Picker/Time Wheel
   آیفون) + یه Feedback بسیار ظریف برای توقف نهایی. */
var _wheelAudioCtx=null;
function _wheelBeep(freq,dur,type,vol){
  try{
    if(!_wheelAudioCtx)_wheelAudioCtx=new (window.AudioContext||window.webkitAudioContext)();
    var ctx=_wheelAudioCtx;
    if(ctx.state==='suspended')ctx.resume();
    var o=ctx.createOscillator(),g=ctx.createGain();
    o.type=type||'sine';o.frequency.value=freq;
    var peak=vol!=null?vol:.16;
    g.gain.setValueAtTime(.0001,ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(peak,ctx.currentTime+.006);
    g.gain.exponentialRampToValueAtTime(.0001,ctx.currentTime+dur);
    o.connect(g);g.connect(ctx.destination);
    o.start();o.stop(ctx.currentTime+dur);
  }catch(err){}
}
/* Tick میکروسکوپی هر عبور از Segment — نه موسیقی، نه صدای بلند؛ فرکانس بالا +
   مدت خیلی کوتاه، دقیقاً حس «تیک» مکانیکی Picker، نه یه نُت موسیقیایی. */
function _wheelTickPulse(hapticOn,soundOn){
  if(hapticOn){
    try{var tg=window._slTg;if(tg&&tg.HapticFeedback&&tg.HapticFeedback.selectionChanged)tg.HapticFeedback.selectionChanged()}catch(err){}
  }
  if(soundOn)_wheelBeep(1600,.014,'sine',.05);
}
/* یک Feedback بسیار ظریف برای توقف نهایی — نه صدای جایزه، نه Game Show. */
function _wheelStopPulse(hapticOn,soundOn){
  if(hapticOn){
    try{var tg=window._slTg;if(tg&&tg.HapticFeedback&&tg.HapticFeedback.impactOccurred)tg.HapticFeedback.impactOccurred('soft')}catch(err){}
  }
  if(soundOn)_wheelBeep(480,.05,'sine',.08);
}
/* ─── همون منحنی cubic-bezier ترنزیشن CSS چرخ (app.css: .sl-wg-disc transition) —
   عمداً این‌جا هم به‌صورت ثابت تکرار شده تا شبیه‌سازی JS دقیقاً با انیمیشن واقعی
   یکی باشه؛ اگه یه‌روز easing چرخ توی CSS عوض شد، این مقادیر هم باید هماهنگ عوض
   بشن. الگوریتم: حل عددی Newton-Raphson برای x(u)=t، همون روش استاندارد UnitBezier. */
var _WG_EASE=[.16,.73,.12,1];
function _wgBezierY(t,x1,y1,x2,y2){
  function A(a1,a2){return 1-3*a2+3*a1}
  function B(a1,a2){return 3*a2-6*a1}
  function C(a1){return 3*a1}
  function bx(u){return ((A(x1,x2)*u+B(x1,x2))*u+C(x1))*u}
  function by(u){return ((A(y1,y2)*u+B(y1,y2))*u+C(y1))*u}
  function bxd(u){return 3*A(x1,x2)*u*u+2*B(x1,x2)*u+C(x1)}
  var u=Math.max(0,Math.min(1,t));
  for(var i=0;i<8;i++){
    var d=bxd(u);
    if(Math.abs(d)<1e-6)break;
    var diff=bx(u)-t;
    if(Math.abs(diff)<1e-5)break;
    u-=diff/d;
  }
  return by(Math.max(0,Math.min(1,u)));
}
/* موتور Tick — به‌جای یه تایمر ثابت (که هیچ ربطی به سرعت واقعی چرخ نداشت)، هر
   فریم موقعیت واقعی چرخ رو (از روی همون منحنی easingِ انیمیشن CSS) محاسبه می‌کنه
   و دقیقاً هر بار که از یه مرز Segment رد شد، یک Tick می‌زنه — یعنی وقتی چرخ سریعه
   Tickها فشرده‌ترن، وقتی کند می‌شه (نزدیک پایان) Tickها با فاصلهٔ بیشتر میان،
   دقیقاً هم‌زمان با موقعیت واقعی، نه یه Timer جدا. */
var _wgTickRAF=null;
function _wheelDriveTicks(base,fin,durationMs,segCount,hapticOn,soundOn,onDone){
  var segStep=360/Math.max(1,segCount);
  var startTs=null;
  var lastSeg=Math.floor(base/segStep);
  function frame(ts){
    if(!startTs)startTs=ts;
    var elapsed=ts-startTs;
    var t=Math.min(1,elapsed/durationMs);
    var eased=_wgBezierY(t,_WG_EASE[0],_WG_EASE[1],_WG_EASE[2],_WG_EASE[3]);
    var angle=base+eased*(fin-base);
    var seg=Math.floor(angle/segStep);
    if(seg>lastSeg){
      var jump=Math.min(seg-lastSeg,6); // سقف ایمنی برای جهش چند-Segmentی فریم‌های اول (شتاب زیاد)
      for(var i=0;i<jump;i++)_wheelTickPulse(hapticOn,soundOn);
      lastSeg=seg;
    }
    if(t<1){_wgTickRAF=requestAnimationFrame(frame)}
    else{_wgTickRAF=null;onDone&&onDone()}
  }
  _wgTickRAF=requestAnimationFrame(frame);
}
function _wheelStopTicks(){if(_wgTickRAF){cancelAnimationFrame(_wgTickRAF);_wgTickRAF=null}}

/* ─── کانفتی سبک، بدون کتابخانهٔ خارجی ─── */
function _wheelConfetti(container){
  if(!container)return;
  var canvas=document.createElement('canvas');
  canvas.className='sl-wheel-confetti';
  container.appendChild(canvas);
  var ctx=canvas.getContext('2d');
  var w=canvas.width=container.clientWidth+80,h=canvas.height=container.clientHeight+80;
  var colors=['#F59E0B','#EF4444','#22C55E','#6366F1','#EC4899','#06B6D4'];
  var pieces=[];
  for(var i=0;i<70;i++){
    pieces.push({x:w/2,y:h*0.4,vx:(Math.random()-.5)*11,vy:-Math.random()*9-4,
      size:5+Math.random()*5,color:colors[i%colors.length],rot:Math.random()*360,
      vr:(Math.random()-.5)*22,life:0});
  }
  function step(){
    ctx.clearRect(0,0,w,h);
    var alive=false;
    pieces.forEach(function(p){
      if(p.life>85)return;
      alive=true;
      p.vy+=.35;p.x+=p.vx;p.y+=p.vy;p.rot+=p.vr;p.life++;
      ctx.save();ctx.translate(p.x,p.y);ctx.rotate(p.rot*Math.PI/180);
      ctx.fillStyle=p.color;ctx.globalAlpha=Math.max(0,1-p.life/85);
      ctx.fillRect(-p.size/2,-p.size/2,p.size,p.size*.6);
      ctx.restore();
    });
    if(alive)requestAnimationFrame(step);else canvas.remove();
  }
  requestAnimationFrame(step);
}

function _wheelDeviceFp(){
  try{
    var k='sl_device_fp',v=localStorage.getItem(k);
    if(!v){v='fp_'+Math.random().toString(36).slice(2)+Date.now().toString(36);localStorage.setItem(k,v)}
    return v;
  }catch(err){return ''}
}

/* شناسهٔ یکتای هر تلاش واقعی چرخش — یه‌بار per کلیک ساخته می‌شه و فقط برای همون
   fetch (و ریترای شبکهٔ همون fetch) استفاده می‌شه؛ ذخیره/تکرار نمی‌شه. Backend
   با همین شناسه دبل‌کلیک/ریترای شبکه رو idempotent می‌کنه (core/wheel.py). */
function _wheelReqId(){
  if(window.crypto&&crypto.randomUUID)try{return crypto.randomUUID()}catch(e){}
  return 'sp_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2)+'_'+Math.random().toString(36).slice(2);
}

var _wheelSpinning=false,_wheelRotation=0;

function _wheelSegAngles(n){
  var step=360/n,arr=[];
  for(var i=0;i<n;i++)arr.push({start:i*step,end:(i+1)*step,mid:i*step+step/2});
  return arr;
}

/* ═══ آیکون‌های SVG جایزه (بدون ایموجی خام — طبق درخواست redesign) ═══ */
function _wgIcon(type){
  var s='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">';
  var p={
    wallet_credit:'<rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M3 9h18"/><circle cx="16.5" cy="12.5" r="1.3" fill="currentColor" stroke="none"/>',
    discount_percent:'<path d="M8 8h.01M16 16h.01M7 17L17 7"/><circle cx="7.5" cy="7.5" r="2"/><circle cx="16.5" cy="16.5" r="2"/>',
    discount_fixed:'<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0l-7-7a2 2 0 0 1-.6-1.4V5a2 2 0 0 1 2-2h6.2a2 2 0 0 1 1.4.6l7 7a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.1" fill="currentColor" stroke="none"/>',
    extra_spin:'<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v4h-4"/>',
    physical_gift:'<rect x="3.5" y="9" width="17" height="12" rx="1.6"/><path d="M3.5 13h17M12 9v12"/><path d="M12 9S10.5 4.5 8 5.2 8.8 9 12 9Zm0 0s1.5-4.5 4-3.8S15.2 9 12 9Z"/>',
    no_win:'<circle cx="12" cy="12" r="9"/><path d="M9 15s1-1.4 3-1.4S15 15 15 15M9 9h.01M15 9h.01"/>'
  }[type]||'<path d="M12 3l2.6 5.6 6 .7-4.4 4.1 1.2 6-5.4-3-5.4 3 1.2-6L3.4 9.3l6-.7Z"/>';
  return s+p+'</svg>';
}
/* اگه ادمین برای این جایزه تصویر/Illustration آپلود کرده باشه (image_url)، همون
   نشون داده می‌شه؛ وگرنه fallback به آیکون SVG بر پایهٔ نوع جایزه — هیچ‌وقت جای
   خالی نمی‌مونه. */
function _wgMedia(p,size){
  // size خالی/۰ یعنی سایز کاملاً با CSS کلاس والد کنترل بشه (نه inline) — لازم برای
  // Segmentهای گردونه که سایز آیکون باید با دانسیتی تعداد جایزه (sl-wg-d7/d9) عوض
  // بشه؛ inline width اون override رو خنثی می‌کرد.
  var sizeStyle=size?('width:'+size+'px;height:'+size+'px;'):'';
  if(p.image_url)return '<img src="'+esc(thumb(p.image_url,200))+'" alt="" style="'+sizeStyle+'object-fit:contain;display:block" loading="lazy" decoding="async">';
  return _wgIcon(p.prize_type);
}
function _wgStar(){return '<svg viewBox="0 0 24 24" fill="#8A5E12"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 18.9 6.1 20.6l1.3-6.6-4.9-4.6 6.6-.8z"/></svg>'}
function _wgPtr(){return '<svg viewBox="0 0 26 34"><defs><linearGradient id="wgptr" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FCE08A"/><stop offset="1" stop-color="#E29B33"/></linearGradient></defs><path d="M13 33 3 12a10 10 0 1 1 20 0Z" fill="url(#wgptr)" stroke="#8A5E12" stroke-width="1"/><circle cx="13" cy="11" r="3.4" fill="#0A0918"/></svg>'}

/* برچسب بزرگ ارزش جایزه (برای مودال) */
function _wgValueBig(p){
  if(p.prize_type==='wallet_credit')return {v:fmt(p.amount||0)+' <small style="font-size:16px">تومان</small>',plain:false};
  if(p.prize_type==='extra_spin')return {v:'چرخش اضافه',plain:true};
  if(p.prize_type==='no_win')return {v:'این‌بار شانس نیاوردی',plain:true};
  return {v:esc(p.title||''),plain:false};
}

/* ═══ باز کردن گردونه — چیدمان کامل Premium ═══ */
function openWheel(){
  _accPopup('گردونهٔ شانس',skel(3));
  var pb=document.getElementById('post-body');if(pb)pb.classList.add('sl-wg-host');
  slRequireAccount(_accBody,'جایزهٔ گردونه به کیف‌پول و کدهای تخفیف شما می‌نشیند، پس اول ثبت‌نام کنید.').then(function(ok){
  if(!ok)return;
  api('/wheel/state',true).then(function(d){
    var b=_accBody();if(!b)return;
    if(!d.enabled||!d.campaign||!d.prizes||!d.prizes.length){
      b.innerHTML='<div class="sl-wg"><div class="sl-wg-empty" style="padding:80px 24px">🎡 گردونهٔ شانس فعلاً غیرفعاله — به‌زودی برگرد!</div></div>';
      return;
    }
    renderWheelPage(b,d);
  }).catch(function(){var b=_accBody();if(b)b.innerHTML='<div class="sl-wg"><div class="sl-wg-empty" style="padding:80px 24px">خطا در دریافت گردونه</div></div>'});
  });
}
window.openWheel=openWheel;

function renderWheelPage(container,state){
  var s=state.settings||{};
  var rem=state.spins_remaining;
  var badge=rem<0?'∞':fmt(Math.max(0,rem));
  container.innerHTML=
    '<div class="sl-wg">'+
      '<div class="sl-wg-hero">'+
        '<div class="sl-wg-hero-ic">'+_wgIcon('extra_spin')+'</div>'+
        '<div class="sl-wg-hero-tx"><div class="sl-wg-hero-t">'+esc(state.campaign.title||'گردونهٔ شانس')+'</div>'+
          '<div class="sl-wg-hero-s">'+esc(s.description||'هر روز یک شانس رایگان برای بردن جایزه!')+'</div></div>'+
        '<div class="sl-wg-hero-badge"><b>'+badge+'</b><span>چرخش امروز</span></div>'+
      '</div>'+
      '<div class="sl-wg-stage" id="wg-stage"></div>'+
      '<button class="sl-wg-cta" id="wg-cta"></button>'+
      '<div class="sl-wg-cta-sub" id="wg-cta-sub"></div>'+
      '<div class="sl-wg-sec" id="wg-rewards" style="display:none">'+
        '<div class="sl-wg-sec-h"><b>🎁 جوایز من</b><a href="#" id="wg-rewards-all">مشاهده همه</a></div>'+
        '<div id="wg-rewards-list"></div>'+
      '</div>'+
      '<div class="sl-wg-sec" id="wg-activity" style="display:none">'+
        '<div class="sl-wg-sec-h"><b>فعالیت اخیر</b></div>'+
        '<div id="wg-activity-list"></div>'+
      '</div>'+
    '</div>';
  buildWheel(document.getElementById('wg-stage'),state);
  wireCta(container,state);
  loadRewardsPreview();
  loadActivity();
}

/* ساخت گردونهٔ چندلایه — Typography برچسب‌ها (بخش «اصلاح Typography گردونه»،
   ۲۰۲۶-۰۸-۰۹): هر Segment می‌چرخه ولی محتواش (.sl-wg-lbl-in) دقیقاً به همون
   اندازه Counter-Rotate می‌شه (mid و بعد -mid → مجموع صفر روی صفحه) تا آیکون+متن
   همیشه افقی/خوانا بمونن، مستقل از زاویهٔ Segment — نه شعاعی/چرخیده. عرض/فونت
   برچسب با دانسیتی تعداد جایزه (نه طول متن خاص) هماهنگ می‌شه چون خودِ Segment با
   افزایش تعداد باریک‌تر می‌شه. متن اولویت با wheel_label (برچسب کوتاه ادمین) —
   سرور همیشه fallback به title رو از قبل حل کرده، این‌جا فقط نمایشه. */
function buildWheel(stage,state){
  var prizes=state.prizes,n=prizes.length,step=360/n;
  var seg=_wheelSegAngles(n);
  var grad=prizes.map(function(p,i){return esc(p.color)+' '+(i*step)+'deg '+((i+1)*step)+'deg'}).join(',');
  var stageW=Math.min(window.innerWidth*0.8,320);
  var labelR=Math.round((stageW-28)*0.30);
  var densityClass=n>=9?' sl-wg-d9':(n>=7?' sl-wg-d7':'');
  var spokes='',labels='';
  for(var i=0;i<n;i++){
    spokes+='<div class="sl-wg-spoke" style="transform:rotate('+(i*step)+'deg)"></div>';
    var mid=seg[i].mid,p=prizes[i];
    var labelText=esc(p.wheel_label||p.title||'');
    labels+='<div class="sl-wg-lbl" data-i="'+i+'" style="transform:rotate('+mid+'deg)">'+
      '<div class="sl-wg-lbl-in" style="top:-'+labelR+'px;--rot:'+(-mid)+'deg;transform:translate(-50%,-50%) rotate('+(-mid)+'deg)">'+
      '<span style="color:#fff">'+_wgMedia(p)+'</span>'+
      '<span class="sl-wg-lbl-t">'+labelText+'</span>'+
      (state.settings.show_odds&&p.percent!=null?'<span class="sl-wg-lbl-pct">'+faNum(p.percent)+'٪</span>':'')+
      '</div></div>';
  }
  var lamps='',NL=16;
  for(var j=0;j<NL;j++){
    var a=j*(360/NL)*Math.PI/180;
    lamps+='<span class="sl-wg-lamp" style="left:'+(50+46.5*Math.cos(a)).toFixed(2)+'%;top:'+(50+46.5*Math.sin(a)).toFixed(2)+'%;animation-delay:'+(j*0.09).toFixed(2)+'s"></span>';
  }
  stage.innerHTML=
    '<div class="sl-wg-halo"></div>'+
    '<div class="sl-wg-ring"><div class="sl-wg-ring-in"></div></div>'+
    '<div class="sl-wg-disc'+densityClass+'" id="wg-disc" style="background:conic-gradient('+grad+')">'+spokes+labels+'</div>'+
    '<div class="sl-wg-lights">'+lamps+'</div>'+
    '<div class="sl-wg-hub">'+_wgStar()+'</div>'+
    '<div class="sl-wg-ptr">'+_wgPtr()+'</div>';
}

function wireCta(container,state){
  var cta=document.getElementById('wg-cta'),sub=document.getElementById('wg-cta-sub');
  var rem=state.spins_remaining,can=!!state.can_spin&&rem!==0;
  if(can){
    cta.textContent='چرخوندن گردونه';cta.disabled=false;
    sub.innerHTML=rem<0?'<b>چرخش نامحدود</b> برای امروز':(rem===1?'<b>۱ چرخش رایگان</b> امروز':'<b>'+fmt(rem)+' فرصت</b> باقی مانده');
    cta.onclick=function(){doWheelSpin(container,state)};
  }else{
    cta.textContent='چرخش امروز تمام شد';cta.disabled=true;
    sub.innerHTML='فردا دوباره برگرد و شانست رو امتحان کن 🌙';
  }
}

/* چرخش — نتیجه ۱۰۰٪ سمت سرور؛ Frontend فقط انیمیشن */
function doWheelSpin(container,state){
  if(_wheelSpinning)return;_wheelSpinning=true;
  var cta=document.getElementById('wg-cta');
  if(cta){cta.disabled=true;cta.textContent='در حال چرخش…'}
  var dur=state.settings.animation_duration_ms||5200;
  var hapticOn=!!state.settings.haptic_enabled,soundOn=!!state.settings.sound_enabled;
  var disc=document.getElementById('wg-disc');
  if(disc)disc.classList.add('spinning');
  var headers={'Content-Type':'application/json'};
  if(initData)headers['X-Telegram-Init-Data']=initData;
  var reqId=_wheelReqId();
  fetch('/api/v1/wheel/spin',{method:'POST',headers:headers,body:JSON.stringify({device_fingerprint:_wheelDeviceFp(),client_request_id:reqId})})
    .then(function(r){return r.json()}).then(function(res){
      if(!res.ok){
        _wheelSpinning=false;if(disc)disc.classList.remove('spinning');
        wireCta(container,{settings:state.settings,spins_remaining:(res.spins_remaining!=null?res.spins_remaining:state.spins_remaining),can_spin:res.spins_remaining!==0,campaign:state.campaign});
        window._slApp.dialog.alert(res.error||'خطا در چرخش گردونه','گردونهٔ شانس');
        return;
      }
      var prizes=state.prizes,idx=prizes.findIndex(function(p){return p.id===res.prize.id});
      if(idx<0)idx=prizes.length-1;
      var seg=_wheelSegAngles(prizes.length),tMid=seg[idx].mid;
      var jitter=(Math.random()-.5)*(360/prizes.length)*.45;
      var turns=5+Math.floor(Math.random()*2);
      var base=_wheelRotation-(_wheelRotation%360);
      var fin=base+turns*360+(360-tMid)+jitter;_wheelRotation=fin;
      if(disc){
        disc.style.transition='transform '+dur+'ms cubic-bezier('+_WG_EASE.join(',')+')';
        disc.style.transform='rotate('+fin+'deg)';
        // ⚠️ رفع باگ واقعی (۲۰۲۶-۰۸-۱۰): برچسب‌ها فقط زاویهٔ محلی خودِ Segment
        // (mid) رو Counter-Rotate می‌کردن — یعنی فقط وقتی خودِ دیسک روی rotate(0)
        // بود (لحظهٔ buildWheel، قبل از هر Spin) درست بودن. بعد از هر Spin واقعی،
        // دیسک روی یه زاویهٔ دلخواه (نه مضرب ۳۶۰) می‌مونه تا Segment برنده زیر
        // نشانگر بیفته — بدون این هم‌گام‌سازی، هر برچسب به‌اندازهٔ همون باقیماندهٔ
        // زاویه کج/غیرقابل‌خواندن می‌موند (چه حین چرخش چه در حالت سکون نهایی).
        // رفع: هر .sl-wg-lbl-in هم دقیقاً با همون مدت/منحنی transition به سمت
        // rotate(-(fin+mid)) می‌ره — از نظر ریاضی ترکیب Transform، این یعنی
        // چرخش مؤثر محتوای هر برچسب کل مدت انیمیشن (نه فقط لحظهٔ توقف) ثابت/خوانا
        // می‌مونه، مستقل از این‌که دیسک چقدر بچرخه یا کجا بایسته.
        var labelIns=disc.querySelectorAll('.sl-wg-lbl-in');
        labelIns.forEach(function(el){
          var lblEl=el.closest('.sl-wg-lbl');
          var li=lblEl?+lblEl.dataset.i:-1;
          var m=(li>=0&&seg[li])?seg[li].mid:0;
          var rot=-(fin+m);
          el.style.transition='transform '+dur+'ms cubic-bezier('+_WG_EASE.join(',')+')';
          el.style.transform='translate(-50%,-50%) rotate('+rot+'deg)';
          // برای انیمیشن پالس «برد» (slWgWin، پایین‌تر در app.css) که transform رو
          // موقتاً override می‌کنه — یه CSS var می‌ذاریم تا اون keyframe هم بتونه
          // همین زاویهٔ درست رو (به‌جای صفر/چرخیده) حفظ کنه.
          el.style.setProperty('--rot',rot+'deg');
        });
      }
      // Tick هم‌زمان با موقعیت واقعی چرخ — نه Timer ثابت (بخش «تجربهٔ Spin»)
      _wheelDriveTicks(base,fin,dur,prizes.length,hapticOn,soundOn,function(){
        _wheelStopPulse(hapticOn,soundOn); // فقط یه Feedback ظریف توقف، نه صدای جایزه
      });
      setTimeout(function(){
        _wheelSpinning=false;if(disc)disc.classList.remove('spinning');
        var win=res.prize.prize_type!=='no_win';
        var lbl=disc&&disc.querySelector('.sl-wg-lbl[data-i="'+idx+'"]');
        if(lbl&&win)lbl.classList.add('win');
        if(win)_wheelConfetti(document.getElementById('wg-stage'));
        // بروزرسانی موجودی کیف‌پول تب حساب
        if(res.wallet_balance!=null){var mb=document.getElementById('me-bal');if(mb)mb.innerHTML='<span class="sl-money"><span>'+fmt(res.wallet_balance)+'</span><small>تومان</small></span>'}
        setTimeout(function(){showWinModal(res,function(){
          if(lbl)lbl.classList.remove('win');
          // رفرش وضعیت CTA + پیش‌نمایش‌ها
          var newRem=res.spins_remaining;
          state.spins_remaining=newRem;
          wireCta(container,state);
          var badge=document.querySelector('.sl-wg-hero-badge b');if(badge)badge.textContent=(newRem<0?'∞':fmt(Math.max(0,newRem)));
          loadRewardsPreview();loadActivity();
        })},450);
      },dur+120);
    }).catch(function(){
      _wheelStopTicks();_wheelSpinning=false;if(disc)disc.classList.remove('spinning');
      wireCta(container,state);
      window._slApp.dialog.alert('خطای شبکه — دوباره امتحان کن','گردونهٔ شانس');
    });
}

/* مودال برد — شیشه‌ای، روی کل صفحه */
function showWinModal(res,onClose){
  var p=res.prize,win=p.prize_type!=='no_win';
  var big=_wgValueBig(p);
  var codeHtml='';
  if(p.discount_code){
    codeHtml='<div class="sl-wm-codewrap"><div class="sl-wm-codelabel">کد تخفیف شما</div>'+
      '<div class="sl-wm-code"><code id="wm-code">'+esc(p.discount_code)+'</code>'+
      '<button class="sl-wm-code-copy" id="wm-copy">کپی کد</button></div>'+
      (p.expires_at?'<div class="sl-wm-exp">اعتبار تا '+jalaliDate(p.expires_at)+'</div>':'')+'</div>';
  }
  var subtitle=win?(p.prize_type==='wallet_credit'?'به کیف‌پول شما اضافه شد':(p.prize_type==='extra_spin'?'یک شانس دیگه گیرت اومد!':'شما برنده شدید')):'';
  var ov=document.createElement('div');ov.className='sl-wm-ov';
  ov.innerHTML='<div class="sl-wm">'+
    '<div class="sl-wm-glow"></div>'+
    '<div class="sl-wm-ic'+(win?'':' nowin')+'" style="color:#fff">'+_wgMedia(p,46)+'</div>'+
    '<div class="sl-wm-eyebrow">'+(win?'🎉 تبریک!':'دوباره تلاش کن')+'</div>'+
    (subtitle?'<div class="sl-wm-title">'+esc(subtitle)+'</div>':'')+
    '<div class="sl-wm-value'+(big.plain?' plain':'')+'">'+big.v+'</div>'+
    (p.description&&win?'<div class="sl-wm-desc">'+esc(p.description)+'</div>':'')+
    codeHtml+
    '<div class="sl-wm-acts">'+
      (p.discount_code?'<button class="sl-wm-btn sl-wm-btn-p" id="wm-prizes">مشاهده جوایز من</button>':'')+
      (p.prize_type==='physical_gift'?'<button class="sl-wm-btn sl-wm-btn-p" id="wm-prizes">📮 ثبت آدرس دریافت هدیه</button>':'')+
      '<button class="sl-wm-btn '+((p.discount_code||p.prize_type==='physical_gift')?'sl-wm-btn-s':'sl-wm-btn-p')+'" id="wm-close">'+(win?'عالی!':'باشه')+'</button>'+
    '</div></div>';
  document.body.appendChild(ov);
  function close(){ov.remove();if(onClose)onClose()}
  var cp=ov.querySelector('#wm-copy');
  if(cp)cp.addEventListener('click',function(){var c=ov.querySelector('#wm-code').textContent;
    if(navigator.clipboard)navigator.clipboard.writeText(c).then(function(){cp.textContent='✅ کپی شد'});else cp.textContent='✅ کپی شد'});
  var pz=ov.querySelector('#wm-prizes');if(pz)pz.addEventListener('click',function(){close();openMyPrizes()});
  ov.querySelector('#wm-close').addEventListener('click',close);
  ov.addEventListener('click',function(e){if(e.target===ov)close()});
}

/* پیش‌نمایش «جوایز من» روی صفحهٔ گردونه (تا ۲ مورد اخیر، هر نوعی — فقط کد
   تخفیفِ فعال، یا کیف‌پول/چرخش‌اضافه‌ای که واقعاً موفق تحویل شده) */
function loadRewardsPreview(){
  api('/me/prizes',true).then(function(d){
    var sec=document.getElementById('wg-rewards'),list=document.getElementById('wg-rewards-list');
    if(!sec||!list)return;
    var usable=['active','delivered','applied','pending_shipment','needs_address','shipped'];
    var act=((d&&d.items)||[]).filter(function(it){return usable.indexOf(it.status)>=0}).slice(0,2);
    if(!act.length){sec.style.display='none';return}
    sec.style.display='';
    list.innerHTML=act.map(function(it){
      if(it.kind==='discount'){
        var vt=it.type==='percent'?(fmt(it.value)+'٪ تخفیف'):(fmt(it.value)+' تومان تخفیف');
        return '<div class="sl-wg-reward"><div class="sl-wg-reward-ic" style="color:#F5C451">'+_wgIcon(it.type==='percent'?'discount_percent':'discount_fixed')+'</div>'+
          '<div class="sl-wg-reward-tx"><div class="sl-wg-reward-t">'+esc(vt)+'</div>'+
          (it.expires_at?'<div class="sl-wg-reward-s">اعتبار تا '+jalaliDate(it.expires_at)+'</div>':'')+'</div>'+
          '<span class="sl-wg-reward-code">'+esc(it.code)+'</span></div>';
      }
      var lbl=it.kind==='wallet_credit'?(fmt(it.amount||0)+' تومان'):(it.kind==='extra_spin'?'یک چرخش اضافه':(it.title||'هدیه'));
      return '<div class="sl-wg-reward"><div class="sl-wg-reward-ic" style="color:#F5C451">'+_wgIcon(it.kind)+'</div>'+
        '<div class="sl-wg-reward-tx"><div class="sl-wg-reward-t">'+esc(lbl)+'</div>'+
        '<div class="sl-wg-reward-s">'+jalaliDate(it.created_at)+'</div></div></div>';
    }).join('');
    var all=document.getElementById('wg-rewards-all');
    if(all)all.onclick=function(e){e.preventDefault();openMyPrizes()};
  }).catch(function(){});
}

/* فعالیت اخیر (از /me/wheel-activity — فقط‌خواندنی) */
function loadActivity(){
  api('/me/wheel-activity',true).then(function(d){
    var sec=document.getElementById('wg-activity'),list=document.getElementById('wg-activity-list');
    if(!sec||!list)return;
    var items=((d&&d.items)||[]).slice(0,5);
    if(!items.length){sec.style.display='none';return}
    sec.style.display='';
    list.innerHTML=items.map(function(it){
      var t=it.prize_type;
      var label=it.prize_title||(t==='wallet_credit'?(fmt(it.amount||0)+' تومان کیف‌پول'):'جایزه');
      var extra=t==='wallet_credit'?(fmt(it.amount||0)+' تومان به کیف‌پول'):(it.discount_code?('کد: '+it.discount_code):'');
      return '<div class="sl-wg-act"><div class="sl-wg-act-ic" style="color:#F5C451">'+_wgIcon(t)+'</div>'+
        '<div class="sl-wg-act-tx"><div class="sl-wg-act-t">'+esc(label)+'</div>'+
        (extra?'<div class="sl-wg-act-d">'+esc(extra)+'</div>':'')+'</div>'+
        '<div class="sl-wg-act-d">'+jalaliDate(it.created_at)+'</div></div>';
    }).join('');
  }).catch(function(){});
}

/* ═══ «جوایز من» — صفحهٔ کامل با کارت‌های پریمیوم + فیلتر ═══
   items از /me/prizes میان و دو منبع واقعی رو ادغام می‌کنن (بدون هیچ جدول/سیستم
   تازه): کد تخفیف (discount_codes) + کیف‌پول/چرخش‌اضافه/هدیهٔ فیزیکیِ گردونه
   (wheel_spins) — هر آیتم با it.kind مشخص می‌شه، وضعیت هم واقعاً از Backend میاد. */
var _wgPzLabel={
  active:'قابل استفاده',used:'استفاده‌شده',expired:'منقضی‌شده',inactive:'غیرفعال',
  delivered:'واریز شده',applied:'اعمال شد',pending_shipment:'در انتظار ارسال',failed:'ناموفق',
  needs_address:'نیازمند ثبت آدرس',shipped:'ارسال شد',
};
function openMyPrizes(){
  _accPopup('جوایز من',skel(3));
  api('/me/prizes',true).then(function(d){
    var b=_accBody();if(!b)return;
    var items=(d&&d.items)||[];
    if(!items.length){
      b.innerHTML='<div class="sl-empty"><span class="sl-empty-e">🎁</span>هنوز جایزه‌ای نداری — از گردونهٔ شانس شانست رو امتحان کن!</div>';
      return;
    }
    renderMyPrizes(b,items,'all');
  }).catch(function(){var b=_accBody();if(b)b.innerHTML=err('خطا در دریافت جوایز')});
}
window.openMyPrizes=openMyPrizes;

var _pzSourceLabel={wheel:'🎡 جایزه گردونه',winback:'🎟 کد تخفیف Winback',campaign:'🎉 جایزه کمپین',referral:'👥 پاداش معرفی'};
/* رشتهٔ تاریخ سرور (فرمت 'YYYY-MM-DD HH:MM:SS') → ms — Frontend هیچ‌وقت زمان
   انقضا رو خودش حدس نمی‌زنه، فقط همین رشتهٔ واقعی expires_at رو parse می‌کنه. */
/* 🔴 پسوند `Z` اجباری است: `new Date('2026-09-16T12:07:00')` را جاوااسکریپت
   **به‌وقت دستگاه** پارس می‌کند، در حالی که سرور همیشه UTC ذخیره می‌کند
   (قاعدهٔ ۶۳) — و طرف دیگر مقایسه `Date.now()` است که epoch واقعی است. نتیجه:
   شمارش معکوس به اندازهٔ آفست دستگاه اشتباه بود (روی گوشی ایرانی ۳:۳۰)، بدون
   هیچ خطایی. ⚠️ این‌جا `_slTzShift` **نباید** استفاده شود: آن لایهٔ نمایش است
   و جابه‌جایی دوباره، خطا را دوبرابر می‌کند. */
function _parseServerDT(s){
  if(!s)return NaN;
  var str=String(s).trim().replace(' ','T');
  if(!/(?:[zZ]|[+\-]\d{2}:?\d{2})$/.test(str))str+='Z';
  return new Date(str).getTime();
}
function _pad2(n){ return (n<10?'0':'')+n; }
/* تایمر واقعی زنده — یک setInterval مشترک برای همهٔ کارت‌ها (نه یکی به‌ازای هر کارت)،
   خودش رو وقتی ظرف از DOM حذف بشه (رفتن به صفحهٔ دیگه) پاک می‌کنه. */
function _pzStartTimers(container){
  if(window._pzTimerInt){clearInterval(window._pzTimerInt);window._pzTimerInt=null}
  function tick(){
    if(!document.body.contains(container)){clearInterval(window._pzTimerInt);window._pzTimerInt=null;return}
    var timers=container.querySelectorAll('.sl-pz-timer[data-exp]');
    if(!timers.length){clearInterval(window._pzTimerInt);window._pzTimerInt=null;return}
    timers.forEach(function(t){
      var expMs=_parseServerDT(t.dataset.exp);
      var diff=expMs-Date.now();
      var card=t.closest('.sl-pz');
      if(!expMs||diff<=0){
        t.textContent='⏱ منقضی شده';t.classList.add('sl-pz-timer-exp');
        if(card){card.classList.add('is-dim');var btn=card.querySelector('.sl-pz-code-btn');if(btn){btn.disabled=true;btn.textContent='منقضی شده'}}
        return;
      }
      var h=Math.floor(diff/3600000),m=Math.floor(diff%3600000/60000),s=Math.floor(diff%60000/1000);
      t.textContent='⏱ '+faNum((h>0?(_pad2(h)+':'):'')+_pad2(m)+':'+_pad2(s));
    });
  }
  tick();
  window._pzTimerInt=setInterval(tick,1000);
}
function _pzCardHtml(it){
  if(it.kind==='discount'){
    var isPct=it.type==='percent';
    var val=isPct?(fmt(it.value)+'٪ تخفیف'):(fmt(it.value)+' تومان تخفیف');
    var capParts=[];
    if(isPct&&it.max_value)capParts.push('حداکثر تخفیف: '+fmt(it.max_value)+' تومان');
    if(it.min_amount)capParts.push('حداقل خرید: '+fmt(it.min_amount)+' تومان');
    var dim=it.status!=='active';
    var scopeHtml='<div class="sl-pz-scope">'+esc(it.scope_label||'کل فروشگاه')+'</div>';
    var scopeBtn='';
    if(it.product_id)scopeBtn='<button class="sl-pz-scope-btn" data-pid="'+it.product_id+'">مشاهده محصول</button>';
    else if(it.category_id)scopeBtn='<button class="sl-pz-scope-btn" data-cid="'+it.category_id+'">مشاهده محصولات</button>';
    var dateRows='';
    if(it.starts_at)dateRows+='<div class="sl-pz-dr"><span>شروع اعتبار</span><b>'+jalaliDate(it.starts_at)+'</b></div>';
    if(it.expires_at)dateRows+='<div class="sl-pz-dr"><span>پایان اعتبار</span><b>'+jalaliDate(it.expires_at)+'</b></div>';
    var timerHtml=(it.status==='active'&&it.expires_at)?
      '<div class="sl-pz-timer" data-exp="'+esc(it.expires_at)+'">⏱ …</div>':'';
    return '<div class="sl-pz'+(dim?' is-dim':'')+'">'+
      '<div class="sl-pz-ic" style="background:'+(isPct?'linear-gradient(145deg,#8B5CF6,#6366F1)':'linear-gradient(145deg,#F59E0B,#E29B33)')+'">'+_wgIcon(isPct?'discount_percent':'discount_fixed')+'</div>'+
      '<div class="sl-pz-bd">'+
        '<div class="sl-pz-top"><div class="sl-pz-val">'+esc(val)+'</div>'+
          '<span class="sl-pz-badge '+esc(it.status)+'">'+(_wgPzLabel[it.status]||esc(it.status))+'</span></div>'+
        (_pzSourceLabel[it.source]?'<div class="sl-pz-src">'+_pzSourceLabel[it.source]+'</div>':'')+
        scopeHtml+
        (capParts.length?'<div class="sl-pz-desc">'+esc(capParts.join(' · '))+'</div>':(it.description?'<div class="sl-pz-desc">'+esc(it.description)+'</div>':''))+
        dateRows+timerHtml+
        (it.status==='active'?
          '<div class="sl-pz-code"><code>'+esc(it.code)+'</code><button class="sl-pz-code-btn" data-code="'+esc(it.code)+'">کپی کد</button></div>':
          '<div class="sl-pz-code" style="opacity:.7"><code>'+esc(it.code)+'</code></div>')+
        (scopeBtn?'<div class="sl-pz-acts">'+scopeBtn+'</div>':'')+
      '</div></div>';
  }
  var dim2=it.status==='failed';
  var val2,icBg,statusLine,giftActs='';
  if(it.kind==='wallet_credit'){val2='+'+fmt(it.amount||0)+' <small style="font-size:13px">تومان</small>';icBg='linear-gradient(145deg,#22C55E,#16A34A)';statusLine='واریز شده به کیف‌پول'}
  else if(it.kind==='extra_spin'){val2='یک چرخش اضافه';icBg='linear-gradient(145deg,#6366F1,#4F46E5)';statusLine='به گردونه اضافه شد'}
  else{
    val2=esc(it.title||'هدیهٔ فیزیکی');icBg='linear-gradient(145deg,#F59E0B,#E29B33)';
    /* گردش‌کار هدیهٔ فیزیکی (بخش ۹۹): تا آدرس ثبت نشده، دکمهٔ ثبت اطلاعات؛
       بعدش «در انتظار ارسال»؛ ادمین که ارسال زد، «ارسال شد». */
    if(it.status==='needs_address'){
      statusLine='برای دریافت هدیه، اطلاعات پستی‌ات رو ثبت کن';
      giftActs='<div class="sl-pz-acts"><button class="sl-pz-scope-btn sl-pz-gift-btn" data-spin="'+it.spin_id+'">📮 ثبت اطلاعات دریافت</button></div>';
    }else if(it.status==='pending_shipment'){
      statusLine='آدرس ثبت شد — در انتظار ارسال از فروشگاه';
      giftActs='<div class="sl-pz-acts"><button class="sl-pz-scope-btn sl-pz-gift-btn" data-spin="'+it.spin_id+'">✏️ ویرایش اطلاعات دریافت</button></div>';
    }else if(it.status==='shipped'){
      statusLine='هدیه ارسال شد 🎉';
    }else{statusLine='در صف پیگیری/ارسال'}
  }
  return '<div class="sl-pz'+(dim2?' is-dim':'')+'">'+
    '<div class="sl-pz-ic" style="background:'+icBg+'">'+_wgIcon(it.kind)+'</div>'+
    '<div class="sl-pz-bd">'+
      '<div class="sl-pz-top"><div class="sl-pz-val">'+val2+'</div>'+
        '<span class="sl-pz-badge '+esc(it.status)+'">'+(_wgPzLabel[it.status]||esc(it.status))+'</span></div>'+
      (_pzSourceLabel[it.source]?'<div class="sl-pz-src">'+_pzSourceLabel[it.source]+'</div>':'')+
      (dim2?'':'<div class="sl-pz-desc">'+statusLine+'</div>')+
      '<div class="sl-pz-exp">تاریخ: '+jalaliDate(it.created_at)+'</div>'+
      giftActs+
    '</div></div>';
}
/* فرم درون‌کارتی ثبت آدرس هدیهٔ فیزیکی — POST مستقیم (نه helper عمومی api()
   که فقط GET می‌فرسته — درس ثبت‌شدهٔ همین پروژه). */
function _pzGiftForm(btn,items){
  var card=btn.closest('.sl-pz');if(!card)return;
  if(card.querySelector('.sl-pz-gift-form'))return;
  var spinId=btn.dataset.spin;
  var f=document.createElement('div');f.className='sl-pz-gift-form';
  f.innerHTML=
    '<input type="text" class="sl-pz-gf-in" data-gf="name" placeholder="نام و نام خانوادگی گیرنده">'+
    '<input type="tel" class="sl-pz-gf-in no-fa" data-gf="phone" inputmode="tel" placeholder="شماره تماس" style="direction:ltr;text-align:right">'+
    '<textarea class="sl-pz-gf-in" data-gf="addr" rows="3" placeholder="آدرس کامل پستی (استان، شهر، خیابان، پلاک، کدپستی)"></textarea>'+
    '<div class="sl-pz-gf-err" style="display:none"></div>'+
    '<button class="sl-pz-gf-send">ثبت اطلاعات</button>';
  btn.parentElement.after(f);btn.style.display='none';
  f.querySelector('.sl-pz-gf-send').addEventListener('click',function(){
    var name=f.querySelector('[data-gf=name]').value.trim(),
        phone=f.querySelector('[data-gf=phone]').value.trim(),
        addr=f.querySelector('[data-gf=addr]').value.trim(),
        errEl=f.querySelector('.sl-pz-gf-err');
    if(!name||!phone||!addr){errEl.textContent='همهٔ فیلدها لازم است';errEl.style.display='';return}
    var sendBtn=f.querySelector('.sl-pz-gf-send');sendBtn.disabled=true;sendBtn.textContent='در حال ثبت…';
    var h={'Content-Type':'application/json'};
    if(window._slInitData)h['X-Telegram-Init-Data']=window._slInitData;
    fetch('/api/v1/wheel/gift-claim',{method:'POST',headers:h,
      body:JSON.stringify({spin_id:parseInt(spinId,10),full_name:name,phone:phone,address:addr})})
      .then(function(r){return r.json()}).then(function(res){
        if(!res.ok){errEl.textContent=res.error||'خطا در ثبت';errEl.style.display='';sendBtn.disabled=false;sendBtn.textContent='ثبت اطلاعات';return}
        f.innerHTML='<div class="sl-pz-gf-ok">✅ اطلاعات ثبت شد — بعد از ارسال، بهت خبر می‌دیم</div>';
        setTimeout(openMyPrizes,1200);
      }).catch(function(){errEl.textContent='خطای شبکه — دوباره تلاش کن';errEl.style.display='';sendBtn.disabled=false;sendBtn.textContent='ثبت اطلاعات'});
  });
}
/* سایت: «جوایز نقدی» (کیف‌پول) ندارد — همان جایزه این‌جا کدِ تخفیفِ مبلغی است */
var _pzTabs=[['all','همه'],['discount','🎟️ کدهای تخفیف'],
             ['physical_gift','🎁 هدیهٔ فیزیکی'],['extra_spin','🔄 چرخش اضافه']];
function renderMyPrizes(b,items,filter){
  var counts={all:items.length};
  items.forEach(function(it){counts[it.kind]=(counts[it.kind]||0)+1});
  var shown=items.filter(function(it){return filter==='all'||it.kind===filter});
  b.innerHTML=
    '<div class="sl-pz-filters">'+_pzTabs.map(function(c){
      return '<div class="sl-pz-chip'+(filter===c[0]?' on':'')+'" data-f="'+c[0]+'">'+c[1]+(c[0]!=='all'&&counts[c[0]]?' ('+fmt(counts[c[0]])+')':'')+'</div>';
    }).join('')+'</div>'+
    '<div class="sl-pz-list">'+(shown.length?shown.map(_pzCardHtml).join(''):
      '<div class="sl-empty" style="padding:36px"><span class="sl-empty-e">📭</span>موردی در این دسته نیست</div>')+'</div>';
  b.querySelectorAll('.sl-pz-code-btn').forEach(function(btn){
    btn.addEventListener('click',function(){if(btn.disabled)return;var c=btn.dataset.code;
      if(navigator.clipboard)navigator.clipboard.writeText(c).then(function(){btn.textContent='✅ کپی شد'});else btn.textContent='✅ کپی شد'});
  });
  b.querySelectorAll('.sl-pz-scope-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
      if(btn.classList.contains('sl-pz-gift-btn')){_pzGiftForm(btn,items);return}
      if(btn.dataset.pid)openP(btn.dataset.pid);else if(btn.dataset.cid)openC(btn.dataset.cid);
    });
  });
  b.querySelectorAll('.sl-pz-chip').forEach(function(chip){
    chip.addEventListener('click',function(){renderMyPrizes(b,items,chip.dataset.f)});
  });
  _pzStartTimers(b);
}


/* ════════════ راه‌اندازی ════════════ */
function _whBoot(){if(document.getElementById('post-body'))window.openWheel()}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',_whBoot);else _whBoot();
})();
