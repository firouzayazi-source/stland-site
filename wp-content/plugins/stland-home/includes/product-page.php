<?php
/**
 * صفحه‌ی محصول — دو کار، بی‌دست‌زدن به قالبِ باکالا:
 *
 * ۱. **دفترچه‌ی سلامتِ گوشیِ کارکرده** زیرِ «باکس هشدار». حسابداری چک‌لیستِ هر
 *    دستگاه را در متای `stl_health` (قرارداد ۴) می‌فرستد و اینجا یک کارتِ جمع‌وجور
 *    به سبکِ «ویژگی‌ها»ی دیجی‌کالا می‌شود: سرتیترِ خلاصه، باتری، و بندها با ✓/↻/✕ —
 *    ایرادها اول، بعد تعویضی/تعمیری (کهربایی، از ۱.۲۲)، بقیه پشتِ «مشاهده‌ی همه». صاحب فروشگاه (مهر ۱۴۰۵): «دقیقاً زیرِ
 *    همین قسمت … صفحه‌ی سلامتِ گوشی بیاید، همین فضا را پر کند، حرفه‌ای‌تر.»
 *    این تنها استثنا بر «صفحه‌ی محصول فقط از کادرهای خودِ قالب» است، چون قالب
 *    کادری برای چک‌لیست ندارد.
 *
 * ۲. **«دسته‌بندی:» خالی.** تنظیمِ قالب «فقط دسته‌ی اصلی» را نشان می‌دهد: دسته‌ی
 *    اصلیِ Yoast، وگرنه دسته‌های ریشه. گوشی فقط در برگ است («آیفون کارکرده»)،
 *    پس هیچ‌کدام نبود و برچسب تنها می‌ماند. اینجا اگر دسته‌ی اصلی تعیین نشده،
 *    عمیق‌ترین دسته‌ی محصول جایش خوانده می‌شود — فقط خواندن، در دیتابیس چیزی نوشته نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

/** متای `stl_health` → داده‌ی معتبر، یا null */
function stlh_health_data( int $product_id ): ?array {
	$raw  = get_post_meta( $product_id, 'stl_health', true );
	$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	if ( ! is_array( $data ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
		return null;
	}
	$items = [];
	foreach ( $data['items'] as $it ) {
		if ( is_array( $it ) && isset( $it['l'], $it['v'] ) ) {
			$ok = ! empty( $it['ok'] );
			/*
			 * لحن (`t`، از حسابداریِ دفترچه‌ی ۲): ok سبز، info کهربایی (تعویض/تعمیر —
			 * خراب نیست ولی مشتری باید بداند)، bad قرمز. دادهِ قدیمی‌تر فقط `ok` دارد.
			 */
			$tone    = in_array( $it['t'] ?? '', [ 'ok', 'info', 'bad' ], true ) ? $it['t'] : ( $ok ? 'ok' : 'bad' );
			$items[] = [ 'l' => (string) $it['l'], 'v' => (string) $it['v'], 'ok' => 'ok' === $tone, 't' => $tone ];
		}
	}
	if ( ! $items ) {
		return null;
	}
	return [
		'items'   => $items,
		'total'   => max( count( $items ), (int) ( $data['total'] ?? 0 ) ),
		'checked' => (string) ( $data['checked'] ?? '' ),
		'battery' => isset( $data['battery'] ) ? (int) $data['battery'] : null,
		'cycles'  => isset( $data['cycles'] ) ? (int) $data['cycles'] : null,
	];
}

/** HTMLِ کارت — جدا از هوک تا آزمون و پیش‌نمایش بی‌وردپرس هم بسازندش */
function stlh_health_card( array $h ): string {
	$items  = $h['items'];
	$issues = array_values( array_filter( $items, static fn( $i ) => 'bad' === $i['t'] ) );
	$notes  = array_values( array_filter( $items, static fn( $i ) => 'info' === $i['t'] ) );
	$oks    = array_values( array_filter( $items, static fn( $i ) => 'ok' === $i['t'] ) );
	$sorted = array_merge( $issues, $notes, $oks );
	$done   = count( $items );
	$fa     = static fn( $n ) => function_exists( 'stlh_fa' ) ? stlh_fa( (string) $n ) : str_replace( range( 0, 9 ), [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ], (string) $n );
	$e      = static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );

	$badge = $issues
		? '<span class="stl-hc__badge is-warn">' . $fa( count( $issues ) ) . ' مورد نیازمندِ توجه</span>'
		: ( $notes
			? '<span class="stl-hc__badge is-info">' . $fa( count( $notes ) ) . ' قطعه‌ی تعویضی/تعمیری</span>'
			: '<span class="stl-hc__badge">همه سالم</span>' );
	$sub = $fa( $done ) . ' بند از ' . $fa( $h['total'] ) . ' بند بررسی شد';
	if ( '' !== $h['checked'] ) {
		$sub .= ' · ' . $e( $h['checked'] );
	}

	$out  = '<section class="stl-hc" aria-label="دفترچه‌ی سلامت">';
	$out .= '<header class="stl-hc__head"><span class="stl-hc__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v6c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg></span>';
	$out .= '<div class="stl-hc__title"><h3>دفترچه‌ی سلامت</h3><p>' . $sub . '</p></div>' . $badge . '</header>';

	if ( $h['battery'] || $h['cycles'] ) {
		$out .= '<div class="stl-hc__battery">';
		if ( $h['battery'] ) {
			$pct  = max( 0, min( 100, (int) $h['battery'] ) );
			$tone = $pct >= 90 ? 'good' : ( $pct >= 85 ? 'mid' : 'low' );
			$out .= '<div class="stl-hc__tile"><span>سلامتِ باتری</span><b>' . $fa( $pct ) . '٪</b><i class="stl-hc__bar is-' . $tone . '" dir="ltr"><i style="width:' . $pct . '%"></i></i></div>';
		}
		if ( $h['cycles'] ) {
			$out .= '<div class="stl-hc__tile"><span>سیکلِ شارژ</span><b>' . $fa( (int) $h['cycles'] ) . '</b></div>';
		}
		$out .= '</div>';
	}

	$li = static function ( array $i ) use ( $e ): string {
		$mark = match ( $i['t'] ) {
			'ok'    => '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M5 12.5l4.2 4.2L19 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'info'  => '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			default => '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M7 7l10 10M17 7L7 17" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>',
		};
		return '<li class="is-' . $i['t'] . '"><span>' . $e( $i['l'] ) . '</span><b>' . $mark . $e( $i['v'] ) . '</b></li>';
	};

	$first = 6;
	$out  .= '<ul class="stl-hc__grid">' . implode( '', array_map( $li, array_slice( $sorted, 0, $first ) ) ) . '</ul>';
	if ( count( $sorted ) > $first ) {
		$out .= '<details class="stl-hc__more"><summary><span class="stl-hc__open">مشاهده‌ی همه‌ی ' . $fa( count( $sorted ) ) . ' بند</span><span class="stl-hc__close">بستن</span></summary>';
		$out .= '<ul class="stl-hc__grid">' . implode( '', array_map( $li, array_slice( $sorted, $first ) ) ) . '</ul></details>';
	}
	return $out . '</section>';
}

