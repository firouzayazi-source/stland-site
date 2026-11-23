<?php
/**
 * قرارداد ۸ (درخواستِ نشستِ Novo، ۲ آذر ۱۴۰۵ — صاحب فروشگاه، ۱ آذر: «شش صفر از سایت برود — ۱۹۵ میلیون تومان به‌جای ۱۹۵٬۰۰۰٬۰۰۰؛
 * هر وقت خواستم برگردد. و برای تخفیف انواعِ مختلف: یلدا، عید، جمعه‌ی سیاه، یا عادی، با تایمر.»).
 *
 *  • `stl_price_fmt` = `'million'` → قیمتِ همان محصول هر جا نمایش داده می‌شود «۱۹۵ میلیون تومان» (یک رقمِ اعشار با «٫» وقتی لازم است)؛
 *    زیرِ یک میلیون همان شکلِ کامل. ⛔ فقط نمایش (`woocommerce_get_price_html` و کارت‌های خودمان)؛ قیمتِ فروشگاه‌ساز، سبد، تسویه و
 *    درگاه دست نمی‌خورند.
 *  • `stl_sale_label` = متنِ مناسبتِ تخفیف («جمعه سیاه»، «شب یلدا»…، حداکثر ۳۰ نویسه) → برچسبِ کنارِ تخفیف در کارت و صفحه‌ی محصول،
 *    فقط وقتی حراجِ زنده هست. `''` یا نبودن = مثلِ قبل.
 * هر دو را Novo می‌فرستد؛ این‌جا فقط خوانده می‌شود (مالکیتِ داده).
 */
defined( 'ABSPATH' ) || exit;

function stlh_meta_of( WC_Product $p, string $key ): string {
	$id = ( method_exists( $p, 'get_parent_id' ) ? $p->get_parent_id() : 0 ) ?: $p->get_id();
	$v  = get_post_meta( $id, $key, true );
	return is_scalar( $v ) ? trim( (string) $v ) : '';
}

function stlh_price_million( WC_Product $p ): bool {
	return 'million' === stlh_meta_of( $p, 'stl_price_fmt' );
}

/** «۱۹۵ میلیون تومان» یا null اگر کمتر از یک میلیون است */
function stlh_million_text( float $amount ): ?string {
	$toman = function_exists( 'stlh_pg_toman' ) ? stlh_pg_toman( $amount ) : $amount;
	if ( $toman < 1000000 ) {
		return null;
	}
	$m   = round( $toman / 1000000, 1 );
	$num = rtrim( rtrim( number_format( $m, 1, '.', '' ), '0' ), '.' );
	return stlh_fa( str_replace( '.', '٫', $num ) ) . ' ' . stlh_label( 'price_million' );
}

/** یک مبلغ برای این محصول: شکلِ میلیونی (با همان کلاس‌های قیمتِ فروشگاه‌ساز) یا `wc_price` */
function stlh_price_amount_html( WC_Product $p, float $amount, bool $big = false ): string {
	$txt = stlh_price_million( $p ) ? stlh_million_text( $amount ) : null;
	if ( null === $txt ) {
		return wc_price( $amount );
	}
	if ( ! $big ) { // قیمتِ خط‌خورده: ساده و کوچک
		return '<span class="woocommerce-Price-amount amount"><bdi>' . esc_html( $txt ) . '&nbsp;<span class="woocommerce-Price-currencySymbol">' . esc_html( stlh_label( 'price_toman' ) ) . '</span></bdi></span>';
	}
	/*
	 * قیمتِ اصلیِ کالای میلیونی: عددِ درشت با رنگِ گرادیانی و «میلیون تومان» کوچکِ خاکستری (صاحب فروشگاه، ۲ آذر ۱۴۰۵: «عدد درشت‌تر،
	 * وسط‌چین، با رنگ و لعابِ بهتر… فقط روی اعدادی که شش صفر را برداشته‌ام»). سبک داخلِ خودِ برچسب‌هاست (قاعده‌ی CSSِ LiteSpeed).
	 */
	$num  = trim( str_replace( stlh_label( 'price_million' ), '', $txt ) );
	return '<span class="woocommerce-Price-amount amount st-mil" style="display:inline-flex;align-items:baseline;gap:5px;direction:rtl;white-space:nowrap"><bdi style="display:inline-flex;align-items:baseline;gap:5px">'
		. '<b class="st-mil-n" style="font-size:1.75em;line-height:1.1;font-weight:900;letter-spacing:-.5px;color:#1d4ed8;background:linear-gradient(90deg,#1d4ed8,#7c3aed);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent">' . esc_html( $num ) . '</b>'
		. '<span class="st-mil-u" style="font-size:.78em;font-weight:700;color:#64748b">' . esc_html( stlh_label( 'price_million' ) . ' ' . stlh_label( 'price_toman' ) ) . '</span>'
		. '</bdi></span>';
}

