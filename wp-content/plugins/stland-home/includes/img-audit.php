<?php
/**
 * ممیزیِ عکس‌های خراب — فقط خواندن.
 *
 * صاحب فروشگاه (۲۳ مهر ۱۴۰۵): «اگر عکس یا آیکونی خراب شده، عکسِ درست بگذار». هر جایی که سایت به عکسی
 * از همین سایت ارجاع می‌دهد بررسی می‌شود که فایلش روی دیسک هست یا نه، و ارجاع به دامنه‌های دیگر جدا گزارش
 * می‌شود (عکسِ نمونه‌ی قالب از دامنه‌ی سازنده، که هر لحظه ممکن است برود).
 *
 *   GET /stland/v1/img-audit
 */
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function (): void {
	register_rest_route( 'stland/v1', '/img-audit', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		'callback'            => 'stlh_img_audit',
	] );
} );

/** آدرسِ آپلودِ همین سایت → مسیرِ دیسک؛ بیرونی → null */
function stlh_img_path( string $url ): ?string {
	$up   = wp_upload_dir();
	$url  = strtok( html_entity_decode( $url ), '?#' );
	$base = preg_replace( '#^https?://#', '', (string) $up['baseurl'] );
	$u    = preg_replace( '#^https?://#', '', (string) $url );
	if ( str_starts_with( $u, $base ) ) {
		return $up['basedir'] . rawurldecode( substr( $u, strlen( $base ) ) );
	}
	$site = preg_replace( '#^https?://#', '', home_url() );
	if ( str_starts_with( $u, $site . '/' ) ) {
		return untrailingslashit( ABSPATH ) . rawurldecode( substr( $u, strlen( $site ) ) );
	}
	return null;
}