function stlh_health_css(): string {
	return <<<'CSS'
.stl-hc{--hc-ok:#0fa08a;--hc-bad:#e0244d;--hc-mid:#e8a317;--hc-line:#eef0f3;--hc-soft:#f6f7f9;direction:rtl;margin:16px 0 8px;padding:16px;border:1px solid var(--hc-line);border-radius:16px;background:#fff;box-sizing:border-box;width:100%;max-width:100%;font-size:13px;line-height:1.7;color:#3f4064}
.stl-hc *{box-sizing:border-box}
.stl-hc__head{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.stl-hc__icon{flex:0 0 40px;height:40px;border-radius:12px;display:grid;place-items:center;color:var(--hc-ok);background:#e7f7f3}
.stl-hc__title{flex:1;min-width:0}
.stl-hc__title h3{margin:0;font-size:15px;font-weight:800;color:#23254e;line-height:1.5}
.stl-hc__title p{margin:0;font-size:11.5px;color:#81858b}
.stl-hc__badge{flex:0 0 auto;padding:4px 10px;border-radius:999px;font-size:11.5px;font-weight:700;color:var(--hc-ok);background:#e7f7f3;white-space:nowrap}
.stl-hc__badge.is-warn{color:#b4233f;background:#fdecef}.stl-hc__badge.is-info{color:#9a5b00;background:#fff4e0}
.stl-hc__battery{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin-bottom:8px}
.stl-hc__tile{position:relative;padding:10px 12px;border-radius:12px;background:var(--hc-soft)}
.stl-hc__tile span{display:block;font-size:11.5px;color:#81858b}
.stl-hc__tile b{display:block;font-size:15px;font-weight:800;color:#23254e}
.stl-hc__bar{display:block;height:4px;margin-top:6px;border-radius:4px;background:#e3e6ea;overflow:hidden}
.stl-hc__bar>i{display:block;height:100%;border-radius:4px;background:var(--hc-ok)}
.stl-hc__bar.is-mid>i{background:var(--hc-mid)}.stl-hc__bar.is-low>i{background:var(--hc-bad)}
.stl-hc__grid{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}
.stl-hc__more .stl-hc__grid{margin-top:8px}
.stl-hc__grid li{margin:0;padding:8px 12px;border-radius:12px;background:var(--hc-soft);min-width:0}
.stl-hc__grid li span{display:block;font-size:11.5px;color:#81858b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.stl-hc__grid li b{display:flex;align-items:center;gap:4px;font-weight:700;color:#23254e;font-size:13px}
.stl-hc__grid li.is-ok svg{color:var(--hc-ok);flex:0 0 auto}
.stl-hc__grid li.is-bad{background:#fdecef}.stl-hc__grid li.is-bad svg{color:var(--hc-bad);flex:0 0 auto}.stl-hc__grid li.is-bad b{color:#b4233f}
.stl-hc__grid li.is-info{background:#fff4e0}.stl-hc__grid li.is-info svg{color:var(--hc-mid);flex:0 0 auto}.stl-hc__grid li.is-info b{color:#9a5b00;white-space:normal}
.stl-hc__more summary{list-style:none;cursor:pointer;margin-top:10px;display:flex;justify-content:center;align-items:center;gap:6px;padding:8px;border-radius:10px;font-weight:700;color:#19a4c3;font-size:12.5px}
.stl-hc__more summary::-webkit-details-marker{display:none}
.stl-hc__more summary::after{content:"";width:7px;height:7px;border:solid currentColor;border-width:0 0 2px 2px;transform:rotate(-45deg) translateY(-2px)}
.stl-hc__more[open] summary{order:2}.stl-hc__more[open] summary::after{transform:rotate(135deg) translateY(-2px)}
.stl-hc__close,.stl-hc__more[open] .stl-hc__open{display:none}.stl-hc__more[open] .stl-hc__close{display:inline}
.stl-hc__more[open]{display:flex;flex-direction:column}
@media (max-width:767px){.stl-hc{margin:12px 10px;width:auto;padding:14px;border-radius:14px}.stl-hc__grid{grid-template-columns:1fr 1fr}}
.single-product .posted_in:not(:has(a)){display:none}
CSS;
}

/*
 * جای کارت: آخرِ ستونِ خلاصه، همان‌جا که باکسِ ویژه و هشدار نشسته‌اند
 * (`woocommerce_single_product_end` در هر دو نسخه‌ی دسکتاپ و گوشیِ باکالا).
 */
add_action( 'woocommerce_single_product_end', static function (): void {
	$id = get_the_ID();
	$h  = $id ? stlh_health_data( (int) $id ) : null;
	if ( $h ) {
		echo stlh_health_card( $h ); // phpcs:ignore — مقادیر داخلِ تابع escape شده‌اند
	}
}, 99 );

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	wp_register_style( 'stland-product', false, [], STLH_VER );
	wp_enqueue_style( 'stland-product' );
	wp_add_inline_style( 'stland-product', stlh_health_css() );
}, 20 );

/** دسته‌ی اصلیِ خالی → عمیق‌ترین دسته‌ی محصول (فقط برای نمایش) */
add_filter( 'get_post_metadata', static function ( $value, $object_id, $meta_key, $single ) {
	if ( '_yoast_wpseo_primary_product_cat' !== $meta_key || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $value;
	}
	static $busy = false;
	if ( $busy || 'product' !== get_post_type( $object_id ) ) {
		return $value;
	}
	$busy = true;
	$own  = get_post_meta( $object_id, $meta_key, true );
	$busy = false;
	if ( '' !== (string) $own ) {
		return $value;
	}
	$terms = get_the_terms( $object_id, 'product_cat' );
	if ( ! is_array( $terms ) || ! $terms ) {
		return $value;
	}
	$deepest = null;
	$depth   = -1;
	foreach ( $terms as $t ) {
		$d = count( get_ancestors( $t->term_id, 'product_cat', 'taxonomy' ) );
		if ( $d > $depth ) {
			$depth   = $d;
			$deepest = $t;
		}
	}
	return $deepest ? ( $single ? (string) $deepest->term_id : [ (string) $deepest->term_id ] ) : $value;
}, 10, 4 );
