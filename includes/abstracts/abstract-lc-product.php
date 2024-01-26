<?php
use Automattic\WooCommerce\Internal\DependencyManagement\ServiceProviders\ProductAttributesLookupServiceProvider;

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
            lc_deprecated_argument('lc_Product::get_dimensions', '3.0', 'By default, get_dimensions has an argument set to true so that HTML is returned. This is to support the legacy version of the method. To get HTML dimensions, instead use lc_format_dimensions() function. Pass false to this method to return an array of dimensions. This will be the new default behavior in future versions.');
            return apply_filters('woocommerce_product_dimensions', lc_format_dimensions($this->get_dimensions(false)), $this);
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
        if ($this->get_object_read() && !empty($sku) && !lc_product_has_unique_sku($this->get_id(), $sku)) {
            $sku_found = lc_get_product_id_by_sku($sku);

            $this->error(
                'product_invalid_sku',
                __('Invalid or duplicated SKU.', 'woocommerce'),
                400,
                array(
                    'resource_id' => $sku_found,
                    'unique_sku' => lc_product_generate_unique_sku($this->get_id(), $sku),
                )
            );
        }
        $this->set_prop('sku', $sku);
    }

    public function set_price($price)
    {
        $this->set_prop('price', lc_format_decimal($price));
    }

    public function set_regular_price($price)
    {
        $this->set_prop('regular_price', lc_format_decimal($price));
    }

    public function set_date_on_sale_from($date = null)
    {
        $this->set_date_prop('date_on_sale_from', $date);
    }

    public function set_date_on_sale_to($date = null)
    {
        $this->set_date_prop('date_on_sale_to', $date);
    }

    public function set_total_sales($total)
    {
        $this->set_prop('total_sales', absint($total));
    }

    public function set_tax_status($status)
    {
        $options = array(
            'taxable',
            'shipping',
            'none',
        );

        // Set default if empty.
        if (empty($status)) {
            $status = 'taxable';
        }

        $status = strtolower($status);

        if (!in_array($status, $options, true)) {
            $this->error('product_invalid_tax_status', __('Invalid product tax status.', 'woocommerce'));
        }

        $this->set_prop('tax_status', $status);
    }

    public function set_tax_class($class)
    {
        $class = sanitize_title($class);
        $class = 'standard' === $class ? '' : $class;
        $valid_classes = $this->get_valid_tax_classes();

        if (!in_array($class, $valid_classes, true)) {
            $class = '';
        }

        $this->set_prop('tax_class', $class);
    }

    protected function get_valid_tax_classes()
    {
        return lc_Tax::get_tax_class_slugs();
    }

    public function set_manage_stock($manage_stock)
    {
        $this->set_prop('manage_stock', lc_string_to_bool($manage_stock));
    }

    public function set_stock_quantity($quantity)
    {
        $this->set_prop('stock_quantity', '' !== $quantity ? lc_stock_amount($quantity) : null);
    }

    public function set_stock_status($status = 'instock')
    {
        $valid_statuses = lc_get_product_stock_status_options();

        if (isset($valid_statuses[$status])) {
            $this->set_prop('stock_status', $status);
        } else {
            $this->set_prop('stock_status', 'instock');
        }
    }

    public function set_backorders($backorders)
    {
        $this->set_prop('backorders', $backorders);
    }

    public function set_low_stock_amount($amount)
    {
        $this->set_prop('low_stock_amount', '' === $amount ? '' : absint($amount));
    }

    public function set_sold_individually($sold_individually)
    {
        $this->set_prop('sold_individually', lc_string_to_bool($sold_individually));
    }

    public function set_weight($weight)
    {
        $this->set_prop('weight', '' === $weight ? '' : lc_format_decimal($weight));
    }

    public function set_length($length)
    {
        $this->set_prop('length', '' === $length ? '' : lc_format_decimal($length));
    }

    public function set_width($width)
    {
        $this->set_prop('width', '' === $width ? '' : wc_format_decimal($width));
    }

    public function set_height($height)
    {
        $this->set_prop('height', '' === $height ? '' : wc_format_decimal($height));
    }

    public function set_upsell_ids($upsell_ids)
    {
        $this->set_prop('upsell_ids', array_filter((array) $upsell_ids));
    }

    public function set_cross_sell_ids($cross_sell_ids)
    {
        $this->set_prop('cross_sell_ids', array_filter((array) $cross_sell_ids));
    }

    public function set_parent_id($parent_id)
    {
        $this->set_prop('parent_id', absint($parent_id));
    }

    public function set_reviews_allowed($reviews_allowed)
    {
        $this->set_prop('reviews_allowed', wc_string_to_bool($reviews_allowed));
    }

    public function set_purchase_note($purchase_note)
    {
        $this->set_prop('purchase_note', $purchase_note);
    }

    public function set_attributes($raw_attributes)
    {
        $attributes = array_fill_keys(array_keys($this->get_attributes('edit')), null);
        foreach ($raw_attributes as $attribute) {
            if (is_a($attribute, 'WC_Product_Attribute')) {
                $attributes[sanitize_title($attribute->get_name())] = $attribute;
            }
        }

        uasort($attributes, 'wc_product_attribute_uasort_comparison');
        $this->set_prop('attributes', $attributes);
    }

    public function set_default_attributes($default_attributes)
    {
        $this->set_prop('default_attributes', array_map('strval', array_filter((array) $default_attributes, 'wc_array_filter_default_attributes')));
    }

    public function set_menu_order($menu_order)
    {
        $this->set_prop('menu_order', intval($menu_order));
    }

    public function set_post_password($post_password)
    {
        $this->set_prop('post_password', $post_password);
    }

    public function set_category_ids($term_ids)
    {
        $this->set_prop('category_ids', array_unique(array_map('intval', $term_ids)));
    }

    public function set_tag_ids($term_ids)
    {
        $this->set_prop('tag_ids', array_unique(array_map('intval', $term_ids)));
    }

    public function set_virtual($virtual)
    {
        $this->set_prop('virtual', wc_string_to_bool($virtual));
    }

    public function set_shipping_class_id($id)
    {
        $this->set_prop('shipping_class_id', absint($id));
    }

    public function set_downloadable($downloadable)
    {
        $this->set_prop('downloadable', wc_string_to_bool($downloadable));
    }

    public function set_downloads($downloads_array)
    {
        // When the object is first hydrated, only the previously persisted downloads will be passed in.
        $existing_downloads = $this->get_object_read() ? (array) $this->get_prop('downloads') : $downloads_array;
        $downloads = array();
        $errors = array();

        $downloads_array = $this->build_downloads_map($downloads_array);
        $existing_downloads = $this->build_downloads_map($existing_downloads);

        foreach ($downloads_array as $download) {
            $download_id = $download->get_id();
            $is_new = !isset($existing_downloads[$download_id]);
            $has_changed = !$is_new && $existing_downloads[$download_id]->get_file() !== $downloads_array[$download_id]->get_file();

            try {
                $download->check_is_valid($this->get_object_read());
                $downloads[$download_id] = $download;
            } catch (Exception $e) {
                // We only add error messages for newly added downloads (let's not overwhelm the user if there are
                // multiple existing files which are problematic).
                if ($is_new || $has_changed) {
                    $errors[] = $e->getMessage();
                }

                // If the problem is with an existing download, disable it.
                if (!$is_new) {
                    $download->set_enabled(false);
                    $downloads[$download_id] = $download;
                }
            }
        }

        $this->set_prop('downloads', $downloads);

        if ($errors && $this->get_object_read()) {
            $this->error('product_invalid_download', $errors[0]);
        }
    }

    private function build_downloads_map(array $downloads): array
    {
        $downloads_map = array();

        foreach ($downloads as $download_data) {
            // If the item is already a WC_Product_Download we can add it to the map and move on.
            if (is_a($download_data, 'WC_Product_Download')) {
                $downloads_map[$download_data->get_id()] = $download_data;
                continue;
            }

            // If the item is not an array, there is nothing else we can do (bad data).
            if (!is_array($download_data)) {
                continue;
            }

            // Otherwise, transform the array to a WC_Product_Download and add to the map.
            $download_object = new WC_Product_Download();

            // If we don't have a previous hash, generate UUID for download.
            if (empty($download_data['download_id'])) {
                $download_data['download_id'] = wp_generate_uuid4();
            }

            $download_object->set_id($download_data['download_id']);
            $download_object->set_name($download_data['name']);
            $download_object->set_file($download_data['file']);
            $download_object->set_enabled(isset($download_data['enabled']) ? $download_data['enabled'] : true);

            $downloads_map[$download_object->get_id()] = $download_object;
        }

        return $downloads_map;
    }

    public function set_download_limit($download_limit)
    {
        $this->set_prop('download_limit', -1 === (int) $download_limit || '' === $download_limit ? -1 : absint($download_limit));
    }

    public function set_download_expiry($download_expiry)
    {
        $this->set_prop('download_expiry', -1 === (int) $download_expiry || '' === $download_expiry ? -1 : absint($download_expiry));
    }

    public function set_gallery_image_ids($image_ids)
    {
        $image_ids = wp_parse_id_list($image_ids);

        $this->set_prop('gallery_image_ids', $image_ids);
    }

    public function set_image_id($image_id = '')
    {
        $this->set_prop('image_id', $image_id);
    }

    public function set_rating_counts($counts)
    {
        $this->set_prop('rating_counts', array_filter(array_map('absint', (array) $counts)));
    }

    public function set_average_rating($average)
    {
        $this->set_prop('average_rating', wc_format_decimal($average));
    }

    public function set_review_count($count)
    {
        $this->set_prop('review_count', absint($count));
    }

    public function validate_props()
    {
        if (!$this->get_manage_stock()) {
            $this->set_stock_quantity('');
            $this->set_backorders('');
            $this->set_low_stock_amount('');
            return;
        }

        $stock_is_above_notification_threshold = ((int) $this->get_stock_quantity() > absint(get_option('litecommerce_notify_no_stock_amount', 0)));

        $backorders_are_allowed = ('no' !== $this->get_backorders());

        if ($stock_is_above_notification_threshold) {
            $new_stock_status = 'instock';
        } elseif ($backorders_are_allowed) {
            $new_stock_status = 'onbackorder';
        } else {
            $new_stock_status = 'outofstock';
        }

        $this->set_stock_status($new_stock_status);
    }

    public function save()
    {
        $this->validate_props();
        if (!$this->data_store) {
            return $this->get_id();
        }

        do_action('litecommerce_before_' . $this->object_type . '_object_save', $this, $this->data_store);

        $state = $this->before_data_store_save_or_update();

        if ($this->get_id()) {
            $changeset = $this->get_changes();
            $this->data_store->update($this);
        } else {
            $changeset = null;
            $this->data_store->create($this);
        }

        $this->after_data_store_save_or_update();

        if (is_null($changeset) || !empty($changeset)) {
            lc_get_container()->get(
                ProductAttributesLookupServiceProvider::class
            )->on_product_changed($this, $changeset);

        }

        do_action('litecommerce_after_' . $this->object_type . '_object_save', $this, $this->data_store);

        return $this->get_id();

    }

    protected function before_data_store_save_or_update()
    {
    }

    protected function after_data_store_save_or_update()
    {
        $this->maybe_defer_product_sync();
    }

    public function delete($force_delete)
    {
        $product_id = $this->get_id();
        $deleted = parent::delete($force_delete);

        if ($deleted) {
            $this->maybe_defer_product_sync();
            lc_get_container()->get(
                ProductAttributesLookupServiceProvider::class
            )->on_product_deleted($product_id);
        }

        return $deleted;
    }

    protected function maybe_defer_product_sync()
    {
        $parent_id = $this->get_parent_id();
        if ($parent_id) {
            lc_defferred_product_sync($parent_id);
        }
    }

    public function supports($feature)
    {
        return apply_filters('litecommerce_product_supports', in_array($feature, $this->supports, true), $feature, $this);
    }

    public function exists()
    {
        return false !== $this->get_status();
    }

    public function is_type($type)
    {
        return ($this->get_type() === $type || is_array($type) && in_array($this->get_type(), $type, true));
    }

    public function is_sold_individually()
    {
        return apply_filters('woocommerce_is_sold_individually', true === $this->get_sold_individually(), $this);
    }

    public function is_visible()
    {
        $visible = $this->is_visible_core();
        return apply_filters('woocommerce_product_is_visible', $visible, $this->get_id());
    }

    protected function is_visibile_core()
    {
        $visible = 'visible' === $this->get_catalog_visibility() || (is_search() && 'search' === $this->get_catalog_visibility()) || !(is_search() && 'catalog' === $this->get_catalog_visibility());

        if ('trash' === $this->get_status()) {
            $visible = false;
        } elseif ('publish' !== $this->get_status() && !current_user_can('edit_post', $this->get_id())) {
            $visible = false;
        }

        if ($this->get_parent_id()) {
            $parent_product = lc_get_product($this->get_parent_id());

            if ($parent_product && 'publish' !== $parent_product->get_status() && !current_user_can('edit_post', $parent_product->get_id())) {
                $visible = false;
            }
        }

        if ('yes' === get_option('litecommerce_hide_out_of_stock_items') && !$this->is_in_stock()) {
            $visible = false;
        }

        return $visible;
    }

    public function is_purchasable()
    {
        return apply_filters('woocommerce_is_purchasable', $this->exists() && ('publish' === $this->get_status() || current_user_can('edit_post', $this->get_id())) && '' !== $this->get_price(), $this);
    }

    public function is_on_sale($context = 'view')
    {
        if ('' !== (string) $this->get_sale_price($context) && $this->get_regular_price($context) > $this->get_sale_price($context)) {
            $on_sale = true;

            if ($this->get_date_on_sale_from($context) && $this->get_date_on_sale_from($context)->getTimestamp() > time()) {
                $on_sale = false;
            }

            if ($this->get_date_on_sale_to($context) && $this->get_date_on_sale_to($context)->getTimestamp() < time()) {
                $on_sale = false;
            }
        } else {
            $on_sale = false;
        }

        return 'view' === $context ? apply_filters(
            'litecommerce_product_is_on_sale',
            $on_sale,
            $this
        ) : $on_sale;

    }

    public function has_dimensions()
    {
        return ($this->get_length() || $this->get_height() || $this->get_width && !$this->get_virtual());
    }

    public function has_weight()
    {
        return $this->get_weight() && !$this->get_virtual();
    }

    public function is_in_stock()
    {
        return apply_filters(
            'litecommerce_product_is_in_stock',
            'outofstock' !== $this->get_stock_status(),
            $this
        );
    }

    public function needs_shipping()
    {
        return apply_filters('litecommerce_product_needs_shipping', !$this->is_virtual(), $this);
    }

    public function is_taxable()
    {
        return apply_filters('woocommerce_product_is_taxable', $this->get_tax_status() === 'taxable' && wc_tax_enabled(), $this);
    }

    public function is_shipping_taxable()
    {
        return $this->needs_shipping() && ($this->get_tax_status() === 'taxable' || $this->get_tax_status() === 'shipping');
    }

    public function managing_stock()
    {
        if ('yes' === get_option('woocommerce_manage_stock')) {
            return $this->get_manage_stock();
        }
        return false;
    }

    public function backorders_allowed()
    {
        return apply_filters('woocommerce_product_backorders_allowed', ('yes' === $this->get_backorders() || 'notify' === $this->get_backorders()), $this->get_id(), $this);
    }

    public function backorders_require_notification()
    {
        return apply_filters('woocommerce_product_backorders_require_notification', ($this->managing_stock() && 'notify' === $this->get_backorders()), $this);
    }

    public function is_on_backorder($qty_in_cart = 0)
    {
        if ('onbackorder' === $this->get_stock_status()) {
            return true;
        }

        return $this->managing_stock() && $this->backorders_allowed() && ($this->get_stock_quantity() - $qty_in_cart) < 0;
    }

    public function has_enough_stock($quantity)
    {
        return !$this->managing_stock() || $this->backorders_allowed() || $this->get_stock_quantity() >= $quantity;
    }

    public function has_attributes()
    {
        foreach ($this->get_attributes() as $attribute) {
            if ($attribute->get_visible()) {
                return true;
            }
        }
        return false;
    }

    public function has_child()
    {
        return 0 < count($this->get_children());
    }

    public function child_has_dimensions()
    {
        return false;
    }

    public function child_has_weight()
    {
        return false;
    }

    public function has_file($download_id = '')
    {
        return $this->is_downloadable() && $this->get_file($download_id);
    }

    public function has_options()
    {
        return apply_filters('woocommerce_product_has_options', false, $this);
    }

    public function get_title()
    {
        return apply_filters('woocommerce_product_title', $this->get_name(), $this);
    }

    public function get_permalink()
    {
        return get_permalink($this->get_id());
    }

    public function get_children()
    {
        return array();
    }

    public function get_stock_managed_by_id()
    {
        return $this->get_id();
    }

    public function get_price_html($deprecated = '')
    {
        if ('' === $this->get_price()) {
            $price = apply_filters('woocommerce_empty_price_html', '', $this);
        } elseif ($this->is_on_sale()) {
            $price = wc_format_sale_price(wc_get_price_to_display($this, array('price' => $this->get_regular_price())), wc_get_price_to_display($this)) . $this->get_price_suffix();
        } else {
            $price = wc_price(wc_get_price_to_display($this)) . $this->get_price_suffix();
        }

        return apply_filters('woocommerce_get_price_html', $price, $this);
    }

    public function get_formatted_name()
    {
        if ($this->get_sku()) {
            $identifier = $this->get_sku();
        } else {
            $identifier = '#' . $this->get_id();
        }
        return sprintf('%2$s (%1$s)', $identifier, $this->get_name());
    }

    public function get_min_purchase_quantity()
    {
        return 1;
    }

    public function get_max_purchase_quantity()
    {
        return $this->is_sold_individually() ? 1 : ($this->backorders_allowed() || !$this->managing_stock() ? -1 : $this->get_stock_quantity());
    }

    public function add_to_cart_url()
    {
        return apply_filters('woocommerce_product_add_to_cart_url', $this->get_permalink(), $this);
    }

    public function single_add_to_cart_text()
    {
        return apply_filters('woocommerce_product_single_add_to_cart_text', __('Add to cart', 'woocommerce'), $this);
    }

    public function add_to_cart_aria_describedby()
    {
        return apply_filters('litecommerce_product_add_cart_area_describedby', '', $this)
        ;
    }

    public function add_to_cart_text()
    {
        return apply_filters('woocommerce_product_add_to_cart_text', __('Read more', 'woocommerce'), $this);
    }

    public function add_to_cart_description()
    {
        /* translators: %s: Product title */
        return apply_filters('woocommerce_product_add_to_cart_description', sprintf(__('Read more about &ldquo;%s&rdquo;', 'woocommerce'), $this->get_name()), $this);
    }

    public function get_image($size = 'litecommerce_thumbnail', $attr = array(), $placeholder = true)
    {
        $image = '';
        if ($this->get_image_id()) {
            $image = wp_get_attachment_image($this->get_image_id(), $size, false, $attr);

        } elseif ($this->get_parent_id()) {
            $parent_product = lc_get_product(
                $this->get_parent_id()
            );
            if ($parent_product) {
                $image = $parent_product->get_image($size, $attr, $placeholder);
            }
        }

        if (!$image && $placeholder) {
            $image = lc_placeholder_img($size, $attr);
        }

        return apply_filters('litecommerce_product_get_image', $image, $this, $size, $attr, $placeholder, $image);
    }

    public function get_shipping_class()
    {
        $class_id = $this->get_shipping_class_id();
        if ($class_id) {
            $term = get_term_by(
                'id',
                $class_id,
                'product_shipping_class'
            );
            if ($term && !is_wp_error($term)) {
                return $term->slug;
            }
        }
        return '';
    }

    public function get_attribute($attribute)
    {
        $attributes = $this->get_attributes();
        $attribute = sanitize_title($attribute);

        if (isset($attributes[$attribute])) {
            $attribute_object = $attributes[$attribute];
        } elseif (isset($attributes['pa_' . $attribute])) {
            $attribute_object = $attributes['pa' . $attribute];
        } else {
            return '';
        }

        return $attribute_object->is_taxonomy() ? implode(', ', lc_get_product_terms($this->get_id(), $attribute_object->get_name(), array('fields' => 'names'))) : lc_implode_text_attributes($attribute_object->get_options());
    }









}