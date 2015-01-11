<?php
/**
 * Plugin Name: Qstomizer
 * Plugin URI: http://www.qstomizer.com/
 * Description: Qstomizer is a plugin for WordPress and Woocomerce. With it and in the easiest way , you can add a designer for custom products to your online store. Just need to install the plugin, configure it and You´ll have a designer of dozens of products seamlessly integrated with the design of your online store. Compatible with any Theme in the market.
 * Version: 1.0.0
 * Author: Ramon M. Ortigosa
 * Author URI: http://www.qstomizer.com
 * Requires at least: 2.5
 * Tested up to: 4.0.1
 *
 * Text Domain: qstomizer
 * Domain Path: /languages/
 *
 * @author Qstomizer
 */

//Version
if (!defined('QSTOMIZER_VERSION_KEY'))
    define('QSTOMIZER_VERSION_KEY', 'qstomizer_version');

if (!defined('QSTOMIZER_VERSION_NUM'))
    define('QSTOMIZER_VERSION_NUM', '1.0.0');


class Qstomizer{
    protected $QSMZurls;

	public function __construct() {

        include( plugin_dir_path(__FILE__) . '/includes/json.php' );
        $this->QSMZurls = json_decode($json_net,true);

        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
        //Activate
        register_activation_hook( __FILE__, array($this,'qsmz_activate') );
        //Create template
        add_action( 'template_redirect', array($this, 'qsmz_modificar_plantilla'), 1 );
        //Admin page
        add_action('admin_menu', array($this, 'qsmz_qstomizer_add_page'));
        //Product section	
        add_action( 'add_meta_boxes', array($this, 'qsmz_qstomizer_mbe_create'),10, 2);
        //script JS Admin
        add_action( 'admin_enqueue_scripts', array($this,'js_enqueue') );
        //CSS Admin
        add_action( 'admin_enqueue_scripts', array($this,'qsmz_admin_add_css') );
        //Save post meta
        add_action( 'save_post', array($this, 'qsmz_mbe_save_meta'));
        //Llamadas externas
        add_filter('query_vars',  array($this, 'qsmz_plugin_query_vars'));
        add_action('parse_request',  array($this, 'qsmz_plugin_parse_request'));
        //Change image product
        add_filter( 'the_content', array($this, 'featured_image_before_content' )); 

        //Woocommerce related products
        add_filter('woocommerce_related_products_args',array($this, 'qsmz_remove_related_products'), 10);

        add_filter( 'get_terms', array($this, 'qsmz_exclude_custom_category'), 10, 3 );
        add_filter ('posts_where', array($this, 'qsmz_exclude_custom_products'));

        //Cambia la imagen del producto
        add_filter('woocommerce_single_product_image_html', array($this, 'qsmz_change_product_image_html'), 10, 2);
        add_filter('woocommerce_single_product_image_thumbnail_html', array($this, 'qsmz_change_product_image_thumbnail_html'), 10, 4);

        //Imagen del producto en la cesta
        add_filter('woocommerce_cart_item_thumbnail', array($this, 'qsmz_change_product_cart_image_thumbnail_html'),10,3);    

        $priority = has_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail');

        // //Uninstall
        // register_uninstall_hook( __FILE__, array($this,'qsmz_uninstall') );

	}

	function qsmz_activate() {

        if ( ! current_user_can( 'activate_plugins' ) ) return;
        require_once(ABSPATH.'wp-admin/includes/upgrade.php');

        global $wpdb;
        $prefijodb = $wpdb->prefix;

        update_option('qsmz_shop_url', get_site_url());

        if (!get_option(QSTOMIZER_VERSION_KEY)){ //instalacion nueva
           	add_option(QSTOMIZER_VERSION_KEY, QSTOMIZER_VERSION_NUM);
            include( plugin_dir_path(__FILE__) . '/includes/db_ini.php' );
        }else{                
            add_option(QSTOMIZER_VERSION_KEY, QSTOMIZER_VERSION_NUM);
        } 
    }

    function qsmz_remove_related_products( $args ){
        global $wpdb;
        global $post, $woocommerce;
        
        $qsmz_category = get_option("qsmz_category_shop");
        $qsmz_category_hide = get_option("qsmz_category_hide");
        
        $terms = get_the_terms( $post->ID, 'product_cat' );
        $ocultar_cat = false;
        foreach ($terms as $term) {
            $product_cat = $term->slug;
            if ($product_cat==$qsmz_category) {
                $ocultar_cat = true;
                break;
            }
        }
        
        
        if ($qsmz_category_hide==1 && $ocultar_cat){
            return array();
        }else{
            return $args;
        }
    }

    function wptt_single_image_size( $size ){
 
        $size['width'] = '600';
        $size['height'] = '600';
     
        return $size;
     
    }

