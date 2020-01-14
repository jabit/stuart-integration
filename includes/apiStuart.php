<?php

require_once plugin_dir_path( __FILE__ ) . '../vendor/autoload.php';

class ApiStuart
{
    private $httpClient;
    private $job;
    private $client;
    public $stuart_key;
    public $stuart_secret;
    public $here_key;
    public $google_key;

    public function __construct() {
        
        //PHP Stuart integration
        $this->stuart_key = get_option('stuart_api_key');
        $this->stuart_secret = get_option('stuart_secret_key');

        if(get_option( 'stuart_environment' ) == "SANDBOX")
            $environment = \Stuart\Infrastructure\Environment::SANDBOX;
        else
            $environment = \Stuart\Infrastructure\Environment::PRODUCTION;
        $api_client_id = $this->stuart_key; // can be found here: https://admin-sandbox.stuart.com/client/api
        $api_client_secret = $this->stuart_secret; // can be found here: https://admin-sandbox.stuart.com/client/api
        $authenticator = new \Stuart\Infrastructure\Authenticator($environment, $api_client_id, $api_client_secret);
        $this->httpClient = new \Stuart\Infrastructure\HttpClient($authenticator);

        //externals keys
        $this->here_key = get_option('stuart_here_key');
        $this->google_key = get_option('stuart_google_key');
    }

    public function stuart_create_job($order, $item_data){

        $pickupAt = null;
        $now = new \DateTime('now', new DateTimeZone(get_option('timezone_string') != null ? get_option('timezone_string') : date_default_timezone_get()));
        $pickupAt = $now;
        $pickupAt->add(new \DateInterval('PT20M'));

        $this->client = new \Stuart\Client($this->httpClient);
        $this->job = new \Stuart\Job();

        //pick up
        $this->job->addPickup(get_option('pickup_address_'.$_SESSION['pickup_closer']))
            ->setComment(get_option( 'stuart_pickup_details_'.$_SESSION['pickup_closer']))
            ->setContactCompany(get_option( 'pickup_company'))
            ->setContactFirstName(get_option( 'pickup_first_name'))
            ->setContactLastName(get_option( 'pickup_last_name'))
            ->setContactPhone(get_option( 'pickup_phone_'.$_SESSION['pickup_closer'] ))
            ->setContactEmail(get_option( 'pickup_email'))
            ->setPickupAt($pickupAt);

        $this->job->addDropOff($this->formatted_shipping_address($order))
            ->setPackageType('medium')
            ->setComment(__('For the delivery', 'stuart-integration').': '.$order->get_shipping_address_2().', '.__('Order ID', 'stuart-integration').': '.$order->get_id().', '.__('Notes', 'stuart-integration').': '.$order->get_customer_note())
            ->setContactFirstName($order->get_shipping_first_name())
            ->setContactLastName($order->get_shipping_last_name())
            ->setContactPhone($order->get_billing_phone())
            ->setContactEmail($order->get_billing_email())
            ->setPackageDescription('HEALTHY POKE: '.$item_data['name'])
            ->setClientReference($order->get_customer_id().':'.$order->get_id());
        //->setClientReference($order->get_customer_id());
        //->setDropoffAt($dropoffAt);

        $result = $this->client->validateJob($this->job);

        if(isset($result->error) && !empty($result->error)){
            //TODO cancelar pedido y pago???
            $order->update_status( 'failed' );
        }else{
            $this->client->createJob($this->job);
        }

        return $result;
    }

    public function formatted_shipping_address($order)
    {
        return $order->get_shipping_address_1() . ', ' .
                //$order->get_shipping_address_2() . ' ' .
                $order->get_shipping_postcode()  . ', ' .
                $order->get_shipping_city()     . ' ' .
                $order->get_shipping_state();
    }

