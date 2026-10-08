<?php
/**
 * انبارِ پشتیبان (`includes/offsite-backup.php`): مهمان راه ندارد، تکه‌ها به ترتیب و
 * تکرارپذیر می‌نشینند، هشِ غلط رد می‌شود، فقط `keep` نسخه می‌ماند، و نامِ بد (مسیر) رد می‌شود.
 */
$_SERVER['HTTP_HOST'] ??= 'localhost';
require getenv( 'WP_DIR' ) . '/wp-load.php';

$fail = [];
$call = static function ( string $method, string $route, array $q, string $body = '' ) {
	$r = new WP_REST_Request( $method, "/stland/v1/$route" );
	$r->set_query_params( $q );
	if ( '' !== $body ) {
		$r->set_body( $body );
	}
	return rest_do_request( $r );
};

wp_set_current_user( 0 );
if ( 401 !== $call( 'GET', 'backup', [] )->get_status() ) {
	$fail[] = 'guest must get 401';
}

wp_set_current_user( 1 );
$send = static function ( string $name, string $data ) use ( $call ) {
	$half = intdiv( strlen( $data ), 2 );
	$call( 'POST', 'backup/chunk', [ 'name' => $name, 'offset' => 0 ], substr( $data, 0, $half ) );
	// همان تکه دوباره (تلاشِ مجدد) نباید چیزی را خراب کند
	$call( 'POST', 'backup/chunk', [ 'name' => $name, 'offset' => 0 ], substr( $data, 0, $half ) );
	$call( 'POST', 'backup/chunk', [ 'name' => $name, 'offset' => $half ], substr( $data, $half ) );
	return $call( 'POST', 'backup/finish', [ 'name' => $name, 'size' => strlen( $data ), 'sha256' => hash( 'sha256', $data ), 'keep' => 2 ] );
};

$names = [];
for ( $i = 1; $i <= 3; $i++ ) {
	$name    = sprintf( 'sthesabdari-2026100%d-030000.db.enc', $i );
	$names[] = $name;
	$res     = $send( $name, str_repeat( "x$i", 5000 ) );
	if ( 200 !== $res->get_status() ) {
		$fail[] = "finish $i: " . wp_json_encode( $res->get_data() );
	}
	touch( stlh_backup_dir() . "/$name", time() - ( 10 - $i ) );
}
// یک بارِ دیگر تا پاک‌سازی با زمان‌های جاافتاده اجرا شود
$send( 'sthesabdari-20261004-030000.db.enc', 'last' );
$list = array_column( $call( 'GET', 'backup', [] )->get_data()['files'] ?? [], 'name' );
if ( count( $list ) !== 2 || ! in_array( 'sthesabdari-20261004-030000.db.enc', $list, true ) ) {
	$fail[] = 'keep=2 must leave the newest two: ' . implode( ',', $list );
}

$read = $call( 'GET', 'backup/file', [ 'name' => 'sthesabdari-20261004-030000.db.enc' ] )->get_data();
if ( 'last' !== base64_decode( $read['data'] ?? '' ) ) {
	$fail[] = 'read back must return the same bytes';
}

$call( 'POST', 'backup/chunk', [ 'name' => 'sthesabdari-20261005-030000.db.enc', 'offset' => 0 ], 'abc' );
if ( 409 !== $call( 'POST', 'backup/finish', [ 'name' => 'sthesabdari-20261005-030000.db.enc', 'size' => 3, 'sha256' => str_repeat( '0', 64 ) ] )->get_status() ) {
	$fail[] = 'wrong hash must be rejected';
}

foreach ( [ '../wp-config.php', 'x.php', 'sthesabdari-20261001-030000.db.enc/../../a' ] as $bad ) {
	if ( 400 !== $call( 'POST', 'backup/chunk', [ 'name' => $bad, 'offset' => 0 ], 'x' )->get_status() ) {
		$fail[] = "bad name not blocked: $bad";
	}
}
echo $fail ? "offsite-backup FAIL:\n  " . implode( "\n  ", $fail ) . "\n" : "offsite-backup ok\n";
exit( $fail ? 1 : 0 );
