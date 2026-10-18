<?php
/**
 * «مقایسه‌ی گوشی‌ها» — صفحه‌ی `stland.ir/compare-phones/`.
 *
 * صاحب فروشگاه (۲۶ مهر ۱۴۰۵، ارتقای افزونه): مشتریِ مردد دو گوشی را کنارِ هم می‌بیند — قیمت، حافظه، رنگ، وضعیت، سلامتِ
 * باتری و سیکل، رجیستری، و خلاصه‌ی دفترچه‌ی سلامت. فقط گوشی‌های **موجود و منتشرشده** که حسابداری برایشان «وضعیت» فرستاده
 * (متاهای قرارداد: `condition`، `storage`، `color`، `battery`، `registry`، `stl_health`) — همه فقط خواندنی.
 *
 * انتخاب با نشانی است (`?a=ID&b=ID`)، پس هر جفت صفحه‌ی خودش را دارد و از حافظه‌ی موقت می‌آید؛ چون با به‌روزرسانیِ محصول
 * پاک نمی‌شود (برگه‌ها از قاعده‌ی پاک‌سازی بیرون‌اند، `cache-tune.php`)، عمرش ۱۰ دقیقه است. از دیدِ گوگل `noindex` است:
 * هزاران جفتِ تکراری صفحه‌ی ارزشمندی نیستند.
 */

defined( 'ABSPATH' ) || exit;

const STLH_COMPARE_PAGE = 'stlh_page_compare';

function stlh_compare_page_id( bool $create = false ): int {
	return stlh_own_page( STLH_COMPARE_PAGE, 'compare-phones', stlh_label( 'compare_title' ), '[stl_compare]', $create );
}

function stlh_compare_url( int $a = 0 ): string {
	$id  = stlh_compare_page_id( true );
	$url = $id ? (string) get_permalink( $id ) : home_url( '/compare-phones/' );
	return $a ? add_query_arg( 'a', $a, $url ) : $url;
}

/** گوشی‌های موجود و منتشرشده با «وضعیت» از حسابداری — [شناسه => نام] */
function stlh_compare_pool(): array {
	// کشِ بخش‌ها متن نگه می‌دارد؛ فهرست به شکلِ JSON، و با هر تغییرِ محصول (نسلِ کش) تازه می‌شود
	$json = stlh_cached( 'compare-pool', static function (): string {
		$ids = get_posts( [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 120,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [
				[ 'key' => 'condition', 'compare' => 'EXISTS' ],
				[ 'key' => '_stock_status', 'value' => 'instock' ],
			],
		] );
		$out = [];
		foreach ( $ids as $id ) {
			$out[ (int) $id ] = get_the_title( $id ) . ' — ' . get_post_meta( $id, 'condition', true );
		}
		return (string) wp_json_encode( $out, JSON_UNESCAPED_UNICODE );
	} );
	$pool = json_decode( $json, true );
	return is_array( $pool ) ? array_combine( array_map( 'intval', array_keys( $pool ) ), array_values( $pool ) ) : [];
}

/** ردیف‌های جدول برای یک گوشی */
function stlh_compare_rows( WC_Product $p ): array {
	$id     = $p->get_id();
	$m      = static fn( string $k ): string => trim( (string) get_post_meta( $id, $k, true ) );
	$h      = function_exists( 'stlh_health_data' ) ? stlh_health_data( $id ) : null;
	$health = '—';
	if ( $h ) {
		$bad    = count( array_filter( $h['items'], static fn( $i ) => 'bad' === $i['t'] ) );
		$note   = count( array_filter( $h['items'], static fn( $i ) => 'info' === $i['t'] ) );
		$health = stlh_fa( count( $h['items'] ) - $bad - $note ) . ' سالم از ' . stlh_fa( $h['total'] )
			. ( $note ? '، ' . stlh_fa( $note ) . ' تعویضی/تعمیری' : '' ) . ( $bad ? '، ' . stlh_fa( $bad ) . ' ایراد' : '' );
	}
	return [
		'قیمت'           => stlh_price_text( $p ),
		'وضعیت'          => $m( 'condition' ) ?: '—',
		'حافظه'          => $m( 'storage' ) ? stlh_fa( $m( 'storage' ) ) : '—',
		'رنگ'            => $m( 'color' ) ?: '—',
		'سلامتِ باتری'   => $m( 'battery' ) ? stlh_fa( $m( 'battery' ) ) . '٪' : '—',
		'سیکلِ شارژ'     => $h && $h['cycles'] ? stlh_fa( $h['cycles'] ) : '—',
		'رجیستری'        => $m( 'registry' ) ?: '—',
		'دفترچه‌ی سلامت' => $health,
	];
}

