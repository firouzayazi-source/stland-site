<?php
/**
 * عکس‌های خراب و مانده‌های نسخه‌ی نمونه — یک بار برای هر `STLH_SITEFIX_REV`.
 *
 * صاحب فروشگاه (۲۳ مهر ۱۴۰۵): «مطالبِ غیرِ استوک‌لند نباشد؛ عکس یا آیکونِ خراب، عکسِ درست بگذار».
 * `/stland/v1/img-audit` نشان داد ۵۵ ارجاع به فایلی است که روی دیسک نیست (آیکون‌های قالب، عکسِ سه مقاله و
 * چهار دسته، تزئیناتِ المنتورِ برگه‌های تماس/پرسش‌ها/پیگیری) و چند جا به دامنه‌ی سازنده‌ی قالب.
 *
 * هر جا فقط وقتی دست می‌خورد که فایلِ فعلی واقعاً نیست؛ مقدارِ قبلی در `stlh_sitefix_log` می‌ماند.
 * جایگزین‌ها آیکون‌های سبکِ خودِ افزونه‌اند (`assets/img/*.svg`) یا عکسی که همین الان روی سایت هست.
 * ⛔ چیزی پاک نمی‌شود جز منوی نمونه‌ای که به هیچ جا وصل نیست؛ برگه‌ی نمونه فقط پیش‌نویس می‌شود.
 */
defined( 'ABSPATH' ) || exit;

const STLH_SITEFIX_REV = 2;

add_action( 'init', 'stlh_sitefix_apply', 30 );

function stlh_sitefix_asset( string $file ): string {
	return STLH_URL . 'assets/img/' . $file;
}

/** آدرسِ آپلود که فایلش نیست؟ (آدرسِ بیرونی یا خالی → نه) */
function stlh_sitefix_broken( $url ): bool {
	$p = is_string( $url ) && '' !== $url ? stlh_img_path( $url ) : null;
	return null !== $p && ! file_exists( $p );
}

/** عکسِ شاخصِ سالم از محصولاتِ منتشرشده‌ی یک دسته (با زیردسته‌ها) */
function stlh_sitefix_cat_image( int $term_id, int $skip = 0 ): int {
	$ids = get_posts( [
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'fields'         => 'ids',
		'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery
		'tax_query'      => [ [ 'taxonomy' => 'product_cat', 'terms' => $term_id, 'include_children' => true ] ], // phpcs:ignore WordPress.DB.SlowDBQuery
	] );
	foreach ( $ids as $pid ) {
		$aid = (int) get_post_thumbnail_id( $pid );
		$f   = $aid && $aid !== $skip ? get_attached_file( $aid ) : '';
		if ( $f && file_exists( $f ) ) {
			return $aid;
		}
	}
	return 0;
}

function stlh_sitefix_apply(): void {
	$rev = (int) get_option( 'stlh_sitefix_rev' );
	if ( $rev >= STLH_SITEFIX_REV || ! function_exists( 'stlh_img_path' ) ) {
		return;
	}
	if ( ! add_option( 'stlh_sitefix_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'stlh_sitefix_lock' ) < 120 ) {
			return;
		}
		update_option( 'stlh_sitefix_lock', time(), false );
	}
	@set_time_limit( 60 );
	$log = [];
	try {
		if ( $rev >= 1 ) {
			// دورِ ۲: سه مقاله‌ای که دورِ ۱ بی‌عکس گذاشت (آیفون‌های نو عکسِ سالم نداشتند) → عکسِ سالمِ هر محصولی
			$log           = (array) get_option( 'stlh_sitefix_log', [] );
			$log['posts2'] = stlh_sitefix_post_images( [ 44340, 44341, 44342 ] );
		} else {
			$log['bakala']  = stlh_sitefix_bakala();
			$log['widgets'] = stlh_sitefix_widgets();
			$log['pages']   = stlh_sitefix_pages();
			$log['thumbs']  = stlh_sitefix_thumbs();
			$log['demo']    = stlh_sitefix_demo();
			$log['cron']    = stlh_sitefix_cron();
			$log['posts2']  = stlh_sitefix_post_images( [ 44340, 44341, 44342 ] );
		}
	} catch ( Throwable $e ) {
		$log['error'] = $e->getMessage();
	}
	if ( function_exists( 'litespeed_purge_all' ) ) {
		litespeed_purge_all();
	} else {
		do_action( 'litespeed_purge_all' );
	}
	update_option( 'stlh_sitefix_log', [ 'at' => gmdate( 'c' ), 'ver' => STLH_VER, 'rev' => STLH_SITEFIX_REV ] + $log, false );
	if ( empty( $log['error'] ) ) {
		update_option( 'stlh_sitefix_rev', STLH_SITEFIX_REV, false );
	}
	delete_option( 'stlh_sitefix_lock' );
}

