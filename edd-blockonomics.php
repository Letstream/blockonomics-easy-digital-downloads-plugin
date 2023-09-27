<?php
/**
 * Plugin Name: EDD - Blockonomics
 * Description: Accept Bitcoin Payments on your Easy Digital Downloads powered website with Blockonomics
 * Version: 1.3.8
 * Author: Blockonomics
 * Author URI: https://www.blockonomics.co
 * License: MIT
 * Text Domain: edd-blockonomics
 * Domain Path: /languages/
 */

/*  Copyright 2017 Blockonomics Inc.

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_Blockonomics
{
  public function __construct()
  {
    if( ! function_exists( 'edd_get_option' ) )
    {
      return;
    }

    if( class_exists( 'EDD_License' ) && is_admin() )
    {
      $license = new EDD_License( __FILE__, 'Blockonomics Payment Gateway', '1.0.0', 'Blockonomics' );
    }

    $this->includes();
    $this->generate_secret_and_callback();

    add_action( 'edd_gateway_blockonomics',         array( $this, 'process_payment' ) );
    add_action( 'init',                         array( $this, 'listener' ) );
    add_action( 'edd_blockonomics_cc_form',         '__return_false' );
    add_action( 'wp_ajax_testsetup', array( $this,'edd_blockonomics_testsetup') );
    add_filter( 'edd_payment_gateways',         array( $this, 'register_gateway' ) );
    add_filter( 'edd_currencies',               array( $this, 'currencies' ) );
    add_filter( 'edd_sanitize_amount_decimals', array( $this, 'btc_decimals' ) );
    add_filter( 'edd_format_amount_decimals',   array( $this, 'btc_decimals' ) );
    add_filter( 'edd_settings_gateways',        array( $this, 'settings' ) );
    add_filter( 'edd_settings_sections_gateways', array( $this, 'register_gateway_section') );
    add_filter( 'edd_accepted_payment_icons',  array($this, 'pw_edd_payment_icon'));
    add_filter( 'edd_view_order_details_payment_meta_after', array( $this, 'action_edd_view_order_details_payment_meta_after'), 10, 1 );
  }

  public function includes()
  {
    if( ! class_exists( 'BlockonomicsAPI' ) )
    {
      require_once( plugin_dir_path( __FILE__ ) . 'php/Blockonomics.php' );
    }
  }

  public function action_edd_view_order_details_payment_meta_after( $payment_id ) 
  {
    $payment = new EDD_Payment( $payment_id );
    $meta_data = $payment->get_meta();
    if ( !empty($meta_data['blockonomics_txid']) )
    {
?>
    <div class="edd-order-tx-id edd-admin-box-inside">
      <p>
        <span class="label"><?php _e( 'Bitcoin Transaction ID:', 'edd-blockonomics' ); ?></span>&nbsp;
        <span><?php echo $meta_data['blockonomics_txid']; ?></span>
      </p>
    </div>
<?php
    }

    if ( !empty($meta_data['bitcoin_address']) )
    {
?>
    <div class="edd-order-tx-id edd-admin-box-inside">
      <p>
        <span class="label"><?php _e( 'Bitcoin Address:', 'edd-blockonomics' ); ?></span>&nbsp;
        <span><?php echo $meta_data['bitcoin_address']; ?></span>
      </p>
    </div>
<?php
    }

    if ( !empty($meta_data['expected_btc_amount']) )
    {
?>
    <div class="edd-order-tx-id edd-admin-box-inside">
      <p>
        <span class="label"><?php _e( 'Expected BTC Amount:', 'edd-blockonomics' ); ?></span>&nbsp;
        <span><?php echo $meta_data['expected_btc_amount']; ?></span>
      </p>
    </div>
<?php
    }

    if ( !empty($meta_data['paid_btc_amount']) )
    {
?>
    <div class="edd-order-tx-id edd-admin-box-inside">
      <p>
        <span class="label"><?php _e( 'Actual BTC Amount:', 'edd-blockonomics' ); ?></span>&nbsp;
        <span><?php echo $meta_data['paid_btc_amount']; ?></span>
      </p>
    </div>
<?php
    }
  }
  
  public function edd_blockonomics_testsetup(){
      $blockonomics = new BlockonomicsAPI;
      $setup_errors_array = $blockonomics->testSetup();
      $btc_error_str = isset($setup_errors_array['btc']) ? $setup_errors_array['btc'] : false;
      $bch_error_str = isset($setup_errors_array['bch']) ? $setup_errors_array['bch'] : false;
      $return = new stdClass();
      if($btc_error_str || $bch_error_str)
      {
        $return->type = 'error';
        if($btc_error_str){
          $return->btc_message = "BTC: " . $btc_error_str . __('<p>For more information, please consult <a href="https://blockonomics.freshdesk.com/support/solutions/articles/33000215104-troubleshooting-unable-to-generate-new-address" target="_blank">this troubleshooting article</a></p>', 'edd-blockonomics');
        }
        if($bch_error_str){
          $return->bch_message = "BCH: " . $bch_error_str . __('<p>For more information, please consult <a href="https://blockonomics.freshdesk.com/support/solutions/articles/33000215104-troubleshooting-unable-to-generate-new-address" target="_blank">this troubleshooting article</a></p>', 'edd-blockonomics');
        }
        echo json_encode($return);
      }
      else
      {
        $return->type = 'updated';
        $active_cryptos = $blockonomics->getActiveCurrencies();
        foreach ($active_cryptos as $code => $crypto) {
          $return->{$code . "_message"} = strtoupper($code) . __(': Congrats ! Setup is all done', 'edd-blockonomics');
        }
        echo json_encode($return);
      }
      wp_die();
  }

  public function register_gateway( $gateways )
  {

    $gateways['blockonomics'] = array(
      'checkout_label'  => __( 'Bitcoin', 'edd-blockonomics' ),
      'admin_label'     => __( 'Blockonomics', 'edd-blockonomics' ),
      'supports'        => array( 'buy_now' )
    );

    return $gateways;
  }

  function pw_edd_payment_icon($icons)
  {
    $icon_url = plugins_url('img/bitcoin.png', __FILE__);
    $icons[$icon_url] = 'Bitcoin';
    return $icons;
  }

  public function register_gateway_section( $gateway_sections )
  {
    $gateway_sections['blockonomics'] = __( 'Blockonomics', 'edd-blockonomics' );
    return $gateway_sections;
  }

  function generate_secret_and_callback($generate_new = false)
  {
    $callback_secret = edd_get_option('edd_blockonomics_callback_secret', '');
    if ( empty( $callback_secret) || $generate_new )
    {
      $callback_secret = sha1(openssl_random_pseudo_bytes(20));
      edd_update_option("edd_blockonomics_callback_secret", $callback_secret);
    }

    $callback_url = add_query_arg( array( 'edd-listener' => 'blockonomics', 'secret' => $callback_secret ), home_url() );
    edd_update_option('edd_blockonomics_callback_url', $callback_url);
  }

  public function process_payment( $purchase_data )
  {
    global $edd_options;

    $api_key = trim(edd_get_option('edd_blockonomics_api_key', ''));

    // Collect payment data
    $payment_data = array(
      'price'         => $purchase_data['price'],
      'date'          => $purchase_data['date'],
      'user_email'    => $purchase_data['user_email'],
      'purchase_key'  => $purchase_data['purchase_key'],
      'currency'      => edd_get_currency(),
      'downloads'     => $purchase_data['downloads'],
      'user_info'     => $purchase_data['user_info'],
      'cart_details'  => $purchase_data['cart_details'],
      'gateway'       => 'blockonomics',
      'status'        => 'pending'
    );

    // Record the pending payment
    $payment_id = edd_insert_payment( $payment_data );

    // Check payment
    if ( ! $payment_id )
    {
      // Record the error
      edd_record_gateway_error( __( 'Payment Error', 'edd-blockonomics' ), sprintf( __( 'Payment creation failed, Payment data: %s', 'edd-blockonomics' ), json_encode( $payment_data ) ), $payment_id );
      // Problems? send back
      edd_send_back_to_checkout( '?payment-mode=blockonomics' );

    }
    else
    {
      try
      {
        $callback_secret = trim(edd_get_option('edd_blockonomics_callback_secret', ''));
        $blockonomics = new BlockonomicsAPI;
        $active_cryptos = $blockonomics->getActiveCurrencies();
        $blockonomics_orders = edd_get_option('edd_blockonomics_orders');
        $order = array(
          'value'              => $purchase_data['price'],
          'satoshi'            => NULL,
          'currency'           => NULL,
          'address'            => NULL,
          'status'             => -1,
          'timestamp'          => time(),
          'txid'               => ''
        );
        $this->update_order($payment_id, $order);
        $order_hash = $blockonomics->encrypt_hash($payment_id);
        // Update post parameters to make them available in the listner method.
        update_post_meta($payment_id, 'blockonomics_address', $order_hash);
        $order_url = $this->get_edd_url(array('select_crypto'=>$order_hash));
        wp_redirect($order_url);
        exit;
      }
      catch ( Blockonomics_Exception $e )
      {
        $error  = json_decode( $e->getResponse() );

        if ( isset( $error->errors ) && is_array( $error->errors ) )
        {
          foreach( $error->errors as $error )
          {
            edd_set_error( 'edd_blockonomics_exception', sprintf( __( 'Error: %s', 'edd-blockonomics' ), $error ) );
          }
        }
        elseif( isset( $error->error ) )
        {
          edd_set_error( 'edd_blockonomics_exception', $error->error );
        }

        edd_send_back_to_checkout( '?payment-mode=blockonomics' );
      }
    }
  }

  private function displayError(){
    $unable_to_generate = __('<h1>Unable to generate address</h1><p> Note for site webmaster: ', 'edd-blockonomics');
    $error_msg = 'Please login to your admin panel, navigate to Downloads > Settings > Payment Gateways [ Blockonomics ] and click <i>Test Setup</i> to diagnose the issue</p>';
    $error_message = $unable_to_generate . $error_msg;
    echo $error_message;
  }

  function update_callback_url($callback_url, $xPub, $blockonomics)
  {
    $blockonomics->update_callback(
      edd_get_option('edd_blockonomics_api_key'),
      $callback_url,
      $xPub
    );
  }

  public function is_order_underpaid($order, $paid_amount){
    // Return TRUE only if there has been a payment which is less than required.
    $underpayment_slack = edd_get_option("edd_blockonomics_underpayment_slack", 0)/100 * $order['satoshi'];
    $is_order_underpaid = ($order['satoshi'] - $underpayment_slack > $paid_amount && !empty($paid_amount)) ? TRUE : FALSE;
    return $is_order_underpaid;
}

  public function listener()
  {
    $blockonomics = new BlockonomicsAPI;
    $listener = htmlspecialchars(isset($_GET['edd-listener']) ? $_GET['edd-listener'] : '');
    if( $listener != 'blockonomics' )
    {
      return;
    }

    $action = htmlspecialchars(isset($_REQUEST['action']) ? $_REQUEST['action'] : '');
    if( !empty($action) )
    {
      $settings_page = admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=blockonomics');
      if($action == "update_callback")
      {
        $this->generate_secret_and_callback(true);
        wp_redirect($settings_page);
        exit;
      }
      
      
    }

    $select_crypto = isset($_REQUEST['select_crypto']) ? $_REQUEST['select_crypto'] : '';
    
    if ($select_crypto) {
      $active_cryptos = $blockonomics->getActiveCurrencies();
        // Check if more than one crypto is activated
      if (count($active_cryptos) > 1) {
        $this->enqueue_stylesheets();
        include plugin_dir_path(__FILE__)."templates/blockonomics_crypto_options.php";
        exit();
      }elseif (count($active_cryptos) === 1) {
        $order_url = $this->get_edd_url(array('show_order'=>$select_crypto, 'crypto'=> array_keys($active_cryptos)[0]));
        wp_redirect($order_url);
        exit();        
      }elseif (count($active_cryptos) === 0) {
        $order_url = $this->get_edd_url(array('crypto' => 'empty'));
        wp_redirect($order_url);
        exit();
      }
      
    }
    $order_hash = isset($_REQUEST['show_order']) ? $_REQUEST['show_order'] : '';
    $crypto = isset($_REQUEST['crypto']) ? $_REQUEST['crypto'] : '';

    if ($crypto === "empty") {
      include plugin_dir_path(__FILE__)."templates/blockonomics_no_crypto_selected.php";
    }elseif ($order_hash && $crypto) {
      $order = $this->process_order($order_hash, $crypto);
      $active_cryptos = $blockonomics->getActiveCurrencies();
      $order_amount = $this->fix_displaying_small_values($order["satoshi"]);

      $context = array(
        "order_id" => $blockonomics->decrypt_hash($order_hash),
        "order" => $order,
        "address" => $order['address'],
        "crypto" => $active_cryptos[$crypto],
        "order_amount" => $order_amount,
        "payment_uri" => $this->get_crypto_payment_uri($active_cryptos[$crypto], $order['address'], $order_amount),
        "crypto_rate_str" => $this->get_crypto_rate_from_params($order['value'], $order['satoshi'])
      );
      
      $this->enqueue_stylesheets();
      $this->enqueue_scripts();

      $context['script'] = $this->get_checkout_script($context);
      
      include plugin_dir_path(__FILE__)."templates/blockonomics_checkout.php";
    }

    $order_id_hash = isset($_REQUEST['finish_order']) ? $_REQUEST['finish_order'] : '';
    if ($order_id_hash)
    {
      $order_id = $blockonomics->decrypt_hash($order_id_hash);
      $order = $blockonomics->get_order($order_id);
      wp_redirect(edd_get_success_page_uri());
      exit;
    }
    
    $order_id_hash = isset($_REQUEST['get_amount']) ? $_REQUEST['get_amount'] : '';
    
    if ($order_id_hash)
    {
      $this->get_order_amount_info($order_id_hash, $crypto);
    }

    try
    {
      $callback_secret = edd_get_option("edd_blockonomics_callback_secret");
      $secret = htmlspecialchars(isset($_REQUEST['secret']) ? $_REQUEST['secret'] : '');

      if ($callback_secret  && $callback_secret == $secret)
      {
        $addr = htmlspecialchars(isset($_REQUEST['addr']) ? $_REQUEST['addr'] : '');
        $order_id = $blockonomics->get_orderId_by_address($addr);

        if ($order_id)
        {
          $order = $blockonomics->get_order($order_id);
          $status = intval(htmlspecialchars(isset($_REQUEST['status']) ? $_REQUEST['status'] : ''));
          $existing_status = $order['status'];
          $time_period = edd_get_option("edd_blockonomics_timeperiod", 10) *60;
          $payment = new EDD_Payment( $order_id );
          $meta_data = $payment->get_meta();
          $network_confirmations = edd_get_option("edd_blockonomics_confirmations", 2);
          if($network_confirmations == 'zero'){
            $network_confirmations = 0;
          }

          if ($status >= $network_confirmations && !isset($meta_data['paid_btc_amount']))
          {
            $value = intval(htmlspecialchars(isset($_REQUEST['value']) ? $_REQUEST['value'] : ''));
            $meta_data['paid_btc_amount'] = $value/1.0e8;
            $payment->update_meta( '_edd_payment_meta', $meta_data ); 
      
            if ($order['satoshi'] > $value && $this->is_order_underpaid($order, $value))
            {
              $status = -2; //Payment error , amount not matching
              edd_insert_payment_note($order_id, __('Paid BTC amount less than expected.','edd-blockonomics'));
              edd_update_payment_status($order_id, 'failed');
            }
            else
            {
              if ($order['satoshi'] < $value)
              {
                edd_insert_payment_note($order_id, __('Overpayment of BTC amount', 'edd-blockonomics'));
              }

              edd_insert_payment_note($order_id, __('Payment completed', 'edd-blockonomics'));
              edd_update_payment_status($order_id, 'publish' );
            }
          }

          $order['txid'] =  htmlspecialchars(isset($_REQUEST['txid']) ? $_REQUEST['txid'] : '');
          $order['status'] = $status;
      
          if ($existing_status == -1)
          {
            $payment = new EDD_Payment( $order_id );
            $meta_data = $payment->get_meta();
            $meta_data['blockonomics_txid'] = $order['txid'];
            $meta_data['expected_btc_amount'] = $order['satoshi']/1.0e8;
            $meta_data['bitcoin_address'] =  $order['address'];
            $payment->update_meta( '_edd_payment_meta', $meta_data ); 
          }
      
          $this->update_order($order_id, $order);
        }
      } else {
        die('Incorrect Secret');
      }
    }
    catch ( Blockonomics_Exception $e )
    {
      $error = json_decode( $e->getResponse() );

      if( isset( $error->errors ) )
      {
        foreach( $error->errors as $error )
        {
          edd_record_gateway_error( __( 'Blockonomics Error', 'edd-blockonomics' ), 'Message: ' . $error );
        }
      } elseif( isset( $error->error ) )
      {
        edd_record_gateway_error( __( 'Blockonomics Error', 'edd-blockonomics' ), 'Message: ' . $error->error );
      }

      die('blockonomics exception error');
    }
  }

  public function currencies( $currencies )
  {
    $currencies['BTC'] = __( 'Bitcoin', 'edd-blockonomics' );
    return $currencies;
  }

  function btc_decimals( $decimals = 2 )
  {
    global $edd_options;
    $currency = edd_get_currency();

    switch ( $currency )
    {
    case 'BTC' :
      $decimals = 8;
      break;
    }

    return $decimals;
  }


  public function settings( $settings )
  {
    $callback_update_url = add_query_arg(array( 'edd-listener' => 'blockonomics', 'action' => 'update_callback') ,home_url());
    $callback_refresh = __( 'CALLBACK URL', 'edd-blockonomics' ).'<a href="'.$callback_update_url.'"
      id="generate-callback" style="font:400 20px/1 dashicons;margin-left: 7px; top: 4px;position:relative;text-decoration: none;" title="Generate New Callback URL">&#xf463;<a>';

    //$settings_page_testsetup = add_query_arg(array( 'edd-listener' => 'blockonomics', 'action' => 'test_setup') ,home_url());
    $settings_page = admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=blockonomics');
    $test_setup = '<p id="testsetup_msg"><b><i>'.__('Use below button to test the configuration.', 'edd-blockonomics').'</i></b></p>
      <p> <a id="edd-blockonomics-test-setup"  href="javascript:testSetupFunc();" class="button button-small" style="max-width:90px;">Test Setup</a> </p>

      <script type="text/javascript">
      var api_key = document.getElementsByName("edd_settings[edd_blockonomics_api_key]")[0].getAttribute(\'value\');

      if(api_key.length == 0)
      {
        var p_element = document.createElement( "p" );
        p_element.innerHTML = "You are few clicks away from accepting bitcoin payments</p><p>Click on <b>Get Started for Free</b> on <a href=\'https://www.blockonomics.co/merchants\' target=\'_blank\'>Blockonomics Merchants</a>. Complete the Wizard, Copy the API Key when shown here";
        var setting_table = document.getElementsByTagName("table")[0];
        setting_table.insertBefore(p_element, setting_table.childNodes[0]);
      }

      var testSetupFunc = function() 
      {
        var current_api_key = document.getElementsByName("edd_settings[edd_blockonomics_api_key]")[0].value;
        if( (current_api_key == api_key && api_key.length == 0 ) 
                || current_api_key != api_key ) 
        {
          if(document.getElementById("setting-error-edd_blockonomics_api_key_changed") == null) 
          {
            /* create notice div */
            var div = document.createElement( "div" );
            div.classList.add( "error", "settings-warning", "notice", "is-dismissible" );
            div.setAttribute( "id", "setting-error-edd_blockonomics_api_key_changed" );

            /* create paragraph element to hold message */
            var p = document.createElement( "p" );

            /* Add message text */
            if( current_api_key == api_key && api_key.length == 0)
            {
              p.innerHTML = "<b>'.__('Please enter your Blockonomics API key and save changes.', 'edd-blockonomics').'</b>";
            }
            else
            {
              p.innerHTML = "<b>'.__('API Key has changed. Click on Save Changes first.', 'edd-blockonomics').'</b>";
            }
            div.appendChild( p );

            /* Create Dismiss icon */
            var b = document.createElement( "button" );
            b.setAttribute( "type", "button" );
            b.classList.add( "notice-dismiss" );

            /* Add screen reader text to Dismiss icon */
            var bSpan = document.createElement( "span" );
            bSpan.classList.add( "screen-reader-text" );
            bSpan.appendChild( document.createTextNode( "Dismiss this notice." ) );
            b.appendChild( bSpan );

            /* Add Dismiss icon to notice */
            div.appendChild( b );

            /* Insert notice in test msg div */
            var test_msg = document.getElementById( "testsetup_msg" );
            test_msg.appendChild(div);

            /* Make the notice dismissable when the Dismiss icon is clicked */
            b.addEventListener( "click", function () 
            {
              div.parentNode.removeChild( div );
            });
          }
        }
        else 
        {
        	var xhr = new XMLHttpRequest();
          xhr.open("POST", "'. admin_url('admin-ajax.php') .'", true);
          xhr.setRequestHeader(\'Content-Type\', \'application/x-www-form-urlencoded;\');
          xhr.send("action=testsetup");
          xhr.onload = function() {

            response = JSON.parse(this.response);
            if(response.btc_message){
              /* create notice div */
              var div = document.createElement( "div" );
              div.classList.add( response.type, "settings-warning", "notice", "is-dismissible" );
              div.setAttribute( "id", "setting-error-edd_blockonomics_api_key_changed" );

              /* create paragraph element to hold message */
              var p = document.createElement( "p" );

              /* Add message text */
              p.innerHTML = "<b>"+response.btc_message+"</b>";
              div.appendChild( p );

              /* Create Dismiss icon */
              var b = document.createElement( "button" );
              b.setAttribute( "type", "button" );
              b.classList.add( "notice-dismiss" );

              /* Add screen reader text to Dismiss icon */
              var bSpan = document.createElement( "span" );
              bSpan.classList.add( "screen-reader-text" );
              bSpan.appendChild( document.createTextNode( "Dismiss this notice." ) );
              b.appendChild( bSpan );

              /* Add Dismiss icon to notice */
              div.appendChild( b );

              /* Insert notice in test msg div */
              var test_msg = document.getElementById( "testsetup_msg" );
              test_msg.appendChild(div);

              /* Make the notice dismissable when the Dismiss icon is clicked */
              b.addEventListener( "click", function () 
              {
                div.parentNode.removeChild( div );
                });
            }
            if(response.bch_message){
              /* create notice div */
              var div2 = document.createElement( "div" );
              div2.classList.add( response.type, "settings-warning", "notice", "is-dismissible" );
              div2.setAttribute( "id", "setting-error-edd_blockonomics_api_key_changed" );

              /* create paragraph element to hold message */
              var p2 = document.createElement( "p" );

              /* Add message text */
              p2.innerHTML = "<b>"+response.bch_message+"</b>";
              div2.appendChild( p2 );

              /* Create Dismiss icon */
              var b2 = document.createElement( "button" );
              b2.setAttribute( "type", "button" );
              b2.classList.add( "notice-dismiss" );

              /* Add screen reader text to Dismiss icon */
              var bSpan2 = document.createElement( "span" );
              bSpan2.classList.add( "screen-reader-text" );
              bSpan2.appendChild( document.createTextNode( "Dismiss this notice." ) );
              b2.appendChild( bSpan2 );

              /* Add Dismiss icon to notice */
              div2.appendChild( b2 );

              /* Insert notice in test msg div */
              var test_msg2 = document.getElementById( "testsetup_msg" );
              test_msg2.appendChild(div2);

              /* Make the notice dismissable when the Dismiss icon is clicked */
              b2.addEventListener( "click", function () 
              {
                div2.parentNode.removeChild( div2 );
                });
            }
          }
        }
      };
