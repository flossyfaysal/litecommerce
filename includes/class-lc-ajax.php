<?php

/**
 * Litecommerce LC_AJAX Ajax Event Handlers.
 * 
 * @package Litecommerce\Class
 */

defined('ABSPATH') || exit;

class LC_AJAX
{
    public static function init()
    {
        add_action('init', array(__CLASS__, 'define_ajax'), 0);
        add_action('template_redirect', array(__CLASS__, 'do_lc_ajax'), 0);
        self::add_ajax_event();
    }

    public static function get_endpoint($request = '')
    {
        return esc_url_raw(apply_filters('woocommerce_ajax_get_endpoint', add_query_arg('wc-ajax', $request, remove_query_arg(array('remove_item', 'add-to-cart', 'added-to-cart', 'order_again', '_wpnonce'), home_url('/', 'relative'))), $request));
    }

    public function define_ajax()
    {
        if (!empty($_GET['lc-ajax'])) {
            lc_maybe_define_constant('DOING_AJAX', true);
            lc_maybe_define_constant('LC_DOING_AJAX', true);
            if (!WP_DEBUG || (WP_DEBUG && !WP_DEBUG_DISPLAY)) {
                @init_set('display_errors', 0);
            }
            $GLOBALS['wpdb']->hide_errors();
        }
    }
}