<?php
/**
 * The Template for displaying customization products.
 *
 * @author      Qstomizer
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Pragma: no-cache');

global $rmQstomizer;
global $product;
get_header('shop'); 
?>
<div id="qsmz_div_container" style="text-align:center;">
    <div id="qsmz_div_product_title">
        <?php the_title( '<h2>', '</h2>' ); ?>
    </div>
    <?php $rmQstomizer->qsmz_include_qstomizer(get_the_ID());  ?>
   <!--  <div id="qsmz_div_product_title">
        <?php //while ( have_posts() ) : the_post(); ?>
            <div class="post">
                <div class="entry">
                        <?php //the_content(); ?>
                </div>
            </div>
        <?php //endwhile; ?>
    </div>  -->
</div>
<?php get_footer('shop');  ?>