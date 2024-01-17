<?php

/**
 * Product Data Store Interface
 * 
 * @version 1.1.0
 * @package LiteCommerce\Interface
 */

/**
 * LC Product Data Store Interface
 * Functions that must be fefined by product store classes. 
 * 
 * @version 1.1.0
 */
interface LC_Product_Data_Store_Interface
{

    /**
     * Returns an array of on sale products, as an array of objects with an ID and parent_id present. Example: $return[0]->id, $return[0]->parent_id
     * 
     * @return array
     */
    public function get_on_sale_products();

    /**
     * Returns a list of product IDs ( id as ke => parent as value) that are featured. Uses get_posts instead of lc_get_products since we want some extra meta queries and ALL products (posts_per_page = -1). 
     * 
     * @return array
     */
    public function get_featured_product_ids();

    /**
     * Check if product sku is found for any other product IDs.
     * 
     * @param int $product_id Product ID.
     * @param string $sku SKU.
     * @return bool
     */
    public function is_existing_sku($product_id, $sku);

    /**
     * Return product ID based on SKU 
     * 
     * @param string $sku SKU.
     * @return int
     */
    public function get_product_id_by_sku($sku);

    public function get_starting_sales();
    public function get_ending_sales();
    public function find_matching_product_variation($product, $match_attributes = array());
    public function sort_all_product_variations($parent_id);
    public function get_related_products($cats_array, $tags_array, $exclude_ids, $limit, $product_id);
    public function update_product_stock($product_id_with_stock, $stock_quantity = null, $operation = 'set');
    public function update_product_sales($product_id, $quantity = null, $operation = 'set');
    public function get_shipping_class_id_by_slug($slug);

    /**
     * Returns an array of products.
     * 
     * @param array $arg @see lc_get_products
     * @return array
     */
    public function get_products($args = array());

    /**
     * Get product type based on product ID.
     * 
     * @param int $product_id Product ID.
     * @return bool|string
     */
    public function get_product_type($product_id);



}