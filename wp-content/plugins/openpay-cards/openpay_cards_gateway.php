<?php

if (!class_exists('Openpay')) {
    require_once("lib/openpay/Openpay.php");
}

/*
  Title:	Openpay Payment extension for WooCommerce
  Author:	Openpay
  URL:		http://www.openpay.mx
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

class Openpay_Cards extends WC_Payment_Gateway
{

    protected $GATEWAY_NAME = "Openpay Cards";
    protected $is_sandbox = true;
    protected $order = null;
    protected $transaction_id = null;
    protected $transactionErrorMessage = null;
    protected $currencies = array('MXN', 'USD');

    public function __construct() {        
        $this->id = 'openpay_cards';
        $this->method_title = __('Openpay Cards', 'openpay_cards');
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();
        $this->logger = wc_get_logger();        

        $this->title = 'Pago con tarjeta de crédito o débito';
        $this->description = '';        
        $this->is_sandbox = strcmp($this->settings['sandbox'], 'yes') == 0;
        $this->test_merchant_id = $this->settings['test_merchant_id'];
        $this->test_private_key = $this->settings['test_private_key'];
        $this->test_publishable_key = $this->settings['test_publishable_key'];
        $this->live_merchant_id = $this->settings['live_merchant_id'];
        $this->live_private_key = $this->settings['live_private_key'];
        $this->live_publishable_key = $this->settings['live_publishable_key'];
        $this->charge_type = $this->settings['charge_type'];
        $this->merchant_id = $this->is_sandbox ? $this->test_merchant_id : $this->live_merchant_id;
        $this->publishable_key = $this->is_sandbox ? $this->test_publishable_key : $this->live_publishable_key;
        $this->private_key = $this->is_sandbox ? $this->test_private_key : $this->live_private_key;

        if ($this->is_sandbox) {
            $this->description .= __('SANDBOX MODE ENABLED. In test mode, you can use the card number 4111111111111111 with any CVC and a valid expiration date.', 'openpay-woosubscriptions');
        }
        
        if (!$this->validateCurrency()) {
            $this->enabled = false;
        }                        
        
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
        add_action('admin_notices', array(&$this, 'perform_ssl_check'));
        add_action('woocommerce_api_'.strtolower(get_class($this)), array($this, 'webhook_handler'));           
    }       
    
    public function webhook_handler() {   
        global $woocommerce;
        $id = $_GET['id'];        
        
        try {            
            $openpay = $this->getOpenpayInstance();
            $charge = $openpay->charges->get($id);
            $order = new WC_Order($charge->order_id);

            if ($order && $charge->status != 'completed') {
                $order->add_order_note(sprintf("%s Credit Card Payment Failed with message: '%s'", 'Openpay_Cards', 'Status '+$charge->status));
                $order->set_status('failed');
                $order->save();

                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), 'error');
                } else {
                    $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
                }
            } else if ($order && $charge->status == 'completed') {
                $order->payment_complete();
                $woocommerce->cart->empty_cart();
                $order->add_order_note(sprintf("%s payment completed with Transaction Id of '%s'", 'Openpay_Cards', $charge->id));
            }
                        
            wp_redirect($this->get_return_url($order));            
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());            
            status_header( 404 );
            nocache_headers();
            include(get_query_template('404'));
            die();
        }                
    }    

    public function perform_ssl_check() {
        if (!$this->is_sandbox && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') :
            echo '<div class="error"><p>'.sprintf(__('%s sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->GATEWAY_NAME, admin_url('admin.php?page=settings')).'</p></div>';
        endif;
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'type' => 'checkbox',
                'title' => __('Enable/Disable', 'woothemes'),
                'label' => __('Enable Openpay Cards', 'woothemes'),
                'default' => 'yes'
            ),
            'charge_type' => array(
		'title' => __('Charge configuration', 'woocommerce'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('What is Selective Authentication? It\'s when risk is detected in the transaction and this one is send it through 3D Secure.', 'woocommerce'),
                'default' => 'direct',
                'desc_tip' => true,
                'options' => array(
                    'direct' => __('Direct', 'woocommerce'),
                    'auth' => __('Selective Authentication', 'woocommerce'),
                    '3d' => __('3D Secure', 'woocommerce'),
                ),
            ),
            'sandbox' => array(
                'type' => 'checkbox',
                'title' => __('Sandbox mode', 'woothemes'),
                'label' => __('Enable sandbox', 'woothemes'),
                'description' => __('Place the payment gateway in test mode using Sandbox API keys.', 'woothemes'),
                'default' => 'no'
            ),
            'test_merchant_id' => array(
                'type' => 'text',
                'title' => __('Sandbox merchant ID', 'woothemes'),
                'description' => __('Get your Sandbox API keys from your Openpay account.', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'test_private_key' => array(
                'type' => 'text',
                'title' => __('Sandbox secret key', 'woothemes'),
                'description' => __('Get your Sandbox API keys from your Openpay account ("sk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'test_publishable_key' => array(
                'type' => 'text',
                'title' => __('Sandbox public key', 'woothemes'),
                'description' => __('Get your Sandbox API keys from your Openpay account ("pk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'live_merchant_id' => array(
                'type' => 'text',
                'title' => __('Production merchant ID', 'woothemes'),
                'description' => __('Get your Production API keys from your Openpay account.', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'live_private_key' => array(
                'type' => 'text',
                'title' => __('Production secret key', 'woothemes'),
                'description' => __('Get your Production API keys from your Openpay account ("sk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'live_publishable_key' => array(
                'type' => 'text',
                'title' => __('Production public key', 'woothemes'),
                'description' => __('Get your Production API keys from your Openpay account ("pk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),            
            'interest_free_3_months' => array(
                'title'           => __( 'Monthly interest-free', 'woothemes' ),
                'label'            => __( '3 months', 'woocommerce' ),                    
                'default'         => 'no',
                'type'            => 'checkbox',
                'checkboxgroup'   => 'start',
                'show_if_checked' => 'option',
                'autoload'        => false,                
            ),
            'interest_free_6_months' => array(
                'label'            => __( '6 months', 'woothemes' ),                    
                'default'         => 'no',
                'type'            => 'checkbox',
                'checkboxgroup'   => '',
                'show_if_checked' => 'yes',
                'autoload'        => false
            ),
            'interest_free_9_months' => array(
                'label'            => __( '9 months', 'woothemes' ),                    
                'default'         => 'no',
                'type'            => 'checkbox',
                'checkboxgroup'   => '',
                'show_if_checked' => 'yes',
                'autoload'        => false
            ),
            'interest_free_12_months' => array(
                'label'            => __( '12 months', 'woothemes' ),                    
                'default'         => 'no',
                'type'            => 'checkbox',
                'checkboxgroup'   => 'end',
                'show_if_checked' => 'yes',
                'autoload'        => false,                
            ),               
            'interest_free_18_months' => array(
                'label'            => __( '18 months', 'woothemes' ),                    
                'default'         => 'no',
                'type'            => 'checkbox',
                'checkboxgroup'   => 'end',
                'show_if_checked' => 'yes',
                'autoload'        => false,
                'description' => __('If you gonna use months interest-free, please select one or more of the following options.', 'woothemes'),
            ),   
            'minimum_amount_interest_free' => array(
                'type' => 'number',
                'title' => __('Minimum amount', 'woothemes'),
                'description' => __('Minimum amount to accept months interest-free (minimum amount must be at least $1,800 MXN).', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            
        );
    }

    public function admin_options() {
        include_once('templates/admin.php');
    }

    public function payment_fields() {
        //$this->images_dir = WP_PLUGIN_URL."/".plugin_basename(dirname(__FILE__)).'/assets/images/';    
        global $woocommerce;
        $this->images_dir = plugin_dir_url( __FILE__ ).'/assets/images/';        
        $months = array('3' => '3 meses', '6' => '6 meses', '9' => '9 meses', '12' => '12 meses', '18' => '18 meses');
        
        foreach ($months as $key => $month){
            if($this->settings['interest_free_'.$key.'_months'] == 'no'){
                unset($months[$key]);
            }
        }
                
        $this->show_months_interest_free = false;
        if(count($months) > 0 && ($woocommerce->cart->total >= $this->settings['minimum_amount_interest_free'])) {
            $this->show_months_interest_free = true;
        }
        
        $this->months = $months;
        
        include_once('templates/payment.php');
    }

    /**
     * payment_scripts function.
     *
     * Outputs scripts used for openpay payment
     *
     * @access public
     */
    public function payment_scripts() {
        if (!is_checkout()) {
            return;
        }
        
        global $woocommerce;
                
        wp_enqueue_script('openpay_js', 'https://openpay.s3.amazonaws.com/openpay.v1.min.js', '', '', true);
        wp_enqueue_script('openpay_fraud_js', 'https://openpay.s3.amazonaws.com/openpay-data.v1.min.js', '', '', true);        
        wp_enqueue_script('payment', plugins_url('assets/js/jquery.payment.js', __FILE__), array( 'jquery' ), '', true);
        wp_enqueue_script('openpay', plugins_url('assets/js/openpay.js', __FILE__), array( 'jquery' ), '', true);        


        $openpay_params = array(
            'merchant_id' => $this->merchant_id,
            'public_key' => $this->publishable_key,
            'sandbox_mode' => $this->is_sandbox,
            'total' => $woocommerce->cart->total,
            'currency' => get_woocommerce_currency()
        );

        // If we're on the pay page we need to pass openpay.js the address of the order.
        if (is_checkout_pay_page() && isset($_GET['order']) && isset($_GET['order_id'])) {
            $order_key = urldecode($_GET['order']);
            $order_id = absint($_GET['order_id']);
            $order = new WC_Order($order_id);

            if ($order->get_id() == $order_id && $order->get_order_key() == $order_key) {
                $openpay_params['get_billing_first_name()'] = $order->get_billing_first_name();
                $openpay_params['get_billing_last_name()'] = $order->get_billing_last_name();
                $openpay_params['get_billing_address_1()'] = $order->get_billing_address_1();
                $openpay_params['get_billing_address_2()'] = $order->get_billing_address_2();
                $openpay_params['get_billing_state()'] = $order->get_billing_state();
                $openpay_params['get_billing_city()'] = $order->get_billing_city();
                $openpay_params['get_billing_postcode()'] = $order->get_billing_postcode();
                $openpay_params['get_billing_country()'] = $order->get_billing_country();
            }
        }

        wp_localize_script('openpay', 'wc_openpay_params', $openpay_params);
    }
    
    private function getProductsDetail() {
        $order = $this->order;
        $products = [];
        foreach( $order->get_items() as $item_product ){                        
            $product = $item_product->get_product();                        
            $products[] = $product->get_name();
        }
        return substr(implode(', ', $products), 0, 249);
    }

    public function process_payment($order_id) {
        global $woocommerce;
        $device_session_id = isset($_POST['device_session_id']) ? wc_clean($_POST['device_session_id']) : '';
        $openpay_token = $_POST['openpay_token'];
        $interest_free = null;
        
        if(isset($_POST['openpay_month_interest_free'])){
            $interest_free = $_POST['openpay_month_interest_free'];
        }
        
        $this->order = new WC_Order($order_id);
        if ($this->processOpenpayCharge($device_session_id, $openpay_token, $interest_free)) {            
            $redirect_url = get_post_meta($order_id, '_openpay_3d_secure_url', true);     
            
            // Si no existe una URL de redireccionamiento, se marca la orden como completada
            if (!$redirect_url) {                
                $this->order->payment_complete();                            
                $this->order->add_order_note(sprintf("%s payment completed with Transaction Id of '%s'", $this->GATEWAY_NAME, $this->transaction_id));                
            }
                             
            $woocommerce->cart->empty_cart();            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
            );
        } else {
            $this->order->add_order_note(sprintf("%s Credit Card Payment Failed with message: '%s'", $this->GATEWAY_NAME, $this->transactionErrorMessage));
            $this->order->set_status('failed');
            $this->order->save();
            
            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), 'error');
            } else {
                $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
            }
        }
    }
    
    protected function processOpenpayCharge($device_session_id, $openpay_token, $interest_free) {
        WC()->session->__unset('pdf_url');   
        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';        
        $redirect_url_3d = site_url('/', $protocol).'wc-api/Openpay_Cards';                  
        
        $charge_request = array(
            "method" => "card",
            "amount" => round($this->order->get_total(), 2),
            "currency" => strtolower(get_woocommerce_currency()),
            "source_id" => $openpay_token,
            "device_session_id" => $device_session_id,
            "description" => sprintf("Items: %s", $this->getProductsDetail()),            
            "order_id" => $this->order->get_id()
        );
        
        if($interest_free > 1){
            $charge_request['payment_plan'] = array('payments' => (int)$interest_free);
        }   
        
        if ($this->charge_type == '3d') {
            $charge_request['use_3d_secure'] = true;
            $charge_request['redirect_url'] = $redirect_url_3d;
        }

        $openpay_customer = $this->getOpenpayCustomer();

        $charge = $this->createOpenpayCharge($openpay_customer, $charge_request, $redirect_url_3d);

        if ($charge != false) {
            $this->transaction_id = $charge->id;
            //Save data for the ORDER
            update_post_meta($this->order->get_id(), '_openpay_customer_id', $openpay_customer->id);
            update_post_meta($this->order->get_id(), '_transaction_id', $charge->id);            
            
            if ($charge->payment_method && $charge->payment_method->type == 'redirect') {
                update_post_meta($this->order->get_id(), '_openpay_3d_secure_url', $charge->payment_method->url);                
            }            
            return true;
        } else {
            return false;
        }
    }

    public function createOpenpayCharge($customer, $charge_request, $redirect_url_3d) {
        Openpay::getInstance($this->merchant_id, $this->private_key);        
        Openpay::setProductionMode($this->is_sandbox ? false : true);
        
        try {
            $charge = $customer->charges->create($charge_request);
            return $charge;
        } catch (Exception $e) {           
            // Si cuenta con autenticación selectiva y hay detección de fraude se envía por 3D Secure
            if ($this->charge_type == 'auth' && $e->getCode() == '3005') {
                $charge_request['use_3d_secure'] = true;
                $charge_request['redirect_url'] = $redirect_url_3d;
                $charge = $customer->charges->create($charge_request);
                return $charge;
            }
            
            $this->error($e);
            return false;
        }
    }

    public function getOpenpayCustomer() {
        $customer_id = null;
        if (is_user_logged_in()) {
            $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_id', true);
        }

        if ($this->isNullOrEmptyString($customer_id)) {
            return $this->createOpenpayCustomer();
        } else {
            $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
            Openpay::setProductionMode($this->is_sandbox ? false : true);
            try {
                return $openpay->customers->get($customer_id);
            } catch (Exception $e) {
                $this->error($e);
                return false;
            }
        }
    }

    public function createOpenpayCustomer() {
        $customerData = array(            
            'name' => $this->order->get_billing_first_name(),
            'last_name' => $this->order->get_billing_last_name(),
            'email' => $this->order->get_billing_email(),
            'requires_account' => false,
            'phone_number' => $this->order->get_billing_phone()            
        );
        
        if($this->hasAddress($this->order)) {
            $customerData['address'] = array(
                'line1' => substr($this->order->get_billing_address_1(), 0, 200),
                'line2' => substr($this->order->get_billing_address_2(), 0, 50),
                'line3' => '',
                'state' => $this->order->get_billing_state(),
                'city' => $this->order->get_billing_city(),
                'postal_code' => $this->order->get_billing_postcode(),
                'country_code' => $this->order->get_billing_country()
            );
        }

        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        try {
            $customer = $openpay->customers->add($customerData);

            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), '_openpay_customer_id', $customer->id);
            }

            return $customer;
        } catch (Exception $e) {
            $this->error($e);
            return false;
        }
    }
    
    public function hasAddress($order) {
        if($order->get_billing_address_1() && $order->get_billing_state() && $order->get_billing_postcode() && $order->get_billing_country() && $order->get_billing_city()) {
            return true;
        }
        return false;    
    }

    public function error(Exception $e) {
        global $woocommerce;

        /* 6001 el webhook ya existe */
        switch ($e->getCode()) {
            /* ERRORES GENERALES */
            case '1000':
            case '1004':
            case '1005':
                $msg = 'Servicio no disponible.';
                break;
            /* ERRORES TARJETA */
            case '3001':
            case '3004':
            case '3005':
            case '3007':
                $msg = 'La tarjeta fue rechazada.';
                break;
            case '3002':
                $msg = 'La tarjeta ha expirado.';
                break;
            case '3003':
                $msg = 'La tarjeta no tiene fondos suficientes.';
                break;
            case '3006':
                $msg = 'La operación no esta permitida para este cliente o esta transacción.';
                break;
            case '3008':
                $msg = 'La tarjeta no es soportada en transacciones en línea.';
                break;
            case '3009':
                $msg = 'La tarjeta fue reportada como perdida.';
                break;
            case '3010':
                $msg = 'El banco ha restringido la tarjeta.';
                break;
            case '3011':
                $msg = 'El banco ha solicitado que la tarjeta sea retenida. Contacte al banco.';
                break;
            case '3012':
                $msg = 'Se requiere solicitar al banco autorización para realizar este pago.';
                break;
            default: /* Demás errores 400 */
                $msg = 'La petición no pudo ser procesada.';
                break;
        }
        $error = 'ERROR '.$e->getErrorCode().'. '.$msg;        
        $this->transactionErrorMessage = $error;
        if (function_exists('wc_add_notice')) {
            wc_add_notice($error, $notice_type = 'error');
        } else {
            $woocommerce->add_error(__('Payment error:', 'woothemes').$error);
        }
    }

    /**
     * Checks if woocommerce has enabled available currencies for plugin
     *
     * @access public
     * @return bool
     */
    public function validateCurrency() {
        return in_array(get_woocommerce_currency(), $this->currencies);
    }

    public function isNullOrEmptyString($string) {
        return (!isset($string) || trim($string) === '');
    }
    
    public function getOpenpayInstance() {
        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
        Openpay::setProductionMode($this->is_sandbox ? false : true);
        return $openpay;
    }

}

function openpay_cards_add_creditcard_gateway($methods) {
    array_push($methods, 'openpay_cards');
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'openpay_cards_add_creditcard_gateway');

