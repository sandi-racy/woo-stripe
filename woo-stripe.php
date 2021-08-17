<?php
/*
 * Plugin Name: Woocommerce Stripe
 * Plugin URI: https://github.com/sandi-racy/woo-stripe
 * Description: Stripe payment gateway for Woocommerce
 * Author: Sandi Rosyandi
 * Author URI: https://sandi-racy.github.io/
 * Version: 0.1
 */

// Register the class as payment gateway
add_filter('woocommerce_payment_gateways', 'woo_stripe_add_gateway_class');
function woo_stripe_add_gateway_class ($gateways) {
	$gateways[] = 'WC_Stripe';
	return $gateways;
}

// Initialization of payment gateway class
add_action('plugins_loaded', 'woo_stripe_init_gateway_class');
function woo_stripe_init_gateway_class () {
	class WC_Stripe extends WC_Payment_Gateway {

	}
}