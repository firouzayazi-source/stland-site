<?php
/**
 * «خبرِ فوری» به بینگ و موتورهای دیگر (IndexNow) — صاحب فروشگاه، ۸ آبان ۱۴۰۵.
 *
 * چرا: ابزارِ وب‌مسترِ بینگ نشان داد بینگ از تیر ۱۴۰۵ فقط ۸ نشانی از نقشه‌ی سایتِ قدیمی دیده و مقاله‌ی تازه را «پیدا شده
 * ولی خوانده نشده» گذاشته؛ جست‌وجوی چت‌جی‌پی‌تی روی فهرستِ بینگ است. با IndexNow هر نوشته/محصول/برگه‌ای که منتشر یا عوض
 * شود، نشانی‌اش به `api.indexnow.org` فرستاده می‌شود (بینگ، یاندکس، سیک و… همه از همین یک نشانی می‌گیرند).
 *
 * چطور (آرام، مثلِ بقیه‌ی کارهای پس‌زمینه):
 *   • کلید یک بار ساخته و در `stlh_indexnow_key` نگه داشته می‌شود؛ فایلِ `/<کلید>.txt` همان را برمی‌گرداند (اثباتِ مالکیت).
 *   • هر تغییر فقط نشانی را در صف (`stlh_indexnow_queue`) می‌گذارد؛ یک رویدادِ زمان‌بندی ۵ دقیقه بعد همه را در **یک** درخواست
 *     می‌فرستد — همگام‌سازیِ حسابداری که ده‌ها محصول را پشتِ هم عوض می‌کند، یک درخواست می‌شود، نه ده‌ها.
 *   • نشانی‌ای که در ۶ ساعتِ گذشته فرستاده شده، دوباره فرستاده نمی‌شود.
 *   • یک بار (با `STLH_INDEXNOW_SEED_REV`) همه‌ی نوشته‌ها، محصولات، برگه‌ها و دسته‌های عمومی هم فرستاده می‌شوند — در رویدادِ
 *     جدای خودش (`stlh_indexnow_seed_urls`)، نه در صفِ مشترک که درخواست‌های هم‌زمان رویش می‌نویسند.
 *   • نتیجه‌ی ۲۰ ارسالِ آخر در `stlh_indexnow_log` (پل: `site-options?prefix=stlh_indexnow`).
 * فقط نشانیِ صفحه‌های عمومی می‌رود؛ چیزی از پایگاه داده‌ی دیگران نوشته یا خوانده نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

const STLH_INDEXNOW_SEED_REV = 2; // ۲: ارسالِ اولیه جدا از صف (در ۱ صفِ مشترک با درخواست‌های هم‌زمان رونویسی شد)
const STLH_INDEXNOW_HOOK     = 'stlh_indexnow_flush';

function stlh_indexnow_key(): string {
	$key = (string) get_option( 'stlh_indexnow_key', '' );
	if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
		$key = md5( wp_generate_password( 40, true, true ) . home_url() );
		update_option( 'stlh_indexnow_key', $key, true );
	}
	return $key;
}

/** `/<کلید>.txt` — پیش از هر کارِ دیگرِ وردپرس */
add_action( 'init', static function (): void {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	if ( ! preg_match( '#^/([a-f0-9]{32})\.txt$#', $path, $m ) || $m[1] !== stlh_indexnow_key() ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=UTF-8' );
	echo $m[1]; // phpcs:ignore — فقط ۳۲ رقمِ شانزده‌شانزدهی
	exit;
}, 1 );

/** نشانی را در صف بگذار و ارسال را ۵ دقیقه بعد زمان‌بندی کن */
function stlh_indexnow_queue( array $urls ): void {
	$home = untrailingslashit( home_url() );
	$q    = (array) get_option( 'stlh_indexnow_queue', [] );
	foreach ( $urls as $u ) {
		$u = (string) $u;
		if ( '' !== $u && str_starts_with( $u, $home ) ) {
			$q[ $u ] = 1;
		}
	}
	update_option( 'stlh_indexnow_queue', $q, false );
	if ( $q && ! wp_next_scheduled( STLH_INDEXNOW_HOOK ) ) {
		wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, STLH_INDEXNOW_HOOK );
	}
}

/** نوشته/محصول/برگه‌ی عمومی؟ */
function stlh_indexnow_public( WP_Post $post ): bool {
	return 'publish' === $post->post_status && empty( $post->post_password )
		&& in_array( $post->post_type, [ 'post', 'product', 'page' ], true )
		&& ! in_array( (int) $post->ID, stlh_indexnow_private_pages(), true );
}

/** سبد، پرداخت و حسابِ کاربری — برای موتورِ جست‌وجو نیستند */
function stlh_indexnow_private_pages(): array {
	return array_map( 'intval', array_filter( [
		get_option( 'woocommerce_cart_page_id' ),
		get_option( 'woocommerce_checkout_page_id' ),
		get_option( 'woocommerce_myaccount_page_id' ),
	] ) );
}

/* انتشار، به‌روزرسانی، و از انتشار درآمدن (موتور باید بفهمد صفحه رفته) */
add_action( 'transition_post_status', static function ( string $new, string $old, WP_Post $post ): void {
	if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || ! in_array( $post->post_type, [ 'post', 'product', 'page' ], true ) ) {
		return;
	}
	if ( ( 'publish' === $new || 'publish' === $old ) && ! in_array( (int) $post->ID, stlh_indexnow_private_pages(), true ) ) {
		$url = get_permalink( $post );
		stlh_indexnow_queue( $url ? [ $url ] : [] );
	}
}, 20, 3 );

