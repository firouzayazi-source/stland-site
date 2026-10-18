<?php
/**
 * آیکون‌های منوی کشوییِ گوشی (`#bk-mm-root`، منوی قالبِ باکالا).
 *
 * صاحب فروشگاه (۲۴ مهر ۱۴۰۵): «آیکونِ لیست آک و کارکرده مرتبط بگذار؛ کلاً آیکون‌های این بخش نامتقارن و بی‌ربط‌اند».
 * قالب رنگِ کاشی را با `nth-child` می‌چرخاند (دو کاشی آبی، دو خاکستری…) و برای مورد بی‌آیکن حرفِ اولِ نام را می‌گذارد
 * («ل»). این‌جا همه‌ی کاشی‌ها یک شکل می‌شوند — همان کاشیِ گرادیان و آیکنِ سفیدِ بخشِ دسته‌بندی‌های صفحه‌ی اصلی — و هر
 * مورد آیکنِ خطیِ خودش را از روی نشانی‌اش می‌گیرد. موردِ تازه‌ای که این‌جا نیست همان کاشیِ یک‌شکل را با آیکنِ قالب می‌گیرد.
 * فقط CSS؛ منو و قالب دست نمی‌خورند.
 */
defined( 'ABSPATH' ) || exit;

/** [انتخاب‌گرِ پیوند، کلیدِ آیکن، رنگ، رنگِ دوم] */
function stlh_mm_icon_rules(): array {
	$home = untrailingslashit( home_url() );
	return [
		[ 'a.bk-mm-link[href="' . $home . '/"]', 'home', '#1f6fd6', '#4f9cf9' ],
		[ 'button.bk-mm-link[data-bk-mm-open="m44319"]', 'phone', '#0f766e', '#2dd4bf' ],
		[ 'button.bk-mm-link[data-bk-mm-open="m44320"]', 'earbuds', '#7c3aed', '#a78bfa' ],
		[ 'a.bk-mm-link[href*="/list/"]', 'list', '#e08a00', '#f7b733' ],
		[ 'a.bk-mm-link[href*="/about/"]', 'info', '#475569', '#94a3b8' ],
		[ 'a.bk-mm-link[href*="/contact/"]', 'call', '#0e9f6e', '#34c38f' ],
	];
}

function stlh_mm_icon_svg( string $key ): string {
	$p = [
		'home'    => '<path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9v11h13V9"/><path d="M10 20v-5.5h4V20"/>',
		'phone'   => '<rect x="6.5" y="2.5" width="11" height="19" rx="3"/><path d="M10.5 5h3M10 18.5h4"/>',
		'earbuds' => '<path d="M8 4.5a3 3 0 0 0-3 3v1a3 3 0 0 0 3 3V19a1.5 1.5 0 0 0 3 0V7.5a3 3 0 0 0-3-3z"/><path d="M16 4.5a3 3 0 0 1 3 3v1a3 3 0 0 1-3 3V19a1.5 1.5 0 0 1-3 0V7.5a3 3 0 0 1 3-3z"/>',
		'list'    => '<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h1M8 12h1M8 16h1M11.5 8H16M11.5 12H16M11.5 16H16"/>',
		'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.6v.01"/>',
		'call'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
	][ $key ] ?? '';
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}

function stlh_mm_icons_css(): string {
	// همه‌ی کاشی‌های سطحِ اول: یک اندازه، تخت با رنگِ زنده، آیکنِ سفید (بی گرادیان و سایه — ۲۷ مهر)
	$css = '#bk-mm-root>.bk-mm-list>li>.bk-mm-link .bk-mm-ico{--g1:#1f6fd6;--g2:#4f9cf9;background:var(--g1)!important;color:#fff!important;box-shadow:none!important;position:relative}'
		. '#bk-mm-root>.bk-mm-list>li>.bk-mm-link .bk-mm-ico i::before{color:#fff}';
	foreach ( stlh_mm_icon_rules() as [ $sel, $key, $c1, $c2 ] ) {
		$svg  = rawurlencode( stlh_mm_icon_svg( $key ) );
		$ico  = '#bk-mm-root ' . $sel . ' .bk-mm-ico';
		$css .= $ico . '{--g1:' . $c1 . ';--g2:' . $c2 . ';font-size:0!important;line-height:0!important}'
			. $ico . '>*{display:none!important}'
			. $ico . '::after{content:"";width:21px;height:21px;background:#fff;-webkit-mask:url("data:image/svg+xml,' . $svg . '") center/contain no-repeat;mask:url("data:image/svg+xml,' . $svg . '") center/contain no-repeat}';
	}
	return $css;
}

add_action( 'wp_head', static function (): void {
	if ( is_admin() ) {
		return;
	}
	echo '<style id="stlh-mm-icons">' . stlh_mm_icons_css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSSِ ساخته‌ی خودمان
}, 30 );
