<?php

/**
 * Handle data for the current customers session
 * 
 * @class LC_Session
 * @version 1.1.0
 * @package Litecommerce\Abstracts
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class LC_Session
{
    protected $_customer_id;
    protected $_data = array();
    protected $_dirty = false;
    public function init()
    {
    }

    public function cleanup_sessions()
    {
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    public function __isset($key)
    {
        return isset($this->_data[sanitize_title($key)]);
    }

    public function __unset($key)
    {
        if (isset($this->_data[$key])) {
            unset($this->_data[$key]);
            $this->_dirty = true;
        }
    }

    public function get($key, $default = null)
    {
        $key = sanitize_key($key);
        return isset($this->_data[$key]) ? maybe_unserialize($this->_data[$key]) : $default;
    }

    public function set($key, $value)
    {
        if ($value !== $this->get($key)) {
            $this->_data[sanitize_key($key)] = maybe_serialize($value);
            $this->_dirty = true;
        }
    }

    public function get_customer_id()
    {
        return $this->_customer_id;
    }



}