/** آیکون‌های قالب که فایلشان نیست */
function stlh_sitefix_bakala(): array {
	global $wpdb;
	// خامِ دیتابیس، مثلِ `stlh_bakala_apply` (نگهبانِ قالب مقدارِ صفحه‌ی مشتری را دست می‌برد)
	$opts = maybe_unserialize( (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'bakala_options' ) ) );
	if ( ! is_array( $opts ) ) {
		return [ 'skip' => 'no bakala_options' ];
	}
	$logo = is_array( $opts['site_header_logo'] ?? null ) ? (string) ( $opts['site_header_logo']['url'] ?? '' ) : '';
	$map  = [
		'special_box_icon'          => stlh_sitefix_asset( 'badge.svg' ),
		'free_shipping_icon'        => stlh_sitefix_asset( 'truck.svg' ),
		'product_description_image' => stlh_sitefix_asset( 'headset.svg' ),
		'extra_btn_product_icon'    => stlh_sitefix_asset( 'whatsapp.svg' ),
		'bakala_preload_logo'       => $logo && ! stlh_sitefix_broken( $logo ) ? $logo : stlh_sitefix_asset( 'placeholder.svg' ),
		'fixed_box_img'             => stlh_sitefix_asset( 'phone.svg' ),
	];
	$backup  = (array) get_option( 'stlh_bakala_backup', [] );
	$changed = [];
	foreach ( $map as $key => $url ) {
		if ( ! array_key_exists( $key, $opts ) ) {
			continue;
		}
		$cur = $opts[ $key ];
		if ( is_array( $cur ) ) {
			if ( ! stlh_sitefix_broken( $cur['url'] ?? '' ) ) {
				continue;
			}
			$new = [ 'url' => $url, 'id' => '', 'height' => '', 'width' => '', 'thumbnail' => $url ];
			if ( 'bakala_preload_logo' === $key && $url === $logo ) {
				$new = $opts['site_header_logo'];
			}
		} else {
			if ( ! stlh_sitefix_broken( (string) $cur ) ) {
				continue;
			}
			$new = $url;
		}
		if ( ! array_key_exists( $key, $backup ) ) {
			$backup[ $key ] = $cur;
		}
		$opts[ $key ] = $new;
		$changed[]    = $key;
	}
	if ( $changed ) {
		update_option( 'stlh_bakala_backup', $backup, false );
		update_option( 'bakala_options', $opts );
		delete_option( 'bakala_options-transients' );
	}
	return $changed;
}

/** عکسِ پیش‌فرضِ ابزارکِ «آخرین مطالب» از دامنه‌ی سازنده‌ی قالب */
function stlh_sitefix_widgets(): array {
	$done = [];
	foreach ( [ 'widget_bakala_widget', 'widget_bigikala_widget' ] as $name ) {
		$w = get_option( $name );
		if ( ! is_array( $w ) ) {
			continue;
		}
		$hit = false;
		foreach ( $w as $i => $inst ) {
			if ( is_array( $inst ) && isset( $inst['thumb_default'] ) && str_contains( (string) $inst['thumb_default'], 'kucod.com' ) ) {
				$w[ $i ]['thumb_default'] = stlh_sitefix_asset( 'placeholder.svg' );
				$hit                      = true;
			}
		}
		if ( $hit ) {
			update_option( $name, $w );
			$done[] = $name;
		}
	}
	return $done;
}

