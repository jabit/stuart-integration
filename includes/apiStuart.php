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

    public function stuart_create_job($order_id, $closer=null){

        if ( !$order_id ) return;

        if ( empty($closer) ){
            $closer = get_option( 'stuart_pickup_closer');
        }
            /*
        if ( !isset($_SESSION['pickup_closer']) && empty($_SESSION['pickup_closer']) ){
            $closer = get_option( 'stuart_pickup_closer');
            if ( empty($closer) ){
                return array(
                    'success' => false,
                    'result' => __('No pickup closer shop find', 'stuart-integration')
                );
            }
        }else{
            $closer = $_SESSION['pickup_closer'];
        }*/

        $order = wc_get_order( $order_id );

        $pickupAt = null;
        $now = new \DateTime('now', new DateTimeZone(get_option('timezone_string') != null ? get_option('timezone_string') : date_default_timezone_get()));
        $pickupAt = $now;
        $pickupAt->add(new \DateInterval('PT20M'));

        $this->client = new \Stuart\Client($this->httpClient);
        $this->job = new \Stuart\Job();

        //pick up
        $this->job->addPickup(get_option('pickup_address_'.$closer))
            ->setComment(get_option( 'stuart_pickup_details_'.$closer))
            ->setContactCompany(get_option( 'pickup_company'))
            ->setContactFirstName(get_option( 'pickup_first_name'))
            ->setContactLastName(get_option( 'pickup_last_name'))
            ->setContactPhone(get_option( 'pickup_phone_'.$closer ))
            ->setContactEmail(get_option( 'pickup_email'))
            ->setPickupAt($pickupAt);

        $commentDelivery = null;
        $commentOrderId = null;
        $commentNotes = null;
        $commentPhone = null;
        $comment = null;
        if(!empty($order->get_shipping_address_2()))
            $comment .= __('For the delivery', 'stuart-integration').': '.$order->get_shipping_address_2().', ';
        if(!empty($order->get_id()))
            $comment .= __('Order ID', 'stuart-integration').': '.$order->get_id().', ';
        if(!empty($order->get_billing_phone()))
            $comment .= __('Customer phone', 'stuart-integration').': '.$order->get_billing_phone().', ';
        if(!empty($order->get_customer_note()))
            $comment .= __('Notes', 'stuart-integration').': '.$order->get_customer_note();

        //Show order in stuart comment
        $item_data = null;
        $item_data .= __('LINK TO WOO ORDER', 'stuart-integration').': '.admin_url('post.php?post='.$order_id.'&action=edit');
        $item_data .= " | ";
        $item_data .= __('SHOP ORDER', 'stuart-integration').': ';

        foreach ( $order->get_items() as $key => $item ) {
            $data = $item->get_data();
            if(end($order->get_items()) == $item){
                $item_data .= $data['quantity'].' x '.$data['name'].PHP_EOL;
            }else{
                $item_data .= $data['quantity'].' x '.$data['name'].', '.PHP_EOL;
            }
        }

        $this->job->addDropOff($this->formatted_shipping_address($order))
            ->setPackageType('medium')
            ->setComment($comment)
            ->setContactFirstName($order->get_shipping_first_name())
            ->setContactLastName($order->get_shipping_last_name())
            ->setContactPhone($order->get_billing_phone())
            ->setContactEmail($order->get_billing_email())
            ->setPackageDescription($item_data)
            ->setClientReference($order->get_customer_id().'-'.$order->get_id());
        //->setClientReference($order->get_customer_id());
        //->setDropoffAt($dropoffAt);

        $result = $this->client->validateJob($this->job);

        $resultJob = false;
        if(isset($result->error) && !empty($result->error)){
            //TODO cancelar pedido y pago???
            $order->update_status( 'failed' );
            return array(
                'success' => false,
                'result' => __($result->error, 'stuart-integration')
            );
        }

        $resultJob = $this->client->createJob($this->job);
        update_option( 'stuart_pickup_closer', '' );
        return array(
            'success' => true,
            'result' => $resultJob->getId()
        );
    }

    public function splitString($cadena, $longitud) {
        // Inicializamos las variables
        $contador = 0;
        $texto = '';

        // Cortamos la cadena por los espacios
        $arrayTexto = explode(' ', $cadena);

        // Reconstruimos la cadena palabra a palabra mientras no sobrepasemos la longitud maxima
        while($longitud >= strlen($texto) + strlen($arrayTexto[$contador])) {
            $texto .= ' '.$arrayTexto[$contador];
            $contador++;
        }

        //añadimos los ... al final de la cadena si esta era mas larga que la longitud maximo
        if(strlen($cadena)>$longitud){
            $texto .= '...';
        }

        return trim($texto);
    }

    public function formatted_shipping_address($order)
    {

        if(!empty($order->get_shipping_address_1())){
            return $order->get_shipping_address_1() . ', ' .
                //$order->get_shipping_address_2() . ' ' .
                $order->get_shipping_postcode()  . ', ' .
                $order->get_shipping_city()     . ' ' .
                $order->get_shipping_state();
        }else{
            return $order->get_billing_address_1() . ', ' .
                //$order->get_shipping_address_2() . ' ' .
                $order->get_billing_postcode()  . ', ' .
                $order->get_billing_city()     . ' ' .
                $order->get_billing_state();
        }

    }

    public function stuart_validation($order_id) {

        global $woocommerce;
        if ( !$order_id ) return;
        $order = wc_get_order( $order_id );

        if(!empty($woocommerce->customer->get_shipping_address_1())){
            $addressTo = $woocommerce->customer->get_shipping_address_1() . ', ' .
                $woocommerce->customer->get_shipping_postcode()  . ', ' .
                $woocommerce->customer->get_shipping_city()     . ' ' .
                $woocommerce->customer->get_shipping_state();
        }else{
            $addressTo = $woocommerce->customer->get_billing_address_1() . ', ' .
                $woocommerce->customer->get_billing_postcode()  . ', ' .
                $woocommerce->customer->get_billing_city()     . ' ' .
                $woocommerce->customer->get_billing_state();
        }

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
        update_option( 'stuart_pickup_closer', $resultCloser['address'] );

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

        //error_log("este es el resultado de stuart $result");

        if(!empty($result->error)){
            //TODO cancelar pedido y pago
            $order->update_status( 'failed' );
            return array(
                'success' => false,
                'result' => __($result->message, 'stuart-integration')
            );
        }else{
            return array(
                'success' => true,
                'result' => __('Stuart validation success', 'stuart-integration'),
                'closer' => $resultCloser['address']
            );
        }

        /*
        return array(
            'success' => true,
            'result' => __($result->message, 'stuart-integration')
        );
        */
    }

    public function stuart_address_validation_without_order_id($address) {


        //Validar direcciones
        $validateAddress = $this->httpClient->performGet('/v2/addresses/validate', ['address' => $address, 'type' => 'delivering']);

        if(!$validateAddress->success()){
            //return $validateAddress;
            return array(
                'success' => false,
                'result' => __(json_decode($validateAddress->getBody())->message, 'stuart-integration')
            );
        }

        /*
        $resultCloser = $this->calculate_closer_shop_from_drop_off($address);

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
        $this->job->addDropOff($address)
            ->setPackageType('medium')
            //->setComment(__('For the delivery', 'stuart-integration').': '.$woocommerce->customer->get_shipping_address_2().', '.__('Order ID', 'stuart-integration').': '.$order->get_id().', '.__('Notes', 'stuart-integration').': '.$order->get_customer_note())
            ->setContactFirstName($address['shipping_first_name'])
            ->setContactLastName($address['shipping_last_name'])
            ->setContactPhone($address['billing_phone'])
            ->setContactEmail($address['billing_email'])
            ->setClientReference('Validating: check address');

        $result = $this->client->validateJob($this->job);

        if(!empty($result->error)){
            return array(
                'success' => false,
                'result' => __($result->message, 'stuart-integration')
            );
        }
*/
        return array(
            'success' => true,
            'result' => __('Address valid', 'stuart-integration')
        );

    }

    public function calculate_closer_shop_from_drop_off($addressTo){

        $addressesFromJson = null;
        $addressToJson = null;
        $lonAdressTo = null;
        $latAdressTo = null;
        $lonAdressFrom = null;
        $latAdressFrom = null;

        $closer = false;

        //TODO calculate the distance between all the shops
        for($i=1;$i<=4;$i++){
            if(!empty(get_option( 'pickup_address_'.$i)))
                $addressesFrom[$i] = get_option( 'pickup_address_'.$i);
        }

        if(!empty($addressesFrom)){

            if(!empty($this->here_key)){

                //Calculate distance with HERE;
                foreach($addressesFrom as $key => $addressFrom){
                    $addressValidate = $this->validateAddress($addressFrom);
                    if(isset($addressValidate['success']) && $addressValidate['success']){
                        $calculateDistance[$key] = $this->getHereDistance($addressFrom, $addressTo);
                        if(!is_numeric($calculateDistance[$key])){
                            return array(
                                'success' => false,
                                'result' => __($calculateDistance[$key], 'stuart-integration')
                            );
                        }
                    }
                }
            }else{
                //Calculate distance with GOOGLE;
                foreach($addressesFrom as $key => $addressFrom){
                    $addressValidate = $this->validateAddress($addressFrom);
                    if(isset($addressValidate['success']) && $addressValidate['success']){
                        $calculateDistance[$key] = $this->getGoogleDistance($addressFrom, $addressTo, "K");
                        if(!is_numeric($calculateDistance[$key])){
                            return array(
                                'success' => false,
                                'result' => __($calculateDistance[$key], 'stuart-integration')
                            );
                        }
                    }
                }
            }
            $closer = min($calculateDistance);
        }

        if(!$closer){
            return array(
                'success' => false,
                'result' => __("We can not find this drop off address", 'stuart-integration')
            );
        }

        $addressNum = array_search($closer, $calculateDistance);

        return array(
            'success' => true,
            'result' => $closer,
            'address' => $addressNum
        );
    }

    public function validateAddress($address){

        //Validar direcciones
        $validateAddress = $this->httpClient->performGet('/v2/addresses/validate', ['address' => $address, 'type' => 'delivering']);

        if(!$validateAddress->success()){
            return array(
                'success' => false,
                'result' => __("This location is for delivery out of range or incorrect", 'stuart-integration')
            );
        }

        return array(
            'success' => true
        );
    }

    public function stuart_get_address_to($address) {

        global $woocommerce;
        $_SESSION['pickup_closer'] = null;

        $addressTo = null;

        if(!empty($woocommerce->customer->get_shipping_address_1())){
            $addressTo = $woocommerce->customer->get_shipping_address_1() . ', ' .
                $woocommerce->customer->get_shipping_postcode()  . ', ' .
                $woocommerce->customer->get_shipping_city()     . ' ' .
                $woocommerce->customer->get_shipping_state();
        }else{
            $addressTo = $woocommerce->customer->get_billing_address_1() . ', ' .
                $woocommerce->customer->get_billing_postcode()  . ', ' .
                $woocommerce->customer->get_billing_city()     . ' ' .
                $woocommerce->customer->get_billing_state();
        }


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

    /*
    function order_review_format_shipping_address($address)
    {
        $ship = 'billing';
        if(isset($address['ship_to_different_address']) && $address['ship_to_different_address']){
            $ship = 'shipping';
        }
        return $address[$ship.'_address_1'] . ', ' .
            $address[$ship.'_postcode']  . ', ' .
            $address[$ship.'_city']     . ' ' .
            $address[$ship.'_state'];
    }
    */
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

        $addressToJson = json_decode(file_get_contents("https://geocoder.ls.hereapi.com/6.2/geocode.json?searchtext=" . urlencode ( $addressTo)."&gen=9&apiKey=" . urlencode ( $this->here_key)), true);

        if(isset($addressToJson["Response"]["View"][0]) && !empty($addressToJson["Response"]["View"][0])){

            $lonAdressTo = $addressToJson["Response"]["View"][0]["Result"][0]["Location"]["DisplayPosition"]["Longitude"];
            $latAdressTo = $addressToJson["Response"]["View"][0]["Result"][0]["Location"]["DisplayPosition"]["Latitude"];

            $addressesFromJson = json_decode(file_get_contents("https://geocoder.ls.hereapi.com/6.2/geocode.json?searchtext=" . urlencode ( $addressFrom)."&gen=9&apiKey=" . urlencode ( $this->here_key)), true);

            if(isset($addressesFromJson["Response"]["View"][0]) && !empty($addressesFromJson["Response"]["View"][0])) {

                $lonAdressFrom = $addressesFromJson["Response"]["View"][0]["Result"][0]["Location"]["DisplayPosition"]["Longitude"];
                $latAdressFrom = $addressesFromJson["Response"]["View"][0]["Result"][0]["Location"]["DisplayPosition"]["Latitude"];

                $route = json_decode(file_get_contents("https://route.ls.hereapi.com/routing/7.2/calculateroute.json?apiKey=" . urlencode($this->here_key) . "&waypoint0=geo!" . urlencode($latAdressTo) . "," . urlencode($lonAdressTo) . "&waypoint1=geo!" . urlencode($latAdressFrom) . "," . urlencode($lonAdressFrom) . "&routeattributes=sm&mode=fastest;bicycle;motorway:-2"), true);

                return $route['response']['route'][0]['summary']['distance'];

            }else{
                return _e( 'Drop off address incorrect', 'stuart-integration' );
            }
        }else{
            return _e( 'Pickup address incorrect', 'stuart-integration' );
        }
    }
}