<?php
/**
 * ارتقای فروش (صاحب فروشگاه، ۳۰ آبان ۱۴۰۵، پیشنهادهای «کارشناسِ ارشدِ طراحی»: «اقساط آنلاین فعلاً ندارم، مابقی را درست کن»).
 *
 *  ۱. «آیفونِ من را پیدا کن» (`stlh_finder`، بخشِ `finder` در ترتیبِ صفحه‌ی اصلی): بودجه، مدل و حداقلِ سلامتِ باتری →
 *     گوشی‌های موجود همان لحظه، با همان کارتِ ردیف‌ها (`stlh_card`). هر گوشیِ کارکرده تک است، پس جست‌وجوی سه‌گزینه‌ای از
 *     اسلایدر بهتر است. داده همه سمتِ سرور ساخته و در حافظه‌ی موقتِ بخش‌ها (`stlh_cached`) می‌ماند؛ فیلتر با چند خط JS.
 *  ۲. «گوشی‌ات را چند می‌خریم؟» زیرِ دکمه‌ی خریدِ صفحه‌ی هر گوشی → برگه‌ی معاوضه با «گوشیِ دلخواه» پرشده (`?want=`).
 * سبکِ هر دو داخلِ خودِ خروجی است (قاعده‌ی CSSِ LiteSpeed در CLAUDE.md). متن‌ها از `stlh_label()`.
 */
defined( 'ABSPATH' ) || exit;

const STLH_FINDER_MAX = 80;
// فقط دسته‌ی گوشی‌ها و زیرشاخه‌هایش (`finder_cat`)؛ «گلس پک فلزی iPhone 17 Pro Max» از نامش گوشی خوانده می‌شد (۳۰ آبان ۱۴۰۵)

/** گوشی‌های موجود برای جست‌وجو: [محصول، نسل، قیمتِ تومان، باتری] */
function stlh_finder_items(): array {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return [];
	}
	$out = [];
	foreach ( wc_get_products( [ 'status' => 'publish', 'stock_status' => 'instock', 'category' => [ (string) stlh_opt( 'finder_cat' ) ], 'limit' => 300, 'orderby' => 'date', 'order' => 'DESC' ] ) as $p ) {
		$gen = stlh_model_number( $p->get_name() );
		if ( $gen < 7 ) {
			continue;
		}
		$price = (int) round( stlh_pg_toman( (float) $p->get_price() ) );
		if ( $price <= 0 ) {
			continue;
		}
		$bat   = (int) preg_replace( '/\D+/', '', stlh_ai_en_digits( (string) $p->get_meta( 'battery' ) ) );
		$out[] = [ $p, $gen, $price, $bat > 0 && $bat <= 100 ? $bat : 100 ]; // نو = باتریِ ۱۰۰
		if ( count( $out ) >= STLH_FINDER_MAX ) {
			break;
		}
	}
	return $out;
}

