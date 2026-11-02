<?php
/**
 * Plugin Name:       StockLand Updater
 * Description:       افزونه‌های استوک لند را از گیت‌هاب به‌روز می‌کند — مثل آپدیت معمولی افزونه‌ها، بدون FTP.
 * Version:           1.4.0
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * Text Domain:       stland-updater
 *
 * چرا این افزونه: هاست ایرانی اتصال FTP از بیرون ایران را رد می‌کند (531).
 * پس به‌جای اینکه گیت‌هاب به سایت بفرستد، سایت خودش از گیت‌هاب برمی‌دارد.
 * هر پوش روی main در Release «latest» یک manifest.json و zip هر افزونه
 * می‌گذارد؛ این افزونه manifest را می‌خواند و نسخه‌ی تازه‌تر را در همان
 * فهرست آپدیت‌های وردپرس نشان می‌دهد.
 *
 * ⛔ وردپرس فقط وقتی آپدیت می‌کند که نسخه بالاتر باشد. پس هر تغییری در
 *    یک افزونه یعنی بالا بردن Version آن — ورک‌فلو اگر فراموش شود قرمز
 *    می‌شود (scripts/verify-bump.php).
 */

defined( 'ABSPATH' ) || exit;

const STLU_REPO     = 'firouzayazi-source/stland-site';
// Overridable in wp-config.php only for testing against a local copy of the release.
defined( 'STLU_RELEASE' ) || define( 'STLU_RELEASE', 'https://github.com/' . STLU_REPO . '/releases/download/latest/' );
const STLU_OPT_AUTO = 'stlu_auto';
const STLU_CACHE    = 'stlu_manifest';

/**
 * manifest.json از Release — نیم ساعت در کش.
 *
 * @return array{sha:string,builtAt:string,plugins:array<string,array{version:string,zip:string}>}|null
 */
