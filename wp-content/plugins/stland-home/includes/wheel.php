<?php
/**
 * گردونه‌ی شانس — عینِ گردونه‌ی ربات (stockland-bot، `core/wheel.py` + مینی‌اپ)، برای سایت (صاحب فروشگاه، ۶ آذر ۱۴۰۵:
 * «بخشِ گردونه شانس هم با تمامِ متعلقاتش بیار»).
 *
 * تصمیم‌های صاحب فروشگاه (همان روز):
 *   • فقط مشتریِ واردشده (حسابِ کاربریِ سایت) می‌چرخاند؛ روزی N بار برای هر حساب (`daily_spin_limit`، ساعتِ ریست به وقتِ تهران).
 *   • جایزه‌ی «اعتبارِ کیف‌پول» ربات این‌جا «کدِ تخفیفِ مبلغی» است (سایت کیف‌پولِ ربات را ندارد).
 *   • تعریفِ جوایز/تنظیمات و پیگیریِ برنده‌ها در Novo (`POST stland/v1/wheel/config` و …، قرارداد `docs/novo-wheel.md`)؛
 *     تا اولین ارسال، جوایزِ پیش‌فرضِ ربات.
 *
 * مثلِ ربات، جایزه ۱۰۰٪ سمتِ سرور انتخاب می‌شود (وزنی، با سقفِ کل/روزانه) و مرورگر فقط انیمیشن را اجرا می‌کند. اتمیک بودن:
 * رزروِ شناسه‌ی درخواست (یکتای کاربر+شناسه) پیش از مصرفِ سهمیه، شمارنده‌ی روزانه با UPDATE شرطی، و سقفِ جایزه با UPDATE شرطی
 * — دو کلیک یا دو زبانه هرگز دو جایزه نمی‌دهند. کدِ تخفیف یک کوپنِ واقعیِ فروشگاه‌ساز است: فقط برای ایمیلِ همان حساب، یک بار،
 * با تاریخِ انقضا، حداقلِ خرید، محدوده‌ی محصول/دسته، و سقفِ تخفیفِ درصدی (`stlh_wheel_max`).
 */
defined( 'ABSPATH' ) || exit;

const STLH_WHEEL_OPT   = 'stlh_wheel_cfg';
const STLH_WHEEL_DBV   = '1';
const STLH_WHEEL_TYPES = [ 'discount_fixed', 'discount_percent', 'extra_spin', 'no_win', 'physical_gift' ];
const STLH_WHEEL_PAGE  = [ 'stlh_page_wheel', 'wheel', 'wheel_title', '[stl_wheel]' ];

/* ───── پیکربندی: پیش‌فرضِ ربات + ارسالِ Novo ───── */

function stlh_wheel_defaults(): array {
	return [
		'settings' => [
			'enabled'               => true,
			'title'                 => '🎡 چرخ‌گردون شانس',
			'description'           => 'هر روز یک شانس رایگان برای بردن جایزه!',
			'daily_spin_limit'      => 1,
			'reset_hour'            => 0,
			'animation_duration_ms' => 5200,
			'sound_enabled'         => true,
			'haptic_enabled'        => true,
			'show_odds'             => false,
		],
		'campaign' => [ 'id' => 1, 'title' => 'کمپین پیش‌فرض', 'ends_at' => null ],
		// همان شش جایزه‌ی پیش‌فرضِ ربات؛ «اعتبارِ کیف‌پول» ← کدِ تخفیفِ مبلغی، و تخفیفِ درصدی با سقف (روی گوشیِ گران بی سقف نمی‌شود)
		'prizes'   => [
			[ 'id' => 'p1', 'title' => '۵ هزار تومان تخفیف', 'icon' => '💵', 'color' => '#22C55E', 'prize_type' => 'discount_fixed', 'value' => 5000, 'validity_hours' => 72, 'weight' => 1, 'description' => 'شانس دوباره امتحان کن!' ],
			[ 'id' => 'p2', 'title' => '۱۰ هزار تومان تخفیف', 'icon' => '💰', 'color' => '#16A34A', 'prize_type' => 'discount_fixed', 'value' => 10000, 'validity_hours' => 72, 'weight' => 0.5, 'description' => '' ],
			[ 'id' => 'p3', 'title' => '۱۰٪ تخفیف خرید بعدی', 'icon' => '🏷', 'color' => '#F59E0B', 'prize_type' => 'discount_percent', 'value' => 10, 'max_discount_value' => 100000, 'validity_hours' => 24, 'weight' => 1, 'description' => 'روی خرید بعدیت اعمال می‌شه' ],
			[ 'id' => 'p4', 'title' => '۲۰٪ تخفیف خرید بعدی', 'icon' => '🎫', 'color' => '#EA580C', 'prize_type' => 'discount_percent', 'value' => 20, 'max_discount_value' => 100000, 'validity_hours' => 24, 'weight' => 0.3, 'description' => '' ],
			[ 'id' => 'p5', 'title' => 'یک چرخش دیگه!', 'icon' => '🔄', 'color' => '#6366F1', 'prize_type' => 'extra_spin', 'value' => 1, 'weight' => 1.5, 'description' => '' ],
			[ 'id' => 'p6', 'title' => 'این بار شانس نیاوردی', 'icon' => '😅', 'color' => '#6B7280', 'prize_type' => 'no_win', 'value' => 0, 'weight' => 3, 'description' => 'فردا دوباره امتحان کن' ],
		],
	];
}

