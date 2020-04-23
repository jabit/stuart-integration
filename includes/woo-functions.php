<?php
// Para evitar llamadas directas
defined("ABSPATH") or exit();

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

if (empty(get_option('stuart_api_key')) && empty(get_option('stuart_secret_key'))) {
    return;
}

require_once plugin_dir_path(__FILE__) . 'apiStuart.php';

//Session init
add_action( 'init', 'stuart_session_start', 1 );
add_action( 'wp_logout', 'stuart_session_end' );
add_action( 'wp_login', 'stuart_session_end' );

function stuart_session_start() {
    if( !headers_sent() && '' == session_id() ) {
        session_start();
    }
}

function stuart_session_end() {
    session_destroy();
}

global $apiStuart;

$apiStuart = new ApiStuart();

function stuart_woocommerce_new_order( $order_id ) {

    if ( !$order_id ) return;
    $_SESSION['pickup_closer'] = null;

    $apiStuart = new ApiStuart();

    $resultValidation = $apiStuart->stuart_validation($order_id);

    if(!$resultValidation['success']){
        wc_add_notice($resultValidation['message'], 'notice' );
        ob_start();
        wc_print_notices();
        $messages = ob_get_clean();
        echo json_encode(array('valid' => false, 'error' => $messages));
        exit;
    }
    $_SESSION['pickup_closer'] = $resultValidation['closer'] ;

    //stuart_woocommerce_new_order_completed($order_id);
    //exit;
}
$chosen_shipping_methods = false;
if(isset($_POST['shipping_method'][0]) && !empty($_POST['shipping_method'][0])){
    $chosen_shipping_methods = explode(':', $_POST['shipping_method'][0]);
    $chosen_shipping_methods = $chosen_shipping_methods[0];
}

if ( $chosen_shipping_methods && $chosen_shipping_methods != 'wc_pickup_store' ) {
    add_action('woocommerce_checkout_order_processed', 'stuart_woocommerce_new_order');
}

// create stuart job after payment completed
function stuart_woocommerce_new_order_completed( $order_id ) {

    $apiStuart = new ApiStuart();

    $resultCreateJob = $apiStuart->stuart_create_job($order_id, $_SESSION['pickup_closer']);
    //error_log("create job: ".$resultCreateJob['success']);
    //error_log("result create job:  ".$resultCreateJob['result']);

    if($resultCreateJob['success']){
        update_order_state_to_completed($order_id);
        $_SESSION['pickup_closer'] = null;
        //send SMS
        if(!empty(get_option( 'stuart_clickatell_key' )))
            stuart_send_notification($order_id);
    }else{
        update_order_state_to_failed($order_id);
        stuart_wc_refund_order($order_id, __('Stuart fail creating the delivery', 'stuart-integration'));
        wc_add_notice( __("Stuart: ".json_decode($resultCreateJob->getBody())->message, 'stuart-integration'), 'notice' );
        ob_start();
        wc_print_notices();
        $messages = ob_get_clean();
        echo json_encode(array('valid' => false, 'error' => $messages));
        exit;
    }
}


add_action('woocommerce_payment_complete', 'stuart_woocommerce_new_order_completed');

function stuart_send_notification($order_id){
    //send SMS
    $api_id = urlencode(get_option( 'stuart_clickatell_key' ));
    if(!empty($api_id)){
        $to = urlencode(get_option( 'pickup_phone_'.$_SESSION['pickup_closer'] ));
        $message = urlencode("Se ha recibido el pedido no. ".$order_id." enlace: ".admin_url('post.php?post='.$order_id.'&action=edit'));

        echo file_get_contents("https://platform.clickatell.com/messages/http/send?apiKey=".$api_id."&to=".$to."&content=".$message);
    }
}

function update_order_state_to_completed( $order_id ) {

    if ( !$order_id ) return;
    $order = new WC_Order( $order_id );

    $order->update_status( 'completed' );

}

function update_order_state_to_failed( $order_id ) {

    if ( !$order_id ) return;
    $order = new WC_Order( $order_id );

    $order->update_status( 'failed' );
}
/**
 * Process Order Refund through Code
 * @return WC_Order_Refund|WP_Error
 */
