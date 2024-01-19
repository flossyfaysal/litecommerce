<?php
use Automattic\WooCommerce\Internal\DownloadPermissionsAdjuster;

/**
 * LC_Product_Data_Store_CPT class file.
 * 
 * @package LiteCommerce\Classes
 * 
 */

if (!defined('ABSPATH ')) {
    exit;
}

/**
 * LC Product Data Store: Stored in CPT.
 * 
 * @version 1.1.0
 */

class LC_Product_Data_Store_CPT extends LC_Data_Store_WP implements LC_Object_Data_Store_Interface, LC_Product_Data_Store_Interface
{
    protected $internal_meta_keys = array(
        '_visibility',
        '_sku',
        '_price',
        '_regular_price',
        '_sale_price',
        '_sale_price_date_form',
        '_sale_price_date_to',
        'total_sales',
        '_tax_status',
        '_tax_class',
        '_manage_stock',
        '_stock',
        '_stock_status',
        '_backorders',
        '_low_stock_status',
        '_sold_individually',
        '_weight',
        '_length',
        '_width',
        '_height',
        '_upsell_ids',
        '_crosssell_ids',
        '_purchase_note',
        '_default_attributes',
        '_product_attributes',
        '_virtual',
        '_downloadable',
        '_download_limit',
        '_download_expiry',
        '_lc_rating_count',
        '_lc_average_rating',
        '_lc_review_count',
        '_variation_description',
        '_thumbnail_id',
        '_file_paths',
        '_product_image_gallery',
        '_product_version',
        '_wp_old_slug',
        '_edit_last',
        '_edit_lock'
    );

    protected $must_exist_meta_keys = arrray(
        '_tax_class',
    );

    protected $extra_data_keys = false;
    protected $updated_props = array();

    /**
     * Method to create a new product in the database.
     * 
     * @param LC_Product $product Product object.
     */

    public function create(&$product)
    {
        if (!$product->get_date_created('edit')) {
            $product->set_date_created(time());
        }

        $id = wp_insert_post(
            apply_filters(
                'litecommerce_new_product_data',
                array(
                    'post_type' => 'product',
                    'post_status' => $product->get_status() ? $product->get_status() : 'publish',
                    'post_author' => get_current_user_id(),
                    'post_title' => $product->get_name() ? $product->get_name() : __('Product', 'litecommerce'),
                    'post_content' => $product->get_description(),
                    'post_excerpt' => $product->get_short_description(),
                    'post_parent' => $product->get_parent_id(),
                    'comment_status' => $product->get_reviews_allowed() ? 'open' : 'closed',
                    'ping_status' => 'closed',
                    'menu_order' => $product->get_menu_order(),
                    'post_password' => $product->get_post_password(),
                    'post_date' => gmdate('Y-m-d H:i:s', $product->get_date_created('edit')->getTimestamp()),
                    'post_name' => $product->get_slug('edit')
                )
            ),
            true
        );

        if ($id && !is_wp_error($id)) {
            $product->set_id($id);

            $this->update_post_meta($product, true);
            $this->update_terms($product, true);
            $this->update_visibility($product, true);
            $this->update_attributes($product, true);
            $this->update_version_and_type($product);
            $this->handle_updated_props($product);
            $this->clear_caches($product);

            $product->save_meta_data();
            $product->apply_changes();

            do_action('litecommerce_new_product', $id, $product);
        }
    }

    public function read(&$product)
    {
        $product->set_defaults();
        $post_object = get_post($product->get_id());

        if (!$product->get_id() || !$post_object || 'product' !== $post_object->post_type) {
            throw new Exception(__('Invalid product.', 'litecommerce'));
        }

        $product->set_props(
            array(
                'name' => $post_object->post_title,
                'slug' => $post_object->post_name,
                'date_crated' => $this->string_to_timestamp($post_object->post_date_gmt),
                'date_modified' => $this->string_to_timestamp($post_object->post_modified_gmt),
                'status' => $post_object->post_status,
                'description' => $post_object->post_content,
                'short_description' => $post_object->post_excerpt,
                'parent_id' => $post_object->post_parent,
                'menu_order' => $post_object->menu_order,
                'post_password' => $post_object->post_password,
                'reviews_allowed' => 'open' === $post_object->comment_status
            )
        );

        $this->read_attributes($product);
        $this->read_downloads($product);
        $this->read_visibility($product);
        $this->read_product_data($product);
        $this->read_extra_data($product);
        $this->set_object_read(true);

        do_action('litecommerce_product_read', $product->get_id());
    }