    public function stuart_validation($order) {

        global $woocommerce;

        $addressTo = $woocommerce->customer->get_shipping_address_1() . ', ' .
                    //$woocommerce->customer->get_shipping_address_2() . ' ' .
                    $woocommerce->customer->get_shipping_postcode()  . ', ' .
                    $woocommerce->customer->get_shipping_city()     . ' ' .
                    $woocommerce->customer->get_shipping_state();

        //Validar direcciones
        $validateAddress = $this->httpClient->performGet('/v2/addresses/validate', ['address' => $addressTo, 'type' => 'delivering']);

        if(!$validateAddress->success()){
            //return $validateAddress;
            return array(
                'success' => false,
                'result' => __(json_decode($validateAddress->getBody())->message, 'stuart-integration')
            );
        }

        $resultCloser = $this->calculate_closer_shop_from_drop_off($addressTo);

        if(!$resultCloser['success']){
            return array(
                'success' => false,
                'result' => __($resultCloser['result'], 'stuart-integration')
            );
        }

        $this->client = new \Stuart\Client($this->httpClient);
        $this->job = new \Stuart\Job();

        //validar Job
        //TODO cambiar a $resultCloser['closer']
        $this->job->addPickup(get_option('pickup_address_'.$resultCloser['address']));

        //$woocommerce->customer->get_shipping_address_2()
        $this->job->addDropOff($addressTo)
            ->setPackageType('medium')
            ->setComment(__('For the delivery', 'stuart-integration').': '.$woocommerce->customer->get_shipping_address_2().', '.__('Order ID', 'stuart-integration').': '.$order->get_id().', '.__('Notes', 'stuart-integration').': '.$order->get_customer_note())
            ->setContactFirstName($order->get_shipping_first_name())
            ->setContactLastName($order->get_shipping_last_name())
            ->setContactPhone($order->get_billing_phone())
            ->setContactEmail($order->get_billing_email())
            ->setClientReference('Validating:'.$order->get_customer_id().':'.$order->get_id());

        $result = $this->client->validateJob($this->job);

        if(!empty($result->error)){
            //TODO cancelar pedido y pago
            $order->update_status( 'failed' );
            return array(
                'success' => false,
                'result' => __($result->message, 'stuart-integration')
            );
        }

        return array(
            'success' => true,
            'result' => __($result->message, 'stuart-integration')
        );
    }

    public function calculate_closer_shop_from_drop_off($addressTo){

        $addressesFromJson = null;
        $addressToJson = null;
        $lonAdressTo = null;
        $latAdressTo = null;
        $lonAdressFrom = null;
        $latAdressFrom = null;
        $_SESSION['pickup_closer'] = null;

        //TODO calculate the distance between all the shops
        for($i=1;$i<=4;$i++){
            if(!empty(get_option( 'pickup_address_'.$i)))
                $addressesFrom[$i] = get_option( 'pickup_address_'.$i);
        }

        if(!empty($this->here_key)){
            //Calculate distance with GOOGLE;
            foreach($addressesFrom as $key => $addressFrom){
                $calculateDistance[$key] = $this->getHereDistance($addressFrom, $addressTo);
                if(!is_numeric($calculateDistance[$key])){
                    return array(
                        'success' => false,
                        'result' => __($calculateDistance[$key], 'stuart-integration')
                    );
                }
            }
        }else{
            //Calculate distance with GOOGLE;
            foreach($addressesFrom as $key => $addressFrom){
                $calculateDistance[$key] = $this->getGoogleDistance($addressFrom, $addressTo, "K");
                if(!is_numeric($calculateDistance[$key])){
                    return array(
                        'success' => false,
                        'result' => __($calculateDistance[$key], 'stuart-integration')
                    );
                }
            }
        }

        $closer = min($calculateDistance);

        if(!$closer){
            return array(
                'success' => false,
                'result' => __("We can not find this drop off address", 'stuart-integration')
            );
        }

        $addressNum = array_search($closer, $calculateDistance);
        $_SESSION['pickup_closer'] = $addressNum;

        return array(
            'success' => true,
            'result' => $closer,
            'address' => $addressNum
        );
    }

    public function stuart_get_address_to( $woocommerce ) {

        $_SESSION['pickup_closer'] = null;

        $addressTo = $woocommerce->customer->get_shipping_address_1() . ', ' .
                    //$woocommerce->customer->get_shipping_address_2() . ' ' .
                    $woocommerce->customer->get_shipping_postcode()  . ', ' .
                    $woocommerce->customer->get_shipping_city()     . ' ' .
                    $woocommerce->customer->get_shipping_state();

        //Validar direcciones
        $validateAddress = $this->httpClient->performGet('/v2/addresses/validate', ['address' => $addressTo, 'type' => 'delivering']);

        if(!$validateAddress->success()){
            return array(
                'success' => false,
                'result' => __("This location is for delivery out of range or incorrect", 'stuart-integration')
            );
        }

        return array(
            'success' => true,
            'result' => $addressTo
        );
    }

