<?php
/**
 * تایمرِ تخفیف — شمارشِ معکوسِ «تا پایانِ تخفیف» روی صفحه‌ی محصول و کارت‌ها.
 *
 * صاحب فروشگاه (۱۷ آبان ۱۴۰۵): «محصولاتی که آف زدیم تایمر بخورد؛ برای همه‌شان ۴۸ ساعت، و برای همیشه این قابلیت باشد که هر
 * بار یک روز، دو روز، یک هفته یا هر چقدر خواستم بگذارم؛ هم در Novo و هم در سایت قابلِ تنظیم؛ خوشگل، به‌روز و جذاب؛ اعداد فارسی.»
 * و در پرسش: وقتی تایمر صفر شد «تخفیف واقعاً تمام شود».
 *
 * • منبعِ زمان همان «زمان‌بندیِ فروشِ ویژه»ی خودِ فروشگاه‌ساز است (`date_on_sale_to`) — نه متای جدا. پس:
 *   – Novo با همان فیلدِ استانداردِ REST (`date_on_sale_to`) تنظیمش می‌کند؛
 *   – در پیشخوان، کادرِ «زمان‌بندی» در ویرایشِ هر محصول؛ و صفحه‌ی گروهیِ «تایمرِ تخفیف» (زیرِ «محصولات») برای همه با هم؛
 *   – پایانِ تخفیف کارِ خودِ فروشگاه‌ساز است (`wc_scheduled_sales`)؛ این‌جا فقط یک رویدادِ یک‌باره سرِ همان دقیقه می‌گذاریم تا
 *     روزانه بودنِ آن رویداد، تخفیفِ تمام‌شده را تا نیمه‌شب نگه ندارد.
 * • ⛔ استثنای مالکیتِ داده (`CLAUDE.md`): قیمت مالِ حسابداری است و این فایل قیمت را نمی‌نویسد؛ فقط «تاریخِ پایانِ تخفیف» را، و
 *   فقط با دستِ صاحب فروشگاه در صفحه‌ی گروهی (و یک بار، ۴۸ ساعتِ اول که خودش خواست).
 * • نمایش: کارتِ صفحه‌ی اصلی (`stlh_card`) خودش قرص را دارد؛ کارت‌های قالب (دسته، جستجو، پیشنهادها) و صفحه‌ی محصول با یک
 *   اسکریپتِ کوچک از نقشه‌ی `{شناسه: پایان}`. اعداد فارسی؛ ثانیه‌شمار با ساعتِ دستگاهِ مشتری.
 */
defined( 'ABSPATH' ) || exit;

/** پایانِ تخفیفِ یک محصول (یونیکس) یا ۰ — فقط اگر الان در تخفیف است و پایانش آینده است */
function stlh_sale_end( WC_Product $p ): int {
	if ( ! method_exists( $p, 'get_date_on_sale_to' ) ) {
		return 0;
	}
	$ends = [];
	foreach ( $p->is_type( 'variable' ) ? $p->get_children() : [ $p->get_id() ] as $id ) {
		$v = $id === $p->get_id() ? $p : wc_get_product( $id );
		if ( ! $v || ! $v->is_on_sale( 'edit' ) ) {
			continue;
		}
		$to = $v->get_date_on_sale_to( 'edit' );
		if ( $to && $to->getTimestamp() > time() ) {
			$ends[] = $to->getTimestamp();
		}
	}
	return $ends ? min( $ends ) : 0;
}

/** نقشه‌ی همه‌ی تایمرهای فعال: [شناسه‌ی محصولِ اصلی => پایان] — کوچک (فقط کالاهای تخفیف‌دار) و پنج دقیقه در حافظه */
function stlh_sale_timers(): array {
	if ( ! function_exists( 'wc_get_product_ids_on_sale' ) ) {
		return [];
	}
	$map = get_transient( 'stlh_sale_timers' );
	if ( is_array( $map ) ) {
		return array_filter( $map, static fn( $t ) => $t > time() );
	}
	$map = [];
	foreach ( wc_get_product_ids_on_sale() as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$main = $p->get_parent_id() ?: $p->get_id();
		$mp   = $main === $p->get_id() ? $p : wc_get_product( $main );
		$end  = $mp ? stlh_sale_end( $mp ) : 0;
		if ( $end ) {
			$map[ $main ] = $end;
		}
	}
	set_transient( 'stlh_sale_timers', $map, 5 * MINUTE_IN_SECONDS );
	return $map;
}