function stlh_finder(): string {
	$items = stlh_finder_items();
	if ( count( $items ) < 3 ) {
		return '';
	}
	$gens = array_unique( array_column( $items, 1 ) );
	rsort( $gens );
	$gen_opts = '<option value="0">' . esc_html( stlh_label( 'finder_any_model' ) ) . '</option>';
	foreach ( $gens as $g ) {
		$gen_opts .= '<option value="' . (int) $g . '">' . esc_html( stlh_label( 'finder_model', [ '{نسل}' => stlh_fa( (string) ( 10 === $g ? 'X' : ( 9 === $g ? 'SE' : $g ) ) ) ] ) ) . '</option>';
	}
	$budget = '<option value="0">' . esc_html( stlh_label( 'finder_any_budget' ) ) . '</option>';
	foreach ( [ 30, 50, 80, 120, 180, 250 ] as $m ) {
		$budget .= '<option value="' . ( $m * 1000000 ) . '">' . esc_html( stlh_label( 'finder_budget', [ '{میلیون}' => stlh_fa( (string) $m ) ] ) ) . '</option>';
	}
	$bat = '<option value="0">' . esc_html( stlh_label( 'finder_any_battery' ) ) . '</option>';
	foreach ( [ 85, 90, 95 ] as $b ) {
		$bat .= '<option value="' . $b . '">' . esc_html( stlh_label( 'finder_battery', [ '{درصد}' => stlh_fa( (string) $b ) ] ) ) . '</option>';
	}
	usort( $items, static fn( $a, $b ) => $a[2] <=> $b[2] ); // ارزان به گران
	$cards = '';
	foreach ( $items as [ $p, $gen, $price, $b ] ) {
		$cards .= '<div class="stlh-fx-it" data-g="' . (int) $gen . '" data-p="' . (int) $price . '" data-b="' . (int) $b . '">' . stlh_card( $p ) . '</div>';
	}
	// ۶ آذر ۱۴۰۵ (صاحب فروشگاه): «به‌جای دو ردیف کراسول مثلِ سایرِ لاین‌ها، از کناره‌ها هم‌اندازه‌ی فروش ویژه، فلش‌ها داخلِ
	// کادر بیاید تو، حرفه‌ای‌تر و سبک‌تر، فیلترها در یک خط». پس همان قالبِ ردیف‌ها (`st-pr-wrapper`/`st-pr-track`، دکمه‌های
	// قبلی/بعدی از home.js) و فیلترها قرصیِ کوچک با برچسبِ پنهان (برای صفحه‌خوان).
	$chev = 'url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2712%27 height=%2712%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%231d4ed8%27 stroke-width=%273%27 stroke-linecap=%27round%27%3E%3Cpath d=%27M6 9l6 6 6-6%27/%3E%3C/svg%3E")';
	$sel  = static fn( string $name, string $label, string $opts ) => '<label class="stlh-fx-f"><span class="stlh-fx-sr">' . esc_html( $label ) . '</span><select name="' . $name . '" aria-label="' . esc_attr( $label ) . '">' . $opts . '</select></label>';
	$icon = '<svg width="22" height="22" style="width:22px;height:22px;flex:0 0 auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
	// رنگ از تنظیمات (`band_colors['finder']`)؛ پیش‌فرض سفید و بی کادر، هماهنگ با قالب
	[ $bc, $bs, $bcss ] = stlh_band( 'finder' );
	return '<section class="st-pr-wrapper stlh-fx-band' . $bc . '"' . $bs . ' id="stlh-finder" dir="rtl"><style>' . $bcss . stlh_finder_css( $chev ) . '</style><div class="st-pr-container stlh-fx">'
		. '<div class="stlh-fx-head"><div class="stlh-fx-ttl"><span class="stlh-fx-ic">' . $icon . '</span><div><h2>' . esc_html( stlh_label( 'finder_title' ) ) . '</h2><p>' . esc_html( stlh_label( 'finder_sub' ) ) . '</p></div></div>'
		. '<span class="stlh-fx-count" aria-live="polite"></span></div>'
		. '<form class="stlh-fx-form" onsubmit="return false">'
		. $sel( 'p', stlh_label( 'finder_l_budget' ), $budget ) . $sel( 'g', stlh_label( 'finder_l_model' ), $gen_opts ) . $sel( 'b', stlh_label( 'finder_l_battery' ), $bat )
		. '</form>'
		. '<div class="st-pr-track"><div class="st-pr-row">' . $cards . '</div></div>'
		. '<p class="stlh-fx-none" hidden>' . esc_html( stlh_label( 'finder_none' ) ) . ' <a href="' . esc_url( stlh_wa_link( stlh_label( 'finder_wa' ) ) ) . '" target="_blank" rel="noopener">' . esc_html( stlh_label( 'ask_whatsapp' ) ) . '</a></p>'
		. '<script data-no-optimize="1">(function(){var r=document.getElementById("stlh-finder");if(!r)return;var f=r.querySelector("form"),row=r.querySelector(".st-pr-row"),it=[].slice.call(r.querySelectorAll(".stlh-fx-it")),c=r.querySelector(".stlh-fx-count"),n=r.querySelector(".stlh-fx-none"),'
		. 'fa=function(x){return String(x).replace(/\d/g,function(d){return "۰۱۲۳۴۵۶۷۸۹"[d]})},T=' . wp_json_encode( stlh_label( 'finder_count' ) ) . ';'
		. 'function run(){var p=+f.p.value,g=+f.g.value,b=+f.b.value,k=0;it.forEach(function(e){var ok=(!p||+e.dataset.p<=p)&&(!g||+e.dataset.g===g)&&(!b||+e.dataset.b>=b);e.hidden=!ok;if(ok)k++});'
		. 'c.textContent=k?T.replace("{تعداد}",fa(k)):"";n.hidden=!!k;row.parentNode.hidden=!k;row.scrollLeft=0;row.dispatchEvent(new Event("scroll"))}'
		. 'f.addEventListener("change",run);run()})();</script>'
		. '</div></section>';
}

