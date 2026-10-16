<?php
/**
 * «لیست آک و کارکرده» — صفحه‌ی `stland.ir/list`.
 *
 * صاحب پروژه (۲۴ مهر ۱۴۰۵): «یک لیست هم برود توی سایت در صفحه‌ی جدیدی، لیستِ آک و کارکرده، گرافیکی و جذاب». Novo
 * (حسابداری) تعیین می‌کند **کدام کالا** در کدام بخش است — همان تیک‌هایی که مینی‌اپِ ربات و پستِ کانال هم از آن
 * می‌خوانند (`sthesabland/src/lib/app-list-site.ts`) — و فقط شناسه‌ی محصول و چیپ‌ها را می‌فرستد. قیمت، حراج، موجودی،
 * عکس و نشانی همین‌جا زنده از ووکامرس خوانده می‌شود (مالکیتِ داده: حسابداری آن‌ها را جدا به سایت می‌برد)؛ پس کالای
 * فروخته‌شده بی هیچ فرستادنِ تازه‌ای از صفحه می‌رود.
 *
 * • `POST /wp-json/stland/v1/app-list` — فقط مدیرِ فروشگاه (رمزِ برنامه‌ی وردپرس که حسابداری دارد).
 * • برگه‌ی `list` یک بار ساخته می‌شود (`stlh_app_list_page`) و قالبِ خودش را دارد؛ اگر صاحب فروشگاه برگه را
 *   پاک یا نامکش را عوض کرد، دوباره ساخته نمی‌شود مگر با رسیدنِ فهرستِ تازه.
 * • HTML از کشِ بخش‌ها (`stlh_cached`) می‌آید و هر تغییرِ محصول یا خودِ فهرست نسل را بالا می‌برد.
 */

defined( 'ABSPATH' ) || exit;

const STLH_APP_LIST_OPT  = 'stlh_app_list';
const STLH_APP_LIST_PAGE = 'stlh_app_list_page';

add_action( 'rest_api_init', function (): void {
	register_rest_route( 'stland/v1', '/app-list', [
		'methods'             => 'POST',
		'permission_callback' => static fn() => current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ),
		'callback'            => 'stlh_app_list_receive',
	] );
} );

/** فهرستِ رسیده از حسابداری — فقط شکلِ مجاز، بقیه دور ریخته می‌شود */
function stlh_app_list_receive( WP_REST_Request $req ): array {
	$sections = [];
	foreach ( (array) $req->get_param( 'sections' ) as $s ) {
		$key = is_array( $s ) ? (string) ( $s['key'] ?? '' ) : '';
		if ( ! in_array( $key, [ 'new', 'used' ], true ) ) {
			continue;
		}
		$items = [];
		foreach ( array_slice( (array) ( $s['items'] ?? [] ), 0, 300 ) as $it ) {
			$id = is_array( $it ) ? absint( $it['id'] ?? 0 ) : 0;
			if ( ! $id ) {
				continue;
			}
			$chips = array_values( array_filter( array_map(
				static fn( $c ) => is_scalar( $c ) ? mb_substr( sanitize_text_field( (string) $c ), 0, 40 ) : '',
				array_slice( (array) ( $it['chips'] ?? [] ), 0, 6 )
			), 'strlen' ) );
			$items[] = [ 'id' => $id, 'chips' => $chips ];
		}
		$sections[ $key ] = [ 'key' => $key, 'label' => sanitize_text_field( (string) ( $s['label'] ?? '' ) ), 'items' => $items ];
	}
	update_option( STLH_APP_LIST_OPT, [ 'sections' => array_values( $sections ), 'at' => time() ], false );
	$page  = stlh_app_list_page_id( true ); // نسل را `update_option_…` پایین بالا می‌برد
	$count = array_sum( array_map( static fn( $s ) => count( $s['items'] ), $sections ) );
	if ( $page ) {
		stlh_app_list_seo_refresh( $page, $count );
	}
	return [ 'ok' => true, 'count' => $count, 'url' => stlh_app_list_url() ];
}

