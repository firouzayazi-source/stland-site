<?php
/**
 * خواندنِ فایل‌های قالب، فقط خواندن، فقط برای مدیرِ کل.
 *
 * هاست SSH ندارد و FTP از خارج رد می‌شود (۵۳۱)؛ پس هر بار که لازم بود بدانیم
 * قالب (باکالا) یک بخش را از کجا می‌خواند، صاحب فروشگاه باید zip یا اسکرین‌شات
 * می‌فرستاد. صاحب فروشگاه (مهر ۱۴۰۵): «دسترسیِ کامل به قالب و تغییرات داشته
 * باشی». این دو مسیر همان را با رمزِ برنامه‌ی وردپرس می‌دهند:
 *
 *   GET /stland/v1/theme-files                     فهرستِ فایل‌های قالبِ فعال (و مادرش)
 *   GET /stland/v1/theme-files?theme=x&path=a.php  متنِ یک فایل
 *   …&offset=N                                      تکه‌ی بعدیِ فایلِ بزرگ‌تر از ۱ مگ
 *   GET /stland/v1/site-options                     تنظیماتِ قالب (Redux)، رمزها پوشیده
 *
 *   GET /stland/v1/theme-files?theme=plugin:<slug>[&path=…]  همان برای یک افزونه
 *     (افزونه‌ی جایگاه، مهر ۱۴۰۵: بی کدش نمی‌شد فهمید چرا روشِ ارسال نمی‌دهد)
 *
 * ⛔ هیچ نوشتنی نیست. تغییر از افزونه‌ی ما یا تنظیماتِ قالب می‌آید، نه ویرایشِ
 *    فایلِ باکالا (به‌روزرسانیِ قالب پاکش می‌کند).
 * ⛔ فقط زیرِ پوشه‌ی قالب‌ها (realpath)، نه wp-config یا uploads؛ فقط
 *    `manage_options`، نه مدیرِ فروشگاه.
 */
defined( 'ABSPATH' ) || exit;

const STLH_THEME_FILE_MAX = 1048576;

add_action( 'rest_api_init', function (): void {
	register_rest_route( 'stland/v1', '/theme-files', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		'callback'            => 'stlh_theme_files',
		'args'                => [
			'theme' => [ 'type' => 'string', 'required' => false ],
			'path'   => [ 'type' => 'string', 'required' => false ],
			'offset' => [ 'type' => 'integer', 'required' => false, 'minimum' => 0 ],
		],
	] );
	register_rest_route( 'stland/v1', '/site-options', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		'callback'            => 'stlh_site_options',
	] );
} );

/**
 * تنظیماتِ قالب (`bakala_options`، همان Redux) — تا بدونِ اسکرین‌شات معلوم
 * باشد متنِ باکس‌ها، استایلِ ویژگی‌ها و… الان چیست. فایلِ تعریفِ این
 * تنظیمات (`inc/setting.php`) بیش از ۱ مگ است و بخشی از قالب رمزگذاری شده.
 *
 * ⛔ فقط خواندن. و هر کلیدی که بوی رمز می‌دهد (توکن، کلید، رمز، API، درگاه،
 *    پیامک…) با «***» پوشیده می‌شود — این پاسخ از پلِ حسابداری می‌گذرد و
 *    رمزِ درگاه یا پیامک نباید از سایت بیرون برود.
 */
function stlh_site_options( ?WP_REST_Request $req = null ): array {
	/*
	 * `?prefix=mnsjay_` — گزینه‌های یک افزونه (جایگاه، مهر ۱۴۰۵: کدش ionCube است و
	 * تنظیماتش تنها راهِ فهمیدنِ «هیچ روش حمل و نقلی» بود). همان پوشاندنِ رمزها.
	 */
	$prefix = $req ? sanitize_key( (string) $req->get_param( 'prefix' ) ) : '';
	if ( strlen( $prefix ) >= 4 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 300", $wpdb->esc_like( $prefix ) . '%' ), ARRAY_A );
		$out  = [];
		foreach ( $rows as $r ) {
			$v = maybe_unserialize( $r['option_value'] );
			if ( is_string( $v ) && strlen( $v ) > 4000 ) {
				$v = '(' . strlen( $v ) . ' bytes)';
			}
			$out[ $r['option_name'] ] = is_array( $v ) ? $v : ( is_object( $v ) ? (array) $v : $v );
		}
		return [ 'prefix' => $prefix, 'options' => stlh_redact( $out ) ];
	}
	$opts = get_option( 'bakala_options', [] );
	return [
		'name'    => 'bakala_options',
		'options' => stlh_redact( is_array( $opts ) ? $opts : [] ),
	];
}

