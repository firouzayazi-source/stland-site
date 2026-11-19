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

const STLH_SMS_SET_VER = '4';

function stlh_sms_desired(): array {
	$f     = function_exists( 'stlh_store_facts' ) ? stlh_store_facts() : [];
	$phone = (string) ( $f['phone'] ?? '' );
	// ۲ (۲۴ آبان ۱۴۰۵): خریدِ آزمایشی (سفارشِ ۴۵۳۸۰) با «خطای 11: شماره گیرنده در لیست سیاه است» برگشت — خطِ اشتراکیِ
	// ملی‌پیامک پیامکِ معمولی به شماره‌ی بسته‌شده نمی‌فرستد. پس الگو (خدماتی، `BaseServiceNumber`): متن به قالبِ
	// `pattern:<کد>` + یک سطر برای هر متغیر ({0}، {1}… به همین ترتیب). کدها الگوهای تأییدشده‌ی خودِ صاحب فروشگاه در پنلِ
	// ملی‌پیامک‌اند (عکسِ پنل، ۲۴ آبان) — همه یک متغیر دارند: شماره‌ی سفارش.
	//   ۳۲۳۴۳۲ «… سفارش {0} ثبت و در حال پردازش است…»  ۲۹۱۷۹۶ «… سفارش {0} در انتظار بررسی می‌باشد.»
	//   ۲۹۱۷۹۷ «… سفارش {0} تکمیل شد…»
	// ۳ (همان روز، دو برگِ دیگرِ پنل): ۲۸۵۶۷۷ «… سفارش {0} لغو شد.» و ۲۶۸۳۸۰ «سفارش {0} با کد {1} به اداره پست تحویل
	//   داده شد… کد مرسوله {2} را… رهگیری نمایید» — {1} و {2} هر دو کدِ رهگیری؛ نامِ متغیرها باید فرق کند (افزونه آن‌ها را با
	//   نام در آرایه می‌گذارد و نامِ تکراری جای قبلی را می‌گیرد). کدِ ورودِ باکالا هم الگوی ۲۶۶۸۰۶ است (همان `ippanel_pattern`).
	// مدیر همان الگوها را می‌گیرد (شماره‌ی سفارش + وضعیت از خودِ متن پیداست).
	return [
		'sms_buyer_settings'       => [
			'order_status'                    => [ 'processing' => 'processing', 'on-hold' => 'on-hold', 'completed' => 'completed', 'cancelled' => 'cancelled', 'set-post-tracking-code' => 'set-post-tracking-code' ],
			'sms_body_processing'             => "pattern:323432\norder:{order_id}",
			'sms_body_on-hold'                => "pattern:291796\norder:{order_id}",
			'sms_body_completed'              => "pattern:291797\norder:{order_id}",
			'sms_body_cancelled'              => "pattern:285677\norder:{order_id}",
			'sms_body_set-post-tracking-code' => "pattern:268380\norder:{order_id}\ncode:{post_tracking_code}\ncode2:{post_tracking_code}",
		],
		'sms_super_admin_settings' => [
			'super_admin_phone'               => $phone,
			'super_admin_order_status'        => [ 'processing' => 'processing', 'on-hold' => 'on-hold', 'bank-sent' => 'bank-sent', 'cancelled' => 'cancelled' ],
			'super_admin_sms_body_processing' => "pattern:323432\norder:{order_id}",
			'super_admin_sms_body_on-hold'    => "pattern:291796\norder:{order_id}",
			// ۴ (۲۹ آبان ۱۴۰۵): مشتری «پرداخت کردم»ِ واریز به شبا را زد (وضعیتِ bank-sent، bank-receipt.php) — مدیر با همان الگوی «در انتظار بررسی» خبر می‌شود
			'super_admin_sms_body_bank-sent'  => "pattern:291796\norder:{order_id}",
			'super_admin_sms_body_cancelled'  => "pattern:285677\norder:{order_id}",
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