/** شناسه‌ی برگه‌ی `list`؛ با `$create` اگر نیست ساخته می‌شود */
function stlh_app_list_page_id( bool $create = false ): int {
	$id = (int) get_option( STLH_APP_LIST_PAGE, 0 );
	if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
		return $id;
	}
	if ( ! $create ) {
		return 0;
	}
	$found = get_page_by_path( 'list' );
	if ( $found instanceof WP_Post && str_contains( (string) $found->post_content, '[stl_app_list]' ) ) {
		$id = $found->ID;
	} else {
		$id = (int) wp_insert_post( [
			'post_type'    => 'page',
			'post_name'    => 'list',
			'post_title'   => stlh_label( 'list_title' ),
			'post_status'  => 'publish',
			'post_content' => '[stl_app_list]',
		] );
	}
	if ( $id ) {
		update_option( STLH_APP_LIST_PAGE, $id, false );
	}
	return $id;
}

/*
 * ─── سئو (۲۵ مهر ۱۴۰۵، تصمیمِ کارشناسی با اجازه‌ی صاحب فروشگاه) ───
 * **یک نشانیِ ثابت** (`/list/`) که هر بار لیست عوض شد همان‌جا تازه می‌شود — نه صفحه‌ی تازه برای هر روز. صفحه‌های روزانه
 * محتوای تکراری و کم‌عمر می‌سازند و اعتبارِ جستجو بینشان پخش می‌شود؛ یک صفحه‌ی ماندگار با تاریخِ «به‌روزشده» همان
 * چیزی است که گوگل برای «قیمتِ روز» بالا می‌آورد. پس با هر لیستِ تازه: تاریخِ ویرایشِ برگه (نقشه‌ی سایت و
 * `dateModified`)، عنوان و توضیحِ یواست با تاریخ و تعداد، تاریخِ دیده‌شدنی زیرِ عنوان، و داده‌ی ساختاریافته‌ی `ItemList`.
 * اگر روزی نامکِ برگه عوض شود، خودِ وردپرس از نشانیِ قبلی به تازه هدایت می‌کند (`_wp_old_slug`).
 */
function stlh_jalali( int $ts ): string {
	$d = new DateTime( '@' . $ts );
	$d->setTimezone( new DateTimeZone( 'Asia/Tehran' ) );
	[ $gy, $gm, $gd ] = array_map( 'intval', explode( '-', $d->format( 'Y-n-j' ) ) );
	$g_d_m = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
	$gy2   = $gm > 2 ? $gy + 1 : $gy;
	$days  = 355666 + 365 * $gy + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + 33 * intdiv( $days, 12053 );
	$days %= 12053;
	$jy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy  += intdiv( $days - 1, 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + intdiv( $days, 31 );
		$jd = 1 + $days % 31;
	} else {
		$jm = 7 + intdiv( $days - 186, 30 );
		$jd = 1 + ( $days - 186 ) % 30;
	}
	$months = [ 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];
	return stlh_fa( $jd . ' ' . $months[ $jm - 1 ] . ' ' . $jy );
}

function stlh_app_list_seo_refresh( int $page, int $count ): void {
	$vars = [ '{تاریخ}' => stlh_jalali( time() ), '{n}' => stlh_fa( $count ) ];
	update_post_meta( $page, '_yoast_wpseo_title', stlh_label( 'list_seo_title', $vars ) . ' | %%sitename%%' );
	update_post_meta( $page, '_yoast_wpseo_metadesc', stlh_label( 'list_seo_desc', $vars ) );
	// تاریخِ ویرایشِ برگه = آخرین به‌روزرسانیِ لیست (نقشه‌ی سایت، `dateModified`)
	wp_update_post( [ 'ID' => $page ] );
}