function stlh_redact( array $data ): array {
	$out = [];
	foreach ( $data as $key => $value ) {
		if ( is_string( $key ) && preg_match( '/pass|secret|token|api|key|merchant|terminal|sms|username|user_name|license|purchase_code|webhook/i', $key ) ) {
			$out[ $key ] = ( '' === $value || null === $value || [] === $value ) ? $value : '***';
			continue;
		}
		$out[ $key ] = is_array( $value ) ? stlh_redact( $value ) : $value;
	}
	return $out;
}

/** قالبِ فعال و مادرش: [نامک => پوشه] */
function stlh_theme_dirs(): array {
	$dirs = [ get_stylesheet() => get_stylesheet_directory() ];
	if ( get_template() !== get_stylesheet() ) {
		$dirs[ get_template() ] = get_template_directory();
	}
	return $dirs;
}

/** مسیرِ نسبی → مسیرِ واقعی، فقط اگر داخلِ همان پوشه‌ی قالب باشد */
function stlh_theme_path( string $base, string $rel ): ?string {
	$root = realpath( $base );
	$full = realpath( $base . '/' . ltrim( $rel, '/' ) );
	if ( ! $root || ! $full || ! is_file( $full ) ) {
		return null;
	}
	return str_starts_with( $full, $root . DIRECTORY_SEPARATOR ) ? $full : null;
}

function stlh_theme_files( WP_REST_Request $req ): array|WP_Error {
	$dirs  = stlh_theme_dirs();
	$theme = (string) $req->get_param( 'theme' );
	if ( str_starts_with( $theme, 'plugin:' ) ) {
		$slug = sanitize_key( substr( $theme, 7 ) );
		$dir  = WP_PLUGIN_DIR . '/' . $slug;
		if ( '' === $slug || ! is_dir( $dir ) ) {
			return new WP_Error( 'stlh_not_found', 'افزونه نیست.', [ 'status' => 404 ] );
		}
		$dirs = [ $theme => $dir ];
	}
	$path  = (string) $req->get_param( 'path' );

	if ( '' !== $path ) {
		$base = $dirs[ $theme ] ?? null;
		$full = $base ? stlh_theme_path( $base, $path ) : null;
		if ( ! $full ) {
			return new WP_Error( 'stlh_not_found', 'فایل در قالبِ فعال نیست.', [ 'status' => 404 ] );
		}
		$size   = (int) filesize( $full );
		$offset = max( 0, (int) $req->get_param( 'offset' ) );
		/*
		 * فایلِ بزرگ‌تر از سقف تکه‌تکه: `next` جای تکه‌ی بعدی است، `null` یعنی تمام.
		 * پیش از این `content` فقط `null` بود و `inc/setting.php` (۱.۲ مگ) خواندنی نبود.
		 */
		$chunk = (string) file_get_contents( $full, false, null, min( $offset, $size ), STLH_THEME_FILE_MAX );
		$end   = min( $offset, $size ) + strlen( $chunk );
		return [
			'theme'   => $theme,
			'path'    => $path,
			'size'    => $size,
			'offset'  => min( $offset, $size ),
			'next'    => $end < $size ? $end : null,
			'content' => $chunk,
		];
	}

	$out = [];
	foreach ( $dirs as $slug => $dir ) {
		$root  = realpath( $dir );
		$files = [];
		if ( $root ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				if ( count( $files ) >= 8000 ) {
					break;
				}
				if ( $f->isFile() ) {
					$files[] = [ substr( $f->getPathname(), strlen( $root ) + 1 ), $f->getSize() ];
				}
			}
			sort( $files );
		}
		$t     = str_starts_with( $slug, 'plugin:' ) ? null : wp_get_theme( $slug );
		$out[] = [ 'slug' => $slug, 'name' => $t ? $t->get( 'Name' ) : $slug, 'version' => $t ? $t->get( 'Version' ) : '', 'files' => $files ];
	}
	return [ 'active' => get_stylesheet(), 'themes' => $out ];
}
