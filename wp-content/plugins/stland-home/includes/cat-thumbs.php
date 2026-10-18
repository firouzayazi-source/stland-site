<?php
/**
 * عکسِ دسته‌هایی که عکس ندارند.
 *
 * صاحب فروشگاه (۲۶ مهر ۱۴۰۵، با عکسِ صفحه‌ی «دسته‌بندی‌ها» از دکمه‌ی پایینِ گوشی): «آیکون رو نداره، آیکون خودشو بذار
 * و خوشگل باشه، شکلکِ بدون شکل نباشه». ابزارکِ دسته‌های قالب (`/elementor-43744/`) عکسِ دسته را از متای `thumbnail_id`
 * می‌خواند و اگر نباشد عکسِ جای‌خالیِ قالب را می‌گذارد؛ حسابداری عکسِ دسته نمی‌فرستد. این‌جا هر دسته‌ای که عکسِ درست
 * ندارد همان آیکنِ صفحه‌ی اصلی (`stlh_cat_icon_key`) را به شکلِ کاشیِ رنگی می‌گیرد (`assets/cat-icons/<کلید>.webp`).
 *
 * عکسی که صاحب فروشگاه خودش گذاشته هیچ‌وقت عوض نمی‌شود — فقط جای خالی پر می‌شود.
 */

defined( 'ABSPATH' ) || exit;

// ۲: کاشیِ تخت با رنگِ زنده به‌جای گرادیان (صاحب فروشگاه، ۲۷ مهر: «همه تک‌بعدی، رنگ‌های زنده»)؛ با هر نسخه‌ی تازه‌ی
// کاشی‌ها، عکسِ قبلیِ خودِ افزونه عوض می‌شود — عکسی که صاحب فروشگاه گذاشته هرگز
const STLH_CAT_THUMBS_REV = 2;

/** کاشیِ یک کلید در کتابخانه‌ی رسانه (یک بار) → شناسه */
function stlh_cat_thumb_media( string $key ): int {
	$src = STLH_DIR . 'assets/cat-icons/' . $key . '.webp';
	if ( ! is_readable( $src ) ) {
		$key = 'tag';
		$src = STLH_DIR . 'assets/cat-icons/tag.webp';
	}
	$map  = (array) get_option( 'stlh_cat_thumb_media', [] );
	$slot = 'v' . STLH_CAT_THUMBS_REV . ':' . $key;
	if ( ! empty( $map[ $slot ] ) && get_post( (int) $map[ $slot ] ) ) {
		return (int) $map[ $slot ];
	}
	$up = wp_upload_bits( 'stland-cat-' . $key . '-v' . STLH_CAT_THUMBS_REV . '.webp', null, (string) file_get_contents( $src ) );
	if ( ! empty( $up['error'] ) ) {
		return 0;
	}
	$id = wp_insert_attachment( [ 'post_mime_type' => 'image/webp', 'post_title' => 'آیکنِ دسته', 'post_status' => 'inherit' ], $up['file'], 0, true );
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
	$map[ $slot ] = $id;
	update_option( 'stlh_cat_thumb_media', $map, false );
	return $id;
}

/** اگر دسته عکسِ درست ندارد، کاشیِ آیکنش را بگیرد — true یعنی چیزی عوض شد */
function stlh_cat_thumb_fill( WP_Term $term ): bool {
	$cur  = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
	$ours = array_map( 'intval', array_values( (array) get_option( 'stlh_cat_thumb_media', [] ) ) );
	if ( $cur && wp_attachment_is_image( $cur ) && ! in_array( $cur, $ours, true ) ) {
		return false; // عکسِ خودِ صاحب فروشگاه
	}
	$aid = stlh_cat_thumb_media( stlh_cat_icon_key( rawurldecode( $term->slug ), $term->name ) );
	if ( ! $aid || $aid === $cur ) {
		return false;
	}
	update_term_meta( $term->term_id, 'thumbnail_id', $aid );
	return true;
}

// یک بار برای دسته‌های فعلی
add_action( 'init', static function (): void {
	if ( (int) get_option( 'stlh_cat_thumbs_rev', 0 ) >= STLH_CAT_THUMBS_REV || ! taxonomy_exists( 'product_cat' ) ) {
		return;
	}
	update_option( 'stlh_cat_thumbs_rev', STLH_CAT_THUMBS_REV, false );
	$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
	$done  = 0;
	foreach ( is_array( $terms ) ? $terms : [] as $t ) {
		$done += (int) stlh_cat_thumb_fill( $t );
	}
	update_option( 'stlh_cat_thumbs_done', $done, false );
	if ( $done ) {
		do_action( 'litespeed_purge_url', '/elementor-43744/' );
	}
}, 45 );

