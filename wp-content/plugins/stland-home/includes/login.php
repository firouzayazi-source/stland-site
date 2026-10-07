<?php
/**
 * صفحه‌ی ورودِ وردپرس با لوگوی خودِ استوک لند.
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «این عکسِ ورود را به لوگوی خودم تغییر بده.» آنچه آنجا
 * بود تکه‌ای بریده و بزرگ‌شده از لوگو بود (اندازه‌ی پس‌زمینه‌ی نادرست در استایلِ
 * فعلی). لوگو داخلِ خودِ افزونه است (`assets/img/login-logo.png`، ۲۵۶×۲۵۶) تا به
 * کتابخانه‌ی رسانه و تنظیمِ دیگری بند نباشد. اولویتِ ۹۹ و `!important` چون استایلِ
 * فعلیِ صفحه‌ی ورود از جای دیگری می‌آید و باید رویش بنشیند.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'login_enqueue_scripts', static function (): void {
	$logo = esc_url( STLH_URL . 'assets/img/login-logo.png?v=' . STLH_VER );
	echo '<style id="stland-login-logo">'
		. 'body.login #login h1 a,body.login h1 a{background-image:url(' . $logo . ') !important;'
		. 'background-size:contain !important;background-position:center !important;background-repeat:no-repeat !important;'
		. 'width:112px !important;height:112px !important;max-width:none !important;padding:0 !important;margin:0 auto 24px !important;'
		. 'border-radius:26px !important;box-shadow:0 10px 28px rgba(0,0,0,.18) !important;text-indent:-9999px !important;overflow:hidden !important;display:block !important}'
		. 'body.login #login h1 a img,body.login h1 a img{display:none !important}'
		. '</style>';
}, 99 );

add_filter( 'login_headerurl', static fn() => home_url( '/' ), 99 );
add_filter( 'login_headertext', static fn() => 'استوک لند', 99 );
