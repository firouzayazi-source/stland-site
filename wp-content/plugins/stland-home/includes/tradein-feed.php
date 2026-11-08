<?php
/**
 * درخواست‌های «فروش یا معاوضه» برای Novo — `stland/v1/tradein`.
 *
 * صاحب فروشگاه (۱۸ آبان ۱۴۰۵): «فروش و معاوضه‌ای که در سایت انجام می‌شود باید در Novo جای خودش را داشته باشد … و
 * داخلِ Novo اعلان داشته باشیم.» Novo هر دو دقیقه از راهِ دروازه‌ی سایت (`woo/site-gate.ts`) با رمزِ برنامه‌ی مدیر
 * درخواست‌های تازه‌تر از آخرین شناسه را می‌خواند و وضعیتِ رسیدگی‌اش را این‌جا برمی‌گرداند تا در پیشخوان هم دیده شود.
 *
 * ⛔ فقط مدیرِ کل (`manage_options`)؛ فقط همان فیلدهای فرم (`STLH_TRADEIN_FIELDS`) — نه آی‌پی، نه ایمیل، نه چیزِ دیگر.
 * ⛔ نوشتن فقط متای کوچکِ `stlh_status` است؛ خودِ درخواست این‌جا عوض نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

/** وضعیتِ رسیدگی — [مقدار => برچسب]؛ همان `SITE_TRADEIN_STATUS`ِ Novo */
const STLH_TRADEIN_STATUSES = [
	'new'       => 'تازه',
	'seen'      => 'دیده شد',
	'contacted' => 'تماس گرفته شد',
	'done'      => 'انجام شد',
	'rejected'  => 'رد شد',
];

add_action( 'rest_api_init', static function (): void {
	$admin = static fn() => current_user_can( 'manage_options' );
	register_rest_route( 'stland/v1', '/tradein', [
		'methods'             => 'GET',
		'permission_callback' => $admin,
		'callback'            => 'stlh_tradein_feed',
		'args'                => [
			'after_id' => [ 'type' => 'integer', 'default' => 0, 'minimum' => 0 ],
			'limit'    => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 50 ],
		],
	] );
	register_rest_route( 'stland/v1', '/tradein/(?P<id>\d+)', [
		'methods'             => [ 'POST', 'PUT' ],
		'permission_callback' => $admin,
		'callback'            => 'stlh_tradein_set_status',
		'args'                => [
			'status' => [ 'type' => 'string', 'required' => true, 'enum' => array_keys( STLH_TRADEIN_STATUSES ) ],
		],
	] );
} );

/** درخواست‌های بعد از `after_id`، به ترتیبِ شناسه — زباله‌دان نه */
function stlh_tradein_feed( WP_REST_Request $req ): array {
	global $wpdb;
	$after = max( 0, (int) $req->get_param( 'after_id' ) );
	$limit = min( 50, max( 1, (int) $req->get_param( 'limit' ) ) );
	$ids   = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('private','publish','draft','pending') AND ID > %d ORDER BY ID ASC LIMIT %d",
		STLH_TRADEIN_PT,
		$after,
		$limit
	) );
	$out = [];
	foreach ( array_map( 'intval', $ids ) as $id ) {
		$kind   = (string) get_post_meta( $id, 'stlh_kind', true );
		$kind   = isset( STLH_TRADEIN_KINDS[ $kind ] ) ? $kind : 'trade';
		$fields = [];
		foreach ( array_keys( STLH_TRADEIN_FIELDS ) as $key ) {
			$fields[ $key ] = (string) get_post_meta( $id, 'stlh_' . $key, true );
		}
		$status = (string) get_post_meta( $id, 'stlh_status', true );
		$out[]  = [
			'id'        => $id,
			'date_gmt'  => (string) get_post_field( 'post_date_gmt', $id ),
			'kind'      => $kind,
			'kindLabel' => STLH_TRADEIN_KINDS[ $kind ],
			'fields'    => $fields,
			'status'    => isset( STLH_TRADEIN_STATUSES[ $status ] ) ? $status : 'new',
		];
	}
	return $out;
}

/** وضعیتِ رسیدگی از Novo */
function stlh_tradein_set_status( WP_REST_Request $req ) {
	$id     = (int) $req['id'];
	$status = (string) $req->get_param( 'status' );
	if ( STLH_TRADEIN_PT !== get_post_type( $id ) ) {
		return new WP_Error( 'stlh_tradein_not_found', 'درخواست پیدا نشد.', [ 'status' => 404 ] );
	}
	if ( ! isset( STLH_TRADEIN_STATUSES[ $status ] ) ) {
		return new WP_Error( 'stlh_tradein_bad_status', 'وضعیت نامعتبر است.', [ 'status' => 400 ] );
	}
	update_post_meta( $id, 'stlh_status', $status );
	return [ 'id' => $id, 'status' => $status ];
}

// ستونِ «وضعیت در Novo» در فهرستِ پیشخوان
add_filter( 'manage_' . STLH_TRADEIN_PT . '_posts_columns', static function ( array $cols ): array {
	$cols['stlh_status'] = 'وضعیت در Novo';
	return $cols;
} );
add_action( 'manage_' . STLH_TRADEIN_PT . '_posts_custom_column', static function ( string $col, int $id ): void {
	if ( 'stlh_status' === $col ) {
		echo esc_html( STLH_TRADEIN_STATUSES[ (string) get_post_meta( $id, 'stlh_status', true ) ] ?? STLH_TRADEIN_STATUSES['new'] );
	}
}, 10, 2 );
