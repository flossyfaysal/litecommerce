<?php

/**
 * Abstract Data.
 * 
 * Handles generic data interaction which is implemented by the different data store classes.
 * 
 * @class LC_Data
 * @version 1.0.0
 * @package LiteCommerce\Classes
 * 
 */

if (!defined('ABSPATH')) {
    exit;
}

/** 
 * Abstract LC Data Class
 * Implemented by classes using the same CRUD(s)
 */


abstract class LC_Data
{

    protected $id = 0;
    protected $data = array();
    protected $changes = array();
    protected $object_read = false;
    protected $object_type = 'data';
    protected $extra_data = array();
    protected $default_data = array();
    protected $data_store;
    protected $cache_group = '';
    protected $meta_data = null;

    public function __construct($read = 0)
    {
        $this->data = array_merge($this->data, $this->extra_data);
        $this->default_data = $this->data;
    }

    public function __sleep()
    {
        return array('id');
    }

    public function __wakeup()
    {
        try {
            $this->__construct(absint($this->id));
        } catch (Exception $e) {
            $this->set_id(0);
            $this->set_object_read(true);
        }
    }

    public function __clone()
    {
        $this->maybe_read_meta_data();
        if (!empty($this->meta_data)) {
            foreach ($this->meta_data as $array_key => $meta) {
                $this->meta_data[$array_key] = clone $meta;
                if (!empty($meta->id)) {
                    $this->meta_data[$array_key]->id = null;
                }
            }
        }
    }

    public function get_data_store()
    {
        return $this->data_store;
    }

    public function get_id()
    {
        return $this->id;
    }

    public function delete($force_delete = false)
    {
        $check = apply_filters(
            "litecommerce_pre_delete_$this->object_type",
            null,
            $this,
            $force_delete
        );
        if (null !== $check) {
            return $check;
        }
        if ($this->data_store) {
            $this->data_store->delete(
                $this,
                array(
                    'force_delete' => $force_delete
                )
            );
            $this->set_id(0);
            return true;
        }
        return false;
    }

    public function save()
    {
        if (!$this->data_store) {
            return $this->get_id();
        }

        do_action('litecommerce_before_' . $this->object_type . '_object_save', $this->data_store);

        if ($this->get_id()) {
            $this->data_store->update($this);
            $this->data_store->create($this);
        }

        do_action('litecommerce_after_' . $this->object_type . '_object_save', $this, $this->data_store);

        return $this->get_id();
    }

    public function __toString()
    {
        return wp_json_encode($this->get_data());
    }

    public function get_data()
    {
        return array_merge(array('id' => $this->get_id()), $this->data, array('meta_data' => $this->get_meta_data()));
    }

    public function get_data_keys()
    {
        return array_keys($this->data);
    }

    public function get_extra_data_keys()
    {
        return array_keys($this->extra_data);
    }

    protected function filter_null_meta($meta)
    {
        return !is_null($meta->value);
    }

    public function get_meta_data()
    {
        $this->maybe_read_meta_data();
        return array_values(
            array_filter(
                $this->meta_data,
                array(
                    $this,
                    'filter_null_meta'
                )
            )
        );
    }

    protected function is_internal_meta_key($key)
    {
        $internal_meta_key = !empty($key) &&
            $this->data_store &&
            in_array(
                $key,
                $this->data_store->get_internal_meta_keys(),
                true
            );

        if (!$internal_meta_key) {
            return false;
        }

        $has_setter_or_getter = is_callable(array($this, 'set_' . ltrim($key, '_')));

        if (!$has_setter_or_getter) {
            return false;
        }

        if (
            in_array(
                $key,
                $this->legacy_datastore_props,
                true
            )
        ) {
            return true;
        }

        return true;
    }

