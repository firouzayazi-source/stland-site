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
 * ⛔ نگه داشته می‌شوند: `stland-backups/` (پشتیبانِ رمزگذاری‌شده‌ی حسابداری)، `sep-relay/` (درگاهِ سامان)،
 *    `golzaripmg.ir/` (سایتِ دیگرِ صاحب فروشگاه — جز Duplicator و zipِ زیبانو).
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
	// Duplicator در سایتِ golzaripmg.ir (صاحب فروشگاه: «دوبلیکیتور را نمی‌خواهیم، خودِ سایت را می‌خواهیم»)؛
	// وردپرسِ آن سایت با نبودِ پوشه افزونه را خودش غیرفعال می‌کند
	'golzaripmg.ir/wp-content/plugins/duplicator',
	// فایلِ نصبِ افزونه‌ی قالبِ زیبانو، از وب دانلودشدنی؛ خودِ قالب و افزونه‌ی نصب‌شده می‌مانند
	'golzaripmg.ir/wp-content/themes/zibanoo/plugin/zibanoo-plugin.zip',
	// سطلِ زباله‌ی Media Cleaner (عکس‌های حذف‌شده‌ی ۱۳ مهر) و لاگش
	'public_html/wp-content/.media-cleaner-private-edeb42e161888f20e36f/trash',
	'public_html/wp-content/.media-cleaner-private-edeb42e161888f20e36f/logs',
	// لاگِ خطا (دوباره ساخته می‌شوند)؛ خلاصه‌شان پیش از پاک شدن در `stlh_cleanup_errors` می‌ماند
	'error_log',
	'public_html/error_log',
	'public_html/wp-admin/error_log',
	'public_html/wp-includes/customize/error_log',
	// فایلِ آزمایشیِ رله‌ی سپ — کارش تمام شده (صاحب فروشگاه، ۲۴ مهر ۱۴۰۵: «انجام بده»)
	'public_html/sep-relay/probe.php',
];

/**
 * `.htaccess`ِ رله‌ی سپ جا مانده بود: `/sep-relay/OnlinePG/OnlinePG` به ۴۰۴ِ وردپرس می‌رسید و رله دیده نمی‌شد.
 * متن عیناً از `stockland-bot/infra/sep-relay/.htaccess` (هیچ `RewriteBase`ی ننویسید — همان‌جا توضیح داده شده).
 * فقط اگر پوشه هست و فایل نیست؛ هیچ‌وقت روی فایلِ موجود نمی‌نویسد.
 */
const STLH_SEP_RELAY_HTACCESS = <<<'HT'
# همهٔ درخواست‌ها به index.php رله می‌روند؛ خودِ index.php مسیر را با فهرستِ سفید می‌سنجد.
# ⛔ RewriteBase ننویسید: مقصدِ نسبیِ index.php آن‌وقت index.phpِ وردپرس می‌شود.
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L,QSA]
</IfModule>

<FilesMatch "\.(md|log)$">
  Require all denied
</FilesMatch>
HT;

add_action( 'init', static function (): void {
	if ( get_option( 'stlh_sep_htaccess' ) ) {
		return;
	}
	$dir  = untrailingslashit( ABSPATH ) . '/sep-relay';
	$file = $dir . '/.htaccess';
	if ( ! is_dir( $dir ) || ! is_file( $dir . '/index.php' ) ) {
		update_option( 'stlh_sep_htaccess', [ 'at' => gmdate( 'c' ), 'result' => 'no relay dir' ], false );
		return;
	}
	$result = file_exists( $file ) ? 'exists, kept' : ( false !== @file_put_contents( $file, STLH_SEP_RELAY_HTACCESS . "\n" ) ? 'written' : 'write failed' );
	update_option( 'stlh_sep_htaccess', [ 'at' => gmdate( 'c' ), 'result' => $result ], false );
}, 31 );

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
	$errors    = [];
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
		if ( 'error_log' === basename( $real ) ) {
			$errors[ $rel ] = stlh_cleanup_error_summary( $real );
		}
		$freed        = 0;
		$ok           = is_dir( $real ) ? stlh_cleanup_rmdir( $real, $freed ) : ( ( $freed = (int) filesize( $real ) ) >= 0 && @unlink( $real ) );
		$done[ $rel ] = $ok ? 'deleted ' . round( $freed / 1048576, 1 ) . 'MB' : 'failed';
	}
	if ( $errors ) {
		update_option( 'stlh_cleanup_errors', $errors, false );
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

/** پرتکرارترین خطاهای یک error_log (بی تاریخ)، از ۵۰۰ کیلوبایتِ آخرش */
function stlh_cleanup_error_summary( string $file ): array {
	$size = (int) filesize( $file );
	$txt  = (string) @file_get_contents( $file, false, null, max( 0, $size - 512000 ) );
	$seen = [];
	$last = [];
	foreach ( explode( "\n", $txt ) as $line ) {
		$line = trim( (string) preg_replace( '/^\[[^\]]+\]\s*/', '', $line ) );
		if ( '' === $line || str_starts_with( $line, '#' ) || str_starts_with( $line, 'thrown in' ) ) {
			continue;
		}
		$key          = mb_substr( (string) preg_replace( '/\d+/', 'N', $line ), 0, 300 );
		$seen[ $key ] = ( $seen[ $key ] ?? 0 ) + 1;
		$last[ $key ] = mb_substr( $line, 0, 300 );
	}
	arsort( $seen );
	$out = [];
	foreach ( array_slice( $seen, 0, 25, true ) as $k => $n ) {
		$out[] = [ $n, $last[ $k ] ];
	}
	return [ 'bytes' => $size, 'top' => $out ];
}
