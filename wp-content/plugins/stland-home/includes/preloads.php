<?php
/**
 * «پیش‌بارگذاری»ِ عکس‌ها در `<head>` — فقط مالِ خودِ سایت، و هر کدام یک بار.
 *
 * صاحب فروشگاه (۲۶ مهر ۱۴۰۵): «سرعتِ لودِ سایت و باز کردنِ صفحه‌ها». صفحه‌ی اصلیِ گوشی پیش از هر چیز با اولویتِ بالا
 * عکسِ دموی قالب را از سایتِ دیگری (`bakala.site/…/slide-mob-01.webp`) می‌خواست — اتصالِ تازه به سروری بیرون از ایران،
 * برای عکسی که اصلاً روی صفحه نیست — و یک عکسِ اسلایدر را سه بار پیش‌بارگذاری می‌کرد. پنج درخواستِ «اولویتِ بالا» با هم
 * سرِ پهنای باند دعوا می‌کردند و عکسِ اصلیِ صفحه دیرتر می‌رسید. کدِ قالب رمزگذاری شده، پس روی HTMLِ نهایی پیش از رفتن به
 * کشِ LiteSpeed درست می‌شود (`litespeed_buffer_after`) و نسخه‌ی کش‌شده هم همان درست‌شده است.
 *
 * فقط `<link rel="preload" as="image">`ِ داخلِ `<head>` دست می‌خورد؛ CSS، فونت و بقیه‌ی صفحه نه.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'litespeed_buffer_after', 'stlh_fix_image_preloads', 20 );

function stlh_fix_image_preloads( $html ) {
	if ( ! is_string( $html ) || is_admin() || ! str_contains( $html, 'preload' ) ) {
		return $html;
	}
	$end = stripos( $html, '</head>' );
	if ( false === $end ) {
		return $html;
	}
	$own  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$seen = [];
	$head = preg_replace_callback(
		'/<link\b[^>]*\brel=["\']preload["\'][^>]*>\s*/i',
		static function ( array $m ) use ( $own, &$seen ): string {
			$tag = $m[0];
			if ( ! preg_match( '/\bas=["\']image["\']/i', $tag ) || ! preg_match( '/\bhref=["\']([^"\']+)["\']/i', $tag, $h ) ) {
				return $tag;
			}
			$host = strtolower( (string) wp_parse_url( $h[1], PHP_URL_HOST ) );
			if ( '' !== $host && $host !== $own && 'www.' . $own !== $host ) {
				return ''; // عکسِ سایتِ دیگر (دموی قالب)
			}
			if ( isset( $seen[ $h[1] ] ) ) {
				return ''; // تکراری
			}
			$seen[ $h[1] ] = true;
			return $tag;
		},
		substr( $html, 0, $end )
	);
	return null === $head ? $html : $head . substr( $html, $end );
}
