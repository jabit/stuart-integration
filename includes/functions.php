<?php
// Para evitar llamadas directas
defined("ABSPATH") or exit();

// Hook the 'admin_menu' action hook, run the function named 'stuart_add_admin_Link()'
add_action( 'admin_menu', 'stuart_add_admin_Link' );
// Add a new top level menu link to the ACP

function stuart_add_admin_Link()
{
    add_menu_page(
        __( 'Stuart config page', 'stuart-integration' ), // Title of the page
        __( 'Stuart Integration', 'stuart-integration' ), // Text to show on the menu link
        'manage_options', // Capability requirement to see the link
        STUART_URL . 'includes/stuart-admin-page.php', // The 'slug' - file to display when clicking the link
        '',
        plugins_url( 'stuart-integration/images/stuart_16.png' )
    );
}

//Save Stuart Keys
add_action( 'admin_post_nopriv_stuart_save_data', 'stuart_save_data' );
add_action( 'admin_post_stuart_save_data', 'stuart_save_data' );

function stuart_save_data() {

    $stuart_api_key = null;
    $stuart_secret_key = null;
    $stuart_environment = null;

    if($_POST && $_POST['action'] == 'stuart_save_data'){

        $stuart_api_key = $_POST['stuart_api_key'];
        $stuart_secret_key = $_POST['stuart_secret_key'];
        $stuart_environment = $_POST['stuart_environment'];

        update_option( 'stuart_api_key', $stuart_api_key );
        update_option( 'stuart_secret_key', $stuart_secret_key );
        update_option( 'stuart_environment', $stuart_environment );
        add_flash_notice( __("Api Settings Saved"), "success", false );
        wp_redirect( site_url('/wp-admin/admin.php?page=stuart-integration/includes/stuart-admin-page.php') ); // <-- here goes address of site that user should be redirected after submitting that form

    }
}

add_action( 'admin_post_nopriv_stuart_save_google_key', 'stuart_save_google_key' );
add_action( 'admin_post_stuart_save_google_key', 'stuart_save_google_key' );

function stuart_save_google_key() {

    if(!empty($_POST['action']) && $_POST['action'] == 'stuart_save_google_key'){

        update_option( 'stuart_google_key', $_POST['stuart_google_api_key'] );
        add_flash_notice( __("Google Api Settings Saved"), "success", false );
        wp_redirect( site_url('/wp-admin/admin.php?page=stuart-integration/includes/stuart-admin-page.php') );
    }
}

//HERE key save
add_action( 'admin_post_nopriv_stuart_save_here_key', 'stuart_save_here_key' );
add_action( 'admin_post_stuart_save_here_key', 'stuart_save_here_key' );

function stuart_save_here_key() {

    if($_POST && $_POST['action'] == 'stuart_save_here_key'){

        update_option( 'stuart_here_key', $_POST['stuart_here_api_key'] );
        add_flash_notice( __("HERE Api Settings Saved"), "success", false );
        wp_redirect( site_url('/wp-admin/admin.php?page=stuart-integration/includes/stuart-admin-page.php') );
    }
}

//Save Stuart Pick up data
add_action( 'admin_post_nopriv_stuart_save_pickup_data', 'stuart_save_pickup_data' );
add_action( 'admin_post_stuart_save_pickup_data', 'stuart_save_pickup_data' );

