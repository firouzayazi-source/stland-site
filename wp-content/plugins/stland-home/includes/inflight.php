<?php
/**
 * درخواست‌های در حالِ اجرا و پردازه‌های PHPِ همین کاربر — عیب‌یابیِ «CPU و رمِ ۱۰۰٪».
 *
 * روزِ جابه‌جایی به پارس‌پک (مهر ۱۴۰۵) Resource Usage نشان داد ۷۳ پردازه و رم و CPU پر، در
 * حالی که Entry Processes فقط ۱ بود؛ پنلِ هاست نمی‌گوید کدام پردازه چه می‌کند. این‌جا:
 *
 *  ۱. هر درخواست (بعد از بارِ همین افزونه) یک فایلِ کوچک در `wp-content/cache/stlh-inflight/`
 *     می‌گذارد — pid، نشانی، روش، UA، زمانِ شروع — و در `shutdown` پاکش می‌کند. فایلی که
 *     مانده یعنی درخواستی که هنوز تمام نشده (یا کشته شده). ⇒ `/perf` → `inflight`.
 *  ۲. `/perf?procs=1`: پردازه‌های همین کاربر از `/proc` (CloudLinux فقط پردازه‌های خودِ
 *     کاربر را نشان می‌دهد) با رم، زمانِ CPU و عمر.
 *
 * فقط نوشتن/پاک کردنِ یک فایلِ چندصدبایتی در هر درخواست؛ هیچ کوئری‌ای.
 */

defined( 'ABSPATH' ) || exit;

function stlh_inflight_dir(): string {
	return WP_CONTENT_DIR . '/cache/stlh-inflight';
}

( static function (): void {
	if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
		return;
	}
	$dir = stlh_inflight_dir();
	if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0755, true ) ) {
		return;
	}
	$file = $dir . '/' . getmypid() . '-' . substr( md5( uniqid( '', true ) ), 0, 6 ) . '.json';
	$data = [
		'pid'    => getmypid(),
		'start'  => (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) ),
		'method' => (string) ( $_SERVER['REQUEST_METHOD'] ?? ( PHP_SAPI === 'cli' ? 'CLI' : '' ) ),
		'uri'    => substr( (string) ( $_SERVER['REQUEST_URI'] ?? ( $_SERVER['argv'][0] ?? '' ) ), 0, 300 ),
		'ua'     => substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 120 ),
		'ip'     => (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ),
		'cron'   => defined( 'DOING_CRON' ) && DOING_CRON,
		'ajax'   => (string) ( $_REQUEST['action'] ?? '' ),
	];
	if ( false === @file_put_contents( $file, (string) wp_json_encode( $data ) ) ) {
		return;
	}
	register_shutdown_function( static function () use ( $file ): void {
		@unlink( $file );
	} );
} )();

/** درخواست‌های تمام‌نشده، قدیمی‌ترین اول؛ فایل‌های بیش از یک روز پاک می‌شوند */
function stlh_inflight_report(): array {
	$now  = microtime( true );
	$rows = [];
	foreach ( (array) glob( stlh_inflight_dir() . '/*.json' ) as $f ) {
		$d = json_decode( (string) @file_get_contents( $f ), true );
		if ( ! is_array( $d ) ) {
			continue;
		}
		$age   = $now - (float) ( $d['start'] ?? $now );
		$alive = is_dir( '/proc/' . (int) $d['pid'] );
		// پردازه‌ی کشته‌شده shutdown را اجرا نمی‌کند؛ بعد از ۱۰ دقیقه فقط شلوغی است
		if ( $age > DAY_IN_SECONDS || ( ! $alive && $age > 600 ) ) {
			@unlink( $f );
			continue;
		}
		$d['age_s'] = round( $age, 1 );
		$d['alive'] = $alive;
		unset( $d['start'] );
		$rows[] = $d;
	}
	usort( $rows, static fn( $a, $b ) => $b['age_s'] <=> $a['age_s'] );
	return [ 'n' => count( $rows ), 'self_pid' => getmypid(), 'rows' => array_slice( $rows, 0, 60 ) ];
}

/** پردازه‌های همین کاربر از `/proc` */
function stlh_inflight_procs(): array {
	$uid   = function_exists( 'posix_getuid' ) ? posix_getuid() : getmyuid();
	$hz    = 100;
	$boot  = null;
	$up    = @file_get_contents( '/proc/uptime' );
	$uptime = $up ? (float) explode( ' ', $up )[0] : null;
	$rows  = [];
	$total = 0;
	foreach ( (array) glob( '/proc/[0-9]*' ) as $p ) {
		$status = @file_get_contents( $p . '/status' );
		if ( ! $status || ! preg_match( '/^Uid:\s+(\d+)/m', $status, $m ) || (int) $m[1] !== (int) $uid ) {
			continue;
		}
		preg_match( '/^VmRSS:\s+(\d+)/m', $status, $rss );
		preg_match( '/^State:\s+(\S+)/m', $status, $st );
		$cmd  = trim( str_replace( "\0", ' ', (string) @file_get_contents( $p . '/cmdline' ) ) );
		$stat = (string) @file_get_contents( $p . '/stat' );
		$cpu  = null;
		$age  = null;
		if ( $stat && preg_match( '/\)\s+(.*)$/', $stat, $sm ) ) {
			$f   = explode( ' ', $sm[1] );
			$cpu = round( ( (int) ( $f[11] ?? 0 ) + (int) ( $f[12] ?? 0 ) ) / $hz, 1 );
			if ( null !== $uptime ) {
				$age = round( $uptime - ( (int) ( $f[19] ?? 0 ) ) / $hz );
			}
		}
		$mb     = isset( $rss[1] ) ? round( (int) $rss[1] / 1024, 1 ) : null;
		$total += (float) $mb;
		$rows[] = [
			'pid'   => (int) basename( $p ),
			'state' => $st[1] ?? '',
			'mb'    => $mb,
			'cpu_s' => $cpu,
			'age_s' => $age,
			'cmd'   => substr( $cmd, 0, 160 ),
		];
	}
	usort( $rows, static fn( $a, $b ) => ( $b['cpu_s'] ?? 0 ) <=> ( $a['cpu_s'] ?? 0 ) );
	return [ 'uid' => $uid, 'n' => count( $rows ), 'rss_mb_total' => round( $total ), 'rows' => array_slice( $rows, 0, 80 ) ];
}
