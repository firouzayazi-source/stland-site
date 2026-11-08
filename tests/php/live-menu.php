<?php
/**
 * منوی بالای سایت از درختِ زنده (`includes/live-menu.php`) — بی وردپرس، روی همان شکلِ منوی واقعیِ سایت (۱۸ آبان ۱۴۰۵):
 * «آیفون» و «لوازم جانبی» پیوندِ «#» با «مشاهده‌ی همه» و دسته‌های ثابت؛ صاحب فروشگاه دید زیرِ «لوازم جانبی» فقط
 * «شارژر» و «محافظ لنز» است در حالی که «کابل» و «محافظ صفحه» هم محصول دارند.
 *
 *   php tests/php/live-menu.php     (check.sh هم اجرا می‌کند)
 */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['stlh_test_opts'] = [];
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function get_option( $k, $d = false ) { return 'stland_home' === $k ? $GLOBALS['stlh_test_opts'] : $d; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
define( 'STLH_OPT', 'stland_home' );
$plugin = dirname( __DIR__, 2 ) . '/wp-content/plugins/stland-home/includes/';
require $plugin . 'helpers.php';
require $plugin . 'live-menu.php';

$fail = [];
$eq   = static function ( $got, $want, string $what ) use ( &$fail ): void {
	if ( $got !== $want ) {
		$fail[] = $what . "\n    got:  " . json_encode( $got, JSON_UNESCAPED_UNICODE ) . "\n    want: " . json_encode( $want, JSON_UNESCAPED_UNICODE );
	}
};

// درختِ زنده‌ی سایت (شناسه‌ها همان سایت): [نام، والد، نمایش؟]
$t = static fn( string $n, int $p, bool $s = true ) => [ 'n' => $n, 'u' => "https://stland.ir/c/$n/", 'p' => $p, 's' => $s ];
$tree = [
	'terms' => [
		2971 => $t( 'تلفن همراه', 0 ),
		3000 => $t( 'آیفون', 2971 ),
		2955 => $t( 'آیفون نو', 3000, false ), // خالی
		2954 => $t( 'آیفون کارکرده', 3000 ),
		3001 => $t( 'اندروید', 2971, false ),
		2958 => $t( 'لوازم جانبی', 0 ),
		2957 => $t( 'شارژر', 2958 ),
		2976 => $t( 'محافظ صفحه', 2958 ),
		2959 => $t( 'محافظ لنز', 2958, false ), // خالی
		3035 => $t( 'قاب و گارد', 2958, false ),
		3034 => $t( 'کابل', 2958 ),
		2972 => $t( 'خدمات', 0, false ),
		2981 => $t( 'اپل آیدی', 2972, false ),
		3005 => $t( 'کالای دیجیتال', 0 ),
		3003 => $t( 'پرفروش‌ها', 0 ),
	],
	'kids'  => [ 0 => [ 2971, 2958, 2972, 3005, 3003 ], 2971 => [ 3000, 3001 ], 3000 => [ 2955, 2954 ], 2958 => [ 2957, 2976, 2959, 3035, 3034 ], 2972 => [ 2981 ] ],
];
$GLOBALS['stlh_test_opts'] = [ 'best_cat' => '3003' ];
function stlh_resolve_cat( string $ref ) { return ctype_digit( $ref ) ? (object) [ 'term_id' => (int) $ref ] : false; }

// منوی واقعیِ «منوی استوک لند» (۲۹۶۷)
$mi = static fn( int $id, int $parent, int $order, string $title, string $type = 'custom', int $obj = 0, string $url = '#' ) => (object) [
	'ID' => $id, 'db_id' => $id, 'menu_item_parent' => (string) $parent, 'menu_order' => $order, 'title' => $title, 'url' => $url,
	'type' => $type, 'object' => 'taxonomy' === $type ? 'product_cat' : ( 'post_type' === $type ? 'page' : 'custom' ), 'object_id' => (string) ( $obj ?: $id ),
	'target' => '', 'attr_title' => '', 'description' => '', 'classes' => [ '' ], 'xfn' => '', 'post_parent' => 0,
];
$menu = [
	$mi( 44310, 0, 1, 'خانه', 'post_type', 42615, 'https://stland.ir/' ),
	$mi( 44319, 0, 2, 'آیفون' ),
	$mi( 45144, 44319, 3, 'مشاهده‌ی همه', 'taxonomy', 3000 ),
	$mi( 44314, 44319, 4, 'آیفون نو', 'taxonomy', 2955 ),
	$mi( 44313, 44319, 5, 'آیفون کارکرده', 'taxonomy', 2954 ),
	$mi( 44320, 0, 6, 'لوازم جانبی' ),
	$mi( 44317, 44320, 7, 'مشاهده‌ی همه', 'taxonomy', 2958 ),
	$mi( 44316, 44320, 8, 'شارژر', 'taxonomy', 2957 ),
	$mi( 44318, 44320, 9, 'محافظ لنز', 'taxonomy', 2959 ),
	$mi( 44939, 0, 10, 'لیست آک و کارکرده', 'post_type', 44938, 'https://stland.ir/list/' ),
	$mi( 45143, 0, 11, 'مجله و اخبار', 'post_type', 11983 ),
	$mi( 44311, 0, 12, 'درباره ما', 'post_type', 8167 ),
	$mi( 44312, 0, 13, 'تماس با ما', 'post_type', 8074 ),
];

$out   = stlh_live_menu_apply( $menu, $tree );
$shape = array_map( static fn( $i ) => ( (int) $i->menu_item_parent ? '  ' : '' ) . $i->title, $out );
$eq( $shape, [
	'خانه',
	'آیفون', '  مشاهده‌ی همه', '  آیفون کارکرده',
	'لوازم جانبی', '  مشاهده‌ی همه', '  شارژر', '  محافظ صفحه', '  کابل',
	// دسته‌ی اصلیِ دارای محصول که در منو نبود، بعد از آخرین گروهِ دسته؛ «پرفروش‌ها» نه
	'کالای دیجیتال',
	'لیست آک و کارکرده', 'مجله و اخبار', 'درباره ما', 'تماس با ما',
], 'شکلِ منو' );

$by = [];
foreach ( $out as $i ) {
	$by[ $i->title . '|' . $i->menu_item_parent ] = $i;
}
// موردِ واقعیِ «شارژر» با همان شناسه می‌ماند؛ «کابل» و «محافظ صفحه» ساختگی با پیوندِ دسته
$eq( (int) $by['شارژر|44320']->ID, 44316, 'شارژر شناسه‌ی واقعی‌اش را نگه دارد' );
$eq( (int) $by['کابل|44320']->ID, STLH_LM_TERM + 3034, 'شناسه‌ی پایدارِ کابل' );
$eq( [ $by['کابل|44320']->type, $by['کابل|44320']->object, $by['کابل|44320']->object_id, $by['کابل|44320']->url ], [ 'taxonomy', 'product_cat', '3034', 'https://stland.ir/c/کابل/' ], 'کابل پیوندِ دسته است' );
$eq( (int) $by['کالای دیجیتال|0']->ID, STLH_LM_TERM + 3005, 'گروهِ بی‌زیرمنو یک پیوند است' );
$eq( array_map( static fn( $i ) => $i->menu_order, $out ), range( 1, count( $out ) ), 'ترتیبِ یکتا و پشتِ سرِ هم' );
// ورودی دست نخورد (موارد کلون شدند)
$eq( $menu[7]->menu_order, 8, 'منوی اصلی دست نخورد' );

// نامِ تازه از Novo (یا نامِ نمایشی) روی موردِ واقعی هم می‌نشیند؛ ترتیبِ درخت برنده است
$tree2                       = $tree;
$tree2['terms'][2957]['n']   = 'شارژر و آداپتور';
$tree2['kids'][2958]         = [ 3034, 2957, 2976, 2959, 3035 ];
$tree2['terms'][3035]['s']   = true;
$tree2['terms'][3005]['s']   = false;
$tree2['kids'][2972]         = [ 2981 ];
$tree2['terms'][2981]['s']   = true;
$tree2['terms'][2972]['s']   = true;
$shape2 = array_map( static fn( $i ) => ( (int) $i->menu_item_parent ? '  ' : '' ) . $i->title, stlh_live_menu_apply( $menu, $tree2 ) );
$eq( array_slice( $shape2, 4, 10 ), [
	'لوازم جانبی', '  مشاهده‌ی همه', '  کابل', '  شارژر و آداپتور', '  محافظ صفحه', '  قاب و گارد',
	// گروهِ تازه با زیرمنو: سرِ «#» و «مشاهده‌ی همه» (برچسبِ `menu_all`)
	'خدمات', '  مشاهده‌ی همه', '  اپل آیدی',
	'لیست آک و کارکرده',
], 'نام، ترتیب و گروهِ تازه با زیرمنو' );

echo $fail ? "live-menu FAIL:\n  " . implode( "\n  ", $fail ) . "\n" : "live-menu ok\n";
exit( $fail ? 1 : 0 );
