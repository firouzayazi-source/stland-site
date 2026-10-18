<?php
/**
 * کادرِ هشدارِ قالب و کادرِ پشتیبانیِ خالی در صفحه‌ی محصول.
 *
 * صاحب فروشگاه (۲۶ مهر ۱۴۰۵، با عکسِ صفحه‌ی کابل):
 *  • «هشدار سامانه همتا» برای گوشی معنی دارد، این‌جا نباید باشد؛ در گوشی هم باید متن داشته باشد. قالب یک متنِ ثابت برای همه‌ی
 *    محصول‌ها دارد (`alert_box_content` = فقط عنوان، بی توضیح). حالا در صفحه‌ی گوشی، متن از رجیستریِ همان گوشی (متای `registry`
 *    که حسابداری می‌فرستد) ساخته می‌شود، و در صفحه‌ی غیرِ گوشی کادر برداشته می‌شود. متنی که برای یک محصولِ خاص جدا نوشته شده
 *    (متنی جز همان متنِ ثابت) دست نمی‌خورد.
 *  • کادرِ آرم زیرِ دکمه‌ی خرید «کارایی‌اش چیست؟» — کادرِ «تیمِ پشتیبانی»ِ قالب است که فقط عکس دارد و نام و شماره‌اش خالی
 *    است؛ تماس و واتس‌اپ را کادرِ «بپرسید» (`product-ask.php`) کامل دارد، پس پنهان می‌شود.
 *
 * روی HTMLِ نهایی پیش از حافظه‌ی موقت (`litespeed_buffer_after`)، چون کادرِ هشدار در بخشِ رمزگذاری‌شده‌ی قالب ساخته می‌شود.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'litespeed_buffer_after', 'stlh_product_alert', 24 );

function stlh_product_alert( $html ) {
	if ( ! is_string( $html ) || is_admin() || ! function_exists( 'is_product' ) || ! is_product() || false === strpos( $html, 'bakala-alert-box' ) ) {
		return $html;
	}
	$id    = (int) get_queried_object_id();
	$opts  = get_option( 'bakala_options' );
	$theme = is_array( $opts ) ? (string) ( $opts['alert_box_content'] ?? '' ) : '';
	$reg   = trim( wp_strip_all_tags( (string) get_post_meta( $id, 'registry', true ) ) );
	$text  = '';
	if ( '' !== $reg ) {
		$text = stlh_label( false === mb_strpos( $reg, 'نشده' ) ? 'hamta_ok' : 'hamta_no' );
	}
	$out = preg_replace_callback(
		'/<div class="bakala-alert-box">\s*<div class="alert-box-wrap">(.*?)<p class="alert-box-text">(.*?)<\/p>\s*<\/div>\s*<\/div>/s',
		static function ( array $m ) use ( $theme, $text, $id ): string {
			$cur = trim( wp_strip_all_tags( $m[2] ) );
			if ( '' !== $cur && trim( wp_strip_all_tags( $theme ) ) !== $cur ) {
				return $m[0]; // متنِ ویژه‌ی همین محصول
			}
			if ( '' === $text || ! stlh_is_phone_product( $id ) ) {
				return '';
			}
			// آیکن به زبانِ مشترکِ سایت (کاشیِ گرادیان، آیکنِ سفید): سبزِ سپر برای رجیسترشده، کهربایی برای رجیسترنشده
			$ok   = false === mb_strpos( $reg, 'نشده' );
			$path = $ok ? '<path d="M12 3l7 2.8v5.4c0 4.5-3 7.9-7 9.8-4-1.9-7-5.3-7-9.8V5.8z"/><path d="m8.8 12.2 2.2 2.2 4.4-4.6"/>'
				: '<path d="M12 3.5 21 19.5H3z"/><path d="M12 10v4.2M12 16.9v.01"/>';
			$ico  = '<span class="stlh-alert-ico' . ( $ok ? '' : ' is-warn' ) . '" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg></span>';
			return '<div class="bakala-alert-box stlh-alert"><div class="alert-box-wrap">' . $ico . '<p class="alert-box-text">' . esc_html( $text ) . '</p></div></div>';
		},
		$html
	);
	return is_string( $out ) ? $out : $html;
}

// صفحه‌های محصول با به‌روزرسانیِ افزونه پاک نمی‌شوند؛ یک بار دوباره ساخته شوند تا کادرها درست برسند
add_action( 'init', static function (): void {
	if ( (int) get_option( 'stlh_product_alert_rev', 0 ) >= 3 || ! defined( 'LSCWP_V' ) ) {
		return;
	}
	update_option( 'stlh_product_alert_rev', 3, false );
	do_action( 'litespeed_purge_posttype', 'product' );
}, 45 );
