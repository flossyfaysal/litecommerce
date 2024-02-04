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

    private static function lc_ajax_headers()
    {
        if (!headers_sent()) {
            send_origin_headers();
            send_nosniff_header();
            lc_nocache_headers();
            header('Content-Type: text/html; charset=' . get_option('blog_charset'));
            header('X-Robots--Tag: noindex');
        } elseif (WP_DEBUG) {
            headers_sent($file, $line);
            trigger_error("lc_ajax_headers cannot set header - headers already sent by {$file} on line {$line}");
        }
    }

    public static function do_lc_ajax()
    {
        global $wp_query;

        if (!empty($_GET['lc-ajax'])) {
            $wp_query->set('lc-ajax', sanitize_text_field(wp_unslash($_GET['lc-ajax'])));
        }

        $action = $wp_query->get('lc-ajax');

        if ($action) {
            self::lc_ajax_headers();
            $action = sanitize_text_field($action);
            do_action('lc_ajax_' . $action);
            wp_die();
        }
    }

    public static function add_ajax_events()
    {
        $ajax_events_nopriv = array(
            'get_refreshed_fragments',
            'apply_coupon',
            'remove_coupon',
            'update_shipping_method',
            'get_cart_totals',
            'update_order_review',
            'add_to_cart',
            'remove_from_cart',
            'checkout',
            'get_variation',
            'get_customer_location',
        );

        foreach ($ajax_events_nopriv as $ajax_event) {
            add_action('wp_ajax_woocommerce_' . $ajax_event, array(__CLASS__, $ajax_event));
            add_action('wp_ajax_nopriv_woocommerce_' . $ajax_event, array(__CLASS__, $ajax_event));
            add_action('wc_ajax_' . $ajax_event, array(__CLASS__, $ajax_event));
        }


        $ajax_events = array(
            'feature_product',
            'mark_order_status',
            'get_order_details',
            'add_attribute',
            'add_new_attribute',
            'remove_variations',
            'save_attributes',
            'add_attributes_and_variations',
            'add_variation',
            'link_all_variations',
            'revoke_access_to_download',
            'grant_access_to_download',
            'get_customer_details',
            'add_order_item',
            'add_order_fee',
            'add_order_shipping',
            'add_order_tax',
            'add_coupon_discount',
            'remove_order_coupon',
            'remove_order_item',
            'remove_order_tax',
            'calc_line_taxes',
            'save_order_items',
            'load_order_items',
            'add_order_note',
            'delete_order_note',
            'json_search_products',
            'json_search_products_and_variations',
            'json_search_downloadable_products_and_variations',
            'json_search_customers',
            'json_search_categories',
            'json_search_categories_tree',
            'json_search_taxonomy_terms',
            'json_search_product_attributes',
            'json_search_pages',
            'term_ordering',
            'product_ordering',
            'refund_line_items',
            'delete_refund',
            'rated',
            'update_api_key',
            'load_variations',
            'save_variations',
            'bulk_edit_variations',
            'tax_rates_save_changes',
            'shipping_zones_save_changes',
            'shipping_zone_add_method',
            'shipping_zone_remove_method',
            'shipping_zone_methods_save_changes',
            'shipping_zone_methods_save_settings',
            'shipping_classes_save_changes',
            'toggle_gateway_enabled',
        );

        foreach ($ajax_events as $ajax_event) {
            add_action('wp_ajax_woocommerce_' . $ajax_event, array(__CLASS__, $ajax_event));
        }

        $ajax_private_events = array(
            'order_add_meta',
            'order_delete_meta',
        );

        foreach ($ajax_private_events as $ajax_event) {
            add_action(
                'wp_ajax_woocommerce_' . $ajax_event,
                function () use ($ajax_event) {
                    call_user_func(array(__CLASS__, $ajax_event));
                }
            );
        }

        $ajax_heartbeat_callbacks = array(
            'order_refresh_lock',
            'check_locked_orders',
        );
        foreach ($ajax_heartbeat_callbacks as $ajax_callback) {
            add_filter(
                'heartbeat_received',
                function ($response, $data) use ($ajax_callback) {
                    return call_user_func_array(array(__CLASS__, $ajax_callback), func_get_args());
                },
                11,
                2
            );
        }
    }

    public static function get_refreshed_fragments()
    {
        ob_start();

        litecommerce_mini_cart();
        $mini_cart = ob_get_clean();


        $data = array(
            'fragments' => apply_filters(
                'litecommerce_add_to_cart_framgements',
                array(
                    'div.widget_shoppping_cart_content' => '<div class="widget_shopping_cart_content"' . $mini_cart . '</div>',
                )
            ),
            'cart_hash' => LC()->cart->get_cart_hash(),
        );

        wp_send_json($data);
    }
}