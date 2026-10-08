<?php
/**
 * STLH-PAGE-CACHE — کشِ صفحه‌ی stland-home (این فایل را افزونه‌ی stland-home در
 * wp-content/advanced-cache.php می‌گذارد و برمی‌دارد؛ دستی ویرایش نکنید).
 *
 * سرورِ این سایت Apache است و کشِ صفحه‌ی LiteSpeed روی خودِ سرور کار نمی‌کند؛ تنها کش
 * لبه‌ی QUIC.cloud در خارج بود و هر صفحه‌ای که در آن لبه نبود، برای مشتری از صفر
 * ساخته می‌شد (۲ تا ۴ ثانیه روی سرور). این فایل پیش از بار شدنِ وردپرس، صفحه‌ی
 * ذخیره‌شده را برای بازدیدکننده‌ی ناشناس می‌فرستد (چند میلی‌ثانیه).
 *
 * فقط: GET/HEAD، بی پارامتر (جز utm/fbclid/gclid)، بی کوکیِ ورود/سبد/جلسه. ذخیره را خودِ
 * افزونه تصمیم می‌گیرد (سبد، پرداخت، حساب، جستجو، ۴۰۴ هرگز). گوشی و دسکتاپ جدا.
 */

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$dir = WP_CONTENT_DIR . '/cache/stlh-page/';
	if ( ! is_file( $dir . '.on' ) || 'cli' === PHP_SAPI ) {
		return;
	}
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return;
	}
	$uri   = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
	$parts = explode( '?', $uri, 2 );
	$path  = $parts[0];
	if ( isset( $parts[1] ) && '' !== $parts[1] ) {
		parse_str( $parts[1], $q );
		foreach ( array_keys( $q ) as $k ) {
			if ( ! preg_match( '/^(utm_[a-z_]+|fbclid|gclid|_ga)$/', (string) $k ) ) {
				return;
			}
		}
	}
	if ( preg_match( '#^/(wp-admin|wp-login|wp-json|wp-cron|xmlrpc|wc-api|feed)|\.(xml|txt|php)$#i', $path ) ) {
		return;
	}
	foreach ( array_keys( $_COOKIE ) as $c ) {
		if ( preg_match( '/^(wordpress_logged_in_|wordpress_sec_|wp-postpass_|comment_author_|woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_|location$|phone$|opt_code$|email$|response$)/', (string) $c ) ) {
			return;
		}
	}
	$ua     = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	$mobile = (bool) preg_match( '#Mobile|Android|Silk/|Kindle|BlackBerry|Opera Mini|Opera Mobi#', $ua );
	$https  = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) || 'https' === ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' );
	$key    = md5( ( $https ? 'https' : 'http' ) . '://' . strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) . rawurldecode( $path ) ) . ( $mobile ? '-m' : '-d' );
	$file   = $dir . $key . '.html';

	$GLOBALS['stlh_pc'] = [ 'file' => $file, 'dir' => $dir, 'store' => null ];

	if ( is_file( $file ) && ( time() - (int) filemtime( $file ) ) < 10 * 3600 ) {
		$fh   = fopen( $file, 'rb' );
		$meta = $fh ? json_decode( (string) fgets( $fh ), true ) : null;
		if ( $fh && is_array( $meta ) ) {
			foreach ( (array) ( $meta['h'] ?? [] ) as $h ) {
				header( $h, false );
			}
			header( 'X-STLH-Cache: hit; age=' . ( time() - (int) filemtime( $file ) ) );
			if ( 'HEAD' !== $method ) {
				fpassthru( $fh );
			}
			fclose( $fh );
			exit;
		}
		if ( $fh ) {
			fclose( $fh );
		}
	}

	// نبود: صفحه ساخته می‌شود؛ بیرونی‌ترین بافر خروجیِ نهایی (بعد از بهینه‌سازیِ LiteSpeed) را می‌گیرد
	ob_start( static function ( string $html, int $phase ): string {
		if ( ! ( $phase & PHP_OUTPUT_HANDLER_FINAL ) ) {
			// کسی وسطِ کار بافر را خالی کرد: این تکه کلِ صفحه نیست، ذخیره نمی‌شود
			$GLOBALS['stlh_pc']['partial'] = true;
			return $html;
		}
		if ( ! empty( $GLOBALS['stlh_pc']['partial'] ) ) {
			return $html;
		}
		$pc = $GLOBALS['stlh_pc'] ?? null;
		if ( ! $pc || true !== $pc['store'] || ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) || 200 !== http_response_code() ) {
			return $html;
		}
		if ( strlen( $html ) < 5000 || false === stripos( $html, '</html>' ) ) {
			return $html;
		}
		$keep = [];
		foreach ( headers_list() as $h ) {
			if ( preg_match( '/^set-cookie\s*:/i', $h ) ) {
				return $html; // صفحه‌ای که کوکی می‌گذارد، شخصی است
			}
			if ( ! preg_match( '/^(date|expires|x-powered-by|content-length|x-stlh-cache)\s*:/i', $h ) ) {
				$keep[] = $h;
			}
		}
		if ( ! is_dir( $pc['dir'] ) ) {
			@mkdir( $pc['dir'], 0755, true );
		}
		$tmp = $pc['file'] . '.' . getmypid() . '.tmp';
		if ( false !== @file_put_contents( $tmp, json_encode( [ 'h' => $keep, 't' => time() ] ) . "\n" . $html . "\n<!-- stlh page cache " . gmdate( 'c' ) . ' -->' ) ) {
			@rename( $tmp, $pc['file'] );
		}
		return $html;
	} );
} )();
