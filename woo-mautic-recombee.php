<?php
/*
Plugin Name: Mautic Recombee for WooCommerce
Plugin URI: https://mtcextendee.com
Description: This plug-in is designed to integrate Recombee service to Mautic for WooCommerce
Version: 0.1.0.0
Author: MTC Extendee
Author URI: http://mtcextendee.com
Developer: Kuzmany
Developer URI: https://bit.ly/2H7Effg
Text Domain: woo-mautic-recombee
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

use Mautic\Auth\ApiAuth;

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	if ( is_admin() ){
		if (! ( get_option( 'mautic_woocommerce_settings_server' ) ) ) {
			add_action( 'admin_notices', 'mautic_woocommerce_admin_notice_no_configuration' );
		}
		add_filter( 'woocommerce_settings_tabs_array', 'mautic_woocommerce_add_settings_tab', 50 );
		add_action( 'woocommerce_settings_tabs_mautic', 'mautic_woocommerce_settings_tab' );
		add_action( 'woocommerce_update_options_mautic', 'mautic_woocommerce_settings_tab_update' );
	}
	else {
	}
}

/* 
Plug-in management
*/

function mautic_woocommerce_add_settings_tab($settings_tabs) {
//	authorize_mautic();
	$settings_tabs['mautic'] = __( 'Mautic Recombee', 'woo-mautic-recombee' );
	return $settings_tabs;
}

function mautic_woocommerce_settings_tab() {
    woocommerce_admin_fields( mautic_woocommerce_tab_settings() );
}

function mautic_woocommerce_settings_tab_update() {
    woocommerce_update_options( mautic_woocommerce_tab_settings() );
}

function mautic_woocommerce_tab_settings() {
	$settings = array(

		'mautic_section_title' => array(
			'name' => __('Connection to your Mautic server', 'woo-mautic-recombee'),
			'type' => 'title',
			'desc' => __('The following options are used to connect to your Mautic server.', 'woo-mautic-recombee'),
			'id' => 'mautic_woocommerce_settings_mautic_section_title'
		),
		'server' => array(
			'name' => __('Mautic server', 'woo-mautic-recombee'),
			'type' => 'text',
			'css' => 'min-width:200px;',
			'desc_tip' => __('Your Mautic server (including http/https, no trailing /)', 'woo-mautic-recombee'),
			'placeholder' =>  __('Your Mautic server (including http/https, no trailing /)', 'woo-mautic-recombee'),
			'id' => 'mautic_woocommerce_settings_server'
		),

		'mautic_username' => array(
			'name' => __('Mautic username', 'woo-mautic-recombee'),
			'type' => 'text',
			'css' => 'min-width:200px;',
			'id' => 'mautic_woocommerce_settings_mautic_username'
		),
		'mautic_password' => array(
			'name' => __('Mautic secret key', 'woo-mautic-recombee'),
			'type' => 'password',
			'css' => 'min-width:200px;',
			'id' => 'mautic_woocommerce_settings_mautic_password'
		),
		'mautic_section_end' => array(
			'type' => 'sectionend',
			'id' => 'mautic_woocommerce_settings_mautic_section_end'
		),

	);
	return apply_filters( 'mautic_woocommerce_settings', $settings );
}

function api_mautic() {

	$settings = array(
		'userName'         => get_option( 'mautic_woocommerce_settings_mautic_username' ),
		'password'      => get_option( 'mautic_woocommerce_settings_mautic_password' ),
	);

	$initAuth = new ApiAuth();
	$api = new \Mautic\MauticApi();
	$auth = $initAuth->newAuth($settings, 'BasicAuth');
	return $api->newApi('api', $auth, get_option( 'mautic_woocommerce_settings_server'));;
}


/**
 * Add to cart
 *
 * @param $cart_item_key
 * @param $product_id
 * @param $quantity
 * @param $variation_id
 * @param $variation
 * @param $cart_item_data
 */
function action_woocommerce_add_to_cart( $cart_item_key,  $product_id, $quantity,  $variation_id, $variation, $cart_item_data ) {
	/** @var WC_Product_Simple  $product */
	$product = wc_get_product($product_id);
	$options = ['itemId' => $product_id, 'amount'=>$quantity, 'price'=>$product->get_price()*$quantity];
	mautic_recombee_update_api('AddCartAddition', $options);
};

add_action( 'woocommerce_add_to_cart',  'action_woocommerce_add_to_cart', 10, 6);

/**
 * Remove from cart
 *
 * @param         $cart_item_key
 * @param WC_Cart $cart
 */
function action_woocommerce_remove_cart_item( $cart_item_key,  WC_Cart $cart) {

	$item = $cart->get_cart_item($cart_item_key);
	$options = ['itemId' => $item['product_id']];
	mautic_recombee_update_api('DeleteCartAddition', $options);
};

add_action( 'woocommerce_remove_cart_item',  'action_woocommerce_remove_cart_item', 10, 6);

/**
 * Add purchase
 *
 * @param $order_id
 */
function action_woocommerce_thankyou( $order_id ) {

	if ( ! $order_id )
		return;

	// Getting an instance of the order object
	$order = wc_get_order( $order_id );
	// iterating through each order items (getting product ID and the product object)
	// (work for simple and variable products)
	foreach ( $order->get_items() as $item_id=>$item ) {
		$options = ['itemId' => $item['product_id'], 'amount'=>$item->get_quantity(), 'price'=>$order->get_item_total( $item )];
		mautic_recombee_update_api('AddPurchase', $options);
	}
}

add_action('woocommerce_thankyou', 'action_woocommerce_thankyou', 10, 1);


/**
 * @param WC_Product $product
 */
function action_woocommerce_after_single_product($product) {
    mautic_recombee_update_api('AddDetailView', ['itemId'=>$product->get_id()]);
}

add_action( 'woocommerce_product_additional_information', 'action_action_woocommerce_after_single_product', 5 );



/**
 * @param       $component
 * @param array $options
 */
function mautic_recombee_update_api($component, $options = []) {
	//$options['userId'] = 1;
	if(!isset($options['userId']) && !isset($_COOKIE['mtc_id']))
	{
		return;
	}
	if(!isset($options['userId'])){
		$options['userId'] = $_COOKIE['mtc_id'];
	}
    try {
        $result = api_mautic()->makeRequest('recombee/'.$component, $options, 'POST');
    } catch (Exception $exception) {
        // $exception->getMessage();
    }
}

function mautic_woocommerce_admin_notice_no_configuration() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e( 'The Woocomerce integration for Mautic Recombee bundle', 'woo-mautic-recombee' ); ?></p>
    </div>
    <?php
}

add_action('plugins_loaded', 'mautic_woocommerce_load_textdomain');
function mautic_woocommerce_load_textdomain() {
	load_plugin_textdomain( 'woo-mautic-recombee', false, dirname( plugin_basename(__FILE__) ) . '/lang/' );
}