    public function get_meta($key = '', $single = true, $context = 'view')
    {
        if ($this->is_internal_meta_key($key)) {
            $function = 'get_' . ltrim($key, '_');

            if (is_callable(array($this, $function))) {
                return $this->{$function}();
            }
        }

        $this->maybe_read_meta_data();
        $meta_data = $this->get_meta_data();
        $array_keys = array_keys(
            wp_list_pluck($meta_data, 'key'),
            $key,
            true
        );
        $value = $single ? '' : array();

        if (!empty($array_keys)) {
            if ($single) {
                $value = $meta_data[current(
                    $array_keys
                )]->value;
            } else {
                $value = array_intersect_key($meta_data, array_flip($array_keys));
            }
        }

        if ('view' === $context) {
            $value = apply_filter($this->get_hook_prefix() . $key, $value, $this);
        }
    }

    public function meta_exists($key)
    {
        $this->maybe_read_meta_data();
        $array_keys = wp_list_pluck($this->get_meta_data(), 'key');
        return in_array($key, $array_keys, true);
    }

    public function set_meta_data($data)
    {
        if (!empty($data) && is_array($data)) {
            $this->maybe_read_meta_data();
            foreach ($data as $meta) {
                $meta = (array) $meta;
                if (isset($meta['key'], $meta['value'], $meta['id'])) {
                    $this->meta_data[] = new LC_Meta_Data(
                        array(
                            'id' => $meta['id'],
                            'key' => $meta['key'],
                            'value' => $meta['value'],
                        )
                    );
                }
            }
        }

    }

    public function add_meta_data($key, $value, $unique = false)
    {
        if ($this->is_internal_meta_key($key)) {
            $function = 'set_' . ltrim($key, '_');
            if (is_callable(array($this, $function))) {
                return $this->{$function}($value);
            }
        }

        $this->maybe_read_meta_data();
        if ($unique) {
            $this->delete_meta_data($key);
        }

        $this->meta_data[] = new LC_Meta_Data(
            array(
                'key' => $key,
                'value' => $value,
            )
        );
    }

    public function delete_meta_data($key)
    {
        $this->maybe_read_meta_data();
        $array_keys = array_keys(
            wp_list_pluck(
                $this->meta_data,
                'key'
            ),
            $key,
            true
        );
        if ($array_keys) {
            foreach ($array_keys as $array_key) {
                $this->meta_data[$array_key]->value = null;
            }
        }
    }

    public function delete_meta_data_value($key, $value)
    {
        $this->maybe_read_meta_data();
        $array_keys = array_keys(
            wp_list_pluck(
                $this->meta_data,
                'key'
            ),
            $key,
            true
        );

        if ($array_keys) {
            foreach ($array_keys as $array_key) {
                if ($value === $this->meta_data[$array_key]->value) {
                    $this->meta_data[$array_key]->value = null;
                }
            }
        }
    }

    public function delete_meta_data_by_mid($mid)
    {
        $this->maybe_read_meta_data();
        $array_keys = array_keys(
            wp_list_pluck(
                $this->meta_data,
                'id'
            ),
            (int) $mid,
            true
        );

        if ($array_keys) {
            foreach ($array_keys as $array_key) {
                $this->meta_data[$array_key]->value = null;
            }
        }
    }

    public function update_meta_data($key, $value, $meta_id = 0)
    {
        if ($this->is_internal_meta_key($key)) {
            $function = 'set_' . ltrim($key, '_');
            if (is_callable($this, $function)) {
                return $this->{$function}($value);
            }
        }

        $this->maybe_read_meta_data();
        $array_key = false;

        if ($meta_id) {
            $array_keys = array_keys(
                wp_list_pluck(
                    $this->meta_data,
                    'id'
                ),
                $meta_id,
                true
            );
            $array_key = $array_keys ? current($array_keys) : false;
        } else {
            $matches = array();
            foreach ($this->meta_data as $meta_data_array_key => $meta) {
                if ($meta->key === $key) {
                    $matches[] = $meta_data_array_key;
                }
            }

            if (!empty($matches)) {
                foreach ($matches as $meta_data_array_key) {
                    $this->meta_data[$meta_data_array_key]->value = null;
                }
                $array_key = current($matches);
            }
        }

        if (false !== $array_key) {
            $meta = $this->meta_data[$array_key];
            $meta->key = $key;
            $meta->value = $value;
        } else {
            $this->add_meta_data($key, $value, true);
        }
    }