    function qsmz_change_product_image_html( $html, $post_id ) {
        global $post;
        if (get_post_meta($post_id, '_qsmz_product')){
            $imgfront = get_post_meta($post_id, '_qsmz_img_front');
            $imgback = get_post_meta($post_id, '_qsmz_img_back');
            $imagehtml = '<img src="'.$this->QSMZurls["QSMZurlS3images"].$imgfront[0].'" width="'. $qsmz_width .'" height="'. $qsmz_height .'">';
            return $imagehtml;
        }else{
            return $html;
        }
    }

    function qsmz_change_product_image_thumbnail_html( $html, $value, $post_id, $image_class ) {
        global $post;

        if (get_post_meta($post_id, '_qsmz_product')){
            $imgfront = get_post_meta($post_id, '_qsmz_img_front');
            $imgback = get_post_meta($post_id, '_qsmz_img_back');
            
            if ($imgback[0]!='null'){
                $image_link = $this->QSMZurls["QSMZurlS3images"].$imgback[0];
                $image_title = "Back of the product";
                $image = '<img src="'.$this->QSMZurls["QSMZurlS3images"].$imgback[0].'" width="90" height="90" class="attachment-shop_thumbnail">';
                $imagehtml =  sprintf( '<a href="%s" class="%s" title="%s" data-rel="prettyPhoto[product-gallery]">%s</a>', $image_link, $image_class, $image_title, $image);
                
                return $imagehtml;
            }else{
                return "<span></span>";
            }
            
        }else{
            return $html;
        }
    }

    function qsmz_change_product_cart_image_thumbnail_html($html, $cart_item, $cart_item_key){

        $post_id = $cart_item['product_id'];
        if (get_post_meta($post_id, '_qsmz_product')){
            $imgfront = get_post_meta($post_id, '_qsmz_img_front');
            return '<img src="'.$this->QSMZurls["QSMZurlS3images"].$imgfront[0].'" width="90" height="90" class="attachment-shop_thumbnail">';
        }else{
            return $html;
        }
    }

    function qsmz_template_loop_product_thumbnail(){
        global $post, $woocommerce;

        if ( ! $placeholder_width )
            $placeholder_width = $woocommerce->get_image_size( 'shop_catalog_image_width' );
        if ( ! $placeholder_height )
            $placeholder_height = $woocommerce->get_image_size( 'shop_catalog_image_height' );
            
        $output = '<div class="imagewrapper">';

        if ( has_post_thumbnail() ) {
            
            if (get_post_meta($post->ID, '_qsmz_product')){
                $imgfront = get_post_meta($post->ID, '_qsmz_img_front');
                $output .= '<img src="'.$this->QSMZurls["QSMZurlS3images"]. $imgfront[0] .'" alt="Qstomizer image" width="' . $placeholder_width['width'] . '" height="' . $placeholder_height['height'] . '" />';
            }
        }
        
        $output .= '</div>';
        
        echo $output;
    }

    function qsmz_exclude_custom_category( $terms, $taxonomies, $args ) {
        //Excluye una categoria. No aparece la categoría
        $new_terms = array();
        $qsmz_category = get_option("qsmz_category_shop");
        $qsmz_category_hide = get_option("qsmz_category_hide");
        
        if ($qsmz_category_hide==1){
            // if a product category and on the shop page
            if ( in_array( 'product_cat', $taxonomies ) && !is_admin() ) {
              foreach ( $terms as $key => $term ) {
                //if ( ! in_array( $term->term_id, array( 102 ) ) ) {
                if ( $term->slug!=$qsmz_category  ) {
                  $new_terms[] = $term;
                }

              }

              $terms = $new_terms;
            }
        }   
        return $terms;
    }

    function qsmz_exclude_custom_products ($where) {
        global $wpdb;

        $where = apply_filters('qstomizer_filter_products', $where);        
        return $where;
    }

    function qsmz_plugin_query_vars($vars) {
        $vars[] = 'qsmz_external';
        return $vars;
    }

