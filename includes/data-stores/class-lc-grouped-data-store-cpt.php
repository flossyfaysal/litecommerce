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
}