<?php
/**
 * انبارِ پشتیبانِ حسابداری، بیرون از سرورِ حسابداری.
 *
 * صاحب فروشگاه (مهر ۱۴۰۵) پیشنهادِ «پشتیبانِ بیرون از سرور» را پذیرفت: پشتیبانِ
 * شبانه تا امروز فقط روی همان VPS بود و اگر خودِ سرور از دست می‌رفت، پشتیبان هم
 * با آن می‌رفت. این هاست جداست (ایران، نه آلمان) و حسابداری از قبل با رمزِ
 * برنامه‌ی وردپرس به آن وصل است.
 *
 *   POST /stland/v1/backup/chunk   ?name=…&offset=N   بدنه = تکه‌ی خام (رمزگذاری‌شده)
 *   POST /stland/v1/backup/finish  ?name=…&size=…&sha256=…&keep=7
 *   GET  /stland/v1/backup                              فهرست
 *   GET  /stland/v1/backup/file    ?name=…&offset=N     تکه‌ای از فایل (base64)، برای بازیابی
 *
 * ⛔ فایل‌ها **رمزگذاری‌شده** می‌رسند (AES-256-GCM در حسابداری) و کلیدش هرگز اینجا
 *    نمی‌آید؛ این‌جا فقط نگه‌داری است. با این حال پوشه بیرون از ریشه‌ی وب است اگر
 *    هاست بگذارد، وگرنه زیرِ uploads با نامِ تصادفی و `.htaccess` بسته.
 * ⛔ فقط `manage_options` (مدیرِ کل) و فقط نامِ ساده — هیچ مسیری از بیرون ساخته نمی‌شود.
 */
defined( 'ABSPATH' ) || exit;

const STLH_BACKUP_CHUNK_MAX = 8388608; // ۸ مگ؛ حسابداری ۲ مگ می‌فرستد
const STLH_BACKUP_READ      = 4194304;

add_action( 'rest_api_init', function (): void {
	$admin = static fn() => current_user_can( 'manage_options' );
	register_rest_route( 'stland/v1', '/backup', [
		'methods'             => 'GET',
		'permission_callback' => $admin,
		'callback'            => 'stlh_backup_list',
	] );
	register_rest_route( 'stland/v1', '/backup/chunk', [
		'methods'             => 'POST',
		'permission_callback' => $admin,
		'callback'            => 'stlh_backup_chunk',
	] );
	register_rest_route( 'stland/v1', '/backup/finish', [
		'methods'             => 'POST',
		'permission_callback' => $admin,
		'callback'            => 'stlh_backup_finish',
	] );
	register_rest_route( 'stland/v1', '/backup/file', [
		'methods'             => 'GET',
		'permission_callback' => $admin,
		'callback'            => 'stlh_backup_read',
	] );
} );

/** پوشه‌ی نگه‌داری: بیرونِ ریشه‌ی وب اگر بشود، وگرنه uploads با نامِ تصادفی و بسته */
function stlh_backup_dir(): string {
	$outside = dirname( untrailingslashit( ABSPATH ) ) . '/stland-backups';
	if ( is_dir( $outside ) || ( is_writable( dirname( $outside ) ) && @mkdir( $outside, 0700 ) ) ) {
		return $outside;
	}
	$suffix = get_option( 'stlh_backup_suffix' );
	if ( ! $suffix ) {
		$suffix = wp_generate_password( 24, false );
		update_option( 'stlh_backup_suffix', $suffix, false );
	}
	$dir = wp_upload_dir()['basedir'] . '/stland-backups-' . $suffix;
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( "$dir/.htaccess" ) ) {
		file_put_contents( "$dir/.htaccess", "Require all denied\nDeny from all\n" );
		file_put_contents( "$dir/index.html", '' );
	}
	return $dir;
}

/** فقط نامِ ساده‌ی فایلِ پشتیبانِ رمزگذاری‌شده */
function stlh_backup_name( $raw ): ?string {
	$name = is_string( $raw ) ? $raw : '';
	return preg_match( '/^sthesabdari-[0-9]{8}-[0-9]{4,6}(-d)?\.db\.enc$/', $name ) ? $name : null;
}

function stlh_backup_error( string $message, int $status = 400 ): WP_Error {
	return new WP_Error( 'stlh_backup', $message, [ 'status' => $status ] );
}

function stlh_backup_list(): array {
	$dir   = stlh_backup_dir();
	$files = [];
	foreach ( glob( "$dir/*.enc" ) ?: [] as $path ) {
		$files[] = [ 'name' => basename( $path ), 'size' => filesize( $path ), 'mtime' => filemtime( $path ) ];
	}
	usort( $files, static fn( $a, $b ) => $b['mtime'] <=> $a['mtime'] );
	$free = @disk_free_space( $dir );
	return [ 'files' => $files, 'free' => false === $free ? null : (int) $free ];
}