    function qsmz_plugin_parse_request($wp) {
        global $wpdb;

        if (array_key_exists('qsmz_external', $wp->query_vars) 
            && substr($wp->query_vars['qsmz_external'],0,10) == 'addproduct') {
            $external = split("-",$wp->query_vars['qsmz_external']);
            // var_dump( $external ); 

            // $external = split("_",$wp->query_vars['qsmz_external']);
            
            $QZ_nonce = $external[1];
            $QZ_order = $external[2];
            $QZ_keyorder = $external[3];
            $QZ_imgf = $external[4];
            $QZ_imgb = $external[5];
            
            $tabla = $wpdb->prefix."qstomizer_plugin_nonces";
            $strSQL = 'select id_customizable, fecha, post_aduplicar,tipo_solicitud,id_order, url_imagen from '.$tabla.
                    ' where key_link=%s';
            $strSQL = $wpdb->prepare($strSQL, $QZ_nonce);
            $result = $wpdb->get_row($strSQL);
            $create = false;
            if ($result != null) {
                $post_aduplicar = $result->post_aduplicar;
                $id_customizable = $result->id_customizable;
                $fecha = $result->fecha;
                $tipo_solicitud = $result->tipo_solicitud;
                // $id_order = $result->id_order; //Debe venir desde la url
                $url_imagen = $result->url_imagen;
                $create = true;
            }

            if (!$create){
                wp_redirect( get_site_url() );
                die();
            }
            $image_post_id = $QZ_imgf;
            $image_post_id = get_post_thumbnail_id($post_aduplicar);
            $product_original = get_product( $post_aduplicar );

            $tipo_prod_original = $product_original->product_type;
            $new_id = $this->duplicate_product_action($post_aduplicar);
            update_post_meta( $new_id, '_thumbnail_id', $image_post_id );
            update_post_meta( $new_id, '_qsmz_mbe_costume', '');
            add_post_meta( $new_id, '_qsmz_order_id', $QZ_order);
            add_post_meta( $new_id, '_qsmz_key_order', $QZ_keyorder);
            add_post_meta( $new_id, '_qsmz_img_front', $QZ_imgf);
            add_post_meta( $new_id, '_qsmz_img_back', $QZ_imgb);
            add_post_meta( $new_id, '_qsmz_product', true);
            $status = 'publish';
            $current_post = get_post( $new_id, 'ARRAY_A' );
            $current_post['post_status'] = $status;
            wp_update_post($current_post);
            $qsmz_category = get_option("qsmz_category_shop");

            if ($qsmz_category!=""){
                $cat_ids = array( $qsmz_category );
                wp_set_object_terms( $new_id, $cat_ids, 'product_cat', false);
                
                $args = array(
                    'type'                     => 'product',
                    'taxonomy'                 => 'product_type');
                $categories = get_categories( $args );
                foreach ($categories as $category) {
                    $this->qsmz_remove_post_term($new_id, $category->term_id, "product_type");
                }
                wp_set_object_terms( $new_id, $tipo_prod_original, 'product_type' ); //solo dejo el tipo de producto igual que el original para mostrar las variaciones
                
            }
            
            $permalink = get_permalink( $new_id );
            wp_redirect( $permalink );
            die();

        }
    }   

 
    function featured_image_before_content( $content ) { 
          global $post;

          if ( is_singular('product') ) {

              $imgfront = get_post_meta($post->ID, '_qsmz_img_front');
              $imgback = get_post_meta($post->ID, '_qsmz_img_back');

              $qsmz_mostrar_img = get_option("qsmz_mostrar_img");
              $qsmz_width = get_option("qsmz_tamano_img_width");
              $qsmz_height = get_option("qsmz_tamano_img_height");

              $strimgf = $strimgb = "";
              if ($qsmz_mostrar_img == 1 && is_numeric($qsmz_width) && is_numeric($qsmz_height)){
                if($imgfront[0]!="null") $strimgf = '<img src="'.$this->QSMZurls["QSMZurlS3images"].$imgfront[0].'" width="'. $qsmz_width .'" height="'. $qsmz_height .'">';
                if($imgback[0]!="null") $strimgb = '<img src="'.$this->QSMZurls["QSMZurlS3images"].$imgback[0].'" width="'. $qsmz_width .'" height="'. $qsmz_height .'">';
              }

              $content = $strimgf . $strimgb . $content;            
          }

        return $content;
    }



    /**
    * Duplicate a product action.
    */
    public function duplicate_product_action($id) {

           $post = $this->get_product_to_duplicate( $id );

           // Copy the page and insert it
           if ( ! empty( $post ) ) {
                $new_id = $this->duplicate_product( $post );

                // If you have written a plugin which uses non-WP database tables to save
                // information about a page you can hook this action to dupe that data.
                do_action( 'woocommerce_duplicate_product', $new_id, $post );

                // Redirect to the edit screen for the new draft page
                //wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
                return ($new_id);
                //wp_redirect( $url_salto );
                exit;
          } else {
                wp_die(__( 'Product creation failed, could not find original product:', 'qstomizer' ) . ' ' . $id );
           }
    }
    
