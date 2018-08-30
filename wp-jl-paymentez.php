<?php
/*
Plugin Name: Wp-JL-Paymentez
Plugin URI: http://thejlmedia.com
Description: Allow make payments with Paymentez .
Version: 1.0
Author: Jorge Veliz
Author URI: http://thejlmedia.com
License: GPL2
*/




/**
 * Paymentez Payment Gateway
 *
 * Provides an Paymentez Payment Gateway
 *
 * @class       WC_Gateway_Offline
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 * @author      TheJLMedia
 */

/**
 * Check if WooCommerce is active
 **/


defined( 'ABSPATH' ) or exit;


if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	//load the plugin
	add_action('plugins_loaded', 'wc_jl_paymentez_gateway_init',0);


	function wc_jl_paymentez_gateway_init()
	{
		//check if WC_Payment_Gateway exists
		if(!class_exists('WC_Payment_Gateway')) return;

	


		// //change button on checkout "Pay with Paymentez"
		// function change_jl_paymentez_text_order()
		// {
		// 	return __('Pay with Paymentez','jl-paymentez');
		// }
		// add_filter('woocommerce_order_button_text','change_jl_paymentez_text_order');




		//add de plugin to the gateway
		add_filter( 'woocommerce_payment_gateways', 'jl_paymentez_add_to_gateways' );
		function jl_paymentez_add_to_gateways( $gateways ) {
		    $gateways[] = 'JL_Paymentez';
		    return $gateways;
		}


		/**		
		send transaction paymentez code to email
		**/
		function jl_woocommerce_custom_email_order_meta_fields($order, $sent_to_admin, $plain_text) {
			if($order->get_payment_method() == 'jl_paymentez') {
	    	$paymentez = new JL_Paymentez();
	    ?>
				<p><b><?php echo $paymentez->translate("code") ?></b>: <?php echo get_post_meta( $order->get_order_number(), '_'.$paymentez->get_id().'_transaction_id', true ) ?></p>
				<p><b><?php echo $paymentez->translate("authorization_code") ?></b>: <?php echo get_post_meta( $order->get_order_number(), '_'.$paymentez->get_id().'_authorization_code', true ) ?></p>
		<?php 
			}
		}
		add_filter('woocommerce_email_order_meta','jl_woocommerce_custom_email_order_meta_fields');

		
		

		/**
			Page of thanks
		**/
		function jl_woocommerce_thankyou( $order_id ) {
			$order = wc_get_order( $order_id );
			if($order->get_payment_method() == 'jl_paymentez') {
				$paymentez = new JL_Paymentez();
				if($order->ge)
				if ($_GET['transaction_id']) {
					$transaction_id  = $_GET['transaction_id'];
					?>
					<p><b><?php echo $paymentez->translate('code') ?> : </b><?php echo $transaction_id ?></p>
					<?php 
					//update the meta data
					// update_post_meta($order_id,'_'.$paymentez->get_id().'_transaction_id',$transaction_id);
				}
			}
		}


		/**
			Show Paymentez Code
		**/

		function jl_woocommerce_show_paymentez_transaction_code($order){
			if($order->get_payment_method() == 'jl_paymentez') {
				$paymentez = new JL_Paymentez();
				if($paymentez->enabled == 'yes' &&  $order->get_payment_method()  == $paymentez->get_id()) {
			    	echo '<p><strong>'.__('Paymentez Transaction Id').':</strong> <br/>' . get_post_meta( $order->get_id(), '_'.$paymentez->get_id().'_transaction_id', true ) . '</p>';
			    	echo '<p><strong>'.__('Paymentez Authorization Code').':</strong> <br/>' . get_post_meta( $order->get_order_number(), '_'.$paymentez->get_id().'_authorization_code', true ) . '</p>';
					
				}
			}
		}



	

		class JL_Paymentez extends WC_Payment_Gateway  {

			

			//load the form
			function receipt_page($order)
			{
				echo $this->generate_paymentez_form($order);
			}

			

			public $api_url_development;
			public $api_url_production;
			public $title;
			public $language;
			public $paymentez_client_app_code;
			public $paymentez_client_app_key;
			public $env_mode;
			private $ecuadorian_app;
			public $iva_percentage;
			public $enabled;



			function __construct() {
				$this->id = "jl_paymentez";
				$this->method_title = "JL Paymentez";
				$this->icon = apply_filters('woocomerce_paymentez_icon', plugins_url('assets/img/logo.jpg', __FILE__));
				$this->has_fields = false;
				$this->method_description = "Allow pay with Paymentez";

				$this->supports = ['products','refunds'];


				$this->init_form_fields();
				$this->init_settings();

				$this->api_url_development = "https://ccapi-stg.paymentez.com";
				$this->api_url_production = "https://ccapi.paymentez.com";


				// Define user set variables
				$this->title = $this->get_option( 'title' );
				$this->language = $this->get_option( 'language' );
				$this->paymentez_client_app_code = $this->get_option('paymentez_client_app_code');
				$this->paymentez_client_app_key = $this->get_option('paymentez_client_app_key');
				// $this->paymentez_client_app_refund = $this->get_option('paymentez_client_app_refund');
				$this->env_mode = $this->get_option( 'env_mode' );
				$this->ecuadorian_app = $this->get_option( 'ecuadorian_app' );
				$this->iva_percentage = $this->get_option( 'iva_percentage' );
				$this->enabled = $this->get_option( 'enabled' );
				

				add_action('init', array(&$this, 'check_paymentez_response'));
				// Actions
				if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
					//allow update
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				} else {
					add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
				}


				add_action( 'woocommerce_receipt_'.$this->id, array(&$this, 'receipt_page') );


				if ($this->enabled == 'yes') {

				    // add_action( 'woocommerce_email', 'jl_remove_woocommerce_order_status_completed' );

				    
				    /**
				     * Update the order meta with field value
				     **/
				    add_action('woocommerce_thankyou', 'jl_woocommerce_thankyou');


					/**
					* Display field value on the order edit page
					*/
					add_action( 'woocommerce_admin_order_data_after_billing_address', 'jl_woocommerce_show_paymentez_transaction_code', 10, 1 );
				   
				}


			}

			/**
				get id of plugin	
			**/
			public function get_id()
			{
				return $this->id;
			}

			/**
				get get_client_app_code inserted by user
			**/
			public function get_client_app_code()
			{
				return $this->paymentez_client_app_code;
			}

			/**	
				get paymentez_client_app_key inserted by user
			**/

			public function get_client_app_key()
			{
				return $this->paymentez_client_app_key;
			}

			/**	
				get paymentez_client_app_key inserted by user
			**/

			// public function get_client_app_refund()
			// {
			// 	return $this->paymentez_client_app_refund;
			// }


			/**
				verify is in env mode
			**/
			public function is_dev_mode()
			{
				return $this->env_mode == 'yes' ? true : false;
			}


			public function init_form_fields()
			{
				$is_ecuadorian_app  = "&quote;is_ecuadorian_app&quote;";

				$changeIva = "
					document.getElementById(\"is_ecuadorian_app\").value == 1 ? document.getElementById(\"iva_percentage_paymentez\").setAttribute(\"disabled\",\"false\") : document.getElementById(\"iva_percentage_paymentez\").setAttribute(\"disabled\",\"true\")
				";

				return $this->form_fields = [
				         
				       'enabled' => [
				           'title'   => __( 'Enable/Disable', 'jl-paymentez' ),
				           'type'    => 'checkbox',
				           'label'   => __( 'Enable Paymentez Gateway', 'jl-paymentez' ),
				           'default' => false
				       ],

				        'env_mode' => [
				       		'title' => __('Development Mode','jl-paymentez'),
				       		'type' => 'checkbox',
				       		'label' => __('If you need to do test.','jl-paymentez'),
				       		'default' => false,
				       		'desc_tip'    => true,
				       ],

				       'title' => [
				           'title'       => __( 'Title', 'jl-paymentez' ),
				           'type'        => 'text',
				           'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'jl-paymentez' ),
				           'default'     => __( 'JL Paymentez', 'jl-paymentez' ),
				           'desc_tip'    => true,
				       ],


				       'paymentez_client_app_code' => [
				       		'title' => __('Paymentez Client App Code','jl-paymentez'),
				       		'type' => 'text',
				       		'description' => 'Provided by Paymentez',
				       		'default' => 'Your Paymentez Client App Code',
				       		'desc_tip'    => true,
				       ],
				       'paymentez_client_app_key' => [
				       		'title' => __('Paymentez Client App Key','jl-paymentez'),
				       		'type' => 'text',
				       		'description' => 'Provided by Paymentez',
				       		'default' => 'Your Paymentez Client App Key',
				       		'desc_tip'    => true,
				       ],
				       // 'paymentez_client_app_refund' => [
				       // 		'title' => __('Paymentez Refund Key','jl-paymentez'),
				       // 		'type' => 'text',
				       // 		'description' => 'Provided by Paymentez',
				       // 		'default' => 'Your Paymentez Refund',
				       // 		'desc_tip'    => true,
				       // ],

				       'language' => [
				       		'title' => 'Language',
				       		'type' => 'select',
				       		'description'=>"Language of Paymentez's form",
				       		'options' => [
				       			'es' => 'Spanish',
				       			'en' => 'English',
				       			'pt' => 'Portuguese'
				       		],
				       		'default' => 'es'
				       ],
				       'ecuadorian_app' => [
				       		'title' => 'Pay in Equador',
				       		'id' => 'is_ecuadorian_app',
				       		'type' => 'checkbox',
				       		'label' => __('Use Paymentez in Equador','jl-paymentez'),
				       		'default' => false,
				       		'desc_tip'    => true,
				       ],
				       'iva_percentage' => [
				       		'title' => 'IVA %',
				       		'id' => 'iva_percentage_paymentez',
				       		'type' => 'text',
				       		'description' => __( 'Tax used on Equador', 'jl-paymentez' ),
				       		'default' => '12',
				       ] 
				   ];
			}


			public function process_payment( $order_id )
			{
				// global $woocommerce;
				$order = new WC_Order( $order_id );

				if($order->get_payment_method() == 'jl_paymentez') {
					return [
    			    	'result'    => 'success',
    			    	'redirect'  =>  $order->get_checkout_payment_url( true )
    				];
    			}
			}


			public function generate_paymentez_form($order_id)
			{
				global $woocommerce;
				$order = new WC_Order( $order_id );
				$customer_id = $order->get_customer_id();
				$email = $order->get_billing_email();
				$phone = $order->get_billing_phone();
				$total = $order->get_total();
				$order_description = $this->get_description_orders($order->get_items( 'line_item' ));
				$env_mode = $this->env_mode == 'yes' ? 'stg' : 'prod';
				$lang = $this->language;
				$textBtn = $lang == 'en' ? "Pay" : 'Pagar';
				$successMessage =  $this->translate('success');
				$location = plugins_url('/includes/success.php?order_id='.$order_id, __FILE__);
				$inEquador = $this->ecuadorian_app == 'yes' ? true : false;
				$tax_percentage = $this->iva_percentage;
				$order_vat = number_format(($order->get_total_tax()),2,'.','');
				$order->get_total_tax() ?    $order_taxable_amount = ($total - $order_vat)  :  $order_taxable_amount = 0;


				
				return "
					<style>
						#pay-with-paymentez {
							/* Permalink - use to edit and share this gradient: http://colorzilla.com/gradient-editor/#679333+0,52712f+100 */
							background: #679333; /* Old browsers */
							background: -moz-linear-gradient(top, #679333 0%, #52712f 100%); /* FF3.6-15 */
							background: -webkit-linear-gradient(top, #679333 0%,#52712f 100%); /* Chrome10-25,Safari5.1-6 */
							background: linear-gradient(to bottom, #679333 0%,#52712f 100%); /* W3C, IE10+, FF16+, Chrome26+, Opera12+, Safari7+ */
							filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#679333', endColorstr='#52712f',GradientType=0 ); /* IE6-9 */
							margin: 0 auto;
							color: #ffffff;
						}

						.paymentez-success {
							background: #28a745;
		    				padding: 5px;
		    				color: #ffff;
						}

						.paymentez-error {
							padding: 5px;
		    				color: #ffff;
		    				background: #dc3545
						}
					</style>
					<script src='https://code.jquery.com/jquery-1.11.3.min.js' type='text/javascript'></script>
					<link href='https://cdn.paymentez.com/js/1.0.1/paymentez.min.css' rel='stylesheet' type='text/css' />
					<script src='https://cdn.paymentez.com/checkout/1.0.1/paymentez-checkout.min.js'></script>
					<button class='js-paymentez-checkout' id='pay-with-paymentez' style='display:none'>$textBtn</button>
					<p id='message-response'></p>
					<script>

						var paramOpen = {
							user_id: '$customer_id',
							user_email: '$email', //optional        
							user_phone: '$phone', //optional
							order_description: '$order_description',
							order_amount: $total,
							order_reference: '#$order_id',
							order_vat : $order_vat,
						};


						if($inEquador) {
							paramOpen.order_tax_percentage = $tax_percentage;
							// paramOpen.order_installments_type = 0;
							paramOpen.order_taxable_amount = $order_taxable_amount;
						}

						var getErrorMessage = function(statusDetail){
							var message = '';
							switch(statusDetail) {
								case 9 :
									message = 'Denied transaction';
									break;
								case 1:
									message = 'Reviewed transaction';
									break;
								case 11:
									message = 'Rejected by kount transaction';
									break;
								default:
									message = 'Card in black list'
									break;
							}
							return message;
						}


						var paymentezCheckout = new PaymentezCheckout.modal({
						      client_app_code: '$this->paymentez_client_app_code', // Client Credentials Provied by Paymentez
						      client_app_key: '$this->paymentez_client_app_key', // Client Credentials Provied by Paymentez
						      locale: '$lang', // User's preferred language (es, en, pt). English will be used by default.
						      env_mode: '$env_mode', // `prod`, `stg`, `dev`, `local` to change environment. Default is `stg`
						      onClose: function() {
						        $('#pay-with-paymentez').css('display','block');
						      },
						      onResponse: function(response) {
						      	debugger;
						      	if(response.error) {
						      		$('#message-response').addClass('paymentez-error').text(response.error.description)
						      	} else if(response.transaction)  {
						      		if(response.transaction.status == 'failure') {
						      			$('#message-response').addClass('paymentez-error').text(getErrorMessage(response.transaction.status_detail))
						      		} else {
							      		$('#pay-with-paymentez').css('display','block');
							      		$('#message-response').addClass('paymentez-success').text('$successMessage');
							      		setTimeout(function () {
							      			window.location.href = '$location'+'&transaction_id='+response.transaction.id;
							      		}, 5000); //will call the function after 5 secs.
						      		}
						      	}
						      }
						  })
						      var btnOpenCheckout = document.querySelector('.js-paymentez-checkout');
						        btnOpenCheckout.addEventListener('click', function(){
						          // Open Checkout with further options:
						          paymentezCheckout.open(paramOpen);
						      })

				            // Close Checkout on page navigation:
				            window.addEventListener('popstate', function() {
				              paymentezCheckout.close();
				            });

				            $(document).ready(function(){
				            	$('#pay-with-paymentez').click();
				            })
					</script>";
			}


			private function get_description_orders ($orders) {

				$description = "";
				foreach ($orders as $key => $order) {
					$description.=$order->get_name().', ';
				}

				return $description;

			}

			public function translate($key)
			{
				$messages = [
					'eng' => [
						'success' => 'Pay completed Successfull',
						'refund' => 'Your payment has been successfully reversed',
						'code' => 'Paymentez Code',
						'authorization_code' => 'Paymentez Authorization Code '
					],
					'pt' => [
						'success' => 'Pagamento bem sucedido',
						'refund' => 'Seu pagamento foi revertido de forma satisfatória',
						'code' => 'Código Paymentez',
						'authorization_code' => 'Código de autorização Paymentez '
					],
					'es' => [
						'success' => 'Pago Completado exitosamente',
						'refund' => 'Su pago se ha reversado Satisfactoriamente',
						'code' => 'Código Paymentez',
						'authorization_code' => 'Código de autorización Paymentez '
					]
				];

				return $messages[$this->language][$key];
			}


			private function generate_token() {
				$appCode = $this->get_client_app_code();  
				$appKey =   $this->get_client_app_key(); 
				$unix_timestamp = time();
				$uniq_token_string = $appKey.(string)($unix_timestamp);
				$uniq_token_hash = hash("sha256", $uniq_token_string);
				$auth_token = base64_encode($appCode.';'.(string)$unix_timestamp.';'.$uniq_token_hash);
				return $auth_token;
			}

			// private function generate_token_refund() {
			// 	$appCode = $this->get_client_app_code();  
			// 	$appKey =   $this->get_client_app_refund(); 
			// 	$unix_timestamp = time();
			// 	$uniq_token_string = $appKey.(string)($unix_timestamp);
			// 	$uniq_token_hash = hash("sha256", $uniq_token_string);
			// 	$auth_token = base64_encode($appCode.';'.(string)$unix_timestamp.';'.$uniq_token_hash);
			// 	return $auth_token;
			// }


			//refund
			public function process_refund($order_id, $amount = null, $reason = '')
			{
				$order = wc_get_order( $order_id );
				
				$paramsRefound = "/v2/transaction/refund/";

				$urlPost = $this->is_dev_mode() ?  $this->api_url_development.$paramsRefound : $this->api_url_production.$paramsRefound;


				$transactionId = get_post_meta( $order->get_id(), '_'.$this->get_id().'_transaction_id', true );

			
				$data = [
					'transaction' => [
						'id' => $transactionId
					]
				];

				

				$ch = curl_init($urlPost);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					"Content-Type:application/json",
					"Auth-Token:".$this->generate_token()
				]);
				
				$payload = json_encode($data);
				curl_setopt($ch, CURLOPT_POSTFIELDS,$payload);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$response = curl_exec($ch);
				
				$response = json_decode($response,true);
				

				//if completed ;)
				if (array_key_exists('status', $response) && $response['status'] == 'success') {
					if ($reason) {
						$order->add_order_note($reason);
					}
					$order->set_status("refund");
					return true;	
				}


				return false;

			}
		}

		
	}

}

?>