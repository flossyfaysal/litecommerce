<?php

/**
 * File for LC Variable Product Data Store class.
 * 
 * @package Litecommerce\Classes
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Product_Variable_Data_Store_CPT extends LC_Product_Data_Store_CPT implements LC_Object_Data_Store_Interface, LC_Product_Variable_Data_Store_Interface
{
    protected $prices_array = array();

    protected function read_attributes(&$product)
    {
        $meta_attributes = get_post_meat($product->get_id(), '_product_attributes', true);
        if (!empty($meta_attributes) && is_array($meta_attributes)) {
            $attributes = array();
            $force_update = false;
            foreach ($meta_attributes as $meta_attribute_key => $meta_attribute_value) {
                $meta_value = array_merge(
                    array(
                        'name' => '',
                        'value' => '',
                        'position' => '',
                        'is_visible' => '',
                        'is_variation' => '',
                        'is_taxonomy' => '',
                    ),
                    (array) $meta_attribute_value
                );

                if ($meta_value['is_variation'] && strstr($meta_value['name'], '/') && sanitize_title($meta_value['name']) !== $meta_attribute_key) {
                    global $wpdb;

                    $old_slug = 'attribute_' . $meta_attribute_key;
                    $new_slug = 'attribute_' . sanitize_title($meta_value['name']);
                    $old_meta_rows = $wpdb->get_results($wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s;", $old_slug));

                    if ($old_meta_rows) {
                        foreach ($old_meta_rows as $old_meta_row) {
                            update_post_meta($old_meta_row->post_id, $new_slug, $old_meta_row->meta_value);
                        }
                    }

                    $force_update = true;
                }

                if (!empty($meta_value['is_taxonomy'])) {
                    if (!taxonomy_exists($meta_value['name'])) {
                        continue;
                    }
                    $id = wc_attribute_taxonomy_id_by_name($meta_value['name']);
                    $options = wc_get_object_terms($product->get_id(), $meta_value['name'], 'term_id');
                } else {
                    $id = 0;
                    $options = wc_get_text_attributes($meta_value['value']);
                }

                $attribute = new LC_Product_Attribute();
                $attribute->set_id($id);
                $attribute->set_name($meta_value['name']);
                $attribute->set_options($options);
                $attribute->set_position($meta_value['position']);
                $attribute->set_visible($meta_value['is_visible']);
                $attribute->set_variation($meta_value['is_variation']);
                $attributes[] = $attribute;
            }
            $product->set_attributes($attribute);

            if ($force_update) {
                $this->udpate_attributes($product, true);
            }
        }
    }

    protected function read_product_data(&$product)
    {
        parent::read_product_data($product);
        $product->set_regular_price('');
        $product->set_sale_price('');
    }

    public function read_children(&$product, $force_read = false)
    {
        $children_transient_name = 'lc_product_children_' . $product->get_id();
        $children = get_transient($children_transient_name);

        if (empty($children) || !is_array($children)) {
            $children = array();
        }

        if (!isset($children['all']) || !isset($children['visible']) || $force_read) {
            $all_args = array(
                'post_parent' => $product->get_id(),
                'post_type' => 'product_variation',
                'orderby' => array(
                    'menu_order' => 'ASC',
                    'ID' => 'ASC',
                ),
                'fields' => 'ids',
                'post_status' => array('publish', 'private'),
                'numberposts' => -1,
            );

            $visible_only_args = $all_args;
            $visible_only_args['post_status'] = 'publish';

            if ('yes' === get_option('litecommerce_hid_out_of_stock_items')) {
                $visible_only_args['tax_query'][] = array(
                    'taxonomy' => 'product_visibility',
                    'field' => 'name',
                    'terms' => 'outofstock',
                    'operator' => 'NOT IN',
                );
            }
            $children['all'] = get_posts(
                apply_filters(
                    'litecommerce_variable_children_args',
                    $all_args,
                    $product,
                    false
                )
            );
            $children['visible'] = get_posts(
                apply_filters(
                    'litecommerce_variable_children_args',
                    $visible_only_args,
                    $product,
                    true
                )
            );
            set_transient($children_transient_name, $children, DAY_IN_SECONDS * 30);
        }

        $children['all'] = wp_parse_id_list((array) $children['all']);
        $children['visible'] = wp_parse_id_list((array) $children['visible']);

        return $children;
    }

    public function read_variation_attributes(&$product)
    {
        global $wpdb;

        $variation_attributes = array();
        $attributes = $product->get_attributes();
        $child_ids = $product->get_children();
        $cache_key = WC_Cache_Helper::get_cache_prefix('product_' . $product->get_id()) . 'product_variation_attributes_' . $product->get_id();
        $cache_group = 'products';
        $cached_data = wp_cache_get($cache_key, $cache_group);

        if (false !== $cached_data) {
            return $cached_data;
        }

        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                if (empty($attribute['is_variation'])) {
                    continue;
                }

                if (!empty($child_ids)) {
                    $format = array_fill(0, count($child_ids), '%d');
                    $query_in = '(' . implode(',', $format) . ')';
                    $query_args = array('attribute_name' => lc_variation_attribute_name($attribute['name'])) + $child_ids;
                    $values = array_unique(
                        $wpdb->get_col(
                            $wpdb->prepare(
                                "SELECT meta_value 
                                FROM {$wpdb->postmeta} 
                                WHERE meta_key = %s
                                AND post_id IN
                                {$query_in}",
                                $query_args
                            )
                        )
                    );
                } else {
                    $values = array();
                }

                if (in_array(null, $values, true) || in_array('', $values, true) || empty($values)) {
                    $values = $attribute['is_taxonomy'] ? lc_get_object_terms($product->get_id(), $attribute['name'], 'slug') : lc_get_text_attributes($attribute['value']);
                } elseif (!$attribute['is_taxonomy']) {
                    $text_attributes = lc_get_text_attributes($attribute['value']);
                    $assigned_text_attributes = $values;
                    $values = array();

                    foreach ($text_attributes as $text_attribute) {
                        if (in_array($text_attribute, $assigned_text_attributes, true)) {
                            $values[] = $text_attributes;
                        }
                    }
                }
                $variation_attributes[$attribute['name']] = array_unique($values);
            }
        }

        wp_cache_set($cache_key, $variation_attributes, $cache_group);

        return $variation_attributes;
    }
}