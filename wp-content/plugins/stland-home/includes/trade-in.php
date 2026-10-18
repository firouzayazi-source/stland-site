<?php
/**
 * «معاوضه‌ی گوشیِ قدیمی» — صفحه‌ی `stland.ir/trade-in/` و فرمش.
 *
 * صاحب فروشگاه (۲۶ مهر ۱۴۰۵، ارتقای افزونه): معاوضه جزوِ خدمات است (`stlh_store_facts()`: «معاوضه‌ی همه‌ی گوشی‌های
 * قدیمی») ولی در سایت راهی برای ثبتش نبود. فرم مدل، حافظه، سلامتِ باتری، وضعیت و گوشیِ دلخواه را می‌گیرد؛ درخواست در
 * خودِ وردپرس می‌ماند («درخواست‌های معاوضه» در پیشخوان، و کارتِ «حالِ سایت») و یک نامه به ایمیلِ مدیر می‌رود؛ بعد مشتری
 * با یک دکمه همان خلاصه را در واتس‌اپ می‌فرستد تا عکس‌ها را همان‌جا بدهد — بارگذاریِ عکس روی هاستِ اشتراکی جا و
 * خطرِ فایلِ آلوده دارد.
 *
 * ⛔ هرزنامه: فیلدِ پنهان (`website`) و سقفِ ۵ درخواست در ساعت برای هر آی‌پی. صفحه در حافظه‌ی موقت می‌ماند و فرم به
 * `admin-post.php` می‌رود، پس کد امنیتیِ یک‌بارمصرفِ وردپرس (که در صفحه‌ی ذخیره‌شده کهنه می‌شد) به کار نمی‌آید.
 */

defined( 'ABSPATH' ) || exit;

const STLH_TRADEIN_PT   = 'stlh_tradein';
const STLH_TRADEIN_PAGE = 'stlh_page_tradein';

add_action( 'init', static function (): void {
	register_post_type( STLH_TRADEIN_PT, [
		'label'        => 'درخواست‌های معاوضه',
		'labels'       => [ 'name' => 'درخواست‌های معاوضه', 'singular_name' => 'درخواستِ معاوضه', 'menu_name' => 'معاوضه' ],
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-update',
		'supports'     => [ 'title', 'editor' ],
		'capabilities' => [ 'create_posts' => 'do_not_allow' ],
		'map_meta_cap' => true,
	] );
} );

/** شناسه‌ی برگه‌ی معاوضه؛ با `$create` اگر نیست ساخته می‌شود */
function stlh_tradein_page_id( bool $create = false ): int {
	return stlh_own_page( STLH_TRADEIN_PAGE, 'trade-in', stlh_label( 'tradein_title' ), '[stl_trade_in]', $create );
}

function stlh_tradein_url(): string {
	$id = stlh_tradein_page_id( true );
	return $id ? (string) get_permalink( $id ) : home_url( '/trade-in/' );
}

/**
 * برگه‌ای که افزونه یک بار می‌سازد (معاوضه، مقایسه). اگر صاحب فروشگاه پاکش کرد یا نامکش را عوض کرد، با همان نامک
 * پیدا یا دوباره ساخته می‌شود.
 */
function stlh_own_page( string $opt, string $slug, string $title, string $shortcode, bool $create ): int {
	$id = (int) get_option( $opt, 0 );
	if ( $id && 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
		return $id;
	}
	if ( ! $create ) {
		return 0;
	}
	$found = get_page_by_path( $slug );
	if ( $found instanceof WP_Post && str_contains( (string) $found->post_content, $shortcode ) && 'publish' === $found->post_status ) {
		$id = $found->ID;
	} else {
		$id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $title, 'post_status' => 'publish', 'post_content' => $shortcode ] );
	}
	if ( $id ) {
		update_option( $opt, $id, false );
	}
	return $id;
}

const STLH_TRADEIN_FIELDS = [
	'model'   => [ 'مدلِ گوشیِ شما', 'مثلاً آیفون ۱۳ پرو', true ],
	'storage' => [ 'حافظه', 'مثلاً ۱۲۸ گیگ', false ],
	'battery' => [ 'سلامتِ باتری (٪)', 'از تنظیمات ← باتری', false ],
	'state'   => [ 'وضعیتِ ظاهری و ایرادها', 'خط و خش، تعویضِ قطعه، ایراد…', false ],
	'want'    => [ 'گوشیِ دلخواه برای خرید', 'اختیاری', false ],
	'name'    => [ 'نامِ شما', '', true ],
	'phone'   => [ 'شماره‌ی تماس', '۰۹…', true ],
];

