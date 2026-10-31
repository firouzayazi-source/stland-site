<?php
/**
 * صفحه‌ی ورودِ وردپرس (`wp-login.php`) — به سبکِ خودِ استوک لند.
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «این عکسِ ورود را به لوگوی خودم تغییر بده.» لوگو داخلِ خودِ افزونه است
 * (`assets/img/login-logo.png`، ۲۵۶×۲۵۶) تا به کتابخانه‌ی رسانه و تنظیمِ دیگری بند نباشد.
 *
 * صاحب فروشگاه (۹ آبان ۱۴۰۵، با عکسِ صفحه‌ی ورودِ سایتِ دیگرش): «همین کیفیت برای استوک لند، رنگ مرتبط با لوگوی خودمان،
 * جذاب و شکیل». پس: زمینه‌ی تیره‌ی هم‌رنگِ لوگو و سربرگ (`#262626`) با درخششِ آبیِ رنگِ اصلیِ سایت (`#0071e3`)، لوگو و
 * نامِ فروشگاه بالا، کارتِ سفیدِ گرد، دکمه‌ی آبیِ قرصی، و پیوندهای زیرِ کارت. قالبِ «شمیم»ِ افزونه‌ی فارسی‌سازِ فروشگاه
 * فقط در همین صفحه کنار می‌رود (تنظیمش دست نمی‌خورد) تا دو طرح روی هم نیفتند؛ فونتِ همان افزونه (یکان‌بخ با اعدادِ فارسی) می‌ماند.
 * فقط CSS؛ فرم، نشانی‌ها و رفتارِ خودِ وردپرس دست نمی‌خورد.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'login_enqueue_scripts', static function (): void {
	wp_dequeue_style( 'pw-admin-login-template-shamim' );
}, 999 );

function stlh_login_css(): string {
	$logo  = esc_url( STLH_URL . 'assets/img/login-logo.png?v=' . STLH_VER );
	$title = addcslashes( wp_strip_all_tags( stlh_label( 'login_title' ) ), '"\\' );
	return <<<CSS
:root{--sl-bg:#262626;--sl-bg2:#141416;--sl-blue:#0071e3;--sl-blue2:#3d8bff;--sl-ink:#23254e;--sl-line:#e3e6ec}
html{background:var(--sl-bg2)!important}
body.login{min-height:100dvh!important;display:flex!important;align-items:center;justify-content:center;padding:28px 16px!important;box-sizing:border-box;
 background:radial-gradient(120% 60% at 50% 0%,rgba(0,113,227,.28),transparent 60%),radial-gradient(90% 50% at 50% 100%,rgba(61,139,255,.12),transparent 70%),linear-gradient(180deg,var(--sl-bg) 0%,var(--sl-bg2) 100%)!important;
 font-family:"YekanBakh","Yekan Bakh",Tahoma,sans-serif!important;color:#fff}
body.login *{font-family:inherit}
body.login #login{width:100%!important;max-width:400px!important;padding:0!important;margin:0 auto!important}
body.login #login h1{margin:0 0 26px!important;text-align:center}
body.login #login h1 a,body.login h1 a{background:url({$logo}) center/contain no-repeat!important;width:104px!important;height:104px!important;max-width:none!important;padding:0!important;margin:0 auto!important;
 border-radius:26px!important;box-shadow:0 0 0 1px rgba(255,255,255,.08),0 18px 44px -10px rgba(0,113,227,.55)!important;text-indent:-9999px!important;overflow:hidden!important;display:block!important}
body.login #login h1 a img,body.login h1 a img{display:none!important}
body.login #login h1::after{content:"{$title}";display:block;margin-top:16px;font-size:26px;font-weight:900;color:#fff;letter-spacing:0;line-height:1.4}
body.login form{margin:0!important;padding:30px 26px 26px!important;border:0!important;border-radius:28px!important;background:#fff!important;box-shadow:0 30px 60px -20px rgba(0,0,0,.55)!important;overflow:visible!important}
body.login form p{margin:0 0 4px}
body.login label{font-size:14.5px!important;font-weight:600;color:var(--sl-ink)!important;line-height:2}
body.login form .input,body.login input[type=text],body.login input[type=password],body.login input[type=email]{width:100%!important;height:54px!important;margin:6px 0 18px!important;padding:0 16px!important;font-size:16px!important;
 border:1.5px solid var(--sl-line)!important;border-radius:16px!important;background:#f7f8fa!important;color:#1f2430!important;box-shadow:none!important;transition:border-color .15s,box-shadow .15s,background .15s}
body.login form .input:focus,body.login input[type=text]:focus,body.login input[type=password]:focus{border-color:var(--sl-blue)!important;background:#fff!important;box-shadow:0 0 0 4px rgba(0,113,227,.14)!important;outline:0!important}
body.login .user-pass-wrap{position:relative}
body.login .wp-pwd{position:relative}
body.login .wp-pwd .button.wp-hide-pw{top:6px!important;height:54px!important;width:48px!important;color:#7d8597!important;background:transparent!important;border:0!important;box-shadow:none!important;left:4px!important;right:auto!important}
body.login .wp-pwd .button.wp-hide-pw:focus{box-shadow:none!important;color:var(--sl-blue)!important}
body.login .forgetmenot{display:flex;align-items:center;gap:8px;float:right!important;margin:14px 0 0!important}
body.login .forgetmenot .wp-tooltip__toggle{padding:0!important;border:0!important;background:none!important;box-shadow:none!important;color:#9aa1ad!important;cursor:pointer}
body.login .forgetmenot label{font-weight:500!important;color:#5b5f6b!important;font-size:14px!important}
body.login input[type=checkbox]{width:22px!important;height:22px!important;margin:0!important;border:1.5px solid #c9ced8!important;border-radius:7px!important;box-shadow:none!important}
body.login input[type=checkbox]:checked{background:var(--sl-blue)!important;border-color:var(--sl-blue)!important}
body.login input[type=checkbox]:checked::before{content:""!important;width:6px!important;height:11px!important;margin:2px 7px 0 0!important;border:solid #fff;border-width:0 2.5px 2.5px 0;transform:rotate(45deg);display:block}
body.login .submit{float:left!important;margin:0!important;padding:0!important}
body.login #wp-submit,body.login .button-primary{height:52px!important;min-width:128px;padding:0 32px!important;border:0!important;border-radius:999px!important;font-size:16px!important;font-weight:800!important;line-height:52px!important;
 color:#fff!important;text-shadow:none!important;background:linear-gradient(135deg,var(--sl-blue2),var(--sl-blue))!important;box-shadow:0 14px 28px -10px rgba(0,113,227,.75)!important;transition:transform .15s,box-shadow .15s}
body.login #wp-submit:hover,body.login .button-primary:hover{transform:translateY(-1px);box-shadow:0 18px 32px -10px rgba(0,113,227,.85)!important}
body.login #wp-submit:focus,body.login .button-primary:focus{outline:0!important;box-shadow:0 0 0 4px rgba(0,113,227,.25),0 14px 28px -10px rgba(0,113,227,.75)!important}
body.login form::after{content:"";display:table;clear:both}
body.login #nav,body.login #backtoblog{margin:22px 0 0!important;padding:0!important;text-align:center!important;font-size:15px!important}
body.login #backtoblog{margin-top:14px!important}
body.login #nav a,body.login #backtoblog a{color:rgba(255,255,255,.82)!important;text-decoration:none!important;border-bottom:1px solid rgba(255,255,255,.35);padding-bottom:4px;transition:color .15s,border-color .15s}
body.login #nav a:hover,body.login #backtoblog a:hover{color:#fff!important;border-color:var(--sl-blue2)}
body.login .message,body.login .notice,body.login #login_error{margin:0 0 18px!important;padding:12px 16px!important;border:0!important;border-radius:16px!important;font-size:14px;line-height:1.8;box-shadow:none!important}
body.login .message,body.login .notice-info,body.login .success{background:rgba(0,113,227,.14)!important;color:#dbe9ff!important;border-right:4px solid var(--sl-blue)!important}
body.login #login_error,body.login .notice-error{background:rgba(224,36,77,.16)!important;color:#ffd9e1!important;border-right:4px solid #e0244d!important}
body.login .message a,body.login #login_error a{color:#fff!important}
body.login .language-switcher,body.login .privacy-policy-page-link{display:none!important}
@media (max-width:420px){body.login form{padding:26px 20px 22px!important;border-radius:24px!important}body.login #login h1 a{width:92px!important;height:92px!important}body.login #login h1::after{font-size:23px}}
CSS;
}

add_action( 'login_enqueue_scripts', static function (): void {
	echo '<style id="stland-login">' . stlh_login_css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSSِ ثابتِ خودمان، عنوان escape شده
}, 99 );

add_filter( 'login_headerurl', static fn() => home_url( '/' ), 99 );
add_filter( 'login_headertext', static fn() => stlh_label( 'login_title' ), 99 );