function stlh_finder_css( string $chev = 'none' ): string {
	// رنگ‌ها از متغیرهای `stlh_band()`؛ بی آن‌ها (سفید) پیش‌فرض‌های روشنِ همین‌جا
	return '.stlh-fx *{box-sizing:border-box}.stlh-fx-sr{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}'
		. '.stlh-fx-band .st-pr-container.stlh-fx{margin:0 auto!important;box-sizing:border-box}'
		. '.stlh-fx-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;color:var(--ink,#0f172a)}'
		. '.stlh-fx-ttl{display:flex;align-items:center;gap:10px;min-width:0}.stlh-fx-ic{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:12px;background:var(--soft,#eff6ff);color:#1d4ed8;flex:0 0 auto}.st-band .stlh-fx-ic{color:var(--ink)}'
		. '.stlh-fx-head h2{margin:0;color:var(--ink,#0f172a);font-size:19px;font-weight:900;line-height:1.4}.stlh-fx-head p{margin:2px 0 0;color:var(--ink2,#64748b);font-size:12.5px;line-height:1.6}'
		. '.stlh-fx-count{flex:0 0 auto;background:var(--chip,#eff6ff);color:var(--chipink,#1d4ed8);font-weight:900;font-size:12.5px;padding:6px 12px;border-radius:999px;white-space:nowrap}.stlh-fx-count:empty{display:none}'
		. '.stlh-fx-form{display:flex;gap:8px;margin:0 0 14px}'
		. '.stlh-fx-f{flex:0 1 230px;min-width:0;position:relative;margin:0}'
		. '.stlh-fx-f select{-webkit-appearance:none;appearance:none;width:100%;height:40px!important;min-height:0!important;line-height:38px!important;margin:0;border:1px solid #e2e8f0!important;border-radius:999px!important;padding:0 14px 0 34px!important;font-size:13.5px;font-weight:700;color:#0f172a;font-family:inherit;cursor:pointer;text-overflow:ellipsis;background:#f8fafc ' . $chev . ' no-repeat left 14px center;background-size:12px}'
		. '.st-band .stlh-fx-f select{background-color:#fff!important;border-color:transparent!important;box-shadow:0 2px 8px -4px rgba(15,23,42,.35)}'
		. '.stlh-fx-f select:focus{outline:2px solid #93c5fd;outline-offset:1px}'
		. '.stlh-fx-band .st-pr-card{background:#fff}.stlh-fx-it[hidden],.stlh-fx .st-pr-track[hidden]{display:none!important}.stlh-fx-it .st-pr-card{height:100%}'
		. '.stlh-fx-none{text-align:center;color:var(--ink2,#475569);margin:6px 0 2px;font-size:14px}.stlh-fx-none a{color:var(--ink,#1d4ed8);font-weight:800;text-decoration:underline}.stlh-fx-none[hidden]{display:none}'
		. '@media(max-width:576px){.stlh-fx-head h2{font-size:16.5px}.stlh-fx-head p{display:none}.stlh-fx-ic{width:34px;height:34px;border-radius:10px}'
		. '.stlh-fx-form{gap:6px;margin-bottom:12px}.stlh-fx-f{flex:1 1 0}.stlh-fx-f select{height:36px!important;line-height:34px!important;font-size:12px;padding:0 10px 0 26px!important;background-position:left 10px center;background-size:10px}}';
}

/* ───── «گوشی‌ات را چند می‌خریم؟» در صفحه‌ی گوشی ───── */

add_action( 'woocommerce_after_add_to_cart_form', static function (): void {
	global $product;
	if ( ! $product instanceof WC_Product || stlh_model_number( $product->get_name() ) < 7 || ! function_exists( 'stlh_tradein_url' ) ) {
		return;
	}
	$url = add_query_arg( 'want', rawurlencode( $product->get_name() ), stlh_tradein_url() );
	echo '<a class="stlh-ti-btn" href="' . esc_url( $url ) . '" style="display:flex!important;align-items:center;justify-content:center;gap:8px;margin:12px 0;padding:11px 14px;border:1.5px dashed #1d4ed8!important;border-radius:12px;color:#1e3a8a!important;-webkit-text-fill-color:#1e3a8a!important;font-weight:800;text-decoration:none;background:#eef2ff!important;opacity:1!important;filter:none!important;text-shadow:none!important;font-size:14px;line-height:1.6">'
		. '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>'
		. '<span style="color:#1e3a8a!important;-webkit-text-fill-color:#1e3a8a!important;opacity:1!important">' . esc_html( stlh_label( 'tradein_btn' ) ) . '</span></a>';
} );
