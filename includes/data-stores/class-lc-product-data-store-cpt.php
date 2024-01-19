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

    public function read_stock_quantity(&$product, $new_stock)
    {
        $object_read = $product->get_object_read();
        $product->set_object_read(false);
        $product->set_stock_quantity(
            is_null(
                $new_stock
            ) ? get_post_meta($product->get_id(), '_stock', true) : $new_stock
        );
        $product->set_object_read($object_read);
    }

    protected function read_visibility(&$product)
    {
        $terms = get_the_terms(
            $product->get_id(),
            'product_visibility'
        );
        $term_names = is_array($terms) ? wp_list_pluck(
            $terms,
            'name'
        ) : array();
        $featured = in_array('featured', $term_names, true);
        $exclude_search = in_array('exclude-from-search', $term_names, true);
        $exclude_catalog = in_array('exclude-from-catalog', $term_names, true);

        if ($exclude_search && $exclude_catalog) {
            $catalog_visibility = 'hidden';
        } elseif ($exclude_search) {
            $catalog_visibility = 'catalog';
        } elseif ($exclude_catalog) {
            $catalog_visibility = 'search';
        } else {
            $catalog_visibility = 'visible';
        }

        $product->set_props(
            array(
                'featured' => $featured,
                'catalog_visibility' => $catalog_visibility
            )
        );
    }

    /**
     * Read attributes from post meta.
     * 
     * @param LC_Product $product Product object.
     */

    protected function read_attributes(&$product)
    {
        $meta_attributes = get_post_meta($product->get_id(), '_product_attributes', true);

        if (!empty($meta_attributes) && is_array($meta_attributes)) {
            $attributes = array();
            foreach ($meta_attributes as $meta_attribute_key => $meta_attribute_value) {
                $meta_value = array_merge(
                    array(
                        'name' => '',
                        'value' => '',
                        'position' => 0,
                        'is_visible' => 0,
                        'is_variation' => 0,
                        'is_taxonomy' => 0,
                    ),
                    (array) $meta_attribute_value
                );

                if (!empty($meta_value['is_taxonomy'])) {
                    if (!taxonomy_exists($meta_value['name'])) {
                        continue;
                    }
                    $id = lc_attribute_taxonomy_id_by_name(
                        $meta_value['name']
                    );
                    $options = lc_get_object_terms(
                        $product->get_id(),
                        $meta_value['name'],
                        'term_id'
                    );
                } else {
                    $id = 0;
                    $options = lc_get_text_attributes(
                        $meta_value['name']
                    );
                }

                $attributes = new LC_Product_Attributes();
                $attributes->set_id($id);
                $attributes->set_name($meta_value['name']);
                $attributes->set_options($options);
                $attributes->position($meta_value['position']);
                $attributes->set_variation($meta_value['is_variation']);
                $attributes->set_visible($meta_value['is_variation']);
                $attributes[] = $attributes;
            }
            $product->set_attributes($attributes);
        }
    }

    protected function read_downloads(&$product)
    {
        $meta_values = array_filter((array) get_post_meta($product->get_id(), '_downloadable_files', true));
        if ($meta_values) {
            foreach ($meta_values as $key => $value) {
                if (!isset($value['name'], $value['file'])) {
                    continue;
                }
                $download = new LC_Product_Download();
                $download->set_id($key);
                $download->set_name($value['name'] ? $value['name'] : lc_get_filename_from_url($value['file']));
                $download->set_file(apply_filters('litecommerce_file_download_path', $value['file'], $product, $key));
                $downloads[] = $download;
            }
            $product->set_downloads($download);
        }
    }

    protected function update_post_meta(&$product, $force = false)
    {
        $meta_key_to_props = array(
            '_sku' => 'sku',
            '_regular_price' => 'regular_price',
            '_sale_price' => 'sale_price',
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
            '_product_image_gallery' => 'gallery_image_ids',
            '_download_limit' => 'download_limit',
            '_download_expiry' => 'download_expiry',
            '_thumbnail_id' => 'image_id',
            '_stock' => 'stock_quantity',
            '_stock_status' => 'stock_status',
            '_wc_average_rating' => 'average_rating',
            '_wc_rating_count' => 'rating_counts',
            '_wc_review_count' => 'review_count',
        );

        $extra_data_keys = $product->get_extra_data_keys();

        foreach ($extra_data_keys as $key) {
            $meta_key_to_props['_' . $key] = $key;
        }

        $props_to_update = $force ? $meta_key_to_props : $this->get_props_to_update($product, $meta_key_to_props);

        foreach ($props_to_update as $meta_key => $prop) {
            $value = $product->{"get_$prop"}('edit');
            $value = is_string($value) ? wp_slash($value) : $value;
            switch ($prop) {
                case 'virtual':
                case 'downloadable':
                case 'manage_stock':
                case 'sold_individually':
                    $value = lc_bool_to_string($value);
                    break;
                case 'gallery_image_ids':
                    $value = implode(',', $value);
                    break;
                case 'date_on_sale_from':
                case 'date_on_sale_to':
                    $value = $value ? $value->getTimestamp() : '';
                    break;
                case 'stock_quantity':
                    // Fire actions to let 3rd parties know the stock is about to be changed.
                    if ($product->is_type('variation')) {
                        do_action('woocommerce_variation_before_set_stock', $product);
                    } else {
                        do_action('woocommerce_product_before_set_stock', $product);
                    }
                    break;
            }
            $updated = $this->update_or_delete_post_meta($product, $meta_key, $value);

            if ($updated) {
                $this->updated_props[] = $prop;
            }
        }

        if (!$this->extra_data_saved) {
            foreach ($extra_data_keys as $key) {
                $meta_key = '_' . $key;
                $function = 'get_' . $key;
                if (!array_key_exists($meta_key, $props_to_update)) {
                    continue;
                }

                if (is_callable(array($product, $function))) {
                    $value = $product->{$function}('edit');
                    $value = is_string($value) ? wp_slash($value) : $value;
                    $updated = $this->update_or_delete_post_meta($product, $meta_key, $value);

                    if ($updated) {
                        $this->updated_props[] = $key;
                    }
                }
            }
        }

        if ($this->update_downloads($product, $force)) {
            $this->updated_props[] = 'downloads';
        }
    }

    protected function handle_updated_props(&$product)
    {
        $price_is_synced = $product->is_type(
            array(
                'variable',
                'grouped'
            )
        );
        if (!$price_is_synced) {
            if (in_array('regular_price', $this->updated_props, true) || in_array('sale_price', $this->updated_props, true)) {
                if ($product->get_sale_price('edit') >= $product->get_regular_price('edit')) {
                    update_post_meta($product->get_id(), '_sale_price', '');
                    $product->set_sale_price('');
                }
            }

            if (in_array('date_on_sale_from', $this->updated_props, true) || in_array('date_on_sale_to', $this->updated_props, true) || in_array('regular_price', $this->updated_props, true) || in_array('sale_price', $this->updated_props, true)) {
                if ($product->is_on_sale('edit')) {
                    update_post_meta($product->get_id(), '_price', $product->get_sale_price('edit'));
                    $product->set_price($product->get_sale_price('edit'));
                } else {
                    update_post_meta($product->get_id(), '_price', $product->get_regular_price('edit'));
                    $product->set_price($product->get_regular_price('edit'));
                }
            }
        }

        if (in_array('stock_quantity', $this->updated_props, true)) {
            if ($product->is_type('variation')) {
                do_action('litecommerce_variation_set_stock', $product);
            } else {
                do_action('litecommerce_product_set_stock', $product);
            }
        }

        if (in_array('stock_status', $this->updated_props, true)) {
            if ($product->is_type('variation')) {
                do_action(
                    'litecommerce_variation_set_stock_status',
                    $product->get_id(),
                    $product->get_stock_status(),
                    $product
                );
            } else {
                do_action(
                    'litecommerce_product_set_stock_status',
                    $product->get_id(),
                    $product->get_stock_status(),
                    $product
                );
            }
        }

        if (array_intersect($this->updated_props, array('sku', 'regular_price', 'sale_price', 'date_on_sale_from', 'date_on_sale_to', 'total_sales', 'average_rating', 'stock_quantity', 'stock_status', 'manage_stock', 'downloadable', 'virtual', 'tax_status', 'tax_class'))) {
            $this->update_lookup_table($product->get_id(), 'lc_product_meta_lookup');
        }

        do_action(
            'litecommerce_product_object_updated_props',
            $product,
            $this->updated_props
        );

        $this->updated_props = array();
    }

    protected function update_terms(&$procuct, $force = false)
    {

    }


}