/** یک جایزه با همه‌ی کلیدها و نوعِ درست (ورودیِ Novo یا پیش‌فرض) */
function stlh_wheel_prize_norm( array $p, int $i ): ?array {
	$type = (string) ( $p['prize_type'] ?? 'no_win' );
	if ( ! in_array( $type, STLH_WHEEL_TYPES, true ) ) {
		$type = 'wallet_credit' === $type ? 'discount_fixed' : 'no_win'; // نوعِ کیف‌پولِ ربات = کدِ مبلغی
	}
	$id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $p['id'] ?? '' ) ) ?: 'p' . ( $i + 1 );
	$ids = static fn( $v ): array => array_values( array_filter( array_map( 'absint', is_array( $v ) ? $v : explode( ',', (string) $v ) ) ) );
	return [
		'id'                  => substr( $id, 0, 40 ),
		'title'               => mb_substr( (string) ( $p['title'] ?? '' ), 0, 120 ) ?: 'جایزه',
		'wheel_label'         => mb_substr( (string) ( $p['wheel_label'] ?? '' ), 0, 40 ),
		'icon'                => mb_substr( (string) ( $p['icon'] ?? '🎁' ), 0, 8 ),
		'color'               => sanitize_hex_color( (string) ( $p['color'] ?? '' ) ) ?: '#6366F1',
		'image_url'           => esc_url_raw( (string) ( $p['image_url'] ?? '' ) ),
		'prize_type'          => $type,
		'value'               => max( 0, (int) ( $p['value'] ?? 0 ) ),
		'max_discount_value'  => max( 0, (int) ( $p['max_discount_value'] ?? 0 ) ),
		'min_purchase_amount' => max( 0, (int) ( $p['min_purchase_amount'] ?? 0 ) ),
		'validity_hours'      => max( 0, (int) ( $p['validity_hours'] ?? 0 ) ),
		'weight'              => max( 0.0, (float) ( $p['weight'] ?? 1 ) ),
		'total_limit'         => max( 0, (int) ( $p['total_limit'] ?? 0 ) ),
		'daily_limit'         => max( 0, (int) ( $p['daily_limit'] ?? 0 ) ),
		'active'              => ! isset( $p['active'] ) || ! empty( $p['active'] ),
		'sort_order'          => (int) ( $p['sort_order'] ?? $i ),
		'description'         => mb_substr( (string) ( $p['description'] ?? '' ), 0, 300 ),
		'product_ids'         => $ids( $p['product_ids'] ?? [] ),
		'category_ids'        => $ids( $p['category_ids'] ?? [] ),
	];
}

function stlh_wheel_cfg(): array {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$d    = stlh_wheel_defaults();
	$push = (array) get_option( STLH_WHEEL_OPT, [] );
	$set  = array_merge( $d['settings'], array_intersect_key( (array) ( $push['settings'] ?? [] ), $d['settings'] ) );
	$set['daily_spin_limit']      = max( 0, (int) $set['daily_spin_limit'] );
	$set['reset_hour']            = min( 23, max( 0, (int) $set['reset_hour'] ) );
	$set['animation_duration_ms'] = min( 12000, max( 1500, (int) $set['animation_duration_ms'] ) );
	foreach ( [ 'enabled', 'sound_enabled', 'haptic_enabled', 'show_odds' ] as $b ) {
		$set[ $b ] = ! empty( $set[ $b ] );
	}
	$raw    = isset( $push['prizes'] ) && is_array( $push['prizes'] ) ? $push['prizes'] : $d['prizes'];
	$prizes = [];
	foreach ( array_values( $raw ) as $i => $p ) {
		if ( is_array( $p ) && ( $n = stlh_wheel_prize_norm( $p, $i ) ) ) {
			$prizes[] = $n;
		}
	}
	usort( $prizes, static fn( $a, $b ) => $a['sort_order'] <=> $b['sort_order'] );
	$camp = array_merge( $d['campaign'], array_intersect_key( (array) ( $push['campaign'] ?? [] ), $d['campaign'] ) );
	return $memo = [ 'settings' => $set, 'campaign' => $camp, 'prizes' => $prizes, 'version' => (string) ( $push['version'] ?? '' ) ];
}

/* ───── جدول‌ها ───── */

function stlh_wheel_tables(): array {
	global $wpdb;
	return [ 's' => $wpdb->prefix . 'stlh_wheel_spins', 'd' => $wpdb->prefix . 'stlh_wheel_daily', 'c' => $wpdb->prefix . 'stlh_wheel_count' ];
}

add_action( 'init', static function (): void {
	// هنگامِ نصبِ خودِ وردپرس جدولی نساز (upgrade.php را زودتر بار می‌کرد و نصب با «Cannot redeclare» می‌شکست).
	if ( wp_installing() || get_option( 'stlh_wheel_dbv' ) === STLH_WHEEL_DBV ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t  = stlh_wheel_tables();
	$cs = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$t['s']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  campaign_id int(11) NOT NULL DEFAULT 0,
  prize_id varchar(40) NOT NULL DEFAULT '',
  prize_type varchar(24) NOT NULL DEFAULT '',
  prize_title varchar(160) NOT NULL DEFAULT '',
  amount bigint(20) NOT NULL DEFAULT 0,
  coupon_code varchar(40) NOT NULL DEFAULT '',
  expires_at datetime NULL,
  status varchar(16) NOT NULL DEFAULT 'pending',
  req_id varchar(120) NOT NULL DEFAULT '',
  usage_date varchar(10) NOT NULL DEFAULT '',
  ip varchar(64) NOT NULL DEFAULT '',
  claim_name varchar(120) NOT NULL DEFAULT '',
  claim_phone varchar(40) NOT NULL DEFAULT '',
  claim_address text NULL,
  claim_status varchar(16) NOT NULL DEFAULT '',
  admin_note varchar(500) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_req (user_id,req_id),
  KEY user_created (user_id,created_at),
  KEY prize (prize_id)
) $cs;" );
	dbDelta( "CREATE TABLE {$t['d']} (
  user_id bigint(20) unsigned NOT NULL,
  usage_date varchar(10) NOT NULL,
  used int(11) NOT NULL DEFAULT 0,
  extra int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (user_id,usage_date)
) $cs;" );
	dbDelta( "CREATE TABLE {$t['c']} (
  prize_id varchar(40) NOT NULL,
  day varchar(10) NOT NULL,
  n int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (prize_id,day)
) $cs;" );
	update_option( 'stlh_wheel_dbv', STLH_WHEEL_DBV, false );
}, 5 );