</script>
';

    $advanced_settings = '<p id="advanced_title_closed" class="bnomics-options-bold">
        <a href="javascript:show_advanced()">'.__('Advanced Settings', 'edd-blockonomics').'&#9660;</a>
      </p>
      <p id="advanced_title_open" class="bnomics-options-bold">
        <a href="javascript:show_basic()">'.__('Advanced Settings', 'blockonomics-bitcoin-payments').' &#9650;</a>
      </p>
      
      <script>
        let advanced_title_closed = document.getElementById("advanced_title_closed");
        let advanced_title_open = document.getElementById("advanced_title_open");

        function show_advanced() {
          advanced_title_closed.style.display = "none";
          advanced_title_open.style.display = "block";
          document.querySelectorAll(".edd-blockonomics-advanced").forEach(ele => ele.style.display = "table-row");
        }
        function show_basic() {
          advanced_title_closed.style.display = "block";
          advanced_title_open.style.display = "none";
          document.querySelectorAll(".edd-blockonomics-advanced").forEach(ele => ele.style.display = "none");
        }

        document.addEventListener("DOMContentLoaded", function(event) {
          show_basic();
        });

      </script>
    ';


    $blockonomics_settings = array(
      array(
        'id'      => 'edd_blockonomics_api_key',
        'name'    => __( 'BLOCKONOMICS API KEY', 'edd-blockonomics' ),
        'type'    => 'text'
      ),
      array(
        'id'      => 'edd_blockonomics_callback_url',
        'name'    => $callback_refresh,
        'readonly' => true,
        'type'    => 'text'
      ),
      array(
        'id'      => 'blockonomics_btc',
        'name'    =>  __('Bitcoin (BTC)', 'edd-blockonomics'),
        'type'    => 'checkbox',
      ),
      array(
        'id'      => 'blockonomics_bch',
        'name'    =>  __("Bitcoin Cash (BCH)", 'edd-blockonomics'),
        'type'    => 'checkbox',
      ),
      array(
        'id'      => 'edd_blockonomics_advanced_settings',
        'name'    => $advanced_settings,
        'readonly' => true,
        'type'    => 'advanced_settings',
      ),
      array(
        'id'      => 'edd_blockonomics_timeperiod',
        'name'    => __('Time period of countdown timer on payment page (in minutes)', 'edd-blockonomics'),
        'type'    => 'select',
        'options' => array(
          '10' => '10',
          '15' => '15',
          '20' => '20',
          '25' => '25',
          '30' => '30'
        ),
        'class' => 'edd-blockonomics-advanced'
      ),
      array(
        'id'      => 'edd_blockonomics_margin',
        'name'    => __('Extra Currency Rate Margin % (Increase live fiat to BTC rate by small percent)', 'edd-blockonomics'),
        'type'    => 'number',
        'max'     => 20,
        'class' => 'edd-blockonomics-advanced'
      ),
      array(
        'id'      => 'edd_blockonomics_confirmations',
        'name'    => __('Network Confirmations required for payment to complete', 'edd-blockonomics'),
        'type'    => 'select',
        'options' => array(
          '2' => '2 (recommended)',
          '1' => '1',
          'zero' => '0'
        ),
        'class' => 'edd-blockonomics-advanced'
      ),
      array(
        'id'      => 'edd_blockonomics_underpayment_slack',
        'name'    => __('Underpayment Slack % (Allow payments that are off by a small percentage)', 'edd-blockonomics'),
        'type'    => 'number',
        'max'     => 20,
        'class' => 'edd-blockonomics-advanced'
      ),
      array(
        'id'      => 'edd_blockonomics_testsetup',
        'name'    => $test_setup,
        'readonly' => true,
        'type'    => 'testsetup',
      )
    );

    $blockonomics_settings = apply_filters('edd_blockonomics_settings', $blockonomics_settings);
    $settings['blockonomics'] = $blockonomics_settings;
    return $settings;
  }

  public function enqueue_stylesheets(){
      wp_enqueue_style('bnomics-style', plugin_dir_url(__FILE__) . "/css/order.css");
  }

  public function enqueue_scripts(){
    wp_enqueue_script( 'reconnecting-websocket', plugins_url('js/vendors/reconnecting-websocket.min.js', __FILE__), array(), NULL, true);
    wp_enqueue_script( 'qrious', plugins_url('js/vendors/qrious.min.js', __FILE__), array(), NULL, true);
    wp_enqueue_script( 'bnomics-checkout', plugins_url('js/checkout.js', __FILE__), array('reconnecting-websocket', 'qrious'), NULL, true);
  }

  public function get_checkout_script($context) {
    $blockonomics = new BlockonomicsAPI;

    $script = "const blockonomics_data = '" . json_encode( array (
      'crypto' => $context['crypto'],
      'crypto_address' => $context['address'],
      'time_period' => edd_get_option('edd_blockonomics_timeperiod', 10),
      'finish_order_url' => $this->get_edd_url(array('finish_order'=>$blockonomics->encrypt_hash($context['order_id']), 'crypto'=>  $context['crypto']['code'])),
      'get_order_amount_url' => $this->get_edd_url(array('get_amount'=>$blockonomics->encrypt_hash($context['order_id']), 'crypto'=>  $context['crypto']['code'])),
      'payment_uri' => $context['payment_uri']
    )). "'";

    return $script;
  }

  public function get_crypto_payment_uri($crypto, $address, $order_amount) {
    return $crypto['uri'] . ":" . $address . "?amount=" . $order_amount;
  }

  public function fix_displaying_small_values($satoshi){
    if ($satoshi < 10000){
        return rtrim(number_format($satoshi/1.0e8, 8),0);
    } else {
        return $satoshi/1.0e8;
    }
  }

  public function get_edd_url($query) {
    $final_query = array_merge($query, array('edd-listener' => 'blockonomics'));
    return add_query_arg($final_query, home_url());
  }

  
  public function get_crypto_rate_from_params($value, $satoshi) {
    // Crypto Rate is re-calculated here and may slightly differ from the rate provided by Blockonomics
    // This is required to be recalculated as the rate is not stored anywhere in $order, only the converted satoshi amount is.
    // This method also helps in having a constant conversion and formatting for both JS and NoJS Templates avoiding the scientific notations.
    return number_format($value*1.0e8/$satoshi, 2, '.', '');
  }

  public function get_order_amount_info($order_hash, $crypto){
    $blockonomics = new BlockonomicsAPI;

    $order = $this->process_order($order_hash, $crypto);
    $order_amount = $this->fix_displaying_small_values($order['satoshi']);
    $cryptos = $blockonomics->getActiveCurrencies();
    $crypto_obj = $cryptos[$crypto];

    $response = array(
        "payment_uri" => $this->get_crypto_payment_uri($crypto_obj, $order['address'], $order_amount),
        "order_amount" => $order_amount,
        "crypto_rate_str" => $this->get_crypto_rate_from_params($order['value'], $order['satoshi'])
    );
    header("Content-Type: application/json");
    exit(json_encode($response));
  }

  public function process_order($order_hash, $crypto) {
    $blockonomics = new BlockonomicsAPI;
    //Decrypt Order Hash to get order_id
    $order_id = $blockonomics->decrypt_hash($order_hash);
    
    // Get Order by order id
    $order = $blockonomics->get_order($order_id);

    if (isset($order) && !isset($order['address'])) {
      $callback_secret = trim(edd_get_option('edd_blockonomics_callback_secret', ''));
      $responseObj = $blockonomics->new_address($callback_secret, $crypto);
      
      if($responseObj->response_code != 200)
      {
        edd_record_gateway_error( __( 'Error while getting Address', 'edd-blockonomics' ) );
        $this->displayError();
        return;
      }

      $address = $responseObj->address;
      $order['address'] = $address;
    }
    if (!isset($order)) {
      // Todo: Do Something if the order is not found
    }

    // Update the order Prices
    $order = $this->calculate_order_price_params($order, $crypto);

    // Update the order
    $this->update_order($order_id, $order);

    return $order;
  }

  public function calculate_order_price_params($order, $crypto){
    // Check if order is unused or new

    $blockonomics = new BlockonomicsAPI;

    if ( $order['status'] == -1) {

      $currency = edd_get_currency();
      if($currency == 'RIAL'){
        $currency = 'IRR';
      }

      if($currency != 'BTC'){
        $price = $blockonomics->get_price($currency, $crypto);
        $price = $price * 100/(100 + edd_get_option('edd_blockonomics_margin', 0));
      }else{
        $price = 1;
      }

      $order['currency'] = $currency;
      $order['satoshi'] = intval(1.0e8*$order['value']/$price);
    }

    return $order;
  }

  public function get_order($address) {
    $blockonomics_orders = edd_get_option('edd_blockonomics_orders');

    if (isset($blockonomics_orders[$address])) {
      return $blockonomics_orders[$address];
    }
    return NULL;
  }    

  public function update_order($order_id, $order) {
    $blockonomics_orders = edd_get_option('edd_blockonomics_orders');
    $blockonomics_orders[$order_id] = $order;
    edd_update_option('edd_blockonomics_orders', $blockonomics_orders);
  } 

}

/*Call back method for the setting 'testsetup'*/
function edd_testsetup_callback()
{
  printf("");
}

function edd_advanced_settings_callback()
{
  printf("");
}

function edd_currency_settings_callback()
{
  printf("");
}

function edd_blockonomics_init()
{
  $edd_blockonomics = new EDD_Blockonomics;
}
add_action( 'plugins_loaded', 'edd_blockonomics_init' );

