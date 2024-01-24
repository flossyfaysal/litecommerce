<?php

/**
 * LiteCommerce product base class. 
 * 
 * @package LiteCommerce\Abstracts
 * 
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Product extends Legacy_LC_Product
{
    protected $object_type = 'product';
    protected $post_type = 'product';
    protected $cache_group = 'products';
    protected $data = array(
        'name' => '',
        'slug' => '',
        'date_created' => null,
        'date_modified' => null,
        'status' => false,
        'featured' => false,
        'catalog_visibility' => 'visible',
        'description' => '',
        'sku' => '',
        'price' => '',
        'regular_price' => '',
        'sale_price' => '',
        'date_on_sale_from' => '',
        'date_on_sale_to' => '',
        'total_sales' => '0',
        'tax_status' => '',
        'tax_class' => '',
        'manage_stock' => '',
        'stock_quantity' => '',
        'backorders' => '',
        'low_stock_amount' => '',
        'sold_individually' => '',
        'weight' => '',
        'length' => '',
        'width' => '',
        'height' => '',
        'upsell_ids' => '',
        'cross_sell_ids' => '',
        'parent_id' => '',
        'reviews_allowed' => '',
        'purchase_note' => '',
        'attributes' => array(),
        'default_attributes' => array(),
        'menu_order' => 0,
        'post_password' => '',
        'virtual' => false,
        'downloadable' => false,
        'image_id' => '',
        'gallery_image_ids' => array(),
        'download_limit' => -1,
        'download_expiry' => -1,
        'rating_counts' => array(),
        'average_rating' => 0,
        'review_count' => 0,
    );

    protected $supports = array();

    public function __construct($product = 0)
    {
        parent::__construct($product);
        if (is_numeric($product) && $product > 0) {
            $this->set_id($product);
        } elseif ($product instanceof $self) {
            $this->set_id(absint($product->get_id()));
        } elseif (!empty($product)) {
            $this->set_id(absint($product->ID));
        } else {
            $this->set_object_read(true);
        }

        $this->data_store = LC_Data_Store::load('product-' . $this->get_type());
        if ($this->get_id() > 0) {
            $this->data_store->read($this);
        }
    }

    public function get_type()
    {
        return isset($this->product_type) ? $this->product_type : 'simple';
    }

    public function get_name($context = 'view')
    {
        return $this->get_prop('name', $context);
    }

    public function get_slug($context = 'view')
    {
        return $this->get_prop('slug', $context);
    }

    public function get_date_created($context = 'view')
    {
        return $this->get_prop('date_created', $context);
    }


}