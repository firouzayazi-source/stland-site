<?php
/**
 * ابزارهای آیفون: «کارشناسی قیمت»، «سلامت آیفون»، «مقایسه دو آیفون» — صاحب فروشگاه، ۶ آذر ۱۴۰۵: «عینِ همان سه فیچرِ
 * پروژه‌ی ربات (stockland-bot) را با زبانِ طراحیِ خودمان این‌جا بیاور؛ آن‌جا بمانند، این‌جا هم اضافه شوند… نگه‌داری و
 * تغییرِ همه‌چیز داخلِ Novo باشد».
 *
 * از ربات چه آمد (عیناً): موتورِ قیمت (`pricing_engine.price`)، امتیاز و حکم (`scoring_engine`)، متنِ گزارش (`report.py`)،
 * موتورِ سلامت (`iphone_health/engine.compute_report`) و پلِ سلامت ← قیمت (`derive_valuation_prefill`)، مقایسه‌ی مشخصات،
 * و ظاهر/مراحلِ مینی‌اپ (`assets/ipt/ipt.js` از `app/app.js`، با همان متن‌ها). داده‌های پیش‌فرض در `assets/ipt/data/*.json`
 * (کاتالوگِ ۵۴ مدل، قیمت‌های پایه‌ی ۱۵ آبانِ صاحب فروشگاه، ضریب‌ها، ۴۳ تستِ سلامت، مشخصاتِ ۲۴ مدل).
 * هوشِ مصنوعی نیامد: در ربات هم پیش‌فرض خاموش است و نتیجه بی آن کامل است.
 *
 * ⛔ مالکیتِ داده: قیمت‌ها، ضریب‌ها، تست‌ها و مشخصات را Novo می‌فرستد (`POST stland/v1/ipt/config`، قرارداد در
 * `docs/novo-iphone-tools.md`)؛ سایت فقط می‌خواند و پیش‌فرض‌ها فقط تا رسیدنِ اولین ارسال‌اند. درخواست‌های فروش، کارشناسی‌ها
 * و گزارش‌های سلامت را Novo با GET می‌خواند.
 *
 * تفاوت‌های عمدی با ربات: یک ردیفِ قیمت برای هر «مدل + حافظه» (بقیه‌ی ابعاد درصدی — همان «ساده»ی مشخصات)، پیشنهادِ نقدی =
 * درصدی از قیمتِ منصفانه (`buy_pct`، صاحب فروشگاه)، حافظه‌ی بی قیمت در فهرست نمی‌آید (بن‌بست نشود)، مالکیتِ گزارش با شناسه‌ی
 * دستگاه در مرورگر (سایت حسابِ تلگرامی ندارد)، و درخواستِ فروش بی ثبت‌نام (فیلدِ پنهان و سقفِ آی‌پی).
 */
defined( 'ABSPATH' ) || exit;

const STLH_IPT_OPT      = 'stlh_ipt_cfg';
const STLH_IPT_VAL_PT   = 'stlh_ipt_val';
const STLH_IPT_LEAD_PT  = 'stlh_ipt_lead';
const STLH_IPT_HR_PT    = 'stlh_ipt_hr';
const STLH_IPT_NS       = 'stland/v1';
const STLH_IPT_PAGES    = [
	'price'   => [ 'stlh_page_ipt_price', 'iphone-price', 'ipt_price_title', '[stl_ipt tool="price"]' ],
	'health'  => [ 'stlh_page_ipt_health', 'iphone-health', 'ipt_health_title', '[stl_ipt tool="health"]' ],
	'compare' => [ 'stlh_page_ipt_compare', 'iphone-compare', 'ipt_compare_title', '[stl_ipt tool="compare"]' ],
];

/* ───── داده: پیش‌فرض از JSON + ارسالِ Novo ───── */

function stlh_ipt_json( string $name ): array {
	static $memo = [];
	if ( ! isset( $memo[ $name ] ) ) {
		$raw            = file_get_contents( STLH_DIR . 'assets/ipt/data/' . $name . '.json' );
		$memo[ $name ]  = is_string( $raw ) ? (array) json_decode( $raw, true ) : [];
	}
	return $memo[ $name ];
}

/** پیکربندیِ مؤثر: هر کلیدی که Novo فرستاده جای پیش‌فرض را می‌گیرد */
function stlh_ipt_cfg(): array {
	static $cfg = null;
	if ( null !== $cfg ) {
		return $cfg;
	}
	$push  = (array) get_option( STLH_IPT_OPT, [] );
	$mod   = stlh_ipt_json( 'iphone_models' );
	$coef  = stlh_ipt_json( 'valuation_coefficients' );
	$hi    = stlh_ipt_json( 'health_items' );
	$sp    = stlh_ipt_json( 'specs' );
	$dp    = stlh_ipt_json( 'default_prices' );
	$cfg   = [
		'models'           => $push['models'] ?? $mod['models'],
		'parts'            => $push['parts'] ?? $mod['parts'],
		'coefficients'     => $push['coefficients'] ?? $coef['coefficients'],
		'score_weights'    => array_merge( (array) $coef['score_weights'], (array) ( $push['score_weights'] ?? [] ) ),
		'grade_stop_keys'  => $coef['grade_stop_calc_keys'],
		'grade_special'    => $coef['grade_special_keys'],
		'prices'           => $push['prices'] ?? stlh_ipt_default_prices( $mod['models'], $dp['prices'] ),
		'unregistered_pct' => $push['unregistered_pct'] ?? array_column( array_filter( $dp['prices'], static fn( $r ) => null !== $r['unregistered_pct'] ), 'unregistered_pct', 'model' ),
		'modifiers'        => (array) ( $push['modifiers'] ?? [] ),
		'settings'         => array_merge( [ 'fx_sensitivity' => 0.5, 'usd_rate' => 0, 'buy_pct' => 90, 'price' => true, 'health' => true, 'compare' => true ], (array) ( $push['settings'] ?? [] ) ),
		'health'           => array_merge( $hi, (array) ( $push['health'] ?? [] ) ),
		'spec_fields'      => $push['spec_fields'] ?? $sp['fields'],
		'specs'            => array_merge( (array) $sp['specs'], (array) ( $push['specs'] ?? [] ) ),
		'version'          => (string) ( $push['version'] ?? '' ),
		'updated'          => (string) ( $push['updated'] ?? '' ),
	];
	return $cfg;
}

/**
 * قیمتِ پایه‌ی پیش‌فرض (جدولِ ۱۵ آبانِ صاحب فروشگاه در ربات): «دستگاه در حد آک، بدون باز شدگی، بالاترین باتری، با رجیستر»
 * برای حافظه‌ی مرجع — پرو و پرو مکسِ نسلِ ۱۳ به بالا ۲۵۶، بقیه کوچک‌ترین. حافظه‌های دیگر تا Novo نفرستد قیمت ندارند.
 */
function stlh_ipt_default_prices( array $models, array $table ): array {
	$by   = array_column( $models, null, 'name' );
	$rows = [];
	foreach ( $table as $r ) {
		$m = $by[ $r['model'] ] ?? null;
		if ( ! $m || empty( $m['storages'] ) ) {
			continue;
		}
		$rows[] = [ 'model' => $m['name'], 'storage' => stlh_ipt_anchor_storage( $m ), 'base' => (int) $r['registered_toman'] ];
	}
	return $rows;
}

function stlh_ipt_gb( string $label ): int {
	if ( ! preg_match( '/(\d+)\s*(GB|TB)/i', $label, $m ) ) {
		return 0;
	}
	return (int) $m[1] * ( 'TB' === strtoupper( $m[2] ) ? 1024 : 1 );
}

function stlh_ipt_anchor_storage( array $m ): string {
	$st = array_values( array_filter( (array) $m['storages'], static fn( $s ) => stlh_ipt_gb( $s ) > 0 ) );
	usort( $st, static fn( $a, $b ) => stlh_ipt_gb( $a ) <=> stlh_ipt_gb( $b ) );
	if ( ! $st ) {
		return '';
	}
	if ( preg_match( '/iPhone (\d+) Pro/', $m['name'], $g ) && (int) $g[1] >= 13 && in_array( '256GB', $st, true ) ) {
		return '256GB';
	}
	return $st[0];
}