/* ───── کمکی‌ها ───── */

/** «روزِ مؤثر» به وقتِ تهران با ساعتِ ریست (مثلِ `_effective_usage_date` ربات) */
function stlh_wheel_day(): string {
	$h = (int) stlh_wheel_cfg()['settings']['reset_hour'];
	$d = new DateTime( 'now', new DateTimeZone( 'Asia/Tehran' ) );
	$d->modify( '-' . $h . ' hours' );
	return $d->format( 'Y-m-d' );
}

/** تومان ← واحدِ فروشگاه */
function stlh_wheel_store_amount( int $toman ): float {
	$k = function_exists( 'stlh_pg_toman' ) ? stlh_pg_toman( 1.0 ) : 1.0;
	return $k > 0 ? $toman / $k : (float) $toman;
}

function stlh_wheel_remaining( int $uid ): int {
	global $wpdb;
	$limit = (int) stlh_wheel_cfg()['settings']['daily_spin_limit'];
	if ( $limit <= 0 ) {
		return -1; // نامحدود
	}
	$t   = stlh_wheel_tables();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT used, extra FROM {$t['d']} WHERE user_id=%d AND usage_date=%s", $uid, stlh_wheel_day() ) );
	return max( 0, $limit + (int) ( $row->extra ?? 0 ) - (int) ( $row->used ?? 0 ) );
}

/** مصرفِ اتمیکِ یک چرخش */
function stlh_wheel_consume( int $uid ): bool {
	global $wpdb;
	$limit = (int) stlh_wheel_cfg()['settings']['daily_spin_limit'];
	$t     = stlh_wheel_tables();
	$day   = stlh_wheel_day();
	$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t['d']} (user_id, usage_date, used, extra) VALUES (%d, %s, 0, 0)", $uid, $day ) );
	$sql = $limit <= 0
		? $wpdb->prepare( "UPDATE {$t['d']} SET used=used+1 WHERE user_id=%d AND usage_date=%s", $uid, $day )
		: $wpdb->prepare( "UPDATE {$t['d']} SET used=used+1 WHERE user_id=%d AND usage_date=%s AND used < %d + extra", $uid, $day, $limit );
	return 1 === (int) $wpdb->query( $sql );
}

/** سقفِ کل (day='all') یا روزانه‌ی یک جایزه — گرفتنِ اتمیکِ یک سهم */
function stlh_wheel_claim_slot( string $pid, string $day, int $limit ): bool {
	global $wpdb;
	$t = stlh_wheel_tables();
	$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t['c']} (prize_id, day, n) VALUES (%s, %s, 0)", $pid, $day ) );
	return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$t['c']} SET n=n+1 WHERE prize_id=%s AND day=%s AND n < %d", $pid, $day, $limit ) );
}

function stlh_wheel_release_slot( string $pid, string $day ): void {
	global $wpdb;
	$t = stlh_wheel_tables();
	$wpdb->query( $wpdb->prepare( "UPDATE {$t['c']} SET n=n-1 WHERE prize_id=%s AND day=%s AND n > 0", $pid, $day ) );
}

function stlh_wheel_issued( string $pid, string $day ): int {
	global $wpdb;
	$t = stlh_wheel_tables();
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT n FROM {$t['c']} WHERE prize_id=%s AND day=%s", $pid, $day ) );
}

function stlh_wheel_pick( array $pool ): array {
	$total = array_sum( array_map( static fn( $p ) => $p['weight'], $pool ) );
	if ( $total <= 0 ) {
		return $pool[ array_rand( $pool ) ];
	}
	$r  = mt_rand() / mt_getrandmax() * $total;
	$up = 0.0;
	foreach ( $pool as $p ) {
		$up += $p['weight'];
		if ( $up >= $r ) {
			return $p;
		}
	}
	return end( $pool );
}

/** جایزه‌ی «بدونِ برد» وقتی همه‌ی جوایزِ محدود تمام شده‌اند */
function stlh_wheel_fallback(): array {
	return array_merge( stlh_wheel_prize_norm( [ 'title' => 'بدون برد', 'icon' => '😅', 'color' => '#6B7280', 'prize_type' => 'no_win' ], 0 ), [ 'id' => '' ] );
}

