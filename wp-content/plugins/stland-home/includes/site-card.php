<?php
/**
 * کارتِ «حالِ سایت» در پیشخوانِ وردپرس — صاحب فروشگاه (۲۶ مهر ۱۴۰۵، ارتقای افزونه): همه‌چیز در یک نگاه و از گوشی.
 *
 * فقط خواندن، فقط برای مدیرِ کل: نسخه‌ی افزونه، پاک شدن‌های کاملِ حافظه‌ی موقت (۲۴ ساعتِ گذشته، `cache-tune.php`)،
 * آخرین گرم کردنِ صفحه‌ی اصلی (`home-warm.php`)، آخرین تغییرِ محصول (یعنی آخرین همگام‌سازیِ حسابداری)، درخواست‌های
 * معاوضه‌ی هفته (`trade-in.php`) و آخرین مقاله‌ها.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_dashboard_setup', static function (): void {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'stlh_site_card', 'حالِ سایتِ استوک لند', 'stlh_site_card' );
	}
} );

function stlh_ago( int $ts ): string {
	if ( ! $ts ) {
		return '—';
	}
	return stlh_fa( human_time_diff( $ts, time() ) ) . ' پیش';
}

function stlh_site_card(): void {
	global $wpdb;
	$log    = (array) get_option( 'stlh_purge_log', [] );
	$day    = array_filter( $log, static fn( $r ) => strtotime( (string) ( $r['at'] ?? '' ) ) > time() - DAY_IN_SECONDS );
	$warm   = (array) get_option( 'stlh_home_warm_last', [] );
	$warm_s = '—';
	if ( ! empty( $warm['rows'] ) ) {
		$warm_s = stlh_ago( (int) strtotime( (string) $warm['at'] ) ) . ' — ' . implode( '، ', array_map( static fn( $r ) => ( 'mobile' === $r['dev'] ? 'گوشی' : 'رایانه' ) . ' ' . stlh_fa( round( (int) $r['ms'] / 1000, 1 ) ) . ' ثانیه', $warm['rows'] ) );
	}
	$last_product = (string) $wpdb->get_var( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" );
	$tradeins     = function_exists( 'stlh_tradein_page_id' ) ? (int) ( new WP_Query( [ 'post_type' => STLH_TRADEIN_PT, 'post_status' => 'private', 'date_query' => [ [ 'after' => '7 days ago' ] ], 'fields' => 'ids', 'posts_per_page' => 50 ] ) )->found_posts : 0;
	$posts        = get_posts( [ 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 3 ] );

	$row = static fn( string $k, string $v ): string => '<tr><th style="text-align:right;padding:4px 0;font-weight:600">' . esc_html( $k ) . '</th><td style="padding:4px 8px">' . $v . '</td></tr>';
	echo '<table style="width:100%;direction:rtl">';
	echo $row( 'نسخه‌ی افزونه', esc_html( stlh_fa( STLH_VER ) ) ); // phpcs:ignore
	echo $row( 'پاک شدنِ کاملِ حافظه‌ی موقت (۲۴ ساعت)', esc_html( stlh_fa( count( $day ) ) . ' بار' . ( $day ? ' — آخری ' . stlh_ago( (int) strtotime( (string) end( $day )['at'] ) ) : '' ) ) ); // phpcs:ignore
	echo $row( 'گرم کردنِ صفحه‌ی اصلی', esc_html( $warm_s ) ); // phpcs:ignore
	echo $row( 'آخرین تغییرِ محصول (همگام‌سازیِ حسابداری)', esc_html( $last_product ? stlh_ago( (int) strtotime( $last_product . ' UTC' ) ) : '—' ) ); // phpcs:ignore
	echo $row( 'درخواستِ معاوضه (۷ روز)', '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . STLH_TRADEIN_PT ) ) . '">' . esc_html( stlh_fa( $tradeins ) ) . '</a>' ); // phpcs:ignore
	$list = implode( '<br>', array_map( static fn( $p ) => '<a href="' . esc_url( get_permalink( $p ) ) . '" target="_blank">' . esc_html( $p->post_title ) . '</a>', $posts ) );
	echo $row( 'آخرین مقاله‌ها', $list ?: '—' ); // phpcs:ignore
	echo '</table>';
}