function stlu_manifest( bool $fresh = false ): ?array {
	if ( ! $fresh ) {
		$cached = get_site_transient( STLU_CACHE );
		if ( is_array( $cached ) ) {
			return $cached ?: null;
		}
	}
	$data = '' !== stlu_token() ? stlu_manifest_private() : null;
	if ( null === $data ) {
		$res  = wp_remote_get( STLU_RELEASE . 'manifest.json', [ 'timeout' => 15 ] );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$data = 200 === $code ? json_decode( (string) wp_remote_retrieve_body( $res ), true ) : null;
	}
	$ok = is_array( $data ) && isset( $data['plugins'] ) && is_array( $data['plugins'] );
	// شکست هم کش می‌شود (کوتاه‌تر) تا هر بار باز کردن پیشخوان منتظر گیت‌هاب نماند.
	set_site_transient( STLU_CACHE, $ok ? $data : [], $ok ? 30 * MINUTE_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	return $ok ? $data : null;
}

/*
 * ─── مخزنِ خصوصی ───
 * صاحب فروشگاه (۱۰ آبان ۱۴۰۵) مخزن را برای امنیت خصوصی کرد؛ از آن به بعد نشانیِ عمومیِ Release جوابِ ۴۰۴ می‌دهد و سایت
 * هیچ نسخه‌ای نمی‌گیرد. پس با یک کلیدِ فقط‌خواندنیِ گیت‌هاب (Fine-grained، فقط همین مخزن، «Contents: Read-only») از راهِ
 * API خوانده می‌شود: فهرستِ فایل‌های Releaseِ `latest`، بعد هر فایل با `Accept: application/octet-stream`. گیت‌هاب به یک
 * نشانیِ امضاشده‌ی موقت می‌فرستد که **بی** کلید خوانده می‌شود (کلید فقط به api.github.com می‌رود).
 * کلید: ثابتِ `STLU_TOKEN` در wp-config.php، وگرنه گزینه‌ی `stlu_token` از همین صفحه‌ی تنظیمات (بی autoload). هرگز نمایش داده
 * یا در گزارش نوشته نمی‌شود. بی کلید، همان راهِ عمومیِ قبلی.
 */
function stlu_token(): string {
	$t = defined( 'STLU_TOKEN' ) ? (string) STLU_TOKEN : (string) get_option( 'stlu_token', '' );
	return preg_match( '/^[A-Za-z0-9_]{20,255}$/', $t ) ? $t : '';
}

function stlu_api_args( array $extra = [] ): array {
	return array_merge( [
		'timeout' => 20,
		'headers' => [
			'Authorization'        => 'Bearer ' . stlu_token(),
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'stland-updater',
		],
	], $extra );
}

const STLU_ASSET_API = 'https://api.github.com/repos/' . STLU_REPO . '/releases/assets/';

/** نشانیِ امضاشده‌ی یک فایلِ Release (بی دنبال کردنِ هدایت، تا کلید به سرورِ دیگری نرود) */
function stlu_asset_url( int $id ) {
	$args = stlu_api_args( [ 'redirection' => 0 ] );
	$args['headers']['Accept'] = 'application/octet-stream';
	$res = wp_remote_get( STLU_ASSET_API . $id, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$loc  = (string) wp_remote_retrieve_header( $res, 'location' );
	if ( $code >= 300 && $code < 400 && '' !== $loc ) {
		return $loc;
	}
	return new WP_Error( 'stlu_asset', 'GitHub ' . $code );
}

function stlu_manifest_private(): ?array {
	$res = wp_remote_get( 'https://api.github.com/repos/' . STLU_REPO . '/releases/tags/latest', stlu_api_args() );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$rel    = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$assets = [];
	foreach ( (array) ( $rel['assets'] ?? [] ) as $a ) {
		if ( isset( $a['name'], $a['id'] ) ) {
			$assets[ (string) $a['name'] ] = (int) $a['id'];
		}
	}
	if ( empty( $assets['manifest.json'] ) ) {
		return null;
	}
	$url = stlu_asset_url( $assets['manifest.json'] );
	if ( is_wp_error( $url ) ) {
		return null;
	}
	$res  = wp_remote_get( $url, [ 'timeout' => 15 ] );
	$data = ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ? json_decode( (string) wp_remote_retrieve_body( $res ), true ) : null;
	if ( ! is_array( $data ) || ! isset( $data['plugins'] ) || ! is_array( $data['plugins'] ) ) {
		return null;
	}
	foreach ( $data['plugins'] as $slug => &$info ) {
		$zip = (string) ( $info['zip'] ?? '' );
		if ( isset( $assets[ $zip ] ) ) {
			$info['asset'] = $assets[ $zip ];
		}
	}
	unset( $info );
	return $data;
}

/** بسته‌ی هر افزونه: مخزنِ خصوصی → نشانیِ API (دانلودش با `upgrader_pre_download` پایین)، وگرنه نشانیِ عمومی */
function stlu_package( array $info ): string {
	return ! empty( $info['asset'] ) && '' !== stlu_token()
		? STLU_ASSET_API . (int) $info['asset']
		: STLU_RELEASE . rawurlencode( (string) $info['zip'] );
}

// دانلودِ بسته از مخزنِ خصوصی: وردپرس خودش سرآیندِ کلید را نمی‌فرستد
add_filter( 'upgrader_pre_download', static function ( $reply, $package ) {
	if ( ! is_string( $package ) || ! str_starts_with( $package, STLU_ASSET_API ) ) {
		return $reply;
	}
	$url = stlu_asset_url( (int) substr( $package, strlen( STLU_ASSET_API ) ) );
	if ( is_wp_error( $url ) ) {
		return $url;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$tmp = wp_tempnam( 'stland-plugin.zip' );
	$res = wp_remote_get( $url, [ 'timeout' => 120, 'stream' => true, 'filename' => $tmp ] );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		@unlink( $tmp ); // phpcs:ignore
		return is_wp_error( $res ) ? $res : new WP_Error( 'stlu_download', 'GitHub ' . wp_remote_retrieve_response_code( $res ) );
	}
	return $tmp;
}, 10, 2 );

function stlu_file( string $slug ): string {
	return $slug . '/' . $slug . '.php';
}

function stlu_item( string $slug, array $info ): object {
	return (object) [
		'id'           => 'github.com/' . STLU_REPO . '/' . $slug,
		'slug'         => $slug,
		'plugin'       => stlu_file( $slug ),
		'new_version'  => (string) $info['version'],
		'url'          => 'https://github.com/' . STLU_REPO,
		'package'      => stlu_package( $info ),
		'requires_php' => '8.1',
		'icons'        => [],
		'banners'      => [],
	];
}

/*
 * ─── آپدیت در فهرست معمولی وردپرس ───
 *
 * ⛔ روی «خواندنِ» فهرست سوار است، نه «نوشتنش». وردپرس فهرست را فقط وقتی
 *    می‌نویسد که api.wordpress.org جواب بدهد؛ از هاست ایرانی همیشه جواب
 *    نمی‌دهد و آن‌وقت آپدیتِ ما هم هیچ‌وقت دیده نمی‌شد. خواندن همیشه هست —
 *    صفحه‌ی افزونه‌ها، دکمه‌ی به‌روزرسانی و به‌روزرسانیِ خودکار همه از آن می‌خوانند.
 */
add_filter( 'site_transient_update_plugins', function ( $transient ) {
	$manifest = stlu_manifest();
	if ( ! $manifest ) {
		return $transient;
	}
	if ( ! is_object( $transient ) ) {
		$transient = (object) [ 'last_checked' => time(), 'checked' => [], 'response' => [], 'no_update' => [], 'translations' => [] ];
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$installed = get_plugins();
	foreach ( $manifest['plugins'] as $slug => $info ) {
		$file = stlu_file( (string) $slug );
		if ( ! isset( $installed[ $file ] ) ) {
			continue;
		}
		$item = stlu_item( (string) $slug, $info );
		unset( $transient->response[ $file ], $transient->no_update[ $file ] );
		if ( version_compare( $item->new_version, (string) $installed[ $file ]['Version'], '>' ) ) {
			$transient->response[ $file ] = $item;
		} else {
			$transient->no_update[ $file ] = $item;
		}
	}
	return $transient;
} );

// «مشاهده‌ی جزئیات» — بدون این، وردپرس از wordpress.org می‌پرسد و خطا می‌دهد.
add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
		return $result;
	}
	$manifest = stlu_manifest();
	if ( ! $manifest || ! isset( $manifest['plugins'][ $args->slug ] ) ) {
		return $result;
	}
	$info = $manifest['plugins'][ $args->slug ];
	return (object) [
		'name'          => $args->slug,
		'slug'          => $args->slug,
		'version'       => $info['version'],
		'author'        => 'StockLand',
		'homepage'      => 'https://github.com/' . STLU_REPO,
		'download_link' => stlu_package( $info ),
		'requires_php'  => '8.1',
		'sections'      => [ 'description' => 'منتشرشده از کامیت ' . esc_html( substr( (string) $manifest['sha'], 0, 7 ) ) ],
	];
}, 10, 3 );