/** کاتالوگ با شناسه‌ی ثابت (ترتیبِ فهرست) و سری؛ سری‌ها جدیدترین اول */
function stlh_ipt_catalog(): array {
	static $cat = null;
	if ( null !== $cat ) {
		return $cat;
	}
	$models = [];
	$series = [];
	foreach ( array_values( stlh_ipt_cfg()['models'] ) as $i => $m ) {
		$g = (string) ( $m['series_group'] ?? '' ) ?: 'سایر مدل‌ها';
		if ( ! isset( $series[ $g ] ) ) {
			$series[ $g ] = [ 'id' => count( $series ) + 1, 'name' => $g, 'image_url' => '', 'top' => 0 ];
		}
		$series[ $g ]['top'] = max( $series[ $g ]['top'], (int) ( $m['sort_order'] ?? $i ) );
		$models[ $i + 1 ]    = [
			'id'             => $i + 1,
			'name'           => (string) $m['name'],
			'series'         => (string) ( $m['year'] ?? '' ),
			'series_id'      => $series[ $g ]['id'],
			'series_name'    => $g,
			'sort_order'     => (int) ( $m['sort_order'] ?? $i ),
			'dual_sim_parts' => (string) ( $m['dual_sim_parts'] ?? '' ),
			'esim_only'      => (int) ( $m['esim_only'] ?? 0 ),
			'features'       => implode( ',', (array) ( $m['features'] ?? [] ) ),
			'storages'       => array_values( (array) ( $m['storages'] ?? [] ) ),
			'colors'         => array_values( (array) ( $m['colors'] ?? [] ) ),
			'image_url'      => '',
		];
	}
	uasort( $series, static fn( $a, $b ) => $b['top'] <=> $a['top'] );
	$cat = [ 'models' => $models, 'series' => array_values( array_map( static fn( $s ) => array_diff_key( $s, [ 'top' => 1 ] ), $series ) ) ];
	return $cat;
}

function stlh_ipt_model( int $id ): ?array {
	return stlh_ipt_catalog()['models'][ $id ] ?? null;
}

/** ردیف‌های قیمت با شناسه: [شناسه => ردیف] */
function stlh_ipt_prices(): array {
	static $rows = null;
	if ( null !== $rows ) {
		return $rows;
	}
	$ids  = array_column( stlh_ipt_catalog()['models'], 'id', 'name' );
	$rows = [];
	foreach ( array_values( (array) stlh_ipt_cfg()['prices'] ) as $i => $r ) {
		$mid = (int) ( $ids[ (string) ( $r['model'] ?? '' ) ] ?? 0 );
		if ( ! $mid || (int) ( $r['base'] ?? 0 ) <= 0 || ( isset( $r['active'] ) && ! $r['active'] ) ) {
			continue;
		}
		$rows[ $i + 1 ] = [
			'id'       => $i + 1,
			'model_id' => $mid,
			'storage'  => (string) $r['storage'],
			'base'     => (int) $r['base'],
			'fx_ref'   => (float) ( $r['fx_ref'] ?? 0 ),
			'demand'   => (float) ( $r['demand'] ?? 0 ),
			'market'   => (float) ( $r['market'] ?? 0 ),
		];
	}
	return $rows;
}

function stlh_ipt_resolve( int $model_id, string $storage ): ?array {
	foreach ( stlh_ipt_prices() as $r ) {
		if ( $r['model_id'] === $model_id && strcasecmp( $r['storage'], $storage ) === 0 ) {
			return $r;
		}
	}
	return null;
}

/** ضریب‌های فعال: [دسته => [کلید => ردیف]] به ترتیب */
function stlh_ipt_coefs(): array {
	static $c = null;
	if ( null === $c ) {
		$c    = [];
		$list = array_filter( (array) stlh_ipt_cfg()['coefficients'], static fn( $r ) => ! isset( $r['active'] ) || $r['active'] );
		usort( $list, static fn( $a, $b ) => ( (int) ( $a['sort_order'] ?? 0 ) ) <=> ( (int) ( $b['sort_order'] ?? 0 ) ) );
		foreach ( $list as $r ) {
			$c[ (string) $r['category'] ][ (string) $r['option_key'] ] = $r;
		}
	}
	return $c;
}

function stlh_r1000( float $x ): int {
	return (int) round( $x / 1000, 0, PHP_ROUND_HALF_EVEN ) * 1000;
}

/** نردبانِ درصد (رنگ/پارت/سری اصالت/رجیستری): مدل ← سری ← سراسری ← جدولِ ضریب‌ها */
function stlh_ipt_modifier( string $dim, string $key, array $model ): float {
	$cfg = stlh_ipt_cfg();
	foreach ( [ [ 'model', $model['name'] ], [ 'series', $model['series_name'] ], [ 'global', '' ] ] as [ $scope, $name ] ) {
		foreach ( $cfg['modifiers'] as $m ) {
			if ( ( $m['dim'] ?? '' ) === $dim && ( $m['key'] ?? '' ) === $key && ( $m['scope'] ?? 'global' ) === $scope && ( 'global' === $scope || ( $m['name'] ?? '' ) === $name ) ) {
				return (float) $m['pct'];
			}
		}
		// «بدون رجیستر» در جدولِ قیمتِ صاحب فروشگاه درصدِ مخصوصِ هر مدل است
		if ( 'model' === $scope && 'registry' === $dim && 'registry_unregistered' === $key && isset( $cfg['unregistered_pct'][ $model['name'] ] ) ) {
			return (float) $cfg['unregistered_pct'][ $model['name'] ];
		}
	}
	if ( in_array( $dim, [ 'grade', 'registry' ], true ) ) {
		return (float) ( stlh_ipt_coefs()[ $dim ][ $key ]['percent'] ?? 0 );
	}
	if ( 'color' === $dim ) {
		foreach ( $model['colors'] as $c ) {
			if ( ( $c['name'] ?? '' ) === $key ) {
				return (float) ( $c['price_percent'] ?? 0 );
			}
		}
	}
	return 0.0;
}

/** موتورِ قیمت — `pricing_engine.price` عیناً (درصدها جمع می‌شوند، نه ضرب) */
function stlh_ipt_price( array $model, array $cap, array $sel, string $part, string $color ): array {
	$cfg    = stlh_ipt_cfg();
	$base   = $cap['base'];
	$rate   = (float) $cfg['settings']['usd_rate'];
	$sens   = max( 0.0, (float) $cfg['settings']['fx_sensitivity'] );
	$fx     = ( $cap['fx_ref'] > 0 && $rate > 0 ) ? ( $rate - $cap['fx_ref'] ) / $cap['fx_ref'] * 100 * $sens : 0.0;
	$market = $cap['market'];
	$demand = $cap['demand'];
	$amt    = static fn( float $p ): int => stlh_r1000( $base * $p / 100 );
	$contrib = [
		[ 'label' => 'نرخ ارز', 'pct' => round( $fx, 2 ), 'amount' => $amt( $fx ) ],
		[ 'label' => 'دادهٔ بازار Stland', 'pct' => round( $market, 2 ), 'amount' => $amt( $market ) ],
		[ 'label' => 'عرضه و تقاضای مدل', 'pct' => round( $demand, 2 ), 'amount' => $amt( $demand ) ],
	];
	$cond = 0.0;
	if ( '' !== $part ) {
		$p = stlh_ipt_modifier( 'part', $part, $model );
		$label = '';
		foreach ( (array) $cfg['parts'] as $pp ) {
			if ( ( $pp['code'] ?? '' ) === $part ) {
				$label = (string) $pp['label'];
			}
		}
		$cond     += $p;
		$contrib[] = [ 'label' => 'پارت (' . ( $label ?: $part ) . ')', 'pct' => $p, 'amount' => $amt( $p ) ];
	}
	if ( '' !== $color ) {
		$p         = stlh_ipt_modifier( 'color', $color, $model );
		$cond     += $p;
		$contrib[] = [ 'label' => 'رنگ (' . $color . ')', 'pct' => $p, 'amount' => $amt( $p ) ];
	}
	$coefs = stlh_ipt_coefs();
	foreach ( $sel as $cat => $keys ) {
		foreach ( (array) $keys as $k ) {
			$c = $coefs[ $cat ][ $k ] ?? null;
			if ( ! $c ) {
				continue;
			}
			$p         = in_array( $cat, [ 'grade', 'registry' ], true ) ? stlh_ipt_modifier( $cat, $k, $model ) : (float) $c['percent'];
			$cond     += $p;
			$contrib[] = [ 'label' => (string) $c['option_label'], 'category' => $cat, 'pct' => $p, 'amount' => $amt( $p ) ];
		}
	}
	$wide  = $fx + $market + $demand;
	$total = $wide + $cond;
	$fair  = max( 0, stlh_r1000( $base * ( 1 + $total / 100 ) ) );
	return [
		'market_price'  => max( 0, stlh_r1000( $base * ( 1 + $wide / 100 ) ) ),
		'fair_price'    => $fair,
		// پیشنهادِ نقدی = درصدی از قیمتِ منصفانه (صاحب فروشگاه، ۶ آذر؛ در ربات «مرجعِ خرید» جدا بود)
		'buy_price'     => max( 0, stlh_r1000( $fair * (float) $cfg['settings']['buy_pct'] / 100 ) ),
		'fx_pct'        => round( $fx, 2 ),
		'condition_pct' => round( $cond, 2 ),
		'total_pct'     => round( $total, 2 ),
		'fx_rate'       => $rate,
		'contributions' => $contrib,
	];
}