add_shortcode( 'stl_trade_in', static function (): string {
	$ok = isset( $_GET['ok'], $_GET['k'] ) ? (int) $_GET['ok'] : 0;
	if ( $ok && hash_equals( stlh_tradein_key( $ok ), (string) $_GET['k'] ) && STLH_TRADEIN_PT === get_post_type( $ok ) ) {
		$msg = stlh_label( 'tradein_msg', [ '{شماره}' => stlh_fa( $ok ), '{خلاصه}' => stlh_tradein_summary( $ok ) ] );
		return '<div class="stlh-ti"><p class="stlh-ti__done">' . esc_html( stlh_label( 'tradein_done' ) ) . '</p>'
			. '<a class="stlh-ti__wa" href="' . esc_url( stlh_wa_link( $msg ) ) . '" target="_blank" rel="noopener">' . esc_html( stlh_label( 'ask_whatsapp' ) ) . '</a></div>';
	}
	$err   = isset( $_GET['err'] ) ? '<p class="stlh-ti__err">' . esc_html( 'many' === $_GET['err'] ? 'درخواست‌های زیادی از این دستگاه رسیده؛ کمی بعد دوباره امتحان کنید یا تماس بگیرید.' : 'لطفاً مدل، نام و شماره‌ی تماس را کامل بنویسید.' ) . '</p>' : '';
	$rows  = '';
	foreach ( STLH_TRADEIN_FIELDS as $key => [ $label, $ph, $req ] ) {
		$tag   = 'state' === $key ? 'textarea' : 'input';
		$attrs = sprintf( 'name="%s" placeholder="%s"%s%s', esc_attr( $key ), esc_attr( $ph ), $req ? ' required' : '', 'phone' === $key ? ' inputmode="tel" dir="ltr"' : ( 'battery' === $key ? ' inputmode="numeric"' : '' ) );
		$field = 'textarea' === $tag ? "<textarea $attrs rows=\"3\"></textarea>" : "<input type=\"text\" $attrs>";
		$rows .= '<label class="stlh-ti__row"><span>' . esc_html( $label ) . ( $req ? ' *' : '' ) . '</span>' . $field . '</label>';
	}
	return '<div class="stlh-ti"><p class="stlh-ti__sub">' . esc_html( stlh_label( 'tradein_sub' ) ) . '</p>' . $err
		. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
		. '<input type="hidden" name="action" value="stlh_tradein">'
		. '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="stlh-ti__hp" aria-hidden="true">'
		. $rows . '<button type="submit">ثبتِ درخواستِ معاوضه</button></form></div>';
} );

function stlh_tradein_key( int $id ): string {
	return substr( wp_hash( 'stlh_tradein|' . $id ), 0, 12 );
}

function stlh_tradein_summary( int $id ): string {
	$parts = [];
	foreach ( [ 'model', 'storage', 'battery', 'state', 'want' ] as $k ) {
		$v = (string) get_post_meta( $id, 'stlh_' . $k, true );
		if ( '' !== $v ) {
			$parts[] = STLH_TRADEIN_FIELDS[ $k ][0] . ': ' . $v;
		}
	}
	return implode( ' — ', $parts );
}

foreach ( [ 'admin_post_stlh_tradein', 'admin_post_nopriv_stlh_tradein' ] as $stlh_hook ) {
	add_action( $stlh_hook, 'stlh_tradein_submit' );
}

