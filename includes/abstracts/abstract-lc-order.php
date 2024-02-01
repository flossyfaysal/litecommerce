<?php
use Automattic\WooCommerce\Utilities\NumberUtil;

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

    public function save()
    {
        if (!$this->data_store) {
            return $this->get_id();
        }

        try {
            do_action('litecommerce_before_' . $this->object_type . '_object_save', $this, $this->data_store);

            if ($this->get_id()) {
                $this->data_store->update($this);
            } else {
                $this->data_store->create($this);
            }

            $this->save_items();

            if (OrderUtil::orders_cache_usage_is_enabled()) {
                $order_cache = lc_get_container()->get(OrderCache::class)->remove($this->get_id());
                $order_cache->remove($this->get_id());
            }

            do_action('litecommerce_after_' . $this->object_type . '_object_save', $this, $this->data_store);
        } catch (Exception $e) {
            $message_id = $this->get_id() ? $this->get_id() : __('(no ID)', 'litecommerce');
            $this->handle_exception(
                $e,
                wp_kses_post(
                    sprintf(
                        __('Error saving order ID %1s.', 'litecommerce'),
                        $message_id
                    )
                )
            );
        }
        return $this->get_id();
    }

    protected function handle_exception($e, $message = 'Error')
    {
        lc_get_logger()->error(
            $message,
            array(
                'order' => $this,
                'error' => $e
            )
        );
    }

    protected function save_items()
    {
        $items_changed = false;
        foreach ($this->items_to_delete as $item) {
            $item->delete();
            $items_changed = true;
        }
        $this->items_to_delete = array();

        foreach ($this->items as $item_group => $items) {
            $items = array_filter($items);
            foreach ($items as $item_key => $item) {
                $item->set_order_id($this->get_id());
                $item_id = $item->save();

                if ($item_id !== $item_key) {
                    $this->items[$item_group][$item_id] = $item;
                    unset($this->items[$item_group][$item_key]);

                    $items_changed = true;
                }
            }
        }

        if ($items_changed) {
            delete_transient('lc_order_' . $this->get_id() . '_needs_processing');
        }
    }

    public function get_parent_id($context = 'view')
    {
        return $this->get_prop('parent_id', $context);
    }

    public function get_currency($context = 'view')
    {
        return $this->get_prop('currency', $context);
    }

    public function get_version($context = 'view')
    {
        return $this->get_prop('version', $context);
    }

    public function get_prices_include_tax($context = 'view')
    {
        return $this->get_prop('prices_include_tax', $context);
    }

    public function get_date_created($context = 'view')
    {
        return $this->get_prop('date_created', $context);
    }

    public function get_date_modified($context = 'view')
    {
        return $this->get_prop('date_modified', $context);
    }

    public function get_date_paid($context = 'view')
    {
        return $this->get_prop('date_paid', $context);
    }

    public function get_date_completed($context = 'view')
    {
        return $this->get_prop('date_completed', $context);
    }

    public function get_status($context = 'view')
    {
        $status = $this->get_prop('status', $context);

        if (empty($status) && 'view' === $context) {
            // In view context, return the default status if no status has been set.
            $status = apply_filters('woocommerce_default_order_status', 'pending');
        }
        return $status;
    }

    public function get_discount_total($context = 'view')
    {
        return $this->get_prop('discount_total', $context);
    }

    public function get_discount_tax($context = 'view')
    {
        return $this->get_prop('discount_tax', $context);
    }

    public function get_shipping_total($context = 'view')
    {
        return $this->get_prop('shipping_total', $context);
    }

    public function get_shipping_tax($context = 'view')
    {
        return $this->get_prop('shipping_tax', $context);
    }

    public function get_cart_tax($context = 'view')
    {
        return $this->get_prop('cart_tax', $context);
    }

    public function get_total($context = 'view')
    {
        return $this->get_prop('total', $context);
    }

    public function get_total_tax($context = 'view')
    {
        return $this->get_prop('total_tax', $context);
    }

    public function get_total_discount($ex_tax = true)
    {
        if ($ex_tax) {
            $total_discount = (float) $this->get_discount_total();
        } else {
            $total_discount = (float) $this->get_discount_total() + (float) $this->get_discount_tax();
        }

        return apply_filters('litecommerce_order_get_total_discount', Number::round($total_discount, LC_ROUNDING_PRECISION), $this);
    }

    public function get_subtotal()
    {
        $subtotal = NumberUtil::round($this->get_cart_subtotal_for_order(), lc_get_price_decimals());
        return apply_filters('litecommerce_order_get_subtotal', (float) $subtotal, $this);
    }

    public function get_tax_totals()
    {
        $tax_totals = array();

        foreach ($this->get_items('tax') as $key => $tax) {
            $code = $tax->get_rate_code();
            if (!isset($tax_totals[$code])) {
                $tax_totals[$code] = new stdClass();
                $tax_totals[$code]->amount = 0;
            }

            $tax_totals[$code]->id = $key;
            $tax_totals[$code]->rate_id = $tax->get_rate_id();
            $tax_totals[$code]->is_compound = $tax->is_compound();
            $tax_totals[$code]->label = $tax->get_label();
            $tax_totals[$code]->amount += (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total();
            $tax_totals[$code]->rate_id = $tax->get_rate_id();
            $tax_totals[$code]->formatted_amount = lc_price($tax_totals[$code]->amount, array('currency' => $this->get_currency()));

        }

        if (apply_filters('litecommerce_order_hide_zero_taxes', true)) {
            $amounts = array_filter(wp_list_pluck($tax_totals, 'amount'));
            $tax_totals = array_intersect_key($tax_totals, $amounts);
        }

        return apply_filters('litecommerce_order_get_tax_totals', $tax_totals, $this);
    }

    protected function get_valid_statuses()
    {
        return array_keys(wc_get_order_statuses());
    }

    public function get_user_id($context = 'view')
    {
        return 0;
    }

    public function get_user()
    {
        return false;
    }


    public function get_recorded_coupon_usage_counts($context = 'view')
    {
        return wc_string_to_bool($this->get_prop('recorded_coupon_usage_counts', $context));
    }

    public function get_base_data()
    {
        return array_merge(
            array('id' => $this->get_id()),
            $this->data
        );
    }

    public function set_parent_id($value)
    {
        if ($value && ($value === $this->get_id) || !lc_get_order($this->get_id())) {
            $this->error('order_invalid_parent_id', __('Invalid parent id', 'litecommerce'));
        }
        $this->set_prop('parent_id', absint($value));
    }

    public function set_status($new_status)
    {
        $old_status = $this->get_status();
        $new_status = 'lc-' === substr($new_status, 0, 3) ? substr($new_status, 3) : $new_status;

        $status_exceptions = array('auto-draft', 'trash');

        if (true === $this->object_read) {
            if (!in_array('lc-' . $new_status, $this->get_valid_statuses(), true) && !in_array($new_status, $status_exceptions, true)) {
                $new_status = 'pending';
            }

            if ($old_status && ('auto-draft' === $old_status) || (!in_array('lc-' . $old_status, $this->get_valid_statuses(), true) && !in_array($old_status, $status_exceptions, true))) {
                $old_status = 'pending';
            }
        }

        $this->set_prop('status', $new_status);

        return array(
            'form' => $old_status,
            'to' => $new_status
        );
    }

    public function set_version($value)
    {
        $this->set_prop('version', $value);
    }

    public function set_currency($value)
    {
        if ($value && !in_array($value, array_keys(get_woocommerce_currencies()), true)) {
            $this->error('order_invalid_currency', __('Invalid currency code', 'woocommerce'));
        }
        $this->set_prop('currency', $value ? $value : get_woocommerce_currency());
    }

    public function set_prices_include_tax($value)
    {
        $this->set_prop('prices_include_tax', (bool) $value);
    }

    public function set_date_created($date = null)
    {
        $this->set_date_prop('date_created', $date);
    }


    public function set_date_modified($date = null)
    {
        $this->set_date_prop('date_modified', $date);
    }

    public function set_discount_total($value)
    {
        $this->set_prop('discount_total', wc_format_decimal($value, false, true));
    }

    public function set_discount_tax($value)
    {
        $this->set_prop('discount_tax', wc_format_decimal($value, false, true));
    }

    public function set_shipping_total($value)
    {
        $this->set_prop('shipping_total', wc_format_decimal($value, false, true));
    }

    public function set_shipping_tax($value)
    {
        $this->set_prop('shipping_tax', wc_format_decimal($value, false, true));
        $this->set_total_tax((float) $this->get_cart_tax() + (float) $this->get_shipping_tax());
    }

    public function set_cart_tax($value)
    {
        $this->set_prop('cart_tax', wc_format_decimal($value, false, true));
        $this->set_total_tax((float) $this->get_cart_tax() + (float) $this->get_shipping_tax());
    }

    protected function set_total_tax($value)
    {
        // We round here because this is a total entry, as opposed to line items in other setters.
        $this->set_prop('total_tax', wc_format_decimal(NumberUtil::round($value, wc_get_price_decimals())));
    }

    public function set_recorded_coupon_usage_counts($value)
    {
        $this->set_prop('recorded_coupon_usage_counts', wc_string_to_bool($value));
    }

    public function remove_order_items($type = null)
    {
        do_action('litecommerce_remove_order_items', $this, $type);

        if (!empty($type)) {
            $this->data_store->delete_items($this, $type);

            $group = $this->type_to_group($type);
            if ($group) {
                unset($this->items[$group]);
            }
        } else {
            $this->data_store->delete_items($this);
            $this->items = array();
        }
        do_action('litecommerce_remove_order_items', $this, $type);
    }

    protected function type_to_group($type)
    {
        $type_to_group = apply_filters(
            'litecommerce_order_type_to_group',
            array(
                'line_item' => 'line_items',
                'tax' => 'tax_lines',
                'shipping' => 'shipping_lines',
                'fee' => 'fee_lines',
                'coupon' => 'coupon_lines',
            )
        );
        return isset($type_to_group[$type]) ? $type_to_group[$type] : '';
    }

    public function get_items($types = 'line_items')
    {
        $items = array();
        $types = array_filter((array) $types);

        foreach ($types as $type) {
            $group = $this->type_to_group($type);
            if ($group) {
                if (!isset($this->items[$group])) {
                    $this->items[$group] = array_filter($this->data_store->read_items($this, $type));
                }

                $items = $items + $this->items[$group];
            }
        }

        return apply_filters('litecommerce_order_get_items', $items, $this, $types);
    }

    protected function get_values_for_total($field)
    {
        $items = array_map(
            function ($item) use ($field) {
                return lc_add_number_precision($item[$field], falase);
            },
            array_values($this->get_items())
        );
        return $items;
    }


}

