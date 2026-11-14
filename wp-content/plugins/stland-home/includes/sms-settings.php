<?php
/**
 * تنظیماتِ افزونه‌ی «پیامک حرفه‌ای ووکامرس» از مخزن (صاحب فروشگاه، ۲۴ آبان ۱۴۰۵: «تمام تنظیمات خودت انجام بده،
 * متن پیام‌ها رو کوتاه و استاندارد کن و تیک‌هایی که نیاز است بزن»).
 *
 * مثلِ `theme-settings.php`: یک بار برای هر `STLH_SMS_SET_VER` می‌نویسد، فقط کلیدهای همین‌جا را (بقیه‌ی تنظیماتِ افزونه
 * دست نمی‌خورد) و مقدارِ پیشینِ هر بخش را پیش از اولین نوشتن در `stlh_sms_backup` نگه می‌دارد. نتیجه در `stlh_sms_applied`؛
 * سنجش با `stland/v1/sms-check`.
 *
 * ⛔ نام کاربری، رمز و شماره‌ی خطِ سامانه‌ی پیامکی این‌جا نیست (مخزن عمومی است) — صاحب فروشگاه در پیشخوان وارد می‌کند.
 * ⛔ متن‌ها کوتاه‌اند: پیامکِ فارسی تا ۷۰ نویسه یک پیامک حساب می‌شود؛ متن‌های پیش‌فرضِ افزونه (نام، فهرستِ کالا، مبلغ…)
 *    چند پیامک می‌شدند.
 * خریدار: پرداخت‌شده، در انتظارِ بررسی (کارت‌به‌کارت/اقساط)، تکمیل، لغو، کدِ رهگیریِ پست. «در انتظار پرداخت» نه (شلوغ و پرهزینه)،
 * «مسترد شده» نه (فروشگاه وجه پس نمی‌دهد، فقط معاوضه). مدیر: پرداخت‌شده، در انتظارِ بررسی، لغو. خبرنامه‌ی محصول خاموش
 * (تکراریِ «خبرم کنِ» باکالاست و گوشیِ کارکرده تک‌نسخه است).
 */
defined( 'ABSPATH' ) || exit;

const STLH_SMS_SET_VER = '1';

function stlh_sms_desired(): array {
	$f     = function_exists( 'stlh_store_facts' ) ? stlh_store_facts() : [];
	$phone = (string) ( $f['phone'] ?? '' );
	$sign  = "\nاستوک لند";
	return [
		'sms_buyer_settings'       => [
			'order_status'                => [ 'processing' => 'processing', 'on-hold' => 'on-hold', 'completed' => 'completed', 'cancelled' => 'cancelled', 'set-post-tracking-code' => 'set-post-tracking-code' ],
			'sms_body_processing'         => '{b_first_name} عزیز، سفارش {order_id} پرداخت و ثبت شد.' . $sign,
			'sms_body_on-hold'            => '{b_first_name} عزیز، سفارش {order_id} ثبت شد و در حال بررسی است.' . $sign,
			'sms_body_completed'          => '{b_first_name} عزیز، سفارش {order_id} تکمیل شد. ممنون از خریدتان.' . $sign,
			'sms_body_cancelled'          => 'سفارش {order_id} لغو شد. پرسش: ' . $phone . $sign,
			'sms_body_set-post-tracking-code' => 'سفارش {order_id} ارسال شد. کد رهگیری پست: {post_tracking_code}' . $sign,
		],
		'sms_super_admin_settings' => [
			'super_admin_phone'                    => $phone,
			'super_admin_order_status'             => [ 'processing' => 'processing', 'on-hold' => 'on-hold', 'cancelled' => 'cancelled' ],
			'super_admin_sms_body_processing'      => 'سفارش تازه {order_id} پرداخت شد: {price}',
			'super_admin_sms_body_on-hold'         => 'سفارش {order_id} منتظر بررسی است: {price} ({payment_method})',
			'super_admin_sms_body_cancelled'       => 'سفارش {order_id} لغو شد.',
		],
		'sms_notif_settings'       => [
			'enable_notif_sms_main' => 'off',
		],
	];
}

add_action( 'wp_loaded', function (): void {
	if ( get_option( 'stlh_sms_set_ver' ) === STLH_SMS_SET_VER ) {
		return;
	}
	$backup = (array) get_option( 'stlh_sms_backup', [] );
	$done   = [];
	foreach ( stlh_sms_desired() as $section => $vals ) {
		$cur = get_option( $section );
		if ( ! array_key_exists( $section, $backup ) ) {
			$backup[ $section ] = $cur;
		}
		$cur = is_array( $cur ) ? $cur : [];
		update_option( $section, array_merge( $cur, $vals ) );
		$done[ $section ] = array_keys( $vals );
	}
	update_option( 'stlh_sms_backup', $backup, false );
	update_option( 'stlh_sms_applied', [ 'ver' => STLH_SMS_SET_VER, 'at' => gmdate( 'c' ), 'keys' => $done ], false );
	update_option( 'stlh_sms_set_ver', STLH_SMS_SET_VER, false );
} );
