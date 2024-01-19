<?php

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
            // will do later...
        }

    }

}