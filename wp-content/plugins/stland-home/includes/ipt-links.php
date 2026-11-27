<?php
/**
 * پیوندِ ابزارهای آیفون در سایت (صاحب فروشگاه، ۶ آذر ۱۴۰۵: «باید در جای مناسب و به صورت حرفه‌ای پیوند بخورد»).
 *
 *   • بخشِ صفحه‌ی اصلی `tools` («ابزارهای رایگانِ آیفون»، سه کارت) — در «ترتیبِ بخش‌ها» جابه‌جا یا برداشته می‌شود؛ یک بار بعد از
 *     «آیفونِ من را پیدا کن» نشانده می‌شود و اگر صاحب فروشگاه برش داشت، دیگر برنمی‌گردد.
 *   • منوی بالا (رایانه و گوشی): گروهِ «ابزارهای آیفون» با سه زیرمنو، کنارِ گروه‌های دسته (`stlh_live_menu_apply`؛ فقط با منوی زنده).
 *   • صفحه‌ی هر آیفون (فقط دسته‌ی گوشی‌ها): زیرِ «گوشیِ قبلی‌ات را چند می‌خریم؟» دو پیوندِ «مقایسه با مدلِ دیگر» (همین مدل از پیش
 *     انتخاب‌شده، `?a=`) و «تستِ سلامتِ آیفون».
 *   • برگه‌ی معاوضه: بالای فرم، «اول قیمتِ منصفانه را خودت ببین» → کارشناسی.
 *   • گردونه‌ی شانس (اگر روشن است) کارتِ چهارمِ بخش و آخرین موردِ منو است (نه در صفحه‌ی گوشی).
 * ابزارِ خاموش در Novo (`settings.price|health|compare`) هیچ‌جا پیوند نمی‌گیرد. متن‌ها از `stlh_label()`.
 */
defined( 'ABSPATH' ) || exit;

const STLH_IPT_TOOLS = [ 'price', 'health', 'compare' ];

/** ابزارهای روشن: ابزار => [عنوان، توضیح، نشانی] */
function stlh_ipt_links(): array {
	$out = [];
	if ( ! function_exists( 'stlh_ipt_cfg' ) ) {
		return $out;
	}
	$set = (array) stlh_ipt_cfg()['settings'];
	foreach ( STLH_IPT_TOOLS as $t ) {
		if ( ! empty( $set[ $t ] ) ) {
			$out[ $t ] = [ stlh_label( 'ipt_' . $t . '_title' ), stlh_label( 'tools_' . $t . '_desc' ), stlh_ipt_url( $t ) ];
		}
	}
	// گردونه‌ی شانس (`includes/wheel.php`) — کنارِ ابزارها، وقتی روشن است
	if ( function_exists( 'stlh_wheel_cfg' ) && stlh_wheel_cfg()['settings']['enabled'] ) {
		$out['wheel'] = [ stlh_label( 'wheel_title' ), stlh_label( 'tools_wheel_desc' ), stlh_wheel_url() ];
	}
	return $out;
}