function stuart_save_pickup_data() {

    if($_POST && $_POST['action'] == 'stuart_save_pickup_data'){

        $pickup_first_name = $_POST['pickup_first_name'];
        $pickup_last_name = $_POST['pickup_last_name'];
        $pickup_company = $_POST['pickup_company'];
        $pickup_address_1 = $_POST['pickup_address_1'];
        $pickup_address_2 = $_POST['pickup_address_2'];
        $pickup_address_3 = $_POST['pickup_address_3'];
        $pickup_address_4 = $_POST['pickup_address_4'];
        $pickup_phone_1 = $_POST['pickup_phone_1'];
        $pickup_phone_2 = $_POST['pickup_phone_2'];
        $pickup_phone_3 = $_POST['pickup_phone_3'];
        $pickup_phone_4 = $_POST['pickup_phone_4'];
        $pickup_email = $_POST['pickup_email'];
        $stuart_pickup_details_1 = $_POST['stuart_pickup_details_1'];
        $stuart_pickup_details_2 = $_POST['stuart_pickup_details_2'];
        $stuart_pickup_details_3 = $_POST['stuart_pickup_details_3'];
        $stuart_pickup_details_4 = $_POST['stuart_pickup_details_4'];

        if(!empty($_POST['action']) && $_POST['action'] == "stuart_save_pickup_data"){
            update_option( 'pickup_first_name', $pickup_first_name );
            update_option( 'pickup_last_name', $pickup_last_name );
            update_option( 'pickup_company', $pickup_company );
            update_option( 'pickup_address_1', $pickup_address_1 );
            update_option( 'pickup_address_2', $pickup_address_2 );
            update_option( 'pickup_address_3', $pickup_address_3 );
            update_option( 'pickup_address_4', $pickup_address_4 );
            update_option( 'pickup_phone_1', $pickup_phone_1 );
            update_option( 'pickup_phone_2', $pickup_phone_2 );
            update_option( 'pickup_phone_3', $pickup_phone_3 );
            update_option( 'pickup_phone_4', $pickup_phone_4 );
            update_option( 'pickup_email', $pickup_email );
            update_option( 'stuart_pickup_details_1', $stuart_pickup_details_1 );
            update_option( 'stuart_pickup_details_2', $stuart_pickup_details_2 );
            update_option( 'stuart_pickup_details_3', $stuart_pickup_details_3 );
            update_option( 'stuart_pickup_details_4', $stuart_pickup_details_4 );
            add_flash_notice( __("Pick Up Settings Saved"), "success", false );
            wp_redirect( site_url('/wp-admin/admin.php?page=stuart-integration/includes/stuart-admin-page.php') );
        }
    }
}
/*
add_action( 'admin_post_nopriv_stuart_save_dropoff_data', 'stuart_save_dropoff_data' );
add_action( 'admin_post_stuart_save_dropoff_data', 'stuart_save_dropoff_data' );

function stuart_save_dropoff_data()
{
    global $wpdb;

    if($_POST){

        if(!empty($_POST['action']) && $_POST['action'] == "stuart_save_dropoff_data"){
            update_option( 'stuart_dropoff_address', $_POST['stuart_dropoff_address'] );
        }
    }

    add_flash_notice( __("Drop Off Settings Saved"), "success", false );
    wp_redirect( site_url('/wp-admin/admin.php?page=stuart-integration/includes/stuart-admin-page.php') );
}
*/
/**
 * Add a flash notice to {prefix}options table until a full page refresh is done
 *
 * @param string $notice our notice message
 * @param string $type This can be "info", "warning", "error" or "success", "warning" as default
 * @param boolean $dismissible set this to TRUE to add is-dismissible functionality to your notice
 * @return void
 */

function add_flash_notice( $notice = "", $type = "warning", $dismissible = true ) {
    // Here we return the notices saved on our option, if there are not notices, then an empty array is returned
    $notices = get_option( "stuart_flash_notices", array() );

    $dismissible_text = ( $dismissible ) ? "is-dismissible" : "";

    // We add our new notice.
    array_push( $notices, array(
        "notice" => $notice,
        "type" => $type,
        "dismissible" => $dismissible_text
    ) );

    // Then we update the option with our notices array
    update_option("stuart_flash_notices", $notices );
}

/**
 * Function executed when the 'admin_notices' action is called, here we check if there are notices on
 * our database and display them, after that, we remove the option to prevent notices being displayed forever.
 * @return void
 */

function display_flash_notices() {
    $notices = get_option( "stuart_flash_notices", array() );

    // Iterate through our notices to be displayed and print them.
    foreach ( $notices as $notice ) {
        printf('<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
            $notice['type'],
            $notice['dismissible'],
            $notice['notice']
        );
    }

    // Now we reset our options to prevent notices being displayed forever.
    if( ! empty( $notices ) ) {
        delete_option( "stuart_flash_notices", array() );
    }
}

// We add our display_flash_notices function to the admin_notices
add_action( 'admin_notices', 'display_flash_notices', 12 );