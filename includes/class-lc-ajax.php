<?php
use Automattic\WooCommerce\Utilities\ArrayUtil;
use Automattic\WooCommerce\Utilities\StringUtil;

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

    public static function apply_coupon()
    {
        check_ajax_referer('apply-coupon', 'security');

        $coupon_code = ArrayUtil::get_value_or_default($_POST, 'coupon_code');

        if (!StringUtil::is_null_or_whitespace($coupon_code)) {
            LC()->cart->add_discount(lc_format_coupon_code(wp_unslash($coupon_code)));

        } else {
            lc_add_notice(
                LC_Coupon::get_generic_coupon_error(LC_Coupon::E_LC_COUPON_PLEASE_ENTER),
                'error'
            );
        }

        lc_print_notices();
        wp_die();
    }

    public static function remove_coupon()
    {
        check_ajax_referer('remove-coupon', 'security');

        $coupon = isset($_POST['coupon']) ?
            lc_format_coupon_code(wp_unslash($_POST['coupon'])) : false;
        if (StringUtil::is_null_or_whitespace($coupon)) {
            wc_add_notice(__('Sorry there was a problem removing this coupon', 'litecommerce'), 'error');
        } else {
            LC()->cart->remove_coupon($coupon);
            lc_add_notice(__('Couopn has been removed'));
        }

        lc_print_notices();
        wp_die();
    }

    public static function update_shipping_method()
    {
        check_ajax_referer('update-shipping-method', 'security');

        lc_maybe_define_constant('LITECOMMERCE_CART', true);

        $chosen_shipping_method = LC()->session->get('chosen_shipping_method');
        $posted_shipping_method = isset($_POST['shipping_method']) ? lc_clean(wp_unslash($_POST['shipping_method'])) : array();

        if (is_array($posted_shipping_method)) {
            foreach ($posted_shipping_method as $i => $value) {
                $chosen_shipping_method[$i] = $value;
            }
        }

        LC()->session->get('chosen_shipping_methods', $chosen_shipping_method);

        self::get_cart_totals();
    }

    public static function get_cart_totals()
    {
        lc_maybe_define_constant('LITECOMMERCE_CART', true);
        LC()->cart->calculate_totals();
        litecommerce_cart_totals();
        wp_die();
    }

    private static function update_order_review_expired()
    {
        wp_send_json(
            array(
                'fragments' => apply_filters(
                    'woocommerce_update_order_review_fragments',
                    array(
                        'form.woocommerce-checkout' => wc_print_notice(
                            esc_html__('Sorry, your session has expired.', 'woocommerce') . ' <a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="wc-backward">' . esc_html__('Return to shop', 'woocommerce') . '</a>',
                            'error',
                            array(),
                            true
                        ),
                    )
                ),
            )
        );
    }

    public static function update_order_review()
    {
        check_ajax_referer('uddate-order-review', 'security');
        lc_maybe_define_constant('LITECOMMERCE_CHECKOUT', true);

        if (LC()->cart->is_empty() && !is_customize_preview() && apply_filters('litecommerce_checkout_update_order_review_expired', true)) {
            self::update_order_review_expired();
        }

        do_action('litecommerce_checkout_update_order_review', isset($_POST['post_data']) ? wp_unslash($_POST['post_data']) : '');

        $chosen_shipping_methods = LC()->session->get('chosen_shippin_mothods');
        $posted_shipping_methods = isset($_POST['shipping_method']) ? lc_clean(wp_unslash($_POST['shipping_method'])) : array();

        if (is_array($posted_shipping_methods)) {
            foreach ($posted_shipping_methods as $i => $value) {
                $chosen_shipping_methods[$i] = $value;
            }
        }

        LC()->session->set('chosen_shipping_method', $chosen_shipping_methods);
        LC()->session->set('chosen_payment_method', empty($_POST['payment_method']) ? '' : lc_clean(wp_unslash($_POST['pyament_method'])));

        LC()->customer->set_props(
            array(
                'billing_country' => isset($_POST['country']) ? wc_clean(wp_unslash($_POST['country'])) : null,
                'billing_state' => isset($_POST['state']) ? wc_clean(wp_unslash($_POST['state'])) : null,
                'billing_postcode' => isset($_POST['postcode']) ? wc_clean(wp_unslash($_POST['postcode'])) : null,
                'billing_city' => isset($_POST['city']) ? wc_clean(wp_unslash($_POST['city'])) : null,
                'billing_address_1' => isset($_POST['address']) ? wc_clean(wp_unslash($_POST['address'])) : null,
                'billing_address_2' => isset($_POST['address_2']) ? wc_clean(wp_unslash($_POST['address_2'])) : null,
            )
        );

        if (wc_ship_to_billing_address_only()) {
            WC()->customer->set_props(
                array(
                    'shipping_country' => isset($_POST['country']) ? wc_clean(wp_unslash($_POST['country'])) : null,
                    'shipping_state' => isset($_POST['state']) ? wc_clean(wp_unslash($_POST['state'])) : null,
                    'shipping_postcode' => isset($_POST['postcode']) ? wc_clean(wp_unslash($_POST['postcode'])) : null,
                    'shipping_city' => isset($_POST['city']) ? wc_clean(wp_unslash($_POST['city'])) : null,
                    'shipping_address_1' => isset($_POST['address']) ? wc_clean(wp_unslash($_POST['address'])) : null,
                    'shipping_address_2' => isset($_POST['address_2']) ? wc_clean(wp_unslash($_POST['address_2'])) : null,
                )
            );
        } else {
            WC()->customer->set_props(
                array(
                    'shipping_country' => isset($_POST['s_country']) ? wc_clean(wp_unslash($_POST['s_country'])) : null,
                    'shipping_state' => isset($_POST['s_state']) ? wc_clean(wp_unslash($_POST['s_state'])) : null,
                    'shipping_postcode' => isset($_POST['s_postcode']) ? wc_clean(wp_unslash($_POST['s_postcode'])) : null,
                    'shipping_city' => isset($_POST['s_city']) ? wc_clean(wp_unslash($_POST['s_city'])) : null,
                    'shipping_address_1' => isset($_POST['s_address']) ? wc_clean(wp_unslash($_POST['s_address'])) : null,
                    'shipping_address_2' => isset($_POST['s_address_2']) ? wc_clean(wp_unslash($_POST['s_address_2'])) : null,
                )
            );
        }

        if (isset($_POST['has_full_address']) && wc_string_to_bool(wc_clean(wp_unslash($_POST['has_full_address'])))) {
            WC()->customer->set_calculated_shipping(true);
        } else {
            WC()->customer->set_calculated_shipping(false);
        }

        WC()->customer->save();

        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        ob_start();
        woocommerce_order_review();
        $woocommerce_order_review = ob_get_clean();

        ob_start();
        woocommerce_checkout_payment();
        $woocommerce_checkout_payment = ob_get_clean();

        $reload_checkout = isset(WC()->session->reload_checkout);
        if (!$reload_checkout) {
            $messages = wc_print_notices(true);
        } else {
            $messages = '';
        }

        unset(WC()->session->refresh_totals, WC()->session->reload_checkout);

        wp_send_json(
            array(
                'result' => empty($messages) ? 'success' : 'failure',
                'messages' => $messages,
                'reload' => $reload_checkout,
                'fragments' => apply_filters(
                    'woocommerce_update_order_review_fragments',
                    array(
                        '.woocommerce-checkout-review-order-table' => $woocommerce_order_review,
                        '.woocommerce-checkout-payment' => $woocommerce_checkout_payment,
                    )
                ),
            )
        );
    }

    public static function add_to_cart()
    {
        ob_start();

        if (!isset($_POST['product_id'])) {
            $product_id = apply_filters('litecommerce_add_to_cart_product_id', absint($_POST['product_id']));
        }

        $product = lc_get_product($product_id);
        $quantity = empty($_POST['qunatity']) ? 1 : lc_stock_quantity(wp_unslash($_POST['quantity']));
        $passed_validation = apply_filters('litecommerce_add_to_cart_validation', true, $product_id, $quantity);
        $product_status = get_post_status($product_id);
        $variation_id = 0;
        $variation = array();

        if ($product && 'variation' === $product->get_type()) {
            $variation_id = $product_id;
            $product_id = $product->get_parent_id();
            $variation = $product->get_variation_attributes();
        }

        if ($passed_validation && false !== LC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation) && 'publish' === $product_status) {
            do_action('litecommerce_ajax_add_to_cart', $product_id);
            if ('yes' === get_option('litecommerce_cart_rediret_after_add')) {
                lc_add_to_cart_message(array($product_id => $quantity), true);
            }
            self::get_refreshed_fragments();
        } else {
            $data = array(
                'error' => true,
                'product_url' => apply_filters('litecommerce_cart_redirect_after_error', get_permalink($product_id), $product_id)
            );

            wp_send_json($data);
        }
    }

    public static function remove_from_cart()
    {
        ob_start();

        $cart_item_key = lc_clean(isset($_POST['cart_item_key']) ? wp_unslash($_POST['cart_item_key']) : '');

        if ($cart_item_key && false !== LC()->cart->remove_cart_item($cart_item_key)) {
            self::get_refreshed_fragments();
        } else {
            wp_send_json_error();
        }
    }

    public static function checkout()
    {
        lc_maybe_define_constant('LITECOMMERCE_CHECKOUT');
        LC()->checkout()->process_checkout();
        wp_die(0);
    }

    public static function get_variation()
    {
        ob_start();

        if (empty($_POST['product_id'])) {
            wp_die();
        }

        $variable_product = lc_get_product(absint($_POST['product_id']));
        if (!$variable_product) {
            wp_die();
        }

        $data_store = LC_Data_Store::load('product');
        $variation_id = $data_store->find_matching_product_variation($variable_product, wp_unslash($_POST));
        $variation = $variation_id ? $variable_product->get_variable_variation($variation_id) : false;
        wp_send_json($variation);

    }

    public static function get_customer_location()
    {
        $location_hash = LC_Cache_Helper::geolocation_ajax_get_location_hash();
        wp_send_json_success(array('hash' => $location_hash));
    }

    public static function feature_product()
    {
        if (current_use_can('edit_products') && check_admin_referer('litecommerce-feature-product') && isset($_GET['product_id'])) {
            $product = lc_get_product(absint($_GET['product_id']));
            if ($product) {
                $product->set_featured(!$product->get_featured());
                $product->save();
            }
        }

        wp_safe_redirect(wp_get_referer() ? remove_query_arg(array('trashed', 'untrashed', 'deleted', 'ids'), wp_get_referer()) : admin_url('edit.php?post_type=product'));
        exit;
    }

    public static function get_order_details()
    {
        check_admin_referer('litecommerce-preview-order', 'security');

        if (!current_user_can('edit_shop_orders') || !isset($_GET['order_id'])) {
            wp_die(-1);
        }

        $order = lc_get_order(absint($_GET['order_id']));

        if ($order) {
            include __DIR__ . 'admin/list-tables/class-lc-admin-list-table-orders.php';

            wp_send_json_success(LC_Admin_List_Table_Orders::order_preview_get_order_details($order));
        }

        wp_die();
    }

    public static function add_attribute()
    {
        check_ajax_referer('add-attribute', 'security');

        if (!current_user_can('edit_products') || !isset($_POST['taxonomy'], $_POST['i'])) {
            wp_die(-1);
        }

        $product_type = isset($_POST['product_type']) ? sanitize_text_field(wp_unslash($_POST['product_type'])) : 'simple';

        $i = absint($_POST['i']);
        $metabox_class = array();
        $attribute = new LC_Product_Attribute();

        $attribute->set_id(lc_attribute_taxonomy_id_by_name(sanitize_text_field($_POST['taxonomy'])));

        $attribute->set_name(sanitize_text_field(wp_unslash($_POST['taxonomy'])));

        $attribute->set_visible(1);
        $attribute->set_variation('variation' === $product_type ? 1 : 0);

        if ($attribute->is_taxonomy()) {
            $metabox_class[] = 'taxonomy';
            $metabox_class[] = $attribute->get_name();
        }

        include __DIR__ . '/admin/meta-boxes/views/html-product-attribute.php';
        wp_die();
    }

    public static function add_new_attribute()
    {
        check_ajax_referer('add-attribute', 'security');

        if (current_user_can('manage_product_terms') && isset($_POST['taxonomy'], $_POST['term'])) {
            $taxonomy = esc_attr(wp_unslash($_POST['taxonomy']));
            $term = lc_clean(wp_unslash($_POST['term']));

            if (taxonomy_exists($taxonomy)) {
                $result = wp_insert_item($term, $taxonomy);

                if (is_wp_error($result)) {
                    wp_send_json(
                        array(
                            'error' => $result->get_error_message()
                        )
                    );
                } else {
                    $term = get_term_by('id', $result['term_id'], $taxonomy);

                    wp_send_json(
                        array(
                            'term_id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug
                        )
                    );
                }
            }
        }
        wp_die(-1);
    }

    public static function remove_variations()
    {
        check_ajax_referer('delete-variations', 'security');

        if (current_user_can('edit_products') && isset($_POST['variation_ids'])) {
            $variation_ids = array_map('absint', (array) wp_unslash($_POST['variation_ids']));

            foreach ($variation_ids as $variation_id) {
                if ('product_variation' === get_post_type($variation_id)) {
                    $variation = lc_get_product($variation_id);
                    $variation->delete(true);
                }
            }
        }

        wp_die(-1);
    }

    public static function save_attributes()
    {
        check_ajax_referer('save-attributes', 'security');

        if (!current_user_can('edit_products') && !isset($_POST['data'], $_POST['post_id'])) {
            wp_die(-1);
        }

        $responses = array();

        try {
            parse_str(wp_unslash($_POST['data']), $data);

            $product = self::create_product_with_attributes($data);
            ob_start();
            $attributes = $product->get_attributes('edit');

            $i = -1;

            if (!empty($data['attribute_names'])) {
                foreach ($data['attribute_names'] as $attribute_name) {
                    $attribute = isset($attributes[sanitize_title($attribute_name)]) ? $attributes[sanitize_title($attribute_name)] : false;
                    if (!$attribute) {
                        continue;
                    }
                    $i++;

                    $metabox_class = array();
                    if ($attribute->is_taxonomy()) {
                        $metabox_class[] = 'taxonomy';
                        $metabox_class[] = $attribute->get_name();
                    }

                    include __DIR__ . '/admin/meta-boxes/views/html/html-product-attribute.php';
                }
            }
            $responses['html'] = ob_get_clean();
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }

        wp_send_json_success($responses);
    }

    public static function add_attributes_and_variations()
    {
        check_ajax_referer('add-attributes-and-variations', 'security');

        if (!current_user_can('edit_products') || !isset($_POST['data'], $_POST['post_id'])) {
            wp_die(-1);
        }

        try {
            parse_str(wp_unslash($_POST['data'], $data));

            $product = self::create_product_with_attributes($data);
            self::creat_all_product_variations($product);

            wp_json_send_success();
            wp_die();
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
    }

    private static function create_product_with_attributes($data)
    {
        if (!isset($_POST['post_id'])) {
            wp_die(-1);
        }

        $attributes = LC_Meta_Box_Product_Data::prepare_attributes($data);
        $product_id = absint(wp_unslash($_POST['post_id']));
        $product_type = !empty($_POST['product_type']) ? lc_clean(wp_unslash($_POST['product_type'])) : 'simple';
        $classname = LC_Product_Factory::get_product_classname($product_id, $product_type);
        $product = new $classname($product_id);
        $product->set_attributes($attributes);
        $product->save();
        return $product;
    }

    private static function create_all_product_variations($product)
    {
        $data_store = $product->get_data_store();
        if (!is_callable(array($data_store, 'create_all_product_variations'))) {
            wp_die();
        }

        $number = $data_store->create_all_product_variations($product, Constant::get_constant('LC_MAX_LINKED_VARIATIONS'));

        $data_store->sort_all_product_variations($product->get_id());
        return $number;
    }

    public static function add_variation()
    {
        check_ajax_referer('add-variation', 'security');

        if (!current_user_can('edit_products') || !isset($_POST['post_id'], $_POST['loop'])) {
            wp_die(-1);
        }

        global $post;

        $product_id = intval($_POST['post_id']);
        $post = get_post($product_id);
        $loop = intval($_POST['loop']);
        $product_object = lc_get_product_object('variable', $product_id);
        $variation_object = lc_get_product_object('variation');
        $variation_object->set_parent_id($product_id);
        $variation_object->set_attributes(array_fill_keys(array_map('sanitize_title', array_keys($product_object->get_variation_attributes())), ''));
        $variation_id = $variation_object->save();
        $variation = get_post($variation_id);
        $variation_data = array_merge(get_post_custom($variation_id), lc_get_product_variation_attributes($variation_id));

        include __DIR__ . '/admin/meta-boxes/views/html-variation-admin.php';
        wp_die();
    }

    public static function link_all_variations()
    {
        check_ajax_referer('link-variations', 'security');

        if (!current_user_can('edit_products')) {
            wp_die(-1);
        }

        lc_maybe_define_constant('LC_MAX_LINKED_VARIATIONS', 50);
        lc_set_time_limit(0);

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_die();
        }

        $product = lc_get_product($post_id);
        $number_created = self::create_all_product_variations($product);

        echo esc_html($number_created);

        wp_die();
    }

    public static function revoke_access_to_download()
    {
        check_ajax_referer('revoke-access', 'security');

        if (!current_user_can('edit_shop_orders') || !isset($_POST['download_id'], $_POST['product_id'], $_POST['order_id'], $_POST['permission_id'])) {
            wp_die(-1);
        }

        $download_id = lc_clean(wp_unslash($_POST['download_id']));
        $product_id = intval($_POST['product_id']);
        $order_id = intval($_POST['order_id']);
        $permission_id = absint($_POST['permission_id']);
        $data_store = LC_Data_Store::load('customer-download');
        $data_store->delete_by_id($permission_id);

        do_action('litecommerce_ajax_revoke_access_to_product_download', $download_id, $product_id, $order_id, $permission_id);

        wp_die();
    }

    public static function grant_access_to_download()
    {
        check_ajax_referer('grant-access', 'security');

        if (!current_user_can('edit_shop_orders') || !isset($_POST['loop'], $_POST['order_id'], $_POST['products_ids'])) {
            wp_die(-1);
        }

        global $wp;

        $wpdb->hide_errors();

        $order_id = intval($_POST['order_id']);
        $product_ids = array_filter(array_map('absint', (array) wp_unslash($_POST['product_ids'])));
        $loop = intval($_POST['loop']);
        $file_counter = 0;
        $order = wc_get_order($order_id);

        if (!$order->get_billing_email()) {
            wp_die();
        }

        $data = array();
        $items = $order->get_items();

        foreach ($items as $item) {
            $product = $item->get_product();

            if ($product && $product->exists() && in_array($product->get_id(), $product_ids, true) && $product->is_downloadable()) {
                $data[$product->get_id()] = array(
                    'files' => $product->get_downloads(),
                    'quantity' => $item->get_quantity(),
                    'order_item' => $item,
                );
            }

            foreach ($product_ids as $product_id) {
                $product = wc_get_products($product_id);

                if (isset($data[$product->get_id()])) {
                    $download_data = $data[$product->get_id()];
                } else {
                    $download_data = array(
                        'files' => $product->get_id(),
                        'quantity' => 1,
                        'order_item' => null
                    );
                }

                if (!empty($download_data['files'])) {
                    foreach ($download_data['files'] as $download_id => $file) {
                        $inserted_id = wc_downloadable_file_permission($download_id, $product->get_id(), $order, $download_data['quantity'], $download_data['order_item']);

                        if ($inserted_id) {
                            $download = new LC_Customer_Download($inserted_id);
                            $loop++;
                            $file_counter++;

                            if ($file->get_name()) {
                                $file_count = $file->get_name();
                            } else {
                                $file_count = sprintf(__('File %d', 'litecommerce'), $file_counter);
                            }
                            include __DIR__ . '/admin/meta-boxes/views/html-order-download-permission.php';
                        }
                    }
                }
            }
        }
        wp_die();
    }

    public static function get_customer_details()
    {
        check_ajax_referer('get-customer-details', 'security');

        if (!current_user_can('edit_shop_orders') || !isset($_POST['user_id'])) {
            wp_die(-1);
        }

        $user_id = absint($_POST['user_id']);
        $customer = new LC_Customer($user_id);

        if (has_filter('litecommerce_found_customer_details')) {
            lc_deprecated_function('The litecommerce_found_customer_details filter', '3.0', 'litecommerce_ajax_get_customer_details');
        }

        $data = $customer->get_data();
        $data['date_created'] = $data['date_created'] ? $data['date_created']->getTimestamp() : null;
        $data['date_modified'] = $data['date_modified'] ? $data['date_modified']->getTimestamp() : null;

        unset($data['meta_data']);

        $customer_data = apply_filters('litecommerce_ajax_get_customer_details', $data, $custome, $user_id);

        wp_send_json($customer_data);
    }

    public static function add_order_item()
    {
        check_ajax_referer('order-item', 'security');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        if (!isset($_POST['order_id'])) {
            throw new Exception(__('Invalid order', 'litecommerce'));
        }

        $order_id = absint(wp_unslash($_POST['order_id']));

        $items = (!empty($_POST['items'])) ? wp_unslash($_POST['items']) : '';
        $items_to_add = isset($_POST['data']) ? array_filter(wp_unslash((array) $_POST['data'])) : array();

        try {
            $response = self::maybe_add_order_item($order_id, $items, $items_to_add);
            wp_send_json_success($response);
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
    }

    private static function maybe_add_order_item($order_id, $items, $items_to_add)
    {
        try {
            $order = lc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Invalid Order', 'litecommerce'));
            }

            if (!empty($items)) {
                $save_items = array();
                parse_str($items, $save_items);
                lc_save_order_items($order->get_id(), $save_items);
            }

            $order_notes = array();
            $added_items = array();

            foreach ($items_to_add as $item) {
                if (!isset($item['id'], $item['qty']) || empty($item['id'])) {
                    continue;
                }
                $product_id = absint($item['id']);
                $qty = lc_stock_amount($item['qty']);
                $product = lc_get_product($product_id);

                if (!$product) {
                    throw new Exception(__('Invalid product ID', 'litecommerce'));
                }
                if ('variable' === $product->get_type()) {
                    throw new Exception(__('%s is a variable product  and cannot be added.', 'litecommerce'), $product->get_name());
                }
                $validation_error = new WP_Error();
                $validation_error = apply_filters('litecommerce_ajax_add_order_item_validation', $validation_error, $product, $order, $qty);

                if ($validation_error->get_error_code()) {
                    throw new Exception(sprintf(__('Error: %s', 'litecommerce'), $validation_error->get_error_message()));
                }

                $item_id = $order->add_product($product, $qty, array('order' => $order));
                $item = apply_filters('litecommerce_ajax_order_item', $order->get_item($item_id), $order, $product);
                $added_items[$item_id] = $item;
                $order_notes[$item_id] = $product->get_formatted_name();

                do_action('litecommerce_ajax_add_order_item_meta', $item_id, $item, $order);
            }

            $order->add_order_note(sprintf(__('Added line items: %s', 'litecommerce'), implode(', ', $order_notes)), false, true);

            do_action('litecommerce_ajax_order_items_added', $added_items, $order);

            $data = get_post_meta($order_id);

            ob_start();
            include __DIR__ . '/admin/meta-boxes/views/html-order-items.php';
            $items_html = ob_get_clean();

            ob_start();
            include __DIR__ . 'admin/meta-boxes/views/html-order-notes.php';
            $notes_html = ob_get_clean();

            return array(
                'html' => $items_html,
                'notes_html' => $notes_html
            );
        } catch (Exception $e) {
            throw $e;
        }
    }

    public static function add_order_fee()
    {
        check_ajax_referer('order-item', 'security');
        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        $response = array();
        try {
            $order_id = isset($_POST['order_id']) ? absint($_POST['post_id']) : 0;
            $order = lc_get_order($order_id);

            if (!$order) {
                throw new Exception(__('Invalid order', 'litecommerce'));
            }
            $amount = isset($_POST['amount']) ? lc_clean(wp_unslash($_POST['amount'])) : 0;
            $calculate_tax_args = array(
                'country' => isset($_POST['country']) ? lc_strtoupper(lc_clean(wp_unslash($_POST['country']))) : '',
                'state' => isset($_POST['state']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['state']))) : '',
                'postcode' => isset($_POST['postcode']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['postcode']))) : '',
                'city' => isset($_POST['city']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['city']))) : '',
            );

            if (strstr($amount, '%s')) {
                $order->calculate_totals(false);
                $formatted_amount = $amount;
                $percent = floatval(trim($amount, '%s'));
                $amount = $order->get_total() * ($percent / 100);

            } else {
                $amount = floatval($amount);
                $formatted_amount = lc_price($amount, array('currency' => $order->get_currency()));
            }

            $fee = new LC_Order_Item_Fee();
            $fee->set_amount($amount);
            $fee->set_total($amount);
            $fee->set_name(sprintf(__('%s feeeee', 'litecommerce'), lc_clean($formatted_amount)));

            $order->add_item($fee);
            $order->calculate_taxes($calculate_tax_args);
            $order->calculate_totals(false);
            $order->save();

            ob_start();
            include __DIR__ . '/admin/meta-boxes/views/html-order-items.php';
            $response['html'] = ob_get_clean();
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
        wp_send_json_success($response);
    }

    public static function add_order_shipping()
    {
        check_ajax_referer('order-item', 'security');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        $response = array();

        try {
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $order = lc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Invalid order', 'litecommerce'));
            }

            $order_taxes = $order->get_taxes();
            $shipping_methods = LC()->shipping() ? LC()->shipping()->load_shipping_methods() : array();

            $item = new LC_Order_Item_Shipping();
            $item->set_shipping_rate(new LC_Shipping_Rate());

            $item->set_order_id($order_id);
            $item_id = $item->save();

            ob_start();
            include __DIR__ . '/admin/meta-boxes/views/html-order-shipping.php';
            $response['html'] = ob_get_clean();


        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }

        wp_send_json_success($response);
    }

    public static function add_order_tax()
    {
        check_ajax_referer('order-item', 'security');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        $response = array();

        try {
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $order = wc_get_order($order_id);

            if (!$order) {
                throw new Exception(__('Invalid order', 'woocommerce'));
            }

            $rate_id = isset($_POST['rate_id']) ? absint($_POST['rate_id']) : '';

            if (!$rate_id) {
                throw new Exception(__('Invalid rate', 'woocommerce'));
            }

            $data = get_post_meta($order_id);

            $item = new LC_Order_Item_Tax();
            $item->set_rate($rate_id);
            $item->set_order_id($order_id);
            $item->save();

            ob_start();
            include __DIR__ . '/admin/meta-boxes/views/html-order-items.php';
            $response['html'] = ob_get_clean();
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
        wp_send_json_success($response);
    }

    public static function add_coupon_discount()
    {
        lc_get_container()->get(CouponsController::class)->add_coupon_discount_via_ajax();
    }

    public static function remove_order_coupon()
    {
        check_ajax_referer('order-item', 'security');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        $response = array();

        try {
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $order = wc_get_order($order_id);
            $calculate_tax_args = array(
                'country' => isset($_POST['country']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['country']))) : '',
                'state' => isset($_POST['state']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['state']))) : '',
                'postcode' => isset($_POST['postcode']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['postcode']))) : '',
                'city' => isset($_POST['city']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['city']))) : '',
            );

            if (!$order) {
                throw new Exception(__('Invalid order', 'woocommerce'));
            }

            $coupon = ArrayUtil::get_value_or_default($_POST, 'coupon');
            if (StringUtil::is_null_or_whitespace($coupon)) {
                throw new Exception(__('Invalid coupon', 'woocommerce'));
            }

            $code = wc_format_coupon_code(wp_unslash($coupon));
            if ($order->remove_coupon($code)) {
                // translators: %s coupon code.
                $order->add_order_note(esc_html(sprintf(__('Coupon removed: "%s".', 'woocommerce'), $code)), 0, true);
            }
            $order->calculate_taxes($calculate_tax_args);
            $order->calculate_totals(false);

            ob_start();
            include __DIR__ . '/admin/meta-boxes/views/html-order-items.php';
            $response['html'] = ob_get_clean();

            ob_start();
            $notes = wc_get_order_notes(array('order_id' => $order_id));
            include __DIR__ . '/admin/meta-boxes/views/html-order-notes.php';
            $response['notes_html'] = ob_get_clean();

        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
        wp_send_json_success($response);
    }

    public static function remove_order_item()
    {
        check_ajax_referer('order-item', 'security');

        if (!current_user_can('edit_shop_orders') || !isset($_POST['order_id'], $_POST['order_item_ids'])) {
            wp_die(-1);
        }

        $response = array();

        try {
            $order_id = absint($_POST['order_id']);
            $order = wc_get_order($order_id);

            if (!$order) {
                throw new Exception(__('Invalid order', 'woocommerce'));
            }

            if (!isset($_POST['order_item_ids'])) {
                throw new Exception(__('Invalid items', 'woocommerce'));
            }

            $order_item_ids = wp_unslash($_POST['order_item_ids']);
            $items = (!empty($_POST['items'])) ? wp_unslash($_POST['items']) : '';

            $calculate_tax_args = array(
                'country' => isset($_POST['country']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['country']))) : '',
                'state' => isset($_POST['state']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['state']))) : '',
                'postcode' => isset($_POST['postcode']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['postcode']))) : '',
                'city' => isset($_POST['city']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['city']))) : '',
            );

            if (is_numeric($order_item_ids)) {
                $order_item_ids = array($order_item_ids);
            }

            if (!empty($items)) {
                $save_items = array();
                parse_str($items, $save_items);
                wc_save_order_items($order->get_id(), $save_items);
            }

            if (!empty($order_item_ids)) {
                foreach ($order_item_ids as $item_id) {
                    $item_id = absint($item_id);
                    $item = $order->get_item($item_id);
                    if (!$item) {
                        continue;
                    }
                    if ($item->is_type('line_item')) {
                        $changed_stock = wc_maybe_adjust_line_item_product_stock($item, 0);
                        if ($changed_stock) {
                            $order->add_order_note(sprintf(__('Deleted %1$s and adjusted stock (%2$s)', 'woocommerce'), $item->get_name(), $changed_stock['from'] . '&rarr;' . $changed_stock['to']), false, true);
                        } else {
                            $order->add_order_note(sprintf(__('Deleted %s', 'woocommerce'), $item->get_name()), false, true);
                        }
                    }
                    wc_delete_order_item($item_id);
                }
            }

            $order = wc_get_order($order_id);
            $order->calculate_taxes($calculate_tax_args);
            $order->calculate_totals(false);

            do_action('litecommerce_ajax_order_items_removed', $item_id ?? 0, $item ?? false, $changed_stock ?? false, $order);

            ob_start();
            include __DIR__ . '/admin/meta-boxes/views/html-order-items.php';
            $items_html = ob_get_clean();

            ob_start();
            $notes = wc_get_order_notes(array('order_id' => $order_id));
            include __DIR__ . '/admin/meta-boxes/views/html-order-notes.php';
            $notes_html = ob_get_clean();

            wp_send_json_success(
                array(
                    'html' => $items_html,
                    'notes_html' => $notes_html,
                )
            );
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }

        wp_send_json_success($response);
    }

    public static function remove_order_tax()
    {
        check_ajax_referer('order-item', 'security');

        if (!current_user_can('edit_shop_orders') || !isset($_POST['order_id'], $_POST['rate_id'])) {
            wp_die(-1);
        }

        $response = array();


        try {
            $order_id = absint($_POST['order_id']);
            $rate_id = absint($_POST['rate_id']);

            $order = wc_get_order($order_id);
            if (!$order->is_editable()) {
                throw new Exception(__('Order not editable', 'woocommerce'));
            }

            wc_delete_order_item($rate_id);

            $order = wc_get_order($order_id);
            $order->calculate_totals(false);

            ob_start();
            include __DIR__ . '/admin/meta-boxes/views/html-order-items.php';
            $response['html'] = ob_get_clean();
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
        wp_send_json_success($response);
    }

    public static function calc_line_taxes()
    {
        wc_get_container()->get(TaxesController::class)->calc_line_taxes_via_ajax();
    }

    public static function save_order_items()
    {
        check_ajax_referer('order-item', 'security');
        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        if (isset($_POST['order_id'], $_POST['items'])) {
            $order_id = absint($_POST['order_id']);
            $items = array();
            parse_str(wp_unslash($_POST['items']), $items);

            wc_save_order_items($order_id, $items);

            $order = wc_get_order($order_id);

            ob_start();
            include __DIR__ . '/admin/meta-boxes/views/html-order-items.php';
            $items_html = ob_get_clean();

            ob_start();
            $notes = wc_get_order_notes(array('order_id' => $order_id));
            include __DIR__ . '/admin/meta-boxes/views/html-order-notes.php';
            $notes_html = ob_get_clean();

            wp_send_json_success(
                array(
                    'html' => $items_html,
                    'notes_html' => $notes_html,
                )
            );
        }
        wp_die();
    }

    public static function load_order_items()
    {
        check_ajax_referer('order-item', 'security');

        if (!current_user_can('edit_shop_orders') || !isset($_POST['order_id'])) {
            wp_die(-1);
        }

        // Return HTML items.
        $order_id = absint($_POST['order_id']);
        $order = wc_get_order($order_id);
        include __DIR__ . '/admin/meta-boxes/views/html-order-items.php';
        wp_die();
    }

    public static function add_order_note()
    {
        check_ajax_referer('add-order-note', 'security');

        if (!current_user_can('edit_shop_orders') || !isset($_POST['post_id'], $_POST['note'], $_POST['note_type'])) {
            wp_die(-1);
        }

        $post_id = absint($_POST['post_id']);
        $note = wp_kses_post(trim(wp_unslash($_POST['note'])));
        $note_type = wc_clean(wp_unslash($_POST['note_type']));

        $is_customer_note = ('customer' === $note_type) ? 1 : 0;

        if ($post_id > 0) {
            $order = lc_get_order($post_id);
            $comment_id = $order->add_order_note($note, $is_customer_note, true);
            $note = lc_get_order_note($comment_id);

            $note_classes = array('note');
            $note_classes[] = $is_customer_note ? 'customer-note' : '';
            $note_classes = apply_filters('litecommerce_order)_note_class', array_filter($note_classes), $note);
            ?>
            <li rel="<?php echo absint($note->id); ?>" class="<?php echo esc_attr(implode(' ', $note_classes)); ?>">
                <div class="note_content">
                    <?php echo wp_kses_post(wpautop(wptexturize(make_clickable($note->content)))); ?>
                </div>
                <p class="meta">
                    <abbr class="exact-date" title="<?php echo esc_attr($note->date_created->date('y-m-d h:i:s')); ?>">
                        <?php printf(esc_html__('added on %1$s at %2$s', 'litecommerce'), esc_html($note->date_created->date_i18n(lc_date_format())), esc_html($note->date_created->date_i18n(lc_time_format()))); ?>
                    </abbr>
                    <?php
                    if ('system' !== $note->added_by):
                        printf(' ' . esc_html__('by %s', 'litecommerce'), esc_html($note->added_by));
                    endif;
                    ?>
                    <a href="#" class="delete_note" role="button">
                        <?php esc_html_e('Delete Note', 'litecommerce') ?>
                    </a>
                </p>
            </li>
            <?php
        }
        wp_die();
    }

    public static function delete_order_note()
    {
        check_ajax_referer('delete-order-note', 'security');

        if (!current_user_can('edit_shop_orders') || !isset($_POST['note_id'])) {
            wp_die(-1);
        }

        $note_id = (int) $_POST['note_id'];

        if ($note_id > 0) {
            lc_delete_order_note($note_id);
        }
        wp_die();
    }

    public static function json_search_products($term = '', $include_variations = false)
    {
        check_ajax_referer('search-products', 'security');

        if (empty($term) && isset($_GET['term'])) {
            $term = (string) lc_clean(wp_unslash($_GET['term']));
        }

        if (empty($term)) {
            wp_die();
        }

        if (!empty($_GET['limit'])) {
            $limit = absint($_GET['limit']);
        } else {
            $limit = absint(apply_filters('litecommerce_json_search_limit', 30));
        }

        $include_ids = !empty($_GET['include']) ? array_map('absint', (array) wp_unslash($_GET['include'])) : array();

        $exclude_ids = !empty($_GET['exclude']) ? array_map('absint', (array) wp_unslash($_GET['exclude'])) : array();

        $exclude_types = array();
        if (!empty($_GET['exclude_type'])) {
            $exclude_types = wp_unslash($_GET['exclude_type']);
            if (!is_array($exclude_types)) {
                $exclude_types = explode(',', $exclude_types);
            }
            foreach ($exclude_types as &$exclude_type) {
                $exclude_type = strtolower(trim($exclude_type));
            }
            $exclude_types = array_intersect(
                array_merge(array('variation'), array_keys(lc_get_products_types())),
                $exclude_types
            );
        }

        $data_store = LC_Data_Store::load('product');
        $ids = $data_store->search_products($term, '', (bool) $include_variations, false, $limit, $include_ids, $exclude_ids);

        $products = array();
        foreach ($ids as $id) {
            $product_object = lc_get_product($id);

            if (!lc_products_array_filter_readable($product_object)) {
                continue;
            }

            $formatted_name = $product_object->get_formatted_name();
            $managing_stock = $product_object->managing_stock();

            if (in_array($product_object->get_type(), $exclude_types, true)) {
                continue;
            }

            if ($managing_stock && !empty($_GET['display_stock'])) {
                $stock_amount = $product_object->get_stock_quantity();
                $formatted_name .= ' &ndash; ' . sprintf(__('Stock: %d', 'litecommerce'), lc_format_stock_quantity_for_display($stock_amount, $product_object));
            }

            $products[$product_object->get_id()] = rawurldecode(wp_strip_all_tags($formatted_name));
        }
        wp_send_json(apply_filters('litecommerce_json_search_found_products', $products));
    }

    public static function json_search_products_and_variations()
    {
        self::json_search_products('', true);
    }

    public static function json_search_downloadable_products_and_variations()
    {
        check_ajax_referer('search-products', 'security');

        if (!empty($_GET['limit'])) {
            $limit = absint($_GET['limit']);
        } else {
            $limit = absint(apply_filters('woocommerce_json_search_limit', 30));
        }

        $include_ids = !empty($_GET['include']) ? array_map('absint', (array) wp_unslash($_GET['include'])) : array();
        $exclude_ids = !empty($_GET['exclude']) ? array_map('absint', (array) wp_unslash($_GET['exclude'])) : array();

        $term = isset($_GET['term']) ? (string) wc_clean(wp_unslash($_GET['term'])) : '';
        $data_store = WC_Data_Store::load('product');
        $ids = $data_store->search_products($term, 'downloadable', true, false, $limit);

        $product_objects = array_filter(array_map('wc_get_product', $ids), 'wc_products_array_filter_readable');
        $products = array();

        foreach ($product_objects as $product_object) {
            $products[$product_object->get_id()] = rawurldecode(wp_strip_all_tags($product_object->get_formatted_name()));
        }

        wp_send_json($products);
    }

    public static function json_search_customers()
    {
        ob_start();

        check_ajax_referer('search-customer', 'security');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        $term = isset($_GET['term']) ? (string) lc_clean(wp_unslash($_GET['term'])) : '';
        $limit = 0;
        if (empty($term)) {
            wp_die();
        }

        $ids = array();

        if (is_numeric($term)) {
            $customer = new LC_Customer(intval($term));
            if (0 !== $customer->get_id()) {
                $ids = array($customer->get_id());
            }
        }

        if (empty($ids)) {
            $data_store = LC_Data_Store::load('customer');
            if (3 > strlen($term)) {
                $limit = 20;
            }

            $ids = $data_store->search_customers($term, $limit);
        }

        $found_customers = array();

        if (empty($_GET['exclude'])) {
            $ids = array_diff($ids, array_map('absint', (array) wp_unslash($_GET['exclude'])));
        }

        foreach ($ids as $id) {
            $customer = new LC_Customer($id);
            $found_customers[$id] = sprintf(
                esc_html__('%1$s (#%2$s &ndash; %3$s)', 'litecommerce'),
                $customer->get_first_name(),
                $customer->get_last_name(),
                $customer->get_id(),
                $customer->get_email()
            );
        }

        wp_send_json(apply_filters('litecommerce_json_search_found_customers', $found_customers));
    }



}