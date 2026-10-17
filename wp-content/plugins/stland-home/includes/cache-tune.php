<?php
/**
 * کمتر پاک شدنِ کش و کمتر ساختنِ بی‌فایده — صاحب فروشگاه (۲۶ مهر ۱۴۰۵): «سرعت خوب نیست هنوز».
 *
 * سنجش (`/perf?probe=1` و پروفایلِ صفحه): صفحه از کشِ LiteSpeed ~۰٫۰۱ ثانیه، ساختِ سرد ~۲ تا ۳ ثانیه روی این هاستِ
 * اشتراکی. گرم‌کن عمداً خاموش است (`warm.php`)، پس سرعتی که مشتری می‌بیند یعنی «چند بار کش پاک می‌شود». سه کار:
 *
 * ۱. **قاعده‌های پاک‌سازیِ LiteSpeed** (یک بار، مقدارِ قبلی در `stlh_ls_purge_backup`): با هر به‌روزرسانیِ محصول
 *    (هر ارسالِ Novo، هر فروش) «همه‌ی برگه‌ها» (درباره، پرسش‌ها، تماس…) و بایگانیِ ماهانه هم پاک می‌شدند که هیچ
 *    ربطی به محصول ندارند؛ و **ساختنِ هر واژه‌ی تازه** (رنگ، برچسب، دسته‌ای که همگام‌سازی می‌سازد) **کلِ سایت** را
 *    پاک می‌کرد. محصول، صفحه‌ی اصلی، فروشگاه و دسته‌ها مثلِ قبل پاک می‌شوند؛ ویرایش و حذفِ واژه و منو هم.
 * ۲. **پوسته‌ی «همگام‌سازیِ سیستمِ طراحی»ِ المنتور**: این سایت چیزی برای همگام‌سازی ندارد، پس فایل هیچ‌وقت ساخته
 *    نمی‌شد و المنتور در **هر** ساختِ صفحه دوباره می‌ساختش (~۱۲۰ میلی‌ثانیه در پروفایل). وقتی خالی درآمد، ۱۲ ساعت
 *    دوباره امتحان نمی‌شود؛ تغییرِ کلاس‌های سراسریِ المنتور دوباره بازش می‌کند.
 * ۳. **دفترِ پاک‌سازیِ کامل**: هر بار که کلِ کش پاک شد، زمان و عاملش (۳۰ تای آخر) — `/perf` → `purge_log`؛ تا
 *    علتِ بعدی با عدد پیدا شود، نه حدس.
 */

defined( 'ABSPATH' ) || exit;

const STLH_LS_PURGE_SET = [
	'litespeed.conf.purge-post_p' => '', // «همه‌ی برگه‌ها» با هر محصول
	'litespeed.conf.purge-post_m' => '', // بایگانیِ ماهانه
];
const STLH_LS_PURGE_REV = 1;

add_action( 'init', static function (): void {
	if ( (int) get_option( 'stlh_ls_purge_rev', 0 ) >= STLH_LS_PURGE_REV || ! defined( 'LSCWP_V' ) ) {
		return;
	}
	$backup = (array) get_option( 'stlh_ls_purge_backup', [] );
	foreach ( STLH_LS_PURGE_SET as $key => $value ) {
		$backup[ $key ] ??= get_option( $key, null );
		update_option( $key, $value );
	}
	$key  = 'litespeed.conf.purge-hook_all';
	$hook = get_option( $key, [] );
	$list = is_array( $hook ) ? $hook : (array) json_decode( (string) $hook, true );
	if ( in_array( 'create_term', $list, true ) ) {
		$backup[ $key ] ??= $hook;
		$list = array_values( array_diff( $list, [ 'create_term' ] ) );
		update_option( $key, is_array( $hook ) ? $list : wp_json_encode( $list ) );
	}
	update_option( 'stlh_ls_purge_backup', $backup, false );
	update_option( 'stlh_ls_purge_rev', STLH_LS_PURGE_REV, false );
}, 30 );

/* ─── المنتور: پوسته‌ی همگام‌سازیِ خالی هر بار ساخته نشود ─── */
const STLH_DSS_EMPTY = 'stlh_dss_empty';

add_action( 'wp', static function (): void {
	if ( is_admin() || ! get_transient( STLH_DSS_EMPTY ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	$plugin = \Elementor\Plugin::$instance ?? null;
	$mm     = $plugin && isset( $plugin->modules_manager ) ? $plugin->modules_manager : null;
	$module = $mm && method_exists( $mm, 'get_modules' ) ? $mm->get_modules( 'design-system-sync' ) : null;
	if ( $module ) {
		remove_action( 'wp_enqueue_scripts', [ $module, 'enqueue_sync_stylesheet' ] );
	}
} );

// بعد از تلاشِ خودِ المنتور: اگر پوسته‌ای در صف نیامد، یعنی خالی بود
add_action( 'wp_enqueue_scripts', static function (): void {
	if ( is_admin() || get_transient( STLH_DSS_EMPTY ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	if ( ! wp_style_is( 'elementor-design-system-sync', 'enqueued' ) ) {
		set_transient( STLH_DSS_EMPTY, 1, 12 * HOUR_IN_SECONDS );
	}
}, 99 );

add_action( 'elementor/global_classes/update', static fn() => delete_transient( STLH_DSS_EMPTY ) );

/* ─── دفترِ پاک‌سازیِ کامل ─── */
add_action( 'litespeed_purged_all', static function (): void {
	$why = [];
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 ) as $f ) {
		$fn = ( isset( $f['class'] ) ? $f['class'] . '::' : '' ) . ( $f['function'] ?? '' );
		if ( '' === $fn || str_contains( $fn, 'Hook::' ) || in_array( $fn, [ 'do_action', 'apply_filters', '{closure}', 'call_user_func_array' ], true ) || str_starts_with( $fn, 'LiteSpeed\\' ) ) {
			continue;
		}
		$why[] = $fn;
		if ( count( $why ) >= 4 ) {
			break;
		}
	}
	$log   = (array) get_option( 'stlh_purge_log', [] );
	$log[] = [
		'at'   => gmdate( 'c' ),
		'hook' => (string) current_action(),
		'by'   => implode( ' ← ', $why ),
		'uri'  => substr( (string) ( $_SERVER['REQUEST_URI'] ?? ( PHP_SAPI === 'cli' ? 'cli' : '' ) ), 0, 80 ),
	];
	update_option( 'stlh_purge_log', array_slice( $log, -30 ), false );
} );