/** کوپنِ فروشگاه برای این کاربر */
function stlh_wheel_issue_coupon( int $uid, array $prize ): array {
	$user = get_userdata( $uid );
	$code = 'WH-' . strtoupper( wp_generate_password( 7, false, false ) );
	$c    = new WC_Coupon();
	$c->set_code( $code );
	$c->set_discount_type( 'discount_percent' === $prize['prize_type'] ? 'percent' : 'fixed_cart' );
	$c->set_amount( 'discount_percent' === $prize['prize_type'] ? min( 100, $prize['value'] ) : stlh_wheel_store_amount( $prize['value'] ) );
	$c->set_usage_limit( 1 );
	$c->set_usage_limit_per_user( 1 );
	$c->set_individual_use( true );
	if ( $user && $user->user_email ) {
		$c->set_email_restrictions( [ $user->user_email ] );
	}
	if ( $prize['min_purchase_amount'] > 0 ) {
		$c->set_minimum_amount( (string) stlh_wheel_store_amount( $prize['min_purchase_amount'] ) );
	}
	if ( $prize['product_ids'] ) {
		$c->set_product_ids( $prize['product_ids'] );
	}
	if ( $prize['category_ids'] ) {
		$c->set_product_categories( $prize['category_ids'] );
	}
	$exp = $prize['validity_hours'] > 0 ? time() + $prize['validity_hours'] * HOUR_IN_SECONDS : 0;
	if ( $exp ) {
		$c->set_date_expires( $exp );
	}
	$c->set_description( 'گردونه‌ی شانس — ' . $prize['title'] . ' — کاربر ' . $uid );
	$c->update_meta_data( 'stlh_wheel', 1 );
	$c->update_meta_data( 'stlh_wheel_user', $uid );
	if ( 'discount_percent' === $prize['prize_type'] && $prize['max_discount_value'] > 0 ) {
		$c->update_meta_data( 'stlh_wheel_max', stlh_wheel_store_amount( $prize['max_discount_value'] ) );
	}
	if ( ! $c->save() ) {
		throw new RuntimeException( 'coupon' );
	}
	return [ 'code' => $code, 'expires_at' => $exp ? gmdate( 'Y-m-d H:i:s', $exp ) : null ];
}

/* سقفِ تخفیفِ درصدی (`stlh_wheel_max`): مجموعِ تخفیفِ همان کوپن در یک محاسبه از سقف نمی‌گذرد */
$GLOBALS['stlh_wheel_given'] = [];
foreach ( [ 'woocommerce_before_calculate_totals', 'woocommerce_order_before_calculate_totals' ] as $stlh_h ) {
	add_action( $stlh_h, static function (): void {
		$GLOBALS['stlh_wheel_given'] = [];
	} );
}
unset( $stlh_h );
add_filter( 'woocommerce_coupon_get_discount_amount', static function ( $discount, $base, $item, $single, $coupon ) {
	if ( ! $coupon instanceof WC_Coupon || ! $coupon->is_type( 'percent' ) ) {
		return $discount;
	}
	$max = (float) $coupon->get_meta( 'stlh_wheel_max' );
	if ( $max <= 0 ) {
		return $discount;
	}
	$code = $coupon->get_code();
	$key  = $code . '|' . ( is_array( $item ) ? (string) ( $item['key'] ?? md5( serialize( $item ) ) ) : ( is_object( $item ) && method_exists( $item, 'get_id' ) ? $item->get_id() : '' ) );
	$given = $GLOBALS['stlh_wheel_given'][ $code ] ?? [];
	unset( $given[ $key ] ); // همان قلم دوباره حساب می‌شود
	$left  = max( 0.0, $max - array_sum( $given ) );
	$d     = min( (float) $discount, $left );
	$given[ $key ] = $d;
	$GLOBALS['stlh_wheel_given'][ $code ] = $given;
	return $d;
}, 20, 5 );

/* ───── وضعیت و چرخش (عینِ get_state / spin ربات) ───── */

function stlh_wheel_state( int $uid ): array {
	$cfg = stlh_wheel_cfg();
	$s   = $cfg['settings'];
	$out = [ 'enabled' => $s['enabled'], 'settings' => $s, 'campaign' => null, 'prizes' => [], 'spins_remaining' => 0, 'can_spin' => false ];
	$act = array_values( array_filter( $cfg['prizes'], static fn( $p ) => $p['active'] ) );
	if ( ! $s['enabled'] || ! $act ) {
		return $out;
	}
	$tw   = array_sum( array_map( static fn( $p ) => $p['weight'], $act ) ) ?: 1;
	$list = [];
	foreach ( $act as $p ) {
		$it = [ 'id' => $p['id'], 'title' => $p['title'], 'icon' => $p['icon'], 'color' => $p['color'], 'prize_type' => $p['prize_type'], 'sort_order' => $p['sort_order'], 'image_url' => $p['image_url'], 'wheel_label' => $p['wheel_label'] ?: $p['title'] ];
		if ( $s['show_odds'] ) {
			$it['percent'] = round( 100 * $p['weight'] / $tw, 1 );
		}
		$list[] = $it;
	}
	$rem = stlh_wheel_remaining( $uid );
	$out['campaign']        = [ 'id' => (int) $cfg['campaign']['id'], 'title' => (string) $cfg['campaign']['title'], 'ends_at' => $cfg['campaign']['ends_at'] ];
	$out['prizes']          = $list;
	$out['spins_remaining'] = $rem;
	$out['can_spin']        = 0 !== $rem;
	return $out;
}

/** پاسخِ نتیجه از ردیفِ ثبت‌شده — برای چرخشِ تازه و بازپخشِ تکراری یکسان */
function stlh_wheel_result( object $row, bool $dup = false ): array {
	if ( 'failed' === $row->status ) {
		return [ 'ok' => false, 'error' => 'صدور این جایزه با خطا مواجه شد — لطفاً دوباره تلاش کنید', 'duplicate' => $dup ];
	}
	$p = null;
	foreach ( stlh_wheel_cfg()['prizes'] as $x ) {
		if ( $x['id'] === $row->prize_id ) {
			$p = $x;
		}
	}
	return [
		'ok'              => true,
		'duplicate'       => $dup,
		'prize'           => [
			'id' => $row->prize_id, 'title' => $row->prize_title, 'icon' => $p['icon'] ?? ( 'no_win' === $row->prize_type ? '😅' : '🎁' ),
			'color' => $p['color'] ?? '#6B7280', 'prize_type' => $row->prize_type, 'image_url' => $p['image_url'] ?? '',
			'amount' => (int) $row->amount, 'discount_code' => $row->coupon_code, 'expires_at' => $row->expires_at, 'description' => $p['description'] ?? '',
		],
		'wallet_balance'  => null,
		'spins_remaining' => stlh_wheel_remaining( (int) $row->user_id ),
	];
}

