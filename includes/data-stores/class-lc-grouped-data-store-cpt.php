<?php

/**
 * Class LC_Product_Grouped_Data_Store_CPT file.
 * 
 * @package Litecommerce\DataStores
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Product_Grouped_Data_Store_CPT extends LC_Product_Data_Store_CPT implements LC_Object_Data_Store_Interface
{
    protected function update_post_meta(&$product, $force = false)
    {
        $meta_key_to_props = array(
            '_children' => 'children'
        );
        $props_to_update = $force ? $meta_key_to_props : $this->get_props_to_update($product, $meta_key_to_props);

        foreach ($props_to_update as $meta_key => $prop) {
            $value = $product->{"get_$prop"}('edit');
            $updated = update_post_meta($product->get_id(), $meta_key, $value);
            if ($updated) {
                $this->updated_props[] = $prop;
            }
        }

        // call parent
        parent::update_post_meta($product, $force);
    }

    protected function handle_updated_props(&$product)
    {
        if (in_array('children', $this->updated_props, true)) {
            $this->update_prices_from_children($product);
        }
        parent::handle_updated_props($product);
    }

    public function sync_price(&$product)
    {
        $this->update_prices_from_children($product);
    }

    protected function update_prices_from_children(&$product)
    {
        $child_prices = array();
        foreach ($product->get_children('edit') as $child_id) {
            $child = wc_get_product($child_id);
            if ($child) {
                $child_prices[] = $child->get_price('edit');
            }
        }
        $child_prices = array_filter($child_prices);
        delete_post_meta($product->get_id(), '_price');
        delete_post_meta($product->get_id(), '_sale_price');
        delete_post_meta($product->get_id(), '_regular_price');

        if (!empty($child_prices)) {
            add_post_meta($product->get_id(), '_price', min($child_prices));
            add_post_meta($product->get_id(), '_price', max($child_prices));
        }

        $this->update_lookup_table($product->get_id(), 'lc_product_meta_lookup');

        do_action('litecommerce_updated_product_price', $product->get_id());
    }
}