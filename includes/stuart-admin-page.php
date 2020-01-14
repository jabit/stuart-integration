<?php
// Para evitar llamadas directas
defined("ABSPATH") or exit();
?>

<div class="wrap_stuartform">
    <h1><?php _e( 'Stuart Integration', 'stuart-integration' ) ?></h1>
    <p><?php _e( 'Welcome to Stuart integration configuration', 'stuart-integration' ) ?></p>
    <div class="row mb-5">
        <div id="api-key" class="col-sm-12">
            <div class="card card-body">
                <form id="stuartForm" name="stuartform" action="<?php echo esc_attr( admin_url('admin-post.php') ); ?>" method="POST">
                    <input type="hidden" name="action" value="stuart_save_data" />
                <p class="card-text"><?php _e("Please enter the corresponding keys to your Stuart account in these text fields.", 'stuart-integration'); ?></p>
                <div class="form-group">
                    <label for="stuart_environment"><?php _e( 'Please select environment', 'stuart-integration' ) ?></label>
                    <select class="form-control" id="stuart_environment" name="stuart_environment">
                        <option value="SANDBOX" <?php if(get_option( 'stuart_environment' ) == "SANDBOX") echo "selected" ?>><?php _e( 'Sandbox', 'stuart-integration' ) ?></option>
                        <option value="PRODUCTION" <?php if(get_option( 'stuart_environment' ) == "PRODUCTION") echo "selected" ?>><?php _e( 'Production', 'stuart-integration' ) ?></option>
                    </select>
                </div>
                <div class="input-group">
                    <input type="text" class="form-control large" id="stuart_api_key" name="stuart_api_key" <?php echo get_option( 'stuart_api_key' ) != false ? "value =".get_option( 'stuart_api_key' ) : "value=''"; ?> placeholder="<?php _e("Stuart API KEY", 'stuart-integration'); ?>">
                </div>
                <div class="input-group ">
                    <input type="text" class="form-control large" id="stuart_secret_key" name="stuart_secret_key" <?php echo get_option( 'stuart_secret_key' ) != false ? "value =".get_option( 'stuart_secret_key' ) : "value=''"; ?> placeholder="<?php _e("Stuart SECRET KEY", 'stuart-integration'); ?>">
                </div>
                <div class="input-group ">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit" id="button-config"><?php _e("Submit", 'stuart-integration'); ?></button>
                    </div>
                </div>
                </form>
            </div>
        </div>
    </div>

    <h2><?php _e( 'Pick up data location', 'stuart-integration' ) ?></h2>
    <p><?php _e( 'Please, place here the pick up location', 'stuart-integration' ) ?></p>
    <div class="row mb-5">
        <div class="col-sm-12">
            <div class="card card-body">
                <h4 class="card-title"><?php _e( 'Restaurant pick up address', 'stuart-integration' ) ?></h4>
                <!--<h5 class="card-subtitle"> All bootstrap element classies </h5>-->
                <form class="form-horizontal mt-4" id="stuartPickupForm" name="stuartpickupform" action="<?php echo esc_attr( admin_url('admin-post.php') ); ?>" method="POST">
                    <input type="hidden" name="action" value="stuart_save_pickup_data" />
                    <div class="form-row mb-3">
                        <div class="col">
                            <label><?php _e( 'First name', 'stuart-integration' ) ?></label>
                            <input type="text" name="pickup_first_name" class="form-control" value="<?php echo get_option( 'pickup_first_name' );?>" placeholder="<?php _e("First name", 'stuart-integration'); ?>" required>
                        </div>
                        <div class="col">
                            <label><?php _e( 'Last name', 'stuart-integration' ) ?></label>
                            <input type="text" name="pickup_last_name" class="form-control" value ="<?php echo get_option( 'pickup_last_name' );?>" placeholder="<?php _e("Last name", 'stuart-integration'); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php _e( 'Company', 'stuart-integration' ) ?></label>
                        <input type="text" name="pickup_company" class="form-control" value ="<?php echo get_option( 'pickup_company' );?>" placeholder="<?php _e("Company", 'stuart-integration'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="pickup_email"><?php _e( 'Email', 'stuart-integration' ) ?></label>
                        <input type="email" id="pickup_email" name="pickup_email" class="form-control" value ="<?php echo get_option( 'pickup_email' );?>" placeholder="<?php _e("Email", 'stuart-integration'); ?>" required>
                    </div>
                    <div class="form-group">
                        <!-- Nav tabs -->
                        <ul class="nav nav-pills mt-4 mb-1" role="tablist">
                            <li class="nav-item"> <a class="nav-link active" data-toggle="tab" href="#shop1" role="tab"><span class="hidden-sm-up"><i class="ti-home"></i></span> <span class="hidden-xs-down"><?php _e( 'Shop', 'stuart-integration' ) ?> 1</span></a> </li>
                            <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#shop2" role="tab"><span class="hidden-sm-up"><i class="ti-home"></i></span> <span class="hidden-xs-down"><?php _e( 'Shop', 'stuart-integration' ) ?> 2</span></a> </li>
                            <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#shop3" role="tab"><span class="hidden-sm-up"><i class="ti-home"></i></span> <span class="hidden-xs-down"><?php _e( 'Shop', 'stuart-integration' ) ?> 3</span></a> </li>
                            <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#shop4" role="tab"><span class="hidden-sm-up"><i class="ti-home"></i></span> <span class="hidden-xs-down"><?php _e( 'Shop', 'stuart-integration' ) ?> 4</span></a> </li>
                        </ul>
                        <!-- Tab panes -->
                        <div class="tab-content mt-2 border p-2">
                            <div class="tab-pane active" id="shop1" role="tabpanel">
                                <div class="form-group">
                                    <label><?php _e( 'Address It must be an address formatted by Google ', 'stuart-integration') ?><a href="https://www.google.com/maps/preview">Maps</a></label>
                                    <input type="text" id="stuartSearchInput" name="pickup_address_1" class="form-control searchInput" value ="<?php echo get_option( 'pickup_address_1' );?>" placeholder="<?php _e("Enter a location shop 1", 'stuart-integration'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label><?php _e( 'Phone', 'stuart-integration' ) ?></label>
                                    <input type="tel" name="pickup_phone_1" class="form-control" value ="<?php echo get_option( 'pickup_phone_1' );?>" placeholder="<?php _e("Phone shop 1", 'stuart-integration'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label><?php _e( 'Add details for the courier (floor number, door, local, other information, etc.)', 'stuart-integration' ) ?></label>
                                    <textarea name="stuart_pickup_details_1" class="form-control" rows="4"><?php echo get_option( 'stuart_pickup_details_1' ) != false ? get_option( 'stuart_pickup_details_1' ) : null;?></textarea>
                                </div>
                            </div>
                            <div class="tab-pane" id="shop2" role="tabpanel">
                                <div class="form-group">
                                    <label><?php _e( 'Address It must be an address formatted by Google ', 'stuart-integration') ?><a href="https://www.google.com/maps/preview">Maps</a></label>
                                    <input type="text" id="stuartSearchInputAddress2" name="pickup_address_2" class="form-control searchInput" value ="<?php echo get_option( 'pickup_address_2' );?>" placeholder="<?php _e("Enter location shop 2", 'stuart-integration'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="pickup_phone"><?php _e( 'Phone', 'stuart-integration' ) ?></label>
                                    <input type="tel" name="pickup_phone_2" class="form-control" value ="<?php echo get_option( 'pickup_phone_2' );?>" placeholder="<?php _e("Phone shop 2", 'stuart-integration'); ?>">
                                </div>
                                <div class="form-group">
                                    <label><?php _e( 'Add details for the courier (floor number, door, local, other information, etc.)', 'stuart-integration' ) ?></label>
                                    <textarea name="stuart_pickup_details_2" class="form-control" rows="4"><?php echo get_option( 'stuart_pickup_details_2' ) != false ? get_option( 'stuart_pickup_details_2' ) : null;?></textarea>
                                </div>
                            </div>
                            <div class="tab-pane" id="shop3" role="tabpanel"><div class="form-group">
                                    <label><?php _e( 'Address It must be an address formatted by Google ', 'stuart-integration') ?><a href="https://www.google.com/maps/preview">Maps</a></label>
                                    <input type="text" id="stuartSearchInputAddress3" name="pickup_address_3" class="form-control searchInput" value ="<?php echo get_option( 'pickup_address_3' );?>" placeholder="<?php _e("Enter location shop 3", 'stuart-integration'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="pickup_phone"><?php _e( 'Phone', 'stuart-integration' ) ?></label>
                                    <input type="tel" name="pickup_phone_3" class="form-control" value ="<?php echo get_option( 'pickup_phone_3' );?>" placeholder="<?php _e("Phone shop 3", 'stuart-integration'); ?>">
                                </div>
                                <div class="form-group">
                                    <label><?php _e( 'Add details for the courier (floor number, door, local, other information, etc.)', 'stuart-integration' ) ?></label>
                                    <textarea name="stuart_pickup_details_3" class="form-control" rows="4"><?php echo get_option( 'stuart_pickup_details_3' ) != false ? get_option( 'stuart_pickup_details_3' ) : null;?></textarea>
                                </div>
                            </div>
                            <div class="tab-pane" id="shop4" role="tabpanel"><div class="form-group">
                                    <label><?php _e( 'Address It must be an address formatted by Google ', 'stuart-integration') ?><a href="https://www.google.com/maps/preview">Maps</a></label>
                                    <input type="text" id="stuartSearchInputAddress4" name="pickup_address_4" class="form-control searchInput" value ="<?php echo get_option( 'pickup_address_4' );?>" placeholder="<?php _e("Enter location shop 4", 'stuart-integration'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="pickup_phone"><?php _e( 'Phone', 'stuart-integration' ) ?></label>
                                    <input type="tel" name="pickup_phone_4" class="form-control" value ="<?php echo get_option( 'pickup_phone_4' );?>" placeholder="<?php _e("Phone shop 4", 'stuart-integration'); ?>">
                                </div>
                                <div class="form-group">
                                    <label><?php _e( 'Add details for the courier (floor number, door, local, other information, etc.)', 'stuart-integration' ) ?></label>
                                    <textarea name="stuart_pickup_details_4" class="form-control" rows="4"><?php echo get_option( 'stuart_pickup_details_4' ) != false ? get_option( 'stuart_pickup_details_4' ) : null;?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="input-group ">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit" id="button-pickup"><?php _e("Submit", 'stuart-integration'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!--
    <h2><?php _e( 'Drop off data location', 'stuart-integration' ) ?></h2>
    <div class="row mb-5">
        <div class="col-sm-12">
            <div class="card card-body">
                <h4 class="card-title"><?php _e( 'Woocommerce Address', 'stuart-integration' ) ?></h4>

                <form class="form-horizontal mt-4" id="stuartDropoffForm" name="stuartdropoffform" action="<?php echo esc_attr( admin_url('admin-post.php') ); ?>" method="POST">
                    <input type="hidden" name="action" value="stuart_save_dropoff_data" />
                    <div class="form-group">
                        <select class="form-control custom-select" name="stuart_dropoff_address">
                            <option value="billing" <?php if(get_option( 'stuart_dropoff_address' ) == "billing") echo "selected" ?>><?php _e( 'Billing Address', 'stuart-integration' ) ?></option>
                            <option value="shipping" <?php if(get_option( 'stuart_dropoff_address' ) == "shipping") echo "selected" ?>><?php _e( 'Shipping Address', 'stuart-integration' ) ?></option>
                        </select>
                    </div>
                    <div class="input-group ">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit" id="button-dropoff"><?php _e("Submit", 'stuart-integration'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    -->

    <h2><?php _e( 'GOOGLE API', 'stuart-integration' ) ?></h2>
    <div class="row mb-5">
        <div class="col-sm-12">
            <div class="card card-body">
                <h4 class="card-title"><?php _e( 'Please place here your Google API key', 'stuart-integration' ) ?></h4>
                <!--<h5 class="card-subtitle"> All bootstrap element classies </h5>-->
                <form class="form-horizontal mt-4" id="stuartGoogleKeyForm" name="stuartgooglekeyform" action="<?php echo esc_attr( admin_url('admin-post.php') ); ?>" method="POST">
                    <input type="hidden" name="action" value="stuart_save_google_key" />
                    <p class="card-text"><?php _e("You can find here your ", 'stuart-integration'); ?><a href="https://console.cloud.google.com/home/dashboard"><?php _e("Google API key", 'stuart-integration'); ?></a></p>
                    <div class="input-group">
                        <input type="text" class="form-control large" id="stuart_google_api_key" name="stuart_google_api_key" <?php echo get_option( 'stuart_google_key' ) != false ? "value =".get_option( 'stuart_google_key' ) : "value=''"; ?> placeholder="<?php _e("Google API key", 'stuart-integration'); ?>">
                    </div>
                    <div class="input-group ">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit" id="button-dropoff"><?php _e("Submit", 'stuart-integration'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <h2><?php _e( 'HERE MAPS API', 'stuart-integration' ) ?></h2>
    <p class="col-4 m-0 p-0"><?php _e("Alternative to google maps to perform distance calculations between stores and calculate the shortest distance between the customer's address and the store that will place the order", 'stuart-integration'); ?></p>
    <div class="row mb-5">
        <div class="col-sm-12">
            <div class="card card-body">
                <h4 class="card-title"><?php _e( 'Please place here your HERE API key', 'stuart-integration' ) ?></h4>
                <!--<h5 class="card-subtitle"> All bootstrap element classies </h5>-->
                <form class="form-horizontal mt-4" id="stuartHereKeyForm" name="stuartherekeyform" action="<?php echo esc_attr( admin_url('admin-post.php') ); ?>" method="POST">
                    <input type="hidden" name="action" value="stuart_save_here_key" />
                    <p class="card-text"><?php _e("You can find here your ", 'stuart-integration'); ?><a href="https://developer.here.com/"><?php _e("HERE API key", 'stuart-integration'); ?></a></p>
                    <div class="input-group">
                        <input type="text" class="form-control large" id="stuart_here_api_key" name="stuart_here_api_key" <?php echo get_option( 'stuart_here_key' ) != false ? "value =".get_option( 'stuart_here_key' ) : "value=''"; ?> placeholder="<?php _e("HERE API key", 'stuart-integration'); ?>">
                    </div>
                    <div class="input-group ">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit" id="button-dropoff"><?php _e("Submit", 'stuart-integration'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php if(!empty(get_option( 'stuart_google_key'))): ?>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?libraries=places&key=<?php echo get_option( 'stuart_google_key') ?>"></script>
