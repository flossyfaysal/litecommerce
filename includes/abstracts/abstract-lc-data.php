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

 if( !defined( 'ABSPATH' )){
    exit; 
 }

 /** 
  * Abstract LC Data Class
  * Implemented by classes using the same CRUD(s)
  */


abstract class LC_Data{

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

    public function __construct( $read = 0 ){
        $this->data = array_merge( $this->data, $this->extra_data);
        $this->default_data = $this->data;
    }

    public function __sleep(){
        return array('id');
    }

    public function __wakeup(){
        try{
            $this->__construct( absint( $this->id));
        }catch( Exception $e){
            $this->set_id(0);
            $this->set_object_read(true);
        }
    }

    public function __clone(){
        $this->maybe_read_meta_data();
        if( ! empty( $this->meta_data )){
            foreach( $this->meta_data as $array_key => $meta){
                $this->meta_data[ $array_key] = clone $meta;
                if( !empty( $meta->id)){
                    $this->meta_data[$array_key]->id = null;
                }
            }
        }
    }

    public function get_data_store(){
        return $this->data_store;
    }

    public function get_id(){
        return $this->id; 
    }

    public function delete( $force_delete = false){
        $check = apply_filters(
            "litecommerce_pre_delete_$this->object_type", 
            null, $this, $force_delete
        );
        if( null !== $check){
            return $check;
        }
        if( $this->data_store){
            $this->data_store->delete($this, array(
                'force_delete' => $force_delete
            ));
            $this->set_id(0);
            return true; 
        }
        return false;
    }

    public function save(){
        if( ! $this->data_store ){
            return $this->get_id();
        } 

        do_action( 'litecommerce_before_' . $this->object_type . '_object_save', $this->data_store);

        if( $this->get_id()){
            $this->data_store->update( $this);
            $this->data_store->create( $this);
        }

        do_action( 'litecommerce_after_' . $this->object_type . '_object_save', $this, $this->data_store );

        return $this->get_id();
    }

    public function __toString(){
        return wp_json_encode( $this->get_data());
    }

    public function get_data(){
        return array_merge( array( 'id' => $this->get_id()), $this->data, array( 'meta_data' => $this->get_meta_data()));
    }

    public function get_meta_data(){
        $this->maybe_read_meta_data();
    }


    public function maybe_read_meta_data(){
        if( is_null( $this->meta_data)){
            $this->read_meta_data();
        }
    }

    public function read_meta_data( $force_read = false){
        $this->meta_data = array();
        $cache_loaded = false;

        if( !$this->get_id()){
            return;
        }

        if( !$this->data_store){
            return; 
        }

        if( !empty( $this->cache_group)){
            $cache_key = $this->get_meta_cache_key();

        }

        if( ! $force_read ){
            if( !empty( $this->cache_group)){
                $cached_meta = wp_cache_get( $cache_key, $this->cache_group);
                $cache_loaded = is_array( $cached_meta );
            }
        }

        $raw_meta_data = $cache_loaded ? $this->data_store->filter_raw_meta_data($this, $cached_meta) : 
        $this->data_store->read_meta($this);

        if( is_array( $raw_meta_data)){
            $this->init_meta_data( $raw_meta_data );
            if( ! $cache_loaded && !empty($this->cache_group) ){
                wp_cache_set( $cache_key, $raw_meta_data
                , $this->cache_group);
            }
        }
    }

    public function init_meta_data(
        array $filtered_meta_data = array()
    ){
        $this->meta_data = array();
        foreach( $filtered_meta_data as $meta){
            $this->meta_data[] = new LC_Meta_Data(
                array(
                    'id' => (int) $meta->meta_id,
                    'key' => $meta->meta_key,
                    'value' => maybe_unserialize($meta->meta_value)
                )
            );
        }
    }
    
    public function get_meta_cache_key(){
        if( !$this->get_id()){
            lc_doing_it_wrong( 
                'get_meta_cache_key', 
                'Id needs to be set before fetching a cache key.',
                '1.1.0'
            );
            return false;
        }
        return self::generate_meta_cache_key(
            $this->get_id(), $this->cache_group
        )
    }

    public static generate_meta_cache_key(
        $id, $cache_group
    ){
        return 
        LC_Cache_Helper::get_cache_prefix($cache_group) . LC_Cache_Helper::get_cache_prefix('object_' . $id) . 'object_meta_' . $id;
    }

    public function set_id( $id){
        $this->id = absint( $id);
    }

    public function set_object_read( $read = true){
        $this->object_read = (bool) $read;
    }

}