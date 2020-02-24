<?php
// Para evitar llamadas directas
defined("ABSPATH") or exit();

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

require_once plugin_dir_path(__FILE__) . 'apiStuart.php';

global $apiStuart;
$apiStuart = new ApiStuart();

if ( isset($_POST['shipping_method'][0]) && $_POST['shipping_method'][0] != 'wc_pickup_store' ) {
    if (!empty($apiStuart->stuart_key) && !empty($apiStuart->stuart_secret)) {
        add_action('woocommerce_checkout_order_processed', 'stuart_woocommerce_new_order');
        add_action('woocommerce_payment_complete', 'stuart_woocommerce_new_order_completed');
    }
}

function stuart_woocommerce_new_order( $order_id ) {

    if ( !$order_id ) return;

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
}

// create stuart job after payment completed
function stuart_woocommerce_new_order_completed( $order_id ) {

    if(!did_action( 'woocommerce_checkout_order_processed' )) return;

    $apiStuart = new ApiStuart();
    $resultCreateJob = $apiStuart->stuart_create_job($order_id);

    if(isset($resultCreateJob->error) && !empty($resultCreateJob->error)){
        update_order_state_to_failed($order_id);
        stuart_wc_refund_order($order_id, __('Stuart fail creating the delivery', 'stuart-integration'));
        wc_add_notice( __("Stuart: ".json_decode($resultCreateJob->getBody())->message, 'stuart-integration'), 'notice' );
        ob_start();
        wc_print_notices();
        $messages = ob_get_clean();
        echo json_encode(array('valid' => false, 'error' => $messages));
        exit;
    }else{
        update_order_state_to_completed($order_id);
        //send SMS
        stuart_send_notification($order_id);
    }
}

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

    //add_action( 'template_redirect', 'plugin_is_page_checkout' );
    add_action('woocommerce_checkout_update_order_review', 'stuart_add_shipping_fee');
    add_action( 'woocommerce_cart_calculate_fees','stuart_add_shipping_fee' );
}

function stuart_add_shipping_fee( $posted_data ) {

    if ( isset($_POST['shipping_method'][0]) && $_POST['shipping_method'][0] != 'wc_pickup_store' ) {

        global $woocommerce;

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
                //remove_action( 'woocommerce_proceed_to_checkout','woocommerce_button_proceed_to_checkout', 20);
                //add_action( 'woocommerce_cart_coupon', 'stuart_add_my_account_button' );
                wc_add_notice(sprintf($addressTo['result']), 'notice');
            }else if ( is_checkout() && ! is_wc_endpoint_url()  ) {
                //add_action( 'woocommerce_review_order_before_submit', 'stuart_add_my_account_button' );
                add_filter( 'woocommerce_order_button_html', 'replace_order_button_html', 10, 2 );
                //add_action( 'woocommerce_before_checkout_form', 'stuart_shipping_notice' );
                wc_add_notice(sprintf($addressTo['result']), 'notice');
            }
            return;

        }else{

            $closer = $apiStuart->calculate_closer_shop_from_drop_off($addressTo['result']);

            if(!$closer['success']){
                wc_add_notice( $closer['result'], 'notice' );
                return;
            }

            $distance = $closer['result'];
            // Set here your shipping fee amount
            $fee = get_option('stuart_first_fee') != false ? get_option('stuart_first_fee') : 2.5; //< 1500

            if ( is_admin() && ! defined( 'DOING_AJAX' ) )
                return;

            $difference = null;
            if($distance > 1500 && $distance < 3500){
                $fee = get_option('stuart_second_fee') != false ? get_option('stuart_second_fee') : 2.65;
            }else if($distance >= 3500){
                $difference = $distance - 3500;
                if($difference > 1000){
                    $fee = get_option('stuart_second_fee') != false ? get_option('stuart_second_fee') : 2.65 + round($difference/1000, 0, PHP_ROUND_HALF_UP);
                }
            }
            WC()->cart->add_fee( __('Shipping Fee', 'stuart-integration'), $fee, false );
        }
    }
}
function stuart_shipping_notice() {
    echo '<ul class="woocommerce-error" role="alert"><li>'.__("This location is for delivery out of range or incorrect", 'stuart-integration').'</li></ul>';
}
/*
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

/*
function check_if_pick_up_shipping_methods() {
    global $woocommerce;

    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
        return;

    $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
    $chosen_shipping = $chosen_methods[0];

    if ( $chosen_shipping == 'local_pickup' ) {
        return true;
    }
    return false;

}
*/