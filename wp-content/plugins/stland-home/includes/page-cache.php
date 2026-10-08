<?php
/**
 * کشِ صفحه روی خودِ سرور — «صفحه باید همان ثانیه‌ی اول باز شود».
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «صفحه‌ی محصولی که تا حالا باز نشده ۵–۶ ثانیه طول
 * می‌کشد.» سنجش از داخلِ سرور: سرور Apache است و کشِ صفحه‌ی LiteSpeed فقط روی لبه‌ی
 * QUIC.cloud (خارج) کار می‌کند؛ هیچ پاسخی سرآیندِ `x-litespeed-cache` نداشت و همان صفحه
 * یک دقیقه بعد از گرم کردن دوباره ۳ ثانیه ساخته شد. یعنی هر بازدیدی که به لبه‌ای
 * می‌خورد که آن صفحه را ندارد، کلِ وردپرس را از صفر اجرا می‌کرد.
 *
 * ─── چطور ───
 * • `wp-content/advanced-cache.php` (از `includes/dropin-advanced-cache.php` کپی می‌شود؛
 *   `WP_CACHE` از قبل در wp-config روشن است): پیش از بار شدنِ وردپرس، فایلِ ذخیره‌شده را
 *   می‌فرستد؛ اگر نبود، خروجیِ **نهایی** (بعد از بهینه‌سازیِ LiteSpeed) را ذخیره می‌کند.
 * • این فایل تصمیم می‌گیرد چه چیزی ذخیره شود: فقط مهمان، فقط صفحه‌های عمومی (نه سبد،
 *   پرداخت، حساب، جستجو، ۴۰۴، پیش‌نمایش)؛ و صفحه‌ای که کوکی بگذارد هرگز.
 * • هر تغییری که کشِ بخش‌ها را پاک می‌کند (محصول، موجودی، دسته، تنظیمات — `stlh_cache_flush`)
 *   و هر ذخیره‌ی برگه/مقاله/منو/تنظیماتِ قالب، کلِ کش را پاک می‌کند؛ گرم‌کن (`warm.php`)
 *   ۲۰ ثانیه بعد صفحه‌ها را دوباره می‌سازد. عمرِ هر فایل حداکثر ۱۰ ساعت (nonceها معتبر بمانند).
 * ⛔ advanced-cache.phpِ کسِ دیگری را هرگز بازنویسی نمی‌کند. کلیدِ «کشِ صفحه» در تنظیمات
 *    خاموشش می‌کند (فایل برداشته و کش پاک می‌شود).
 */

defined( 'ABSPATH' ) || exit;

const STLH_PC_MARK = 'STLH-PAGE-CACHE';

function stlh_pc_dir(): string {
	return WP_CONTENT_DIR . '/cache/stlh-page/';
}

function stlh_pc_on(): bool {
	return '1' === (string) stlh_opt( 'page_cache' );
}

/** وضعیتِ فعلیِ advanced-cache.php: ours | foreign | none */
function stlh_pc_dropin_state(): string {
	$f = WP_CONTENT_DIR . '/advanced-cache.php';
	if ( ! is_file( $f ) ) {
		return 'none';
	}
	return str_contains( (string) file_get_contents( $f, false, null, 0, 400 ), STLH_PC_MARK ) ? 'ours' : 'foreign';
}

/** نصب/به‌روزرسانی یا برداشتنِ فایل‌ها مطابقِ کلید؛ یک بار برای هر نسخه یا تغییرِ کلید */
function stlh_pc_sync( bool $force = false ): array {
	$want  = stlh_pc_on() && defined( 'WP_CACHE' ) && WP_CACHE;
	$stamp = STLH_VER . '|' . ( $want ? 1 : 0 );
	if ( ! $force && get_option( 'stlh_pc_state' ) === $stamp ) {
		return [ 'skipped' => true ];
	}
	$state = stlh_pc_dropin_state();
	$dest  = WP_CONTENT_DIR . '/advanced-cache.php';
	$out   = [ 'want' => $want, 'before' => $state ];
	if ( $want ) {
		if ( 'foreign' !== $state ) {
			wp_mkdir_p( stlh_pc_dir() );
			$ok = @copy( STLH_DIR . 'includes/dropin-advanced-cache.php', $dest . '.tmp' ) && @rename( $dest . '.tmp', $dest );
			$out['installed'] = $ok;
			if ( $ok ) {
				@file_put_contents( stlh_pc_dir() . '.on', '1' );
				@file_put_contents( stlh_pc_dir() . '.htaccess', "Require all denied\nDeny from all\n" );
				@file_put_contents( stlh_pc_dir() . 'index.html', '' );
			}
		}
	} else {
		@unlink( stlh_pc_dir() . '.on' );
		if ( 'ours' === $state ) {
			@unlink( $dest );
		}
		stlh_pc_purge();
	}
	stlh_pc_purge();
	$out['after'] = stlh_pc_dropin_state();
	update_option( 'stlh_pc_state', $stamp, true );
	update_option( 'stlh_pc_install', $out + [ 'at' => gmdate( 'c' ) ], false );
	return $out;
}
add_action( 'init', static fn() => stlh_pc_sync(), 5 );