/* قیمت و موجودیِ محصول (حسابداری) بی‌آن‌که وضعیتِ انتشار عوض شود */
add_action( 'woocommerce_update_product', static function ( $product_id ): void {
	$post = get_post( (int) $product_id );
	if ( $post && stlh_indexnow_public( $post ) ) {
		stlh_indexnow_queue( [ get_permalink( $post ) ] );
	}
}, 20 );

/** فرستادنِ صف در یک درخواست */
add_action( STLH_INDEXNOW_HOOK, 'stlh_indexnow_flush' );
function stlh_indexnow_flush(): void {
	$q = array_keys( (array) get_option( 'stlh_indexnow_queue', [] ) );
	if ( ! $q ) {
		return;
	}
	$sent = (array) get_option( 'stlh_indexnow_sent', [] );
	$now  = time();
	$sent = array_filter( $sent, static fn( $t ) => $now - (int) $t < 6 * HOUR_IN_SECONDS );
	$urls = array_values( array_filter( $q, static fn( $u ) => ! isset( $sent[ $u ] ) ) );
	update_option( 'stlh_indexnow_queue', [], false );
	if ( ! $urls ) {
		update_option( 'stlh_indexnow_sent', $sent, false );
		return;
	}
	stlh_indexnow_send( $urls, $sent );
}

/** یک درخواست به IndexNow؛ ناموفق → دوباره در صف، یک ساعتِ دیگر */
function stlh_indexnow_send( array $urls, ?array $sent = null ): void {
	$now  = time();
	$sent = $sent ?? array_filter( (array) get_option( 'stlh_indexnow_sent', [] ), static fn( $t ) => $now - (int) $t < 6 * HOUR_IN_SECONDS );
	$urls = array_slice( array_values( array_unique( $urls ) ), 0, 10000 ); // سقفِ هر درخواستِ IndexNow
	$key  = stlh_indexnow_key();
	$res  = wp_remote_post( 'https://api.indexnow.org/indexnow', [
		'timeout' => 15,
		'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
		'body'    => wp_json_encode( [
			'host'        => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'key'         => $key,
			'keyLocation' => home_url( '/' . $key . '.txt' ),
			'urlList'     => $urls,
		] ),
	] );
	$code = is_wp_error( $res ) ? $res->get_error_message() : (int) wp_remote_retrieve_response_code( $res );
	if ( in_array( $code, [ 200, 202 ], true ) ) {
		foreach ( $urls as $u ) {
			$sent[ $u ] = $now;
		}
	} else {
		// بی‌پاسخ یا رد شد: صف برمی‌گردد و یک ساعتِ دیگر دوباره (بی‌تکرارِ پشتِ هم)
		update_option( 'stlh_indexnow_queue', (array) get_option( 'stlh_indexnow_queue', [] ) + array_fill_keys( $urls, 1 ), false );
		if ( ! wp_next_scheduled( STLH_INDEXNOW_HOOK ) ) {
			wp_schedule_single_event( $now + HOUR_IN_SECONDS, STLH_INDEXNOW_HOOK );
		}
	}
	update_option( 'stlh_indexnow_sent', $sent, false );
	$log   = (array) get_option( 'stlh_indexnow_log', [] );
	$log[] = [ 'at' => gmdate( 'c', $now ), 'n' => count( $urls ), 'code' => $code, 'first' => $urls[0] ];
	update_option( 'stlh_indexnow_log', array_slice( $log, -20 ), false );
}

/** یک بار: همه‌ی صفحه‌های عمومیِ فعلی */
add_action( 'init', static function (): void {
	if ( (int) get_option( 'stlh_indexnow_seed', 0 ) >= STLH_INDEXNOW_SEED_REV ) {
		return;
	}
	$lock = (int) get_option( 'stlh_indexnow_seed_lock', 0 );
	if ( $lock && time() - $lock < 10 * MINUTE_IN_SECONDS ) {
		return;
	}
	update_option( 'stlh_indexnow_seed_lock', time(), false );
	$urls = [ home_url( '/' ) ];
	$ids  = get_posts( [
		'post_type'      => [ 'post', 'product', 'page' ],
		'post_status'    => 'publish',
		'has_password'   => false,
		'posts_per_page' => 2000,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] );
	foreach ( array_diff( array_map( 'intval', $ids ), stlh_indexnow_private_pages() ) as $id ) {
		$urls[] = get_permalink( $id );
	}
	foreach ( [ 'product_cat', 'category' ] as $tax ) {
		$terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => true ] );
		foreach ( is_array( $terms ) ? $terms : [] as $t ) {
			$link = get_term_link( $t );
			if ( is_string( $link ) ) {
				$urls[] = $link;
			}
		}
	}
	update_option( 'stlh_indexnow_seed_urls', array_values( array_unique( array_filter( $urls ) ) ), false );
	update_option( 'stlh_indexnow_seed', STLH_INDEXNOW_SEED_REV, false );
	delete_option( 'stlh_indexnow_seed_lock' );
	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'stlh_indexnow_seed_send' );
}, 50 );

add_action( 'stlh_indexnow_seed_send', static function (): void {
	$urls = (array) get_option( 'stlh_indexnow_seed_urls', [] );
	delete_option( 'stlh_indexnow_seed_urls' );
	if ( $urls ) {
		stlh_indexnow_send( $urls );
	}
} );