function stlh_img_audit( WP_REST_Request $req ): array {
	// `?db=1` — پیشوندهای گزینه‌های autoload (برای پیدا کردنِ جامانده‌های افزونه‌های حذف‌شده) و رویدادهای cronِ بی‌صاحب
	if ( ! empty( $req->get_param( 'db' ) ) ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT option_name, LENGTH(option_value) AS b FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')", ARRAY_A );
		$pre  = [];
		foreach ( $rows as $r ) {
			$k = preg_replace( '/^(_site_transient_|_transient_)/', '', $r['option_name'] );
			$k = preg_match( '/^([a-z]+[_-]?[a-z]*)/i', $k, $m ) ? strtolower( $m[1] ) : $k;
			$pre[ $k ]['n'] = ( $pre[ $k ]['n'] ?? 0 ) + 1;
			$pre[ $k ]['kb'] = round( ( $pre[ $k ]['kb'] ?? 0 ) + $r['b'] / 1024, 1 );
		}
		uasort( $pre, static fn( $a, $b ) => $b['n'] <=> $a['n'] );
		$cron = [];
		foreach ( (array) _get_cron_array() as $ts => $hooks ) {
			foreach ( (array) $hooks as $hook => $x ) {
				$cron[ $hook ] = has_action( $hook ) ? 'ok' : 'orphan';
			}
		}
		return [ 'autoload_total' => count( $rows ), 'prefixes' => array_slice( $pre, 0, 70, true ), 'cron' => $cron, 'active' => (array) get_option( 'active_plugins', [] ) ];
	}
	@set_time_limit( 60 );
	$missing  = [];
	$external = [];
	$checked  = 0;
	$check    = static function ( string $url, string $where ) use ( &$missing, &$external, &$checked ): void {
		$url = trim( $url );
		if ( '' === $url || str_starts_with( $url, 'data:' ) || ! preg_match( '#\.(png|jpe?g|gif|webp|svg|ico|avif)(\?|$)#i', $url ) ) {
			return;
		}
		$checked++;
		$p = stlh_img_path( $url );
		if ( null === $p ) {
			if ( ! preg_match( '#(enamad\.ir|samandehi\.ir|gravatar\.com)#', $url ) && count( $external ) < 60 ) {
				$external[] = [ $where, $url ];
			}
		} elseif ( ! file_exists( $p ) && count( $missing ) < 200 ) {
			$missing[] = [ $where, $url ];
		}
	};
	// ۱) تنظیماتِ قالب
	$walk = static function ( $v, string $k ) use ( &$walk, $check ): void {
		if ( is_array( $v ) ) {
			foreach ( $v as $kk => $vv ) {
				$walk( $vv, $k . '.' . $kk );
			}
		} elseif ( is_string( $v ) ) {
			preg_match_all( '#https?://[^\s"\'<>)]+#', $v, $m );
			foreach ( $m[0] as $u ) {
				$check( $u, 'bakala:' . $k );
			}
		}
	};
	$walk( get_option( 'bakala_options', [] ), '' );
	// ۲) لوگو، آیکونِ سایت
	foreach ( [ 'site_icon', 'site_logo' ] as $opt ) {
		$id = (int) get_option( $opt );
		if ( $id ) {
			$f = get_attached_file( $id );
			if ( ! $f || ! file_exists( $f ) ) {
				$missing[] = [ 'wp:' . $opt, (string) wp_get_attachment_url( $id ) ];
			}
		}
	}
	$logo = (int) get_theme_mod( 'custom_logo' );
	if ( $logo && ( ! get_attached_file( $logo ) || ! file_exists( (string) get_attached_file( $logo ) ) ) ) {
		$missing[] = [ 'theme:custom_logo', (string) wp_get_attachment_url( $logo ) ];
	}
	// ۳) عکسِ شاخص و گالریِ محصولاتِ منتشرشده + عکسِ دسته‌ها
	global $wpdb;
	$ids = $wpdb->get_results( "SELECT p.ID, p.post_title, pm.meta_key, pm.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key IN ('_thumbnail_id','_product_image_gallery') WHERE p.post_type IN ('product','post','page') AND p.post_status = 'publish'", ARRAY_A );
	foreach ( $ids as $r ) {
		foreach ( array_filter( array_map( 'absint', explode( ',', (string) $r['meta_value'] ) ) ) as $aid ) {
			$checked++;
			$f = get_attached_file( $aid );
			if ( ! $f || ! file_exists( $f ) ) {
				if ( count( $missing ) < 200 ) {
					$missing[] = [ 'post#' . $r['ID'] . ' ' . mb_substr( $r['post_title'], 0, 40 ) . ' (' . $r['meta_key'] . ')', (string) wp_get_attachment_url( $aid ) ];
				}
			}
		}
	}
	foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] ) as $t ) {
		$aid = (int) get_term_meta( $t->term_id, 'thumbnail_id', true );
		if ( $aid ) {
			$checked++;
			$f = get_attached_file( $aid );
			if ( ! $f || ! file_exists( $f ) ) {
				$missing[] = [ 'cat:' . $t->name, (string) wp_get_attachment_url( $aid ) ];
			}
		}
	}
	// ۴) محتوای برگه‌ها/نوشته‌ها و دادهٔ المنتور و ابزارک‌ها
	$rows = $wpdb->get_results( "SELECT p.ID, p.post_title, p.post_content, (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_elementor_data' LIMIT 1) AS el FROM {$wpdb->posts} p WHERE p.post_status = 'publish' AND p.post_type IN ('page','post','elementor_library','nav_menu_item')", ARRAY_A );
	foreach ( $rows as $r ) {
		$blob = (string) $r['post_content'] . ' ' . str_replace( '\\/', '/', (string) $r['el'] );
		preg_match_all( '#https?://[^\s"\'<>)\\\\]+#', $blob, $m );
		foreach ( array_unique( $m[0] ) as $u ) {
			$check( $u, 'post#' . $r['ID'] . ' ' . mb_substr( $r['post_title'], 0, 40 ) );
		}
	}
	foreach ( $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE 'widget\\_%'" ) as $w ) {
		preg_match_all( '#https?://[^\s"\'<>)\\\\]+#', (string) $w, $m );
		foreach ( array_unique( $m[0] ) as $u ) {
			$check( $u, 'widget' );
		}
	}
	return [ 'checked' => $checked, 'missing' => $missing, 'external' => $external ];
}
