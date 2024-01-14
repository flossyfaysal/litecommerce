<?php
/**
 * Plugin Name: LiteCommerce
 * Plugin URI: https://litecommerce.com/
 * Description: A very lite ecommerce platform that covers all necessary stuffs.
 * Version: 1.1.0
 * Author: Jompha
 * Author URI: https://jompha.com
 * Text Domain: litecommerce
 * Domain Path: /i18n/languages/
 * Requires at least: 6.3
 * Requires PHP: 7.4
 *
 * @package LiteCommerce
 */

defined( 'ABSPATH' ) || exit; 

if( !defined( 'LC_PLUGIN_FILE') ){
    define( 'LC_PLUGIN_FILE', __FILE__ );
}

if( ! class_exists( 'LiteCommerce', false ) ){
    include_once dirname( LC_PLUGIN_FILE ) . '/includes/class-litecommerce.php';
}

function LC(){
    return LiteCommerce::instance();
}

$GLOBALS['litecommerce'] = LC();
