<?php
	
	require_once( '../../../../wp-load.php' );   
	

	if (isset( $_GET['order_id'] )){
		
		
		global $woocommerce;
		
		$jlPaymentez = new JL_Paymentez();
		
		$order_id = $_GET['order_id'];
		$transaction_id = $_GET['transaction_id'];
		$order = new WC_Order($order_id);
        $order->update_status('completed');
        $order->add_order_note($jlPaymentez->translate("success").' - ' .$jlPaymentez->translate('code') .' '. $transaction_id);
        // Reduce stock levels
    	$order->reduce_order_stock();

    	// Mark order as Paid
		$order->payment_complete();
        
        // Paso importantisímo ya que vacia el carrito
        $woocommerce->cart->empty_cart();

		header("Location:".$jlPaymentez->get_return_url( $order ).'&transaction_id='.$transaction_id);

	}
 
?>