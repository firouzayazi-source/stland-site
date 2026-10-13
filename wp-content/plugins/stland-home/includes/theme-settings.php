<?php
/**
 * تنظیماتِ قالب (باکالا) از مخزن، نه از پیشخوان.
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «تغییراتی که می‌خواهیم خودت لحاظ کن، من دستکاری نکنم.» پلِ حسابداری
 * عمداً فقط‌خواندنی است (رمزِ برنامه‌ای که لو برود نباید بتواند سایت را عوض کند)، پس نوشتن از بیرون
 * نیست: مقدارِ دلخواه همین‌جا در `STLH_BAKALA_SET` می‌آید، با نسخه‌ی افزونه منتشر می‌شود، و خودِ
 * افزونه یک بار در `bakala_options` می‌نویسد — پیشخوانِ باکالا هم همان را نشان می‌دهد.
 *
 *  • فقط وقتی فهرست عوض شده می‌نویسد (`stlh_bakala_set_hash`)؛ اگر کسی بعداً از پیشخوان عوضش کرد،
 *    تا تغییرِ بعدیِ فهرست دوباره زور نمی‌شود.
 *  • مقدارِ پیشین هر کلید پیش از اولین نوشتن در `stlh_bakala_backup` می‌ماند (برگشت = همان مقدار
 *    را این‌جا بگذارید). هر دو از `/wp-json/stland/v1/site-options?prefix=stlh` خواندنی‌اند.
 *  • کلیدی که در تنظیماتِ قالب نیست نوشته نمی‌شود (غلطِ تایپی گزینه‌ی تازه نمی‌سازد) و در
 *    `stlh_bakala_applied` با `missing` گزارش می‌شود.
 */
defined( 'ABSPATH' ) || exit;

/** کلیدِ `bakala_options` => مقدار. هر خط: چرا، و درخواستِ چه کسی. */
const STLH_BAKALA_SET = [
	// پاپ‌آپِ «اولین خرید» (صاحب فروشگاه، ۲۱ مهر ۱۴۰۵): متنِ قبلی «تا سقفِ ۱ میلیون» با کدِ ۵۰ هزاری نمی‌خواند
	'bk_popup_coupon_desc'      => '۵۰ هزار تومان تخفیف برای اولین خرید، برای سبد بالای ۱ میلیون تومان',
	// شمارشِ معکوس تا ۲۶ مهر بود و بعدش تاریخِ گذشته نشان می‌داد؛ کد تاریخِ انقضا ندارد
	'bk_popup_countdown_enable' => '0',
];

/**
 * کدهای تخفیفی که سایت باید داشته باشد — فقط **ساخته** می‌شوند اگر نباشند؛ کدِ موجود هرگز بازنویسی
 * نمی‌شود (ویرایش و آمار از «کالاها ← کدهای تخفیف»ِ حسابداری). مبلغ‌ها به واحدِ سایت (تومان، IRT).
 */
const STLH_COUPONS = [
	// کدِ پاپ‌آپِ «اولین خرید»؛ بی‌آن نگهبانِ قالب پاپ‌آپ را پنهان می‌کرد
	'STLAND19' => [
		'type'         => 'fixed_cart',
		'amount'       => 50000,
		'minimum'      => 1000000,
		'per_customer' => 1,
		'individual'   => true,
		'description'  => 'اولین خرید — پاپ‌آپِ سایت (۵۰ هزار تومان، سبدِ بالای ۱ میلیون)',
	],
];

/**
 * گزینه‌های خودِ وردپرس (نه قالب) — همان روال: یک بار نوشتن، مقدارِ قبلی در `stlh_bakala_backup`
 * با پیشوندِ `wp:`. شناسه‌ی پیوست فقط وقتی نوشته می‌شود که فایلش روی هاست باشد.
 */
const STLH_SITE_OPTIONS = [
	// آیکونِ سایت (نوارِ مدیر و تبِ مرورگر) — فایلِ پیوستِ ۴۴۴۹۲ در جابه‌جایی به پارس‌پک گم شد و
	// اصلش هم نیست؛ لوگوی «SL» (۴۴۸۰۱، مربعِ ۱۰۲۴) جایش (صاحب فروشگاه، ۲۱ مهر ۱۴۰۵: «لوگوی بالا خراب است»)
	'site_icon' => 44801,
];

