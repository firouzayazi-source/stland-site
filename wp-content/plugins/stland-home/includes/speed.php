<?php
/**
 * سرعت — رژیمِ فایل‌ها در صفحه‌های فروشگاه، و سبک کردنِ هر درخواست.
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «تمام زورت را بزن سرعتِ سایت در بالاترین سطح باشد.»
 * اندازه‌گیری با مرورگرِ واقعی: قالبِ باکالا در **هر** صفحه ۳۰ فایلِ CSS و ۳۵
 * فایلِ JS می‌فرستد، از جمله چیزهایی که آن صفحه اصلاً ندارد — آیکن‌فونتِ
 * المنتور (۳۵۴ کیلوبایت برای صفر آیکن)، دو تقویمِ شمسی، اسلایدرِ قیمت، کیف پول،
 * استوری، شمارش‌معکوس، select2 و… روی گوشی همین‌ها چند ثانیه صفحه‌ی سفید است.
 *
 * ─── قاعده ───
 * هر صفحه فقط چیزی را می‌گیرد که روی همان صفحه استفاده می‌شود. فهرست‌ها از
 * بررسیِ markupِ واقعیِ هر صفحه درآمده‌اند (نه حدس) و پیش از انتشار با مسدود
 * کردنِ همین فایل‌ها در مرورگر سنجیده شده‌اند: بی‌خطای JS، جستجو و افزودن به
 * سبد سالم، عکسِ صفحه یکسان. سبد، پرداخت و حساب دست نمی‌خورند.
 *
 * ⛔ `wp_dequeue_*` کافی نیست: اگر فایلِ دیگری یکی از این‌ها را «وابستگی» اعلام
 *    کرده باشد، وردپرس باز هم چاپش می‌کند. پس تگِ خروجی هم خالی می‌شود
 *    (`style_loader_tag`/`script_loader_tag`). و `wp_deregister` هرگز — وابسته‌هایش
 *    هم می‌پرند.
 * ⛔ یک کلید در تنظیمات («رژیمِ فایل‌ها») خاموشش می‌کند؛ اگر چیزی شکست، اول آن.
 */

defined( 'ABSPATH' ) || exit;

/** چه چیزی در کدام صفحه لازم نیست — کلید: دسته‌ی صفحه، مقدار: [css => [...], js => [...]] */
function stlh_diet_lists(): array {
	// در هیچ صفحه‌ی فروشگاهی استفاده نمی‌شود (markup هیچ‌کدام را صدا نمی‌زند)
	$never_css = [ 'mega-theme-icon', 'pDate-style', 'bakala-story-style', 'bakala-wallet-frontend' ];
	// vanilla-tilt می‌ماند: قالب روی دسکتاپ `VanillaTilt` را همه‌جا صدا می‌زند
	$never_js  = [ 'pDate', 'pDatepicker', 'pDatepickerLoader', 'bakala-story-script', 'bakala-wallet-frontend', 'jquery-flipclock', 'jquery-lif', 'matrix-wolfoffer-countdown', 'matrix-wolfoffer-easing', 'flipdown' ];
	// صفحه‌ی اصلی (قالبِ خودِ افزونه): نه المنتور، نه اسلایدر، نه فیلتر، نه فرم
	$front_css = [ 'elementor-frontend', 'elementor-post-42615', 'elementor-post-12643', 'nouislider', 'slick.css', 'slick.theme', 'select2', 'swatches-and-photos', 'jcaa-core', 'persian-datepicker' ];
	// flickity می‌ماند: general.jsِ قالب روی صفحه‌ی اصلی `$.fn.flickity` را صدا می‌زند و بی‌آن خطا می‌دهد
	$front_js  = [ 'elementor-frontend', 'elementor-frontend-modules', 'elementor-webpack-runtime', 'nouislider', 'slick.min.js', 'my_loadmore', 'bakala-select2', 'swatches-and-photos', 'jcaa-product', 'tippy', 'popper', 'persian-datepicker' ];
	// صفحه‌ی محصول: فیلترِ قیمت و «بیشتر بارگذاری کن» ندارد. المنتور می‌ماند — کاروسلِ
	// «محصولات مرتبط» از Swiperِ داخلِ المنتور (CSS و JS) استفاده می‌کند؛ بی‌آن کارت‌ها له می‌شوند.
	$product_css = [ 'nouislider', 'persian-datepicker' ];
	// تقویمِ شمسیِ قالب (`jalaliDatepicker`) فقط در فروشگاه صدا زده می‌شود، پس آنجا می‌ماند
	$product_js  = [ 'nouislider', 'my_loadmore', 'tippy', 'persian-datepicker' ];
	// فروشگاه/دسته/جستجو: فیلترِ قیمت و «بیشتر» لازم است؛ المنتور نه
	$shop_css = [ 'elementor-frontend', 'elementor-post-12643' ];
	$shop_js  = [ 'elementor-frontend', 'elementor-frontend-modules', 'elementor-webpack-runtime' ];
	return [
		'front'   => [ 'css' => array_merge( $never_css, $front_css ), 'js' => array_merge( $never_js, $front_js ) ],
		'product' => [ 'css' => array_merge( $never_css, $product_css ), 'js' => array_merge( $never_js, $product_js ) ],
		'shop'    => [ 'css' => array_merge( $never_css, $shop_css ), 'js' => array_merge( $never_js, $shop_js ) ],
	];
}

