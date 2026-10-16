<?php
/**
 * قالبِ صفحه‌ی «لیست آک و کارکرده» (`includes/app-list.php`) — سربرگ و پانویس از قالبِ سایت.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="stlh-list" class="stlh-list">
	<?php echo do_shortcode( '[stl_app_list]' ); ?>
</main>
<?php
get_footer();
