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

    public function get_tooltip_html($data)
    {
        if (true === $data['desc_tip']) {
            $tip = $data['description'];
        } elseif (!empty($data['desc_tip'])) {
            $tip = $data['desc_tip'];
        } else {
            $tip = '';
        }

        return $tip ? lc_help_tip($tip, true) : '';
    }

    public function get_description_html($data)
    {
        if (true === $data['desc_tip']) {
            $description = '';
        } elseif (!empty($data['desc_tip'])) {
            $description = $data['description'];
        } elseif (!empty($data['description'])) {
            $description = $data['description'];
        } else {
            $description = '';
        }

        return $description ? '<p class="description">' . wp_kses_post($description) . '</p>' . "\n" : '';
    }

    public function get_custom_attribute_html($data)
    {
        $custom_attributes = array();

        if (!empty($data['custom_attributes']) && is_array($data['custom_attributes'])) {
            foreach ($data['custom_attributes'] as $attribute => $attribute_value) {
                $custom_attributes[] = esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
            }
        }

        return implode(' ', $custom_attributes);
    }

    public function generate_text_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
        );
        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr>
            <th>
                <label>
                    <?php echo wp_kses_post($data['title']); ?>
                </label>
            </th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><span>
                            <?php echo wp_kses_post($data['title']); ?>
                        </span></legend>
                    <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>"
                        type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>"
                        id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>"
                        value="<?php echo esc_attr($this->get_option($key)); ?>"
                        placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?>
                        <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?> />
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        <tr>
            <?php

            return ob_get_clean();
    }

    public function generate_safe_text_html($key, $data)
    {
        $data['type'] = 'text';
        return $this->generate_text_html($key, $data);
    }

    public function generate_price_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>">
                    <?php echo wp_kses_post($data['title']); ?>
                    <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>
                            <?php echo wp_kses_post($data['title']); ?>
                        </span></legend>
                    <input class="wc_input_price input-text regular-input <?php echo esc_attr($data['class']); ?>" type="text"
                        name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>"
                        style="<?php echo esc_attr($data['css']); ?>"
                        value="<?php echo esc_attr(wc_format_localized_price($this->get_option($key))); ?>"
                        placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?>
                        <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?> />
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public function generate_decimal_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>">
                    <?php echo wp_kses_post($data['title']); ?>
                    <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>
                            <?php echo wp_kses_post($data['title']); ?>
                        </span></legend>
                    <input class="wc_input_decimal input-text regular-input <?php echo esc_attr($data['class']); ?>" type="text"
                        name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>"
                        style="<?php echo esc_attr($data['css']); ?>"
                        value="<?php echo esc_attr(wc_format_localized_decimal($this->get_option($key))); ?>"
                        placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?>
                        <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?> />
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public function generate_password_html($key, $data)
    {
        $data['type'] = 'password';
        return $this->generate_text_html($key, $data);
    }

    public function generate_color_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>">
                    <?php echo wp_kses_post($data['title']); ?>
                    <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>
                            <?php echo wp_kses_post($data['title']); ?>
                        </span></legend>
                    <span class="colorpickpreview"
                        style="background:<?php echo esc_attr($this->get_option($key)); ?>;">&nbsp;</span>
                    <input class="colorpick <?php echo esc_attr($data['class']); ?>" type="text"
                        name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>"
                        style="<?php echo esc_attr($data['css']); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>"
                        placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?>
                        <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?> />
                    <div id="colorPickerDiv_<?php echo esc_attr($field_key); ?>" class="colorpickdiv"
                        style="z-index: 100; background: #eee; border: 1px solid #ccc; position: absolute; display: none;">
                    </div>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }


    public function generate_textarea_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>">
                    <?php echo wp_kses_post($data['title']); ?>
                    <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>
                            <?php echo wp_kses_post($data['title']); ?>
                        </span></legend>
                    <textarea rows="3" cols="20" class="input-text wide-input <?php echo esc_attr($data['class']); ?>"
                        type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>"
                        id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>"
                        placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?>
                        <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?>><?php echo esc_textarea($this->get_option($key)); ?></textarea>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public function generate_checkbox_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'label' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        if (!$data['label']) {
            $data['label'] = $data['title'];
        }

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>">
                    <?php echo wp_kses_post($data['title']); ?>
                    <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>
                            <?php echo wp_kses_post($data['title']); ?>
                        </span></legend>
                    <label for="<?php echo esc_attr($field_key); ?>">
                        <input <?php disabled($data['disabled'], true); ?> class="<?php echo esc_attr($data['class']); ?>"
                            type="checkbox" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>"
                            style="<?php echo esc_attr($data['css']); ?>" value="1" <?php checked($this->get_option($key), 'yes'); ?>         <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?> />
                        <?php echo wp_kses_post($data['label']); ?>
                    </label><br />
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public function generate_multiselect_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
            'select_buttons' => false,
            'options' => array(),
        );

        $data = wp_parse_args($data, $defaults);
        $value = (array) $this->get_option($key, array());

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>">
                    <?php echo wp_kses_post($data['title']); ?>
                    <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>
                            <?php echo wp_kses_post($data['title']); ?>
                        </span></legend>
                    <select multiple="multiple" class="multiselect <?php echo esc_attr($data['class']); ?>"
                        name="<?php echo esc_attr($field_key); ?>[]" id="<?php echo esc_attr($field_key); ?>"
                        style="<?php echo esc_attr($data['css']); ?>" <?php disabled($data['disabled'], true); ?>         <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?>>
                        <?php foreach ((array) $data['options'] as $option_key => $option_value): ?>
                            <?php if (is_array($option_value)): ?>
                                <optgroup label="<?php echo esc_attr($option_key); ?>">
                                    <?php foreach ($option_value as $option_key_inner => $option_value_inner): ?>
                                        <option value="<?php echo esc_attr($option_key_inner); ?>" <?php selected(in_array((string) $option_key_inner, $value, true), true); ?>>
                                            <?php echo esc_html($option_value_inner); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php else: ?>
                                <option value="<?php echo esc_attr($option_key); ?>" <?php selected(in_array((string) $option_key, $value, true), true); ?>>
                                    <?php echo esc_html($option_value); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok. ?>
                    <?php if ($data['select_buttons']): ?>
                        <br /><a class="select_all button" href="#">
                            <?php esc_html_e('Select all', 'woocommerce'); ?>
                        </a> <a class="select_none button" href="#">
                            <?php esc_html_e('Select none', 'woocommerce'); ?>
                        </a>
                    <?php endif; ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public function generate_title_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'class' => '',
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        </table>
        <h3 class="wc-settings-sub-title <?php echo esc_attr($data['class']); ?>" id="<?php echo esc_attr($field_key); ?>">
            <?php echo wp_kses_post($data['title']); ?>
        </h3>
        <?php if (!empty($data['description'])): ?>
            <p>
                <?php echo wp_kses_post($data['description']); ?>
            </p>
        <?php endif; ?>
        <table class="form-table">
            <?php

            return ob_get_clean();
    }

    public function validate_text_field($key, $value)
    {
        $value = is_null($value) ? '' : $value;
        return wp_kses_post(trim(stripslashes($value)));
    }

    public function format_settings($value)
    {
        wc_deprecated_function('format_settings', '2.6');
        return $value;
    }

    public function validate_settings_fields($form_fields = array())
    {
        wc_deprecated_function('validate_settings_fields', '2.6');
    }

    public function validate_multiselect_field($key, $value)
    {
        return is_array($value) ? array_map('wc_clean', array_map('stripslashes', $value)) : '';
    }

    public function validate_select_field($key, $value)
    {
        $value = is_null($value) ? '' : $value;
        return wc_clean(stripslashes($value));
    }

    public function validate_checkbox_field($key, $value)
    {
        return !is_null($value) ? 'yes' : 'no';
    }

    public function validate_textarea_field($key, $value)
    {
        $value = is_null($value) ? '' : $value;
        return wp_kses(
            trim(stripslashes($value)),
            array_merge(
                array(
                    'iframe' => array(
                        'src' => true,
                        'style' => true,
                        'id' => true,
                        'class' => true,
                    ),
                ),
                wp_kses_allowed_html('post')
            )
        );
    }

    public function validate_password_field($key, $value)
    {
        $value = is_null($value) ? '' : $value;
        return trim(stripslashes($value));
    }

    public function validate_decimal_field($key, $value)
    {
        $value = is_null($value) ? '' : $value;
        return ('' === $value) ? '' : wc_format_decimal(trim(stripslashes($value)));
    }

    public function validate_price_field($key, $value)
    {
        $value = is_null($value) ? '' : $value;
        return ('' === $value) ? '' : wc_format_decimal(trim(stripslashes($value)));
    }

    public function validate_safe_text_field(string $key, ?string $value): string
    {
        return wc_get_container()->get(HtmlSanitizer::class)->sanitize((string) $value, HtmlSanitizer::LOW_HTML_BALANCED_TAGS_NO_LINKS);
    }

    public function validate_text_field($key, $value)
    {
        $value = is_null($value) ? '' : $value;
        return wp_kses_post(trim(stripslashes($value)));
    }

}