/** برچسبِ مناسبت (فقط با حراجِ زنده) */
function stlh_sale_occasion( WC_Product $p ): string {
	if ( ! $p->is_on_sale() ) {
		return '';
	}
	return mb_substr( wp_strip_all_tags( stlh_meta_of( $p, 'stl_sale_label' ) ), 0, 30 );
}

/** برچسب با متنِ داده‌شده (متن escape می‌شود؛ `{t}` برای قالبِ اسکریپتِ فهرست‌ها) */
function stlh_sale_occasion_tag( string $text, bool $big = false ): string {
	return '<span class="st-sale-tag" style="display:inline-flex;align-items:center;gap:4px;background:#111827;color:#fff;border-radius:999px;padding:' . ( $big ? '4px 12px' : '2px 9px' ) . ';font-size:' . ( $big ? '13px' : '11px' ) . ';font-weight:800;line-height:1.6;white-space:nowrap;margin:2px 0;vertical-align:middle;width:max-content;max-width:100%">'
		. '<svg width="' . ( $big ? 14 : 11 ) . '" height="' . ( $big ? 14 : 11 ) . '" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3.5 13.5V3.5h10l7.1 7.1a2 2 0 0 1 0 2.8z"/><circle cx="8.5" cy="8.5" r="1.6"/></svg>'
		. '<span>' . esc_html( $text ) . '</span></span>';
}

function stlh_sale_occasion_html( WC_Product $p, bool $big = false ): string {
	$t = stlh_sale_occasion( $p );
	return '' === $t ? '' : stlh_sale_occasion_tag( $t, $big );
}

