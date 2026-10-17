<?php
/**
 * «تازه‌های خبری» در صفحه‌ی مقاله، روی گوشی.
 *
 * صاحب فروشگاه (۲۵ مهر ۱۴۰۵): «این بخشِ تازه‌های خبری قبل از مطلب می‌آید و تمامِ فضا را گرفته؛ در سایت‌های حرفه‌ای
 * چطور است؟» پیشنهاد و تأیید: ستونِ کناریِ وبلاگِ باکالا (`aside.blog-sidebar`، ابزارکِ `Recent_Posts_Widget`) روی گوشی
 * زیرِ ستونِ مقاله می‌افتاد و خواننده باید از پنج خبر رد می‌شد تا به متن برسد — گوگل هم نسخه‌ی گوشی را می‌سنجد. حالا:
 *
 * • روی گوشی بعد از مقاله، کادرِ «ما را دنبال کنید» و «مطالب مرتبط»ِ قالب می‌آید (همه‌ی بچه‌های ردیف ترتیب می‌گیرند؛
 *   «مطالب مرتبط» هم داخلِ همین ردیف است و بی ترتیب بالای مقاله می‌پرید)، به شکلِ اسلایدرِ افقی؛ دسکتاپ همان ستونِ کناری.
 * • مقاله‌ای که باز است در فهرستِ خودش نمی‌آید (`bakala_default_query_arguments`، فیلترِ خودِ قالب).
 * • عکسِ کارت روی گوشی اندازه‌ی `medium` است، نه ۱۰۰×۱۰۰ِ ابزارک که در کارت تار می‌شد — فقط داخلِ همین ستون.
 *
 * قالب دست نمی‌خورد. CSS داخلِ خودِ صفحه است، مثلِ صفحه‌ی لیست (کشِ ترکیبیِ LiteSpeed گاهی کهنه می‌ماند).
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'bakala_default_query_arguments', static function ( $query ) {
	if ( is_array( $query ) && is_singular( 'post' ) ) {
		$query['post__not_in'] = array_values( array_unique( array_merge( (array) ( $query['post__not_in'] ?? [] ), [ (int) get_queried_object_id() ] ) ) );
	}
	return $query;
} );

// فقط وقتی ستونِ کناری در حالِ چاپ است (نه عکسِ شاخصِ خودِ مقاله یا جای دیگر)
add_action( 'dynamic_sidebar_before', static function (): void {
	$GLOBALS['stlh_in_sidebar'] = true;
} );
add_action( 'dynamic_sidebar_after', static function (): void {
	$GLOBALS['stlh_in_sidebar'] = false;
} );
add_filter( 'post_thumbnail_size', static function ( $size ) {
	if ( ! empty( $GLOBALS['stlh_in_sidebar'] ) && is_array( $size ) && is_singular( 'post' ) && wp_is_mobile() ) {
		return 'medium';
	}
	return $size;
} );

add_action( 'wp_head', static function (): void {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$s = '.single-post .main-warp>.row.right_sidebar';
	?>
<style id="stlh-blog-sidebar">
@media (max-width:767px){
<?php echo $s; ?>{display:flex;flex-direction:column}
<?php echo $s; ?>>*{order:2}
<?php echo $s; ?>>section.col-sm-9{order:1}
<?php echo $s; ?>>aside.blog-sidebar{order:3;width:100%;margin:8px 0 24px}
.single-post .blog-sidebar .hr-widget{margin:0;padding:0;background:none;box-shadow:none}
.single-post .blog-sidebar .blog-widget-title{margin:0 4px 10px;font-size:17px;font-weight:800}
.single-post .blog-sidebar .rpwe-ul{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;list-style:none;margin:0;padding:2px 2px 12px;scrollbar-width:none}
.single-post .blog-sidebar .rpwe-ul::-webkit-scrollbar{display:none}
.single-post .blog-sidebar .rpwe-li{flex:0 0 62%;max-width:240px;scroll-snap-align:start;margin:0;padding:0;border:0;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.single-post .blog-sidebar .rpwe-img{display:block;margin:0;float:none}
.single-post .blog-sidebar .rpwe-li img{display:block;float:none;margin:0;width:100%;height:auto;aspect-ratio:16/9;object-fit:cover;border-radius:0}
.single-post .blog-sidebar .rpwe-title{margin:8px 10px 4px;font-size:13px;line-height:1.7;font-weight:700;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.single-post .blog-sidebar .rpwe-title a{color:inherit}
.single-post .blog-sidebar .rpwe-time{display:block;margin:0 10px 10px;font-size:11px;color:#8e8e93}
}
</style>
	<?php
}, 20 );
