<?php
/**
 * قالب صفحه اصلی — هدر و فوتر از قالب فعال سایت، محتوا از افزونه
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="stlh-home" class="stlh-home">
	<?php echo do_shortcode( '[stl_home]' ); ?>
</main>
<?php
get_footer();