/** امتیاز ۰ تا ۱۰۰ و حکم — `scoring_engine` عیناً */
function stlh_ipt_score( array $sel, ?bool $features_ok ): array {
	$w     = stlh_ipt_cfg()['score_weights'];
	$coefs = stlh_ipt_coefs();
	$total = 0.0;
	$bd    = [];
	foreach ( $w as $cat => $weight ) {
		if ( 'features' === $cat || empty( $coefs[ $cat ] ) ) {
			continue;
		}
		$worst  = min( array_map( static fn( $r ) => (float) $r['percent'], $coefs[ $cat ] ) );
		$chosen = array_values( array_filter( array_map( static fn( $k ) => $coefs[ $cat ][ $k ] ?? null, (array) ( $sel[ $cat ] ?? [] ) ) ) );
		$frac   = 0.0;
		foreach ( $chosen as $r ) {
			$p     = (float) $r['percent'];
			$frac += ( $p >= 0 || $worst >= 0 ) ? 0 : min( 1, $p / $worst );
		}
		$d      = min( 1, $frac ) * (float) $weight;
		$total += $d;
		$bd[]   = [ 'category' => $cat, 'label' => implode( '، ', array_column( $chosen, 'option_label' ) ) ?: '—', 'deduction' => round( $d, 1 ) ];
	}
	if ( false === $features_ok ) {
		$d      = (float) ( $w['features'] ?? 0 ) * 0.5;
		$total += $d;
		$bd[]   = [ 'category' => 'features', 'label' => 'برخی امکانات مشکل دارند', 'deduction' => round( $d, 1 ) ];
	}
	$score = max( 0, min( 100, (int) round( 100 - $total, 0, PHP_ROUND_HALF_EVEN ) ) );
	[ $e, $t ] = $score >= 80 ? [ '🟢', 'خرید عالی' ] : ( $score >= 50 ? [ '🟡', 'خرید مناسب با مذاکره' ] : [ '🔴', 'خرید توصیه نمی‌شود' ] );
	return [ 'score' => $score, 'breakdown' => $bd, 'verdict_emoji' => $e, 'verdict_text' => $t ];
}

/** متنِ گزارش — `report.py` عیناً (رقمِ لاتین با جداکننده‌ی ٬) */
function stlh_ipt_report( array $contrib, int $score ): string {
	$f    = static fn( int $n ): string => str_replace( ',', '٬', number_format( $n ) );
	$neg  = array_values( array_filter( $contrib, static fn( $c ) => $c['amount'] < 0 ) );
	$pos  = array_values( array_filter( $contrib, static fn( $c ) => $c['amount'] > 0 ) );
	usort( $neg, static fn( $a, $b ) => $a['amount'] <=> $b['amount'] );
	usort( $pos, static fn( $a, $b ) => $b['amount'] <=> $a['amount'] );
	$neg  = array_slice( $neg, 0, 3 );
	$pos  = array_slice( $pos, 0, 2 );
	$out  = [];
	if ( $neg ) {
		$out[] = 'این دستگاه به دلیل ' . implode( '، ', array_column( $neg, 'label' ) ) . ' حدود ' . $f( abs( array_sum( array_column( $neg, 'amount' ) ) ) ) . ' تومان کمتر از یک نمونهٔ کامل ارزش دارد.';
	}
	if ( $pos ) {
		$out[] = 'از طرفی به دلیل ' . implode( '، ', array_column( $pos, 'label' ) ) . ' حدود ' . $f( array_sum( array_column( $pos, 'amount' ) ) ) . ' تومان به ارزش دستگاه اضافه شده.';
	}
	if ( ! $neg && ! $pos ) {
		$out[] = 'این دستگاه در شرایط استاندارد بازار قرار داره، بدون افت یا افزایش قابل‌توجه.';
	}
	$out[] = 'امتیاز Stland این دستگاه ' . $score . ' از ۱۰۰ است.';
	return implode( ' ', $out );
}

/* ───── سلامت ───── */

function stlh_ipt_health_data(): array {
	static $d = null;
	if ( null !== $d ) {
		return $d;
	}
	$h    = stlh_ipt_cfg()['health'];
	$cats = [];
	foreach ( array_values( (array) $h['categories'] ) as $i => $c ) {
		$cats[ (string) $c['category_key'] ] = [ 'id' => $i + 1, 'category_key' => (string) $c['category_key'], 'title' => (string) $c['title'], 'icon' => (string) ( $c['icon'] ?? '' ), 'sort_order' => (int) ( $c['sort_order'] ?? $i ) ];
	}
	$tests = [];
	foreach ( array_values( (array) $h['tests'] ) as $i => $t ) {
		if ( ( isset( $t['active'] ) && ! $t['active'] ) || ! isset( $cats[ (string) $t['category_key'] ] ) ) {
			continue;
		}
		$tests[ $i + 1 ] = [
			'id'               => $i + 1,
			'category_id'      => $cats[ (string) $t['category_key'] ]['id'],
			'category_key'     => (string) $t['category_key'],
			'test_key'         => (string) $t['test_key'],
			'title'            => (string) $t['title'],
			'guide_text'       => (string) ( $t['guide_text_html'] ?? '' ),
			'requires_feature' => (string) ( $t['requires_feature'] ?? '' ),
			'excludes_feature' => (string) ( $t['excludes_feature'] ?? '' ),
			'is_dealbreaker'   => ! empty( $t['is_dealbreaker'] ),
			'weight'           => (float) ( $t['weight'] ?? 1 ),
			'quick_test'       => ! empty( $t['quick_test'] ),
			'sort_order'       => (int) ( $t['sort_order'] ?? $i ),
			'web_test_key'     => (string) ( $t['web_test_key'] ?? '' ),
			'schematic_part'   => (string) ( $t['schematic_part'] ?? '' ),
		];
	}
	// ترتیبِ تست‌ها: فصل، بعد ترتیبِ خودِ تست
	uasort( $tests, static fn( $a, $b ) => [ $cats[ $a['category_key'] ]['sort_order'], $a['sort_order'] ] <=> [ $cats[ $b['category_key'] ]['sort_order'], $b['sort_order'] ] );
	$d = [ 'categories' => $cats, 'tests' => $tests, 'raw' => $h ];
	return $d;
}

/** تست‌های مربوط به یک مدل و حالت */
function stlh_ipt_applicable( array $model, string $mode ): array {
	$tags = array_flip( array_filter( explode( ',', $model['features'] ) ) );
	return array_filter( stlh_ipt_health_data()['tests'], static function ( $t ) use ( $tags, $mode ) {
		if ( '' !== $t['requires_feature'] && ! isset( $tags[ $t['requires_feature'] ] ) ) {
			return false;
		}
		if ( '' !== $t['excludes_feature'] && isset( $tags[ $t['excludes_feature'] ] ) ) {
			return false;
		}
		return 'quick' !== $mode || $t['quick_test'];
	} );
}