function stlh_wheel_spin( int $uid, string $req ): array {
	global $wpdb;
	$cfg = stlh_wheel_cfg();
	if ( ! $cfg['settings']['enabled'] ) {
		return [ 'ok' => false, 'error' => 'گردونهٔ شانس در حال حاضر غیرفعال است' ];
	}
	$t   = stlh_wheel_tables();
	$req = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', $req ), 0, 120 ) ?: 'auto-' . wp_generate_uuid4();
	$now = current_time( 'mysql', true );
	$ip  = substr( preg_replace( '/[^0-9a-f:.]/i', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), 0, 64 );
	// رزروِ شناسه‌ی درخواست پیش از هر چیز — تکرارِ همان کلیک فقط نتیجه‌ی قبلی را بازپخش می‌کند
	$ins = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t['s']} (user_id, campaign_id, req_id, status, usage_date, ip, created_at) VALUES (%d, %d, %s, 'pending', %s, %s, %s)", $uid, (int) $cfg['campaign']['id'], $req, stlh_wheel_day(), $ip, $now ) );
	if ( 1 !== (int) $ins ) {
		for ( $i = 0; $i < 20; $i++ ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['s']} WHERE user_id=%d AND req_id=%s", $uid, $req ) );
			if ( ! $row || 'pending' !== $row->status ) {
				break;
			}
			usleep( 100000 );
		}
		if ( empty( $row ) ) {
			return [ 'ok' => false, 'error' => 'خطا در ثبت درخواست — دوباره تلاش کنید' ];
		}
		return 'pending' === $row->status ? [ 'ok' => false, 'error' => 'درخواست قبلی هنوز در حال پردازش است' ] : stlh_wheel_result( $row, true );
	}
	$sid = (int) $wpdb->insert_id;
	$fin = static function ( array $f ) use ( $wpdb, $t, $sid ): void {
		$wpdb->update( $t['s'], $f, [ 'id' => $sid ] );
	};
	if ( ! stlh_wheel_consume( $uid ) ) {
		$fin( [ 'status' => 'failed' ] );
		return [ 'ok' => false, 'error' => 'چرخش امروز شما تمام شده — فردا دوباره امتحان کنید', 'spins_remaining' => 0 ];
	}
	$day  = stlh_wheel_day();
	$pool = array_values( array_filter( $cfg['prizes'], static fn( $p ) => $p['active']
		&& ! ( $p['total_limit'] && stlh_wheel_issued( $p['id'], 'all' ) >= $p['total_limit'] )
		&& ! ( $p['daily_limit'] && stlh_wheel_issued( $p['id'], $day ) >= $p['daily_limit'] ) ) );
	$chosen = null;
	while ( $pool ) {
		$c = stlh_wheel_pick( $pool );
		if ( $c['daily_limit'] && ! stlh_wheel_claim_slot( $c['id'], $day, $c['daily_limit'] ) ) {
			$pool = array_values( array_filter( $pool, static fn( $p ) => $p['id'] !== $c['id'] ) );
			continue;
		}
		if ( $c['total_limit'] && ! stlh_wheel_claim_slot( $c['id'], 'all', $c['total_limit'] ) ) {
			if ( $c['daily_limit'] ) {
				stlh_wheel_release_slot( $c['id'], $day );
			}
			$pool = array_values( array_filter( $pool, static fn( $p ) => $p['id'] !== $c['id'] ) );
			continue;
		}
		$chosen = $c;
		break;
	}
	$chosen = $chosen ?: stlh_wheel_fallback();
	$f      = [ 'prize_id' => $chosen['id'], 'prize_type' => $chosen['prize_type'], 'prize_title' => $chosen['title'], 'amount' => $chosen['value'], 'status' => 'issued' ];
	try {
		if ( in_array( $chosen['prize_type'], [ 'discount_fixed', 'discount_percent' ], true ) ) {
			$cp               = stlh_wheel_issue_coupon( $uid, $chosen );
			$f['coupon_code'] = $cp['code'];
			$f['expires_at']  = $cp['expires_at'];
		} elseif ( 'extra_spin' === $chosen['prize_type'] ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . $t['d'] . ' SET extra=extra+%d WHERE user_id=%d AND usage_date=%s', max( 1, $chosen['value'] ), $uid, $day ) );
		}
	} catch ( Throwable $e ) {
		$f['status'] = 'failed';
	}
	$fin( $f );
	if ( 'failed' === $f['status'] ) {
		return [ 'ok' => false, 'error' => 'خطا در صدور جایزه — لطفاً با پشتیبانی تماس بگیرید' ];
	}
	if ( 'physical_gift' === $chosen['prize_type'] ) {
		stlh_wheel_mail_admin( 'برنده‌ی هدیه‌ی گردونه: ' . $chosen['title'], "کاربر {$uid} برنده‌ی «{$chosen['title']}» شد؛ آدرس را در «جوایز من» ثبت می‌کند." );
	}
	return stlh_wheel_result( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['s']} WHERE id=%d", $sid ) ) );
}

function stlh_wheel_mail_admin( string $subject, string $body ): void {
	wp_mail( (string) get_option( 'admin_email' ), '[' . wp_specialchars_decode( get_bloginfo( 'name' ) ) . '] ' . $subject, $body );
}

