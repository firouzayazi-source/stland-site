<?php
/**
 * پاکسازیِ یک‌باره‌ی فایل‌های زائدِ هاست.
 *
 * صاحب فروشگاه (۲۲ مهر ۱۴۰۵) بعد از گزارشِ `/stland/v1/disk`: «تمام آن‌هایی که تشخیص دادی اضافه است
 * یا واجب نیست همه را پاک کن خودت». پل فقط‌خواندنی است، پس فهرستِ **دقیق** این‌جاست و با نسخه‌ی افزونه
 * می‌رود؛ افزونه یک بار پاک می‌کند و نتیجه را در `stlh_cleanup_done` می‌گذارد
 * (`/wp-json/stland/v1/site-options?prefix=stlh_cleanup`).
 *
 * ⛔ فقط همین مسیرها، نسبت به پوشه‌ی خانه‌ی هاست؛ هیچ الگو یا ستاره‌ای. پیوند دنبال نمی‌شود و هر چیزی
 *    که realpathاش بیرونِ خانه یا خودِ یکی از پوشه‌های اصلی باشد رد می‌شود.
 * ⛔ نگه داشته می‌شوند: `stland-backups/` (پشتیبانِ حسابداری)، `sep-relay/`، `golzaripmg.ir/`، `error_log`ها.
 */
defined( 'ABSPATH' ) || exit;

const STLH_CLEANUP = [
	// نصب‌کننده‌ی قالبِ پولی و افزونه‌ی درگاه، از اینترنت دانلودشدنی
	'public_html/wp-content/uploads/bakala-9.1.5-6f1e8682e3a01ec5a7eefa18e44d44e3.zip',
	'public_html/wp-content/uploads/zhaket-woo-sep-1.2.3-ee8c57b71b956f764c80d3774e7bcad0.zip',
	// مانده از جابه‌جایی به پارس‌پک (ساختارِ هاستِ قبلی، داخلِ ریشه‌ی وب)
	'public_html/backup',
	'public_html/domains',
	'public_html/imap',
	'public_html/.htaccess.bk',
	// پشتیبان‌ها و خروجیِ پایگاه داده‌ی ۱۸–۱۹ مهر (بیرونِ ریشه‌ی وب، فقط فضا)
	'backup-stlandir_multidomain_2026-10-10-01-38-01.tar.gz',
	'stlandir_enuxwp.sql',
	'stlandir_golzariwp.sql', // خروجیِ پایگاه داده‌ی golzaripmg.ir؛ خودِ آن سایت دست نمی‌خورد
	// افزونه‌هایی که ۱.۴۹.۹ خاموش کرد — فقط اگر هنوز خاموش‌اند
	'public_html/wp-content/plugins/ajax-search-for-woocommerce',
	'public_html/wp-content/plugins/zhaket-updater',
];

add_action( 'init', 'stlh_cleanup_run', 30 );

function stlh_cleanup_run(): void {
	$hash = md5( (string) wp_json_encode( STLH_CLEANUP ) );
	if ( get_option( 'stlh_cleanup_hash' ) === $hash ) {
		return;
	}
	if ( ! add_option( 'stlh_cleanup_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'stlh_cleanup_lock' ) < 300 ) {
			return;
		}
		update_option( 'stlh_cleanup_lock', time(), false );
	}
	@set_time_limit( 120 );
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$home      = realpath( dirname( untrailingslashit( ABSPATH ) ) );
	$protected = array_filter( array_map( 'realpath', [ $home, ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR, get_theme_root(), wp_upload_dir()['basedir'], $home . '/stland-backups' ] ) );
	$done      = [];
	foreach ( STLH_CLEANUP as $rel ) {
		$path = $home . '/' . $rel;
		if ( ! file_exists( $path ) && ! is_link( $path ) ) {
			$done[ $rel ] = 'absent';
			continue;
		}
		$real = is_link( $path ) ? null : realpath( $path );
		if ( ! $real || ! str_starts_with( $real, $home . '/' ) || in_array( $real, $protected, true ) ) {
			$done[ $rel ] = 'refused';
			continue;
		}
		if ( str_starts_with( $real, realpath( WP_PLUGIN_DIR ) . '/' ) ) {
			$active = array_filter( array_keys( get_plugins() ), static fn( $f ) => str_starts_with( $f, basename( $real ) . '/' ) && is_plugin_active( $f ) );
			if ( $active ) {
				$done[ $rel ] = 'active-kept';
				continue;
			}
		}
		$freed        = 0;
		$ok           = is_dir( $real ) ? stlh_cleanup_rmdir( $real, $freed ) : ( ( $freed = (int) filesize( $real ) ) >= 0 && @unlink( $real ) );
		$done[ $rel ] = $ok ? 'deleted ' . round( $freed / 1048576, 1 ) . 'MB' : 'failed';
	}
	wp_clean_plugins_cache( false );
	delete_transient( 'stlh_disk_report' );
	update_option( 'stlh_cleanup_done', [ 'at' => gmdate( 'c' ), 'ver' => STLH_VER, 'items' => $done ], false );
	update_option( 'stlh_cleanup_hash', $hash, false );
	delete_option( 'stlh_cleanup_lock' );
}

/** پاک کردنِ بازگشتیِ یک پوشه، بی دنبال کردنِ پیوند */
function stlh_cleanup_rmdir( string $dir, int &$freed ): bool {
	$ok = true;
	foreach ( (array) @scandir( $dir ) as $e ) {
		if ( '.' === $e || '..' === $e || ! is_string( $e ) ) {
			continue;
		}
		$p = "$dir/$e";
		if ( is_dir( $p ) && ! is_link( $p ) ) {
			$ok = stlh_cleanup_rmdir( $p, $freed ) && $ok;
		} else {
			$freed += (int) @filesize( $p );
			$ok     = @unlink( $p ) && $ok;
		}
	}
	return @rmdir( $dir ) && $ok;
}