<script>
    var searchInput1 = 'stuartSearchInput';
    var searchInput2 = 'stuartSearchInputAddress2';
    var searchInput3 = 'stuartSearchInputAddress3';
    var searchInput4 = 'stuartSearchInputAddress4';

    $(document).ready(function () {

        let autocomplete1;
        autocomplete1 = new google.maps.places.Autocomplete((document.getElementById(searchInput1)), {
            types: ['geocode'],
        });

        google.maps.event.addListener(autocomplete1, 'place_changed', function () {
            let near_place = autocomplete1.getPlace();
        });

        let autocomplete2;
        autocomplete2 = new google.maps.places.Autocomplete((document.getElementById(searchInput2)), {
            types: ['geocode'],
        });

        google.maps.event.addListener(autocomplete2, 'place_changed', function () {
            let near_place = autocomplete2.getPlace();
        });

        let autocomplete3;
        autocomplete3 = new google.maps.places.Autocomplete((document.getElementById(searchInput3)), {
            types: ['geocode'],
        });

        google.maps.event.addListener(autocomplete3, 'place_changed', function () {
            let near_place = autocomplete3.getPlace();
        });

        let autocomplete4;
        autocomplete4 = new google.maps.places.Autocomplete((document.getElementById(searchInput4)), {
            types: ['geocode'],
        });

        google.maps.event.addListener(autocomplete4, 'place_changed', function () {
            let near_place = autocomplete4.getPlace();
        });
    });
</script>
<?php endif; ?>