add_action( 'init', 'stlh_bakala_apply', 20 );

function stlh_bakala_apply(): void {
	$hash = md5( (string) wp_json_encode( [ STLH_BAKALA_SET, STLH_COUPONS, STLH_SITE_OPTIONS ] ) );
	if ( get_option( 'stlh_bakala_set_hash' ) === $hash ) {
		return;
	}
	// دو درخواستِ هم‌زمان نباید هر دو کد بسازند: `add_option` درجِ اتمی است
	if ( ! add_option( 'stlh_bakala_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'stlh_bakala_lock' ) < 120 ) {
			return;
		}
		update_option( 'stlh_bakala_lock', time(), false );
	}
	$coupons = stlh_coupons_ensure();
	global $wpdb;
	// خامِ دیتابیس، نه `get_option`: نگهبانِ قالب (`theme-guard.php`) مقدارِ صفحه‌ی مشتری را دست می‌برد
	$raw  = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'bakala_options' ) );
	$opts = maybe_unserialize( (string) $raw );
	if ( ! is_array( $opts ) ) {
		delete_option( 'stlh_bakala_lock' );
		return;
	}
	$backup  = (array) get_option( 'stlh_bakala_backup', [] );
	$changed = [];
	$missing = [];
	foreach ( STLH_BAKALA_SET as $key => $value ) {
		if ( ! array_key_exists( $key, $opts ) ) {
			$missing[] = $key;
			continue;
		}
		if ( $opts[ $key ] === $value ) {
			continue;
		}
		if ( ! array_key_exists( $key, $backup ) ) {
			$backup[ $key ] = $opts[ $key ];
		}
		$opts[ $key ] = $value;
		$changed[]    = $key;
	}
	foreach ( STLH_SITE_OPTIONS as $key => $value ) {
		$current = get_option( $key );
		if ( (string) $current === (string) $value ) {
			continue;
		}
		if ( 'site_icon' === $key ) {
			$file = get_attached_file( (int) $value );
			if ( ! $file || ! file_exists( $file ) ) {
				$missing[] = 'wp:' . $key;
				continue;
			}
		}
		if ( ! array_key_exists( 'wp:' . $key, $backup ) ) {
			$backup[ 'wp:' . $key ] = $current;
		}
		update_option( $key, $value );
		$changed[] = 'wp:' . $key;
	}
	if ( $changed ) {
		update_option( 'stlh_bakala_backup', $backup, false );
		if ( array_filter( $changed, static fn( $k ) => ! str_starts_with( $k, 'wp:' ) ) ) {
			update_option( 'bakala_options', $opts );
			delete_option( 'bakala_options-transients' );
		}
	}
	if ( $changed || $coupons ) {
		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
		} else {
			do_action( 'litespeed_purge_all' );
		}
	}
	update_option( 'stlh_bakala_applied', [ 'at' => gmdate( 'c' ), 'ver' => STLH_VER, 'changed' => $changed, 'missing' => $missing, 'coupons' => $coupons ], false );
	update_option( 'stlh_bakala_set_hash', $hash, false );
	delete_option( 'stlh_bakala_lock' );
}

/** کدهای `STLH_COUPONS` که نیستند را می‌سازد؛ کدهای ساخته‌شده را برمی‌گرداند */
function stlh_coupons_ensure(): array {
	if ( ! class_exists( 'WC_Coupon' ) || ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
		return [];
	}
	$made = [];
	foreach ( STLH_COUPONS as $code => $c ) {
		if ( wc_get_coupon_id_by_code( $code ) > 0 ) {
			continue;
		}
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $c['type'] );
		$coupon->set_amount( $c['amount'] );
		$coupon->set_minimum_amount( $c['minimum'] ?? 0 );
		$coupon->set_usage_limit_per_user( $c['per_customer'] ?? 0 );
		$coupon->set_individual_use( ! empty( $c['individual'] ) );
		$coupon->set_description( $c['description'] ?? '' );
		if ( $coupon->save() ) {
			$made[] = $code;
		}
	}
	return $made;
}
