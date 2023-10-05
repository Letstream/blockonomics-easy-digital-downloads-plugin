<?php

class BlockonomicsAPI
{
    const BASE_URL = 'https://www.blockonomics.co';
    const NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    const PRICE_URL = 'https://www.blockonomics.co/api/price';
    const ADDRESS_URL = 'https://www.blockonomics.co/api/address?only_xpub=true&get_callback=true';
    const SET_CALLBACK_URL = 'https://www.blockonomics.co/api/update_callback';
    const GET_CALLBACKS_URL = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';

    const BCH_BASE_URL = 'https://bch.blockonomics.co';
    const BCH_NEW_ADDRESS_URL = 'https://bch.blockonomics.co/api/new_address';
    const BCH_PRICE_URL = 'https://bch.blockonomics.co/api/price';
    const BCH_SET_CALLBACK_URL = 'https://bch.blockonomics.co/api/update_callback';
    const BCH_GET_CALLBACKS_URL = 'https://bch.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';
    
    public function __construct()
    {
        $this->api_key = $this->get_api_key();
    }

    public function get_api_key()
    {
        return edd_get_option('edd_blockonomics_api_key');
    }

    public function test_new_address_gen($crypto, $response)
    {
        $error_str = '';
        $callback_secret = edd_get_option("edd_blockonomics_callback_secret");
        $response = $this->new_address($callback_secret, $crypto, true);
        if ($response->response_code!=200){ 
            $error_str = $response->response_message;
        }
        return $error_str;
    }

    public function new_address($secret, $crypto, $reset=false)
    {
        if($reset)
        {
            $get_params = "?match_callback=$secret&reset=1";
        } 
        else
        {
            $get_params = "?match_callback=$secret";
        }
        if($crypto === 'btc'){
            $url = BlockonomicsAPI::NEW_ADDRESS_URL.$get_params;
        }else{
            $url = BlockonomicsAPI::BCH_NEW_ADDRESS_URL.$get_params;            
        }
        $response = $this->post($url, $this->api_key, '', 8);
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        if (wp_remote_retrieve_body($response))
        {
          $body = json_decode(wp_remote_retrieve_body($response));
          $responseObj->{'response_message'} = isset($body->message) ? $body->message : '';
          $responseObj->{'address'} = isset($body->address) ? $body->address : '';
        }
        return $responseObj;
    }

    public function get_price($currency, $crypto)
    {
        if($crypto === 'btc'){
            $url = BlockonomicsAPI::PRICE_URL. "?currency=$currency";
        }else{
            $url = BlockonomicsAPI::BCH_PRICE_URL. "?currency=$currency";
        }
        $response = $this->get($url);
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        if (wp_remote_retrieve_body($response))
        {
          $body = json_decode(wp_remote_retrieve_body($response));
          $responseObj->{'response_message'} = isset($body->message) ? $body->message : '';
          $responseObj->{'price'} = isset($body->price) ? $body->price : '';
        }
        return $responseObj->price;
    }

    public function update_callback($callback_url, $crypto, $xpub)
    {   
        if ($crypto === 'btc'){
            $url = BlockonomicsAPI::SET_CALLBACK_URL;
        }else{
            $url = BlockonomicsAPI::BCH_SET_CALLBACK_URL;
        }
    	$body = json_encode(array('callback' => $callback_url, 'xpub' => $xpub));
    	$response = $this->post($url, $this->api_key, $body);
        $responseObj = json_decode(wp_remote_retrieve_body($response));
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        return json_decode(wp_remote_retrieve_body($response));
    }

    public function get_callbacks($crypto)
    {
        if ($crypto === 'btc'){
            $url = BlockonomicsAPI::GET_CALLBACKS_URL;
        }else{
            $url = BlockonomicsAPI::BCH_GET_CALLBACKS_URL;
        }
        $response = $this->get($url, $this->api_key);
        return $response;
    }

    public function check_get_callbacks_response_code($response){
        $error_str = '';
        //TODO: Check This: WE should actually check code for timeout
        if (!wp_remote_retrieve_response_code($response)) {
            $error_str = __('Your server is blocking outgoing HTTPS calls', 'edd-blockonomics');
        }
        elseif (wp_remote_retrieve_response_code($response)==401)
            $error_str = __('API Key is incorrect', 'edd-blockonomics');
        elseif (wp_remote_retrieve_response_code($response)!=200)
            $error_str = $response->data;
        return $error_str;
    }

