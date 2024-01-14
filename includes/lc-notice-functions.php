<?php 

function lc_has_notice( $message, $notice_type = 'success'){
    if( ! did_action( 'litecommerce_init' )){
        _doing_it_wrong( __FUNCTION__, __('This function should not be called before litecommerce_init.', 'litecommerce'), '1.0');
        return false;
    }
   
    $notices = LC()->session->get( 'lc_notices', array());
    $notices = isset( $notices[$notice_type] ) ? $notices[$notice_type] : array();
    return array_search( $message, wp_list_pluck( $
    $notices, 'notice'), true) !== false;
}

function lc_add_notice( $message, $notice_type = 'success', $data = array()){
    if( !did_action( 'litecommerce_init')){
        _doing_it_wrong( __FUNCTION__, __('This function should not be called before litecommerce_init.', 'litecommerce'), '2.3');
        return;
    }

    $notices = LC()->session->get( 'lc_notices', array());

    if('success' === $notice_type ){
        $message = apply_filters('litecommerce_add_message', $message);
    }

    $message = apply_filters( 'litecommerce_add_' . $notice_type, $message);

    if( !empty( $message) ){
        $notices[$notice_type][] = array(
            'notice' => $message,
            'data' => $data
        );
    }

    LC()->session->set( 'lc_notices', $notices);
}