function stuart_wc_refund_order( $order_id, $refund_reason = '' ) {

    $order  = wc_get_order( $order_id );

    // If it's something else such as a WC_Order_Refund, we don't want that.
    if( ! is_a( $order, 'WC_Order') ) {
        return new WP_Error( 'wc-order', __( 'The order ID is not a valid WooCommerce order', 'stuart-integration' ) );
    }

    if( 'refunded' == $order->get_status() ) {
        return new WP_Error( 'wc-order', __( 'Order has been already refunded', 'stuart-integration' ) );
    }

    // Get Items
    $order_items   = $order->get_items();
    // Refund Amount
    $refund_amount = 0;
    // Prepare line items which we are refunding
    $line_items = array();

    if ( $order_items ) {
        foreach( $order_items as $item_id => $item ) {

            //$item_meta 	= $order->get_item_meta( $item_id );
            //$tax_data = $item_meta['_line_tax_data'];
            $tax_data = $item->get_meta( '_line_tax_data' );
            $total_data = $item->get_meta( '_line_total' );
            $refund_tax = 0;
            if( is_array( $tax_data[0] ) ) {
                $refund_tax = array_map( 'wc_format_decimal', $tax_data[0] );
            }

            $refund_amount = wc_format_decimal( $refund_amount ) + wc_format_decimal( $total_data[0] );

            $qty_data = $item->get_meta( '_qty' );
            $line_items[ $item_id ] = array(
                'qty' => $qty_data[0],
                'refund_total' => wc_format_decimal( $total_data[0] ),
                'refund_tax' =>  $refund_tax );

        }
    }

    $refund = wc_create_refund( array(
        'amount'         => $refund_amount,
        'reason'         => $refund_reason,
        'order_id'       => $order_id,
        'line_items'     => $line_items,
        'refund_payment' => true
    ));

    return $refund;
}

function stuart_add_shipping_fee( $posted_data ) {

    /*global $woocommerce;

    if( !is_cart() && !is_checkout()){
        return;
    }*/
    global $woocommerce;

    //if ( is_admin() && ! defined( 'DOING_AJAX' ) )return;


    $chosen_shipping_methods = false;
    if(isset($_POST['shipping_method'][0]) && !empty($_POST['shipping_method'][0])){
        $chosen_shipping_methods = explode(':', $_POST['shipping_method'][0]);

        if($chosen_shipping_methods[0] != 'local_pickup') return;
    }

    $closer = null;
    $addressTo = null;

    $post = false;
    if(!isset($posted_data->cart_contents)){
        $post = array();
        $vars = explode('&', $posted_data);
        foreach ($vars as $k => $value){
            $v = explode('=', urldecode($value));
            $post[$v[0]] = $v[1];
        }
    }

    $apiStuart = new ApiStuart();

    $addressTo = $apiStuart->stuart_get_address_to($post);

    if(!$addressTo['success']){
        wc_clear_notices();
        if ( is_user_logged_in() && is_cart() && ! is_wc_endpoint_url() ) {
            wc_add_notice(sprintf($addressTo['result']), 'notice');
        }
        if ( is_checkout() && ! is_wc_endpoint_url()  ) {
            add_filter( 'woocommerce_order_button_html', 'replace_order_button_html', 10, 2 );
            wc_add_notice(sprintf($addressTo['result']), 'notice');
        }
        return;

    }else{

        $closer = $apiStuart->calculate_closer_shop_from_drop_off($addressTo['result']);

        if(!$closer['success']){
            wc_add_notice( $closer['result'], 'notice' );
            return;
        }

        if ( is_admin() && ! defined( 'DOING_AJAX' ) )
            return;

        $distance = $closer['result'];
        // Set here your shipping fee amount

        $default_first_fee = 0;
        if(!empty(get_option('stuart_first_fee')) && get_option('stuart_first_fee') > 0){
            $default_first_fee = get_option('stuart_first_fee');
        }
        $default_second_fee = 0;
        if(!empty(get_option('stuart_second_fee')) && get_option('stuart_second_fee') > 0){
            $default_second_fee = get_option('stuart_second_fee');
        }

        $fee = 0;
        if($default_first_fee > 0 && $default_second_fee > 0){
            $fee = $default_first_fee; //< 1500

            $difference = null;
            if($distance > 1500 && $distance < 3500){ //> 1500 < 3500
                $fee = $default_second_fee;
            }else if($distance >= 3500){ // > 3500
                $difference = $distance - 3500;
                if($difference > 1000){
                    $fee = $default_second_fee + round($difference/1000, 0, PHP_ROUND_HALF_UP);
                }
            }
        }

        //$woocommerce->cart->add_fee( __('Shipping Fee', 'stuart-integration'), $fee, false );
        $woocommerce->cart->add_fee( __('Shipping Fee', 'stuart-integration'), $fee, false );
    }


}