    /**
    * Function to create the duplicate of the product.
    *
    * @access public
    * @param mixed $post
    * @param int $parent (default: 0)
    * @param string $post_status (default: '')
    * @return void
    */
    public function duplicate_product( $post, $parent = 0, $post_status = '' ) {
           global $wpdb;

           $new_post_author     = wp_get_current_user();
           $new_post_date       = current_time('mysql');
           $new_post_date_gmt   = get_gmt_from_date($new_post_date);

           if ( $parent > 0 ) {
                   $post_parent     = $parent;
                   $post_status         = $post_status ? $post_status : 'publish';
                   $suffix      = '';
           } else {
                   $post_parent     = $post->post_parent;
                   $post_status         = $post_status ? $post_status : 'draft';
                   $suffix          = ' ' . __( '(Custom)', 'qstomizer' );
           }

           $new_post_type       = $post->post_type;
           $post_content            = str_replace("'", "''", $post->post_content);
           $post_content_filtered   = str_replace("'", "''", $post->post_content_filtered);
           $post_excerpt            = str_replace("'", "''", $post->post_excerpt);
           $post_title              = str_replace("'", "''", $post->post_title).$suffix;
           $post_name               = str_replace("'", "''", $post->post_name);
           $comment_status          = str_replace("'", "''", $post->comment_status);
           $ping_status             = str_replace("'", "''", $post->ping_status);

           // Insert the new template in the post table
           $wpdb->query(
                           "INSERT INTO $wpdb->posts
                           (post_author, post_date, post_date_gmt, post_content, post_content_filtered, post_title, post_excerpt,  post_status, post_type, comment_status, ping_status, post_password, to_ping, pinged, post_modified, post_modified_gmt, post_parent, menu_order, post_mime_type)
                           VALUES
                           ('$new_post_author->ID', '$new_post_date', '$new_post_date_gmt', '$post_content', '$post_content_filtered', '$post_title', '$post_excerpt', '$post_status', '$new_post_type', '$comment_status', '$ping_status', '$post->post_password', '$post->to_ping', '$post->pinged', '$new_post_date', '$new_post_date_gmt', '$post_parent', '$post->menu_order', '$post->post_mime_type')");

           $new_post_id = $wpdb->insert_id;

           // Copy the taxonomies
           $this->duplicate_post_taxonomies( $post->ID, $new_post_id, $post->post_type );

           // Copy the meta information
           $this->duplicate_post_meta( $post->ID, $new_post_id );

           // Copy the children (variations)
           if ( $children_products =& get_children( 'post_parent='.$post->ID.'&post_type=product_variation' ) ) {

                   if ( $children_products )
                           foreach ( $children_products as $child )
                                   $this->duplicate_product( $this->get_product_to_duplicate( $child->ID ), $new_post_id, $child->post_status );
           }

           return $new_post_id;
    }

    /**
    * Get a product from the database to duplicate
    *
    * @access public
    * @param mixed $id
    * @return void
    */
    private function get_product_to_duplicate( $id ) {
           global $wpdb;

           $id = absint( $id );

           if ( ! $id )
                   return false;

           $post = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE ID=$id" );

           if ( isset( $post->post_type ) && $post->post_type == "revision" ) {
                   $id   = $post->post_parent;
                   $post = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE ID=$id" );
           }
           return $post[0];
    }

    /**
    * Copy the taxonomies of a post to another post
    *
    * @access public
    * @param mixed $id
    * @param mixed $new_id
    * @param mixed $post_type
    * @return void
    */
    private function duplicate_post_taxonomies( $id, $new_id, $post_type ) {
           global $wpdb;
           $taxonomies = get_object_taxonomies($post_type); //array("category", "post_tag");
           foreach ($taxonomies as $taxonomy) {
                   $post_terms = wp_get_object_terms($id, $taxonomy);
                   $post_terms_count = sizeof( $post_terms );
                   for ($i=0; $i<$post_terms_count; $i++) {
                           wp_set_object_terms($new_id, $post_terms[$i]->slug, $taxonomy, true);
                   }
           }
    }

    /**
    * Copy the meta information of a post to another post
    *
    * @access public
    * @param mixed $id
    * @param mixed $new_id
    * @return void
    */
    private function duplicate_post_meta( $id, $new_id ) {
           global $wpdb;
           $post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$id");

           if (count($post_meta_infos)!=0) {
                   $sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
                   foreach ($post_meta_infos as $meta_info) {
                           $meta_key = $meta_info->meta_key;
                           $meta_value = addslashes($meta_info->meta_value);
                           $sql_query_sel[]= "SELECT $new_id, '$meta_key', '$meta_value'";
                   }
                   $sql_query.= implode(" UNION ALL ", $sql_query_sel);
                   $wpdb->query($sql_query);
           }
    }

    function qsmz_remove_post_term( $post_id, $term, $taxonomy ) {
 
        if ( ! is_numeric( $term ) ) {
          $term = get_term( $term, $taxonomy );
          if ( ! $term || is_wp_error( $term ) )
            return false;
          $term_id = $term->term_id;
        } else {
          $term_id = $term;
        }
       
        // Get the existing terms and only keep the ones we don't want removed
        $new_terms = array();
        $current_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
       
        foreach ( $current_terms as $current_term ) {
          if ( $current_term != $term_id )
            $new_terms[] = intval( $current_term );
        }
       
        return wp_set_object_terms( $post_id, $new_terms, $taxonomy );
    }

    function qsmz_qstomizer_mbe_create( $post_type, $post ){
        if ($post_type = "product"){
            add_meta_box('qsmz-meta', 'Qstomizer: Product Template', array(&$this, 'qsmz_qstomizer_mbe_function'), 'product', 'normal', 'high');
        }
        if ($post_type = "shop_order"){
                add_meta_box('custom_order_option', 'Qstomizer. Products customization', array(&$this, 'qsmz_custom_order_data'),'shop_order', 'normal', 'high');
        }
    }

    function qsmz_custom_order_data($order_id){
        $order = new WC_Order( $order_id );
        $items = $order->get_items();
        $validNonce = wp_create_nonce($time."-qsmz_GET_orderDATA");
        $shop = get_option("qsmz_shop_url");
        $qsmz_private_key = get_option("qsmz_private_key");
        $qsmz_public_key = get_option("qsmz_public_key");
        ?>
        <table class="widefat fixed" cellspacing="0">            
            <tbody>
        <?php
        foreach ( $items as $item ) {
            $product_name = $item['name'];
            $product_id = $item['product_id'];
            $product_variation_id = $item['variation_id'];
            $imgfront = get_post_meta($product_id, '_qsmz_img_front');
            $order_id = get_post_meta($product_id, '_qsmz_order_id');
            $order_key = get_post_meta($product_id, '_qsmz_key_order');

            if($imgfront[0]!="null") $strimgf = '<img src="'.$this->QSMZurls["QSMZurlS3images"].$imgfront[0].'" width="90" height="90">';

            $values = array(
                'shop' => $shop,
                'nonce' => $validNonce,
                'key' => $qsmz_public_key,
                'order_id' => $order_id[0],
                'order_key' => $order_key[0]
            );
            $signature = $this->qsmz_create_signature($values, $qsmz_private_key);

            $query = "?signaturewp=".$signature."&shop=".urlencode($shop)."&nonce=".$validNonce."&key=".$qsmz_public_key."&order_id=".$order_id[0]."&order_key=".$order_key[0];

            $url = $this->QSMZurls["QSMZorderURL"].$query;

            if ( get_post_meta( $product_id, '_qsmz_product', true) ){ 
                ?>
                    <tr class="alternate" valign="middle">
                        <td class="column-columnname" width="90" height="90" valign="middle"><?php echo $strimgf; ?></td>
                        <td class="column-columnname" valign="middle"><?php echo $product_name; ?> <a href="<?php echo $url; ?>" target="_blank">Show Data</a></td>
                    </tr>
            <?php
            }
        }
        ?>
                </tbody>
            </table>
        <?php
    }

    function qsmz_mbe_save_meta( $post_id ){
        if ( isset( $_POST['qsmz_mbe_costume'])){
            update_post_meta( $post_id, '_qsmz_mbe_costume', strip_tags( $_POST['qsmz_mbe_costume']));
        }
    }

    function qsmz_qstomizer_mbe_function($post){
        global $wpdb, $idioma;
        $qsmz_mbe_costume = get_post_meta( $post->ID, '_qsmz_mbe_costume', true);
        $qsmz_order_id = get_post_meta( $post->ID, '_qsmz_order_id', true);  
        $time = time();
        $validNonce = wp_create_nonce($time."-qsmz_load_designer");
        $qsmz_public_key = get_option("qsmz_public_key");
        $qsmz_private_key = get_option("qsmz_private_key");
        
        echo '<img src="'.plugins_url('/images/qs-icon.png', __FILE__).'">'._e('Select the product template:', 'qstomizer');

        $values = array(
            'key' => $qsmz_public_key,
            'nonce' => $validNonce
        );
        $signature = $this->qsmz_create_signature($values, $qsmz_private_key);

        $query = "?signaturewp=".$signature."&nonce=".$validNonce;
        $query .= "&key=".$qsmz_public_key;

        $url = $this->QSMZurls["QSMZurlProducts"].$query;

        $theReply = wp_remote_retrieve_body( wp_remote_get($url) );
      
        $json_a = json_decode($theReply, true);

        if (is_null($json_a)) echo "<p><b>There is no product templates. Qstomizer is still not active. Please check your license.</b></p>";
        ?>
             
        <p>Product:
            <select name="qsmz_mbe_costume">
                <option value="" <?php selected( $qsmz_mbe_costume, ''); ?>><?php _e('Select product', 'qstomizer'); ?></option>
                <?php
                foreach ($json_a as $article) {
                ?>
                    <option value="<?php echo $article['id']; ?>" <?php selected( $qsmz_mbe_costume, $article['id']); ?>><?php echo $article['description']; ?></option>
                <?php
                }
                ?>
            </select>
        </p>
        
        <?php   

    } 

    public function qsmz_modificar_plantilla($post){
        $qsmz_mbe_costume = get_post_meta( get_the_ID(), '_qsmz_mbe_costume', true);
        if ( is_single() && get_post_type() == 'product' && $qsmz_mbe_costume != '' ) {
            include( plugin_dir_path(__FILE__) . '/templates/customizador.php' );
            exit();
        }
    }

    public function qsmz_include_qstomizer($postID){
        global $wpdb;
        $qsmz_mbe_costume = get_post_meta( $postID, '_qsmz_mbe_costume', true);
        
        $time = time();
        $validNonce = wp_create_nonce($time."-qsmz_load_designer");
        $qsmz_public_key = get_option("qsmz_public_key");
        $qsmz_private_key = get_option("qsmz_private_key");

        $id_template = 0;
        $shop = get_option("qsmz_shop_url");

        $valuesnonce = array(
            'key_link' => $validNonce,
            'id_customizable' => $qsmz_mbe_costume,
            'fecha' => time(),
            'post_aduplicar' => $postID,
            'tipo_solicitud' => 1
        );
        $formats_values = array('%s', '%d', '%d', '%d', '%d');
        $wpdb->insert($wpdb->prefix."qstomizer_plugin_nonces", $valuesnonce, $formats_values);

        $values = array(
            'product' => $qsmz_mbe_costume,
            'template' => $id_template,
            'pkey' => $qsmz_public_key,
            'nonce' => $validNonce,
            'post' => $postID 
        );
	    $signature = $this->qsmz_create_signature($values, $qsmz_private_key);

	    $query = "?signaturewp=".$signature."&nonce=".$validNonce;
	    $query .= "&product=".$qsmz_mbe_costume."&template=".$id_template."&post=".$postID."&pkey=".$qsmz_public_key;

        $url = $this->QSMZurls["QSMZurl"].$query;
        $theBody = wp_remote_retrieve_body( wp_remote_get($url, array("timeout"=>30)) );
        print $theBody;        
    }

    function qsmz_create_signature($values, $secret){
    	foreach($values as $k => $v) {
			if($k == 'signaturewp') continue;
			$signature[] = $k . '=' . $v;
		}
		sort($signature);
		$signature = hash_hmac("sha256", implode('', $signature) , $secret, FALSE);
		return $signature;
    }

    function qsmz_qstomizer_add_page() {
        add_menu_page( 'Qstomizer', 'Qstomizer', 'manage_options', 'qsmz_admin_settings', array($this, 'qsmz_plugin_settings_page'),plugins_url('/images/qs-icon.png', __FILE__),59 );
        add_submenu_page('qsmz_admin_settings', __('General Settings', 'qstomizer'), __('Settings', 'qstomizer'), 'manage_options', 'qsmz_admin_settings', array($this, 'qsmz_plugin_settings_page'));
        add_submenu_page('qsmz_admin_settings', __('License', 'qstomizer'), __('License', 'qstomizer'), 'manage_options', 'qsmz_admin_license', array($this, 'qsmz_menu_license_settings'));
    }

    function js_enqueue( $hook ) {
    	wp_register_script('qsmz_custom_script', plugin_dir_url( __FILE__ ) . 'js/qstomizer.js');
	    wp_enqueue_script( 'qsmz_custom_script' );
	}

    function qsmz_admin_add_css(){
        if ( is_admin() ) {
          wp_register_style("qsmz_admin_styles", plugins_url ("css/qsmz_admin.css", __FILE__));
          wp_enqueue_style ('qsmz_admin_styles');
        }
    }

	function qsmz_admin_tabs( $current = 'general' ) {
        $tabs = array(  'general' => 'Plugin Options', 
                        'license' => __('license','qstomizer') );
        echo '<div id="icon-themes" class="icon32"><br></div>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach( $tabs as $tab => $name ){
            $class = ( $tab == $current ) ? ' nav-tab-active' : '';
            echo "<a class='nav-tab$class' href='?page=qsmz_admin_settings&tab=$tab'>$name</a>";

        }
        echo '</h2>';
    }

