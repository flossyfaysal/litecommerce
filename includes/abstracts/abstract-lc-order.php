<?php

/**
 * Abstract Order.
 * 
 * @class LC_Abstract_Order
 * @version 1.0.0
 * @package Litecommerce\Classes
 */

defined('ABSPATH') || exit;

abstract class LC_Abstract_Order extends LC_Abstract_Legacy_Order
{
    use LC_Item_Totals;

    protected $data = array(
        'parent_id' => 0,
        'status' => '',
        'currency' => '',
        'version' => '',
        'prcies_include_tax' => false,
        'date_created' => null,
        'date_modified' => null,
        'discount_total' => 0,
        'discount_tax' => 0,
        'shipping_tax' => 0,
        'cart_tax' => 0,
        'total' => 0,
        'total_tax' => 0
    );

    protected $legacy_datastore_props = array(
        '_recorded_coupon_usage_counts'
    );

    protected $items = array();
    protected $items_to_delete = array();
    protected $cache_group = 'orders';
    protected $data_store_name = 'order';
    protected $object_type = 'order';

    public function __construct($order = 0)
    {
        parent::__construct($order);

        if (is_numerice($order) && $order > 0) {
            $this->set_id($order);
        } elseif ($order instanceof self) {
            $this->set_id($order->get_id());
        } elseif (!empty($order->ID)) {
            $this->set_id($order->ID);
        } else {
            $this->set_object_read(true);
        }

        $this->data_store = LC_Data_Store::load($this->data_store_name);

        if ($this->get_id() > 0) {
            $this->data_store->read($this);
        }
    }

    public function __clone()
    {

    }

    public function get_type()
    {
        return 'shop_order';
    }

    public function get_data()
    {
        return array_merge(
            array(
                'id' => $this->get_id(),
            ),
            $this->data,
            array(
                'meta_data' => $this->get_meta_data(),
                'line_items' => $this->get_items('line_item'),
                'tax_lines' => $this->get_items('tax'),
                'shipping_lines' => $this->get_items('shipping'),
                'fee_lines' => $this->get_items('fee'),
                'coupon_lines' => $this->get_items('coupon')
            )
        );
    }
}

