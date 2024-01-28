<?php

class LC_Admin
{

    public function __construct()
    {
        add_action('init', array($this, 'includes'));
        add_action('current_screen', array($this, 'conditional_includes'));
        add_action('admin_init', array($this, 'buffer'), 1);
        add_action('admin_init', array($this, 'preview_emails'));
        add_action('admin_init', array($this, 'prevent_admin_access'));
        add_action('admin_init', array($this, 'admin_redirects'));
        add_action('admin_footer', 'lc_print_js', 25);
        add_filter('admin_footer_text', array($this, 'admin_footer_text'), 1);
        add_filter('action_scheduler_post_type_args', array($this, 'disable_webhook_post_export'));
        add_filter('admin_body_class', array($this, 'include_admin_body_class'), 9999);

        if (isset($_GET['page']) && 'lc-addons' === $_GET['page']) {
            add_filter('admin_body_class', array($this, 'LC_Admin_Addons', 'filter_admin_body_classes'));
        }

    }

    public function preview_emails()
    {

    }

    public function includes()
    {

    }

    public function conditional_includes()
    {

    }

    public function buffer()
    {
        ob_start();
    }
}

return new LC_Admin();