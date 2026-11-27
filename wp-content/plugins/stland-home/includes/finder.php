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
		$cards .= '<div class="stlh-fd-it" data-g="' . (int) $gen . '" data-p="' . (int) $price . '" data-b="' . (int) $b . '">' . stlh_card( $p ) . '</div>';
	}
	$sel = static fn( string $name, string $label, string $opts ) => '<label class="stlh-fd-f"><span>' . esc_html( $label ) . '</span><select name="' . $name . '">' . $opts . '</select></label>';
	return '<section class="stlh-fd" id="stlh-finder" dir="rtl"><style>' . stlh_finder_css() . '</style>'
		. '<div class="stlh-fd-head"><h2>' . esc_html( stlh_label( 'finder_title' ) ) . '</h2><p>' . esc_html( stlh_label( 'finder_sub' ) ) . '</p></div>'
		. '<form class="stlh-fd-form" onsubmit="return false">'
		. $sel( 'p', stlh_label( 'finder_l_budget' ), $budget ) . $sel( 'g', stlh_label( 'finder_l_model' ), $gen_opts ) . $sel( 'b', stlh_label( 'finder_l_battery' ), $bat )
		. '</form>'
		. '<p class="stlh-fd-count" aria-live="polite"></p>'
		. '<div class="stlh-fd-grid">' . $cards . '</div>'
		. '<button type="button" class="stlh-fd-more">' . esc_html( stlh_label( 'finder_more' ) ) . '</button>'
		. '<p class="stlh-fd-none">' . esc_html( stlh_label( 'finder_none' ) ) . ' <a href="' . esc_url( stlh_wa_link( stlh_label( 'finder_wa' ) ) ) . '" target="_blank" rel="noopener">' . esc_html( stlh_label( 'ask_whatsapp' ) ) . '</a></p>'
		. '<script data-no-optimize="1">(function(){var r=document.getElementById("stlh-finder");if(!r)return;var f=r.querySelector("form"),it=[].slice.call(r.querySelectorAll(".stlh-fd-it")),c=r.querySelector(".stlh-fd-count"),m=r.querySelector(".stlh-fd-more"),n=r.querySelector(".stlh-fd-none"),lim=8,'
		. 'fa=function(x){return String(x).replace(/\d/g,function(d){return "۰۱۲۳۴۵۶۷۸۹"[d]})},T=' . wp_json_encode( stlh_label( 'finder_count' ) ) . ';'
		. 'function run(){var p=+f.p.value,g=+f.g.value,b=+f.b.value,k=0,shown=0;it.forEach(function(e){var ok=(!p||+e.dataset.p<=p)&&(!g||+e.dataset.g===g)&&(!b||+e.dataset.b>=b);if(ok){k++;if(shown<lim){e.hidden=false;shown++}else e.hidden=true}else e.hidden=true});'
		. 'c.textContent=k?T.replace("{تعداد}",fa(k)):"";n.hidden=!!k;m.hidden=k<=lim}'
		. 'f.addEventListener("change",function(){lim=8;run()});m.addEventListener("click",function(){lim+=8;run()});run()})();</script>'
		. '</section>';
}

function stlh_finder_css(): string {
	return '.stlh-fd{max-width:1280px;margin:24px auto;padding:18px 16px;background:linear-gradient(135deg,#eef4ff,#fff);border:1px solid #dbe6ff;border-radius:20px;box-sizing:border-box}'
		. '.stlh-fd *{box-sizing:border-box}.stlh-fd-head h2{margin:0;font-size:20px;font-weight:900;color:#0f172a}.stlh-fd-head p{margin:4px 0 0;color:#475569;font-size:14px}'
		. '.stlh-fd-form{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:14px 0 6px}'
		. '.stlh-fd-f{display:flex;flex-direction:column;gap:4px;font-size:13px;color:#334155;font-weight:700}'
		. '.stlh-fd-f select{width:100%;height:44px;border:1px solid #cbd5e1;border-radius:12px;padding:0 10px;font-size:15px;background:#fff;font-family:inherit;color:#0f172a}'
		. '.stlh-fd-count{margin:6px 0 10px;color:#1d4ed8;font-weight:800;font-size:14px;min-height:1em}'
		. '.stlh-fd-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.stlh-fd-it[hidden]{display:none}'
		. '.stlh-fd-it .st-pr-card{height:100%}'
		. '.stlh-fd-more{display:block;margin:14px auto 0;border:1px solid #1d4ed8;background:#fff;color:#1d4ed8;border-radius:12px;padding:10px 22px;font-weight:800;font-family:inherit;cursor:pointer}.stlh-fd-more[hidden]{display:none}'
		. '.stlh-fd-none{text-align:center;color:#475569;margin:10px 0 0}.stlh-fd-none[hidden]{display:none}'
		. '@media(max-width:900px){.stlh-fd-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}'
		. '@media(max-width:600px){.stlh-fd{margin:16px 10px;padding:14px 12px}.stlh-fd-form{grid-template-columns:1fr 1fr}.stlh-fd-form .stlh-fd-f:first-child{grid-column:1/-1}.stlh-fd-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}}';
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
