<?php
/*
 * Plugin Name: Woocommerce Stripe
 * Plugin URI: https://github.com/sandi-racy/woo-stripe
 * Description: Stripe payment gateway for Woocommerce
 * Author: Sandi Rosyandi
 * Author URI: https://sandi-racy.github.io/
 * Version: 0.1
 */

// Load all dependecies
require __DIR__ . '/vendor/autoload.php';

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
		public function __construct() {
			$this->id = 'woo_stripe';
			$this->title = 'Stripe';
			$this->method_title = 'Stripe';
			$this->method_description = 'Stripe payment gateway for Woocommerce';
			$this->init_form_fields();
			$this->init_settings();
			$this->secret_key = $this->get_option('secret_key');
			$this->publishable_key = $this->get_option('publishable_key');

			// Hook for processing options page
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

			// Register webhook
			add_action('woocommerce_api_woo-stripe', [$this, 'webhook']);

			// Show payment status on thank you page
			add_filter('woocommerce_thankyou_order_received_text', [$this, 'thankyou'], 10, 2);
 		}

 		// Options page
 		public function init_form_fields () {
			$this->form_fields = [
				'publishable_key' => [
					'title' => 'Publishable key',
					'type' => 'text',
					'description' => 'Publishable key from Stripe'
				],
				'secret_key' => [
					'title' => 'Secret key',
					'type' => 'text',
					'description' => 'Secret key from Stripe'
				]
			];
		}

		// Process the payment
		public function process_payment ($order_id) {
			$order = wc_get_order($order_id);
			$order_received_url = $order->get_checkout_order_received_url();
			$cancel_url = $order_received_url . '&status=cancel';
			$currency = get_woocommerce_currency();

			// Create line items
			$line_items = [];
			foreach ($order->get_items() as $item) {
				$product = $item->get_product();
				$amount = $product->get_price() * 100;
				array_push($line_items, [
					'name' => $product->get_title(),
					'amount' => $amount,
					'currency' => $currency,
					'quantity' => $item->get_quantity()
				]);
			}

			// Create Stripe checkout session
			\Stripe\Stripe::setApiKey($this->secret_key);
			$checkout_session = \Stripe\Checkout\Session::create([
  				'payment_method_types' => ['card'],
  				'line_items' => $line_items,
  				'mode' => 'payment',
  				'success_url' => $order_received_url,
  				'cancel_url' => $cancel_url,
			]);

			// Store payment intent
			$order->update_meta_data('woo_stripe_payment_intent', $checkout_session->payment_intent);
			$order->save();

			return [
				'result' => 'success',
				'redirect' => $checkout_session->url
			];
		}

		// Show payment status on thank you page
		public function thankyou ($text, $order) {
			// Get status query string
			$status == '';
			if (isset($_GET['status'])) {
				$status = $_GET['status'];
			}

			if ($order->get_payment_method() == $this->id) {
				if ($status == 'cancel') {
					return __('You cancelled the payment', 'woo_stripe');
				} else {
					return __('Your order status is:', 'woo_stripe') . ' ' . $order->get_status();
				}
			}
			return $text;
		}

		// Process Stripe webhook
		public function webhook () {
			// Get Stripe object
			$payload = file_get_contents('php://input');
			$event = null;
			try {
				$payload_array = json_decode($payload, true);
    			$event = \Stripe\Event::constructFrom($payload_array);
			} catch (\UnexpectedValueException $e) {
    			status_header(400);
    			wp_die();
			}

			switch ($event->type) {
    			case 'payment_intent.succeeded':
    				// Find order ID from payment intent
        			global $wpdb;
        			$prepare = $wpdb->prepare('SELECT post_id FROM ' . $wpdb->prefix . 'postmeta WHERE meta_key = %s AND meta_value = %s', 'woo_stripe_payment_intent', $event->data->object->charges->data[0]->payment_intent);
        			$order_id = $wpdb->get_var($prepare);
        			$order = wc_get_order($order_id);
        			$order->payment_complete();
        			break;
			}

			status_header(200);
		}
	}
}