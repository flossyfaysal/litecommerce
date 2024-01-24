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

    public function get_date_modified($context = 'view')
    {
        return $this->get_prop('date_modified', $context);
    }

    public function get_status($context = 'view')
    {
        return $this->get_prop('status', $context);
    }

    public function get_featured($context = 'view')
    {
        return $this->get_prop('featured', $context);
    }

    public function get_catalog_visibility($context = 'view')
    {
        return $this->get_prop('catalog_visibility', $context);
    }

    public function get_description($context = 'view')
    {
        return $this->get_prop('description', $context);
    }

    public function get_short_description($context = 'view')
    {
        return $this->get_prop('short_description', $context);
    }

    public function get_sku($context = 'view')
    {
        return $this->get_prop('sku', $context);
    }

    public function get_price($context = 'view')
    {
        return $this->get_prop('price', $context);
    }

    public function get_regular_price($context = 'view')
    {
        return $this->get_prop('regular_price', $context);
    }

    public function get_sale_price($context = 'view')
    {
        return $this->get_prop('sale_price', $context);
    }

    public function get_date_on_sale_from($context = 'view')
    {
        return $this->get_prop('date_on_sale_from', $context);
    }

    public function get_date_on_sale_to($context = 'view')
    {
        return $this->get_prop('date_on_sale_to', $context);
    }

    public function get_total_sales($context = 'view')
    {
        return $this->get_prop('total_sales', $context);
    }

    public function get_tax_status($context = 'view')
    {
        return $this->get_prop('tax_status', $context);
    }

    public function get_tax_class($context = 'view')
    {
        return $this->get_prop('tax_class', $context);
    }

    public function get_manage_stock($context = 'view')
    {
        return $this->get_prop('manage_stock', $context);
    }

    public function get_stock_quantity($context = 'view')
    {
        return $this->get_prop('stock_quantity', $context);
    }

    public function get_backorders($context = 'view')
    {
        return $this->get_prop('backorders', $context);
    }

    public function get_low_stock_amount($context = 'view')
    {
        return $this->get_prop('low_stock_amount', $context);
    }

    public function get_sold_individually($context = 'view')
    {
        return $this->get_prop('sold_individually', $context);
    }

    public function get_weight($context = 'view')
    {
        return $this->get_prop('weight', $context);
    }

    public function get_length($context = 'view')
    {
        return $this->get_prop('length', $context);
    }

    public function get_width($context = 'view')
    {
        return $this->get_prop('width', $context);
    }

    public function get_height($context = 'view')
    {
        return $this->get_prop('height', $context);
    }

    public function get_dimensions($formatted = true)
    {
        if ($formatted) {
            wc_deprecated_argument('WC_Product::get_dimensions', '3.0', 'By default, get_dimensions has an argument set to true so that HTML is returned. This is to support the legacy version of the method. To get HTML dimensions, instead use wc_format_dimensions() function. Pass false to this method to return an array of dimensions. This will be the new default behavior in future versions.');
            return apply_filters('woocommerce_product_dimensions', wc_format_dimensions($this->get_dimensions(false)), $this);
        }
        return array(
            'length' => $this->get_length(),
            'width' => $this->get_width(),
            'height' => $this->get_height(),
        );
    }

    public function get_upsell_ids($context = 'view')
    {
        return $this->get_prop('upsell_ids', $context);
    }

    public function get_cross_sell_ids($context = 'view')
    {
        return $this->get_prop('cross_sell_ids', $context);
    }

    public function get_parent_id($context = 'view')
    {
        return $this->get_prop('parent_id', $context);
    }

    public function get_reviews_allowed($context = 'view')
    {
        return $this->get_prop('reviews_allowed', $context);
    }

    public function get_purchase_note($context = 'view')
    {
        return $this->get_prop('purchase_note', $context);
    }

    public function get_attributes($context = 'view')
    {
        return $this->get_prop('attributes', $context);
    }

    public function get_default_attributes($context = 'view')
    {
        return $this->get_prop('default_attributes', $context);
    }
    public function get_menu_order($context = 'view')
    {
        return $this->get_prop('menu_order', $context);
    }

    public function get_post_password($context = 'view')
    {
        return $this->get_prop('post_password', $context);
    }

    public function get_category_ids($context = 'view')
    {
        return $this->get_prop('category_ids', $context);
    }

    public function get_tag_ids($context = 'view')
    {
        return $this->get_prop('tag_ids', $context);
    }

    public function get_virtual($context = 'view')
    {
        return $this->get_prop('virtual', $context);
    }

    public function get_gallery_image_ids($context = 'view')
    {
        return $this->get_prop('gallery_image_ids', $context);
    }

    public function get_shipping_class_id($context = 'view')
    {
        return $this->get_prop('shipping_class_id', $context);
    }

    public function get_downloads($context = 'view')
    {
        return $this->get_prop('downloads', $context);
    }

    public function get_download_expiry($context = 'view')
    {
        return $this->get_prop('download_expiry', $context);
    }

    public function get_downloadable($context = 'view')
    {
        return $this->get_prop('downloadable', $context);
    }

    public function get_download_limit($context = 'view')
    {
        return $this->get_prop('download_limit', $context);
    }

    public function get_image_id($context = 'view')
    {
        return $this->get_prop('image_id', $context);
    }

    public function get_rating_counts($context = 'view')
    {
        return $this->get_prop('rating_counts', $context);
    }

    public function get_average_rating($context = 'view')
    {
        return $this->get_prop('average_rating', $context);
    }

    public function get_review_count($context = 'view')
    {
        return $this->get_prop('review_count', $context);
    }

    public function set_name($name)
    {
        $this->set_prop('name', $name);
    }

    public function set_slug($slug)
    {
        $this->set_prop('slug', $slug);
    }

    public function set_date_created($date = null)
    {
        $this->set_date_prop('date_created', $date);
    }

    public function set_date_modified($date = null)
    {
        $this->set_date_prop('date_modified', $date);
    }

    public function set_status($status)
    {
        $this->set_prop('status', $status);
    }

    public function set_featured($featured)
    {
        $this->set_prop('featured', lc_string_to_bool($featured));
    }

    public function set_catalog_visibility($visibility)
    {
        $options = array_keys(
            lc_get_product_visibility_options()
        );
        $visibility = in_array($visibility, $options, true) ? $visibility : strtolower($visibility);

        if (!in_array($visibility, $options, true)) {
            $this->error(
                'product_invalid_catalog_visibility',
                __(
                    'Invalid catalog visibility options.',
                    'litecommerce'
                )
            );
        }
        $this->set_prop('catalog_visibility', $visibility);
    }

    public function set_description($description)
    {
        $this->set_prop('description', $description);
    }

    public function set_short_description($short_description)
    {
        $this->set_prop('short_description', $short_description);
    }

    public function set_sku($sku)
    {
        $sku = (string) $sku;
        if ($this->get_object_read() && !empty($sku) && !wc_product_has_unique_sku($this->get_id(), $sku)) {
            $sku_found = wc_get_product_id_by_sku($sku);

            $this->error(
                'product_invalid_sku',
                __('Invalid or duplicated SKU.', 'woocommerce'),
                400,
                array(
                    'resource_id' => $sku_found,
                    'unique_sku' => wc_product_generate_unique_sku($this->get_id(), $sku),
                )
            );
        }
        $this->set_prop('sku', $sku);
    }

}