/** قرصِ کارتِ صفحه‌ی اصلی (`stlh_card`) — JS عددش را هر ثانیه می‌گذارد */
function stlh_sale_timer_pill( WC_Product $p, bool $inline = false ): string {
	$end = $p->is_in_stock() ? stlh_sale_end( $p ) : 0;
	return $end ? '<span class="stlh-tm-pill' . ( $inline ? ' stlh-tm-inline' : '' ) . '" data-stlh-end="' . $end . '" hidden></span>' : '';
}

// ─── پایانِ واقعیِ تخفیف سرِ همان دقیقه ───
$stlh_sale_schedule = static function ( $product_id ): void {
	$p = wc_get_product( $product_id );
	if ( ! $p ) {
		return;
	}
	delete_transient( 'stlh_sale_timers' );
	$to = $p->get_date_on_sale_to( 'edit' );
	if ( $to && $to->getTimestamp() > time() ) {
		$at = $to->getTimestamp() + 30;
		if ( ! wp_next_scheduled( 'stlh_sale_end_tick', [ $at ] ) ) {
			wp_schedule_single_event( $at, 'stlh_sale_end_tick', [ $at ] );
		}
	}
};
add_action( 'woocommerce_update_product', $stlh_sale_schedule );
add_action( 'woocommerce_new_product', $stlh_sale_schedule );
add_action( 'woocommerce_update_product_variation', $stlh_sale_schedule );
add_action( 'stlh_sale_end_tick', static function (): void {
	if ( function_exists( 'wc_scheduled_sales' ) ) {
		wc_scheduled_sales(); // قیمت را خودِ فروشگاه‌ساز به قیمتِ اصلی برمی‌گرداند و محصول را ذخیره می‌کند (کش‌ها با همان ذخیره پاک می‌شوند)
	}
	delete_transient( 'stlh_sale_timers' );
	if ( function_exists( 'stlh_timer_purge' ) ) {
		stlh_timer_purge(); // تخفیفِ تمام‌شده از همه‌ی صفحه‌های کش‌شده برود
	}
} );

// ─── نمایش ───
add_action( 'wp_enqueue_scripts', static function (): void {
	if ( is_admin() || ! function_exists( 'is_woocommerce' ) ) {
		return;
	}
	$map = stlh_sale_timers();
	if ( ! $map ) {
		return;
	}
	$single = 0;
	if ( is_product() ) {
		$p      = wc_get_product( get_queried_object_id() );
		$single = ( $p && $p->is_in_stock() ) ? ( $map[ $p->get_id() ] ?? 0 ) : 0;
	}
	wp_register_style( 'stland-sale-timer', false, [], STLH_VER );
	wp_enqueue_style( 'stland-sale-timer' );
	wp_add_inline_style( 'stland-sale-timer', stlh_sale_timer_css() );
	wp_register_script( 'stland-sale-timer', false, [], STLH_VER, [ 'in_footer' => true ] );
	wp_enqueue_script( 'stland-sale-timer' );
	wp_add_inline_script( 'stland-sale-timer', 'window.stlhTimers=' . wp_json_encode( [
		'map'    => (object) $map,
		'single' => $single,
		'title'  => stlh_label( 'timer_title' ),
		'sub'    => stlh_label( 'timer_sub' ),
		'done'   => stlh_label( 'timer_done' ),
	] ) . ';' . stlh_sale_timer_js() );
}, 30 );