    public function check_get_callbacks_response_body ($response, $crypto){
        $error_str = '';
        $response_body = json_decode(wp_remote_retrieve_body($response));

        //if merchant doesn't have any xPubs on his Blockonomics account
        if (!isset($response_body) || count($response_body) == 0)
        {
            $error_str = __('Please add a new store on blockonomics website', 'edd-blockonomics');
        }
        //if merchant has at least one xPub on his Blockonomics account
        elseif (count($response_body) >= 1)
        {
            $error_str = $this->examine_server_callback_urls($response_body, $crypto);
        }
        return $error_str;
    }

    // checks each existing xpub callback URL to update and/or use
    public function examine_server_callback_urls($response_body, $crypto)
    {
        $callback_secret = edd_get_option("edd_blockonomics_callback_secret");
        $api_url = add_query_arg('edd-listener', 'blockonomics', home_url() );
        $wordpress_callback_url = add_query_arg('secret', $callback_secret, $api_url);
        $base_url = preg_replace('/https?:\/\//', '', $api_url);
        $available_xpub = '';
        $partial_match = '';
        //Go through all xpubs on the server and examine their callback url
        foreach($response_body as $one_response){
            $server_callback_url = isset($one_response->callback) ? $one_response->callback : '';
            $server_base_url = preg_replace('/https?:\/\//', '', $server_callback_url);
            $xpub = isset($one_response->address) ? $one_response->address : '';
            if(!$server_callback_url){
                // No callback
                $available_xpub = $xpub;
            }else if($server_callback_url == $wordpress_callback_url){
                // Exact match
                return '';
            }
            else if(strpos($server_base_url, $base_url) === 0 ){
                // Partial Match - Only secret or protocol differ
                $partial_match = $xpub;
            }
        }
        // Use the available xpub
        if($partial_match || $available_xpub){
          $update_xpub = $partial_match ? $partial_match : $available_xpub;
            $response = $this->update_callback($wordpress_callback_url, $crypto, $update_xpub);
            if ($response->response_code != 200) {
                return $response->message;
            }
            return '';
        }
        // No match and no empty callback
        $error_str = __("Please add a new store on blockonomics website", 'edd-blockonomics');
        return $error_str;
    }

    public function check_callback_urls_or_set_one($crypto, $response) 
    {
        //If BCH enabled and API Key is not set: give error
        if(!$this->api_key && $crypto === 'bch'){
            $error_str = __('Set the API Key or disable BCH', 'blockonomics-bitcoin-payments');
            return $error_str;
        }
        //chek the current callback and detect any potential errors
        $error_str = $this->check_get_callbacks_response_code($response);
        if(!$error_str){
            //if needed, set the callback.
            $error_str = $this->check_get_callbacks_response_body($response, $crypto);
        }
        return $error_str;
    }

