<?php
/**
 * «گزارشِ سلامتِ آیفون‌های کارکرده‌ی استوک لند» — صاحب فروشگاه، ۸ آبان ۱۴۰۵ («با کیفیتِ عالی بساز»).
 *
 * چرا: هوش مصنوعی عددی را نقل می‌کند که جای دیگری نیست. دفترچه‌ی سلامتِ هر گوشیِ کارکرده (متای `stl_health`، قرارداد ۴،
 * از حسابداری) داده‌ی واقعی و منحصربه‌فرد است؛ این‌جا فقط **خوانده** و جمع زده می‌شود — چیزی دستی نوشته نمی‌شود.
 *
 * چه نشان می‌دهد (همه خودکار، با هر گوشیِ تازه یا تغییرِ دفترچه «نسلِ» کش عوض می‌شود):
 *   • عددهای کلی: تعدادِ گوشیِ بررسی‌شده، میانگین و میانه‌ی سلامتِ باتری، سهمِ ۸۵٪ و بالاتر، سهمِ «بی هیچ ایراد یا تعویض».
 *   • رایج‌ترین موردها (بندهای ناسالم/تعویضی)، و «باتریِ تعویضی» جدا — درسِ مهمِ خریدار: ۱۰۰٪ همیشه یعنی باتریِ اصل نیست.
 *   • هر نسل فقط وقتی دست‌کم STLH_HR_MIN_GROUP گوشی دارد (عددِ دو سه گوشی میانگین نیست).
 *   • داده‌ی ساختاریافته‌ی `Dataset` برای موتورها و هوش مصنوعی؛ لینک در `llms.txt` و زیرِ هر دفترچه در صفحه‌ی محصول.
 * گوشیِ فروخته‌شده هم در آمار می‌ماند (دفترچه‌اش واقعی بوده). برگه یک بار ساخته می‌شود (`stlh_hr_page`)؛ عنوان و متن‌ها از
 * «نام‌ها و متن‌ها» (`hr_*`).
 */

defined( 'ABSPATH' ) || exit;

const STLH_HR_PAGE      = 'stlh_hr_page';
const STLH_HR_SLUG      = 'iphone-health-report';
const STLH_HR_MIN_GROUP = 5;
const STLH_HR_MIN_ALL   = 10;

/** شناسه‌ی برگه؛ با `$create` اگر نیست ساخته می‌شود */
function stlh_hr_page_id( bool $create = false ): int {
	$id = (int) get_option( STLH_HR_PAGE, 0 );
	if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
		return $id;
	}
	if ( ! $create ) {
		return 0;
	}
	$found = get_page_by_path( STLH_HR_SLUG );
	if ( $found instanceof WP_Post && str_contains( (string) $found->post_content, '[stl_health_report]' ) ) {
		$id = $found->ID;
	} else {
		$id = (int) wp_insert_post( [
			'post_type'    => 'page',
			'post_name'    => STLH_HR_SLUG,
			'post_title'   => stlh_label( 'hr_title' ),
			'post_status'  => 'publish',
			'post_content' => '[stl_health_report]',
		] );
		if ( $id ) {
			update_post_meta( $id, '_yoast_wpseo_title', stlh_label( 'hr_seo_title' ) );
			update_post_meta( $id, '_yoast_wpseo_metadesc', stlh_label( 'hr_seo_desc' ) );
			update_post_meta( $id, '_yoast_wpseo_focuskw', 'سلامت باتری آیفون کارکرده' );
		}
	}
	if ( $id ) {
		update_option( STLH_HR_PAGE, $id, false );
	}
	return $id;
}

