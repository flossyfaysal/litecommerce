<?php

/**
 * Object data store interface
 * 
 * @version 1.1.0
 * @package LiteCommerce\Interface
 */

/**
 * LC Data Store Interface
 * 
 * @version 1.10
 */

interface LC_Object_Data_Store_Interface
{
    /**
     * Method to create a new record of a LC_Data based object
     * 
     * @param LC_Data $data Data object
     */
    public function create(&$data);

    /** 
     * Method to read a record. Creates a new LC_Data based object
     * 
     * @param LC_Data $data Data object
     */
    public function read(&$data);

    /**
     * Updates a record in the database 
     * 
     * @param LC_Data $data Data Object
     */
    public function update(&$data);

    /**
     * Deletes a record from the database
     * 
     * @param LC_Data $data Data object
     * @return array $args Array of args to passs to the delete method.
     * @return bool result
     */
    public function delete(&$data, $args = array());

    /**
     * Returns an array of meta for an object 
     * 
     * @param LC_Data $data Data object 
     * @return array
     */
    public function read_meta(&$data);

    /**
     * Deletes meta based on meta ID
     * 
     * @param LC_Data $data Data object. 
     * @param object $meta Meta object (containing at list ->id)
     * @return array
     */
    public function delete_meta(&$data, $meta);

    /**
     * Add new piece of meta
     * 
     * @param LC_Data $data Data object.
     * @param object $meta Meta object (Containing ->id, ->key and ->value). 
     * 
     */
    public function update_meta(&$data, $meta);

}