/** داده‌ی ساختاریافته: فهرستِ کالاها با قیمت و موجودی — برای نتیجه‌ی جستجو */
function stlh_app_list_schema( array $sections ): string {
	$items = [];
	$pos   = 0;
	$irr   = function_exists( 'get_woocommerce_currency' ) && 'IRT' === get_woocommerce_currency() ? 10 : 1;
	foreach ( $sections as $s ) {
		foreach ( $s['cards'] as $c ) {
			$p   = $c['p'];
			$img = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : '';
			$items[] = [
				'@type'    => 'ListItem',
				'position' => ++$pos,
				'item'     => array_filter( [
					'@type'  => 'Product',
					'name'   => $p->get_name(),
					'url'    => $p->get_permalink(),
					'image'  => $img ?: null,
					'offers' => [
						'@type'         => 'Offer',
						'price'         => (string) round( (float) $p->get_price() * $irr ),
						'priceCurrency' => 'IRR',
						'availability'  => 'https://schema.org/InStock',
					],
				] ),
			];
		}
	}
	if ( ! $items ) {
		return '';
	}
	$schema = [ '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => stlh_label( 'list_title' ), 'numberOfItems' => $pos, 'itemListElement' => $items ];
	return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
}

function stlh_app_list_url(): string {
	$id = stlh_app_list_page_id();
	return $id ? (string) get_permalink( $id ) : home_url( '/list/' );
}

// قالبِ خودِ افزونه: سربرگ و پانویس از قالبِ سایت، میانه از ما (مثلِ صفحه‌ی اصلی)
add_filter( 'template_include', function ( string $template ): string {
	$id = stlh_app_list_page_id();
	if ( ! $id || is_admin() || ! is_page( $id ) || isset( $_GET['elementor-preview'] ) ) {
		return $template;
	}
	return STLH_DIR . 'templates/app-list.php';
}, 99 );

/*
 * CSSِ این صفحه داخلِ خودِ صفحه (۲۴ مهر ۱۴۰۵): صاحب فروشگاه (واردشده با حسابِ مدیر) صفحه را بی‌طرح دید در حالی که
 * برای مهمان درست بود — فایلِ ترکیب‌شده‌ی LiteSpeed برای آن نسخه‌ی صفحه به‌روز نبود. حدودِ ۶ کیلوبایت است؛ داخلِ
 * <head> هیچ‌وقت از فایلِ HTMLاش جدا نمی‌افتد.
 */
add_action( 'wp_head', static function (): void {
	$id = stlh_app_list_page_id();
	if ( ! $id || ! is_page( $id ) ) {
		return;
	}
	$css = (string) @file_get_contents( STLH_DIR . 'assets/css/app-list.css' );
	if ( '' !== $css ) {
		echo '<style id="stlh-app-list-css">' . preg_replace( '/\s+/', ' ', $css ) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- فایلِ خودِ افزونه
	}
}, 20 );

add_filter( 'stlh_force_enqueue', static function ( bool $on ): bool {
	$id = stlh_app_list_page_id();
	return $on || ( $id && is_page( $id ) );
} );

add_action( 'update_option_' . STLH_APP_LIST_OPT, 'stlh_cache_flush', 10, 0 );
// کشِ LiteSpeed فقط صفحه‌ی اصلی را با نسل پاک می‌کند؛ این برگه هم
add_action( 'stlh_cache_flushed', static function (): void {
	if ( stlh_app_list_page_id() ) {
		do_action( 'litespeed_purge_url', stlh_app_list_url() );
	}
} );

add_shortcode( 'stl_app_list', static function (): string {
	stlh_assets();
	return stlh_cached( 'app-list', 'stlh_app_list_html' );
} );

/** کالاهایی که همین حالا خریدنی‌اند، به ترتیبِ فهرستِ حسابداری */
function stlh_app_list_sections(): array {
	$data = (array) get_option( STLH_APP_LIST_OPT, [] );
	$out  = [];
	foreach ( (array) ( $data['sections'] ?? [] ) as $s ) {
		$cards = [];
		foreach ( (array) ( $s['items'] ?? [] ) as $it ) {
			$p = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $it['id'] ) : null;
			if ( ! $p || 'publish' !== $p->get_status() || ! $p->is_in_stock() || ! $p->is_visible() ) {
				continue;
			}
			$cards[] = [ 'p' => $p, 'chips' => (array) ( $it['chips'] ?? [] ) ];
		}
		if ( $cards ) {
			$out[] = [ 'key' => (string) $s['key'], 'label' => stlh_label( 'list_sec_' . $s['key'] ) ?: (string) $s['label'], 'cards' => $cards ];
		}
	}
	return $out;
}