// به‌روزرسانی خودکار، اگر روشن باشد
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	if ( '1' !== get_option( STLU_OPT_AUTO ) || empty( $item->slug ) ) {
		return $update;
	}
	$manifest = stlu_manifest();
	return ( $manifest && isset( $manifest['plugins'][ $item->slug ] ) ) ? true : $update;
}, 10, 2 );

/*
 * ─── بعد از آپدیتِ افزونه‌های ما: پاک کردنِ کلِ حافظه‌ی موقت فقط وقتی کد عوض شده ───
 * پیش‌تر هر نسخه کلِ حافظه‌ی موقتِ صفحه‌ها را پاک می‌کرد (و المنتور هم با هر آپدیتِ هر افزونه‌ای، جدا یک بارِ دیگر).
 * برنامه‌ی محتوا تقریباً هر روز یک نسخه می‌دهد که فقط مقاله دارد؛ پس سایت هر روز «سرد» می‌شد و هر صفحه را اولین
 * بازدیدکننده ۲٫۵ تا ۴٫۵ ثانیه از نو می‌ساخت (`/perf`، ۵ آبان ۱۴۰۵). حالا اثرِ انگشتِ فایل‌های هر افزونه پیش و پس از
 * نصب سنجیده می‌شود، بی فایل‌های محتوا (`STLU_CONTENT_ONLY`) و بی سطرهای نسخه در فایلِ اصلی؛ اگر یکی بود، فقط محتوا
 * عوض شده و کلِ حافظه دست نمی‌خورد — خودِ `stland-home` مقاله‌ی تغییرکرده، وبلاگ و صفحه‌ی اصلی را پاک می‌کند.
 * ⛔ هر فایلِ تازه‌ای که روی ظاهرِ صفحه اثر دارد نباید به `STLU_CONTENT_ONLY` برود. شک = پاک کردنِ کامل.
 * پاک کردنِ فایل‌های CSSِ المنتور ربطی به افزونه‌های ما ندارد و برای آپدیتِ فقط-افزونه‌های-ما برداشته می‌شود.
 */
const STLU_CONTENT_ONLY = [ 'content/', 'assets/posts/', 'includes/posts.php' ];

/** اثرِ انگشتِ کد و ظاهرِ یک افزونه (بی محتوای مقاله‌ها و بی سطرِ نسخه) */
function stlu_fingerprint( string $file ): string {
	$dir = WP_PLUGIN_DIR . '/' . dirname( $file );
	if ( ! is_dir( $dir ) ) {
		return '';
	}
	$files = [];
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		$rel = str_replace( '\\', '/', substr( $f->getPathname(), strlen( $dir ) + 1 ) );
		foreach ( STLU_CONTENT_ONLY as $skip ) {
			if ( str_starts_with( $rel, $skip ) ) {
				continue 2;
			}
		}
		$files[ $rel ] = $f->getPathname();
	}
	ksort( $files );
	$h = hash_init( 'md5' );
	foreach ( $files as $rel => $path ) {
		$body = (string) file_get_contents( $path );
		if ( basename( $file ) === $rel ) {
			$body = (string) preg_replace( '/^.*(?:\* Version:|_VER\b).*$/m', '', $body );
		}
		hash_update( $h, $rel . "\0" . $body . "\0" );
	}
	return hash_final( $h );
}

/** افزونه‌های ما در این آپدیت */
function stlu_ours_in( array $extra ): array {
	$ours = array_map( 'stlu_file', array_keys( ( stlu_manifest() ?? [ 'plugins' => [] ] )['plugins'] ) );
	$up   = array_merge( (array) ( $extra['plugins'] ?? [] ), isset( $extra['plugin'] ) ? [ $extra['plugin'] ] : [] );
	return array_values( array_intersect( $up, $ours ) );
}

add_filter( 'upgrader_pre_install', static function ( $response, $extra ) {
	$file = (string) ( $extra['plugin'] ?? '' );
	if ( '' !== $file && ! isset( $GLOBALS['stlu_fp_before'][ $file ] ) ) {
		$GLOBALS['stlu_fp_before'][ $file ] = stlu_fingerprint( $file );
	}
	return $response;
}, 10, 2 );

