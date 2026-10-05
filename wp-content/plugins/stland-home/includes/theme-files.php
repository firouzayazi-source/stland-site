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
			'path'  => [ 'type' => 'string', 'required' => false ],
		],
	] );
} );

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
	$path  = (string) $req->get_param( 'path' );

	if ( '' !== $path ) {
		$base = $dirs[ $theme ] ?? null;
		$full = $base ? stlh_theme_path( $base, $path ) : null;
		if ( ! $full ) {
			return new WP_Error( 'stlh_not_found', 'فایل در قالبِ فعال نیست.', [ 'status' => 404 ] );
		}
		$size = (int) filesize( $full );
		return [
			'theme'   => $theme,
			'path'    => $path,
			'size'    => $size,
			'content' => $size > STLH_THEME_FILE_MAX ? null : (string) file_get_contents( $full ),
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
		$t     = wp_get_theme( $slug );
		$out[] = [ 'slug' => $slug, 'name' => $t->get( 'Name' ), 'version' => $t->get( 'Version' ), 'files' => $files ];
	}
	return [ 'active' => get_stylesheet(), 'themes' => $out ];
}
