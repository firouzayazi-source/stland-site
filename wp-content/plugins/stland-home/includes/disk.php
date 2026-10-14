<?php
/**
 * گزارشِ فضای هاست و فایل‌های زائد — فقط خواندن.
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «ببین چه فایل‌هایی داریم که بی‌استفاده‌اند، هم برای امنیت هم فضا، تا در
 * فایل‌منیجر پاکشان کنم.» هاست SSH ندارد و پل فقط GET است؛ این مسیر فهرست می‌دهد و **هیچ چیز پاک
 * نمی‌کند** — پاک کردن کارِ خودِ صاحب فروشگاه در فایل‌منیجر است.
 *
 *   GET /stland/v1/disk           (کشِ یک‌ساعته؛ `?fresh=1` از نو)
 *
 * ⛔ محتوای هیچ فایلی بیرون نمی‌رود، فقط مسیر و اندازه. فقط `manage_options`.
 * ⛔ پیمایش سقف دارد (تعداد و زمان) تا روی هاستِ اشتراکی بار نشود.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function (): void {
	register_rest_route( 'stland/v1', '/disk', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		'callback'            => 'stlh_disk_report',
	] );
} );

function stlh_disk_report( WP_REST_Request $req ): array {
	// `?errors=1` — آخرین خطاهای PHP (فقط خواندن؛ خطوطِ رازدار پوشیده). برای عیب‌یابیِ بی SSH.
	if ( ! empty( $req->get_param( 'errors' ) ) ) {
		return stlh_disk_error_tail();
	}
	$cached = get_transient( 'stlh_disk_report' );
	if ( $cached && empty( $req->get_param( 'fresh' ) ) ) {
		return $cached;
	}
	@set_time_limit( 90 );
	$wp      = untrailingslashit( ABSPATH );
	$home    = dirname( $wp );
	$uploads = wp_upload_dir()['basedir'];
	$rel     = static fn( string $p ): string => ltrim( substr( $p, strlen( $home ) ), '/' );

	$dirs    = [];   // پوشه (تا عمقِ ۴ از home) => [اندازه، تعداد]
	$flagged = [];
	$big     = [];
	$n       = 0;
	$t0      = microtime( true );
	$cut     = false;
	$ext_bad = '/\.(zip|tar|gz|tgz|rar|7z|sql|bak|backup|old|orig|save|swp|wpress|jpa|daf|log)$/i';

	try {
		// پیوندها دنبال نمی‌شوند و `.cagefs` (فایل‌سیستمِ مجازیِ CloudLinux) بیرون از open_basedir است
		$skip = [ $home . '/.cagefs', $home . '/.cl.selector', $home . '/mail', $home . '/etc' ];
		$it   = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $home, FilesystemIterator::SKIP_DOTS ),
				static function ( SplFileInfo $f ) use ( $skip ): bool {
					try {
						return ! $f->isLink() && ! in_array( $f->getPathname(), $skip, true );
					} catch ( Throwable ) {
						return false;
					}
				}
			),
			RecursiveIteratorIterator::SELF_FIRST,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);
		foreach ( $it as $f ) {
			if ( ++$n > 600000 || microtime( true ) - $t0 > 60 ) {
				$cut = true;
				break;
			}
			try {
				if ( ! $f->isFile() ) {
					continue;
				}
				$size  = (int) $f->getSize();
				$mtime = (int) $f->getMTime();
			} catch ( Throwable ) {
				continue;
			}
			$path = $f->getPathname();
			$r    = $rel( $path );
			$parts = explode( '/', $r );
			for ( $d = 1; $d <= min( 4, count( $parts ) - 1 ); $d++ ) {
				$k = implode( '/', array_slice( $parts, 0, $d ) );
				$dirs[ $k ][0] = ( $dirs[ $k ][0] ?? 0 ) + $size;
				$dirs[ $k ][1] = ( $dirs[ $k ][1] ?? 0 ) + 1;
			}
			$name = $f->getFilename();
			$why  = null;
			if ( preg_match( $ext_bad, $name ) || preg_match( '/^(error_log|php_errorlog|core\.\d+)$/', $name ) ) {
				$why = 'archive/log';
			} elseif ( str_starts_with( $path, $uploads . '/' ) && preg_match( '/\.(php\d?|phtml|phar|cgi|pl|sh)$/i', $name ) && 'index.php' !== $name ) {
				$why = 'script-in-uploads';
			} elseif ( preg_match( '/^(phpinfo|info|adminer|installer|test|shell|wso|up|upload|cmd)\.php$/i', $name ) ) {
				$why = 'suspicious-name';
			}
			if ( $why && count( $flagged ) < 400 ) {
				$flagged[] = [ $r, $size, $why, gmdate( 'Y-m-d', $mtime ) ];
			}
			if ( $size > 20 * 1048576 ) {
				$big[] = [ $r, $size, gmdate( 'Y-m-d', $mtime ) ];
			}
		}
	} catch ( Throwable $e ) {
		$cut = 'error: ' . $e->getMessage();
	}

	$top = [];
	foreach ( $dirs as $k => [ $s, $c ] ) {
		if ( $s >= 5 * 1048576 ) {
			$top[] = [ $k, round( $s / 1048576, 1 ), $c ];
		}
	}
	usort( $top, static fn( $a, $b ) => $b[1] <=> $a[1] );

	// ریشه‌ها: هر چیزی که در home و ریشه‌ی وردپرس هست (فقط نام و اندازه)
	$ls = static function ( string $dir ) use ( $dirs, $rel ): array {
		$out = [];
		foreach ( (array) @scandir( $dir ) as $e ) {
			if ( '.' === $e || '..' === $e || ! is_string( $e ) ) {
				continue;
			}
			$p     = "$dir/$e";
			$out[] = is_dir( $p ) ? [ $e . '/', round( ( $dirs[ $rel( $p ) ][0] ?? 0 ) / 1048576, 1 ) ] : [ $e, (int) @filesize( $p ) ];
		}
		return $out;
	};

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$inactive = [];
	foreach ( get_plugins() as $file => $data ) {
		if ( ! is_plugin_active( $file ) ) {
			$slug       = strtok( $file, '/' );
			$inactive[] = [ $file, $data['Name'], $data['Version'], round( ( $dirs[ $rel( WP_PLUGIN_DIR . '/' . $slug ) ][0] ?? 0 ) / 1048576, 1 ) ];
		}
	}
	$themes = [];
	foreach ( wp_get_themes() as $slug => $t ) {
		$themes[] = [ $slug, $t->get( 'Name' ), $t->get( 'Version' ), $slug === get_stylesheet() ? 'active' : ( $slug === get_template() ? 'parent' : 'inactive' ), round( ( $dirs[ $rel( $t->get_stylesheet_directory() ) ][0] ?? 0 ) / 1048576, 1 ) ];
	}
	$mu = $ls( WPMU_PLUGIN_DIR );

	$out = [
		'at'         => gmdate( 'c' ),
		'home'       => basename( $home ),
		'wp_dir'     => $rel( $wp ),
		'scanned'    => $n,
		'seconds'    => round( microtime( true ) - $t0, 1 ),
		'cut'        => $cut,
		'total_mb'   => round( array_sum( array_map( static fn( $k ) => $dirs[ $k ][0], array_filter( array_keys( $dirs ), static fn( $k ) => ! str_contains( $k, '/' ) ) ) ) / 1048576, 1 ),
		'home_ls'    => $ls( $home ),
		'wp_ls'      => $wp !== $home ? $ls( $wp ) : null,
		'wpc_ls'     => $ls( WP_CONTENT_DIR ),
		'top_dirs'   => array_slice( $top, 0, 120 ),
		'big_files'  => $big,
		'flagged'    => $flagged,
		'inactive_plugins' => $inactive,
		'themes'     => $themes,
		'mu_plugins' => $mu,
		'wpc_dirs'   => array_map( static fn( $e ) => $e, $ls( WP_CONTENT_DIR . '/uploads' ) ),
	];
	set_transient( 'stlh_disk_report', $out, HOUR_IN_SECONDS );
	return $out;
}

/*
 * ─── `public_html/sep-relay` — واسطِ درگاهِ سامان (صاحب فروشگاه: «باید درست کار کند، دقیق بررسی کن») ───
 *   GET /stland/v1/sep-relay              فهرستِ فایل‌ها
 *   GET /stland/v1/sep-relay?file=a.php   متن، با هر خطی که بوی رمز/ترمینال/کلید می‌دهد پوشیده
 * ⛔ فقط خواندن، فقط زیرِ همان پوشه (realpath).
 */