add_action( 'init', static function (): void {
	if ( ! get_option( STLH_HR_PAGE ) && post_type_exists( 'page' ) && add_option( 'stlh_hr_page_lock', time(), '', false ) ) {
		stlh_hr_page_id( true );
	}
	// ۱.۷۷.۰ برگه را ساخت ولی عنوانِ گوگل خالی ماند (یواست پیش‌فرضِ «عنوان - نامِ سایت» را گذاشت) — یک بار، فقط اگر خالی است
	$id = (int) get_option( STLH_HR_PAGE, 0 );
	if ( $id && ! get_option( 'stlh_hr_seo_set' ) ) {
		foreach ( [ '_yoast_wpseo_title' => stlh_label( 'hr_seo_title' ), '_yoast_wpseo_metadesc' => stlh_label( 'hr_seo_desc' ), '_yoast_wpseo_focuskw' => 'سلامت باتری آیفون کارکرده' ] as $k => $v ) {
			if ( '' === (string) get_post_meta( $id, $k, true ) ) {
				update_post_meta( $id, $k, $v );
			}
		}
		update_option( 'stlh_hr_seo_set', 1, false );
	}
}, 60 );

function stlh_hr_url(): string {
	$id = stlh_hr_page_id();
	return $id ? (string) get_permalink( $id ) : '';
}

/** نسلِ گوشی از نام («آیفون 13 پرو مکس» → 13) */
function stlh_hr_gen( string $name ): int {
	$name = function_exists( 'stlh_ai_en_digits' ) ? stlh_ai_en_digits( $name ) : $name;
	return preg_match( '/(?:آیفون|iphone)\s*(\d{1,2})\b/iu', $name, $m ) ? (int) $m[1] : 0;
}