function stlh_sale_timer_css(): string {
	return <<<'CSS'
.stlh-tm{--tm1:#ff3d5a;--tm2:#ff8a3d;position:relative;margin:12px 0;padding:12px 14px 13px;border-radius:18px;color:#fff;direction:rtl;
background:linear-gradient(120deg,var(--tm1),var(--tm2));box-shadow:0 10px 28px -12px rgba(255,61,90,.75);overflow:hidden;font-family:inherit}
.stlh-tm:before{content:"";position:absolute;inset:-40% -10% auto auto;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.14)}
.stlh-tm-head{position:relative;display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:9px}
.stlh-tm-title{display:flex;align-items:center;gap:6px;font-weight:900;font-size:15px;line-height:1.3}
.stlh-tm-title svg{width:18px;height:18px;flex:0 0 auto;animation:stlh-tm-zap 1.6s ease-in-out infinite}
.stlh-tm-sub{font-size:12px;font-weight:600;opacity:.92;white-space:nowrap}
.stlh-tm-row{position:relative;display:flex;align-items:stretch;justify-content:center;gap:6px;direction:rtl}
.stlh-tm-u{flex:1 1 0;min-width:0;max-width:74px;background:rgba(20,10,20,.28);backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,.22);
border-radius:12px;padding:6px 2px 5px;text-align:center}
.stlh-tm-n{display:block;font-size:22px;font-weight:900;line-height:1.15;font-variant-numeric:tabular-nums;letter-spacing:.5px}
.stlh-tm-l{display:block;font-size:10.5px;font-weight:700;opacity:.88;margin-top:1px;direction:rtl}
.stlh-tm-sep{align-self:center;font-weight:900;font-size:18px;opacity:.7;padding-bottom:12px}
.stlh-tm-u.s .stlh-tm-n{animation:stlh-tm-tick 1s ease-out infinite}
.stlh-tm.done{background:linear-gradient(120deg,#6b6b75,#8d8d97);box-shadow:none}
.stlh-tm.done .stlh-tm-row{display:none}
@keyframes stlh-tm-tick{0%{transform:translateY(-2px);opacity:.55}35%{transform:none;opacity:1}}
@keyframes stlh-tm-zap{50%{transform:scale(1.18) rotate(-8deg)}}
.stlh-tm-pill{position:absolute;z-index:3;left:8px;bottom:8px;display:inline-flex;align-items:center;gap:4px;padding:3px 9px 3px 8px;border-radius:999px;
background:linear-gradient(120deg,#ff3d5a,#ff8a3d);color:#fff;font-size:11.5px;font-weight:800;line-height:1.5;direction:rtl;
box-shadow:0 4px 12px -4px rgba(255,61,90,.8);font-variant-numeric:tabular-nums;pointer-events:none}
.stlh-tm-pill.stlh-tm-inline{position:static;display:flex;width:max-content;max-width:100%;margin:4px 0 10px;margin-inline-start:auto;padding:6px 13px 6px 12px;font-size:13.5px;gap:6px;box-shadow:0 6px 16px -6px rgba(255,61,90,.85)}
.stlh-tm-pill.stlh-tm-inline svg{width:15px;height:15px}
@media (max-width:576px){.stlh-tm-pill.stlh-tm-inline{font-size:12px;padding:5px 10px;gap:5px}.stlh-tm-pill.stlh-tm-inline svg{width:13px;height:13px}}
.stlh-tm-pill svg{width:12px;height:12px;flex:0 0 auto}
.st-pr-img,.products__item-image-wrapper,.product-thumb{position:relative}
@media (prefers-reduced-motion:reduce){.stlh-tm *{animation:none!important}}
CSS;
}

function stlh_sale_timer_js(): string {
	return <<<'JS'
(function(){var T=window.stlhTimers||{},M=T.map||{},fa='۰۱۲۳۴۵۶۷۸۹',
f=function(n,w){n=String(n);while(n.length<(w||2))n='0'+n;return n.replace(/\d/g,function(d){return fa[d]})},
zap='<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>',
clk='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2M9 2h6"/></svg>',
left=function(e){return Math.max(0,Math.floor(e-Date.now()/1000))},
pills=[],box=null;
function pillText(s){var d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.floor(s%3600/60),x=s%60;
return clk+(d?'<span>'+f(d,1)+' روز و</span>':'')+'<span dir="ltr">'+f(h)+':'+f(m)+':'+f(x)+'</span>'}
function mountPill(li,end){if(li.querySelector('.stlh-tm-pill'))return;var p=document.createElement('span');p.className='stlh-tm-pill stlh-tm-inline';p.dataset.stlhEnd=end;
var t=li.querySelector('.products__item-fatitle,.product-title,.title');if(t&&t.parentNode){t.parentNode.insertBefore(p,t.nextSibling)}else{li.appendChild(p)}}
function scan(){
document.querySelectorAll('li.product[data-id],.products-list article[data-id],[data-product_id].product').forEach(function(li){
var id=li.getAttribute('data-id')||li.getAttribute('data-product_id'),end=M[id];if(!end)return;
mountPill(li,end)});
pills=[].slice.call(document.querySelectorAll('.stlh-tm-pill[data-stlh-end]'))}
function mountBox(){var end=T.single;if(!end||document.querySelector('.stlh-tm'))return;
var b=document.createElement('div');b.className='stlh-tm';b.setAttribute('role','timer');b.dataset.stlhEnd=end;
var u=function(c,l){return '<span class="stlh-tm-u '+c+'"><span class="stlh-tm-n">۰۰</span><span class="stlh-tm-l">'+l+'</span></span>'},sep='<span class="stlh-tm-sep">:</span>';
b.innerHTML='<div class="stlh-tm-head"><span class="stlh-tm-title">'+zap+'<span>'+T.title+'</span></span><span class="stlh-tm-sub">'+T.sub+'</span></div>'+
'<div class="stlh-tm-row">'+u('d','روز')+sep+u('h','ساعت')+sep+u('m','دقیقه')+sep+u('s','ثانیه')+'</div>';
var a=document.querySelector('.product-info-box .price-section');
if(a){a.parentNode.insertBefore(b,a.nextSibling)}else{
var r=document.querySelector('.product-remain')||document.querySelector('.bakala-product-price')||document.querySelector('.summary .price');
if(!r)return;r.parentNode.insertBefore(b,r)}box=b;
/* شمارشِ معکوسِ خودِ قالب (flipclock) برای کالای زمان‌دار — دو تایمر کنارِ هم نباشد */
document.querySelectorAll('.flip-clock-wrapper,.flipdown').forEach(function(w){(w.closest('[class*=countdown],[class*=timer],[class*=offer],[class*=flip]')||w).style.display='none'})}
function clocks(){document.querySelectorAll('.stlh-tm-clock[data-stlh-end]').forEach(function(c){var s=left(+c.dataset.stlhEnd);if(!s){c.closest('.st-sale-band')&&(c.textContent=T.done);return}
var d=Math.floor(s/86400),t=[f(Math.floor(s%86400/3600)),f(Math.floor(s%3600/60)),f(s%60)];c.innerHTML=(d?'<b>'+f(d,1)+'</b><i>روز</i>':'')+'<b>'+t[0]+'</b>:<b>'+t[1]+'</b>:<b>'+t[2]+'</b>'})}
function tick(){clocks();pills.forEach(function(p){var s=left(+p.dataset.stlhEnd);if(!s){p.remove();return}p.hidden=false;p.innerHTML=pillText(s)});
if(box){var s=left(+box.dataset.stlhEnd);if(!s){box.classList.add('done');box.querySelector('.stlh-tm-sub').textContent=T.done;return}
var d=Math.floor(s/86400),q=box.querySelectorAll('.stlh-tm-n');q[0].textContent=f(d);q[1].textContent=f(Math.floor(s%86400/3600));
q[2].textContent=f(Math.floor(s%3600/60));q[3].textContent=f(s%60);var du=box.querySelector('.stlh-tm-u.d');
du.style.display=d?'':'none';du.nextSibling.style.display=d?'':'none'}}
function go(){scan();mountBox();tick();setInterval(tick,1000);
if(window.jQuery){jQuery(document).on('ajaxComplete',function(){setTimeout(function(){scan();tick()},300)})}}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',go):go()})();
JS;
}

/* ─── بخشِ «فروش ویژه»ی صفحه‌ی اصلی (کلید `sale` در ترتیبِ بخش‌ها) ───
 * صاحب فروشگاه (۱۷ آبان ۱۴۰۵): «یک بخش هم برای فروش ویژه؛ کالاهایی که تایمر دارند آن‌جا هم بیایند، زمینه‌ی قرمز مثلِ فروشِ ویژه‌ی
 * فروشگاه‌های بزرگ». نوارِ قرمز با عنوان و شمارشِ معکوسِ نزدیک‌ترین پایان، و کارت‌های سفید؛ بی کالای تایمردارِ موجود، پنهان. */
function stlh_sale(): string {
	if ( ! function_exists( 'stlh_woo' ) || ! stlh_woo() ) {
		return '';
	}
	$map   = stlh_sale_timers();
	$items = [];
	foreach ( $map as $id => $end ) {
		$p = wc_get_product( $id );
		if ( $p && 'publish' === $p->get_status() && $p->is_in_stock() && $p->is_visible() ) {
			$items[ $id ] = $p;
		}
	}
	if ( ! $items ) {
		return '';
	}
	uasort( $items, static fn( $a, $b ) => stlh_sale_percent( $b ) <=> stlh_sale_percent( $a ) );
	$items = array_slice( $items, 0, 16, true );
	$soon  = min( array_intersect_key( $map, $items ) );
	return '<section class="st-pr-wrapper st-sale-band"><div class="st-pr-container"><div class="st-sale-head">'
		. '<div class="st-sale-title"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg><h2>' . esc_html( stlh_label( 'sale_title' ) ) . '</h2></div>'
		. '<div class="st-sale-count"><span>' . esc_html( stlh_label( 'timer_sub' ) ) . '</span><span class="stlh-tm-clock" data-stlh-end="' . (int) $soon . '"></span></div>'
		. '</div><div class="st-pr-track"><div class="st-pr-row">' . implode( '', array_map( 'stlh_card', $items ) ) . '</div></div></div></section>';
}