add_shortcode( 'stl_compare', static function (): string {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	$pool = stlh_compare_pool();
	$pick = [];
	foreach ( [ 'a', 'b' ] as $k ) {
		$v          = isset( $_GET[ $k ] ) ? absint( $_GET[ $k ] ) : 0;
		$pick[ $k ] = isset( $pool[ $v ] ) ? $v : 0;
	}
	$select = static function ( string $k ) use ( $pool, $pick ): string {
		$o = '<option value="">انتخاب کنید…</option>';
		foreach ( $pool as $id => $name ) {
			$o .= '<option value="' . (int) $id . '"' . selected( $pick[ $k ], $id, false ) . '>' . esc_html( stlh_fa( $name ) ) . '</option>';
		}
		return '<select name="' . $k . '" aria-label="گوشیِ ' . ( 'a' === $k ? 'اول' : 'دوم' ) . '">' . $o . '</select>';
	};
	$html = '<div class="stlh-cmp"><p class="stlh-cmp__sub">' . esc_html( stlh_label( 'compare_sub' ) ) . '</p>'
		. '<form method="get" class="stlh-cmp__form">' . $select( 'a' ) . $select( 'b' ) . '<button type="submit">مقایسه</button></form>';
	$prods = array_filter( array_map( static fn( $id ) => $id ? wc_get_product( $id ) : null, $pick ) );
	if ( count( $prods ) === 2 ) {
		[ $a, $b ] = array_values( $prods );
		$ra   = stlh_compare_rows( $a );
		$rb   = stlh_compare_rows( $b );
		$head = static fn( WC_Product $p ): string => '<th><a href="' . esc_url( get_permalink( $p->get_id() ) ) . '">' . stlh_card_image( $p ) . '<span>' . esc_html( $p->get_name() ) . '</span></a></th>';
		$html .= '<div class="stlh-cmp__wrap"><table class="stlh-cmp__t"><thead><tr><th></th>' . $head( $a ) . $head( $b ) . '</tr></thead><tbody>';
		foreach ( $ra as $label => $va ) {
			$vb    = $rb[ $label ];
			$html .= '<tr' . ( $va !== $vb ? ' class="is-diff"' : '' ) . '><th>' . esc_html( $label ) . '</th><td>' . esc_html( $va ) . '</td><td>' . esc_html( $vb ) . '</td></tr>';
		}
		$html .= '</tbody></table></div><p class="stlh-cmp__note">ردیف‌های رنگی با هم فرق دارند. قیمت و موجودی همین لحظه است.</p>';
	} elseif ( ! $pool ) {
		$html .= '<p>فعلاً گوشیِ موجودی برای مقایسه نیست.</p>';
	}
	return $html . '</div>';
} );

add_action( 'template_redirect', static function (): void {
	$id = stlh_compare_page_id();
	if ( $id && is_page( $id ) ) {
		do_action( 'litespeed_control_set_ttl', 600 );
	}
} );

// جفت‌های بی‌شمار برای گوگل صفحه نیستند
add_filter( 'wpseo_robots', static function ( $robots ) {
	$id = stlh_compare_page_id();
	return $id && is_page( $id ) ? 'noindex, follow' : $robots;
} );

add_action( 'wp_head', static function (): void {
	$id = stlh_compare_page_id();
	if ( ! $id || ! is_page( $id ) ) {
		return;
	}
	echo '<style id="stlh-cmp-css">'
		. '.stlh-cmp{direction:rtl;font-size:14px;margin:0 0 32px}.stlh-cmp__sub{color:#555;line-height:1.9}'
		. '.stlh-cmp__form{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 16px}.stlh-cmp__form select{flex:1 1 220px;min-width:0;min-height:44px;border:1px solid #dfe1e6;border-radius:12px;padding:0 10px;font:inherit;background:#fff}'
		. '.stlh-cmp__form button{flex:0 0 auto;min-height:44px;padding:0 22px;border:0;border-radius:12px;background:#0a63ff;color:#fff;font-weight:800}'
		. '.stlh-cmp__wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}.stlh-cmp__t{width:100%;min-width:420px;border-collapse:separate;border-spacing:0;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #eef0f3}'
		. '.stlh-cmp__t th,.stlh-cmp__t td{padding:10px;border-bottom:1px solid #f0f1f4;text-align:center;vertical-align:middle}.stlh-cmp__t tbody th{text-align:right;font-weight:700;color:#3a3a3c;white-space:nowrap}'
		. '.stlh-cmp__t thead th a{display:flex;flex-direction:column;align-items:center;gap:6px;color:#1f2133;font-weight:800;text-decoration:none}.stlh-cmp__t thead img{width:84px;height:84px;object-fit:cover;border-radius:12px}'
		. '.stlh-cmp__t tr.is-diff td{background:#fff8ec}.stlh-cmp__note{color:#8e8e93;font-size:12.5px;margin-top:8px}'
		. "</style>\n"; // phpcs:ignore
} );