/** آمار از همه‌ی دفترچه‌های آیفونِ کارکرده (منتشرشده، موجود یا فروخته) */
function stlh_hr_stats(): array {
	$ids = get_posts( [
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1000,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => [ [ 'key' => 'stl_health', 'compare' => 'EXISTS' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery
		'tax_query'      => [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => [ 'iphone-second-hand' ], 'include_children' => true ] ], // phpcs:ignore WordPress.DB.SlowDBQuery
	] );
	$rows = [];
	foreach ( $ids as $id ) {
		$h   = stlh_health_data( (int) $id );
		$gen = stlh_hr_gen( (string) get_the_title( $id ) );
		if ( ! $h || ! $gen ) {
			continue;
		}
		$bad = $info = [];
		$batt_replaced = false;
		foreach ( $h['items'] as $it ) {
			if ( 'ok' === $it['t'] ) {
				continue;
			}
			if ( 'bad' === $it['t'] ) {
				$bad[] = $it['l'];
			} else {
				$info[] = $it['l'];
			}
			if ( str_contains( $it['l'], 'باتری' ) ) {
				$batt_replaced = true;
			}
		}
		$rows[] = [
			'gen'     => $gen,
			'battery' => $h['battery'] && $h['battery'] > 0 && $h['battery'] <= 100 ? (int) $h['battery'] : null,
			'cycles'  => $h['cycles'] ?: null,
			'issues'  => array_merge( $bad, $info ),
			'clean'   => ! $bad && ! $info,
			'batt_rp' => $batt_replaced,
			'at'      => (string) get_post_field( 'post_date_gmt', $id ),
		];
	}
	$n    = count( $rows );
	$bats = array_values( array_filter( array_column( $rows, 'battery' ) ) );
	sort( $bats );
	$pct  = static fn( int $part, int $all ) => $all ? (int) round( 100 * $part / $all ) : 0;
	$avg  = static fn( array $a ) => $a ? (int) round( array_sum( $a ) / count( $a ) ) : null;

	$freq = [];
	foreach ( $rows as $r ) {
		foreach ( array_unique( $r['issues'] ) as $l ) {
			$freq[ $l ] = ( $freq[ $l ] ?? 0 ) + 1;
		}
	}
	arsort( $freq );

	$groups = [];
	foreach ( $rows as $r ) {
		$groups[ $r['gen'] ][] = $r;
	}
	krsort( $groups );
	$gen_rows = [];
	foreach ( $groups as $g => $list ) {
		if ( count( $list ) < STLH_HR_MIN_GROUP ) {
			continue;
		}
		$gb         = array_values( array_filter( array_column( $list, 'battery' ) ) );
		$gen_rows[] = [
			'gen'   => $g,
			'n'     => count( $list ),
			'avg'   => $avg( $gb ),
			'clean' => $pct( count( array_filter( $list, static fn( $r ) => $r['clean'] ) ), count( $list ) ),
		];
	}
	$dates = array_filter( array_column( $rows, 'at' ) );
	sort( $dates );

	return [
		'n'         => $n,
		'n_batt'    => count( $bats ),
		'avg'       => $avg( $bats ),
		'median'    => $bats ? $bats[ intdiv( count( $bats ), 2 ) ] : null,
		'min'       => $bats ? $bats[0] : null,
		'p85'       => $pct( count( array_filter( $bats, static fn( $b ) => $b >= 85 ) ), count( $bats ) ),
		'clean'     => $pct( count( array_filter( $rows, static fn( $r ) => $r['clean'] ) ), $n ),
		'clean_n'   => count( array_filter( $rows, static fn( $r ) => $r['clean'] ) ),
		'batt_rp'   => count( array_filter( $rows, static fn( $r ) => $r['batt_rp'] ) ),
		'freq'      => array_slice( $freq, 0, 6, true ),
		'gens'      => $gen_rows,
		'gen_count' => count( $groups ),
		'since'     => $dates ? strtotime( $dates[0] . ' UTC' ) : 0,
	];
}

function stlh_hr_html(): string {
	$s  = stlh_hr_stats();
	$fa = static fn( $v ) => stlh_fa( (string) $v );
	$e  = static fn( $v ) => esc_html( (string) $v );
	$vars = [
		'{n}'     => $fa( $s['n'] ),
		'{از}'    => $s['since'] ? stlh_jalali( $s['since'] ) : '',
		'{تاریخ}' => stlh_jalali( time() ),
	];

	$o  = '<div class="stl-hr">';
	$o .= '<p class="stl-hr__lead">' . $e( stlh_label( 'hr_intro', $vars ) ) . '</p>';
	$o .= '<p class="stl-hr__date">' . $e( stlh_label( 'hr_updated', $vars ) ) . '</p>';

	if ( $s['n'] < STLH_HR_MIN_ALL ) {
		return $o . '<p class="stl-hr__box">' . $e( stlh_label( 'hr_collecting', $vars ) ) . '</p></div>';
	}

	// ─── عددهای کلی ───
	$tiles = [
		[ $fa( $s['n'] ), stlh_label( 'hr_t_n' ) ],
		[ $fa( $s['avg'] ) . '٪', stlh_label( 'hr_t_avg' ) ],
		[ $fa( $s['p85'] ) . '٪', stlh_label( 'hr_t_p85' ) ],
		[ $fa( $s['clean'] ) . '٪', stlh_label( 'hr_t_clean' ) ],
	];
	$o .= '<div class="stl-hr__tiles">';
	foreach ( $tiles as [ $num, $label ] ) {
		$o .= '<div class="stl-hr__tile"><b>' . $e( $num ) . '</b><span>' . $e( $label ) . '</span></div>';
	}
	$o .= '</div>';

	// ─── جمع‌بندیِ نقل‌شدنی (یک بند، با عدد) ───
	$o .= '<p class="stl-hr__answer">' . $e( stlh_label( 'hr_summary', $vars + [
		'{میانگین}' => $fa( $s['avg'] ),
		'{میانه}'   => $fa( $s['median'] ),
		'{کمترین}'  => $fa( $s['min'] ),
		'{سالم}'    => $fa( $s['clean_n'] ),
		'{باتری}'   => $fa( $s['batt_rp'] ),
	] ) ) . '</p>';

	// ─── رایج‌ترین موردها ───
	if ( $s['freq'] ) {
		$o .= '<h2>' . $e( stlh_label( 'hr_h_issues' ) ) . '</h2><ul class="stl-hr__bars">';
		$max = max( $s['freq'] );
		foreach ( $s['freq'] as $label => $count ) {
			$w   = (int) round( 100 * $count / max( 1, $max ) );
			$o  .= '<li><span>' . $e( $label ) . '</span><i dir="ltr"><i style="width:' . $w . '%"></i></i><b>' . $e( stlh_label( 'hr_of', [ '{x}' => $fa( $count ), '{n}' => $fa( $s['n'] ) ] ) ) . '</b></li>';
		}
		$o .= '</ul>';
		if ( $s['batt_rp'] ) {
			$o .= '<p class="stl-hr__box is-warn">' . $e( stlh_label( 'hr_battery_lesson', [ '{باتری}' => $fa( $s['batt_rp'] ), '{n}' => $fa( $s['n'] ) ] ) ) . '</p>';
		}
	}

	// ─── هر نسل ───
	$o .= '<h2>' . $e( stlh_label( 'hr_h_gens' ) ) . '</h2>';
	if ( $s['gens'] ) {
		$o .= '<table class="stl-hr__table"><thead><tr><th>' . $e( stlh_label( 'hr_c_gen' ) ) . '</th><th>' . $e( stlh_label( 'hr_c_n' ) ) . '</th><th>' . $e( stlh_label( 'hr_c_avg' ) ) . '</th><th>' . $e( stlh_label( 'hr_c_clean' ) ) . '</th></tr></thead><tbody>';
		foreach ( $s['gens'] as $g ) {
			$o .= '<tr><td>' . $e( stlh_label( 'hr_gen_name', [ '{نسل}' => $fa( $g['gen'] ) ] ) ) . '</td><td>' . $fa( $g['n'] ) . '</td><td>' . ( null === $g['avg'] ? '—' : $fa( $g['avg'] ) . '٪' ) . '</td><td>' . $fa( $g['clean'] ) . '٪</td></tr>';
		}
		$o .= '</tbody></table>';
	}
	$o .= '<p class="stl-hr__note">' . $e( stlh_label( 'hr_gens_note', [ '{حداقل}' => $fa( STLH_HR_MIN_GROUP ) ] ) ) . '</p>';

	// ─── روش و پیوندها ───
	$o .= '<h2>' . $e( stlh_label( 'hr_h_method' ) ) . '</h2><p>' . $e( stlh_label( 'hr_method' ) ) . '</p>';
	$links = [
		[ home_url( '/product-category/cellphone/%d8%a2%db%8c%d9%81%d9%88%d9%86/iphone-second-hand/' ), stlh_label( 'hr_l_shop' ) ],
		[ home_url( '/iphone-battery-health/' ), stlh_label( 'hr_l_battery' ) ],
		[ home_url( '/used-iphone-test/' ), stlh_label( 'hr_l_test' ) ],
	];
	$term = get_term_by( 'slug', 'iphone-second-hand', 'product_cat' );
	if ( $term ) {
		$link = get_term_link( $term );
		if ( is_string( $link ) ) {
			$links[0][0] = $link;
		}
	}
	foreach ( [ 1 => 'iphone-battery-health', 2 => 'used-iphone-test' ] as $i => $slug ) {
		$p = get_page_by_path( $slug, OBJECT, 'post' );
		if ( $p ) {
			$links[ $i ][0] = (string) get_permalink( $p );
		}
	}
	$o .= '<ul class="stl-hr__links">';
	foreach ( $links as [ $url, $label ] ) {
		$o .= '<li><a href="' . esc_url( $url ) . '">' . $e( $label ) . '</a></li>';
	}
	$o .= '</ul>';

	return $o . '</div>' . stlh_hr_schema( $s );
}

/** داده‌ی ساختاریافته‌ی `Dataset` — همان عددهای روی صفحه */
function stlh_hr_schema( array $s ): string {
	$url  = stlh_hr_url();
	$data = [
		'@context'          => 'https://schema.org',
		'@type'             => 'Dataset',
		'name'              => stlh_label( 'hr_title' ),
		'description'       => stlh_label( 'hr_seo_desc' ),
		'url'               => $url,
		'inLanguage'        => 'fa-IR',
		'isAccessibleForFree' => true,
		'dateModified'      => gmdate( 'c' ),
		'creator'           => [ '@type' => 'MobilePhoneStore', '@id' => home_url( '/#store' ), 'name' => get_bloginfo( 'name' ) ?: 'استوک لند', 'url' => home_url( '/' ) ],
		'spatialCoverage'   => 'قم، ایران',
		'keywords'          => [ 'سلامت باتری آیفون', 'آیفون کارکرده', 'دفترچه سلامت', 'قطعه تعویضی آیفون' ],
		'variableMeasured'  => [
			[ '@type' => 'PropertyValue', 'name' => 'تعداد گوشیِ بررسی‌شده', 'value' => $s['n'] ],
			[ '@type' => 'PropertyValue', 'name' => 'میانگین سلامت باتری', 'value' => $s['avg'], 'unitText' => 'درصد' ],
			[ '@type' => 'PropertyValue', 'name' => 'سهم گوشی با باتری ۸۵٪ و بالاتر', 'value' => $s['p85'], 'unitText' => 'درصد' ],
			[ '@type' => 'PropertyValue', 'name' => 'سهم گوشی بی هیچ ایراد یا قطعه‌ی تعویضی', 'value' => $s['clean'], 'unitText' => 'درصد' ],
			[ '@type' => 'PropertyValue', 'name' => 'گوشی با باتری تعویضی یا غیراصل', 'value' => $s['batt_rp'] ],
		],
	];
	if ( $s['since'] ) {
		$data['temporalCoverage'] = gmdate( 'Y-m-d', $s['since'] ) . '/..';
	}
	return '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
}

add_shortcode( 'stl_health_report', static function (): string {
	return stlh_cached( 'health-report', 'stlh_hr_html' );
} );

// دفترچه در متای محصول است؛ متا از REST حسابداری بی `woocommerce_update_product` هم عوض می‌شود
foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
	add_action( $hook, static function ( $mid, $object_id, string $key ): void {
		if ( 'stl_health' === $key || 'stlh_health_note' === $key ) {
			stlh_cache_flush();
		}
	}, 10, 3 );
}
// کشِ LiteSpeed این برگه را هم با نسل پاک کند
add_action( 'stlh_cache_flushed', static function (): void {
	if ( $url = stlh_hr_url() ) {
		do_action( 'litespeed_purge_url', $url );
	}
} );

