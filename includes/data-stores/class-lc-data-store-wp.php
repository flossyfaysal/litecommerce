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
                $key_mapping = array(
                    'parent' => 'post_parent',
                    'parent_exclude' => 'post_parent__not_in',
                    'exclude' => 'post__not_in',
                    'limit' => 'posts_per_page',
                    'type' => 'post_type',
                    'return' => 'fields',
                );

                if (isset($key_mapping[$key])) {
                    $wp_query_args[$key_mapping[$key]] = $value;
                } else {
                    $wp_query_args[$key] = $value;
                }
            }
        }
        return apply_filters('litecommerce_get_wp_query_args', $wp_query_args, $query_vars);
    }

    public function parse_date_for_wp_query($query_var, $key, $wp_query_args = array())
    {
        $query_parse_regex = '/([^.<>]*)(>=|<=|>|<|\.\.\.)([^.<>]+)/';
        $valid_operators = array('>', '>=', '=', '<=', '<', '...');
        $precision = 'second';
        $dates = array();
        $operator = '=';

        try {
            if (is_a($query_var, 'LC_DateTime')) {
                $dates[] = $query_var;
            } elseif (is_numeric($query_var)) {
                $dates[] = new LC_DateTime("@{$query_var}", new DateTimeZone('UTC'));
            } elseif (preg_match($query_parse_regex, $query_var, $sections)) {
                if (!empty($sections[1])) {
                    $dates[] = is_numeric($sections[1]) ? new WC_DateTime("@{$sections[1]}", new DateTimeZone('UTC')) : lc_string_to_datetime($sections[1]);
                }
                $operator = in_array($sections[2], $valid_operators, true) ? $sections[2] : '';
                $dates[] = is_numeric($sections[3]) ? new WC_DateTime("@{$sections[3]}", new DateTimeZone('UTC')) : lc_string_to_datetime($sections[3]);

                if (!is_numeric($sections[1]) && !is_numeric($sections[3])) {
                    $precision = 'day';
                }
            } else {
                $dates[] = lc_string_to_datetime($query_var);
                $precision = 'day';
            }
        } catch (Exception $e) {
            return $wp_query_args;
        }

        if (!$operator || empty($dates) || ('...' === $operator && count($dates) < 2)) {
            return $wp_query_args;
        }

        if ('post_date' === $key || 'post_modified' === $key) {
            if (!isset($wp_query_args['date_query'])) {
                $wp_query_args['date_query'] = array();
            }

            $query_arg = array(
                'column' => 'day' === $precision ? $key : $key . '_gmt',
                'inclusive' => '>' !== $operator && '<' !== $operator,
            );

            // Add 'before'/'after' query args.
            $comparisons = array();
            if ('>' === $operator || '>=' === $operator || '...' === $operator) {
                $comparisons[] = 'after';
            }
            if ('<' === $operator || '<=' === $operator || '...' === $operator) {
                $comparisons[] = 'before';
            }

            foreach ($comparisons as $index => $comparison) {
                if ('day' === $precision) {
                    $query_arg[$comparison]['year'] = $dates[$index]->date('Y');
                    $query_arg[$comparison]['month'] = $dates[$index]->date('n');
                    $query_arg[$comparison]['day'] = $dates[$index]->date('j');
                } else {
                    $query_arg[$comparison] = gmdate('m/d/Y H:i:s', $dates[$index]->getTimestamp());
                }
            }

            if (empty($comparisons)) {
                $query_arg['year'] = $dates[0]->date('Y');
                $query_arg['month'] = $dates[0]->date('n');
                $query_arg['day'] = $dates[0]->date('j');
                if ('second' === $precision) {
                    $query_arg['hour'] = $dates[0]->date('H');
                    $query_arg['minute'] = $dates[0]->date('i');
                    $query_arg['second'] = $dates[0]->date('s');
                }
            }
            $wp_query_args['date_query'][] = $query_arg;
            return $wp_query_args;
        }

        if (!isset($wp_query_args['meta_query'])) {
            $wp_query_args['meta_query'] = array();
        }

        if ('day' === $precision) {
            $start_timestamp = strtotime(gmdate('m/d/Y 00:00:00', $dates[0]->getTimestamp()));
            $end_timestamp = '...' !== $operator ? ($start_timestamp + DAY_IN_SECONDS) : strtotime(gmdate('m/d/Y 00:00:00', $dates[1]->getTimestamp()));
            switch ($operator) {
                case '>':
                case '<=':
                    $wp_query_args['meta_query'][] = array(
                        'key' => $key,
                        'value' => $end_timestamp,
                        'compare' => $operator,
                    );
                    break;
                case '<':
                case '>=':
                    $wp_query_args['meta_query'][] = array(
                        'key' => $key,
                        'value' => $start_timestamp,
                        'compare' => $operator,
                    );
                    break;
                default:
                    $wp_query_args['meta_query'][] = array(
                        'key' => $key,
                        'value' => $start_timestamp,
                        'compare' => '>=',
                    );
                    $wp_query_args['meta_query'][] = array(
                        'key' => $key,
                        'value' => $end_timestamp,
                        'compare' => '<=',
                    );
            }
        } else {
            if ('...' !== $operator) {
                $wp_query_args['meta_query'][] = array(
                    'key' => $key,
                    'value' => $dates[0]->getTimestamp(),
                    'compare' => $operator,
                );
            } else {
                $wp_query_args['meta_query'][] = array(
                    'key' => $key,
                    'value' => $dates[0]->getTimestamp(),
                    'compare' => '>=',
                );
                $wp_query_args['meta_query'][] = array(
                    'key' => $key,
                    'value' => $dates[1]->getTimestamp(),
                    'compare' => '<=',
                );
            }
        }

        return $wp_query_args;
    }

    public function get_internal_meta_keys()
    {
        return $this->internal_meta_keys;
    }

    protected function get_valid_search_terms($terms)
    {
        $valid_terms = array();
        $stopwords = $this->get_search_stopwords();

        foreach ($terms as $term) {
            if (preg_match('/^".+"$/', $term)) {
                $term = trim($term, "\"'");
            } else {
                $term = trim($term, "\"' ");
            }

            if (empty($term) || (1 == strlen($term) && preg_match('/^[a-z\-]/i', $term))) {
                continue;
            }

            if (in_array(lc_strtolower($term), $stopwords, true)) {
                continue;
            }

            $valid_terms[] = $term;
        }

        return $valid_terms;
    }

    protected function get_search_stopwords()
    {
        $stopwords = array_map(
            'lc_strtolower',
            array_map(
                'trmi',
                explode(
                    ',',
                    _x(
                        'about,an,are,as,at,be,by,com,for,from,how,in,is,it,on,or,what,who,will,when,with,www.',
                        'Comma-sepearted list of search stopwords in your language',
                        'litecommerce'
                    )
                )
            )
        );

        return apply_filters(
            'wp_search_stopwords',
            $stopwords
        );
    }

    protected function get_data_for_lookup_table($id, $table)
    {
        return array();
    }

    protected function get_primary_key_for_lookup_table($table)
    {
        return '';
    }

    protected function update_lookup_table($id, $table)
    {
        global $wpdb;
        $id = absint($id);
        $table = sanitize_key($table);

        if (empty($id) || empty($table)) {
            return false;
        }

        $existing_data = wp_cache_get('lookup_table', 'object_' . $id);
        $update_data = $this->get_data_for_lookup_table(
            $id,
            $table
        );

        if (!empty($update_data) && $update_data !== $existing_data) {
            $wpdb->replace(
                $wpdb->$table,
                $update_data
            );
            wp_cache_set('lookup_table', $update_data, 'object_' . $id);
        }
    }






}