/** «جوایز من»: کدهای تخفیف (وضعیتِ واقعیِ کوپن) + چرخشِ اضافه + هدیه‌ی فیزیکی — همان شکلِ `/me/prizes` ربات */
function stlh_wheel_prizes_of( int $uid ): array {
	global $wpdb;
	$t     = stlh_wheel_tables();
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['s']} WHERE user_id=%d AND status<>'pending' AND prize_type IN ('discount_fixed','discount_percent','extra_spin','physical_gift') ORDER BY id DESC LIMIT 100", $uid ) );
	$cfg   = [];
	foreach ( stlh_wheel_cfg()['prizes'] as $p ) {
		$cfg[ $p['id'] ] = $p;
	}
	$items = [];
	foreach ( $rows as $r ) {
		$created = $r->created_at;
		if ( 'failed' === $r->status ) {
			$items[] = [ 'kind' => str_starts_with( $r->prize_type, 'discount' ) ? 'discount' : $r->prize_type, 'spin_id' => (int) $r->id, 'title' => $r->prize_title, 'amount' => 0, 'status' => 'failed', 'source' => 'wheel', 'created_at' => $created, 'code' => '', 'type' => 'fixed', 'value' => 0 ];
			continue;
		}
		if ( str_starts_with( $r->prize_type, 'discount' ) ) {
			$p      = $cfg[ $r->prize_id ] ?? [];
			$status = 'active';
			$cid    = function_exists( 'wc_get_coupon_id_by_code' ) ? wc_get_coupon_id_by_code( $r->coupon_code ) : 0;
			if ( ! $cid ) {
				$status = 'inactive';
			} else {
				$c = new WC_Coupon( $cid );
				if ( $c->get_usage_count() >= max( 1, (int) $c->get_usage_limit() ) ) {
					$status = 'used';
				} elseif ( $c->get_date_expires() && $c->get_date_expires()->getTimestamp() < time() ) {
					$status = 'expired';
				}
			}
			$pids    = (array) ( $p['product_ids'] ?? [] );
			$items[] = [
				'kind' => 'discount', 'spin_id' => (int) $r->id, 'code' => $r->coupon_code,
				'type' => 'discount_percent' === $r->prize_type ? 'percent' : 'fixed', 'value' => (int) $r->amount,
				'max_value' => (int) ( $p['max_discount_value'] ?? 0 ), 'min_amount' => (int) ( $p['min_purchase_amount'] ?? 0 ),
				'status' => $status, 'product_id' => 1 === count( $pids ) ? $pids[0] : null, 'category_id' => null,
				'scope_label' => $pids || ! empty( $p['category_ids'] ) ? 'محصولاتِ منتخب' : 'کل فروشگاه',
				'starts_at' => null, 'expires_at' => $r->expires_at, 'description' => $p['description'] ?? '',
				'source' => 'wheel', 'created_at' => $created,
			];
			continue;
		}
		$status = 'applied';
		if ( 'physical_gift' === $r->prize_type ) {
			$status = [ '' => 'needs_address', 'pending' => 'pending_shipment', 'sent' => 'shipped' ][ $r->claim_status ] ?? 'pending_shipment';
		}
		$items[] = [ 'kind' => $r->prize_type, 'spin_id' => (int) $r->id, 'title' => $r->prize_title, 'amount' => (int) $r->amount, 'status' => $status, 'source' => 'wheel', 'created_at' => $created ];
	}
	return $items;
}

/* ───── REST ───── */

function stlh_wheel_auth(): int {
	return (int) get_current_user_id();
}

function stlh_wheel_need_login(): WP_REST_Response {
	return new WP_REST_Response( [ 'ok' => false, 'error' => 'login', 'detail' => 'برای چرخاندنِ گردونه وارد حسابِ کاربری شوید.', 'login_url' => stlh_wheel_login_url() ], 401 );
}

function stlh_wheel_login_url(): string {
	$back = stlh_wheel_url();
	$acc  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
	return $acc ? add_query_arg( 'redirect_to', rawurlencode( $back ), $acc ) : wp_login_url( $back );
}

/** شمارنده‌ی ساده برای هر کاربر (ضدِ سیل) */
function stlh_wheel_limited( int $uid, string $kind, int $max, int $secs ): bool {
	$k = 'stlh_wh_rl_' . $kind . '_' . $uid;
	$n = (int) get_transient( $k );
	if ( $n >= $max ) {
		return true;
	}
	set_transient( $k, $n + 1, $secs );
	return false;
}