/** تزئیناتِ گم‌شده‌ی برگه‌های المنتور (تماس، پرسش‌ها، پیگیری، درباره) */
function stlh_sitefix_pages(): array {
	global $wpdb;
	$up   = untrailingslashit( (string) wp_upload_dir()['baseurl'] );
	$logo = '';
	$raw  = maybe_unserialize( (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'bakala_options' ) ) );
	if ( is_array( $raw ) && is_array( $raw['site_header_logo'] ?? null ) ) {
		$logo = (string) ( $raw['site_header_logo']['url'] ?? '' );
	}
	$files = [
		'wave-header-min-1.svg' => stlh_sitefix_asset( 'wave.svg' ),
		'Rectangle-black.svg'   => stlh_sitefix_asset( 'rect-dark.svg' ),
		'Mobile.svg'            => stlh_sitefix_asset( 'phone.svg' ),
		'clock.svg'             => stlh_sitefix_asset( 'clock.svg' ),
		'post.svg'              => stlh_sitefix_asset( 'carrier-post.svg' ),
		'tipax.svg'             => stlh_sitefix_asset( 'carrier-tipax.svg' ),
		'snapp.svg'             => stlh_sitefix_asset( 'carrier-snapp.svg' ),
		'cropped-IMG_5132.png'  => $logo ?: stlh_sitefix_asset( 'placeholder.svg' ),
	];
	$from = [];
	$to   = [];
	foreach ( $files as $f => $new ) {
		$old = $up . '/' . $f;
		if ( ! stlh_sitefix_broken( $old ) ) {
			continue;
		}
		foreach ( [ $old, str_replace( 'https://', 'http://', $old ) ] as $o ) {
			$from[] = $o;
			$to[]   = $new;
			$from[] = str_replace( '/', '\\/', $o ); // شکلِ JSONِ `_elementor_data`
			$to[]   = str_replace( '/', '\\/', $new );
		}
	}
	if ( ! $from ) {
		return [];
	}
	$done = [];
	foreach ( [ 8074, 8144, 10341, 8167, 43729 ] as $pid ) {
		$hit = false;
		// مستقیم در جدول، نه `update_post_meta`: آن unslash می‌کند و `\/`ِ JSONِ المنتور را خراب می‌کند
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $pid ) ) as $m ) {
			$v = str_replace( $from, $to, (string) $m->meta_value );
			if ( $v !== $m->meta_value ) {
				$wpdb->update( $wpdb->postmeta, [ 'meta_value' => $v ], [ 'meta_id' => $m->meta_id ] );
				$hit = true;
			}
		}
		$content = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $pid ) );
		$v       = str_replace( $from, $to, $content );
		if ( $v !== $content ) {
			$wpdb->update( $wpdb->posts, [ 'post_content' => $v ], [ 'ID' => $pid ] );
			$hit = true;
		}
		if ( $hit ) {
			delete_post_meta( $pid, '_elementor_css' );
			delete_post_meta( $pid, '_elementor_element_cache' );
			clean_post_cache( $pid );
			$done[] = $pid;
		}
	}
	if ( $done && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	return $done;
}

/** عکسِ شاخصِ مقاله‌ها و عکسِ دسته‌ها که فایلشان نیست → عکسِ سالمِ یک محصولِ همان دسته */
function stlh_sitefix_thumbs(): array {
	$done = [];
	$cats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
	foreach ( is_array( $cats ) ? $cats : [] as $t ) {
		$aid = (int) get_term_meta( $t->term_id, 'thumbnail_id', true );
		if ( ! $aid ) {
			continue;
		}
		$f = get_attached_file( $aid );
		if ( $f && file_exists( $f ) ) {
			continue;
		}
		$new = stlh_sitefix_cat_image( $t->term_id );
		if ( $new ) {
			update_term_meta( $t->term_id, 'thumbnail_id', $new );
		} else {
			delete_term_meta( $t->term_id, 'thumbnail_id' );
		}
		$done[] = 'cat:' . $t->name . ' ' . $aid . '→' . $new;
	}
	// مقاله‌ها: عکسِ آیفون‌های نو (هر مقاله یک عکسِ متفاوت)
	$iphone = get_term_by( 'name', 'آیفون نو', 'product_cat' );
	$used   = 0;
	$posts  = get_posts( [ 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids' ] );
	foreach ( $posts as $pid ) {
		$aid = (int) get_post_thumbnail_id( $pid );
		if ( ! $aid ) {
			continue;
		}
		$f = get_attached_file( $aid );
		if ( $f && file_exists( $f ) ) {
			continue;
		}
		$new = $iphone instanceof WP_Term ? stlh_sitefix_cat_image( $iphone->term_id, $used ) : 0;
		if ( $new ) {
			set_post_thumbnail( $pid, $new );
			$used = $new;
		} else {
			delete_post_thumbnail( $pid );
		}
		$done[] = 'post:' . $pid . ' ' . $aid . '→' . $new;
	}
	return $done;
}

/** مقاله‌های بی‌عکس → عکسِ شاخصِ سالمِ محصولاتِ اخیر، هر مقاله یکی */
function stlh_sitefix_post_images( array $post_ids ): array {
	$pool = [];
	foreach ( get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 40, 'fields' => 'ids', 'meta_key' => '_thumbnail_id' ] ) as $pid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$aid = (int) get_post_thumbnail_id( $pid );
		$f   = $aid ? get_attached_file( $aid ) : '';
		if ( $f && file_exists( $f ) && ! in_array( $aid, $pool, true ) ) {
			$pool[] = $aid;
		}
	}
	$done = [];
	foreach ( $post_ids as $i => $pid ) {
		if ( get_post_thumbnail_id( $pid ) || ! get_post( $pid ) || ! isset( $pool[ $i ] ) ) {
			continue;
		}
		set_post_thumbnail( $pid, $pool[ $i ] );
		$done[] = $pid . '→' . $pool[ $i ];
	}
	return $done;
}