/** `engine.compute_report` عیناً + پلِ قیمت (`derive_valuation_prefill`) */
function stlh_ipt_health_compute( array $rep ): array {
	$hd      = stlh_ipt_health_data();
	$model   = stlh_ipt_model( (int) $rep['model_id'] );
	$app     = $model ? stlh_ipt_applicable( $model, $rep['mode'] ) : [];
	$answers = (array) $rep['answers'];
	$by_key  = [];
	$num     = 0.0;
	$den     = 0.0;
	$deal    = [];
	$ev      = [ 'auto' => 0, 'assisted' => 0, 'manual' => 0 ];
	$parts   = [];
	$cats    = [];
	$rank    = [ 'ok' => 0, 'unknown' => 1, 'issue' => 2 ];
	$alist   = [];
	foreach ( $answers as $tid => $a ) {
		$t = $hd['tests'][ (int) $tid ] ?? null;
		if ( ! $t ) {
			continue;
		}
		$ans                    = (string) $a['answer'];
		$by_key[ $t['test_key'] ] = $ans;
		$alist[]                = [ 'test_id' => $t['id'], 'test_key' => $t['test_key'], 'title' => $t['title'], 'answer' => $ans, 'evidence' => (string) ( $a['evidence'] ?? 'manual' ) ];
		if ( 'ok' === $ans ) {
			$num += $t['weight'];
		}
		if ( in_array( $ans, [ 'ok', 'issue' ], true ) ) {
			$den += $t['weight'];
		}
		if ( $t['is_dealbreaker'] && 'issue' === $ans ) {
			$deal[] = [ 'test_key' => $t['test_key'], 'title' => $t['title'] ];
		}
		$e = (string) ( $a['evidence'] ?? 'manual' );
		$ev[ isset( $ev[ $e ] ) ? $e : 'manual' ]++;
		if ( '' !== $t['schematic_part'] && ( ! isset( $parts[ $t['schematic_part'] ] ) || $rank[ $ans ] > $rank[ $parts[ $t['schematic_part'] ] ] ) ) {
			$parts[ $t['schematic_part'] ] = $ans;
		}
		$ck = $t['category_key'];
		$cats[ $ck ] ??= [ 'category_key' => $ck, 'title' => $hd['categories'][ $ck ]['title'], 'icon' => $hd['categories'][ $ck ]['icon'], 'ok' => 0, 'issue' => 0, 'unknown' => 0, 'sort' => $hd['categories'][ $ck ]['sort_order'] ];
		$cats[ $ck ][ $ans ] = ( $cats[ $ck ][ $ans ] ?? 0 ) + 1;
	}
	uasort( $cats, static fn( $a, $b ) => $a['sort'] <=> $b['sort'] );
	$answered = count( array_intersect_key( $answers, $app ) );
	$total    = count( $app );
	$score    = $den > 0 ? (int) round( 100 * $num / $den, 0, PHP_ROUND_HALF_EVEN ) : null;
	$contra   = [];
	foreach ( (array) ( $hd['raw']['contradiction_rules'] ?? [] ) as $r ) {
		if ( ( $by_key[ $r['test_key_a'] ] ?? null ) === $r['answer_a'] && ( $by_key[ $r['test_key_b'] ] ?? null ) === $r['answer_b'] ) {
			$contra[] = (string) $r['message'];
		}
	}
	$vd = (array) $hd['raw']['verdicts'];
	$v  = $deal ? $vd['dealbreaker'] : ( null === $score ? $vd['unknown'] : ( $score >= 85 ? $vd['excellent_ge85'] : ( $score >= 65 ? $vd['ok_ge65'] : $vd['poor'] ) ) );
	// پلِ «💰 قیمت منصفانه»
	$prefill = [];
	if ( 'issue' === ( $by_key['body_water'] ?? '' ) ) {
		$prefill['repair'] = 'repair_water';
	} elseif ( 'issue' === ( $by_key['auth_tamper'] ?? '' ) ) {
		$prefill['repair'] = 'repair_opened';
	} elseif ( 'ok' === ( $by_key['body_water'] ?? '' ) && 'ok' === ( $by_key['auth_tamper'] ?? '' ) ) {
		$prefill['repair'] = 'repair_none';
	}
	if ( 'full' === $rep['mode'] ) {
		$fn = array_intersect_key( $by_key, array_flip( (array) $hd['raw']['functional_test_keys_for_prefill'] ) );
		$fn = array_filter( $fn, static fn( $a ) => 'unknown' !== $a );
		if ( in_array( 'issue', $fn, true ) ) {
			$prefill['features_ok'] = false;
		} elseif ( $fn ) {
			$prefill['features_ok'] = true;
		}
	}
	return [
		'report_id'        => (int) $rep['id'],
		'tracking_code'    => (string) $rep['code'],
		'mode'             => $rep['mode'],
		'model_name'       => $model['name'] ?? '',
		'capacity_label'   => (string) $rep['capacity_label'],
		'health_score'     => $score,
		'completeness_pct' => $total ? (int) round( 100 * $answered / $total ) : 0,
		'answered_count'   => $answered,
		'total_count'      => $total,
		'verdict'          => [ 'emoji' => $v[0], 'text' => $v[1] ],
		'has_dealbreaker'  => (bool) $deal,
		'dealbreakers'     => $deal,
		'contradictions'   => $contra,
		'category_summary' => array_values( array_map( static fn( $c ) => array_diff_key( $c, [ 'sort' => 1 ] ), $cats ) ),
		'evidence_counts'  => $ev,
		'part_status'      => (object) $parts,
		'answers'          => $alist,
		'prefill'          => (object) $prefill,
		'quick_note'       => 'quick' === $rep['mode'] ? (string) $hd['raw']['quick_mode_footer'] : '',
		'model_id'         => (int) $rep['model_id'],
		'completed_at'     => (string) ( $rep['completed_at'] ?? '' ),
	];
}

/* ───── انواعِ نوشته (در پیشخوان دیده می‌شوند، Novo می‌خواند) ───── */

