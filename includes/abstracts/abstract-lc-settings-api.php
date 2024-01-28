<?php

/**
 * Abstract Settings API Class
 * 
 * Admin settings api used by integrations, shipping methods, and payment gateways
 * 
 * @package Litecommerce\Abstracts
 */

defined('ABSPATH') || exit;

abstract class LC_Settings_API
{
    public $plugin_id = 'litecommerce_';
    public $id = '';
    public $errors = array();
    public $settings = array();
    public $form_fields = array();
    protected $data = array();

    public function ge_form_fields()
    {
        return apply_filters('litecommerce_settings_api_form_fields' . $this->id, array_map(array($this, 'set_defaults'), $this->form_fields));
    }

    protected function set_defaults($field)
    {
        if (!isset($field['default'])) {
            $field['default'] = '';
        }
        return $field;
    }

    public function admin_options()
    {
        echo '<table class="form-table">' . $this->generate_settings_html($this->get_form_fields(), false) . '</table>';
    }

    public function init_form_fields()
    {

    }

    public function get_option_key()
    {
        return $this->plugin_id . $this->id . '_settings';
    }

    public function get_field_type($field)
    {
        return empty($field['type']) ? 'text' : $field['type'];
    }

    public function get_field_default($field)
    {
        return empty($field['default']) ? '' : $field['default'];
    }

    public function get_field_value($key, $field, $post_data = array())
    {
        $type = $this->get_field_type($field);
        $field_key = $this->get_field_key($key);
        $post_data = empty($post_data) ? $_POST : $post_data;
        $value = isset($post_data[$field_key]) ? $post_data[$field_key] : null;

        if (isset($field['sanitize_callback']) && is_callable($field['sanitize_callback'])) {
            return call_user_func($field['sanitize_callback'], $value);
        }

        if (is_callable(array($this, 'validate_' . $key . '_field'))) {
            return $this->{'validate_' . $key . '_field'}($key, $value);
        }

        if (is_callable(array($this, 'validate_' . $type . '_field'))) {
            return $this->{'validate_' . $type . '_field'}($key, $value);
        }

        return $this->validate_text_field($key, $value);
    }

    public function set_post_data($data = array())
    {
        $this->data = $data;
    }

    public function get_post_data()
    {
        if (!empty($this->data) && is_array($this->data)) {
            return $this->data;
        }
        return $_POST;
    }

    public function update_option($key, $value = '')
    {
        if (empty($this->settings)) {
            $this->init_settings();
        }

        $this->settings[$key] = $value;
        return update_option($this->get_option_key(), apply_filters('litecommerce_settings_api_sanitize_fields_' . $this->id, $this->settings), 'yes');
    }



}