    function qsmz_get_remote_JSON($command){
        $time = time();
        $validNonce = wp_create_nonce($time."-qsmz_GET_productsJSON");
        $qsmz_private_key = get_option("qsmz_private_key");
        $shop = get_option("qsmz_shop_url");

         $values = array(
            'shop' => $shop,
            'nonce' => $validNonce,
            'page' => $command
        );
        $signature = $this->qsmz_create_signature($values, $qsmz_private_key);

        $query = "?signature=".$signature."&shop=".urlencode($shop)."&nonce=".$validNonce;
        $query .= "&page=".$command;

        $url = $this->QSMZurls["QSMZurl"].$query;

        $content=file_get_contents($url);
        $data=json_decode($content);
        return($data);
    }

    function qsmz_plugin_settings_page() {
        global $wpdb;
        
        
        if ( isset($_POST['QSMZSettingsNonceSave']) && wp_verify_nonce($_POST['QSMZSettingsNonceSave'],'frmQMZSettingNonceSave') ) {

            if (isset($_POST["categoria-personaliacion"])){
                update_option("qsmz_category_shop", $_POST["categoria-personaliacion"]);                    
            }

            if (isset($_POST["form_mostrar_img"]) && $_POST["form_mostrar_img"]=="Y"){
                if ($_POST["mostrar_img"]==1){
                    update_option("qsmz_mostrar_img", 1);
                }else{
                    update_option("qsmz_mostrar_img", 0);
                }
            }

            if (isset($_POST["form_tamano_img_width"]) && $_POST["form_tamano_img_width"]=="Y"){
                $width = filter_var($_POST["tamano_img_width"], FILTER_SANITIZE_NUMBER_INT);
                update_option("qsmz_tamano_img_width", $width);
            }
            if (isset($_POST["form_tamano_img_height"]) && $_POST["form_tamano_img_height"]=="Y"){
                $height = filter_var($_POST["tamano_img_height"], FILTER_SANITIZE_NUMBER_INT);
                update_option("qsmz_tamano_img_height", $height);
            }

            ?>
                <div id="message" class="updated">
                    <p><strong><?php _e('Settings saved.', 'qstomizer') ?></strong></p>
                </div>
            <?php

        }
        $qsmz_category = get_option("qsmz_category_shop");
        $qsmz_category_hide = get_option("qsmz_category_hide");
        $qsmz_mostrar_img = get_option("qsmz_mostrar_img");
        $qsmz_width = get_option("qsmz_tamano_img_width");
        $qsmz_height = get_option("qsmz_tamano_img_height");
        $settings = get_option( "qsmz_settings" );

        ?>
	        <div class="wrap">
	            <h2><?php _e('Qstomizer: General Settings', 'qstomizer'); ?></h2>
                <div class="qsmz_admin_cuadro bgcolorNaranja rounded8">
                    <p><img src="<?php echo plugins_url('/images/qs-icon.png', __FILE__); ?>"> <?php echo _e('To create and manage products, orders, templates, etc., please login to qstomizer admin panel', 'qstomizer'); ?><a href="https://www.qstomizer.com/cloud/login.php" target="_blank"><?php echo _e('here', 'qstomizer'); ?></a>.</p>
                </div>

	        <form method="POST" action="">
	            <?php wp_nonce_field('frmQMZSettingNonceSave','QSMZSettingsNonceSave'); ?>
                

	            <br><h3><?php _e('Category and Images', 'qstomizer'); ?></h3>
	            <p><?php _e('Select the category where the custom products will be stored.', 'qstomizer'); ?> </p>        
	            <table class="form-table">  
	                <tr valign="top">  
	                    <th scope="row">  
	                        <label for="cat_pers">  
	                            <?php _e('Category','qstomizer'); ?>
	                        </label>  
	                    </th>  
	                    <td>  
	                        <select name="categoria-personaliacion"> 
	                        <option value=""><?php echo esc_attr(__('Select Category:', 'qstomizer')); ?></option> 
	                        <?php 
	                            $args = array(
	                                'orderby'    => 'ASC',
	                                'hide_empty' => 0
	                            );

	                            $categories = get_terms( 'product_cat', $args ); 
	                             foreach ($categories as $category) {
	                                   $option = '<option value="'.$category->slug.'" ';
	                                   if ($category->slug == $qsmz_category) {
	                                        $option .= 'selected';
	                                   }
	                                   $option .= '>'.$category->name;
	                                   $option .= ' ('.$category->term_id.')';
	                                   $option .= '</option>';
	                                   echo $option;
	                             }
	                        ?>
	                       </select> 
	                    </td>  
	                </tr>   
	                
                  <tr valign="top">
                      <th scope="row">  
                          <label for="mostrar_img">  
                              <?php _e('Show images in the "content":','qstomizer'); ?>
                          </label>  
                      </th>
                      <td>
                          <input type="checkbox" name="mostrar_img" value="1" <?php if ($qsmz_mostrar_img==1) echo "checked"; ?>>
                          <input type="hidden" name="form_mostrar_img" value="Y">
                      </td>
                  </tr>
                  <tr valign="top">
                      <th scope="row">  
                          <label for="tamano_img_width">  
                              <?php _e('Image With (pixels):','qstomizer'); ?>
                          </label>  
                      </th>
                      <td>
                          <input type="text" name="tamano_img_width" value="<?php echo $qsmz_width; ?>"  size="5">
                          <input type="hidden" name="form_tamano_img_width" value="Y">
                      </td>
                  </tr>
                  <tr valign="top">
                      <th scope="row">  
                          <label for="tamano_img_height">  
                              <?php _e('Image height (pixels):','qstomizer'); ?>
                          </label>  
                      </th>
                      <td>
                          <input type="text" name="tamano_img_height" value="<?php echo $qsmz_height; ?>" size="5">
                          <input type="hidden" name="form_tamano_img_height" value="Y">
                      </td>
                  </tr>
	            </table>  
	            <p>  
	                <input type="submit" value="<?php _e('Save','qstomizer'); ?>" class="button-primary"/>  
	            </p> 
	            </form>
	        </div>
        <?php
    }

