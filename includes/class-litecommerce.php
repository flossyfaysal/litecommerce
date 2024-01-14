<?php
/**
 * LiteCommerce setup
 *
 * @package LiteCommerce
 * @since   1.1.0
 */

 defined( 'ABSPATH' ) || exit;

final class LiteCommerce{
    public $version = '1.1.0';
    public $session = null;
    public $query = null;
    public $api;
    public $cart = null;
    public $customer = null;

    protected static $_instance = null;

    public static function instance(){
        if( is_null( self::$_instance )) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __clone(){

    }

    public function __wakeup(){

    }

    public function __construct(){
        $this->define_constants();
        $this->define_tables();
        $this->includes();
        $this->init_hooks();
    }


    public function on_plugins_loaded(){
        do_action( 'litecommerce_loaded' );
    }

    public function define_constants(){
        $upload_dir = wp_upload_dir( null, false);
        $this->define( 'LC_ABSPATH', dirname( LC_PLUGIN_FILE . '/'));
        $this->define( 'LC_PLUGIN_BASENAME', plugin_basename( LC_PLUGIN_FILE));
        $this->define('LC_VERSION', $this->version);
        $this->define('LC_ROUNDING_PRECISION', 6);
        $this->define('LC_DISCOUNT_ROUNDING_MODE', 2); 
        $this->define( 'LC_TAX_ROUNDING_MODE', 'yes' === get_option( 'litecommerce_prices_include_tax', 'no' ) ? 2 : 1 );
		$this->define( 'LC_DELIMITER', '|' );
		$this->define( 'LC_LOG_DIR', $upload_dir['basedir'] . '/wc-logs/' );
		$this->define( 'LC_SESSION_CACHE_GROUP', 'lc_session_id' );
		$this->define( 'LC_TEMPLATE_DEBUG_MODE', false );
    }

    public function define_tables(){
        global $wpdb; 

        $tables = array(
            'payment_tokenmeta' => 'lc_payment_tokenmeta',
            'order_itemmeta' => 'lc_order_itemmeta',
            'lc_product_meta_lookup' => 'lc_product_meta_lookup',
            'lc_tax_rate_classes' => 'lc_tax_rate_classes',
            'lc_reserved_stock' => 'lc_reserved_stock'
        );

        foreach( $tables as $name => $table ){
            $wpdb->name = $wpdb->prefix . $table; 
            $wpdb->tables[] = $tables;
        }
    }

    public function includes(){
        // Autoloader 

        // Interfaces

        // Traits

        // Abstract Classes

        // Core Classes

        // Data Store

        // REST API

        // Tracks.

        if ($this->is_request( 'admin ')){
            include_once LC_ABSPATH . '/includes/admin/class-lc-admin.php';
        }

        $in_post_editor = doing_action('load-post.php') || doing_action('load-post-new.php');

        if( $this->is_request( 'frontend') ||
            $this->is_rest_api_request() || $in_post_editor ){
                $this->frontend_includes();
        }
    }

    public function frontend_includes(){
        
    }

    public function is_request( $type ){
        switch( $type ){
            case 'admin': 
                return is_admin();
            case 'ajax': 
                return defined( 'DOING_AJAX');
            case 'cron':
                return defined( 'DOINT_CRON');
            case 'frontend':
                return ( !is_admin() || defined('DOING_AJAX')) && ! defined( 'DOINT_CRON') && ! $this->is_rest_api_request();
        }
    }

    public function is_rest_api_request(){
        if( empty( $_SERVER['REQUEST_URI'])){
            return false; 
        }
        
        $rest_prefix = trailingslashit( rest_get_url_prefix());
        $is_rest_api_request = ( false !== strpos($_SERVER['REQUEST_URI'], $rest_prefix));
        
        return apply_filters('litecommerce_is_rest_api_request', $is_rest_api_request);

    }

    public function init_hooks(){
        //register_activation_hook( LC_PLUGIN_FILE, array( 'LC_Install', 'install') );

        add_action( 'plugins_loaded', array($this, 'on_plugins_loaded'), -1);
        add_action( 'after_setup_theme', array($this, 'setup_environment')); 
        add_action( 'after_setup_theme', array($this, 'include_template_functinos'));
        add_action('load-post.php', array($this, 'includes'));
        // add_action('init', array($this, 'init'), 0);
        // add_action('init', array( 'LC_Shortcodes','init'));
        // add_action('init', array( 'LC_Emails','init_transactional_emails'));
        // add_action( 'init', array( $this, 'add_image_sizes' ) );
		// add_action( 'init', array( $this, 'load_rest_api' ) );
		// add_action( 'init', array( 'LC_Site_Tracking', 'init' ) );
    }

    public function setup_environment(){
        $this->define( 'LC_TEMPLATE_PATH', $this->template_path() );
        $this->add_thumbnail_support();
    }

    private function add_thumbnail_support(){
        if( !current_theme_supports( 'post-thumbnails')){
          add_theme_support('post-thumbnails');  
        }
        add_post_type_support('product', 'thumbnail');
    }

    private function define( $name, $value ){
        if( !defined( $name )){
            define( $name, $value );
        }
    }

    public function template_path(){
        return apply_filters( 'litecommerce_template_path', 'litecommerce/', 11);
    }

    public function include_template_functinos(){
        include_once LC_ABSPATH . '/includes/lc-template-functions.php';
    }
    
}