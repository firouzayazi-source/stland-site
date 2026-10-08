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
			// سرآیندها base64 ذخیره می‌شوند: یک بایتِ نا-UTF-8 در یکی‌شان json را خراب می‌کرد و هیچ صفحه‌ای از کش نمی‌آمد
			foreach ( (array) ( $meta['h'] ?? [] ) as $h ) {
				$h = base64_decode( (string) $h, true );
				if ( is_string( $h ) && '' !== $h && ! preg_match( '/[\r\n]/', $h ) ) {
					header( $h, false );
				}
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
			@file_put_contents( $GLOBALS['stlh_pc']['dir'] . '.why', gmdate( 'H:i:s' ) . ' partial ' . substr( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 0, 70 ) . "\n", FILE_APPEND );
			return $html;
		}
		$pc  = $GLOBALS['stlh_pc'] ?? null;
		$why = '';
		$keep = [];
		if ( ! $pc ) {
			return $html;
		}
		if ( true !== $pc['store'] ) {
			$why = null === $pc['store'] ? 'no-decision' : 'not-cacheable';
		} elseif ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			$why = 'DONOTCACHEPAGE';
		} elseif ( 200 !== http_response_code() ) {
			$why = 'status-' . http_response_code();
		} elseif ( strlen( $html ) < 5000 || false === stripos( $html, '</html>' ) ) {
			$why = 'short-' . strlen( $html );
		} else {
			foreach ( headers_list() as $h ) {
				if ( preg_match( '/^set-cookie\s*:\s*([^=;]+)/i', $h, $m ) ) {
					// «اخیراً دیده‌شده»ی ووکامرس شخصی نیست و صفحه به آن وابسته نیست: فقط در نسخه‌ی ذخیره‌شده گذاشته نمی‌شود
					if ( 'woocommerce_recently_viewed' === trim( $m[1] ) ) {
						continue;
					}
					$why = 'cookie-' . $m[1];
					break;
				}
				if ( ! preg_match( '/^(date|expires|x-powered-by|content-length|x-stlh-cache)\s*:/i', $h ) ) {
					$keep[] = $h;
				}
			}
		}
		if ( '' !== $why ) {
			// چرا ذخیره نشد — ۴۰ خطِ آخر، برای /perf
			$log = $pc['dir'] . '.why';
			$old = is_file( $log ) ? array_slice( (array) file( $log, FILE_IGNORE_NEW_LINES ), -39 ) : [];
			$old[] = gmdate( 'H:i:s' ) . ' ' . $why . ' ' . substr( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 0, 70 );
			@file_put_contents( $log, implode( "\n", $old ) . "\n" );
			return $html;
		}
		if ( ! is_dir( $pc['dir'] ) ) {
			@mkdir( $pc['dir'], 0755, true );
		}
		$tmp = $pc['file'] . '.' . getmypid() . '.tmp';
		if ( false !== @file_put_contents( $tmp, json_encode( [ 'h' => array_map( 'base64_encode', $keep ), 't' => time() ] ) . "\n" . $html . "\n<!-- stlh page cache " . gmdate( 'c' ) . ' -->' ) ) {
			@rename( $tmp, $pc['file'] );
		}
		return $html;
	} );
} )();
