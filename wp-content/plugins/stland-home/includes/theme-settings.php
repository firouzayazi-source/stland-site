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
	// پانویس (صاحب فروشگاه، ۲۱ مهر ۱۴۰۵: «پانویس و سربرگ نیاز به تعویض دارد؟»): آیکون‌های فیس‌بوک، توییتر،
	// گوگل‌پلاس و یوتیوب به `#` می‌رفتند — مقدارِ خالی آیکون را برمی‌دارد (مثلِ آپارات)
	'facebook'                  => '',
	'twitter'                   => '',
	'googleplus'                => '',
	'youtube'                   => '',
	// «۲۰۲۵ - ۲۰۲۹» یعنی چهار سالِ آینده؛ سال‌دار هم هر سال کهنه می‌شود
	'copyright-two'             => 'Copyright © stland.ir',
	// سبدِ خریدِ شناورِ کناره‌ی صفحه (صاحب فروشگاه با عکس، ۲۱ مهر ۱۴۰۵: «پیدایش نکردم غیرفعالش کنم»)؛
	// سبدِ سربرگ سرِ جایش است
	'floating-cart'             => '0',
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

/**
 * افزونه‌هایی که یک بار خاموش می‌شوند (حذف نه — از «افزونه‌ها» دوباره روشن‌شدنی‌اند). صاحب فروشگاه، ۲۲ مهر ۱۴۰۵:
 * «افزونه‌های بلااستفاده را غیرفعال کن، Code Snippets و ژاکت‌آپدیت هم». اگر کسی دوباره روشنشان کند، تا تغییرِ
 * بعدیِ همین فهرست دست نمی‌خورند.
 */
const STLH_PLUGINS_OFF = [
	// جستجوی سایت روی موتورِ خودِ باکالاست (`scale_search_engine` = scale)؛ FiboSearch فقط بار می‌شد
	'ajax-search-for-woocommerce/ajax-search-for-woocommerce.php',
	// تنها کدِ فعالش شورت‌کدِ قدیمیِ `[st_products_slider]` بود که صفحه‌ی اصلیِ افزونه جایش را گرفته (پایین‌تر بی‌صدا می‌شود)
	'code-snippets/code-snippets.php',
	// به‌روزرسانِ خودکارِ محصولاتِ ژاکت (درگاهِ سپ)؛ به‌روزرسانی از این به بعد دستی
	'zhaket-updater/zhaket-updater.php',
];

// برگه‌ی ۴۲۶۱۵ (صفحه‌ی اصلیِ قدیمیِ المنتور) هنوز `[st_products_slider]` دارد؛ بی Code Snippets متنِ خامش نیاید
add_action( 'init', static function (): void {
	if ( ! shortcode_exists( 'st_products_slider' ) ) {
		add_shortcode( 'st_products_slider', '__return_empty_string' );
	}
}, 99 );

add_action( 'init', 'stlh_bakala_apply', 20 );

function stlh_bakala_apply(): void {
	$hash = md5( (string) wp_json_encode( [ STLH_BAKALA_SET, STLH_COUPONS, STLH_SITE_OPTIONS, STLH_PLUGINS_OFF ] ) );
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
	$plugins = stlh_plugins_off();
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
	if ( $changed || $coupons || $plugins ) {
		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
		} else {
			do_action( 'litespeed_purge_all' );
		}
	}
	update_option( 'stlh_bakala_applied', [ 'at' => gmdate( 'c' ), 'ver' => STLH_VER, 'changed' => $changed, 'missing' => $missing, 'coupons' => $coupons, 'plugins_off' => $plugins ], false );
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

/** افزونه‌های `STLH_PLUGINS_OFF` که فعال‌اند را خاموش می‌کند؛ خاموش‌شده‌ها را برمی‌گرداند */
function stlh_plugins_off(): array {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$off = array_values( array_filter( STLH_PLUGINS_OFF, 'is_plugin_active' ) );
	if ( $off ) {
		deactivate_plugins( $off, true );
	}
	return $off;
}
