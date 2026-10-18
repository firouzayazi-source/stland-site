<?php
/**
 * «پرسش‌های متداول»ِ هر مقاله به زبانِ گوگل (FAQPage).
 *
 * صاحب فروشگاه (۲۶ مهر ۱۴۰۵): ارتقای افزونه — «همه را بساز». هر مقاله‌ی برنامه‌ی محتوا بخشِ «پرسش‌های متداول» دارد
 * (`docs/seo-plan.md`: «برای هوش مصنوعی: پاسخِ کوتاه و صریح … و ۳ تا ۶ پرسشِ متداول»)، ولی گوگل و پاسخ‌گوهای هوش مصنوعی آن
 * را فقط متن می‌دیدند. این‌جا همان پرسش‌ها و پاسخ‌ها از خودِ متنِ منتشرشده خوانده می‌شوند — هر `<h3>` بعد از سرتیترِ
 * `id="faq"` و بندِ پس از آن، تا سرتیترِ بعدیِ سطحِ ۲ — و نه از جای دیگر؛ پس چیزی که در صفحه نیست به گوگل گفته نمی‌شود.
 *
 * مقاله‌ای که بخشِ پرسش ندارد، یا کمتر از دو پرسش دارد، چیزی نمی‌گیرد.
 */

defined( 'ABSPATH' ) || exit;

/** پرسش و پاسخ‌های بخشِ `#faq`ِ یک متن — [[پرسش، پاسخ]، …] */
function stlh_faq_pairs( string $html ): array {
	if ( ! preg_match( '/<h2\b[^>]*\bid=["\']faq["\'][^>]*>.*?<\/h2>(.*?)(?=<h2\b|$)/is', $html, $m ) ) {
		return [];
	}
	preg_match_all( '/<h3\b[^>]*>(.*?)<\/h3>\s*(?:<!--[^>]*-->\s*)*<p\b[^>]*>(.*?)<\/p>/is', $m[1], $qa, PREG_SET_ORDER );
	$pairs = [];
	foreach ( $qa as $row ) {
		$q = trim( wp_strip_all_tags( $row[1] ) );
		$a = trim( wp_strip_all_tags( $row[2] ) );
		if ( '' !== $q && '' !== $a ) {
			$pairs[] = [ $q, $a ];
		}
	}
	return $pairs;
}

add_action( 'wp_head', static function (): void {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$pairs = stlh_faq_pairs( (string) get_post_field( 'post_content', get_queried_object_id() ) );
	if ( count( $pairs ) < 2 ) {
		return;
	}
	$data = [
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => array_map( static fn( $p ) => [
			'@type'          => 'Question',
			'name'           => $p[0],
			'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $p[1] ],
		], $pairs ),
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSONِ خودِ ما
}, 30 );