/** کدِ یکی از این افزونه‌ها عوض شده؟ (اثرِ انگشتِ پیش از نصب نبود = بله) */
function stlu_code_changed( array $files ): bool {
	foreach ( $files as $file ) {
		$before = (string) ( $GLOBALS['stlu_fp_before'][ $file ] ?? '' );
		if ( '' === $before || stlu_fingerprint( $file ) !== $before ) {
			return true;
		}
	}
	return false;
}

add_action( 'upgrader_process_complete', function ( $upgrader, array $extra ): void {
	if ( ( $extra['type'] ?? '' ) !== 'plugin' ) {
		return;
	}
	$ours = stlu_ours_in( $extra );
	if ( ! $ours ) {
		return;
	}
	$all = array_merge( (array) ( $extra['plugins'] ?? [] ), isset( $extra['plugin'] ) ? [ $extra['plugin'] ] : [] );
	if ( count( array_unique( $all ) ) === count( $ours ) && class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		$cb = [ \Elementor\Plugin::$instance->files_manager, 'on_upgrader_process_complete' ];
		$p  = has_action( 'upgrader_process_complete', $cb );
		if ( false !== $p ) {
			remove_action( 'upgrader_process_complete', $cb, $p );
		}
	}
	if ( stlu_code_changed( $ours ) ) {
		stlu_purge_caches();
	}
}, 1, 2 );

function stlu_purge_caches(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	wp_cache_flush();
	do_action( 'litespeed_purge_all' );
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
	}
}

/*
 * ─── به‌روزرسانیِ خودکارِ واقعی: هر ۱۵ دقیقه ───
 * صاحب فروشگاه (مهر ۱۴۰۵): «به‌روزرسانیِ خودکار روشن است ولی هر چه بمانم آپدیت نمی‌کند.»
 * به‌روزرسانیِ خودکارِ خودِ وردپرس فقط با رویدادِ `wp_version_check` اجرا می‌شود — هر ۱۲
 * ساعت — و آن هم به جوابِ api.wordpress.org بسته است. این‌جا با کلیدِ همان صفحه، هر ۱۵
 * دقیقه manifest تازه خوانده و هر افزونه‌ی ما که نسخه‌ی تازه دارد، در پس‌زمینه (cron) نصب
 * می‌شود. در cron وردپرس افزونه را پیش از نصب غیرفعال نمی‌کند، پس فعال می‌ماند.
 * نتیجه‌ی آخرین اجرا در گزینه‌ی `stlu_auto_last` (در `/perf` و همین صفحه دیده می‌شود).
 */
add_filter( 'cron_schedules', static function ( array $s ): array {
	$s['stlu_15min'] = [ 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'هر ۱۵ دقیقه (استوک لند)' ];
	return $s;
} );

add_action( 'init', static function (): void {
	$on = '1' === get_option( STLU_OPT_AUTO );
	$at = wp_next_scheduled( 'stlu_auto_run' );
	if ( $on && ! $at ) {
		wp_schedule_event( time() + 60, 'stlu_15min', 'stlu_auto_run' );
	} elseif ( ! $on && $at ) {
		wp_clear_scheduled_hook( 'stlu_auto_run' );
	}
} );

/** افزونه‌های ما که نسخه‌ی تازه‌تر در گیت‌هاب دارند: [file => [installed, new]] */
function stlu_outdated( array $manifest ): array {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$installed = get_plugins();
	$out       = [];
	foreach ( $manifest['plugins'] as $slug => $info ) {
		$file = stlu_file( (string) $slug );
		if ( isset( $installed[ $file ] ) && version_compare( (string) $info['version'], (string) $installed[ $file ]['Version'], '>' ) ) {
			$out[ $file ] = [ (string) $installed[ $file ]['Version'], (string) $info['version'] ];
		}
	}
	return $out;
}

