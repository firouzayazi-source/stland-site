<?php
/**
 * سرعتِ پیشخوان و سبک کردنِ پایگاه داده.
 *
 * گزارشِ `/perf` (مهر ۱۴۰۵): بالا آمدنِ وردپرس در هر درخواست ~۰٫۹ ثانیه، ۴٬۴۷۹
 * گزینه‌ی autoload (۵۴۶ کیلوبایت) که در **هر** درخواست خوانده می‌شوند و بیشترشان
 * جامانده‌ی افزونه‌هایی‌اند که دیگر نصب نیستند (UberMenu، Hide My WP، Revolution
 * Slider، WP Rocket، Newsletter…)، ۶۲۹ ترنزینت، و داشبوردی پر از ویجت‌هایی که هر
 * بار چند درخواستِ بیرونی می‌زنند.
 *
 * ─── چه می‌کند ───
 * ۱. داشبورد: ویجت‌های خبر/پیش‌نویس/فعالیت/ووکامرس/Yoast/المنتور برداشته می‌شوند.
 * ۲. ووکامرس: بخش‌های تبلیغاتی و «شروعِ کار» و اعلان‌های دوردست خاموش
 *    (تحلیل‌ها و پنلِ فعالیت می‌مانند).
 * ۳. پایگاه داده، **یک بار برای هر نسخه**، وقتی مدیر وارد پیشخوان می‌شود:
 *    • گزینه‌های جامانده‌ی افزونه‌های غیرفعال → `autoload = no` (پاک **نمی‌شوند**؛
 *      برگشت‌پذیر است و فهرستشان در `stlh_autoload_off` می‌ماند).
 *    • `mnsjay_jaayegah_locales` (۶۱ کیلوبایت، فقط در پرداخت لازم) → `autoload = no`.
 *    • ترنزینت‌های منقضی پاک می‌شوند (کارِ خودِ هسته، فقط زودتر).
 *
 * ⛔ هیچ گزینه‌ای حذف نمی‌شود. چیزی که مالِ افزونه‌ی فعال است (جز مورد بالا) دست نمی‌خورد.
 */

defined( 'ABSPATH' ) || exit;

/* ۱. داشبورد */
add_action( 'wp_dashboard_setup', static function (): void {
	foreach ( [
		[ 'dashboard_primary', 'side' ],
		[ 'dashboard_quick_press', 'side' ],
		[ 'dashboard_activity', 'normal' ],
		[ 'woocommerce_dashboard_status', 'normal' ],
		[ 'woocommerce_dashboard_recent_reviews', 'normal' ],
		[ 'wc_admin_dashboard_setup', 'normal' ],
		[ 'wpseo-dashboard-overview', 'normal' ],
		[ 'wpseo-wincher-dashboard-overview', 'normal' ],
		[ 'e-dashboard-overview', 'normal' ],
		[ 'dashboard_php_nag', 'normal' ],
	] as [ $id, $ctx ] ) {
		remove_meta_box( $id, 'dashboard', $ctx );
	}
}, 99 );

/* ۲. ووکامرس */
add_filter( 'woocommerce_admin_features', static function ( array $features ): array {
	$off = [ 'marketing', 'onboarding', 'onboarding-tasks', 'remote-inbox-notifications', 'remote-free-extensions', 'wc-pay-promotion', 'wc-pay-welcome-page', 'shipping-label-banner', 'mobile-app-banner', 'launch-your-store', 'customer-effort-score-tracks', 'core-profiler', 'payment-gateway-suggestions', 'product-block-editor', 'printful' ];
	return array_values( array_filter( $features, static fn( $f ) => ! in_array( $f, $off, true ) ) );
} );
add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );
add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_true' );
add_filter( 'woocommerce_show_marketplace_suggestions', static fn() => 'no' );

