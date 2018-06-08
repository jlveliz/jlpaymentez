<?php 
	
	require_once( '../../../../wp-load.php' );

	global $woocommerce;



	$body = file_get_contents("php://input");

	$myfile = fopen("logs.txt", "wr") or die("Unable to open file!");
	$txt = "user id date";
	// fwrite($txt, $body);
	fwrite($myfile, $body);
	fclose($myfile);

	$webhook = json_decode($body, true);

	if ($webhook['transaction']['status_detail'] == 3) {
		$order_id = str_replace("#", "", $webhook['transaction']['dev_reference']);
		$transaction_id = $webhook['transaction']['id'];
	  	$authorization_code = $webhook['transaction']['authorization_code'];

		$order = wc_get_order( $order_id );
	  	
	  	$paymentez = new JL_Paymentez();

	  	//update the meta data
		update_post_meta($order_id,'_'.$paymentez->get_id().'_transaction_id',$transaction_id);
		update_post_meta($order_id,'_'.$paymentez->get_id().'_authorization_code',$authorization_code);
	}
