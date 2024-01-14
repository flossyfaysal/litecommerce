<?php
/**
 *
 * @version 3.2.0
 * @package LiteCommerce\Classes
 */

 defined ( 'ABSPATH' ) || exit; 

 class LC_Query{
    public $query_vars = array();
    private static $product_query;
    private static $chosen_attributes;
    private $filterer;

    public function __construct(){
        add_action('init', array($this, 'add_endpoints'));

        if( !is_admin()){
            add_action('wp_loaded', array($this, 'get_errors'), 20);
            add_action('query_vars', array($this, 'add_query_vars'), 0);
            add_action('parse_request', array($this, 'parse_request'), 0);
            add_action('pre_get_posts', array($this, 'pre_get_posts'));
        }

        $this->init_query_vars();
    }

    public function parse_request(){
        global $wp;
        foreach( $this->get_query_vars() as $key => $var){
            if( isset( $_GET[$var])){
                $wp->query_vars[$key] = sanitize_text_field(wp_unslash($_GET[$var]));
            }elseif( isset( $wp->query_vars[$var])){
                $wp->query_vars[$key] = $wp->query_vars[$var];
            }
        }
    }

    public function pre_get_posts($q){}

    public function get_errors(){
        $error = ! empty( $_GET['lc_error']) ? sanitize_text_field( wp_unslash( $_GET['lc_error'])) : '';
        
        if( $error && ! lc_has_notice( $error, 'error')){
            lc_add_notice( $error, 'error' );
        }
    }

    public function add_endpoints(){
        $mask = $this->get_endpoint_mask();

        foreach( $this->get_query_vars() as $key => $var ){
            if( !empty( $var )){
                add_rewrite_endpoint( $var, $mask);
            }
        }
    }

    public function get_endpoint_mask(){
      return 4057;
    }

    public function get_query_vars(){
        return apply_filters('litecommerce_get_query_vars', $this->query_vars);
    }

    public function add_query_vars( $vars ){
        foreach( $this->get_query_vars() as $key => $var ){
            $vars[] = $key; 
        }
        return $vars;
    }

    public function init_query_vars(){
        $this->query_vars = array(
            'order-pay' => get_option('litecommerce_checkout_pay_endpoint', 'order-pay'),
            'order-received' => get_option('litecommerce_checkout)order_received_endpoint', 'order-received'),
            'edit_address' => 'edit-address'
        );
    }
 }