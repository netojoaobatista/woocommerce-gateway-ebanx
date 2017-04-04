<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class WC_EBANX_Payment_By_Link {

	private static $errors = array();
	private static $post_id;
	private static $order;
	private static $config;

	/**
	 * The core method. It uses the other methods to create a payment link
	 *
	 * @param  int $post_id The post id
	 * @param  array $config  The config array we need to pass to ebanx-php
	 * @return void
	 */
	public static function create($post_id, $config){
		self::$post_id = $post_id;
		self::$order = wc_get_order($post_id);
		self::$config = $config;

		if ( ! self::can_create_payment() ) {
			return;
		}

		if ( self::validate() ) {
			self::send_errors();
			return;
		}

		$request = self::send_request();
		if ( $request && $request->status !== 'SUCCESS' ) {
			self::add_error( WP_DEBUG
				? $request->status_code . ': ' . $request->status_message
				: __('We couldn\'t create your EBANX order. Could you review your fields and try again?' ) );
			self::send_errors();
			return;
		}

		self::post_request($request->payment->hash, $request->redirect_url);
	}

	/**
	 * Checks if user has permissions to save data.
	 * AND Checks if not an autosave.
	 * AND Checks if not a revision.
	 *
	 * @return bool Can we create a payment by link now?
	 */
	private static function can_create_payment() {
		return current_user_can( 'edit_post', self::$post_id )
				&& ! wp_is_post_autosave( self::$post_id )
				&& ! wp_is_post_revision( self::$post_id );
	}

	/**
	 * Apply all the input validation we need before sending the request
	 *
	 * @return int The number of errors it found
	 */
	private static function validate() {
		if ( ! self::is_it_pending() ) {
			return count(self::$errors);
		}
		self::check_currency_over_country();
		self::check_min_amount();
		self::check_supported_country();
		self::check_valid_email();
		if ( ! empty(self::$order->payment_method) ) {
			self::check_payment_method();
		}
		return count(self::$errors);
	}

	/**
	 * Flashes every error from self::$errors to WC_EBANX_Flash
	 *
	 * @return void
	 */
	private static function send_errors() {
		WC_EBANX_Flash::clear_messages();
		foreach (self::$errors as $error) {
			WC_EBANX_Flash::add_message($error);
		}
	}

	/**
	 * Requests a payment link using ebanx-php
	 *
	 * @return void
	 */
	private static function send_request() {
		$data = array(
			'name'                  => self::$order->billing_first_name . ' ' . self::$order->billing_last_name,
			'email'                 => self::$order->billing_email,
			'country'               => strtolower(self::$order->billing_country),
			'payment_type_code'     => empty(self::$order->payment_method) ? '_all' : WC_EBANX_Constants::$GATEWAY_TO_PAYMENT_TYPE_CODE[self::$order->payment_method],
			'merchant_payment_code' => self::$order->id . '_' . md5(time()),
			'currency_code'         => strtoupper(get_woocommerce_currency()),
			'amount'                => self::$order->get_total()
		);

		\Ebanx\Config::set(self::$config);
		\Ebanx\Config::setDirectMode(false);

		$request = false;

		try {
			$request = \Ebanx\EBANX::doRequest($data);
		} catch (Exception $e) {
			self::add_error($e->getMessage());
			self::send_errors();
		}

		return $request;
	}

	/**
	 * If the request was successful, this method is called before ending the proccess
	 *
	 * @param  string $hash The payment hash
	 * @param  string $url  The payment url
	 * @return void
	 */
	private static function post_request($hash, $url) {
		self::$order->add_order_note(__('Order created via EBANX.', 'woocommerce-gateway-ebanx'));
		update_post_meta(self::$post_id, '_ebanx_payment_hash', $hash);
		update_post_meta(self::$post_id, '_ebanx_checkout_url', $url);
	}

	/**
	 * Check if the error is not already in the array and add it.
	 * To make sure it will show no duplicates.
	 *
	 * @param string $error The error message
	 */
	private static function add_error($error) {
		if ( ! in_array($error, self::$errors) ) {
			self::$errors[] = $error;
		}
	}

	/*
		BEGIN CHECKS
	 */

	/**
	 * Checks if the order status is pending
	 *
	 * @return boolean true if it is pending
	 */
	private static function is_it_pending() {
		if ( ! self::$order->status === 'pending' ) {
			self::add_error(__('You can only create payment links on pending orders.', 'woocommerce-gateway-ebanx'));
			return false;
		}
		return true;
	}

	/**
	 * Checks if the selected country support the shop currency
	 *
	 * @return void
	 */
	private static function check_currency_over_country() {
		if ( get_woocommerce_currency() !== 'USD'
			&& get_woocommerce_currency() !== 'EUR'
			&& get_woocommerce_currency() !== WC_EBANX_Constants::$LOCAL_CURRENCIES[strtolower(self::$order->billing_country)] ) {
			self::add_error(sprintf(__('We can\'t proccess %s in the selected country.', 'woocommerce-gateway-ebanx'), get_woocommerce_currency()));
		}
	}

	/**
	 * Checks if the the order total amount is greater than 1
	 *
	 * @return void
	 */
	private static function check_min_amount() {
		if ( self::$order->get_total() < 1 ) {
			self::add_error(__('The total amount needs to be greater than $1.', 'woocommerce-gateway-ebanx'));
		}
	}

	/**
	 * Checks if EBANX supports the selected country
	 *
	 * @return void
	 */
	private static function check_supported_country() {
		if ( ! in_array(strtolower(self::$order->billing_country), WC_EBANX_Constants::$ALL_COUNTRIES) ) {
			self::add_error(__('EBANX only support the countries: Brazil, Mexico, Peru, Colombia and Chile. Please, use one of these.', 'woocommerce-gateway-ebanx'));
		}
	}

	/**
	 * Checks if the email provided is valid
	 *
	 * @return void
	 */
	private static function check_valid_email() {
		if ( ! filter_var(self::$order->billing_email, FILTER_VALIDATE_EMAIL) ) {
			self::add_error(__('The customer e-mal is required, please provide a valid customer e-mail.', 'woocommerce-gateway-ebanx'));
		}
	}

	/**
	 * Checks if the the payment method is not EBANX Account
	 * AND checks if the payment method is a EBANX payment method
	 * AND checks if the selected payment method is available on the selected country
	 *
	 * @return void
	 */
	private static function check_payment_method() {
		if ( self::$order->payment_method === 'ebanx-account' ) {
			self::add_error(__('Paying with EBANX account is not avaible yet.', 'woocommerce-gateway-ebanx'));
			return count(self::$errors);
		}
		else if ( ! array_key_exists(self::$order->payment_method, WC_EBANX_Constants::$GATEWAY_TO_PAYMENT_TYPE_CODE) ) {
			self::add_error(__('EBANX does not support the selected payment method.', 'woocommerce-gateway-ebanx'));
		}
		else if ( array_key_exists(strtolower(self::$order->billing_country), WC_EBANX_Constants::$EBANX_GATEWAYS_BY_COUNTRY)
			&& ! in_array(self::$order->payment_method, WC_EBANX_Constants::$EBANX_GATEWAYS_BY_COUNTRY[strtolower(self::$order->billing_country)]) ) {
			self::add_error(__('The selected payment method is not available on the selected country.', 'woocommerce-gateway-ebanx'));
		}
	}
}