function stlh_hr_css(): string {
	return ''
		. '.stl-hr{direction:rtl;max-width:860px;margin:0 auto;color:#3f4064;font-size:15px;line-height:1.9;box-sizing:border-box}'
		. '.stl-hr *{box-sizing:border-box}.stl-hr h2{font-size:18px;font-weight:800;color:#23254e;margin:28px 0 12px}'
		. '.stl-hr__lead{font-size:16px;color:#23254e;margin:0 0 4px}.stl-hr__date{font-size:12.5px;color:#81858b;margin:0 0 16px}'
		. '.stl-hr__tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0 0 16px}'
		. '.stl-hr__tile{padding:14px 12px;border-radius:16px;background:#f6f7f9;text-align:center}'
		. '.stl-hr__tile b{display:block;font-size:26px;font-weight:900;color:#0e9f6e;line-height:1.3}.stl-hr__tile span{display:block;font-size:12.5px;color:#5b5f6b}'
		. '.stl-hr__answer{padding:14px 16px;border-radius:16px;background:#eef8f4;color:#23254e;margin:0 0 8px}'
		. '.stl-hr__bars{list-style:none;margin:0;padding:0;display:grid;gap:8px}'
		. '.stl-hr__bars li{display:grid;grid-template-columns:minmax(0,9rem) minmax(0,1fr) auto;align-items:center;gap:10px;margin:0;font-size:13.5px}'
		. '.stl-hr__bars li span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.stl-hr__bars li b{font-size:12.5px;color:#5b5f6b;font-weight:600;white-space:nowrap}'
		. '.stl-hr__bars li>i{display:block;height:8px;border-radius:8px;background:#eef0f3;overflow:hidden}.stl-hr__bars li>i>i{display:block;height:100%;border-radius:8px;background:#e8a317}'
		. '.stl-hr__box{padding:12px 16px;border-radius:14px;background:#f6f7f9;margin:12px 0 0}.stl-hr__box.is-warn{background:#fff4e0;color:#7a4a00}'
		. '.stl-hr__table{width:100%;border-collapse:collapse;font-size:14px}.stl-hr__table th,.stl-hr__table td{padding:10px 8px;border-bottom:1px solid #eef0f3;text-align:center}'
		. '.stl-hr__table th{font-weight:800;color:#23254e;background:#f6f7f9}.stl-hr__table td:first-child,.stl-hr__table th:first-child{text-align:right}'
		. '.stl-hr__note{font-size:12.5px;color:#81858b;margin:8px 0 0}'
		. '.stl-hr__links{display:flex;flex-wrap:wrap;gap:8px;list-style:none;margin:0;padding:0}.stl-hr__links li{margin:0}'
		. '.stl-hr__links a{display:inline-block;padding:8px 14px;border-radius:999px;background:#f1f6fe;color:#1f6fd6;font-weight:700;font-size:13.5px;text-decoration:none}'
		. '@media (max-width:767px){.stl-hr{padding:0 16px}}'
		. '@media (max-width:640px){.stl-hr{font-size:14.5px}.stl-hr__tiles{grid-template-columns:repeat(2,minmax(0,1fr))}.stl-hr__tile b{font-size:22px}.stl-hr__bars li{grid-template-columns:minmax(0,7rem) minmax(0,1fr) auto;gap:8px}.stl-hr__table{font-size:13px}.stl-hr__table th,.stl-hr__table td{padding:8px 4px}}'
		. '';
}

