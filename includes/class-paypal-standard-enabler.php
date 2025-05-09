<?php
/**
 * PayPal Standard Enabler for Server
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WPPPS_PayPal_Standard_Enabler {
    
    public function __construct() {
        // Enable PayPal Standard for WooCommerce
        add_filter('woocommerce_should_load_paypal_standard', '__return_true', 9999999);
        
        // Make sure the PayPal gateway is marked to load
        add_action('plugins_loaded', array($this, 'enable_paypal_gateway'), 20);
    }
    
    /**
     * Enable PayPal gateway and update its option to load
     */
    public function enable_paypal_gateway() {
        $paypal = class_exists('WC_Gateway_Paypal') ? new WC_Gateway_Paypal() : null;
        if ($paypal) {
            $paypal->update_option('_should_load', 'yes');
        }
    }
}