function stlh_tradein_submit(): void {
	$back = stlh_tradein_url();
	if ( ! empty( $_POST['website'] ) ) { // ربات
		wp_safe_redirect( $back );
		exit;
	}
	$ip   = preg_replace( '/[^0-9a-f:.]/i', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$rate = 'stlh_ti_' . md5( $ip );
	$n    = (int) get_transient( $rate );
	if ( $n >= 5 ) {
		wp_safe_redirect( add_query_arg( 'err', 'many', $back ) );
		exit;
	}
	$v = [];
	foreach ( STLH_TRADEIN_FIELDS as $key => $def ) {
		$v[ $key ] = mb_substr( sanitize_textarea_field( wp_unslash( (string) ( $_POST[ $key ] ?? '' ) ) ), 0, 'state' === $key ? 500 : 80 );
	}
	if ( '' === $v['model'] || '' === $v['name'] || strlen( preg_replace( '/\D/', '', strtr( $v['phone'], array_flip( [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] ) ) ) ) < 10 ) {
		wp_safe_redirect( add_query_arg( 'err', 'fields', $back ) );
		exit;
	}
	set_transient( $rate, $n + 1, HOUR_IN_SECONDS );
	$id = wp_insert_post( [
		'post_type'    => STLH_TRADEIN_PT,
		'post_status'  => 'private',
		'post_title'   => 'معاوضه: ' . $v['model'] . ' — ' . $v['name'] . ' (' . $v['phone'] . ')',
		'post_content' => '',
	] );
	if ( ! $id || is_wp_error( $id ) ) {
		wp_safe_redirect( add_query_arg( 'err', 'fields', $back ) );
		exit;
	}
	foreach ( $v as $key => $val ) {
		update_post_meta( $id, 'stlh_' . $key, $val );
	}
	wp_update_post( [ 'ID' => $id, 'post_content' => esc_html( stlh_tradein_summary( $id ) . ' — ' . $v['name'] . ' ' . $v['phone'] ) ] );
	wp_mail( (string) get_option( 'admin_email' ), 'درخواستِ معاوضه‌ی تازه — ' . $v['model'], stlh_tradein_summary( $id ) . "\n" . $v['name'] . ' ' . $v['phone'] . "\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	wp_safe_redirect( add_query_arg( [ 'ok' => $id, 'k' => stlh_tradein_key( $id ) ], $back ) );
	exit;
}

// صفحه‌ی پس از ثبت (با ?ok=) هرگز در حافظه‌ی موقت نماند
add_action( 'template_redirect', static function (): void {
	$id = stlh_tradein_page_id();
	if ( $id && is_page( $id ) && ( isset( $_GET['ok'] ) || isset( $_GET['err'] ) ) ) {
		do_action( 'litespeed_control_set_nocache', 'trade-in result' );
	}
} );

add_action( 'wp_head', static function (): void {
	$id = stlh_tradein_page_id();
	if ( ! $id || ! is_page( $id ) ) {
		return;
	}
	echo '<style id="stlh-ti-css">'
		. '.stlh-ti{direction:rtl;max-width:560px;margin:0 auto 32px;font-size:14px}'
		. '.stlh-ti__sub{color:#555;margin:0 0 16px;line-height:1.9}'
		. '.stlh-ti__row{display:block;margin:0 0 12px}.stlh-ti__row span{display:block;font-weight:700;margin:0 0 4px}'
		. '.stlh-ti input[type=text],.stlh-ti textarea{width:100%;box-sizing:border-box;border:1px solid #dfe1e6;border-radius:12px;padding:10px 12px;font:inherit;background:#fff}'
		. '.stlh-ti button{width:100%;min-height:48px;border:0;border-radius:12px;background:#0a63ff;color:#fff;font-weight:800;font-size:15px;cursor:pointer}'
		// ⛔ نه `left:-9999px`: در صفحه‌ی راست‌به‌چپ همان ۹۹۹۹ پیکسل اسکرولِ افقی می‌ساخت و کلِ صفحه کج می‌شد (۲۶ مهر)
		. '.stlh-ti__hp{position:absolute!important;right:0!important;width:1px!important;height:1px!important;padding:0!important;border:0!important;overflow:hidden!important;clip:rect(0 0 0 0)!important;clip-path:inset(50%)!important;opacity:0}'
		. '.stlh-ti__done{background:#eafaf1;border-radius:12px;padding:12px 14px;line-height:1.9}.stlh-ti__err{background:#fdecef;border-radius:12px;padding:10px 14px}'
		. '.stlh-ti__wa{display:flex;align-items:center;justify-content:center;min-height:48px;border-radius:12px;background:#1fa855;color:#fff!important;font-weight:800;text-decoration:none!important;margin-top:12px}'
		. "</style>\n"; // phpcs:ignore
} );

// برگه‌ها با به‌روزرسانی پاک نمی‌شوند (`cache-tune.php`)؛ پس یک بار همین صفحه دوباره ساخته شود تا سبکِ درست برسد (۱.۶۵.۰)
add_action( 'init', static function (): void {
	if ( (int) get_option( 'stlh_tradein_css_rev', 0 ) >= 1 || ! defined( 'LSCWP_V' ) ) {
		return;
	}
	$id = stlh_tradein_page_id();
	if ( ! $id ) {
		return;
	}
	update_option( 'stlh_tradein_css_rev', 1, false );
	do_action( 'litespeed_purge_post', $id );
}, 45 );