/* ۳. پایگاه داده — یک بار برای هر نسخه */
function stlh_db_tune_leftovers(): array {
	/*
	 * پیشوندِ گزینه ← تکه‌ای از نامِ پوشه‌ی افزونه‌اش. اگر هیچ افزونه‌ی فعالی
	 * آن تکه را در نام ندارد، گزینه جامانده است و لازم نیست در هر درخواست بار شود.
	 */
	return [
		'ubermenu'           => 'ubermenu',
		'_ubermenu'          => 'ubermenu',
		'hide_my_wp'         => 'hide-my-wp',
		'revslider'          => 'revslider',
		'wp_rocket'          => 'wp-rocket',
		'newsletter'         => 'newsletter',
		'berocket'           => 'berocket',
		'br_'                => 'berocket',
		'yit_'               => 'yith',
		'yith_'              => 'yith',
		'googlefonts'        => 'google-font',
		'wpforms'            => 'wpforms',
		'elementor_pro_'     => 'elementor-pro',
		'rank_math'          => 'seo-by-rank-math',
		'wordfence'          => 'wordfence',
		'wp_mail_smtp'       => 'wp-mail-smtp',
		'jet_'               => 'jet-',
		'woodmart'           => 'woodmart',
		'wpcf7'              => 'contact-form-7',
		'duplicator'         => 'duplicator',
		'updraft'            => 'updraftplus',
		'wc_pb_'             => 'woocommerce-product-bundles',
		'dokan'              => 'dokan',
		'litespeed'          => 'litespeed-cache',
	];
}

function stlh_db_tune(): array {
	global $wpdb;
	$active   = array_map( 'dirname', (array) get_option( 'active_plugins', [] ) );
	$is_on    = static fn( string $needle ): bool => (bool) array_filter( $active, static fn( $d ) => str_contains( $d, $needle ) );
	$autoload = "autoload IN ('yes','on','auto','auto-on')";
	$changed  = [];

	foreach ( stlh_db_tune_leftovers() as $prefix => $dir ) {
		if ( $is_on( $dir ) ) {
			continue;
		}
		$like = $wpdb->esc_like( $prefix ) . '%';
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND $autoload", $like ) );
		foreach ( $rows as $name ) {
			$changed[] = $name;
		}
	}
	// سنگین ولی فقط در پرداخت لازم: شهرها و استان‌های «جایگاه»
	foreach ( [ 'mnsjay_jaayegah_locales' ] as $name ) {
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->options} WHERE option_name = %s AND $autoload", $name ) ) ) {
			$changed[] = $name;
		}
	}
	$changed = array_values( array_unique( $changed ) );
	$bytes   = 0;
	if ( $changed ) {
		$in    = implode( ',', array_fill( 0, count( $changed ), '%s' ) );
		$bytes = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name IN ($in)", ...$changed ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET autoload = 'no' WHERE option_name IN ($in)", ...$changed ) );
		wp_cache_delete( 'alloptions', 'options' );
		$prev = (array) get_option( 'stlh_autoload_off', [] );
		update_option( 'stlh_autoload_off', array_values( array_unique( array_merge( $prev, $changed ) ) ), false );
	}

	$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_site\\_transient\\_%'" );
	delete_expired_transients( true );
	$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_site\\_transient\\_%'" );

	return [
		'version'            => STLH_VER,
		'at'                 => gmdate( 'c' ),
		'autoload_off'       => count( $changed ),
		'autoload_off_kb'    => (int) round( $bytes / 1024 ),
		'transients_removed' => max( 0, $before - $after ),
		'transients_left'    => $after,
	];
}

add_action( 'admin_init', static function (): void {
	if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
		return;
	}
	$done = (array) get_option( 'stlh_db_tune', [] );
	if ( ( $done['version'] ?? '' ) === STLH_VER ) {
		return;
	}
	update_option( 'stlh_db_tune', stlh_db_tune(), false );
} );

/** برگشت: همه‌ی گزینه‌هایی که autoload‌شان خاموش شد، دوباره روشن (برای وقتی چیزی عجیب شد) */
function stlh_db_tune_revert(): int {
	global $wpdb;
	$names = (array) get_option( 'stlh_autoload_off', [] );
	if ( ! $names ) {
		return 0;
	}
	$in = implode( ',', array_fill( 0, count( $names ), '%s' ) );
	$n  = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET autoload = 'yes' WHERE option_name IN ($in)", ...$names ) );
	wp_cache_delete( 'alloptions', 'options' );
	delete_option( 'stlh_autoload_off' );
	return $n;
}