if(!empty($apiStuart->here_key) || !empty($apiStuart->google_key)){

    add_action( 'woocommerce_checkout_update_order_review','stuart_add_shipping_fee', 10, 2 );

    //if ( isset($_POST['shipping_method'][0]) && $_POST['shipping_method'][0] != 'wc_pickup_store' ) {
    //add_action('woocommerce_checkout_update_order_review', 'stuart_add_shipping_fee');
    //}

    add_action( 'woocommerce_cart_calculate_fees','stuart_add_shipping_fee', 10, 2 );


}




/*
function stuart_shipping_notice() {
    echo '<ul class="woocommerce-error" role="alert"><li>'.__("This location is for delivery out of range or incorrect", 'stuart-integration').'</li></ul>';
}

function stuart_woocommerce_checkout_update_order_review($posted_data){
    // Parsing posted data on checkout

    $post = array();
    $vars = explode('&', $posted_data);
    foreach ($vars as $k => $value){
        $v = explode('=', urldecode($value));
        $post[$v[0]] = $v[1];
    }

    //print_r($post);
    //print_r(order_review_format_shipping_address($post));
    $apiStuart = new ApiStuart();
    $resultValidation = $apiStuart->stuart_address_validation_without_order_id(order_review_format_shipping_address($post));

    if(!$resultValidation['success']){
        wc_add_notice($resultValidation['result'], 'error' );
        ob_start();
        wc_print_notices();
        $messages = ob_get_clean();
        //echo json_encode(array('valid' => false, 'error' => $messages));
        exit;
    }
    //validateAddress

    //WC()->cart->calculate_shipping();
}
*/
function replace_order_button_html() {
    $order_button_text = __( "Place Order", "stuart-integration" );

    $style = ' style="color:#fff;cursor:not-allowed;background-color:#999;"';
    return '<a class="button alt"'.$style.' name="woocommerce_checkout_place_order" id="place_order" >' . esc_html( $order_button_text ) . '</a>';
}
function stuart_add_my_account_button() {
    echo '<div class="myaccount" style="display: inline-block;"><a href="'.get_permalink( get_option("woocommerce_myaccount_page_id") ).'" class="button" title="'.__("My Account", "stuart-integration").'">'.__("My Account", "stuart-integration").'</a></div>';
}
//shipping filters
if(!empty($apiStuart->stuart_key) && !empty($apiStuart->stuart_secret)){

    add_filter( 'woocommerce_cart_shipping_method_full_label', 'stuart_wc_free_shipping_label', 10, 2 );
    add_filter( 'woocommerce_package_rates', 'stuart_hide_shipping_when_free_is_available', 100 );
}

function stuart_wc_free_shipping_label( $label, $method ) {

    if ( $method->cost == 0 ) {
        $label = __("Stuart", 'stuart-integration');
    }

    return $label;
}

function stuart_hide_shipping_when_free_is_available( $rates ) {

    $free = array();

    foreach ( $rates as $rate_id => $rate ) {

        if ( $rate->method_id == 'free_shipping' ) {
            $free[ $rate_id ] = $rate;
            break;
        }
    }

    return !empty( $free ) ? $free : $rates;
}