/** [شناسه => برچسب] برای کالاهای حراجِ زنده با برچسبِ مناسبت — کارت‌های دسته و جست‌وجو (`catalog-cards.php`) */
function stlh_sale_occasion_map(): array {
	$map = get_transient( 'stlh_sale_occasions' );
	if ( is_array( $map ) ) {
		return $map;
	}
	global $wpdb;
	$map = [];
	foreach ( (array) $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'stl_sale_label' AND meta_value <> '' LIMIT 1000" ) as $r ) {
		$p = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $r->post_id ) : null;
		if ( $p && $p->is_on_sale() && 'publish' === $p->get_status() ) {
			$map[ (int) $r->post_id ] = mb_substr( wp_strip_all_tags( (string) $r->meta_value ), 0, 30 );
		}
	}
	set_transient( 'stlh_sale_occasions', $map, 10 * MINUTE_IN_SECONDS ); // حراجِ زمان‌بندی‌شده با شروعش خودبه‌خود دیده می‌شود
	return $map;
}
foreach ( [ 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_update_product_variation' ] as $stlh_h ) {
	add_action( $stlh_h, static fn() => delete_transient( 'stlh_sale_occasions' ) );
}

/*
 * حراجِ زمان‌بندی‌شده (Novo، ۲ آذر ۱۴۰۵، پیشنهاد): در صفحه‌ی محصول «تخفیف تا … دیگر شروع می‌شود». ⛔ قیمت پیش از شروع عوض نمی‌شود —
 * فقط خبر؛ خودِ فروشگاه‌ساز سرِ زمانش قیمت را عوض می‌کند.
 */
function stlh_sale_soon_html( WC_Product $p ): string {
	if ( $p->is_on_sale() || '' === (string) $p->get_sale_price( 'edit' ) ) {
		return '';
	}
	$from = $p->get_date_on_sale_from( 'edit' );
	$left = $from ? $from->getTimestamp() - time() : 0;
	if ( $left <= 0 || $left > 14 * DAY_IN_SECONDS ) {
		return '';
	}
	$d = intdiv( $left, DAY_IN_SECONDS );
	$h = intdiv( $left % DAY_IN_SECONDS, HOUR_IN_SECONDS );
	$m = max( 1, intdiv( $left % HOUR_IN_SECONDS, MINUTE_IN_SECONDS ) );
	$when = $d > 0 ? stlh_fa( (string) $d ) . ' روز' . ( $h ? ' و ' . stlh_fa( (string) $h ) . ' ساعت' : '' ) : ( $h > 0 ? stlh_fa( (string) $h ) . ' ساعت' : stlh_fa( (string) $m ) . ' دقیقه' );
	$label = stlh_meta_of( $p, 'stl_sale_label' );
	return '<div class="st-sale-soon" dir="rtl" style="margin:10px 0;padding:10px 14px;border-radius:12px;background:#111827;color:#fff;font-weight:800;font-size:14px;line-height:1.7;display:flex;align-items:center;gap:8px;flex-wrap:wrap">'
		. '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2"/><path d="M9 2h6"/></svg>'
		. '<span>' . esc_html( stlh_label( 'sale_soon', [ '{زمان}' => $when, '{مناسبت}' => '' !== $label ? $label : stlh_label( 'sale_soon_default' ) ] ) ) . '</span></div>';
}
function stlh_sale_soon_print(): void {
	static $done = false;
	global $product;
	$p = $product instanceof WC_Product ? $product : ( function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null );
	if ( $done || ! $p instanceof WC_Product || ! is_product() ) {
		return;
	}
	$html = stlh_sale_soon_html( $p );
	if ( '' !== $html ) {
		$done = true;
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
}
add_action( 'woocommerce_single_product_summary', 'stlh_sale_soon_print', 11 );
add_action( 'woocommerce_single_product_end', 'stlh_sale_soon_print', 2 );

// نمایشِ قیمت در همه‌جای فروشگاه‌ساز و قالب (صفحه‌ی محصول، فهرست‌ها، جست‌وجو)
add_filter( 'woocommerce_get_price_html', static function ( $html, $p ) {
	if ( ! $p instanceof WC_Product || is_admin() && ! wp_doing_ajax() ) {
		return $html;
	}
	if ( stlh_price_million( $p ) && ! $p->is_type( 'variable' ) && '' !== (string) $p->get_price() ) {
		$now = stlh_price_amount_html( $p, (float) $p->get_price(), true );
		$html = $p->is_on_sale() && (float) $p->get_regular_price() > (float) $p->get_price()
			? '<del aria-hidden="true">' . stlh_price_amount_html( $p, (float) $p->get_regular_price() ) . '</del> <ins>' . $now . '</ins>'
			: $now;
	}
	// برچسبِ مناسبت کنارِ قیمتِ صفحه‌ی خودِ محصول (کارت‌ها برچسب را جدا می‌گیرند — `stlh_card`)
	if ( function_exists( 'is_product' ) && is_product() && (int) get_queried_object_id() === (int) ( $p->get_parent_id() ?: $p->get_id() ) ) {
		$tag = stlh_sale_occasion_html( $p, true );
		if ( '' !== $tag ) {
			static $once = false;
			if ( ! $once ) {
				$once = true;
				$html = $tag . ' ' . $html;
			}
		}
	}
	return $html;
}, 50, 2 );
