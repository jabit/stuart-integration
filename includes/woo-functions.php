<?php
// Para evitar llamadas directas
defined("ABSPATH") or exit();

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

require_once plugin_dir_path(__FILE__) . 'apiStuart.php';

global $apiStuart;
$apiStuart = new ApiStuart();

if(!empty($apiStuart->stuart_key) && !empty($apiStuart->stuart_secret)){
    add_action( 'woocommerce_new_order', 'stuart_woocommerce_new_order' );
}

// define the woocommerce_new_order callback
function stuart_woocommerce_new_order( $order_id ) {

    if ( !$order_id ) return;
    $order = wc_get_order( $order_id );

    $apiStuart = new ApiStuart();
    $resultValidation = $apiStuart->stuart_validation($order);

    if(!$resultValidation['success']){
        wc_add_notice($resultValidation['message'], 'error' );
        ob_start();
        wc_print_notices();
        $messages = ob_get_clean();
        echo json_encode(array('valid' => false, 'error' => $messages));
        exit;
    }

    $item_data_arr = null;
    foreach ( $order->get_items() as $item ) {
        $item_data_arr = $item->get_data();
    }

    $resultCreateJob = $apiStuart->stuart_create_job($order, $item_data_arr);

    if(!empty($resultCreateJob->error)){
        add_action( 'woocommerce_order_status_processing', 'update_order_state_to_failed', 1 );
        add_action( 'woocommerce_order_status_on-hold', 'update_order_state_to_failed', 1 );
        add_action( 'woocommerce_order_status_pending', 'update_order_state_to_failed', 1 );
        //$order->update_status( 'failed' );
        wc_add_notice( __("Stuart: ".json_decode($resultCreateJob->getBody())->message, 'stuart-integration'), 'error' );
        ob_start();
        wc_print_notices();
        $messages = ob_get_clean();
        echo json_encode(array('valid' => false, 'error' => $messages));
        exit;
    }else{
        add_action( 'woocommerce_order_status_processing', 'update_order_state_to_completed', 1 );
        add_action( 'woocommerce_order_status_on-hold', 'update_order_state_to_completed', 1 );
        add_action( 'woocommerce_order_status_pending', 'update_order_state_to_completed', 1 );
    }
};

function update_order_state_to_completed( $order_id ) {
    global $woocommerce;

    //ID's de las pasarelas de pago a las que afecta, te lo explico a continuación
    $paymentMethods = array( 'bacs', 'cheque', 'cod', 'stripe', 'paypal' );

    if ( !$order_id ) return;
    $order = new WC_Order( $order_id );

    if ( !in_array( $order->payment_method, $paymentMethods ) ) return;
    $order->update_status( 'completed' );
}

function update_order_state_to_failed( $order_id ) {
    global $woocommerce;

    //ID's de las pasarelas de pago a las que afecta, te lo explico a continuación
    $paymentMethods = array( 'bacs', 'cheque', 'cod', 'stripe', 'paypal' );

    if ( !$order_id ) return;
    $order = new WC_Order( $order_id );

    if ( !in_array( $order->payment_method, $paymentMethods ) ) return;
    $order->update_status( 'failed' );
}

/*
function create_stuart_job_when_payment_completed( $order_id ) {

    if ( !$order_id ) return;
    $order = wc_get_order( $order_id );

    $item_data_arr = null;
    foreach ( $order->get_items() as $item ) {
        $item_data_arr = $item->get_data();
    }

    //global $apiStuart;
    //$apiStuart = new ApiStuart();
    //$apiStuart->stuart_create_job($order, $item_data_arr);

    //stuart_create_job($order, $item_data_arr);
};
if(!empty($apiStuart->stuart_key) && !empty($apiStuart->stuart_secret)){
    // add the action
    add_action( 'woocommerce_order_status_completed', 'create_stuart_job_when_payment_completed' );
}
*/

//Session init
add_action( 'init', 'stuart_session_start', 1 );
add_action( 'wp_logout', 'stuart_session_end' );
add_action( 'wp_login', 'stuart_session_end' );

function stuart_session_start() {
    if( ! session_id() ) {
        session_start();
    }
}

function stuart_session_end() {
    session_destroy();
}

if(!empty($apiStuart->stuart_key) && !empty($apiStuart->stuart_secret) && !empty($apiStuart->here_key) ||
    !empty($apiStuart->stuart_key) && !empty($apiStuart->stuart_secret) && !empty($apiStuart->google_key)){
    add_action( 'woocommerce_cart_calculate_fees','stuart_add_shipping_fee' );
    add_action('woocommerce_checkout_update_order_review', 'stuart_add_shipping_fee');
}
function stuart_add_shipping_fee( ) {

    global $woocommerce;
    $closer = null;
    $addressTo = null;

    if ( !empty($woocommerce->customer->get_shipping_address_1()) ) {

        //global $apiStuart;
        $apiStuart = new ApiStuart();

        $addressTo = $apiStuart->stuart_get_address_to($woocommerce);

        if(!$addressTo['success']){
            wc_add_notice($addressTo['result'], 'error');
            return;
        }else{

            $closer = $apiStuart->calculate_closer_shop_from_drop_off($addressTo['result']);

            if(!$closer['success']){
                wc_add_notice( $closer['result'], 'error' );
                return;
            }

            $distance = $closer['result'];
            // Set here your shipping fee amount
            $fee = 2.5; // < 1500

            if ( is_admin() && ! defined( 'DOING_AJAX' ) )
                return;

            $difference = null;
            if($distance > 1500 && $distance < 3500){
                $fee = 2.65;
            }else if($distance >= 3500){
                $difference = $distance - 3500;
                if($difference > 1000){
                    $fee = 2.65 + round($difference/1000, 0, PHP_ROUND_HALF_UP);
                }
            }
            WC()->cart->add_fee( __('Shipping Fee', 'stuart-integration'), $fee, false );
        }
    }
}

//shipping filters
if(!empty($apiStuart->stuart_key) && !empty($apiStuart->stuart_secret)){
    add_filter( 'woocommerce_cart_shipping_method_full_label', 'stuart_wc_free_shipping_label', 10, 2 );
    add_filter( 'woocommerce_package_rates', 'stuart_hide_shipping_when_free_is_available', 100 );
}

function stuart_wc_free_shipping_label( $label, $method ) {

    if ( 0 == $method->cost ) {
        $label = __("Stuart", 'stuart-integration');
    }

    return $label;
}

function stuart_hide_shipping_when_free_is_available( $rates ) {
    $free = array();
    foreach ( $rates as $rate_id => $rate ) {
        if ( 'free_shipping' === $rate->method_id ) {
            $free[ $rate_id ] = $rate;
            break;
        }
    }
    return ! empty( $free ) ? $free : $rates;
}