function stlh_app_list_card( WC_Product $p, array $chips ): string {
	$chip_html = '';
	foreach ( $chips as $c ) {
		$chip_html .= '<span class="stlh-al-chip">' . esc_html( stlh_fa( $c ) ) . '</span>';
	}
	return sprintf(
		'<a class="stlh-al-card" href="%s"><span class="stlh-al-img">%s</span><span class="stlh-al-body"><span class="stlh-al-name">%s</span>%s</span><span class="stlh-al-price">%s</span></a>',
		esc_url( $p->get_permalink() ),
		stlh_card_image( $p ),
		esc_html( $p->get_name() ),
		$chip_html ? '<span class="stlh-al-chips">' . $chip_html . '</span>' : '',
		stlh_price( $p )
	);
}

function stlh_app_list_html(): string {
	$sections = stlh_app_list_sections();
	$data     = (array) get_option( STLH_APP_LIST_OPT, [] );
	$total    = array_sum( array_map( static fn( $s ) => count( $s['cards'] ), $sections ) );

	$tabs = '';
	foreach ( $sections as $s ) {
		$tabs .= sprintf(
			'<a class="stlh-al-tab" href="#list-%s">%s <b>%s</b></a>',
			esc_attr( $s['key'] ),
			esc_html( $s['label'] ),
			esc_html( stlh_fa( count( $s['cards'] ) ) )
		);
	}
	$html = '<div class="stlh-al">'
		. '<header class="stlh-al-hero"><h1>' . esc_html( stlh_label( 'list_title' ) ) . '</h1>'
		. '<p>' . esc_html( stlh_label( 'list_sub' ) ) . '</p>'
		. ( ! empty( $data['at'] ) ? '<p class="stlh-al-upd"><time datetime="' . esc_attr( gmdate( 'c', (int) $data['at'] ) ) . '">'
			. esc_html( stlh_label( 'list_updated', [ '{تاریخ}' => stlh_jalali( (int) $data['at'] ) ] ) ) . '</time></p>' : '' )
		. ( $tabs ? '<nav class="stlh-al-tabs">' . $tabs . '</nav>' : '' )
		. '</header>';

	if ( ! $total ) {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		return $html . '<p class="stlh-al-empty">' . esc_html( stlh_label( 'list_empty' ) )
			. ' <a href="' . esc_url( $shop ) . '">' . esc_html( stlh_label( 'list_shop' ) ) . '</a></p></div>';
	}
	foreach ( $sections as $s ) {
		$cards = '';
		foreach ( $s['cards'] as $c ) {
			$cards .= stlh_app_list_card( $c['p'], $c['chips'] );
		}
		$html .= sprintf(
			'<section class="stlh-al-sec" id="list-%s"><h2>%s <span>%s</span></h2><div class="stlh-al-grid">%s</div></section>',
			esc_attr( $s['key'] ),
			esc_html( $s['label'] ),
			esc_html( stlh_label( 'list_count', [ '{n}' => stlh_fa( count( $s['cards'] ) ) ] ) ),
			$cards
		);
	}
	return $html . '<p class="stlh-al-foot">' . esc_html( stlh_label( 'list_foot' ) ) . '</p></div>' . stlh_app_list_schema( $sections );
}