add_action( 'rest_api_init', function (): void {
	register_rest_route( 'stland/v1', '/sep-relay', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		'callback'            => 'stlh_sep_relay_report',
	] );
} );

function stlh_sep_relay_report( WP_REST_Request $req ): array|WP_Error {
	$root = realpath( ABSPATH . 'sep-relay' );
	if ( ! $root || ! is_dir( $root ) ) {
		return new WP_Error( 'stlh_not_found', 'پوشه‌ی sep-relay نیست.', [ 'status' => 404 ] );
	}
	$file = (string) $req->get_param( 'file' );
	if ( '' !== $file ) {
		$full = realpath( $root . '/' . ltrim( $file, '/' ) );
		if ( ! $full || ! is_file( $full ) || ! str_starts_with( $full, $root . '/' ) ) {
			return new WP_Error( 'stlh_not_found', 'فایل نیست.', [ 'status' => 404 ] );
		}
		$txt = (string) file_get_contents( $full, false, null, 0, 300000 );
		$txt = preg_replace_callback(
			'/^(.*(?:pass|secret|token|key|terminal|merchant|mid\b|tid\b|username|user_name|api).*)$/mi',
			static fn( $m ) => preg_replace( '/([\'"])[^\'"]{4,}\1/', '$1***$1', $m[1] ),
			$txt
		);
		return [ 'file' => $file, 'size' => filesize( $full ), 'mtime' => gmdate( 'c', (int) filemtime( $full ) ), 'content' => $txt ];
	}
	$files = [];
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $it as $f ) {
		if ( count( $files ) >= 500 ) {
			break;
		}
		$files[] = [ substr( $f->getPathname(), strlen( $root ) + 1 ) . ( $f->isDir() ? '/' : '' ), $f->isDir() ? null : $f->getSize(), gmdate( 'Y-m-d H:i', (int) $f->getMTime() ), substr( sprintf( '%o', $f->getPerms() ), -4 ) ];
	}
	sort( $files );
	return [ 'root' => 'public_html/sep-relay', 'files' => $files, 'php' => PHP_VERSION ];
}

