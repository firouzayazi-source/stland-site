<?php
/**
 * بازبینیِ سئو (۲۶ آبان ۱۴۰۵). صاحب فروشگاه پرسید «بخشی هست که ضدِ سئو باشد یا ارتقا بخواهد؟» و گفت «همه را انجام بده».
 * بررسیِ صفحه‌های زنده از راهِ پل:
 *
 * ۱. دسته‌ها: عنوانِ پیش‌فرضِ افزونه‌ی سئو «بایگانی‌های آیفون کارکرده - استوک لند» بود و توضیحِ متا نداشتند. حالا
 *    «خرید {دسته} در قم | استوک لند» و یک توضیح (کلیدهای `seo_cat_*` در «نام‌ها و متن‌ها»). اگر برای دسته‌ای در
 *    پیشخوان عنوان/توضیحِ خودش نوشته شده باشد، همان می‌ماند. دسته‌های وبلاگ هم (`seo_blogcat_*`).
 * ۲. برچسب‌ها (۸۲ از ۱۰۰ فقط یک مقاله) و صفحه‌ی نویسنده (یک نویسنده): «noindex, follow» و بیرون از نقشه‌ی سایت.
 *    خودِ برچسب‌ها و پیوندهایشان می‌مانند.
 * ۳. گوشی‌های کارکرده‌ی هم‌نام (چهار «آیفون 13 128 گیگ»): فقط عنوانی که گوگل می‌بیند رنگ و سلامتِ باتری را می‌گیرد.
 *    نامِ محصول و عنوانِ سئوی حسابداری (`stl_seo_title`) دست نمی‌خورند؛ فقط هنگامِ نمایش.
 * ۴. مسیرِ صفحه دو بار به گوگل می‌رفت (افزونه‌ی سئو + فروشگاه‌ساز): نسخه‌ی فروشگاه‌ساز کنار رفت.
 * ۵. سیاستِ تعویض در دادهٔ محصول: ۷ روز، فقط تعویض، بی بازگرداندنِ وجه (همان پرسشِ متداولِ `stlh_store_pages`).
 *    هزینه‌ی ارسال عمداً نیست: عددش را نداریم و حدس نمی‌زنیم.
 * (تیترِ اصلیِ دومِ صفحه‌ی اصلی از `footer_credit`ِ قالب بود؛ در `theme-settings.php` درست شد.)
 *
 * کلیدِ `seo_boost` (پیش‌فرض روشن) همه را خاموش می‌کند.
 */

defined( 'ABSPATH' ) || exit;

function stlh_seo_boost_on(): bool {
	return '0' !== (string) stlh_opt( 'seo_boost' );
}

/** عنوان/توضیحی که در پیشخوان برای همین دسته نوشته شده (افزونه‌ی سئو) */
function stlh_seo_term_own( WP_Term $t, string $field ): string {
	if ( ! class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
		return '';
	}
	return trim( (string) WPSEO_Taxonomy_Meta::get_term_meta( $t, $t->taxonomy, $field ) );
}

/** [عنوان، توضیح] برای صفحه‌ی دسته‌ی فعلی، یا null */
function stlh_seo_cat_meta(): ?array {
	static $memo = false;
	if ( false !== $memo ) {
		return $memo;
	}
	$memo = null;
	if ( ! stlh_seo_boost_on() || is_admin() ) {
		return null;
	}
	$shop = function_exists( 'is_product_category' ) && is_product_category();
	if ( ! $shop && ! is_category() ) {
		return null;
	}
	$t = get_queried_object();
	if ( ! $t instanceof WP_Term ) {
		return null;
	}
	$pre  = $shop ? 'seo_cat_' : 'seo_blogcat_';
	$vars = [ '{دسته}' => $shop ? stlh_cat_name( $t ) : $t->name ];
	$page = (int) get_query_var( 'paged' );
	$title = stlh_label( $pre . 'title', $vars ) . ( $page > 1 ? stlh_label( 'seo_page', [ '{n}' => stlh_fa( (string) $page ) ] ) : '' ) . ' | ' . ( get_bloginfo( 'name' ) ?: 'استوک لند' );
	$memo = [
		'title' => stlh_seo_term_own( $t, 'title' ) ? null : $title,
		'desc'  => stlh_seo_term_own( $t, 'desc' ) ? null : stlh_label( $pre . 'desc', $vars ),
	];
	return $memo;
}