/** آیکنِ هر ابزار — اندازه در خودِ برچسب (قاعده‌ی LiteSpeed) */
function stlh_ipt_icon( string $tool, int $s = 24 ): string {
	$p = [
		'price'   => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
		'health'  => '<path d="M19 14c1.5-1.5 3-3.3 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/><path d="M3.2 12h4.3l2-3 3 6 2-3h6.3"/>',
		'compare' => '<rect x="3" y="3" width="7" height="18" rx="2"/><rect x="14" y="3" width="7" height="18" rx="2"/><path d="M6.5 7h0M17.5 7h0"/>',
		'wheel'   => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18M5.6 5.6l12.8 12.8M18.4 5.6 5.6 18.4"/><circle cx="12" cy="12" r="2.2" fill="currentColor"/>',
	][ $tool ] ?? '';
	return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/* ───── بخشِ صفحه‌ی اصلی ───── */

function stlh_tools(): string {
	$links = stlh_ipt_links();
	if ( ! $links ) {
		return '';
	}
	[ $bc, $bs, $bcss ] = stlh_band( 'tools' );
	$cards = '';
	foreach ( $links as $t => [ $title, $desc, $url ] ) {
		$cards .= '<a class="stlh-tl-c stlh-tl-' . $t . '" href="' . esc_url( $url ) . '">'
			. '<span class="stlh-tl-ic">' . stlh_ipt_icon( $t ) . '</span>'
			. '<span class="stlh-tl-tx"><b>' . esc_html( $title ) . '</b><small>' . esc_html( $desc ) . '</small></span>'
			. '<span class="stlh-tl-go">' . esc_html( stlh_label( 'tools_go' ) ) . ' <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></span></a>';
	}
	return '<style>' . stlh_tools_css() . $bcss . '</style>'
		. '<section class="st-pr-wrapper stlh-tl-band' . $bc . '"' . $bs . ' id="stlh-tools"><div class="st-pr-container stlh-tl">'
		. stlh_section_head( stlh_label( 'tools_title' ), stlh_label( 'tools_sub' ) )
		. '<div class="stlh-tl-g">' . $cards . '</div></div></section>';
}

function stlh_tools_css(): string {
	return '.stlh-tl{margin:0 auto!important}'
		. '.stlh-tl-g{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:12px}'
		. '.stlh-tl-c{display:flex;align-items:center;gap:12px;padding:14px;border-radius:18px;background:#fff;border:1px solid #e8edf5;text-decoration:none!important;color:#0f172a!important;box-shadow:0 10px 26px -22px rgba(15,23,42,.6);transition:transform .15s,box-shadow .15s;min-width:0}'
		. '.stlh-tl-c:hover{transform:translateY(-2px);box-shadow:0 16px 30px -20px rgba(15,23,42,.55)}'
		. '.stlh-tl-ic{flex:0 0 46px;width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center}'
		. '.stlh-tl-price .stlh-tl-ic{background:#ecfdf5;color:#059669}.stlh-tl-health .stlh-tl-ic{background:#fef2f2;color:#dc2626}.stlh-tl-compare .stlh-tl-ic{background:#eff6ff;color:#1d4ed8}.stlh-tl-wheel .stlh-tl-ic{background:#fff7ed;color:#ea580c}'
		. '.stlh-tl-tx{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:3px;line-height:1.55}'
		. '.stlh-tl-tx b{font-size:15px;font-weight:900;color:#0f172a}.stlh-tl-tx small{font-size:12.5px;color:#64748b;font-weight:500}'
		. '.stlh-tl-go{flex:0 0 auto;display:flex;align-items:center;gap:2px;font-size:12.5px;font-weight:800;color:#1d4ed8;white-space:nowrap}'
		. '@media(max-width:820px){.stlh-tl-g{grid-template-columns:1fr;gap:8px}.stlh-tl-c{padding:11px 12px;border-radius:15px}.stlh-tl-ic{flex-basis:40px;width:40px;height:40px;border-radius:12px}'
		. '.stlh-tl-ic svg{width:21px;height:21px}.stlh-tl-tx b{font-size:14px}.stlh-tl-tx small{font-size:12px}.stlh-tl-go{font-size:0}.stlh-tl-go svg{width:16px;height:16px}}';
}

// یک بار بعد از «آیفونِ من را پیدا کن» در ترتیبِ ذخیره‌شده؛ برداشتنِ دستیِ بعدی سرِ جایش می‌ماند
add_action( 'init', static function (): void {
	if ( get_option( 'stlh_tools_placed' ) ) {
		return;
	}
	update_option( 'stlh_tools_placed', 1, false );
	$o = get_option( STLH_OPT );
	if ( ! is_array( $o ) || empty( $o['order'] ) ) {
		return; // ترتیبِ پیش‌فرض خودش `tools` دارد
	}
	$keys = stlh_lines( (string) $o['order'] );
	if ( in_array( 'tools', $keys, true ) ) {
		return;
	}
	$at = array_search( 'finder', $keys, true );
	$at = false !== $at ? $at + 1 : ( false !== ( $c = array_search( 'categories', $keys, true ) ) ? $c + 1 : count( $keys ) );
	array_splice( $keys, $at, 0, [ 'tools' ] );
	$o['order'] = implode( "\n", $keys );
	update_option( STLH_OPT, $o );
}, 20 );

/* ───── منوی بالا ───── */

/** زیرمنوی گروهِ «ابزارهای آیفون»: [[عنوان، نشانی]…] — خالی یعنی گروه نیاید */
function stlh_ipt_menu_tools(): array {
	return array_values( array_map( static fn( $l ) => [ $l[0], $l[2] ], stlh_ipt_links() ) );
}

/* ───── صفحه‌ی هر آیفون ───── */

/** نامِ مدلِ کاتالوگِ ابزارها از نامِ محصول («آیفون ۱۵ پرو مکس ۲۵۶…» → «iPhone 15 Pro Max»)؛ ناشناخته = '' */
function stlh_ipt_model_of( string $name ): string {
	$n = strtolower( strtr( $name, [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ] ) );
	$n = strtr( $n, [ 'آیفون' => 'iphone', 'ایفون' => 'iphone', 'پرومکس' => 'pro max', 'پرو مکس' => 'pro max', 'پرو' => 'pro', 'مکس' => 'max', 'پلاس' => 'plus', 'مینی' => 'mini', 'ایر' => 'air' ] );
	if ( ! preg_match( '/iphone\s*(\d{1,2}e?|xs|xr|x|se)(?![a-z0-9])\s*(pro\s*max|pro|plus|mini|max|air)?/u', $n, $m ) ) {
		return '';
	}
	$num  = strtoupper( $m[1] );
	$num  = in_array( $num, [ 'XS', 'XR', 'X', 'SE' ], true ) ? $num : strtolower( $num );
	$suf  = trim( preg_replace( '/\s+/', ' ', $m[2] ?? '' ) );
	$want = trim( 'iPhone ' . $num . ( '' !== $suf ? ' ' . ucwords( $suf ) : '' ) );
	if ( 'XS' === $num && 'Max' === ucwords( $suf ) ) {
		$want = 'iPhone XS Max';
	}
	foreach ( (array) stlh_ipt_cfg()['models'] as $mm ) {
		if ( 0 === strcasecmp( (string) ( $mm['name'] ?? '' ), $want ) ) {
			return (string) $mm['name'];
		}
	}
	return '';
}

/** این محصول در دسته‌ی گوشی‌هاست (`finder_cat` یا زیرشاخه‌اش)؟ — لوازمِ جانبیِ هم‌نام نه */
function stlh_ipt_is_phone( WC_Product $p ): bool {
	$root = get_term_by( 'slug', (string) stlh_opt( 'finder_cat' ), 'product_cat' );
	if ( ! $root ) {
		return false;
	}
	$ok = array_merge( [ (int) $root->term_id ], array_map( 'intval', (array) get_term_children( (int) $root->term_id, 'product_cat' ) ) );
	return (bool) array_intersect( $ok, array_map( 'intval', $p->get_category_ids() ) );
}

add_action( 'woocommerce_after_add_to_cart_form', static function (): void {
	global $product;
	if ( ! $product instanceof WC_Product || ! stlh_ipt_is_phone( $product ) ) {
		return;
	}
	$links = stlh_ipt_links();
	unset( $links['price'], $links['wheel'] ); // فروشِ گوشیِ قبلی را دکمه‌ی معاوضه دارد
	if ( ! $links ) {
		return;
	}
	$model = stlh_ipt_model_of( $product->get_name() );
	$h     = '';
	foreach ( $links as $t => [ $title, , $url ] ) {
		$label = stlh_label( 'compare' === $t ? 'tools_pp_compare' : 'tools_pp_health' );
		$href  = 'compare' === $t && '' !== $model ? add_query_arg( 'a', rawurlencode( $model ), $url ) : $url;
		$h    .= '<a href="' . esc_url( $href ) . '" style="flex:1 1 0;min-width:0;display:flex!important;align-items:center;justify-content:center;gap:6px;padding:9px 8px;border-radius:12px;border:1px solid #e2e8f0!important;background:#fff!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;opacity:1!important;font-weight:700;font-size:13px;line-height:1.5;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'
			. '<span style="display:flex;color:' . ( 'compare' === $t ? '#1d4ed8' : '#dc2626' ) . '!important">' . stlh_ipt_icon( $t, 16 ) . '</span>'
			. '<span style="color:#0f172a!important;-webkit-text-fill-color:#0f172a!important">' . esc_html( $label ) . '</span></a>';
	}
	echo '<div class="stlh-pp-tools" style="display:flex;gap:8px;margin:0 0 12px">' . $h . '</div>';
}, 12 );

/* ───── برگه‌ی معاوضه ───── */

add_filter( 'do_shortcode_tag', static function ( $out, $tag ) {
	if ( 'stl_trade_in' !== $tag || isset( $_GET['ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return $out;
	}
	$l = stlh_ipt_links()['price'] ?? null;
	if ( ! $l ) {
		return $out;
	}
	$box = '<a href="' . esc_url( $l[2] ) . '" style="display:flex;align-items:center;gap:10px;max-width:640px;margin:0 auto 14px;padding:12px 14px;border-radius:14px;background:#ecfdf5!important;border:1px solid #a7f3d0!important;color:#065f46!important;-webkit-text-fill-color:#065f46!important;text-decoration:none;font-weight:700;font-size:14px;line-height:1.7">'
		. '<span style="display:flex;flex:0 0 auto;color:#059669!important">' . stlh_ipt_icon( 'price', 22 ) . '</span>'
		. '<span style="flex:1 1 auto;min-width:0">' . esc_html( stlh_label( 'tools_tradein' ) ) . '</span>'
		. '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></a>';
	return $box . $out;
}, 10, 2 );
