<?php
/*
Plugin Name: Stuart WooCommerce integration
Description: Stuart integration (express shipping) with woocommerce wordpress
Author: Jabit Web Developer
Version: 1.1.5
Author URI: https://ja-bit.com
Text Domain: stuart-integration
*/

// Para evitar llamadas directas
defined("ABSPATH") or exit();

defined("STUART_URL") or define("STUART_URL", plugin_dir_path(__FILE__));
defined("STUART_IMAGES_URL") or define("STUART_IMAGES_URL", plugin_dir_path(__FILE__). "images/");

function stuart_activate()
{
    add_option( 'stuart_api_key', '' );
    add_option( 'stuart_secret_key', '' );
    add_option( 'stuart_google_key', '' );
    add_option( 'stuart_here_key', '' );
    add_option( 'stuart_clickatell_key', '' );
    add_option( 'stuart_first_fee', '' );
    add_option( 'stuart_second_fee', '' );

    //add pickup details
    add_option( 'pickup_first_name', null );
    add_option( 'pickup_last_name', null );
    add_option( 'pickup_company', null );
    add_option( 'pickup_address_1', null );
    add_option( 'pickup_address_2', null );
    add_option( 'pickup_address_3', null );
    add_option( 'pickup_address_4', null );
    add_option( 'pickup_phone_1', null );
    add_option( 'pickup_phone_2', null );
    add_option( 'pickup_phone_3', null );
    add_option( 'pickup_phone_4', null );
    add_option( 'stuart_pickup_details_1', null );
    add_option( 'stuart_pickup_details_2', null );
    add_option( 'stuart_pickup_details_3', null );
    add_option( 'stuart_pickup_details_4', null );
    add_option( 'pickup_email', null );
    add_option( 'stuart_pickup_closer', null );
    //add_option( 'stuart_dropoff_address', 'billing' );
    add_option( 'stuart_environment', 'sandbox' );

}
register_activation_hook(__FILE__,'stuart_activate');

function stuart_deactivate()
{
    //nothing to do
}
register_activation_hook(__FILE__,'stuart_deactivate');


function stuart_plugin_uninstall() {
    //Keys
    get_option('stuart_api_key') != false ? delete_option( 'stuart_api_key' ) : "";
    get_option('stuart_secret_key') != false ? delete_option( 'stuart_secret_key' ) : "";
    get_option('stuart_google_key') != false ? delete_option( 'stuart_google_key' ) : "";
    get_option('stuart_here_key') != false ? delete_option( 'stuart_here_key' ) : "";
    get_option('stuart_clickatell_key') != false ? delete_option( 'stuart_clickatell_key' ) : "";
    get_option('stuart_first_fee') != false ? delete_option( 'stuart_first_fee' ) : "";
    get_option('stuart_second_fee') != false ? delete_option( 'stuart_second_fee' ) : "";

    //delete pickup details
    get_option('pickup_first_name') != false ? delete_option( 'pickup_first_name' ) : "";
    get_option('pickup_last_name') != false ? delete_option( 'pickup_last_name' ) : "";
    get_option('pickup_company') != false ?  delete_option( 'pickup_company' ) : "";
    get_option('pickup_address_1') != false ?  delete_option( 'pickup_address_1' ) : "";
    get_option('pickup_address_2') != false ?  delete_option( 'pickup_address_2' ) : "";
    get_option('pickup_address_3') != false ?  delete_option( 'pickup_address_3' ) : "";
    get_option('pickup_address_4') != false ?  delete_option( 'pickup_address_4' ) : "";
    get_option('pickup_phone_1') != false ? delete_option( 'pickup_phone_1' ) : "";
    get_option('pickup_phone_2') != false ? delete_option( 'pickup_phone_2' ) : "";
    get_option('pickup_phone_3') != false ? delete_option( 'pickup_phone_3' ) : "";
    get_option('pickup_phone_4') != false ? delete_option( 'pickup_phone_4' ) : "";
    get_option('stuart_pickup_details_1') != false ? delete_option( 'stuart_pickup_details_1' ) : "";
    get_option('stuart_pickup_details_2') != false ? delete_option( 'stuart_pickup_details_2' ) : "";
    get_option('stuart_pickup_details_3') != false ? delete_option( 'stuart_pickup_details_3' ) : "";
    get_option('stuart_pickup_details_4') != false ? delete_option( 'stuart_pickup_details_4' ) : "";
    get_option('pickup_email') != false ? delete_option( 'pickup_email' ) : "";
    get_option('stuart_pickup_closer') != false ? delete_option( 'stuart_pickup_closer' ) : "";
    get_option('stuart_environment') != false ? delete_option( 'stuart_environment' ) : "";
}
register_uninstall_hook( __FILE__, 'stuart_plugin_uninstall' );

//require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
// Include API Stuart
require_once plugin_dir_path(__FILE__) . 'includes/apiStuart.php';

// Include functions.php
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';

// Include woo-functions.php
require_once plugin_dir_path(__FILE__) . 'includes/woo-functions.php';



if (is_admin()) {
    add_action('admin_enqueue_scripts', "admin_enqueue_scripts");
}

function admin_enqueue_scripts($hook){

    if( $hook != 'stuart-integration/includes/stuart-admin-page.php' )
        return;

    wp_enqueue_style('bootstrap', plugins_url('css/bootstrap.css', __FILE__));
    wp_enqueue_style('stuart_admin_css', plugins_url('css/stuart-integration.css', __FILE__));
    wp_enqueue_script("popper", plugins_url("js/third-party/popper.js", __FILE__), array(), "1.12.9" );
    wp_enqueue_script("bootstrap", plugins_url("js/third-party/bootstrap.min.js", __FILE__), array("jquery", "popper"), "4.0.0");

}

/**
 * Plugin LOADED!
 */
function stuart_plugins_loaded() {
    load_plugin_textdomain( 'stuart-integration', false, basename(dirname(__FILE__)) . "/languages" );
}
add_action('plugins_loaded', 'stuart_plugins_loaded');

function stuart_plugin_locale($locale, $domain) {
    if ($domain == 'stuart-integration') {
        // En el caso de que no sea es, ponemos en_US
        if (empty($locale) || substr( $locale, 0, 2  ) !== "es") {
            return "en_US";
        } else {
            return "es_ES";
        }
    }

    return $locale;
}
add_filter("plugin_locale", "stuart_plugin_locale", 10, 2);

// PRADIÑAS Calle de Goya 12 28001
// SANDOVAL Calle de la Palma 45, 28004 Madrid
// TOLEDO Calle de Rodas 20, 28005 Madrid
// Calle Tambre, 28 28002

//.woocommerce-cart .wc-forward