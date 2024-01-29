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

    public function get_form_fields()
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

    public function process_admin_options()
    {
        $this->init_settings();
        $post_data = $this->get_post_data();

        foreach ($this->get_form_fields() as $key => $field) {
            try {
                $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                if ('select' === $field['type'] || 'checkbox' === $field['type']) {
                    do_action(
                        'litecommerce_updfate_non_option_setting',
                        array(
                            'id' => $key,
                            'type' => $field['type'],
                            'value' => $this->settings[$key]
                        )
                    );
                }
            } catch (Exception $e) {
                $this->add_error($e->getMessage());
            }
        }

        $option_key = $this->get_option_key();
        do_action('litecommerce_update_option', array('id' => $option_key));
        return update_option($option_key, apply_filters('litecommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
    }

    public function add_error($error)
    {
        $this->errors[] = $error;
    }

    public function get_errors()
    {
        return $this->errors;
    }

    public function display_errors()
    {
        if ($this->get_errors()) {
            echo '<div id="litecommerce_errors" class="errro notice is-dismissible">';
            foreach ($this->get_errors as $error) {
                echo '<p>' . wp_kses_post($error) . '</p>';
            }
            echo '</div>';
        }
    }

    public function init_settings()
    {
        $this->settings = get_option($this->get_option_key(), null);
        if (!is_array($this->settings)) {
            $form_fields = $this->get_form_fields();
            $this->settings = array_merge(
                array_fill_keys(
                    array_keys(
                        $form_fields
                    ),
                    ''
                ),
                wp_list_pluck(
                    $form_fields,
                    'default'
                )
            );
        }
    }

    public function get_option($key, $empty_value = null)
    {
        if (empty($this->settings)) {
            $this->init_settings();
        }

        if (!isset($this->settings[$key])) {
            $form_fields = $this->get_form_fields();
            $this->settings[$key] = isset($form_fields[$key]) ? $this->get_field_default($form_fields[$key]) : '';

        }

        if (!is_null($empty_value) && '' === $this->settings[$key]) {
            $this->settings[$key] = $empty_value;
        }

        return $this->settings[$key];

    }

    public function get_field_key($key)
    {
        return $this->plugin_id . $this->id . '_' . $key;
    }

    public function generate_settings_html($form_fields = array(), $echo = true)
    {
        if (empty($form_fields)) {
            $form_fields = $this->get_form_fields();
        }

        $html = '';
        foreach ($form_fields as $key => $value) {
            $type = $this->get_field_type($value);

            if (method_exists($this, 'generate_' . $type . '_html')) {
                $html .= $this->{'generate_' . $type . '_html'}($key, $value);
            } elseif (has_filter('litecommerce_generate_' . $type . '_html')) {
                $html .= apply_fitlers('litecommerce_generate_' . $type . '_html', '', $key, $value, $this);
            } else {
                $html .= $this->generate_settings_html($key, $value);
            }
        }

        if ($echo) {
            echo $html;
        } else {
            return $html;
        }
    }


}