// دسته‌ی تازه‌ای که حسابداری می‌سازد هم بی‌عکس نماند
add_action( 'created_product_cat', static function ( int $term_id ): void {
	$t = get_term( $term_id, 'product_cat' );
	if ( $t instanceof WP_Term ) {
		stlh_cat_thumb_fill( $t );
	}
} );

/*
 * «بدون دسته بندی» در صفحه‌ی دسته‌بندی‌ها و هر فهرستِ دسته‌ی سایت پنهان است (صاحب فروشگاه، ۲۷ مهر ۱۴۰۵: «نباید بیاید و
 * پنهان باشد، اما در Novo باید اعلان داشته باشد که تعیین تکلیف شود» — هشدارش در حسابداری، `woo/uncategorized.ts`).
 * دسته‌ی پیش‌فرضِ ووکامرس پاک‌شدنی نیست، پس فقط از فهرست‌های **صفحه‌ی مشتری** کنار می‌رود: پیشخوان، رابطِ برنامه‌نویسی
 * (حسابداری با آن کار می‌کند)، دسته‌های خودِ یک محصول (`object_ids`) و جست‌وجوی یک دسته با نامک یا شناسه دست نمی‌خورند. پیش‌فرض فقط وقتی کنار می‌رود که
 * واقعاً «بی‌دسته» است (`stlh_is_bucket`)، نه دسته‌ای مثلِ «تلفن همراه» که یک بار پیش‌فرض شده بود.
 */
add_filter( 'get_terms_args', static function ( $args, $taxonomies ) {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! empty( $args['object_ids'] ) || ! empty( $args['slug'] ) || ! empty( $args['include'] ) || ! empty( $args['term_taxonomy_id'] ) || ! in_array( 'product_cat', (array) $taxonomies, true ) ) {
		return $args;
	}
	$def = (int) get_option( 'default_product_cat', 0 );
	if ( ! $def || ! stlh_cat_is_hidden_bucket( $def ) ) {
		return $args;
	}
	$ex              = $args['exclude'] ?? [];
	$args['exclude'] = array_merge( is_array( $ex ) ? $ex : wp_parse_id_list( $ex ), [ $def ] );
	return $args;
}, 10, 2 );

function stlh_cat_is_hidden_bucket( int $id ): bool {
	static $memo = [];
	if ( ! isset( $memo[ $id ] ) ) {
		// `get_term` و `get_term_children` از `get_terms` نمی‌گذرند، پس این‌جا دوباره به همین فیلتر نمی‌رسیم
		$t           = get_term( $id, 'product_cat' );
		$memo[ $id ] = $t instanceof WP_Term && stlh_is_bucket( $t, [ $id => (array) get_term_children( $id, 'product_cat' ) ] );
	}
	return $memo[ $id ];
}

// یک بار: صفحه‌ی دسته‌بندی‌ها بی «بدون دسته بندی» دوباره ساخته شود
add_action( 'init', static function (): void {
	if ( (int) get_option( 'stlh_cat_bucket_hidden_rev', 0 ) >= 1 || ! defined( 'LSCWP_V' ) ) {
		return;
	}
	update_option( 'stlh_cat_bucket_hidden_rev', 1, false );
	do_action( 'litespeed_purge_url', '/elementor-43744/' );
}, 46 );

// یک بار (۱.۶۹.۰): آیکون‌های تخت در CSSِ درون‌خطیِ همه‌ی صفحه‌ها (منوی گوشی، صفحه‌ی محصول) و عکسِ قالب‌اند؛ برگه‌ها و
// محصول‌ها با به‌روزرسانیِ افزونه پاک نمی‌شوند، پس کلِ حافظه‌ی موقت یک بار
add_action( 'init', static function (): void {
	if ( (int) get_option( 'stlh_flat_icons_rev', 0 ) >= 1 || ! defined( 'LSCWP_V' ) ) {
		return;
	}
	update_option( 'stlh_flat_icons_rev', 1, false );
	do_action( 'litespeed_purge_all' );
}, 47 );