    /**
     *
     * Author: CodexWorld
     * Function Name: getDistance()
     * $addressFrom => From address.
     * $addressTo => To address.
     * $unit => Unit type.
     *
     **/
    public function getGoogleDistance($addressFrom, $addressTo, $unit){
        //Change address format
        $formattedAddrFrom = str_replace(' ','+',$addressFrom);
        $formattedAddrTo = str_replace(' ','+',$addressTo);

        //Send request and receive json data
        $geocodeFrom = file_get_contents('https://maps.google.com/maps/api/geocode/json?address='.$formattedAddrFrom.'&sensor=false&key=' . $this->google_key );
        $outputFrom = json_decode($geocodeFrom);
        $geocodeTo = file_get_contents('https://maps.google.com/maps/api/geocode/json?address='.$formattedAddrTo.'&sensor=false&key=' . $this->google_key );
        $outputTo = json_decode($geocodeTo);

        //Get latitude and longitude from geo data
        if(!empty($outputFrom->results) && !empty($outputTo->results)){
            $latitudeFrom = $outputFrom->results[0]->geometry->location->lat;
            $longitudeFrom = $outputFrom->results[0]->geometry->location->lng;
            $latitudeTo = $outputTo->results[0]->geometry->location->lat;
            $longitudeTo = $outputTo->results[0]->geometry->location->lng;

            //Calculate distance from latitude and longitude
            $theta = $longitudeFrom - $longitudeTo;
            $dist = sin(deg2rad($latitudeFrom)) * sin(deg2rad($latitudeTo)) +  cos(deg2rad($latitudeFrom)) * cos(deg2rad($latitudeTo)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;
            $unit = strtoupper($unit);
            if ($unit == "K") {
                return ($miles * 1.609344); //km
            } else if ($unit == "N") {
                return ($miles * 0.8684).' nm';
            } else {
                return $miles.' mi';
            }
        }else{
            return $outputFrom->error_message;
        }

    }

    public function getHereDistance($addressFrom, $addressTo){

        //$auth1 = file_get_contents ( "https://geocoder.ls.hereapi.com/6.2/geocode.json?gen=9&apiKey=" . urlencode ( $this->here_key));
        $auth2 = json_decode ( file_get_contents ( "https://geocoder.ls.hereapi.com/6.2/geocode.json?gen=9&apiKey=" . urlencode ( $this->here_key) . "&searchtext=" . urlencode ( $addressTo)), true);

        if(isset($auth2["Response"]) && !empty($auth2["Response"])){
            $addressToJson = json_decode ( file_get_contents ( "https://geocoder.ls.hereapi.com/6.2/geocode.json?gen=9&apiKey=" . urlencode ( $this->here_key) . "&searchtext=" . urlencode ( $addressTo)), true);

            $lonAdressTo = $addressToJson["Response"]["View"][0]["Result"][0]["Location"]["DisplayPosition"]["Longitude"];
            $latAdressTo = $addressToJson["Response"]["View"][0]["Result"][0]["Location"]["DisplayPosition"]["Latitude"];

            $addressesFromJson = json_decode ( file_get_contents ( "https://geocoder.ls.hereapi.com/6.2/geocode.json?gen=9&apiKey=" . urlencode ( $this->here_key) . "&searchtext=" . urlencode ( $addressFrom)), true);
            $lonAdressFrom = $addressesFromJson["Response"]["View"][0]["Result"][0]["Location"]["DisplayPosition"]["Longitude"];
            $latAdressFrom = $addressesFromJson["Response"]["View"][0]["Result"][0]["Location"]["DisplayPosition"]["Latitude"];

            $route = json_decode ( file_get_contents ("https://route.ls.hereapi.com/routing/7.2/calculateroute.json?apiKey=" . urlencode($this->here_key) . "&waypoint0=geo!" . urlencode($latAdressTo) . "," . urlencode($lonAdressTo) . "&waypoint1=geo!" . urlencode($latAdressFrom) . "," . urlencode($lonAdressFrom) . "&routeattributes=wp,sm,sh,sc&mode=fastest;bicycle;motorway:-2", true));

            return $route->response->route[0]->summary->distance;
        }else{
            return "HERE API KEY incorrect";
        }
    }
}