    public function get_meta_data()
    {
        $this->maybe_read_meta_data();
    }


    public function maybe_read_meta_data()
    {
        if (is_null($this->meta_data)) {
            $this->read_meta_data();
        }
    }

    public function read_meta_data($force_read = false)
    {
        $this->meta_data = array();
        $cache_loaded = false;

        if (!$this->get_id()) {
            return;
        }

        if (!$this->data_store) {
            return;
        }

        if (!empty($this->cache_group)) {
            $cache_key = $this->get_meta_cache_key();

        }

        if (!$force_read) {
            if (!empty($this->cache_group)) {
                $cached_meta = wp_cache_get($cache_key, $this->cache_group);
                $cache_loaded = is_array($cached_meta);
            }
        }

        $raw_meta_data = $cache_loaded ? $this->data_store->filter_raw_meta_data($this, $cached_meta) :
            $this->data_store->read_meta($this);

        if (is_array($raw_meta_data)) {
            $this->init_meta_data($raw_meta_data);
            if (!$cache_loaded && !empty($this->cache_group)) {
                wp_cache_set(
                    $cache_key,
                    $raw_meta_data
                    ,
                    $this->cache_group
                );
            }
        }
    }

    public function init_meta_data(
        array $filtered_meta_data = array()
    ) {
        $this->meta_data = array();
        foreach ($filtered_meta_data as $meta) {
            $this->meta_data[] = new LC_Meta_Data(
                array(
                    'id' => (int) $meta->meta_id,
                    'key' => $meta->meta_key,
                    'value' => maybe_unserialize($meta->meta_value)
                )
            );
        }
    }

    public function save_meta_data()
    {
        if (!$this->data_store || is_null($this->meta_data)) {
            return;
        }
        foreach ($this->meta_data as $array_key => $meta) {
            if (is_null($meta->value)) {
                if (!empty($meta->id)) {
                    $this->data_store->delete_meta(
                        $this,
                        $meta
                    );
                    do_action("deleted_{$this->object_type}_meta", $meta->id, $this->get_id(), $meta->key, $meta->value);

                    unset($this->meta_data[$array_key]);
                }
            } elseif (empty($meta->id)) {
                $meta->id = $this->data_store->add_meta($this, $meta);
                do_action("added_{$this->object_type}_meta", $meta->id, $this->get_id(), $meta->key, $meta->value);
                $meta->apply_changes();
            } else {
                if ($meta->get_changes()) {
                    $this->data_store->update_meta($this, $meta);
                    do_action("updated_{$this->object_type}_meta", $meta->id, $this->get_id(), $meta->key, $meta->value);
                    $meta->apply_changes();
                }
            }
        }

        if (!empty($this->cache_group)) {
            $cache_key = self::generate_meta_cache_key(
                $this->get_id(),
                $this->cache_group
            );
            wp_cache_delete($cache_key, $this->cache_group);
        }
    }

    public function get_meta_cache_key()
    {
        if (!$this->get_id()) {
            lc_doing_it_wrong(
                'get_meta_cache_key',
                'Id needs to be set before fetching a cache key.',
                '1.1.0'
            );
            return false;
        }
        return self::generate_meta_cache_key(
            $this->get_id(),
            $this->cache_group
        );
    }

    public static function generate_meta_cache_key($id, $cache_group)
    {
        return
            LC_Cache_Helper::get_cache_prefix($cache_group) . LC_Cache_Helper::get_cache_prefix('object_' . $id) . 'object_meta_' . $id;
    }