/** این درخواست کدام دسته است؟ null یعنی دست نزن (سبد، پرداخت، حساب، برگه‌ها…) */
function stlh_diet_context(): ?string {
	if ( is_admin() || is_customize_preview() || isset( $_GET['elementor-preview'] ) || is_user_logged_in() && isset( $_GET['stlh_nodiet'] ) ) {
		return null;
	}
	if ( is_front_page() && '1' === stlh_opt( 'takeover' ) ) {
		return 'front';
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		return 'product';
	}
	if ( ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) || ( is_search() && 'product' === ( $_GET['post_type'] ?? '' ) ) ) {
		return 'shop';
	}
	return null;
}

function stlh_diet_on(): bool {
	return '1' === (string) stlh_opt( 'speed_diet' );
}

/** فهرستِ همین درخواست؛ یک بار حساب می‌شود */
function stlh_diet_current(): array {
	static $cur = null;
	if ( null !== $cur ) {
		return $cur;
	}
	$ctx = stlh_diet_on() ? stlh_diet_context() : null;
	$cur = $ctx ? stlh_diet_lists()[ $ctx ] : [ 'css' => [], 'js' => [] ];
	return $cur;
}

add_action( 'wp_enqueue_scripts', static function (): void {
	$d = stlh_diet_current();
	foreach ( $d['css'] as $h ) {
		wp_dequeue_style( $h );
	}
	foreach ( $d['js'] as $h ) {
		wp_dequeue_script( $h );
	}
}, 999 );

add_filter( 'style_loader_tag', static function ( string $tag, string $handle ): string {
	return in_array( $handle, stlh_diet_current()['css'], true ) ? '' : $tag;
}, 999, 2 );

add_filter( 'script_loader_tag', static function ( string $tag, string $handle ): string {
	return in_array( $handle, stlh_diet_current()['js'], true ) ? '' : $tag;
}, 999, 2 );

/*
 * ─── سبک کردنِ هر درخواست (همه‌ی صفحه‌ها، پیشخوان هم) ───
 * شکلک‌های وردپرس (یک JS + CSS در هر صفحه برای چیزی که مرورگرها خودشان دارند)،
 * oEmbed (JS + دو مسیرِ REST در head)، و آدرس‌های RSD/wlwmanifest.
 */
add_action( 'init', static function (): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_generator' );
}, 20 );

add_filter( 'tiny_mce_plugins', static fn( $p ) => is_array( $p ) ? array_diff( $p, [ 'wpemoji' ] ) : $p );
add_action( 'wp_enqueue_scripts', static fn() => wp_dequeue_script( 'wp-embed' ), 100 );

/*
 * ضربانِ وردپرس (heartbeat): پیش‌فرض هر ۱۵ ثانیه یک درخواستِ کامل به سرور — روی
 * هاستِ اشتراکی یعنی پیشخوانِ باز، سرور را مدام مشغول نگه می‌دارد. هر ۶۰ ثانیه
 * کافی است (قفلِ ویرایشِ هم‌زمان همچنان کار می‌کند)، و روی خودِ داشبورد اصلاً نه.
 */
add_filter( 'heartbeat_settings', static function ( array $s ): array {
	$s['interval'] = 60;
	return $s;
} );
add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
	if ( 'index.php' === $hook ) {
		wp_dequeue_script( 'heartbeat' );
	}
}, 99 );

/*
 * ─── متایِ برگه‌های منو در هر صفحه خوانده نشود ───
 * پروفایلِ سایتِ زنده (مهر ۱۴۰۵): سنگین‌ترین کوئری‌های هر صفحه‌ی محصول/دسته (۱۵۰ تا ۳۵۰
 * میلی‌ثانیه، ۱۰ کوئری) خواندنِ **همه‌ی** متایِ برگه‌هایی است که در منوهای هدر و فوتر
 * لینک شده‌اند (درباره‌ی ما، تماس، راهنمای خرید، روش‌های پرداخت، حریم خصوصی، پرسش‌ها…).
 * این برگه‌ها با المنتور ساخته شده‌اند و متایشان (`_elementor_data`) ده‌ها کیلوبایت
 * است؛ منو فقط عنوان و لینکشان را لازم دارد. هسته (`wp_get_nav_menu_items`) آن‌ها را با
 * `get_posts( include, nopaging, update_post_term_cache=false )` می‌گیرد که به‌طورِ
 * پیش‌فرض متا را هم بار می‌کند. این‌جا فقط برای همان الگو `update_post_meta_cache` خاموش
 * می‌شود؛ اگر کدی بعداً متایِ یکی از آن برگه‌ها را بخواهد، همان لحظه و فقط برای همان
 * برگه خوانده می‌شود (درستی عوض نمی‌شود، فقط کارِ بیهوده حذف می‌شود).
 */
add_action( 'pre_get_posts', static function ( WP_Query $q ): void {
	if ( is_admin() || ! stlh_diet_on() ) {
		return;
	}
	if ( $q->get( 'include' ) && $q->get( 'nopaging' ) && false === $q->get( 'update_post_term_cache' ) && 'nav_menu_item' !== $q->get( 'post_type' ) && ! $q->is_main_query() ) {
		$q->set( 'update_post_meta_cache', false );
	}
}, 1 );