add_action( 'rest_api_init', static function (): void {
	$pub = static function ( string $path, string $method, callable $cb ): void {
		register_rest_route( 'stland/v1', '/wheel' . $path, [ 'methods' => $method, 'permission_callback' => '__return_true', 'callback' => $cb ] );
	};
	$adm = static function ( string $path, string $method, callable $cb ): void {
		register_rest_route( 'stland/v1', '/wheel' . $path, [ 'methods' => $method, 'permission_callback' => static fn() => current_user_can( 'manage_options' ), 'callback' => $cb ] );
	};

	$pub( '/state', 'GET', static function () {
		$uid = stlh_wheel_auth();
		return $uid ? [ 'ok' => true ] + stlh_wheel_state( $uid ) : stlh_wheel_need_login();
	} );
	$pub( '/spin', 'POST', static function ( WP_REST_Request $r ) {
		$uid = stlh_wheel_auth();
		if ( ! $uid ) {
			return stlh_wheel_need_login();
		}
		if ( stlh_wheel_limited( $uid, 'spin', 20, 60 ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'error' => 'درخواست‌های زیاد — کمی صبر کنید' ], 429 );
		}
		return stlh_wheel_spin( $uid, (string) ( $r->get_json_params()['client_request_id'] ?? '' ) );
	} );
	$pub( '/me/prizes', 'GET', static function () {
		$uid = stlh_wheel_auth();
		return $uid ? [ 'ok' => true, 'items' => stlh_wheel_prizes_of( $uid ) ] : stlh_wheel_need_login();
	} );
	$pub( '/me/wheel-activity', 'GET', static function () {
		global $wpdb;
		$uid = stlh_wheel_auth();
		if ( ! $uid ) {
			return stlh_wheel_need_login();
		}
		$t = stlh_wheel_tables();
		return [ 'ok' => true, 'items' => $wpdb->get_results( $wpdb->prepare( "SELECT prize_type, prize_title, amount, coupon_code AS discount_code, status, created_at FROM {$t['s']} WHERE user_id=%d AND status='issued' ORDER BY id DESC LIMIT 8", $uid ), ARRAY_A ) ];
	} );
	$pub( '/gift-claim', 'POST', static function ( WP_REST_Request $r ) {
		global $wpdb;
		$uid = stlh_wheel_auth();
		if ( ! $uid ) {
			return stlh_wheel_need_login();
		}
		if ( stlh_wheel_limited( $uid, 'gift', 5, HOUR_IN_SECONDS ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'error' => 'درخواست‌های زیاد — کمی بعد دوباره تلاش کنید' ], 429 );
		}
		$b    = (array) $r->get_json_params();
		$name = mb_substr( sanitize_text_field( (string) ( $b['full_name'] ?? '' ) ), 0, 120 );
		$tel  = mb_substr( sanitize_text_field( (string) ( $b['phone'] ?? '' ) ), 0, 40 );
		$addr = mb_substr( sanitize_textarea_field( (string) ( $b['address'] ?? '' ) ), 0, 800 );
		$sid  = absint( $b['spin_id'] ?? 0 );
		if ( ! $sid || '' === $name || '' === $tel || '' === $addr ) {
			return new WP_REST_Response( [ 'ok' => false, 'error' => 'همهٔ فیلدها (نام، تلفن، آدرس) لازم است' ], 400 );
		}
		$t   = stlh_wheel_tables();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['s']} WHERE id=%d AND user_id=%d AND prize_type='physical_gift' AND status='issued'", $sid, $uid ) );
		if ( ! $row ) {
			return new WP_REST_Response( [ 'ok' => false, 'error' => 'این جایزه پیدا نشد' ], 400 );
		}
		if ( 'sent' === $row->claim_status ) {
			return new WP_REST_Response( [ 'ok' => false, 'error' => 'این هدیه ارسال شده و دیگر قابلِ ویرایش نیست' ], 400 );
		}
		$wpdb->update( $t['s'], [ 'claim_name' => $name, 'claim_phone' => $tel, 'claim_address' => $addr, 'claim_status' => 'pending' ], [ 'id' => $sid ] );
		stlh_wheel_mail_admin( 'آدرسِ برنده‌ی هدیه‌ی گردونه', "جایزه: {$row->prize_title}\nنام: {$name}\nتلفن: {$tel}\nآدرس: {$addr}\nشماره‌ی چرخش: {$sid}" );
		return [ 'ok' => true ];
	} );

	/* Novo — قرارداد: docs/novo-wheel.md */
	$adm( '/config', 'GET', static function () {
		return [ 'ok' => true, 'pushed' => (bool) get_option( STLH_WHEEL_OPT ), 'config' => stlh_wheel_cfg() ];
	} );
	$adm( '/config', 'POST', static function ( WP_REST_Request $r ) {
		$in  = (array) $r->get_json_params();
		$new = (array) get_option( STLH_WHEEL_OPT, [] );
		foreach ( [ 'settings', 'campaign', 'prizes', 'version' ] as $k ) {
			if ( ! array_key_exists( $k, $in ) ) {
				continue;
			}
			if ( null === $in[ $k ] ) {
				unset( $new[ $k ] );
				continue;
			}
			$new[ $k ] = 'version' === $k ? sanitize_text_field( (string) $in[ $k ] ) : ( function_exists( 'stlh_ipt_clean' ) ? stlh_ipt_clean( (array) $in[ $k ] ) : (array) $in[ $k ] );
		}
		$new['updated'] = gmdate( 'c' );
		update_option( STLH_WHEEL_OPT, $new, false );
		if ( function_exists( 'stlh_cache_flush' ) ) {
			stlh_cache_flush();
		}
		return [ 'ok' => true, 'updated' => $new['updated'] ];
	} );
	$adm( '/spins', 'GET', static function ( WP_REST_Request $r ) {
		global $wpdb;
		$t    = stlh_wheel_tables();
		$days = max( 1, min( 365, absint( $r['days'] ?: 30 ) ) );
		$type = sanitize_key( (string) ( $r['type'] ?? '' ) );
		$sql  = "SELECT s.id, s.user_id, u.display_name, u.user_email, s.prize_id, s.prize_type, s.prize_title, s.amount, s.coupon_code, s.expires_at, s.status, s.claim_status, s.created_at FROM {$t['s']} s LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id WHERE s.created_at >= %s AND s.status<>'pending'"
			. ( $type ? $wpdb->prepare( ' AND s.prize_type=%s', $type ) : '' ) . ' ORDER BY s.id DESC LIMIT 1000';
		return [ 'ok' => true, 'items' => $wpdb->get_results( $wpdb->prepare( $sql, gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ), ARRAY_A ) ];
	} );
	$adm( '/gifts', 'GET', static function ( WP_REST_Request $r ) {
		global $wpdb;
		$t  = stlh_wheel_tables();
		$st = sanitize_key( (string) ( $r['status'] ?? '' ) );
		$sql = "SELECT s.id, s.user_id, u.display_name, u.user_email, s.prize_title, s.claim_name, s.claim_phone, s.claim_address, s.claim_status, s.admin_note, s.created_at FROM {$t['s']} s LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id WHERE s.prize_type='physical_gift' AND s.status='issued'"
			. ( in_array( $st, [ 'none', 'pending', 'sent' ], true ) ? $wpdb->prepare( ' AND s.claim_status=%s', 'none' === $st ? '' : $st ) : '' ) . ' ORDER BY s.id DESC LIMIT 500';
		return [ 'ok' => true, 'items' => $wpdb->get_results( $sql, ARRAY_A ) ];
	} );
	$adm( '/gifts/(?P<id>\d+)', 'POST', static function ( WP_REST_Request $r ) {
		global $wpdb;
		$t   = stlh_wheel_tables();
		$id  = absint( $r['id'] );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['s']} WHERE id=%d AND prize_type='physical_gift'", $id ) );
		if ( ! $row ) {
			return new WP_REST_Response( [ 'ok' => false, 'error' => 'پیدا نشد' ], 404 );
		}
		$b  = (array) $r->get_json_params();
		$st = in_array( $b['status'] ?? '', [ 'pending', 'sent' ], true ) ? $b['status'] : $row->claim_status;
		$wpdb->update( $t['s'], [ 'claim_status' => $st, 'admin_note' => mb_substr( sanitize_text_field( (string) ( $b['note'] ?? $row->admin_note ) ), 0, 500 ) ], [ 'id' => $id ] );
		if ( 'sent' === $st && 'sent' !== $row->claim_status && ( $u = get_userdata( (int) $row->user_id ) ) && $u->user_email ) {
			wp_mail( $u->user_email, 'هدیه‌ی گردونه‌ی شانس ارسال شد', "هدیه‌ی «{$row->prize_title}» برایتان ارسال شد. ممنون که با استوک لند هستید." );
		}
		return [ 'ok' => true ];
	} );
	$adm( '/stats', 'GET', static function ( WP_REST_Request $r ) {
		global $wpdb;
		$t    = stlh_wheel_tables();
		$days = max( 1, min( 365, absint( $r['days'] ?: 30 ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT prize_id, prize_title, prize_type, COUNT(*) n, COUNT(DISTINCT user_id) users FROM {$t['s']} WHERE status='issued' AND created_at >= %s GROUP BY prize_id, prize_title, prize_type", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ), ARRAY_A );
		foreach ( $rows as &$x ) {
			if ( str_starts_with( $x['prize_type'], 'discount' ) ) {
				$x['used'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='usage_count' JOIN {$t['s']} s ON s.coupon_code=p.post_title WHERE p.post_type='shop_coupon' AND s.prize_id=%s AND m.meta_value>0 AND s.created_at >= %s", $x['prize_id'], gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
			}
		}
		return [ 'ok' => true, 'items' => $rows ];
	} );
} );

/* ───── برگه و کدِ کوتاه ───── */

function stlh_wheel_url(): string {
	[ $opt, $slug, $label, $sc ] = STLH_WHEEL_PAGE;
	$id = stlh_own_page( $opt, $slug, stlh_label( $label ), $sc, true );
	return $id ? (string) get_permalink( $id ) : home_url( '/' . $slug . '/' );
}

add_action( 'init', static function (): void {
	if ( get_option( 'stlh_wheel_page' ) !== STLH_VER ) {
		stlh_wheel_url();
		update_option( 'stlh_wheel_page', STLH_VER, false );
	}
} );

add_shortcode( 'stl_wheel', static function (): string {
	wp_enqueue_script( 'stlh-wheel', STLH_URL . 'assets/wheel/wheel.js', [], STLH_VER, true );
	wp_localize_script( 'stlh-wheel', 'stlhWheel', [
		'rest'  => esc_url_raw( rest_url( 'stland/v1/wheel' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'login' => stlh_wheel_login_url(),
		'home'  => home_url( '/' ),
		'title'  => stlh_label( 'wheel_title' ),
		'logged' => is_user_logged_in(),
		'ajax'   => admin_url( 'admin-ajax.php' ),
	] );
	$css = (string) file_get_contents( STLH_DIR . 'assets/wheel/wheel.css' );
	return '<div class="stlh-wheel" dir="rtl"><style>' . $css . '</style><div id="post-title" class="stlh-wh-title"></div>'
		. '<div id="post-body" class="stlh-wh-body"><noscript>' . esc_html( stlh_label( 'ipt_noscript' ) ) . '</noscript></div></div>';
} );

// برگه‌ی گردونه به کاربرِ وارد‌شده وابسته است — نه نمایه‌ی گوگل، نه حافظه‌ی موقتِ صفحه
add_filter( 'wpseo_robots', static fn( $r ) => is_page( (int) get_option( STLH_WHEEL_PAGE[0] ) ) ? 'noindex, follow' : $r );
add_action( 'template_redirect', static function (): void {
	if ( is_page( (int) get_option( STLH_WHEEL_PAGE[0] ) ) ) {
		do_action( 'litespeed_control_set_nocache', 'stland wheel' );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}
} );

/* «حساب من» ← «گردونه‌ی شانس» */
add_filter( 'woocommerce_account_menu_items', static function ( $items ) {
	if ( ! stlh_wheel_cfg()['settings']['enabled'] ) {
		return $items;
	}
	$out = [];
	foreach ( (array) $items as $k => $v ) {
		if ( 'customer-logout' === $k ) {
			$out['stlh-wheel'] = stlh_label( 'wheel_title' );
		}
		$out[ $k ] = $v;
	}
	return isset( $out['stlh-wheel'] ) ? $out : $out + [ 'stlh-wheel' => stlh_label( 'wheel_title' ) ];
}, 30 );
add_filter( 'woocommerce_get_endpoint_url', static fn( $url, $endpoint ) => 'stlh-wheel' === $endpoint ? stlh_wheel_url() : $url, 10, 2 );