    public static function prime_raw_meta_data_cache(
        $raw_meta_data_collection,
        $cache_group
    ) {
        foreach ($raw_meta_data_collection as $object_id => $raw_meta_data_array) {
            $cache_key = self::generate_meta_cache_key(
                $object_id,
                $cache_group
            );
            wp_cache_set($cache_key, $raw_meta_data_array, $cache_group);
        }
    }

    public function set_id($id)
    {
        $this->id = absint($id);
    }

    public function set_defaults()
    {
        $this->data = $this->default_data;
        $this->changes = array();
        $this->set_object_read(false);
    }

    public function set_object_read($read = true)
    {
        $this->object_read = (bool) $read;
    }

    public function set_props($props, $context = 'set')
    {
        $errors = false;
        foreach ($props as $prop => $value) {
            try {
                if (
                    is_null($value) || in_array(
                        $prop,
                        array('prop', 'date_prop', 'meta_data'),
                        true
                    )
                ) {
                    continue;
                }
                $setter = "set_$prop";

                if (is_callable(array($this, $setter))) {
                    $this->{$setter}($value);
                }
            } catch (LC_Data_Exception $e) {
                if (!$erros) {
                    $errors = new WP_Error();
                }
                $errors->add(
                    $e->getErrorCode(),
                    $e->getMessage(),
                    array('property_name' => $prop)
                );
            }
        }

        return $errors && count($errors->get_error_codes()) ? $errors : true;
    }

    protected function set_prop($prop, $value)
    {
        if (array_key_exists($prop, $this->data)) {
            if (true === $this->object_read) {
                if ($value !== $this->data[$prop] || array_key_exists($prop, $this->changes)) {
                    $this->changes[$prop] = $value;
                }
            } else {
                $this->data[$prop] = $value;
            }
        }
    }

    public function get_changes()
    {
        return $this->changes;
    }

    public function apply_changes()
    {
        $this->data = array_replace_recursive(
            $this->data,
            $this->changes
        );
        // @codingStandardsIgnoreLine
        $this->changes = array();
    }

    protected function get_hook_prefix()
    {
        return 'litecommerce_' . $this->object_type . '_get_';
    }

    protected function get_prop($prop, $context = 'view')
    {
        $value = null;
        if (array_key_exists($prop, $this->data)) {
            $value = array_key_exists($prop, $this->changes) ? $this->changes[$prop] : $this->data[$prop];
            if ('view' === $context) {
                $value = apply_filters(
                    $this->get_hook_prefix() . $prop,
                    $value,
                    $this
                );
            }
        }

        return $value;
    }

    protected function set_date_prop($prop, $value)
    {
        try {
            if (empty($value) || '0000-00-00 00:00:00' === $value) {
                $this->set_prop($prop, null);
                return;
            }
            if (is_a($value, 'LC_DateTime')) {
                $datetime = $value;
            } elseif (is_numeric($value)) {
                $datetime = new LC_DateTime("
                @{$value}", new DateTimeZone('UTC'));
            } else {
                if (1 === preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|((-|\+)\d{2}:\d{2}))$/', $value, $date_bits)) {
                    $offset = !empty($date_bits[7]) ? iso8601_timezone_to_offset($date_bits[7]) : wc_timezone_offset();
                    $timestamp = gmmktime($date_bits[4], $date_bits[5], $date_bits[6], $date_bits[2], $date_bits[3], $date_bits[1]) - $offset;
                } else {
                    $timestamp = wc_string_to_timestamp(get_gmt_from_date(gmdate('Y-m-d H:i:s', wc_string_to_timestamp($value))));
                }
                $date_time = new LC_DateTime("@{$timestamp}", new DateTimeZone('UTC'));
            }

            if (get_option('timezone_string')) {
                $datetime->setTimezone(new DateTimeZone(lc_timezone_string()));
            } else {
                $datetime->set_utc_offset(
                    lc_timezone_offset()
                );
            }
        } catch (Exception $e) {
        }
    }

    protected function error($code, $message, $http_status_code = 400, $data = array())
    {
        throw new LC_Data_Exception($code, $message, $http_status_code, $data);
    }
}