add_action( 'init', static function (): void {
	$base = [ 'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'supports' => [ 'title', 'editor' ], 'capabilities' => [ 'create_posts' => 'do_not_allow' ], 'map_meta_cap' => true ];
	register_post_type( STLH_IPT_LEAD_PT, $base + [ 'label' => 'درخواست‌های فروشِ آیفون', 'menu_icon' => 'dashicons-money-alt' ] );
	register_post_type( STLH_IPT_HR_PT, $base + [ 'label' => 'شناسنامه‌های سلامت', 'menu_icon' => 'dashicons-shield' ] );
	register_post_type( STLH_IPT_VAL_PT, [ 'public' => false, 'show_ui' => false, 'label' => 'کارشناسی‌های قیمت' ] );
} );

/* ───── کمکی‌های درخواست ───── */

function stlh_ipt_device( WP_REST_Request $r ): string {
	$d = strtolower( (string) $r->get_header( 'x_stl_device' ) );
	return preg_match( '/^[a-f0-9]{32}$/', $d ) ? $d : '';
}

/** سقفِ درخواست برای هر آی‌پی؛ true یعنی رد */
function stlh_ipt_limited( string $kind, int $max, int $secs ): bool {
	$ip  = preg_replace( '/[^0-9a-f:.]/i', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key = 'stlh_ipt_rl_' . $kind . '_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) {
		return true;
	}
	set_transient( $key, $n + 1, $secs );
	return false;
}

function stlh_ipt_err( string $msg, int $status = 400 ): WP_REST_Response {
	return new WP_REST_Response( [ 'ok' => false, 'detail' => $msg, 'error' => $msg ], $status );
}

function stlh_ipt_off( string $tool ): ?WP_REST_Response {
	return empty( stlh_ipt_cfg()['settings'][ $tool ] ) ? stlh_ipt_err( 'این قابلیت در حال حاضر غیرفعال است.', 403 ) : null;
}

function stlh_ipt_route( string $path, string $method, callable $cb ): void {
	register_rest_route( STLH_IPT_NS, '/ipt' . $path, [ 'methods' => $method, 'permission_callback' => '__return_true', 'callback' => $cb ] );
}

function stlh_ipt_novo_route( string $path, string $method, callable $cb ): void {
	register_rest_route( STLH_IPT_NS, '/ipt' . $path, [ 'methods' => $method, 'permission_callback' => static fn() => current_user_can( 'manage_options' ), 'callback' => $cb ] );
}

/* ───── REST برای خودِ ابزارها (همان مسیرهای ربات زیرِ /ipt) ───── */

add_action( 'rest_api_init', static function (): void {

	stlh_ipt_route( '/iphone/wizard-data', 'GET', static function () {
		$cat    = stlh_ipt_catalog();
		$priced = [];
		foreach ( stlh_ipt_prices() as $r ) {
			$priced[ $r['model_id'] ][ strtoupper( $r['storage'] ) ] = 1;
		}
		$models   = [];
		$storages = [];
		$colors   = [];
		foreach ( $cat['models'] as $m ) {
			if ( empty( $priced[ $m['id'] ] ) ) {
				continue;
			}
			$models[] = array_intersect_key( $m, array_flip( [ 'id', 'name', 'series', 'series_id', 'dual_sim_parts', 'esim_only', 'image_url' ] ) );
			// فقط حافظه‌های قیمت‌دار — مشتری به «قیمتی ثبت نشده» نرسد
			$ok                            = array_values( array_filter( $m['storages'], static fn( $s ) => isset( $priced[ $m['id'] ][ strtoupper( $s ) ] ) ) );
			$storages[ (string) $m['id'] ] = array_map( static fn( $s, $i ) => [ 'id' => $i + 1, 'label' => $s, 'sort_order' => $i ], $ok, array_keys( $ok ) );
			$colors[ (string) $m['id'] ]   = array_map( static fn( $c, $i ) => [ 'id' => $i + 1, 'name' => (string) $c['name'], 'hex_color' => (string) ( $c['hex'] ?? '' ) ], $m['colors'], array_keys( $m['colors'] ) );
		}
		$sids   = array_flip( array_column( $models, 'series_id' ) );
		$series = array_values( array_filter( $cat['series'], static fn( $s ) => isset( $sids[ $s['id'] ] ) ) );
		$coefs  = [];
		foreach ( stlh_ipt_coefs() as $c => $rows ) {
			$coefs[ $c ] = array_values( array_map( static fn( $r ) => [ 'option_key' => $r['option_key'], 'option_label' => $r['option_label'], 'percent' => (float) $r['percent'], 'sort_order' => (int) ( $r['sort_order'] ?? 0 ) ], $rows ) );
		}
		$parts = array_map( static fn( $p, $i ) => [ 'id' => $i + 1, 'code' => (string) $p['code'], 'label' => (string) $p['label'] ], (array) stlh_ipt_cfg()['parts'], array_keys( (array) stlh_ipt_cfg()['parts'] ) );
		return [ 'ok' => true, 'series' => $series, 'models' => $models, 'storages' => (object) $storages, 'colors' => (object) $colors, 'parts' => $parts, 'coefficients' => $coefs, 'grade_stop_keys' => stlh_ipt_cfg()['grade_stop_keys'], 'registry_pricing' => (object) array_fill_keys( array_map( 'strval', array_column( $models, 'id' ) ), true ) ];
	} );

	stlh_ipt_route( '/iphone/grades', 'GET', static function ( WP_REST_Request $r ) {
		$spec = (array) stlh_ipt_cfg()['grade_special'];
		$main = [];
		$sp   = [];
		foreach ( stlh_ipt_coefs()['grade'] ?? [] as $k => $c ) {
			$row = [ 'option_key' => $k, 'option_label' => $c['option_label'], 'percent' => (float) $c['percent'], 'special' => in_array( $k, $spec, true ), 'available' => true ];
			if ( $row['special'] ) {
				$sp[] = $row;
			} else {
				$main[] = $row;
			}
		}
		return [ 'ok' => true, 'main' => $main, 'special' => $sp ];
	} );

	stlh_ipt_route( '/iphone/resolve', 'GET', static function ( WP_REST_Request $r ) {
		$cap = stlh_ipt_resolve( (int) $r['model_id'], (string) $r['capacity_label'] );
		if ( ! $cap ) {
			return [ 'ok' => false, 'error' => 'قیمتی برای این ترکیب هنوز در پنل ثبت نشده.' ];
		}
		return [ 'ok' => true, 'capacity' => [ 'id' => $cap['id'], 'base_price' => $cap['base'], 'capacity_label' => $cap['storage'] ] ];
	} );

	stlh_ipt_route( '/iphone/valuate', 'POST', static function ( WP_REST_Request $r ) {
		if ( $off = stlh_ipt_off( 'price' ) ) {
			return $off;
		}
		if ( stlh_ipt_limited( 'val', 15, 60 ) ) {
			return stlh_ipt_err( 'تعداد درخواست بیش از حد مجاز است. ۶۰ ثانیه دیگر تلاش کنید.', 429 );
		}
		$b     = (array) $r->get_json_params();
		$model = stlh_ipt_model( absint( $b['model_id'] ?? 0 ) );
		$cap   = stlh_ipt_prices()[ absint( $b['capacity_id'] ?? 0 ) ] ?? null;
		if ( ! absint( $b['model_id'] ?? 0 ) || ! $cap ) {
			return stlh_ipt_err( 'مدل و ظرفیت دستگاه الزامی است' );
		}
		if ( ! $model ) {
			return stlh_ipt_err( 'مدل یافت نشد' );
		}
		if ( $cap['model_id'] !== $model['id'] ) {
			return stlh_ipt_err( 'ظرفیت انتخاب‌شده متعلق به این مدل نیست' );
		}
		$sel = [];
		foreach ( array_slice( (array) ( $b['selections'] ?? [] ), 0, 30, true ) as $k => $v ) {
			$k = substr( sanitize_key( (string) $k ), 0, 40 );
			$sel[ $k ] = is_array( $v ) ? array_slice( array_map( static fn( $x ) => substr( sanitize_key( (string) $x ), 0, 60 ), $v ), 0, 20 ) : substr( sanitize_key( (string) $v ), 0, 60 );
		}
		$fok   = isset( $b['features_ok'] ) && null !== $b['features_ok'] ? (bool) $b['features_ok'] : null;
		$part  = mb_substr( sanitize_text_field( (string) ( $b['part_number'] ?? '' ) ), 0, 40 );
		$color = mb_substr( sanitize_text_field( (string) ( $b['color'] ?? '' ) ), 0, 40 );
		$pr    = stlh_ipt_price( $model, $cap, $sel, $part, $color );
		$sc    = stlh_ipt_score( $sel, $fok );
		$res   = [
			'model_name'      => $model['name'],
			'capacity_label'  => $cap['storage'],
			'capacity_id'     => $cap['id'],
			'market_price'    => $pr['market_price'],
			'fair_price'      => $pr['fair_price'],
			'buy_price'       => $pr['buy_price'],
			'score'           => $sc['score'],
			'score_breakdown' => $sc['breakdown'],
			'verdict_emoji'   => $sc['verdict_emoji'],
			'verdict_text'    => $sc['verdict_text'],
			'report_text'     => stlh_ipt_report( $pr['contributions'], $sc['score'] ),
			'fx_rate'         => $pr['fx_rate'],
			'contributions'   => $pr['contributions'],
			'sim_type'        => mb_substr( sanitize_key( (string) ( $b['sim_type'] ?? '' ) ), 0, 20 ),
			'part_number'     => $part,
			'color'           => $color,
			'grade_key'       => is_string( $sel['grade'] ?? null ) ? $sel['grade'] : '',
			'ai'              => null,
		];
		$res['valuation_id'] = stlh_ipt_log_valuation( stlh_ipt_device( $r ), $res, mb_substr( sanitize_text_field( (string) ( $b['city'] ?? '' ) ), 0, 60 ), $sel );
		return [ 'ok' => true, 'result' => $res ];
	} );

	stlh_ipt_route( '/iphone/history', 'GET', static function ( WP_REST_Request $r ) {
		$dev = stlh_ipt_device( $r );
		if ( '' === $dev ) {
			return [ 'ok' => true, 'items' => [] ];
		}
		$items = array_map( static fn( WP_Post $p ) => [
			'model_name'     => (string) get_post_meta( $p->ID, 'model_name', true ),
			'capacity_label' => (string) get_post_meta( $p->ID, 'capacity_label', true ),
			'fair_price'     => (int) get_post_meta( $p->ID, 'fair_price', true ),
			'created_at'     => $p->post_date,
		], get_posts( [ 'post_type' => STLH_IPT_VAL_PT, 'post_status' => 'publish', 'numberposts' => 10, 'meta_key' => 'device', 'meta_value' => $dev ] ) );
		return [ 'ok' => true, 'items' => $items ];
	} );

	stlh_ipt_route( '/iphone/sell-request', 'POST', static function ( WP_REST_Request $r ) {
		if ( $off = stlh_ipt_off( 'price' ) ) {
			return $off;
		}
		$b = (array) $r->get_json_params();
		if ( ! empty( $b['website'] ) ) {
			return [ 'ok' => true, 'request_id' => 0 ];
		}
		$phone = preg_replace( '/[^0-9+]/', '', stlh_ai_en_digits( (string) ( $b['phone'] ?? '' ) ) );
		$city  = mb_substr( sanitize_text_field( (string) ( $b['city'] ?? '' ) ), 0, 60 );
		if ( strlen( $phone ) < 8 ) {
			return stlh_ipt_err( 'شمارهٔ تماس معتبر نیست' );
		}
		if ( '' === $city ) {
			return stlh_ipt_err( 'نام شهر الزامی است' );
		}
		if ( stlh_ipt_limited( 'sell', 5, HOUR_IN_SECONDS ) ) {
			return stlh_ipt_err( 'تعداد درخواست بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.', 429 );
		}
		if ( preg_match( '/^(?:\+?98|0)?(9\d{9})$/', $phone, $m ) ) {
			$phone = '0' . $m[1];
		}
		$model = mb_substr( sanitize_text_field( (string) ( $b['model_name'] ?? '' ) ), 0, 80 );
		$price = absint( $b['estimated_price'] ?? 0 );
		$price = ( $price >= 1 && $price <= 5000000000 ) ? $price : 0;
		$grade = sanitize_key( (string) ( $b['grade_key'] ?? '' ) );
		$cap   = stlh_ipt_prices()[ absint( $b['capacity_id'] ?? 0 ) ] ?? null;
		$body  = sprintf( "📱 %s %s\n📞 %s\n🏙 %s\n💰 %s", $model, $cap['storage'] ?? '', $phone, $city, $price ? 'قیمت تخمینی (تأییدنشده): ' . number_format( $price ) . ' تومان' : 'قیمت: توافقی' );
		$id    = wp_insert_post( [ 'post_type' => STLH_IPT_LEAD_PT, 'post_status' => 'publish', 'post_title' => trim( $model . ' — ' . $phone ), 'post_content' => $body ] );
		if ( ! $id || is_wp_error( $id ) ) {
			return stlh_ipt_err( 'خطا در ثبت درخواست' );
		}
		foreach ( [ 'phone' => $phone, 'city' => $city, 'model_name' => $model, 'capacity_label' => $cap['storage'] ?? '', 'grade_key' => $grade, 'estimated_price' => $price, 'status' => 'new', 'device' => stlh_ipt_device( $r ) ] as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		wp_mail( get_option( 'admin_email' ), '🤝 درخواست فروشِ آیفون: ' . $model, $body );
		return [ 'ok' => true, 'request_id' => $id ];
	} );

	stlh_ipt_route( '/iphone/spec-models', 'GET', static function () {
		$specs = stlh_ipt_cfg()['specs'];
		$out   = [];
		foreach ( stlh_ipt_catalog()['models'] as $m ) {
			if ( ! empty( $specs[ $m['name'] ]['chip'] ) ) {
				$out[] = [ 'id' => $m['id'], 'name' => $m['name'], 'year' => $m['series'], 'image_url' => '' ];
			}
		}
		return [ 'ok' => true, 'models' => $out ];
	} );

	stlh_ipt_route( '/iphone/compare', 'GET', static function ( WP_REST_Request $r ) {
		$a = stlh_ipt_model( absint( $r['a'] ) );
		$b = stlh_ipt_model( absint( $r['b'] ) );
		if ( absint( $r['a'] ) === absint( $r['b'] ) ) {
			return stlh_ipt_err( 'دو مدل متفاوت انتخاب کنید.' );
		}
		if ( ! $a || ! $b ) {
			return stlh_ipt_err( 'مدل یافت نشد.', 404 );
		}
		$sp     = stlh_ipt_cfg()['specs'];
		$fields = [];
		foreach ( (array) stlh_ipt_cfg()['spec_fields'] as $f ) {
			$va = (string) ( $sp[ $a['name'] ][ $f['key'] ] ?? '' );
			$vb = (string) ( $sp[ $b['name'] ][ $f['key'] ] ?? '' );
			if ( '' === $va && '' === $vb ) {
				continue;
			}
			$fields[] = [ 'key' => $f['key'], 'label' => $f['label'], 'a' => $va, 'b' => $vb, 'diff' => $va !== $vb ];
		}
		$card = static fn( $m ) => [ 'id' => $m['id'], 'name' => $m['name'], 'year' => $m['series'], 'image_url' => '' ];
		return [ 'ok' => true, 'a' => $card( $a ), 'b' => $card( $b ), 'fields' => $fields ];
	} );

	/* سلامت */

	stlh_ipt_route( '/health/wizard-data', 'GET', static function () {
		$cat = stlh_ipt_catalog();
		$hd  = stlh_ipt_health_data();
		$st  = [];
		foreach ( $cat['models'] as $m ) {
			$st[ (string) $m['id'] ] = array_map( static fn( $s, $i ) => [ 'id' => $i + 1, 'label' => $s, 'sort_order' => $i ], $m['storages'], array_keys( $m['storages'] ) );
		}
		$models = array_values( array_map( static fn( $m ) => array_intersect_key( $m, array_flip( [ 'id', 'name', 'series', 'series_id', 'features', 'image_url' ] ) ), $cat['models'] ) );
		return [
			'ok'              => true,
			'categories'      => array_values( $hd['categories'] ),
			'tests'           => array_values( $hd['tests'] ),
			'series'          => $cat['series'],
			'models'          => $models,
			'storages'        => (object) $st,
			'web_tests'       => (object) $hd['raw']['web_tests'],
			'evidence_levels' => (object) $hd['raw']['evidence_levels'],
			'schematic_parts' => (object) $hd['raw']['schematic_parts'],
		];
	} );

	stlh_ipt_route( '/health/report', 'POST', static function ( WP_REST_Request $r ) {
		if ( $off = stlh_ipt_off( 'health' ) ) {
			return $off;
		}
		$dev = stlh_ipt_device( $r );
		$b   = (array) $r->get_json_params();
		$m   = stlh_ipt_model( absint( $b['model_id'] ?? 0 ) );
		if ( '' === $dev || ! $m ) {
			return stlh_ipt_err( 'ورودی نامعتبر است' );
		}
		$mode = 'full' === ( $b['mode'] ?? '' ) ? 'full' : 'quick';
		if ( ! stlh_ipt_applicable( $m, $mode ) ) {
			return stlh_ipt_err( 'تستی برای این مدل تعریف نشده' );
		}
		if ( stlh_ipt_limited( 'hr', 15, 300 ) ) {
			return stlh_ipt_err( 'تعداد درخواست بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.', 429 );
		}
		do {
			$code = 'SL-' . substr( str_shuffle( str_repeat( 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3 ) ), 0, 5 );
		} while ( get_posts( [ 'post_type' => STLH_IPT_HR_PT, 'post_status' => 'any', 'title' => $code, 'numberposts' => 1, 'fields' => 'ids' ] ) );
		$id = wp_insert_post( [ 'post_type' => STLH_IPT_HR_PT, 'post_status' => 'draft', 'post_title' => $code ] );
		if ( ! $id || is_wp_error( $id ) ) {
			return stlh_ipt_err( 'خطا در شروع تست' );
		}
		$cap = mb_substr( sanitize_text_field( (string) ( $b['capacity_label'] ?? '' ) ), 0, 20 );
		foreach ( [ 'device' => $dev, 'model_id' => $m['id'], 'model_name' => $m['name'], 'capacity_label' => $cap, 'mode' => $mode, 'answers' => [] ] as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		return [ 'ok' => true, 'report' => [ 'id' => $id, 'tracking_code' => $code, 'model_id' => $m['id'], 'capacity_label' => $cap, 'mode' => $mode, 'status' => 'in_progress' ], 'resumed' => false ];
	} );

	register_rest_route( STLH_IPT_NS, '/ipt/health/report/(?P<id>\d+)/(?P<act>answer|finalize)', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => static function ( WP_REST_Request $r ) {
			$id = (int) $r['id'];
			if ( STLH_IPT_HR_PT !== get_post_type( $id ) ) {
				return stlh_ipt_err( 'گزارش یافت نشد', 404 );
			}
			$dev = stlh_ipt_device( $r );
			if ( '' === $dev || get_post_meta( $id, 'device', true ) !== $dev ) {
				return stlh_ipt_err( 'این گزارش متعلق به شما نیست', 403 );
			}
			if ( 'publish' === get_post_status( $id ) ) {
				return stlh_ipt_err( 'این گزارش قبلاً نهایی شده است' );
			}
			$rep = stlh_ipt_hr_load( $id );
			if ( 'answer' === $r['act'] ) {
				$b   = (array) $r->get_json_params();
				$tid = absint( $b['test_id'] ?? 0 );
				$ans = (string) ( $b['answer'] ?? '' );
				if ( ! $tid ) {
					return stlh_ipt_err( 'شناسهٔ تست نامعتبر است' );
				}
				if ( ! isset( stlh_ipt_health_data()['tests'][ $tid ] ) ) {
					return stlh_ipt_err( 'تست یافت نشد', 404 );
				}
				if ( ! in_array( $ans, [ 'ok', 'issue', 'unknown' ], true ) ) {
					return stlh_ipt_err( 'پاسخ نامعتبر است' );
				}
				$ev                     = in_array( $b['evidence'] ?? '', [ 'auto', 'assisted', 'manual' ], true ) ? $b['evidence'] : 'manual';
				$meas                   = $b['measurement'] ?? '';
				$rep['answers'][ $tid ] = [ 'answer' => $ans, 'evidence' => $ev, 'measurement' => mb_substr( is_string( $meas ) ? $meas : (string) wp_json_encode( $meas ), 0, 2000 ) ];
				update_post_meta( $id, 'answers', $rep['answers'] );
				$app = stlh_ipt_applicable( stlh_ipt_model( (int) $rep['model_id'] ) ?? [ 'features' => '' ], $rep['mode'] );
				return [ 'ok' => true, 'answered' => count( array_intersect_key( $rep['answers'], $app ) ), 'total' => count( $app ) ];
			}
			$rep['completed_at'] = current_time( 'mysql' );
			$res                 = stlh_ipt_health_compute( $rep );
			wp_update_post( [ 'ID' => $id, 'post_status' => 'publish', 'post_content' => $res['verdict']['emoji'] . ' ' . $res['verdict']['text'] . "\n" . $res['model_name'] . ' ' . $res['capacity_label'] . "\nامتیاز: " . ( $res['health_score'] ?? '—' ) . ' · کامل بودن: ' . $res['completeness_pct'] . '٪' ] );
			update_post_meta( $id, 'result', $res );
			return [ 'ok' => true, 'result' => $res ];
		},
	] );

	stlh_ipt_route( '/health/history', 'GET', static function ( WP_REST_Request $r ) {
		$dev = stlh_ipt_device( $r );
		if ( '' === $dev ) {
			return [ 'ok' => true, 'items' => [] ];
		}
		$items = [];
		foreach ( get_posts( [ 'post_type' => STLH_IPT_HR_PT, 'post_status' => 'publish', 'numberposts' => 10, 'meta_key' => 'device', 'meta_value' => $dev ] ) as $p ) {
			$res     = (array) get_post_meta( $p->ID, 'result', true );
			$items[] = [ 'model_name' => $res['model_name'] ?? '', 'tracking_code' => $p->post_title, 'verdict' => trim( ( $res['verdict']['emoji'] ?? '' ) . ' ' . ( $res['verdict']['text'] ?? '' ) ), 'health_score' => $res['health_score'] ?? null, 'completeness_pct' => $res['completeness_pct'] ?? 0 ];
		}
		return [ 'ok' => true, 'items' => $items ];
	} );

	/* Novo: پیکربندی و خواندنِ داده‌ها (فقط مدیر) — قرارداد: docs/novo-iphone-tools.md */

	stlh_ipt_novo_route( '/config', 'GET', static function () {
		$c = stlh_ipt_cfg();
		unset( $c['health']['raw'] );
		return [ 'ok' => true, 'pushed' => (bool) get_option( STLH_IPT_OPT ), 'config' => $c ];
	} );

	stlh_ipt_novo_route( '/config', 'POST', static function ( WP_REST_Request $r ) {
		$in      = (array) $r->get_json_params();
		$allowed = [ 'models', 'parts', 'coefficients', 'score_weights', 'prices', 'unregistered_pct', 'modifiers', 'settings', 'health', 'spec_fields', 'specs', 'version' ];
		$new     = (array) get_option( STLH_IPT_OPT, [] );
		foreach ( array_intersect_key( $in, array_flip( $allowed ) ) as $k => $v ) {
			if ( null === $v ) {
				unset( $new[ $k ] ); // null = برگشت به پیش‌فرض
				continue;
			}
			$new[ $k ] = 'version' === $k ? sanitize_text_field( (string) $v ) : stlh_ipt_clean( $v );
		}
		$new['updated'] = gmdate( 'c' );
		update_option( STLH_IPT_OPT, $new, false );
		if ( array_key_exists( 'settings', $in ) && function_exists( 'stlh_cache_flush' ) ) {
			stlh_cache_flush(); // روشن/خاموشِ ابزار: بخشِ صفحه‌ی اصلی و منو
			do_action( 'litespeed_purge_all' );
		}
		return [ 'ok' => true, 'updated' => $new['updated'], 'keys' => array_values( array_diff( array_keys( $new ), [ 'updated' ] ) ) ];
	} );

	foreach ( [ 'leads' => STLH_IPT_LEAD_PT, 'health-reports' => STLH_IPT_HR_PT, 'valuations' => STLH_IPT_VAL_PT ] as $path => $pt ) {
		stlh_ipt_novo_route( '/' . $path, 'GET', static function ( WP_REST_Request $r ) use ( $pt ) {
			$days = max( 1, min( 365, absint( $r['days'] ?: 30 ) ) );
			$out  = [];
			foreach ( get_posts( [ 'post_type' => $pt, 'post_status' => 'publish', 'numberposts' => 300, 'date_query' => [ [ 'after' => $days . ' days ago' ] ] ] ) as $p ) {
				$meta = array_map( static fn( $v ) => maybe_unserialize( $v[0] ), get_post_meta( $p->ID ) );
				unset( $meta['device'], $meta['answers'] );
				$out[] = [ 'id' => $p->ID, 'title' => $p->post_title, 'created_gmt' => gmdate( 'c', strtotime( $p->post_date_gmt ) ) ] + $meta;
			}
			return [ 'ok' => true, 'items' => $out ];
		} );
	}

	stlh_ipt_novo_route( '/leads/(?P<id>\d+)', 'POST', static function ( WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( STLH_IPT_LEAD_PT !== get_post_type( $id ) ) {
			return stlh_ipt_err( 'درخواست یافت نشد', 404 );
		}
		$b = (array) $r->get_json_params();
		if ( isset( $b['status'] ) && in_array( $b['status'], [ 'new', 'contacted', 'archived' ], true ) ) {
			update_post_meta( $id, 'status', $b['status'] );
		}
		if ( isset( $b['note'] ) ) {
			update_post_meta( $id, 'admin_note', sanitize_textarea_field( (string) $b['note'] ) );
		}
		return [ 'ok' => true ];
	} );
} );

/** پاک‌سازیِ بازگشتیِ ورودیِ Novo: فقط عدد، بولی و متنِ بی‌برچسب (متنِ راهنمای سلامت HTMLِ امن) */
function stlh_ipt_clean( $v, string $key = '' ) {
	if ( is_array( $v ) ) {
		$out = [];
		foreach ( $v as $k => $x ) {
			$out[ is_int( $k ) ? $k : mb_substr( sanitize_text_field( (string) $k ), 0, 80 ) ] = stlh_ipt_clean( $x, (string) $k );
		}
		return $out;
	}
	if ( is_bool( $v ) || is_int( $v ) || is_float( $v ) || null === $v ) {
		return $v;
	}
	return 'guide_text_html' === $key ? wp_kses_post( (string) $v ) : sanitize_text_field( (string) $v );
}

function stlh_ipt_hr_load( int $id ): array {
	return [
		'id'             => $id,
		'code'           => get_the_title( $id ),
		'model_id'       => (int) get_post_meta( $id, 'model_id', true ),
		'capacity_label' => (string) get_post_meta( $id, 'capacity_label', true ),
		'mode'           => (string) get_post_meta( $id, 'mode', true ) ?: 'quick',
		'answers'        => (array) get_post_meta( $id, 'answers', true ),
	];
}

/** ثبتِ کارشناسی (تاریخچه‌ی هر دستگاه + خواندنِ Novo)؛ فقط ۳۰۰ تای آخر نگه داشته می‌شود */
function stlh_ipt_log_valuation( string $dev, array $res, string $city, array $sel ): int {
	$id = wp_insert_post( [ 'post_type' => STLH_IPT_VAL_PT, 'post_status' => 'publish', 'post_title' => $res['model_name'] . ' ' . $res['capacity_label'] ] );
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	foreach ( [ 'device' => $dev, 'model_name' => $res['model_name'], 'capacity_label' => $res['capacity_label'], 'fair_price' => $res['fair_price'], 'market_price' => $res['market_price'], 'buy_price' => $res['buy_price'], 'score' => $res['score'], 'verdict' => $res['verdict_emoji'] . ' ' . $res['verdict_text'], 'city' => $city, 'selections' => $sel, 'color' => $res['color'], 'part_number' => $res['part_number'] ] as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	$old = get_posts( [ 'post_type' => STLH_IPT_VAL_PT, 'post_status' => 'publish', 'numberposts' => 50, 'offset' => 300, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ] );
	foreach ( $old as $o ) {
		wp_delete_post( $o, true );
	}
	return (int) $id;
}

/* ───── صفحه‌ها و کدِ کوتاه ───── */

function stlh_ipt_url( string $tool ): string {
	[ $opt, $slug, $label, $sc ] = STLH_IPT_PAGES[ $tool ];
	$id = stlh_own_page( $opt, $slug, stlh_label( $label ), $sc, true );
	return $id ? (string) get_permalink( $id ) : home_url( '/' . $slug . '/' );
}

// برگه‌ها همان بارِ اولِ بعد از نصب ساخته می‌شوند (نه منتظرِ بازدیدِ پیشخوان)؛ بعد فقط یک گزینه خوانده می‌شود
add_action( 'init', static function (): void {
	if ( get_option( 'stlh_ipt_pages' ) !== STLH_VER ) {
		foreach ( array_keys( STLH_IPT_PAGES ) as $t ) {
			stlh_ipt_url( $t );
		}
		update_option( 'stlh_ipt_pages', STLH_VER, false );
	}
} );

add_shortcode( 'stl_ipt', static function ( $atts ): string {
	$tool = in_array( $atts['tool'] ?? '', [ 'price', 'health', 'compare' ], true ) ? $atts['tool'] : 'price';
	$code = isset( $_GET['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['code'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'health' === $tool && preg_match( '/^SL-[A-Z0-9]{3,12}$/', $code ) ) {
		return stlh_ipt_cert_page( $code );
	}
	$ver = STLH_VER;
	wp_enqueue_script( 'stlh-ipt', STLH_URL . 'assets/ipt/ipt.js', [], $ver, true );
	wp_localize_script( 'stlh-ipt', 'stlhIpt', [
		'rest'   => esc_url_raw( rest_url( STLH_IPT_NS . '/ipt' ) ),
		'tool'   => $tool,
		'urls'   => [ 'price' => stlh_ipt_url( 'price' ), 'health' => stlh_ipt_url( 'health' ), 'compare' => stlh_ipt_url( 'compare' ) ],
		'cert'   => stlh_ipt_url( 'health' ),
		'titles' => [ 'price' => stlh_label( 'ipt_price_title' ), 'health' => stlh_label( 'ipt_health_title' ), 'compare' => stlh_label( 'ipt_compare_title' ) ],
	] );
	$css = (string) file_get_contents( STLH_DIR . 'assets/ipt/ipt.css' );
	return '<div class="stlh-ipt" dir="rtl"><style>' . $css . '</style>' . stlh_ipt_nav( $tool )
		. '<div id="post-title" class="stlh-ipt-title"></div><div id="post-body" class="stlh-ipt-body"><noscript>' . esc_html( stlh_label( 'ipt_noscript' ) ) . '</noscript></div></div>';
} );

/** سه دکمه‌ی بالای هر ابزار تا مشتری بینشان جابه‌جا شود */
function stlh_ipt_nav( string $cur ): string {
	$h = '';
	foreach ( [ 'price' => '💰', 'health' => '🩺', 'compare' => '⚖️' ] as $t => $ic ) {
		if ( empty( stlh_ipt_cfg()['settings'][ $t ] ) ) {
			continue;
		}
		$h .= '<a class="stlh-ipt-tab' . ( $t === $cur ? ' on' : '' ) . '" href="' . esc_url( stlh_ipt_url( $t ) ) . '">' . $ic . ' ' . esc_html( stlh_label( 'ipt_' . $t . '_title' ) ) . '</a>';
	}
	return '<nav class="stlh-ipt-tabs">' . $h . '</nav>';
}

/** صفحه‌ی عمومیِ شناسنامه (`?code=SL-…`) — مثلِ `/health/{code}` ربات، بی هوش مصنوعی */
function stlh_ipt_cert_page( string $code ): string {
	if ( stlh_ipt_limited( 'cert', 30, 60 ) ) {
		return '<div class="stlh-ipt-cert"><p>تعداد درخواست زیاد است — کمی بعد دوباره تلاش کنید.</p></div>';
	}
	$p   = get_posts( [ 'post_type' => STLH_IPT_HR_PT, 'post_status' => 'publish', 'title' => $code, 'numberposts' => 1 ] );
	$res = $p ? (array) get_post_meta( $p[0]->ID, 'result', true ) : [];
	$css = '.stlh-ipt-cert{max-width:560px;margin:24px auto;padding:22px 18px;border-radius:22px;background:#fff;border:1px solid #e5e7eb;box-shadow:0 18px 40px -28px rgba(15,23,42,.45);text-align:center;line-height:1.9;color:#0f172a}'
		. '.stlh-ipt-cert .b{font-weight:900;letter-spacing:.2em;color:#1d4ed8;font-size:13px}.stlh-ipt-cert .s{font-size:46px;font-weight:900;margin:6px 0}.stlh-ipt-cert .m{color:#64748b;font-size:13px}'
		. '.stlh-ipt-cert ul{list-style:none;margin:12px 0 0;padding:0;text-align:right}.stlh-ipt-cert li{padding:7px 10px;border-top:1px solid #f1f5f9;font-size:14px}.stlh-ipt-cert .w{color:#b45309}.stlh-ipt-cert .d{color:#b91c1c}';
	if ( ! $res ) {
		return '<div class="stlh-ipt-cert"><style>' . $css . '</style><b>گزارشی با این کد رهگیری پیدا نشد</b><p class="m">کد را دوباره بررسی کنید — فقط گزارش‌های تکمیل‌شده قابل نمایش‌اند.</p></div>';
	}
	$ev   = (array) ( $res['evidence_counts'] ?? [] );
	$date = function_exists( 'stlh_jalali' ) ? stlh_jalali( strtotime( (string) ( $res['completed_at'] ?? $p[0]->post_date ) ) ) : mysql2date( 'Y/m/d', $p[0]->post_date );
	$h    = '<div class="stlh-ipt-cert" dir="rtl"><style>' . $css . '</style><div class="b">STOCKLAND</div><div>🩺 شناسنامهٔ سلامت آیفون</div><div class="m" dir="ltr">' . esc_html( $code ) . '</div>'
		. '<div class="s">' . esc_html( null === ( $res['health_score'] ?? null ) ? '—' : stlh_fa( (string) $res['health_score'] ) ) . ' <small style="font-size:18px">/ ۱۰۰</small></div>'
		. '<div><b>' . esc_html( ( $res['verdict']['emoji'] ?? '' ) . ' ' . ( $res['verdict']['text'] ?? '' ) ) . '</b></div>'
		. '<div class="m">' . esc_html( ( $res['model_name'] ?: 'مدل ثبت نشده' ) . ' · تکمیل گزارش: ' . stlh_fa( (string) ( $res['completeness_pct'] ?? 0 ) ) . '٪ · ' . $date ) . '</div>'
		. '<div class="m">🟢 اندازه‌گیری‌شده: ' . stlh_fa( (string) ( $ev['auto'] ?? 0 ) ) . ' · 🟡 بصری: ' . stlh_fa( (string) ( $ev['assisted'] ?? 0 ) ) . ' · ⚪️ اظهاری: ' . stlh_fa( (string) ( $ev['manual'] ?? 0 ) ) . '</div><ul>';
	foreach ( (array) ( $res['category_summary'] ?? [] ) as $c ) {
		$h .= '<li>' . esc_html( $c['icon'] . ' ' . $c['title'] ) . ' <span style="float:left">' . esc_html( stlh_fa( (string) $c['ok'] ) . '✓ ' . stlh_fa( (string) $c['issue'] ) . '✗ ' . stlh_fa( (string) $c['unknown'] ) . '؟' ) . '</span></li>';
	}
	foreach ( (array) ( $res['dealbreakers'] ?? [] ) as $d ) {
		$h .= '<li class="d">🔴 ' . esc_html( $d['title'] ) . '</li>';
	}
	foreach ( (array) ( $res['contradictions'] ?? [] ) as $c ) {
		$h .= '<li class="w">⚠️ ' . esc_html( $c ) . '</li>';
	}
	if ( ! empty( $res['quick_note'] ) ) {
		$h .= '<li>ℹ️ ' . esc_html( $res['quick_note'] ) . '</li>';
	}
	return $h . '</ul><p class="m" style="margin-top:14px">این گزارش توسط کاربر با راهنمای تست سلامت Stland ثبت شده — سطح اعتبار هر پاسخ بالای صفحه مشخص است.</p>'
		. '<p><a href="' . esc_url( stlh_ipt_url( 'health' ) ) . '">🩺 ' . esc_html( stlh_label( 'ipt_health_title' ) ) . '</a></p></div>';
}

/* شناسنامه‌ی هر کد صفحه‌ی تکراری است: بیرون از فهرستِ گوگل */
add_filter( 'wpseo_robots', static function ( $robots ) {
	return ( isset( $_GET['code'] ) && is_page( (int) get_option( STLH_IPT_PAGES['health'][0] ) ) ) ? 'noindex, follow' : $robots; // phpcs:ignore WordPress.Security.NonceVerification
}, 32 );