add_action( 'stlu_auto_run', static function (): void {
	if ( '1' !== get_option( STLU_OPT_AUTO ) || get_transient( 'stlu_auto_lock' ) ) {
		return;
	}
	$manifest = stlu_manifest( true );
	$log      = [ 'at' => gmdate( 'c' ), 'result' => [] ];
	$todo     = $manifest ? stlu_outdated( $manifest ) : [];
	if ( ! $manifest ) {
		$log['result'][] = 'manifest: no answer from GitHub';
	}
	if ( $todo ) {
		set_transient( 'stlu_auto_lock', 1, 10 * MINUTE_IN_SECONDS );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		wp_clean_plugins_cache( false );
		// خودِ به‌روزرسان آخر — تا اگر نسخه‌ی تازه‌اش ایرادی داشت، بقیه نصب شده باشند
		uksort( $todo, static fn( $a, $b ) => ( str_starts_with( $a, 'stland-updater/' ) <=> str_starts_with( $b, 'stland-updater/' ) ) );
		foreach ( $todo as $file => [ $from, $to ] ) {
			$was = is_plugin_active( $file );
			$up  = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
			$res = $up->upgrade( $file );
			$ok  = true === $res || ( is_array( $res ) && ! is_wp_error( $res ) );
			$log['result'][] = $file . ' ' . $from . ' → ' . $to . ': ' . ( $ok ? 'ok' : ( is_wp_error( $res ) ? $res->get_error_message() : wp_strip_all_tags( implode( ' ', (array) $up->skin->get_upgrade_messages() ) ) ) );
			// وردپرس وقتی کار از راهِ «کرون» شناخته نشود (مثلاً کرونِ واقعیِ هاست) افزونه را پیش از نصب غیرفعال می‌کند و
			// فعال کردنِ دوباره را به مرورگر می‌سپارد؛ اینجا مرورگری نیست. ۱۱ آبان ۱۴۰۵ «stland-home» ۱.۷۸.۸ همین‌طور خاموش ماند.
			if ( $was && ! is_plugin_active( $file ) ) {
				$re              = activate_plugin( $file, '', false, true );
				$log['result'][] = $file . ' reactivate: ' . ( is_wp_error( $re ) ? $re->get_error_message() : 'ok' );
			}
		}
		wp_clean_plugins_cache( false );
		if ( stlu_code_changed( array_keys( $todo ) ) ) {
			stlu_purge_caches();
		} else {
			$log['result'][] = 'content only: no full purge';
		}
		delete_transient( 'stlu_auto_lock' );
		update_option( 'stlu_auto_last', $log, false );
	} else {
		$prev = (array) get_option( 'stlu_auto_last', [] );
		update_option( 'stlu_auto_last', [ 'checked' => gmdate( 'c' ) ] + array_diff_key( $prev, [ 'checked' => 1 ] ), false );
	}
} );

// ─── صفحه‌ی تنظیمات ───
// صاحب فروشگاه (۱۱ آبان ۱۴۰۵): «با گیت هر دقیقه کار نداریم؛ پنهان باشد و با یک پیوند باز شود». صفحه ثبت می‌ماند ولی از
// منوی «تنظیمات» برداشته می‌شود؛ راهِ رسیدن: پیوندِ «به‌روزرسانی» زیرِ همین افزونه در صفحه‌ی «افزونه‌ها».
add_action( 'admin_menu', function (): void {
	add_options_page( 'به‌روزرسانی استوک لند', 'به‌روزرسانی استوک لند', 'update_plugins', 'stland-updater', 'stlu_page' );
} );
add_action( 'admin_menu', static function (): void {
	remove_submenu_page( 'options-general.php', 'stland-updater' );
}, 999 );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=stland-updater' ) ) . '">به‌روزرسانی</a>' );
	return $links;
} );

function stlu_action_url( string $do, array $extra = [] ): string {
	return wp_nonce_url( add_query_arg( [ 'action' => 'stlu', 'do' => $do ] + $extra, admin_url( 'admin-post.php' ) ), 'stlu_' . $do );
}

function stlu_back( string $msg, bool $ok = true ): never {
	set_transient( 'stlu_notice_' . get_current_user_id(), [ $msg, $ok ], 60 );
	wp_safe_redirect( admin_url( 'options-general.php?page=stland-updater' ) );
	exit;
}

/** آزمایش اتصال: هم manifest و هم دانلود zip (که به سرور دیگری ریدایرکت می‌شود) */
function stlu_probe(): array {
	$out = [];
	if ( '' !== stlu_token() ) {
		$start = microtime( true );
		$res   = wp_remote_get( 'https://api.github.com/repos/' . STLU_REPO . '/releases/tags/latest', stlu_api_args() );
		$out[] = [ 'API (مخزنِ خصوصی، با کلید)', is_wp_error( $res ) ? $res->get_error_message() : (string) wp_remote_retrieve_response_code( $res ), round( microtime( true ) - $start, 1 ) ];
		$start = microtime( true );
		$m     = stlu_manifest_private();
		$out[] = [ 'manifest.json', $m ? '200' : 'خوانده نشد', round( microtime( true ) - $start, 1 ) ];
		return $out;
	}
	$start = microtime( true );
	$res   = wp_remote_get( STLU_RELEASE . 'manifest.json', [ 'timeout' => 15 ] );
	$out[] = [ 'manifest.json', is_wp_error( $res ) ? $res->get_error_message() : (string) wp_remote_retrieve_response_code( $res ), round( microtime( true ) - $start, 1 ) ];
	$data  = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$zip   = is_array( $data ) && ! empty( $data['plugins'] ) ? (string) reset( $data['plugins'] )['zip'] : '';
	if ( '' !== $zip ) {
		$start = microtime( true );
		$res   = wp_remote_get( STLU_RELEASE . rawurlencode( $zip ), [ 'timeout' => 30, 'limit_response_size' => 1024 * 1024 ] );
		$out[] = [ $zip, is_wp_error( $res ) ? $res->get_error_message() : (string) wp_remote_retrieve_response_code( $res ), round( microtime( true ) - $start, 1 ) ];
	}
	return $out;
}