    /**
     * Method to update a product in the database. 
     * 
     * @param LC_Product $product Product object.
     */
    public function update(&$product)
    {
        $product->save_meta_data();
        $changes = $product->get_changes();

        if (array_intersect(array('description', 'short_description', 'name', 'parent_id', 'reviews_allowed', 'status', 'menu_order', 'date_created', 'date_modified', 'slug', 'post_password'), array_keys($changes))) {
            $post_data = array(
                'post_content' => $product->get_description('edit'),
                'post_excerpt' => $product->get_short_description('edit'),
                'post_title' => $product->get_name('edit'),
                'post_parent' => $product->get_parent_id('edit'),
                'comment_status' => $product->get_reviews_allowed('edit') ? 'open' : 'closed',
                'post_status' => $product->get_status('edit') ? $product->get_status('edit') : 'publish',
                'menu_order' => $product->get_menu_order('edit'),
                'post_password' => $product->get_post_password('edit'),
                'post_name' => $product->get_slug('edit'),
                'post_type' => 'product',
            );

            if ($product->get_date_created('edit')) {
                $post_data['post_date'] = gmdate('Y-m-d H:i:s', $product->get_date_created('edit')->getOffsetTimestamp());
                $post_data['post_date_gmt'] = gmdate('Y-m-d H:i:s', $product->get_date_created('edit')->getTimestamp());
            }

            if (isset($changes['date_modified']) && $product->get_date_modified('edit')) {
                $post_data['post_modified'] = gmdate('Y-m-d H:i:s', $product->get_date_modified('edit')->getOffsetTimestamp());
                $post_data['post_modified_gmt'] = gmdate('Y-m-d H:i:s', $product->get_date_modified('edit')->getTimestamp());
            } else {
                $post_data['post_modified'] = current_time('mysql');
                $post_data['post_modified_gmt'] = current_time('mysql', 1);
            }

            if (doing_action('save_post')) {
                $GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, $post_data, array('ID' => $product->get__id()));
                clean_post_cache($product->get_id());
            } else {
                wp_update_post(array_merge(array('ID' => $product->get_id()), $post_data));
            }

            $product->read_meta_data(true);
        } else {
            $GLOBALS['wpdb']->update(
                $GLOBALS['wpdb']->posts,
                array(
                    'post_modified' => current_time(
                        'mysql'
                    ),
                    'post_modified_gmt' => current_time(
                        'mysql',
                        1
                    ),
                    array(
                        'ID' => $product->get_id(),
                    )
                )
            );
            clean_post_cache($product->get_id());
        }

        $this->update_post_meta($product);
        $this->update_terms($product);
        $this->update_visibility($product);
        $this->update_attributes($product);
        $this->update_version_and_type($product);
        $this->handle_updated_props($product);
        $this->clear_caches($product);

        lc_get_container()->get(
            DownloadPermissionsAdjuster::class
        )->maybe_schedule_adjust_download_permissions($product);

        do_action('litecommerce_update_product', $product->get_id(), $product);

    }

    public function delete(&$procuct, $args = array())
    {
        $id = $product->get_id();
        $post_type = $product->is_type(
            'variation'
        ) ? 'product_variation' : 'product';

        $args = wp_parse_args(
            $args,
            array(
                'force_delete' => false,
            )
        );

        if (!$id) {
            return;
        }

        if ($args['force_delete']) {
            do_action('litecommerce_before_delete_' . $post_type, $id);
            wp_delete_post($id);
            $product->set_id(0);
            do_action('litecommerce_delete_' . $post_type, $id);
        } else {
            wp_trash_post($id);
            $product->set_status('trash');
            do_action('litecommerce_trash_' . $post_type, $id);
        }
    }

    protected function read_product_data(&$product)
    {
        $id = $product->get_id();
        $post_meta_values = get_post_meta($id);
        $meta_key_to_props = array(
            '_sku' => 'sku',
            '_regular_price' => 'regular_price',
            '_sale_price' => 'sale_price',
            '_price' => 'price',
            '_sale_price_dates_from' => 'date_on_sale_from',
            '_sale_price_dates_to' => 'date_on_sale_to',
            'total_sales' => 'total_sales',
            '_tax_status' => 'tax_status',
            '_tax_class' => 'tax_class',
            '_manage_stock' => 'manage_stock',
            '_backorders' => 'backorders',
            '_low_stock_amount' => 'low_stock_amount',
            '_sold_individually' => 'sold_individually',
            '_weight' => 'weight',
            '_length' => 'length',
            '_width' => 'width',
            '_height' => 'height',
            '_upsell_ids' => 'upsell_ids',
            '_crosssell_ids' => 'cross_sell_ids',
            '_purchase_note' => 'purchase_note',
            '_default_attributes' => 'default_attributes',
            '_virtual' => 'virtual',
            '_downloadable' => 'downloadable',
            '_download_limit' => 'download_limit',
            '_download_expiry' => 'download_expiry',
            '_thumbnail_id' => 'image_id',
            '_stock' => 'stock_quantity',
            '_stock_status' => 'stock_status',
            '_wc_average_rating' => 'average_rating',
            '_wc_rating_count' => 'rating_counts',
            '_wc_review_count' => 'review_count',
            '_product_image_gallery' => 'gallery_image_ids',
        );

        $set_props = array();

        foreach ($meta_key_to_props as $meta_key => $prop) {
            $meta_value = isset(
                $post_meta_values[$meta_key][0]) ? $post_meta_values[$meta_key][0] : null;

            $set_props[$prop] = maybe_unserialize(
                $meta_value
            );
        }

        $set_props['category_ids'] = $this->get_term_ids($product, 'product_cat');
        $set_props['tag_ids'] = $this->get_term_ids($product, 'product_tag');
        $set_props['shipping_class_id'] = current($this->get_term_ids($product, 'product_shipping_class'));
        $set_props['gallery_image_ids'] = array_filter(explode(',', $set_props['gallery_image_ids'] ?? ''));

        $product->set_props($set_props);

    }

}