/** کلِ کشِ صفحه را پاک می‌کند (فقط فایل‌های html خودمان) */
function stlh_pc_purge(): int {
	$n = 0;
	foreach ( (array) glob( stlh_pc_dir() . '*.html' ) as $f ) {
		if ( 'index.html' !== basename( (string) $f ) && @unlink( (string) $f ) ) {
			$n++;
		}
	}
	return $n;
}

/* ─── چه چیزی ذخیره شود ─── */
add_action( 'template_redirect', static function (): void {
	if ( empty( $GLOBALS['stlh_pc'] ) ) {
		return; // advanced-cache این درخواست را از اول مناسب ندانست
	}
	$ok = stlh_pc_on()
		&& ! is_user_logged_in()
		&& ! is_admin()
		&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		&& ! wp_doing_ajax()
		&& ! is_404() && ! is_search() && ! is_feed() && ! is_preview() && ! is_trackback() && ! is_robots()
		&& ! post_password_required()
		&& ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) )
		&& ! ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() )
		&& ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->session->has_session() );
	$GLOBALS['stlh_pc']['store'] = $ok;
	if ( $ok ) {
		header( 'X-STLH-Cache: miss' );
	}
}, PHP_INT_MAX );

/* ─── چه چیزی کش را پاک می‌کند ─── */
add_action( 'stlh_cache_flushed', 'stlh_pc_purge' );
add_action( 'litespeed_purge_all', 'stlh_pc_purge' );
add_action( 'litespeed_purged_all', 'stlh_pc_purge' );
add_action( 'save_post', static function ( $id, $post ): void {
	if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || 'auto-draft' === $post->post_status ) {
		return;
	}
	if ( is_post_type_viewable( $post->post_type ) || 'wp_template' === $post->post_type || 'elementor_library' === $post->post_type ) {
		stlh_pc_purge();
		if ( function_exists( 'stlh_warm_queue' ) ) {
			stlh_warm_queue( stlh_warm_base() );
		}
	}
}, 99, 2 );
foreach ( [ 'deleted_post', 'trashed_post', 'wp_update_nav_menu', 'switch_theme', 'customize_save_after', 'update_option_bakala_options', 'update_option_sidebars_widgets', 'comment_post', 'transition_comment_status', 'upgrader_process_complete', 'activated_plugin', 'deactivated_plugin', 'woocommerce_settings_saved' ] as $stlh_h ) {
	add_action( $stlh_h, 'stlh_pc_purge', 99, 0 );
}
unset( $stlh_h );

/* برداشتنِ advanced-cache اگر افزونه غیرفعال شد — وگرنه کشِ کهنه بی‌صاحب می‌ماند */
register_deactivation_hook( STLH_DIR . 'stland-home.php', static function (): void {
	@unlink( stlh_pc_dir() . '.on' );
	if ( 'ours' === stlh_pc_dropin_state() ) {
		@unlink( WP_CONTENT_DIR . '/advanced-cache.php' );
	}
	stlh_pc_purge();
	delete_option( 'stlh_pc_state' );
} );

/** برای /perf */
function stlh_pc_report(): array {
	$files = (array) glob( stlh_pc_dir() . '*.html' );
	$files = array_filter( $files, static fn( $f ) => 'index.html' !== basename( (string) $f ) );
	return [
		'on'      => stlh_pc_on(),
		'wp_cache' => defined( 'WP_CACHE' ) && WP_CACHE,
		'dropin'  => stlh_pc_dropin_state(),
		'flag'    => is_file( stlh_pc_dir() . '.on' ),
		'pages'   => count( $files ),
		'kb'      => (int) round( array_sum( array_map( 'filesize', $files ) ) / 1024 ),
		'install' => get_option( 'stlh_pc_install', null ),
	];
}