/** عنوانِ گوگلِ محصولِ هم‌نام: «… مشکی، باتری ۸۹٪»؛ null یعنی دست نزن */
function stlh_seo_product_suffix(): ?string {
	static $memo = false;
	if ( false !== $memo ) {
		return $memo;
	}
	$memo = null;
	if ( ! stlh_seo_boost_on() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return null;
	}
	$id   = (int) get_queried_object_id();
	$name = (string) get_post_field( 'post_title', $id );
	if ( '' === $name ) {
		return null;
	}
	global $wpdb;
	$same = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' AND post_title = %s", $name ) );
	if ( $same < 2 ) {
		return null;
	}
	$bits  = [];
	$color = trim( (string) get_post_meta( $id, 'color', true ) );
	if ( '' !== $color && false === mb_stripos( $name, $color ) ) {
		$bits[] = stlh_color_fa( $color );
	}
	$bat = (int) preg_replace( '/\D+/', '', stlh_ai_en_digits( (string) get_post_meta( $id, 'battery', true ) ) );
	if ( $bat > 0 && $bat <= 100 ) {
		$bits[] = stlh_label( 'seo_battery', [ '{n}' => stlh_fa( (string) $bat ) ] );
	}
	if ( ! $bits ) {
		return null;
	}
	$memo = $name . ' ' . implode( '، ', $bits );
	return $memo;
}

function stlh_seo_title_filter( $title ) {
	$title = (string) $title;
	if ( $m = stlh_seo_cat_meta() ) {
		return $m['title'] ?? $title;
	}
	if ( $full = stlh_seo_product_suffix() ) {
		$name = (string) get_post_field( 'post_title', get_queried_object_id() );
		$pos  = mb_strpos( $title, $name );
		return false === $pos ? $full . ' | ' . ( get_bloginfo( 'name' ) ?: 'استوک لند' ) : mb_substr( $title, 0, $pos ) . $full . mb_substr( $title, $pos + mb_strlen( $name ) );
	}
	return $title;
}
add_filter( 'wpseo_title', 'stlh_seo_title_filter', 30 );
add_filter( 'wpseo_opengraph_title', 'stlh_seo_title_filter', 30 );
add_filter( 'wpseo_twitter_title', 'stlh_seo_title_filter', 30 );

function stlh_seo_desc_filter( $desc ) {
	$m = stlh_seo_cat_meta();
	return $m && $m['desc'] ? $m['desc'] : $desc;
}
add_filter( 'wpseo_metadesc', 'stlh_seo_desc_filter', 30 );
add_filter( 'wpseo_opengraph_desc', 'stlh_seo_desc_filter', 30 );
add_filter( 'wpseo_twitter_description', 'stlh_seo_desc_filter', 30 );

/* ── ۲. برچسب و نویسنده بیرون از فهرستِ گوگل ── */

add_filter( 'wpseo_robots', static function ( $robots ) {
	if ( stlh_seo_boost_on() && ( is_tag() || is_author() ) ) {
		return 'noindex, follow';
	}
	return $robots;
}, 30 );

add_filter( 'wpseo_sitemap_exclude_taxonomy', static function ( $exclude, $taxonomy ) {
	return ( stlh_seo_boost_on() && 'post_tag' === $taxonomy ) ? true : $exclude;
}, 10, 2 );

add_filter( 'wpseo_sitemap_exclude_author', static function ( $users ) {
	return stlh_seo_boost_on() ? [] : $users;
} );

/* ── ۴. مسیرِ صفحه فقط یک بار ── */

add_filter( 'woocommerce_structured_data_breadcrumblist', static function ( $markup ) {
	return ( stlh_seo_boost_on() && defined( 'WPSEO_VERSION' ) ) ? [] : $markup;
}, 30 );

/* ── ۵. سیاستِ تعویض در دادهٔ محصول ── */

add_filter( 'woocommerce_structured_data_product', static function ( $markup ) {
	if ( ! stlh_seo_boost_on() || ! is_array( $markup ) || empty( $markup['offers'] ) || ! is_array( $markup['offers'] ) ) {
		return $markup;
	}
	$policy = [
		'@type'                => 'MerchantReturnPolicy',
		'applicableCountry'    => 'IR',
		'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
		'merchantReturnDays'   => 7,
		'refundType'           => 'https://schema.org/ExchangeRefund',
		'returnMethod'         => [ 'https://schema.org/ReturnInStore', 'https://schema.org/ReturnByMail' ],
	];
	foreach ( $markup['offers'] as &$offer ) {
		if ( is_array( $offer ) ) {
			$offer['hasMerchantReturnPolicy'] = $policy;
		}
	}
	unset( $offer );
	return $markup;
}, 30 );