// ذخیره یا پاک کردنِ کلیدِ گیت‌هاب (فرمِ همین صفحه، POST)
add_action( 'admin_post_stlu_token', static function (): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه ندارید.' );
	}
	check_admin_referer( 'stlu_token' );
	$t = trim( (string) wp_unslash( $_POST['stlu_token'] ?? '' ) );
	if ( isset( $_POST['stlu_token_clear'] ) ) {
		delete_option( 'stlu_token' );
		delete_site_transient( STLU_CACHE );
		stlu_back( 'کلید پاک شد.' );
	}
	if ( ! preg_match( '/^[A-Za-z0-9_]{20,255}$/', $t ) ) {
		stlu_back( 'کلید درست نیست — همان متنی را که گیت‌هاب داد کامل بچسبانید (با github_pat_ شروع می‌شود).', false );
	}
	update_option( 'stlu_token', $t, false );
	delete_site_transient( STLU_CACHE );
	stlu_back( stlu_manifest( true ) ? 'کلید ذخیره شد و فهرست از مخزنِ خصوصی خوانده شد.' : 'کلید ذخیره شد ولی گیت‌هاب جواب نداد — «آزمایش اتصال» را بزنید.', (bool) stlu_manifest() );
} );

add_action( 'admin_post_stlu', function (): void {
	$do = sanitize_key( (string) ( $_GET['do'] ?? '' ) );
	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( 'اجازه ندارید.' );
	}
	check_admin_referer( 'stlu_' . $do );

	switch ( $do ) {
		case 'check':
			stlu_manifest( true );
			stlu_back( stlu_manifest() ? 'فهرست از گیت‌هاب خوانده شد.' : 'گیت‌هاب جواب نداد — «آزمایش اتصال» را بزنید.', (bool) stlu_manifest() );

		case 'test':
			set_transient( 'stlu_probe_' . get_current_user_id(), stlu_probe(), 300 );
			stlu_back( 'آزمایش انجام شد — نتیجه پایین صفحه است.' );

		case 'auto':
			update_option( STLU_OPT_AUTO, '1' === get_option( STLU_OPT_AUTO ) ? '' : '1' );
			stlu_back( '1' === get_option( STLU_OPT_AUTO ) ? 'به‌روزرسانی خودکار روشن شد.' : 'به‌روزرسانی خودکار خاموش شد.' );

		case 'install':
			$slug     = sanitize_key( (string) ( $_GET['slug'] ?? '' ) );
			$manifest = stlu_manifest( true );
			if ( ! $manifest || ! isset( $manifest['plugins'][ $slug ] ) ) {
				stlu_back( 'این افزونه در فهرست گیت‌هاب نیست.', false );
			}
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
			$result   = $upgrader->install( stlu_item( $slug, $manifest['plugins'][ $slug ] )->package );
			if ( true !== $result ) {
				$msg = is_wp_error( $result ) ? $result->get_error_message() : implode( ' ', (array) $upgrader->skin->get_upgrade_messages() );
				stlu_back( 'نصب نشد: ' . $msg, false );
			}
			stlu_back( 'نصب شد. از صفحه‌ی افزونه‌ها فعالش کنید.' );
	}
	stlu_back( 'کار ناشناخته.', false );
} );

