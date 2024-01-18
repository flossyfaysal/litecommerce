<?php

/** 
 * Shared logic for WP based data. 
 * Contains functions like meta handling for all default data stores. 
 * Your own data store doesnt need to use LC_Data_Store_WP -- you can wirte
 * your own meta handling functions.
 * 
 * @version 1.1.0
 * @package LiteCommerce\Classes
 */

defined('ABSPATH') || exit;

/**
 * LC_Data_Store_WP class. 
 */

class LC_Data_Store_WP
{

    protected $meta_type = 'post';
    protected $object_id_field_for_meta;
    protected $internal_meta_keys;
    protected $must_exist_meta_keys;

    protected function get_term_ids($object, $taxonomy)
    {
        if (is_numeric($object)) {
            $object_id = $object;
        } else {
            $object_id = $object->get_id();
        }

        $terms = get_the_terms($object_id, $taxonomy);
        if (false === $terms || is_wp_error($terms)) {
            return array();
        }

        return wp_list_pluck($terms, 'term_id');
    }

    public function read_meta(&$object)
    {
        global $wpdb;
        $db_info = $this->get_db_info();
        $raw_meta_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$db_info['meta_id_field']} as meta_id, meta_key, meta_value
				FROM {$db_info['table']}
				WHERE {$db_info['object_id_field']} = %d
				ORDER BY {$db_info['meta_id_field']}",
                $object->get_id()
            )
        );

        return $this->filter_raw_meta_data($object, $raw_meta_data);
    }

    public function delete_meta(&$object, $meta)
    {
        delete_metadata_by_mid($this->meta_type, $meta->id);
    }

    public function add_meta(&$object, $meta)
    {
        return add_metadata($this->meta_type, $object->get_id(), wp_slash($meta->key), is_string($meta->value) ? wp_slash($meta->value) : $meta->value, false);
    }

    public function update_meta(&$object, $meta)
    {
        update_metadata_by_mid($this->meta_type, $meta->id, $meta->value, $meta->key);
    }
    public function filter_raw_meta_data(&$object, $raw_meta_data)
    {
        $this->internal_meta_keys = array_unique(
            array_merge(
                array_map(
                    array($this, 'prefix_key'),
                    $object->get_data_keys()
                ),
                $this->internal_meta_keys
            )
        );

        $meta_data = array_filter(
            $raw_meta_data,
            array(
                $this,
                'exclude_internal_meta_keys'
            )
        );

        return apply_filters("litecommerce_data_store_wp_{$this->meta_type}_read_meta", $meta_data, $object, $this);
    }

    protected function prefix_key($key)
    {
        return '_' === substr($key, 0, 1) ? $key : '_' . $key;
    }

    protected function exclude_internal_meta_keys($meta)
    {
        return !in_array($meta->meta_key, $this->internal_meta_keys, true) && 0 !== stripos($meta->meta_key, 'wp_');
    }

    protected function ge_db_info()
    {
        global $wpdb;

        $meta_id_field = 'meta_id';
        $table = $wpdb->prefix;

        if (!in_array($this->meta_type, array('post', 'user', 'comment', 'term'), true)) {
            $table .= 'litecommerce_';
        }

        $table .= $this->meta_type . 'meta';
        $table .= $this->meta_type . '_id';

        if ('user' === $this->meta_type) {
            $meta_id_field = 'umeta_id';
            $table = $wpdb->usermeta;
        }

        if (!empty($this->object_id_field_for_meta)) {
            $object_id_field = $this->object_id_field_for_meta;
        }

        return array(
            'table' => $table,
            'object_id_field' => $object_id_field,
            'meta_id_field' => $meta_id_field
        );
    }

    protected function get_props_to_update($object, $meta_key_to_props, $meta_type = 'post')
    {
        $props_to_update = array();
        $changed_props = $object->get_changes();

        foreach ($meta_key_to_props as $meta_key => $prop) {
            if (array_key_exists($prop, $changed_props) || !metadata_exists($meta_type, $object->get_id(), $meta_key)) {
                $props_to_update[$meta_key] = $prop;
            }
        }

        return $props_to_update;
    }

    protected function update_or_delete_post_meta($object, $meta_key, $meta_value)
    {
        if (in_array($meta_value, array(array(), ''), true) && !in_array($meta_key, $this->must_exist_meta_keys, true)) {
            $updated = delete_post_meta($object->get_id(), $meta_key);
        } else {
            $updated = update_post_meta($object->get_id(), $meta_key, $meta_value);
        }

        return (bool) $updated;
    }

    protected function get_wp_query_args($query_vars)
    {
        $skipped_values = array('', array(), null);
        $wp_query_args = array(
            'errors' => array(),
            'meta_query' => array()
        );

        foreach ($query_vars as $key => $value) {
            if (in_array($value, $skipped_values, true) || 'meta_query' === $key) {
                continue;
            }

            if (
                in_array(
                    '_' . $key,
                    $this->internal_meta_keys,
                    true
                )
            ) {
                if ('*' === $value) {
                    $wp_query_args['meta_query'][] = array(
                        array(
                            'key' => '_' . $key,
                            'compare' => 'EXISTS',
                        ),
                        array(
                            'key' => '_' . $key,
                            'value' => '',
                            'compare' => '!='
                        ),
                    );
                } else {
                    $wp_query_args['meta_query'][] = array(
                        'key' => '_' . $key,
                        'value' => $value,
                        'compare' => is_array($value) ? 'IN' : '='
                    );
                }
            } else {
                // lets do it later..
            }
        }
    }



}