add_action( 'wp_head', static function (): void {
	$id = stlh_hr_page_id();
	if ( $id && is_page( $id ) ) {
		echo '<style id="stlh-hr-css">' . stlh_hr_css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSSِ ثابتِ خودمان
	}
}, 20 );

/** پیوندِ کوچک زیرِ هر دفترچه در صفحه‌ی محصول */
add_action( 'woocommerce_single_product_end', static function (): void {
	$id  = get_the_ID();
	$url = stlh_hr_url();
	if ( $id && $url && stlh_health_data( (int) $id ) ) {
		echo '<p class="stl-hc-link"><a href="' . esc_url( $url ) . '">' . esc_html( stlh_label( 'hr_product_link' ) ) . '</a></p>'
			. '<style>.stl-hc-link{direction:rtl;margin:-2px 0 10px;font-size:12px}.stl-hc-link a{color:#19a4c3;font-weight:700;text-decoration:none}@media (max-width:767px){.stl-hc-link{margin:-2px 10px 10px}}</style>';
	}
}, 100 );

/** خطِ `llms.txt` */
add_filter( 'stlh_llms_extra', static function ( array $lines ): array {
	if ( $url = stlh_hr_url() ) {
		$lines[] = '- ' . stlh_label( 'hr_title' ) . ' (آمارِ زنده از دفترچه‌ی سلامتِ همه‌ی گوشی‌های کارکرده: سلامت باتری، قطعه‌ی تعویضی، به تفکیک نسل): ' . $url;
	}
	return $lines;
} );