    function qsmz_menu_license_settings(){
        global $qsmz_idioma, $wpdb;

        $qsmz_shop_url = get_option("qsmz_shop_url");
        $mostrarMsg = false;

        if (isset($_POST['QSMZAccessNonceSave'])  && wp_verify_nonce($_POST['QSMZAccessNonceSave'],'frmQMZAccessNonceSave') ) {
            if (isset($_POST['qsmzprivate_key']) && isset($_POST['qsmzpublic_key'])){
                $clave_pub = filter_var($_POST['qsmzpublic_key'], FILTER_SANITIZE_STRING);
                $clave_pri = filter_var($_POST['qsmzprivate_key'], FILTER_SANITIZE_STRING);
                update_option("qsmz_private_key", $clave_pri);
                update_option("qsmz_public_key", $clave_pub);

                $values = array(
                    'url' => $qsmz_shop_url,
                    'publica' => $clave_pub,
                    'nonce' => $validNonce
                );
                $signature = $this->qsmz_create_signature($values, $clave_pri);

                $query = "?signaturewp=".$signature."&url=".urlencode($qsmz_shop_url);
                $query .= "&key=".$clave_pub."&nonce=".$validNonce;

                $url = $this->QSMZurls["QSMZurlActivacion"].$query;
                $theReply = wp_remote_retrieve_body( wp_remote_get($url) );
              
                $json_a = json_decode($theReply, true);
                $mostrarMsg = true;
                if (!$json_a[0]){
                    $clase = "error";
                    $msg = $json_a[1];
                    update_option("qsmz_activacion_msg", "");
                }else{
                    $clase = "updated";
                    $msg = $json_a[1];
                    update_option("qsmz_activacion_msg", $json_a[1]);
                }
            }
        }
        $qsmz_public_key = get_option("qsmz_public_key");
        $qsmz_private_key = get_option("qsmz_private_key");
        $qsmz_shop_url = get_option("qsmz_shop_url");
        $validNonce = wp_create_nonce("nonce-qsmz_active_shop");
        $qsmz_activacion_msg = get_option("qsmz_activacion_msg");
            

        ?>
            <div class="wrap">
                <h2><?php _e('Qstomizer: License keys', 'qstomizer'); ?></h2> 

            <form method="POST" action="">
                <?php wp_nonce_field('frmQMZAccessNonceSave','QSMZAccessNonceSave'); ?>
                <p><?php _e('Type the public and the secret key of your shop.','qstomizer'); ?> </p>
                <?php if ($mostrarMsg){ ?>
                <div class="<?php echo $clase; ?>">
                    <p><?php echo $msg; ?></p>
                </div>
                <?php }else{ ?>
                <div class="qsmz_admin_cuadro bgcolorNaranja rounded8">
                    <h3><img src="<?php echo plugins_url('/images/qs-icon.png', __FILE__); ?>"> <?php echo _e('Click <a href="http://www.qstomizer.com/cloud/signup.php" target="_blank">here</a> to get your <b>FREE</b> keys.', 'qstomizer'); ?></h3>
                </div>
                <?php } ?>
                <h3><?php echo $qsmz_activacion_msg; ?></h3>
                <table class="form-table"> 
                    <tr valign="top">
                        <th scope="row">  
                            <label for="ocult_cat">  
                                <?php _e('Shop URL:', 'qstomizer'); ?> 
                            </label>  
                        </th>
                        <td>
                            <?php echo $qsmz_shop_url; ?>
                        </td>
                    </tr> 
                    <tr valign="top">
                        <th scope="row">  
                            <label for="ocult_cat">  
                                <?php _e('Key:', 'qstomizer'); ?> 
                            </label>  
                        </th>
                        <td>
                            <input type="input" name="qsmzpublic_key" value="<?php echo $qsmz_public_key; ?>" size="30">
                            <input type="hidden" name="form_permitir_imagenes" value="Y">
                        </td>
                    </tr> 
                    <tr valign="top">
                        <th scope="row">  
                            <label for="ocult_cat">  
                                <?php _e('Secret:', 'qstomizer'); ?> 
                            </label>  
                        </th>
                        <td>
                            <input type="input" name="qsmzprivate_key" value="<?php echo $qsmz_private_key; ?>" size="30">
                            <input type="hidden" name="form_secret" value="Y">
                        </td>
                    </tr> 
                </table>

                <p>  
                    <input type="submit" value="<?php _e('Shop Activation','qstomizer'); ?>" class="button-primary"/>  
                </p> 
                </form>
            </div>
        <?php 
    }

    function qsmz_menu_config_producto(){   
        global $wpdb;
        $id_customizable=absint($_GET["rec_id"]);
        echo $id_customizable;
    }
}


if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { 
    if ( in_array( 'qstomizerwp/qstomizerwp.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { 
        add_action( 'admin_notices', 'qsmz_install_fallback_notice2' );
    }else{
        global $qsmztp;
        $rmQstomizer = new Qstomizer();
    }
} else {
    add_action( 'admin_notices', 'qsmz_install_fallback_notice' );
    
    function qsmz_install_fallback_notice(){
        ?>
        <div class="error">Qstomizer: <?php _e('You have to install the free Woocommerce plugin in order to use Qstomizer. For more information and get the plugin, visit this:', 'qstomizer'); ?><a href="http://www.woothemes.com/woocommerce/">http://www.woothemes.com/woocommerce/</a></div>
        <?php
    }
}


function qsmz_install_fallback_notice2(){
        ?>
        <div class="error">Qstomizer: <?php _e('You must deactivate the Qstomizer plugin and activate again.','qstomizer'); ?> </div>
        <?php
}

?>