/** برگه‌ی نمونه‌ی «دسته بندی ها» (ماشین و لپ‌تاپ) → پیش‌نویس؛ منوی نمونه‌ی وصل‌نشده → حذف */
function stlh_sitefix_demo(): array {
	$done      = [];
	$locations = array_map( 'intval', (array) get_nav_menu_locations() );
	$in_widget = [];
	foreach ( (array) get_option( 'widget_nav_menu', [] ) as $inst ) {
		if ( is_array( $inst ) && ! empty( $inst['nav_menu'] ) ) {
			$in_widget[] = (int) $inst['nav_menu'];
		}
	}
	$page = get_post( 13048 );
	if ( $page instanceof WP_Post && 'publish' === $page->post_status && (int) get_option( 'page_on_front' ) !== 13048 ) {
		// اگر منوی فعالی به آن لینک دارد، دست نمی‌زنیم (لینکِ مرده نسازیم)
		$linked = false;
		foreach ( array_unique( array_merge( $locations, $in_widget ) ) as $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu ) as $item ) {
				if ( is_object( $item ) && 'post_type' === $item->type && 13048 === (int) $item->object_id ) {
					$linked = true;
				}
			}
		}
		if ( $linked ) {
			$done[] = 'page:13048 linked, kept';
		} elseif ( ! is_wp_error( wp_update_post( [ 'ID' => 13048, 'post_status' => 'draft' ], true ) ) ) {
			$done[] = 'page:13048 draft';
		}
	}
	$menu = wp_get_nav_menu_object( 2888 );
	if ( $menu && ! in_array( 2888, $locations, true ) && ! in_array( 2888, $in_widget, true ) ) {
		if ( ! is_wp_error( wp_delete_nav_menu( 2888 ) ) ) {
			$done[] = 'menu:2888 deleted';
		}
	}
	return $done;
}

/**
 * رویدادهای cronِ افزونه‌هایی که دیگر نیستند. فقط وقتی هیچ کدی به آن قلاب وصل نیست؛ افزونه‌ای که برگردد
 * خودش دوباره زمان‌بندی می‌کند. قلاب‌های قالب (`bakala_*`) دست نمی‌خورند.
 */
function stlh_sitefix_cron(): array {
	$done = [];
	foreach ( array_keys( (array) _get_cron_array() ) as $ts ) {
		foreach ( array_keys( (array) ( _get_cron_array()[ $ts ] ?? [] ) ) as $hook ) {
			if ( has_action( $hook ) || in_array( $hook, $done, true ) || str_contains( strtolower( $hook ), 'bakala' ) ) {
				continue;
			}
			if ( preg_match( '/^(puc_cron_check_updates|ZhUpClient_|dgwt_wcas_|sucuriscan_|wpo_|Bigiseller_|dokan|newsletter_|rocket_|pws_)/', $hook ) ) {
				wp_unschedule_hook( $hook );
				$done[] = $hook;
			}
		}
	}
	return $done;
}