/**
 * یک تکه، به ترتیب. `offset` باید همان اندازه‌ی فعلیِ فایلِ نیمه‌کاره باشد؛ تکه‌ای که
 * دوباره رسیده (تلاشِ مجددِ حسابداری بعد از قطعی) بی‌صدا پذیرفته می‌شود.
 */
function stlh_backup_chunk( WP_REST_Request $req ) {
	$name = stlh_backup_name( $req->get_param( 'name' ) );
	if ( ! $name ) {
		return stlh_backup_error( 'نامِ فایل معتبر نیست.' );
	}
	$offset = (int) $req->get_param( 'offset' );
	$body   = $req->get_body();
	$len    = strlen( $body );
	if ( $len === 0 || $len > STLH_BACKUP_CHUNK_MAX ) {
		return stlh_backup_error( 'تکه خالی یا بزرگ‌تر از سقف است.' );
	}
	$part = stlh_backup_dir() . "/$name.part";
	if ( 0 === $offset && file_exists( $part ) ) {
		unlink( $part ); // شروعِ دوباره
	}
	clearstatcache( true, $part );
	$have = file_exists( $part ) ? filesize( $part ) : 0;
	if ( $offset + $len === $have ) {
		return [ 'ok' => true, 'size' => $have, 'repeat' => true ];
	}
	if ( $offset !== $have ) {
		return stlh_backup_error( "تکه جابه‌جا رسید (انتظار $have، آمد $offset).", 409 );
	}
	if ( false === file_put_contents( $part, $body, FILE_APPEND | LOCK_EX ) ) {
		return stlh_backup_error( 'هاست نگذاشت بنویسم (جا یا دسترسی).', 507 );
	}
	return [ 'ok' => true, 'size' => $have + $len ];
}

/** پایان: اندازه و هش سنجیده می‌شود، فایل سر جایش می‌نشیند و فقط `keep` نسخه‌ی آخر می‌ماند */
function stlh_backup_finish( WP_REST_Request $req ) {
	$name = stlh_backup_name( $req->get_param( 'name' ) );
	if ( ! $name ) {
		return stlh_backup_error( 'نامِ فایل معتبر نیست.' );
	}
	$dir  = stlh_backup_dir();
	$part = "$dir/$name.part";
	if ( ! file_exists( $part ) ) {
		return stlh_backup_error( 'فایلِ نیمه‌کاره پیدا نشد.', 404 );
	}
	clearstatcache( true, $part );
	$size = (int) $req->get_param( 'size' );
	if ( filesize( $part ) !== $size ) {
		unlink( $part );
		return stlh_backup_error( 'اندازه نخواند؛ دوباره بفرستید.', 409 );
	}
	$want = strtolower( (string) $req->get_param( 'sha256' ) );
	if ( ! hash_equals( hash_file( 'sha256', $part ), $want ) ) {
		unlink( $part );
		return stlh_backup_error( 'هش نخواند؛ دوباره بفرستید.', 409 );
	}
	rename( $part, "$dir/$name" );

	$keep  = max( 2, min( 30, (int) ( $req->get_param( 'keep' ) ?: 7 ) ) );
	$files = glob( "$dir/*.enc" ) ?: [];
	usort( $files, static fn( $a, $b ) => filemtime( $b ) <=> filemtime( $a ) );
	$removed = 0;
	foreach ( array_slice( $files, $keep ) as $old ) {
		if ( @unlink( $old ) ) {
			$removed++;
		}
	}
	// نیمه‌کاره‌های کهنه (ارسالی که وسطش قطع شد و دیگر نیامد)
	foreach ( glob( "$dir/*.part" ) ?: [] as $stale ) {
		if ( filemtime( $stale ) < time() - DAY_IN_SECONDS ) {
			@unlink( $stale );
		}
	}
	return [ 'ok' => true, 'name' => $name, 'removed' => $removed ];
}

function stlh_backup_read( WP_REST_Request $req ) {
	$name = stlh_backup_name( $req->get_param( 'name' ) );
	if ( ! $name ) {
		return stlh_backup_error( 'نامِ فایل معتبر نیست.' );
	}
	$path = stlh_backup_dir() . "/$name";
	if ( ! file_exists( $path ) ) {
		return stlh_backup_error( 'چنین پشتیبانی نیست.', 404 );
	}
	$offset = max( 0, (int) $req->get_param( 'offset' ) );
	$size   = filesize( $path );
	$data   = (string) file_get_contents( $path, false, null, $offset, STLH_BACKUP_READ );
	return [
		'size'   => $size,
		'offset' => $offset,
		'next'   => $offset + strlen( $data ) < $size ? $offset + strlen( $data ) : null,
		'data'   => base64_encode( $data ),
	];
}