/** ۸۰ خطِ آخرِ هر error_log/debug.log (بی تکرار پشتِ‌سرِ‌هم) */
function stlh_disk_error_tail(): array {
	$wp  = untrailingslashit( ABSPATH );
	$out = [];
	foreach ( [ $wp . '/error_log', $wp . '/wp-admin/error_log', WP_CONTENT_DIR . '/debug.log', dirname( $wp ) . '/logs/php.error.log' ] as $f ) {
		if ( ! is_readable( $f ) ) {
			continue;
		}
		$size  = (int) filesize( $f );
		$txt   = (string) @file_get_contents( $f, false, null, max( 0, $size - 200000 ) );
		$lines = array_slice( array_values( array_filter( explode( "\n", $txt ), 'strlen' ) ), -400 );
		$keep  = [];
		$prev  = '';
		foreach ( $lines as $l ) {
			$l   = preg_replace( '/((?:pass|secret|token|key)[^=:]*[=:]\s*)\S+/i', '$1***', mb_substr( $l, 0, 600 ) );
			$sig = preg_replace( '/^\[[^\]]+\]\s*/', '', $l );
			if ( $sig === $prev ) {
				continue;
			}
			$prev   = $sig;
			$keep[] = $l;
		}
		$out[ str_replace( dirname( $wp ), '', $f ) ] = [ 'bytes' => $size, 'mtime' => gmdate( 'c', (int) filemtime( $f ) ), 'tail' => array_slice( $keep, -80 ) ];
	}
	return [ 'now' => gmdate( 'c' ), 'logs' => $out ];
}