    private function get($url, $api_key = '')
    {
    	$headers = $this->set_headers($api_key);

        $response = wp_remote_get( $url, array(
            'method' => 'GET',
            'headers' => $headers
            )
        );

        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo __("Something went wrong", 'edd-blockonomics').": ".$error_message;
        }else{
            return $response;
        }
    }

    private function post($url, $api_key = '', $body = '', $timeout = '')
    {
    	$headers = $this->set_headers($api_key);

        $data = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body
            );
        if($timeout){
            $data['timeout'] = $timeout;
        }

        $response = wp_remote_post( $url, $data );
        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo "Something went wrong: $error_message";
        }else{
            return $response;
        }
    }

    private function set_headers($api_key)
    {
    	if($api_key){
    		return 'Authorization: Bearer ' . $api_key;
    	}else{
    		return '';
    	}
    }

    public function getSupportedCurrencies() {
        return array(
              'btc' => array(
                    'code' => 'btc',
                    'name' => 'Bitcoin',
                    'uri' => 'bitcoin'
              ),
              'bch' => array(
                    'code' => 'bch',
                    'name' => 'Bitcoin Cash',
                    'uri' => 'bitcoincash'
              )
          );
    }

    /*
     * Get list of active crypto currencies
     */
    public function getActiveCurrencies() {
        $active_currencies = array();
        $blockonomics_currencies = $this->getSupportedCurrencies();
        foreach ($blockonomics_currencies as $code => $currency) {
            $enabled = edd_get_option('blockonomics_'.$code);
            if($enabled || ($code === 'btc' && $enabled === false )){
                $active_currencies[$code] = $currency;
            }
        }
        return $active_currencies;
    }

    // Runs when the Blockonomics    Setup button is clicked
    // Returns any errors or false if no errors
    public function testSetup()
    {
        // return $this->test_one_crypto();
        $test_results = array();
        $active_cryptos = $this->getActiveCurrencies();
        foreach ($active_cryptos as $code => $crypto) {
            $test_results[$code] = $this->test_one_crypto($code);
        }
        return $test_results;
    }

    public function test_one_crypto($crypto)
    {
        $response = $this->get_callbacks($crypto);
        $error_str = $this->check_callback_urls_or_set_one($crypto, $response);
        if (!$error_str)
        {
            //Everything OK ! Test address generation
            $error_str = $this->test_new_address_gen($crypto, $response);
        }
        if($error_str) {
            return $error_str;
        }
        // No errors
        return false;
    }

    public function get_edd_url($query) {
        $final_query = array_merge($query, array('edd-listener' => 'blockonomics'));
        return add_query_arg($final_query, home_url());
    }

    public function get_order_checkout_url($order_id){
        $active_cryptos = $this->getActiveCurrencies();
        // Check if more than one crypto is activated
        $order_hash = $this->encrypt_hash($order_id);
        if (count($active_cryptos) > 1) {
            $order_url = $this->get_edd_url(array('select_crypto'=>$order_hash));
        } elseif (count($active_cryptos) === 1) {
            $order_url = $this->get_edd_url(array('show_order'=>$order_hash, 'crypto'=> array_keys($active_cryptos)[0]));
        } elseif (count($active_cryptos) === 0) {
            $order_url = $this->get_edd_url(array('crypto' => 'empty'));
        } 
        return $order_url;
    }

    /**
     * Encrypts a string using the application secret. This returns a hex representation of the binary cipher text
     *
     * @param  $input
     * @return string
     */
    public function encrypt_hash($input)
    {
        $encryption_algorithm = 'AES-128-CBC';
        $hashing_algorith = 'sha256';
        $secret = edd_get_option("edd_blockonomics_callback_secret");;
        $key = hash($hashing_algorith, $secret, true);
        $iv = substr($secret, 0, 16);

        $cipherText = openssl_encrypt(
            $input,
            $encryption_algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return bin2hex($cipherText);
    }

    /**
     * Decrypts a string using the application secret.
     *
     * @param  $hash
     * @return string
     */
    public function decrypt_hash($hash)
    {
        $encryption_algorithm = 'AES-128-CBC';
        $hashing_algorith = 'sha256';
        $secret = edd_get_option("edd_blockonomics_callback_secret");;
        // prevent decrypt failing when $hash is not hex or has odd length
        if (strlen($hash) % 2 || !ctype_xdigit($hash)) {
            echo __("Error: Incorrect Hash. Hash cannot be validated.", 'edd-blockonomics');
            exit();
        }

        // we'll need the binary cipher
        $binaryInput = hex2bin($hash);
        $iv = substr($secret, 0, 16);
        $cipherText = $binaryInput;
        $key = hash($hashing_algorith, $secret, true);

        $decrypted = openssl_decrypt(
            $cipherText,
            $encryption_algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if (empty($this->get_order($decrypted))) {
            echo __("Error: Incorrect hash. EDD order not found.", 'edd-blockonomics');
            exit();
        }

        return $decrypted;
    }

    public function get_order($order_id) {
        $blockonomics_orders = edd_get_option('edd_blockonomics_orders');
    
        if (isset($blockonomics_orders[$order_id])) {
          return $blockonomics_orders[$order_id];
        }
        return NULL;
    }
    
    //This function needs to be replaced with the database table as this method to get order from address is inefficient
    public function get_orderId_by_address($address) {
        $blockonomics_orders = edd_get_option('edd_blockonomics_orders');
        
        foreach ($blockonomics_orders as $order_id => $order) {
            if ($order['address'] == $address){
                return $order_id;
            }
        }
        return NULL;
    }

}