function stlu_fa( string $s ): string {
	return strtr( $s, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

function stlu_css(): string {
	return '.stlu{max-width:720px;margin:24px auto 40px;padding:0 12px;font-size:14px;color:#1f2430}
.stlu *{box-sizing:border-box}
.stlu-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.stlu-head h1{margin:0;padding:0;font-size:22px;font-weight:800}
.stlu-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:700}
.stlu-pill::before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor}
.stlu-ok{background:#e8f6ee;color:#137a3f}.stlu-warn{background:#fff4e0;color:#a35b00}.stlu-bad{background:#fde8ec;color:#b4123a}
.stlu-card{background:#fff;border:1px solid #e6e9ef;border-radius:20px;box-shadow:0 1px 2px rgba(15,23,42,.05),0 6px 18px -10px rgba(15,23,42,.12);padding:6px 18px;margin-bottom:14px}
.stlu-row{display:flex;align-items:center;gap:12px;padding:14px 0;border-top:1px solid #f0f2f5;flex-wrap:wrap}
.stlu-row:first-child{border-top:0}
.stlu-name{flex:1 1 180px;font-weight:700;font-size:15px}
.stlu-name small{display:block;font-weight:500;color:#6b7280;font-size:12.5px;margin-top:2px}
.stlu-tag{font-size:12.5px;font-weight:700;padding:4px 10px;border-radius:999px}
.stlu-btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 18px;border-radius:12px;border:1px solid #d6dbe4;background:#fff;color:#1f2430;font-weight:700;font-size:14px;text-decoration:none;cursor:pointer;font-family:inherit}
.stlu-btn:hover{border-color:#0071e3;color:#0071e3}
.stlu-btn--pri{background:#0071e3;border-color:#0071e3;color:#fff}.stlu-btn--pri:hover{background:#005bbf;color:#fff}
.stlu-meta{color:#5b616e;font-size:13px;line-height:2;padding:12px 0}
.stlu-actions{display:flex;gap:8px;flex-wrap:wrap;margin:4px 0 18px}
.stlu-msg{padding:12px 16px;border-radius:14px;margin-bottom:14px;font-weight:600}
.stlu details{background:#f7f8fa;border:1px solid #e6e9ef;border-radius:16px;padding:0 16px}
.stlu summary{cursor:pointer;padding:14px 0;font-weight:700;color:#5b616e;list-style:none}
.stlu summary::-webkit-details-marker{display:none}
.stlu summary::after{content:"＋";float:left;color:#9aa1ad}.stlu details[open] summary::after{content:"－"}
.stlu-sec{padding:14px 0;border-top:1px solid #e6e9ef}
.stlu-sec h3{margin:0 0 6px;font-size:14px}
.stlu-sec p{margin:0 0 10px;color:#5b616e;font-size:13px}
.stlu-form{display:flex;gap:8px;flex-wrap:wrap}
.stlu-form input{flex:1 1 240px;min-height:40px;border:1px solid #d6dbe4;border-radius:12px;padding:0 12px;direction:ltr}
.stlu-probe{width:100%;border-collapse:collapse;font-size:13px;margin-top:6px}
.stlu-probe td{padding:8px 4px;border-top:1px solid #e6e9ef}
@media (max-width:600px){.stlu{margin-top:12px}.stlu-btn{flex:1 1 auto}}';
}

function stlu_page(): void {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$uid      = get_current_user_id();
	$notice   = get_transient( 'stlu_notice_' . $uid );
	$probe    = get_transient( 'stlu_probe_' . $uid );
	$manifest = stlu_manifest();
	$plugins  = get_plugins();
	$auto     = '1' === get_option( STLU_OPT_AUTO );
	$token    = defined( 'STLU_TOKEN' ) || '' !== stlu_token();
	$last     = (array) get_option( 'stlu_auto_last', [] );
	$next     = $auto ? wp_next_scheduled( 'stlu_auto_run' ) : false;
	delete_transient( 'stlu_notice_' . $uid );

	$rows = [];
	$pending = 0;
	$off     = 0;
	foreach ( (array) ( $manifest['plugins'] ?? [] ) as $slug => $info ) {
		$file      = stlu_file( (string) $slug );
		$installed = $plugins[ $file ]['Version'] ?? null;
		$newer     = null !== $installed && version_compare( (string) $info['version'], $installed, '>' );
		$active    = null !== $installed && is_plugin_active( $file );
		$pending  += $newer ? 1 : 0;
		$off      += ( null !== $installed && ! $active ) ? 1 : 0;
		$rows[]    = compact( 'slug', 'info', 'file', 'installed', 'newer', 'active' );
	}
	if ( ! $manifest ) {
		$pill = [ 'stlu-bad', 'گیت‌هاب جواب نداد' ];
	} elseif ( $off ) {
		$pill = [ 'stlu-bad', stlu_fa( (string) $off ) . ' افزونه خاموش است' ];
	} elseif ( $pending ) {
		$pill = [ 'stlu-warn', stlu_fa( (string) $pending ) . ' نسخه‌ی تازه' ];
	} else {
		$pill = [ 'stlu-ok', 'همه به‌روزند' ];
	}
	$open = $probe || ! $token || ! $manifest;
	?>
	<style><?php echo stlu_css(); // phpcs:ignore WordPress.Security.EscapeOutput -- CSSِ ثابت ?></style>
	<div class="wrap stlu" dir="rtl">
		<div class="stlu-head">
			<h1>به‌روزرسانی استوک لند</h1>
			<span class="stlu-pill <?php echo esc_attr( $pill[0] ); ?>"><?php echo esc_html( $pill[1] ); ?></span>
		</div>
		<?php if ( $notice ) : ?>
			<div class="stlu-msg <?php echo $notice[1] ? 'stlu-ok' : 'stlu-bad'; ?>"><?php echo esc_html( $notice[0] ); ?></div>
		<?php endif; ?>

		<?php if ( $rows ) : ?>
			<div class="stlu-card">
				<?php foreach ( $rows as $r ) : ?>
					<div class="stlu-row">
						<div class="stlu-name">
							<?php echo esc_html( $plugins[ $r['file'] ]['Name'] ?? $r['slug'] ); ?>
							<small>
								<?php if ( null === $r['installed'] ) : ?>
									نصب نشده — نسخه‌ی موجود <?php echo esc_html( stlu_fa( (string) $r['info']['version'] ) ); ?>
								<?php elseif ( $r['newer'] ) : ?>
									نسخه‌ی <?php echo esc_html( stlu_fa( $r['installed'] ) ); ?> ← <?php echo esc_html( stlu_fa( (string) $r['info']['version'] ) ); ?>
								<?php else : ?>
									نسخه‌ی <?php echo esc_html( stlu_fa( $r['installed'] ) ); ?>
								<?php endif; ?>
							</small>
						</div>
						<?php if ( null === $r['installed'] ) : ?>
							<a class="stlu-btn" href="<?php echo esc_url( stlu_action_url( 'install', [ 'slug' => $r['slug'] ] ) ); ?>">نصب</a>
						<?php elseif ( ! $r['active'] ) : ?>
							<span class="stlu-tag stlu-bad">خاموش</span>
							<a class="stlu-btn stlu-btn--pri" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $r['file'] ) ), 'activate-plugin_' . $r['file'] ) ); ?>">روشن کن</a>
						<?php elseif ( $r['newer'] ) : ?>
							<a class="stlu-btn stlu-btn--pri" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $r['file'] ) ), 'upgrade-plugin_' . $r['file'] ) ); ?>">به‌روزرسانی</a>
						<?php else : ?>
							<span class="stlu-tag stlu-ok">به‌روز</span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				<div class="stlu-meta">
					<?php if ( $auto ) : ?>
						هر ۱۵ دقیقه خودش بررسی و نصب می‌کند<?php echo $next ? ' — بررسیِ بعدی ' . esc_html( stlu_fa( human_time_diff( time(), (int) $next ) ) ) . ' دیگر' : ''; ?>.
					<?php else : ?>
						نصبِ خودکار خاموش است؛ نسخه‌ی تازه فقط با دکمه نصب می‌شود.
					<?php endif; ?>
					<?php if ( ! empty( $last['at'] ) ) : ?>
						<br>آخرین نصبِ خودکار: <?php echo esc_html( stlu_fa( human_time_diff( (int) strtotime( (string) $last['at'] ) ) ) ); ?> پیش
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="stlu-msg stlu-bad">فهرستِ افزونه‌ها از گیت‌هاب خوانده نشد. پایینِ صفحه «تنظیماتِ اتصال» را باز کنید و «آزمایشِ اتصال» را بزنید.</div>
		<?php endif; ?>

		<div class="stlu-actions">
			<a class="stlu-btn stlu-btn--pri" href="<?php echo esc_url( stlu_action_url( 'check' ) ); ?>">بررسی همین حالا</a>
		</div>

		<details<?php echo $open ? ' open' : ''; ?>>
			<summary>تنظیماتِ اتصال به گیت‌هاب — معمولاً لازم نیست</summary>

			<div class="stlu-sec">
				<h3>کلیدِ گیت‌هاب</h3>
				<?php if ( defined( 'STLU_TOKEN' ) ) : ?>
					<p>کلید در پرونده‌ی تنظیماتِ وردپرس تعریف شده است.</p>
				<?php else : ?>
					<p><?php echo '' !== stlu_token() ? '✓ کلید ذخیره شده است (نمایش داده نمی‌شود). فقط وقتی کلید منقضی شد، کلیدِ تازه را این‌جا بچسبانید.' : 'کلیدی ذخیره نشده — بی کلید هیچ نسخه‌ای از مخزنِ خصوصی نمی‌رسد.'; ?></p>
					<form class="stlu-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="stlu_token">
						<?php wp_nonce_field( 'stlu_token' ); ?>
						<input type="password" name="stlu_token" autocomplete="off" placeholder="github_pat_…">
						<button class="stlu-btn stlu-btn--pri" type="submit">ذخیره‌ی کلید</button>
						<?php if ( '' !== stlu_token() ) : ?><button class="stlu-btn" type="submit" name="stlu_token_clear" value="1">پاک کردن</button><?php endif; ?>
					</form>
				<?php endif; ?>
			</div>

			<div class="stlu-sec">
				<h3>آزمایش و نصبِ خودکار</h3>
				<p>
					<?php if ( $manifest ) : ?>آخرین انتشار: <code dir="ltr"><?php echo esc_html( substr( (string) $manifest['sha'], 0, 7 ) ); ?></code> — <?php endif; ?>
					<a href="<?php echo esc_url( 'https://github.com/' . STLU_REPO ); ?>" target="_blank" rel="noopener">مخزن در گیت‌هاب</a>
				</p>
				<div class="stlu-form">
					<a class="stlu-btn" href="<?php echo esc_url( stlu_action_url( 'test' ) ); ?>">آزمایشِ اتصال</a>
					<a class="stlu-btn" href="<?php echo esc_url( stlu_action_url( 'auto' ) ); ?>"><?php echo $auto ? 'خاموش کردنِ نصبِ خودکار' : 'روشن کردنِ نصبِ خودکار'; ?></a>
				</div>
				<?php if ( $probe ) : ?>
					<table class="stlu-probe">
						<?php foreach ( $probe as [ $name, $status, $secs ] ) : ?>
							<tr>
								<td><code dir="ltr"><?php echo esc_html( $name ); ?></code></td>
								<td><?php echo '200' === $status ? '✅ درست' : '❌ ' . esc_html( $status ); ?></td>
								<td><?php echo esc_html( stlu_fa( (string) $secs ) ); ?> ثانیه</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<p style="margin-top:8px">هر دو ردیف باید ✅ باشند. اگر ❌ است، هاست اجازه‌ی اتصال به گیت‌هاب را نمی‌دهد یا کلید منقضی شده.</p>
				<?php endif; ?>
				<?php if ( ! empty( $last['result'] ) ) : ?>
					<p dir="ltr" style="text-align:left;font-size:12px;margin-top:8px"><?php echo esc_html( implode( ' | ', (array) $last['result'] ) ); ?></p>
				<?php endif; ?>
			</div>
		</details>
	</div>
	<?php
}
