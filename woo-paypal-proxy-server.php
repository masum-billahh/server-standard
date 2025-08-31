<?php
/**
 * Plugin Name: WooCommerce PayPal Proxy Server
 * Plugin URI: https://yourwebsite.com
 * Description: Serves as a proxy for PayPal payments from external WooCommerce stores
 * Version: 1.0.0
 * Author: Masum Bilah
 * Author URI: https://yourwebsite.com
 * Text Domain: woo-paypal-proxy-server
 * Domain Path: /languages
 * WC requires at least: 5.0.0
 * WC tested up to: 8.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPPPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPPPS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPPPS_VERSION', '1.0.0');

/**
 * Check if WooCommerce is active
 */
function wppps_check_woocommerce_active() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wppps_woocommerce_missing_notice');
        return false;
    }
    return true;
}

add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Display WooCommerce missing notice
 */
function wppps_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('WooCommerce PayPal Proxy Server requires WooCommerce to be installed and active.', 'woo-paypal-proxy-server'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function wppps_init() {
    if (!wppps_check_woocommerce_active()) {
        return;
    }
    
    // Load required files
    require_once WPPPS_PLUGIN_DIR . 'includes/class-paypal-api.php';
    require_once WPPPS_PLUGIN_DIR . 'includes/class-rest-api.php';
    require_once WPPPS_PLUGIN_DIR . 'includes/class-admin.php';
    require_once WPPPS_PLUGIN_DIR . 'includes/class-woo-paypal-gateway-server.php';

    
    // Initialize classes
    $paypal_api = new WPPPS_PayPal_API();
    $rest_api = new WPPPS_REST_API($paypal_api);
    $admin = new WPPPS_Admin();
    
    add_filter('woocommerce_payment_gateways', 'wppps_add_gateway');

    
    // Register REST API routes
    add_action('rest_api_init', array($rest_api, 'register_routes'));
    
    // Add scripts and styles
    add_action('wp_enqueue_scripts', 'wppps_enqueue_scripts');
}
add_action('plugins_loaded', 'wppps_init');

/**
 * Enqueue scripts and styles
 */
function wppps_enqueue_scripts() {
    // Only enqueue on the PayPal Buttons template
    if (is_page_template('paypal-buttons-template.php') || isset($_GET['rest_route'])) {
        wp_enqueue_style('wppps-paypal-style', WPPPS_PLUGIN_URL . 'assets/css/paypal-buttons.css', array(), WPPPS_VERSION);
        wp_enqueue_script('wppps-paypal-script', WPPPS_PLUGIN_URL . 'assets/js/paypal-buttons.js', array('jquery'), WPPPS_VERSION, true);
        
        // Load PayPal SDK
        $paypal_api = new WPPPS_PayPal_API();
        $client_id = $paypal_api->get_client_id();
        $environment = $paypal_api->get_environment();
        
        wp_enqueue_script(
            'paypal-sdk',
            "https://www.paypal.com/sdk/js?client-id={$client_id}&currency=USD&intent=capture",
            array(),
            null,
            true
        );
        
        // Add localized data for the script
        wp_localize_script('wppps-paypal-script', 'wppps_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url(),
            'nonce' => wp_create_nonce('wppps-nonce'),
            'environment' => $environment,
        ));
    }
}

/**
 * Add settings link on plugin page
 */
function wppps_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wppps-settings">' . __('Settings', 'woo-paypal-proxy-server') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'wppps_settings_link');

/**
 * Plugin activation hook
 */
function wppps_activate() {
    // Create necessary database tables or options if needed
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wppps_sites';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        site_url varchar(255) NOT NULL,
        site_name varchar(255) NOT NULL,
        api_key varchar(64) NOT NULL,
        api_secret varchar(64) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY api_key (api_key)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Create a transaction log table
    $log_table = $wpdb->prefix . 'wppps_transaction_log';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$log_table} LIKE 'mirrored_order_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$log_table} ADD COLUMN `mirrored_order_id` bigint(20) NULL DEFAULT NULL");
        }
    
    $sql = "CREATE TABLE $log_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        site_id mediumint(9) NOT NULL,
        order_id varchar(64) NOT NULL,
        paypal_order_id varchar(64) NOT NULL,
        amount decimal(10,2) NOT NULL,
        currency varchar(3) NOT NULL,
        status varchar(20) NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at datetime DEFAULT NULL,
        transaction_data longtext,
        PRIMARY KEY  (id),
        KEY site_id (site_id),
        KEY order_id (order_id),
        KEY paypal_order_id (paypal_order_id)
    ) $charset_collate;";
    
    dbDelta($sql);
    
    // Add plugin options
    add_option('wppps_paypal_client_id', '');
    add_option('wppps_paypal_client_secret', '');
    add_option('wppps_paypal_environment', 'sandbox');
}
register_activation_hook(__FILE__, 'wppps_activate');

/**
 * Add PayPal Direct Gateway to WooCommerce
 */
function wppps_add_gateway($gateways) {
    $gateways[] = 'WPPPS_PayPal_Gateway';
    return $gateways;
}

// Prevent WooCommerce from recalculating taxes on mirrored orders
add_filter('woocommerce_order_get_total', function($total, $order) {
    if ($order->meta_exists('_wppps_tax_adjusted') && $order->get_meta('_wppps_tax_adjusted') === 'yes') {
        // Return the total from meta directly
        $saved_total = $order->get_meta('_order_total');
        if (!empty($saved_total)) {
            return $saved_total;
        }
    }
    return $total;
}, 99, 2);

add_filter('woocommerce_order_get_tax_totals', function($tax_totals, $order) {
    if ($order->meta_exists('_wppps_tax_adjusted') && $order->get_meta('_wppps_tax_adjusted') === 'yes') {
        // If we need to preserve specific tax totals
        return $tax_totals;
    }
    return $tax_totals;
}, 99, 2);

/**
 * AJAX handler for creating a WooCommerce order
 */


function wppps_create_order_handler() {
    check_ajax_referer('wppps-paypal-nonce', 'nonce');
    
    $debug_mode = true; // Set to false in production
    
    // Log request data
    if ($debug_mode) {
        error_log('PayPal Direct - Create Order Request: ' . json_encode($_POST));
    }
    
    try {
        // Create order from checkout form data
        $checkout_form_data = $_POST;
        
        // Validate checkout fields with improved logic
        $errors = array();
        
        // Get all checkout fields
        $checkout_fields = WC()->checkout()->get_checkout_fields();
        
        // Check if user is logged in
        $is_user_logged_in = is_user_logged_in();
        
        // Check if creating account
        $create_account = !empty($checkout_form_data['createaccount']) && $checkout_form_data['createaccount'] == 1;
        
        // Check if shipping to different address
        $ship_to_different_address = !empty($checkout_form_data['ship_to_different_address']) && $checkout_form_data['ship_to_different_address'] == 1;
        
        error_log('PayPal Direct - Validating form: User logged in: ' . ($is_user_logged_in ? 'Yes' : 'No') . 
                ', Create account: ' . ($create_account ? 'Yes' : 'No') . 
                ', Ship to different address: ' . ($ship_to_different_address ? 'Yes' : 'No'));
        
        // Loop through field groups and validate conditionally
        foreach ($checkout_fields as $fieldset_key => $fieldset) {
            // Skip account fields if user is already logged in
            if ($fieldset_key === 'account' && $is_user_logged_in) {
                continue;
            }
            
            // Skip account fields if not creating account
            if ($fieldset_key === 'account' && !$create_account) {
                continue;
            }
            
            // Skip shipping fields if not shipping to different address
            if ($fieldset_key === 'shipping' && !$ship_to_different_address) {
                continue;
            }
            
            foreach ($fieldset as $key => $field) {
                // Only validate required fields that are empty
                if (!empty($field['required']) && empty($checkout_form_data[$key])) {
                    $errors[$key] = sprintf(__('%s is a required field.', 'woocommerce'), $field['label']);
                    error_log('PayPal Direct - Field validation error: ' . $key);
                }
            }
        }
        
        // If we have validation errors, return them
        if (!empty($errors)) {
            error_log('PayPal Direct - Validation errors found: ' . json_encode($errors));
            wp_send_json_error(array(
                'message' => __('Please fill in all required fields.', 'woo-paypal-proxy-server'),
                'errors' => $errors
            ));
            wp_die();
        }
        
        // Create new order
        $order = wc_create_order();
        
        // Set billing address
        $billing_address = array(
            'first_name' => sanitize_text_field($checkout_form_data['billing_first_name']),
            'last_name'  => sanitize_text_field($checkout_form_data['billing_last_name']),
            'company'    => isset($checkout_form_data['billing_company']) ? sanitize_text_field($checkout_form_data['billing_company']) : '',
            'email'      => sanitize_email($checkout_form_data['billing_email']),
            'phone'      => isset($checkout_form_data['billing_phone']) ? sanitize_text_field($checkout_form_data['billing_phone']) : '',
            'address_1'  => sanitize_text_field($checkout_form_data['billing_address_1']),
            'address_2'  => isset($checkout_form_data['billing_address_2']) ? sanitize_text_field($checkout_form_data['billing_address_2']) : '',
            'city'       => sanitize_text_field($checkout_form_data['billing_city']),
            'state'      => sanitize_text_field($checkout_form_data['billing_state']),
            'postcode'   => sanitize_text_field($checkout_form_data['billing_postcode']),
            'country'    => sanitize_text_field($checkout_form_data['billing_country']),
        );
        $order->set_address($billing_address, 'billing');
        
        // Set shipping address
        $ship_to_different_address = isset($checkout_form_data['ship_to_different_address']) && $checkout_form_data['ship_to_different_address'];
        
        if ($ship_to_different_address) {
            $shipping_address = array(
                'first_name' => sanitize_text_field($checkout_form_data['shipping_first_name']),
                'last_name'  => sanitize_text_field($checkout_form_data['shipping_last_name']),
                'company'    => isset($checkout_form_data['shipping_company']) ? sanitize_text_field($checkout_form_data['shipping_company']) : '',
                'address_1'  => sanitize_text_field($checkout_form_data['shipping_address_1']),
                'address_2'  => isset($checkout_form_data['shipping_address_2']) ? sanitize_text_field($checkout_form_data['shipping_address_2']) : '',
                'city'       => sanitize_text_field($checkout_form_data['shipping_city']),
                'state'      => sanitize_text_field($checkout_form_data['shipping_state']),
                'postcode'   => sanitize_text_field($checkout_form_data['shipping_postcode']),
                'country'    => sanitize_text_field($checkout_form_data['shipping_country']),
            );
        } else {
            $shipping_address = $billing_address;
        }
        $order->set_address($shipping_address, 'shipping');
        
        // Add shipping method - IMPROVED VERSION
        if (WC()->session) {
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
            $shipping_packages = WC()->shipping()->get_packages();
            
            if (!empty($chosen_shipping_methods)) {
                error_log('PayPal Direct - Found chosen shipping methods: ' . json_encode($chosen_shipping_methods));
                error_log('PayPal Direct - Shipping packages: ' . json_encode($shipping_packages));
                
                foreach ($shipping_packages as $package_key => $package) {
                    if (isset($chosen_shipping_methods[$package_key], $package['rates'][$chosen_shipping_methods[$package_key]])) {
                        $shipping_rate = $package['rates'][$chosen_shipping_methods[$package_key]];
                        
                        $item = new WC_Order_Item_Shipping();
                        $item->set_props(array(
                            'method_title' => $shipping_rate->get_label(),
                            'method_id'    => $shipping_rate->get_id(),
                            'instance_id'  => $shipping_rate->get_instance_id(),
                            'total'        => wc_format_decimal($shipping_rate->get_cost()),
                            'taxes'        => $shipping_rate->get_taxes(),
                        ));
                        
                        // Add any meta data
                        foreach ($shipping_rate->get_meta_data() as $key => $value) {
                            $item->add_meta_data($key, $value, true);
                        }
                        
                        $order->add_item($item);
                        error_log('PayPal Direct - Added shipping method: ' . $shipping_rate->get_label() . ' with cost: ' . $shipping_rate->get_cost());
                    }
                }
            } else {
                // FALLBACK: Try to get shipping method directly from POST data
                error_log('PayPal Direct - No chosen shipping methods in session, checking POST data');
                
                if (!empty($checkout_form_data['shipping_method']) && is_array($checkout_form_data['shipping_method'])) {
                    foreach ($checkout_form_data['shipping_method'] as $package_key => $method_id) {
                        // Try to find the shipping rate
                        if (!empty($shipping_packages[$package_key]['rates'][$method_id])) {
                            $shipping_rate = $shipping_packages[$package_key]['rates'][$method_id];
                            
                            $item = new WC_Order_Item_Shipping();
                            $item->set_props(array(
                                'method_title' => $shipping_rate->get_label(),
                                'method_id'    => $shipping_rate->get_id(),
                                'instance_id'  => $shipping_rate->get_instance_id(),
                                'total'        => wc_format_decimal($shipping_rate->get_cost()),
                                'taxes'        => $shipping_rate->get_taxes(),
                            ));
                            
                            $order->add_item($item);
                            error_log('PayPal Direct - Added shipping method from POST: ' . $shipping_rate->get_label());
                        }
                    }
                } else if (!empty($checkout_form_data['shipping_method']) && is_string($checkout_form_data['shipping_method'])) {
                    // Handle single shipping method as string
                    $method_id = $checkout_form_data['shipping_method'];
                    
                    // Try to find this method in available packages
                    foreach ($shipping_packages as $package_key => $package) {
                        if (!empty($package['rates'][$method_id])) {
                            $shipping_rate = $package['rates'][$method_id];
                            
                            $item = new WC_Order_Item_Shipping();
                            $item->set_props(array(
                                'method_title' => $shipping_rate->get_label(),
                                'method_id'    => $shipping_rate->get_id(),
                                'instance_id'  => $shipping_rate->get_instance_id(),
                                'total'        => wc_format_decimal($shipping_rate->get_cost()),
                                'taxes'        => $shipping_rate->get_taxes(),
                            ));
                            
                            $order->add_item($item);
                            error_log('PayPal Direct - Added shipping method from string: ' . $shipping_rate->get_label());
                            break;
                        }
                    }
                }
            }
            
            // LAST RESORT: If still no shipping but cart has shipping, add a generic shipping line
if ($order->get_shipping_total() <= 0 && WC()->cart && WC()->cart->get_shipping_total() > 0) {
    $shipping_total = WC()->cart->get_shipping_total();
    error_log('PayPal Direct - Adding shipping from cart: ' . $shipping_total);
    
    // Default values
    $shipping_method_title = 'Shipping'; // default fallback
    $shipping_method_id = 'flat_rate';   // default fallback
    $instance_id = '';
    
    // Get the chosen shipping methods
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    
    if (!empty($chosen_shipping_methods)) {
        // Get the first chosen method (most sites only have one package)
        $chosen_method = reset($chosen_shipping_methods);
        error_log('PayPal Direct - Chosen method ID: ' . $chosen_method);
        
        // Parse the shipping method ID (format is usually method_id:instance_id)
        $method_parts = explode(':', $chosen_method);
        $method_id = $method_parts[0];
        $instance_id = isset($method_parts[1]) ? $method_parts[1] : '';
        
        $shipping_method_id = $method_id;
        
        // Try to find the method in shipping zones
        foreach (WC_Shipping_Zones::get_zones() as $zone) {
            foreach ($zone['shipping_methods'] as $method) {
                if ($method->id . ':' . $method->instance_id === $chosen_method) {
                    $shipping_method_title = $method->get_title();
                    error_log('PayPal Direct - Found method in zones: ' . $shipping_method_title);
                    break 2; // Break both loops
                }
            }
        }
        
        // If not found in zones, check the "rest of the world" zone
        if ($shipping_method_title === 'Shipping') {
            $rest_of_world = new WC_Shipping_Zone(0);
            $shipping_methods = $rest_of_world->get_shipping_methods();
            
            foreach ($shipping_methods as $method) {
                if ($method->id . ':' . $method->instance_id === $chosen_method) {
                    $shipping_method_title = $method->get_title();
                    error_log('PayPal Direct - Found method in rest of world zone: ' . $shipping_method_title);
                    break;
                }
            }
        }
        
        error_log('PayPal Direct - Using shipping method title: ' . $shipping_method_title);
    }
    
    $item = new WC_Order_Item_Shipping();
    $item->set_props(array(
        'method_title' => $shipping_method_title,
        'method_id'    => $shipping_method_id,
        'total'        => wc_format_decimal($shipping_total),
        'taxes'        => WC()->cart->get_shipping_taxes(),
    ));
    
    // Add instance ID if available
    if (!empty($instance_id)) {
        $item->set_instance_id($instance_id);
    }
    
    $order->add_item($item);
    error_log('PayPal Direct - Added shipping with title: ' . $shipping_method_title);
}
        }
        
        // Add cart items
        if (WC()->cart->is_empty()) {
            wp_send_json_error(array(
                'message' => __('Your cart is empty. Please add some items to your cart before checking out.', 'woo-paypal-proxy-server')
            ));
            wp_die();
        }
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            
            // Add line item
            $item_id = $order->add_product(
                $product,
                $quantity,
                array(
                    'total'    => wc_format_decimal($cart_item['line_total'], wc_get_price_decimals()),
                    'subtotal' => wc_format_decimal($cart_item['line_subtotal'], wc_get_price_decimals()),
                    'taxes'    => $cart_item['line_tax_data']
                )
            );
            
            // Add line item meta data
            if (!empty($cart_item['variation_id'])) {
                wc_add_order_item_meta($item_id, '_variation_id', $cart_item['variation_id']);
            }
            
            if (!empty($cart_item['variation'])) {
                foreach ($cart_item['variation'] as $name => $value) {
                    wc_add_order_item_meta($item_id, sanitize_text_field($name), sanitize_text_field($value));
                }
            }
        }
        
        // Add fees
        foreach (WC()->cart->get_fees() as $fee_key => $fee) {
            $item = new WC_Order_Item_Fee();
            $item->set_props(array(
                'name'   => $fee->name,
                'tax_class' => $fee->tax_class,
                'total'  => $fee->amount,
                'total_tax' => $fee->tax,
                'taxes'  => array(
                    'total' => $fee->tax_data,
                ),
            ));
            $order->add_item($item);
        }
        
        // Add coupons
        foreach (WC()->cart->get_coupons() as $code => $coupon) {
            $item = new WC_Order_Item_Coupon();
            $item->set_props(array(
                'code'         => $code,
                'discount'     => WC()->cart->get_coupon_discount_amount($code),
                'discount_tax' => WC()->cart->get_coupon_discount_tax_amount($code),
            ));
            $item->add_meta_data('coupon_data', $coupon->get_data());
            $order->add_item($item);
        }
        
        // Set payment method
        $order->set_payment_method('paypal_direct');
        
        // Calculate totals
        $order->calculate_totals();
        
        // Log the order totals to debug shipping issues
        error_log('PayPal Direct - Order Totals:');
        error_log('PayPal Direct - Subtotal: ' . $order->get_subtotal());
        error_log('PayPal Direct - Shipping Total: ' . $order->get_shipping_total());
        error_log('PayPal Direct - Tax Total: ' . $order->get_total_tax());
        error_log('PayPal Direct - Grand Total: ' . $order->get_total());
        
        // Update order status
        $order->update_status('pending', __('Order created, awaiting PayPal payment', 'woo-paypal-proxy-server'));
        
        // Store order ID in user meta
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, '_last_paypal_direct_order', $order->get_id());
        }
        
        // Log success
        if ($debug_mode) {
            error_log('PayPal Direct - Order created successfully: #' . $order->get_id());
        }
        
        // Return order information
        wp_send_json_success(array(
            'order_id'   => $order->get_id(),
            'order_key'  => $order->get_order_key(),
        ));
    } catch (Exception $e) {
        // Log error
        error_log('PayPal Direct - Error creating order: ' . $e->getMessage());
        
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
    
    wp_die();
}
add_action('wp_ajax_wppps_create_order', 'wppps_create_order_handler');
add_action('wp_ajax_nopriv_wppps_create_order', 'wppps_create_order_handler');

/**
 * AJAX handler for creating a PayPal order
 */
function wppps_create_paypal_order_handler() {
    check_ajax_referer('wppps-paypal-nonce', 'nonce');
    
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    if (!$order_id) {
        wp_send_json_error(array(
            'message' => __('Invalid order ID', 'woo-paypal-proxy-server')
        ));
        wp_die();
    }
    
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error(array(
            'message' => __('Order not found', 'woo-paypal-proxy-server')
        ));
        wp_die();
    }
    
    try {
        // Initialize PayPal API
        $paypal_api = new WPPPS_PayPal_API();
        
        // Prepare order data for PayPal
        $amount = $order->get_total();
        $currency = $order->get_currency();
        $reference_id = $order->get_id();
        
        // Prepare line items
        $line_items = array();
        $subtotal = 0;
        
        // Add line items
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $item_name = $item->get_name();
            $quantity = $item->get_quantity();
            $unit_price = $order->get_item_subtotal($item, false, false);
            $tax_amount = $item->get_total_tax();
            
            $subtotal += $unit_price * $quantity;
            
            $line_items[] = array(
                'name' => $item_name,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'tax_amount' => $tax_amount,
                'sku' => $product ? $product->get_sku() : '',
                'description' => $product ? substr(wp_strip_all_tags($product->get_short_description()), 0, 127) : ''
            );
        }
        
        // Get shipping details
        $shipping_amount = $order->get_shipping_total();
        $shipping_tax = $order->get_shipping_tax();
        
        // Log shipping amount to verify it's actually present
        error_log('PayPal Direct - Order shipping amount: ' . $shipping_amount);
        
        // Get shipping methods from order
        $shipping_items = $order->get_items('shipping');
        $shipping_methods = array();
        
        foreach ($shipping_items as $shipping_item) {
            $shipping_methods[] = array(
                'method_title' => $shipping_item->get_method_title(),
                'method_id' => $shipping_item->get_method_id(),
                'instance_id' => $shipping_item->get_instance_id(),
                'total' => $shipping_item->get_total(),
                'total_tax' => $shipping_item->get_total_tax(),
            );
            error_log('PayPal Direct - Shipping method: ' . $shipping_item->get_method_title() . ' - ' . $shipping_item->get_total());
        }
        
        // Prepare billing address
        $billing_address = array(
            'first_name' => $order->get_billing_first_name(),
            'last_name'  => $order->get_billing_last_name(),
            'address_1'  => $order->get_billing_address_1(),
            'address_2'  => $order->get_billing_address_2(),
            'city'       => $order->get_billing_city(),
            'state'      => $order->get_billing_state(),
            'postcode'   => $order->get_billing_postcode(),
            'country'    => $order->get_billing_country(),
            'email'      => $order->get_billing_email(),
            'phone'      => $order->get_billing_phone()
        );
        
        // Prepare shipping address
        $shipping_address = array(
            'first_name' => $order->get_shipping_first_name(),
            'last_name'  => $order->get_shipping_last_name(),
            'address_1'  => $order->get_shipping_address_1(),
            'address_2'  => $order->get_shipping_address_2(),
            'city'       => $order->get_shipping_city(),
            'state'      => $order->get_shipping_state(),
            'postcode'   => $order->get_shipping_postcode(),
            'country'    => $order->get_shipping_country()
        );
        
        // Prepare custom data
        $custom_data = array(
            'line_items' => $line_items,
            'shipping_amount' => $shipping_amount,
            'shipping_tax' => $shipping_tax,
            'shipping_methods' => $shipping_methods,
            'tax_total' => $order->get_total_tax(),
            'billing_address' => $billing_address,
            'shipping_address' => $shipping_address,
            'description' => 'Order #' . $order->get_id()
        );
        
        // Create PayPal order
        $paypal_order = $paypal_api->create_order(
            $amount,
            $currency,
            $reference_id,
            $order->get_checkout_order_received_url(),
            wc_get_cart_url(),
            $custom_data
        );
        
        if (is_wp_error($paypal_order)) {
            throw new Exception($paypal_order->get_error_message());
        }
        
        // Store PayPal order ID in WooCommerce order meta
        $order->update_meta_data('_paypal_order_id', $paypal_order['id']);
        $order->save();
        
        // Log the PayPal order ID
        error_log('PayPal Direct - PayPal Order ID for WooCommerce Order #' . $order->get_id() . ': ' . $paypal_order['id']);
        
        // Return PayPal order details
        wp_send_json_success(array(
            'id' => $paypal_order['id'],
            'status' => $paypal_order['status']
        ));
    } catch (Exception $e) {
        error_log('PayPal Direct - Error creating PayPal order: ' . $e->getMessage());
        
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
    
    wp_die();
}
add_action('wp_ajax_wppps_create_paypal_order', 'wppps_create_paypal_order_handler');
add_action('wp_ajax_nopriv_wppps_create_paypal_order', 'wppps_create_paypal_order_handler');

/**
 * AJAX handler for capturing PayPal payment
 */
function wppps_capture_paypal_payment_handler() {
    check_ajax_referer('wppps-paypal-nonce', 'nonce');
    
    $paypal_order_id = isset($_POST['paypal_order_id']) ? sanitize_text_field($_POST['paypal_order_id']) : '';
    
    if (empty($paypal_order_id)) {
        wp_send_json_error(array(
            'message' => __('PayPal order ID is required', 'woo-paypal-proxy-server')
        ));
        wp_die();
    }
    
    try {
        // Initialize PayPal API
        $paypal_api = new WPPPS_PayPal_API();
        
        // Capture the payment
        $capture = $paypal_api->capture_payment($paypal_order_id);
        
        if (is_wp_error($capture)) {
            throw new Exception($capture->get_error_message());
        }
        
        // Extract transaction ID
        $transaction_id = '';
        if (!empty($capture['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $transaction_id = $capture['purchase_units'][0]['payments']['captures'][0]['id'];
        }
        
        // Log the capture
        error_log('PayPal Direct - Payment captured for PayPal Order ID: ' . $paypal_order_id . ', Transaction ID: ' . $transaction_id);
        
        // Return capture details
        wp_send_json_success(array(
            'status' => $capture['status'],
            'transaction_id' => $transaction_id
        ));
    } catch (Exception $e) {
        error_log('PayPal Direct - Error capturing payment: ' . $e->getMessage());
        
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
    
    wp_die();
}
add_action('wp_ajax_wppps_capture_paypal_payment', 'wppps_capture_paypal_payment_handler');
add_action('wp_ajax_nopriv_wppps_capture_paypal_payment', 'wppps_capture_paypal_payment_handler');

/**
 * AJAX handler for completing order after payment - FIXED VERSION
 */
function wppps_complete_order_handler() {
    check_ajax_referer('wppps-paypal-nonce', 'nonce');
    
    $paypal_order_id = isset($_POST['paypal_order_id']) ? sanitize_text_field($_POST['paypal_order_id']) : '';
    $transaction_id = isset($_POST['transaction_id']) ? sanitize_text_field($_POST['transaction_id']) : '';
    $wc_order_id = isset($_POST['wc_order_id']) ? intval($_POST['wc_order_id']) : 0;
    
    // Debug log
    error_log('PayPal Direct - Completing order. PayPal Order ID: ' . $paypal_order_id);
    error_log('PayPal Direct - Transaction ID: ' . $transaction_id);
    error_log('PayPal Direct - WC Order ID from frontend: ' . $wc_order_id);
    
    if (empty($paypal_order_id)) {
        wp_send_json_error(array(
            'message' => __('PayPal order ID is required', 'woo-paypal-proxy-server')
        ));
        wp_die();
    }
    
    // First try using the WC order ID from the request if provided
    $order = null;
    if ($wc_order_id) {
        $order = wc_get_order($wc_order_id);
        error_log('PayPal Direct - Using WC order ID from request: ' . $wc_order_id);
    }
    
    // If no order found, try finding by meta
    if (!$order) {
        error_log('PayPal Direct - Searching for order by PayPal Order ID meta');
        
        // Use direct SQL query for more reliable results
        global $wpdb;
        $meta_table = $wpdb->prefix . 'postmeta';
        $posts_table = $wpdb->prefix . 'posts';
        
        $sql = $wpdb->prepare(
            "SELECT post_id FROM $meta_table WHERE meta_key = %s AND meta_value = %s 
             AND post_id IN (SELECT ID FROM $posts_table WHERE post_type = 'shop_order')",
            '_paypal_order_id',
            $paypal_order_id
        );
        
        $order_id = $wpdb->get_var($sql);
        error_log('PayPal Direct - SQL query result for order ID: ' . ($order_id ? $order_id : 'Not found'));
        
        if ($order_id) {
            $order = wc_get_order($order_id);
        }
    }
    
    // Try to find any recent pending order as a fallback
    if (!$order) {
        error_log('PayPal Direct - No order found by meta, trying to find recent pending orders');
        
        // Get recent pending orders
        $orders = wc_get_orders(array(
            'status' => 'pending',
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        error_log('PayPal Direct - Found ' . count($orders) . ' recent pending orders');
        
        // Loop through recent orders
        foreach ($orders as $recent_order) {
            $recent_id = $recent_order->get_id();
            error_log('PayPal Direct - Checking order #' . $recent_id);
            
            // If this recent order is associated with our session or user, use it
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $last_order_id = get_user_meta($user_id, '_last_paypal_direct_order', true);
                
                if ($last_order_id == $recent_id) {
                    $order = $recent_order;
                    error_log('PayPal Direct - Found matching order by user meta: ' . $recent_id);
                    break;
                }
            }
        }
    }
    
    if (!$order) {
        error_log('PayPal Direct - Could not find any matching order for PayPal ID: ' . $paypal_order_id);
        wp_send_json_error(array(
            'message' => __('Order not found for this PayPal transaction', 'woo-paypal-proxy-server')
        ));
        wp_die();
    }
    
    try {
        $order_id = $order->get_id();
        error_log('PayPal Direct - Using order ID: ' . $order_id);
        
        // Check if payment is already completed to avoid duplicate processing
        if ($order->is_paid()) {
            error_log('PayPal Direct - Order #' . $order_id . ' is already paid, redirecting to thank you page');
            wp_send_json_success(array(
                'redirect' => $order->get_checkout_order_received_url()
            ));
            wp_die();
        }
        
        // Store PayPal order ID in case it wasn't stored earlier
        $order->update_meta_data('_paypal_order_id', $paypal_order_id);
        
        // Update order status to processing/completed
        if ($order->get_status() === 'pending') {
            // Add payment complete
            $order->payment_complete($transaction_id);
            
            // Add order note
            $order->add_order_note(
                sprintf(__('Payment completed via PayPal. Transaction ID: %s', 'woo-paypal-proxy-server'), $transaction_id)
            );
            
            // Store transaction ID
            if (!empty($transaction_id)) {
                $order->update_meta_data('_paypal_transaction_id', $transaction_id);
            }
            
            // Save the order
            $order->save();
            
            // Log the completion
            error_log('PayPal Direct - Order #' . $order_id . ' completed with transaction ID: ' . $transaction_id);
            
            // Empty cart
            WC()->cart->empty_cart();
        }
        
        // Return success with redirect URL
        wp_send_json_success(array(
            'redirect' => $order->get_checkout_order_received_url()
        ));
    } catch (Exception $e) {
        error_log('PayPal Direct - Error completing order: ' . $e->getMessage());
        
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
    
    wp_die();
}
add_action('wp_ajax_wppps_complete_order', 'wppps_complete_order_handler');
add_action('wp_ajax_nopriv_wppps_complete_order', 'wppps_complete_order_handler');


function wppps_find_order_handler() {
    check_ajax_referer('wppps-paypal-nonce', 'nonce');
    
    $paypal_order_id = isset($_POST['paypal_order_id']) ? sanitize_text_field($_POST['paypal_order_id']) : '';
    
    if (empty($paypal_order_id)) {
        wp_send_json_error(array(
            'message' => __('PayPal order ID is required', 'woo-paypal-proxy-server')
        ));
        wp_die();
    }
    
    error_log('PayPal Direct - Looking up order by PayPal ID: ' . $paypal_order_id);
    
    // First, try to find by meta directly
    global $wpdb;
    $meta_table = $wpdb->prefix . 'postmeta';
    $posts_table = $wpdb->prefix . 'posts';
    
    $sql = $wpdb->prepare(
        "SELECT post_id FROM $meta_table WHERE meta_key = %s AND meta_value = %s 
         AND post_id IN (SELECT ID FROM $posts_table WHERE post_type = 'shop_order')",
        '_paypal_order_id',
        $paypal_order_id
    );
    
    $order_id = $wpdb->get_var($sql);
    
    if ($order_id) {
        error_log('PayPal Direct - Found order #' . $order_id . ' by meta query');
        $order = wc_get_order($order_id);
        
        wp_send_json_success(array(
            'order_id' => $order_id,
            'order_status' => $order ? $order->get_status() : 'unknown'
        ));
        wp_die();
    }
    
    // Try to get the most recent pending orders
    $args = array(
        'status' => 'pending',
        'limit' => 5,
        'orderby' => 'date',
        'order' => 'DESC',
    );
    
    $orders = wc_get_orders($args);
    error_log('PayPal Direct - Found ' . count($orders) . ' recent pending orders to check');
    
    $order_ids = array();
    foreach ($orders as $order) {
        $order_ids[] = $order->get_id();
    }
    
    if (!empty($order_ids)) {
        wp_send_json_success(array(
            'order_id' => null,
            'possible_order_ids' => $order_ids,
            'message' => 'No direct match found, but found ' . count($order_ids) . ' recent pending orders'
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'No matching orders found'
        ));
    }
    
    wp_die();
}

// Register the AJAX handler
add_action('wp_ajax_wppps_find_order', 'wppps_find_order_handler');
add_action('wp_ajax_nopriv_wppps_find_order', 'wppps_find_order_handler');

// Helper function to get order ID by meta key and value
if (!function_exists('wc_get_order_id_by_meta_key_and_value')) {
    function wc_get_order_id_by_meta_key_and_value($meta_key, $meta_value) {
        global $wpdb;
        
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            $meta_key,
            $meta_value
        ));
        
        return $order_id;
    }
}


/**
 * AJAX handler for deleting a single transaction
 */
function wppps_delete_transaction_handler() {
    // Check nonce
    $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
    check_ajax_referer('delete_transaction_' . $transaction_id, 'nonce');
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => __('Permission denied.', 'woo-paypal-proxy-server')
        ));
        wp_die();
    }
    
    if (!$transaction_id) {
        wp_send_json_error(array(
            'message' => __('Invalid transaction ID.', 'woo-paypal-proxy-server')
        ));
        wp_die();
    }
    
    // Get admin instance
    $admin = new WPPPS_Admin();
    
    // Delete transaction
    $result = $admin->delete_transaction($transaction_id);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => __('Transaction deleted successfully.', 'woo-paypal-proxy-server')
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Failed to delete transaction.', 'woo-paypal-proxy-server')
        ));
    }
    
    wp_die();
}
add_action('wp_ajax_wppps_delete_transaction', 'wppps_delete_transaction_handler');




/**
 * Process Express Checkout webhook from PayPal for shipping address changes
 */
function wppps_process_express_shipping_webhook($request) {
    error_log('Express Checkout: Processing shipping webhook');
    
    $payload = json_decode($request->get_body(), true);
    
    if (empty($payload)) {
        error_log('Express Checkout: Empty payload in shipping webhook');
        return new WP_Error(
            'invalid_payload',
            'Invalid or empty webhook payload',
            array('status' => 400)
        );
    }
    
    error_log('Express Checkout: Webhook payload: ' . json_encode($payload));
    
    // Extract necessary data
    $resource_type = isset($payload['resource_type']) ? $payload['resource_type'] : '';
    $resource = isset($payload['resource']) ? $payload['resource'] : array();
    $event_type = isset($payload['event_type']) ? $payload['event_type'] : '';
    
    if ($resource_type !== 'checkout-shipping-address-change' || empty($resource)) {
        error_log('Express Checkout: Invalid resource type or empty resource');
        return new WP_REST_Response(array('success' => false), 400);
    }
    
    // Extract PayPal order ID and shipping address
    $paypal_order_id = isset($resource['id']) ? $resource['id'] : '';
    $shipping_address = isset($resource['shipping_address']) ? $resource['shipping_address'] : array();
    
    if (empty($paypal_order_id) || empty($shipping_address)) {
        error_log('Express Checkout: Missing PayPal order ID or shipping address');
        return new WP_REST_Response(array('success' => false), 400);
    }
    
    error_log('Express Checkout: Processing shipping change for PayPal order ' . $paypal_order_id);
    
    // Look up the order in our database to get the callback URL
    global $wpdb;
    $transactions_table = $wpdb->prefix . 'wppps_transaction_log';
    
    $transaction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $transactions_table WHERE paypal_order_id = %s ORDER BY id DESC LIMIT 1",
        $paypal_order_id
    ));
    
    if (!$transaction) {
        error_log('Express Checkout: No transaction found for PayPal order ' . $paypal_order_id);
        return new WP_REST_Response(array('success' => false), 404);
    }
    
    // Get the site information
    $sites_table = $wpdb->prefix . 'wppps_sites';
    $site = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sites_table WHERE id = %d",
        $transaction->site_id
    ));
    
    if (!$site) {
        error_log('Express Checkout: No site found for transaction');
        return new WP_REST_Response(array('success' => false), 404);
    }
    
    // Get the stored express checkout data
    $express_data = get_transient('wppps_express_checkout_' . $site->id . '_' . $transaction->order_id);
    
    if (!$express_data || empty($express_data['callback_url'])) {
        error_log('Express Checkout: No callback URL found for transaction');
        return new WP_REST_Response(array('success' => false), 404);
    }
    
    // Call the callback URL to get shipping options
    $callback_url = $express_data['callback_url'];
    
    // Generate security hash
    $timestamp = time();
    $hash = hash_hmac('sha256', $timestamp . $transaction->order_id . $paypal_order_id . $site->api_key, $site->api_secret);
    
    // Add query parameters to callback URL
    $callback_url = add_query_arg(array(
        'order_id' => $transaction->order_id,
        'paypal_order_id' => $paypal_order_id,
        'hash' => $hash,
        'timestamp' => $timestamp
    ), $callback_url);
    
    error_log('Express Checkout: Calling callback URL: ' . $callback_url);
    
    // Send shipping address to callback URL
    $response = wp_remote_post($callback_url, array(
        'body' => array(
            'shipping_data' => json_encode(array(
                'address' => $shipping_address,
                'name' => isset($resource['shipping_name']) ? $resource['shipping_name'] : array()
            ))
        ),
        'timeout' => 30
    ));
    
    // Check for errors
    if (is_wp_error($response)) {
        error_log('Express Checkout: Error calling callback URL: ' . $response->get_error_message());
        return new WP_REST_Response(array('success' => false), 500);
    }
    
    // Get response code
    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code !== 200) {
        error_log('Express Checkout: Callback URL returned error code: ' . $response_code);
        return new WP_REST_Response(array('success' => false), 500);
    }
    
    // Get shipping options from response
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (!$body || !isset($body['success']) || $body['success'] !== true) {
        error_log('Express Checkout: Invalid response from callback URL: ' . wp_remote_retrieve_body($response));
        return new WP_REST_Response(array('success' => false), 500);
    }
    
    // Extract shipping options
    $shipping_options = isset($body['shipping_options']) ? $body['shipping_options'] : array();
    
    if (empty($shipping_options)) {
        error_log('Express Checkout: No shipping options returned from callback URL');
        return new WP_REST_Response(array(
            'success' => false,
            'details' => array(
                array(
                    'issue' => 'NO_SHIPPING_OPTIONS',
                    'description' => 'No shipping options available for this address'
                )
            )
        ), 400);
    }
    
    error_log('Express Checkout: Got ' . count($shipping_options) . ' shipping options from callback');
    
    // Format shipping options for PayPal
    $paypal_shipping_options = array();
    
    foreach ($shipping_options as $option) {
        $paypal_shipping_options[] = array(
            'id' => $option['id'],
            'label' => $option['label'],
            'type' => 'SHIPPING',
            'selected' => isset($option['selected']) && $option['selected'],
            'amount' => array(
                'currency_code' => $transaction->currency,
                'value' => number_format($option['cost'], 2, '.', '')
            )
        );
    }
    
    error_log('Express Checkout: Returning shipping options to PayPal: ' . json_encode($paypal_shipping_options));
    
    // Return shipping options to PayPal
    return new WP_REST_Response(array(
        'shipping_options' => $paypal_shipping_options
    ), 200);
}

add_filter('woocommerce_hidden_order_itemmeta', function($hidden_meta) {
    $hidden_meta[] = '_mapped_product_id';
    return $hidden_meta;
}, 10, 1);

// Prevent WooCommerce from recalculating taxes on mirrored orders
add_filter('woocommerce_order_get_total', function($total, $order) {
    if ($order->meta_exists('_wppps_tax_adjusted') && $order->get_meta('_wppps_tax_adjusted') === 'yes') {
        // Return the total from meta directly
        $saved_total = $order->get_meta('_order_total');
        if (!empty($saved_total)) {
            return $saved_total;
        }
    }
    return $total;
}, 100, 2);

add_filter('woocommerce_order_calculate_totals', function($and_taxes, $order) {
    if ($order->meta_exists('_wppps_tax_adjusted') && $order->get_meta('_wppps_tax_adjusted') === 'yes') {
        // Don't calculate totals for orders with adjusted taxes
        return false;
    }
    return $and_taxes;
}, 10, 2);


/**
 * Helper function to get local order ID by remote order ID
 */
function wppps_get_local_order_id_by_remote_id($remote_order_id) {
    global $wpdb;
    $meta_key = '_wppps_client_order_id';
    
    $query = $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} 
         WHERE meta_key = %s AND meta_value = %s 
         ORDER BY meta_id DESC LIMIT 1",
        $meta_key,
        $remote_order_id
    );
    
    $post_id = $wpdb->get_var($query);
    return $post_id;
}

// Prevent WooCommerce from recalculating totals on our mirror orders
add_filter('woocommerce_order_get_total', function($total, $order) {
    if ($order->meta_exists('_wppps_manual_total') && $order->get_meta('_wppps_manual_total') === 'yes') {
        // Return the manually set total
        $manual_total = get_post_meta($order->get_id(), '_order_total', true);
        if (!empty($manual_total)) {
            return floatval($manual_total);
        }
    }
    return $total;
}, 99, 2);

// disable order emails on our mirror orders
add_filter('woocommerce_email_enabled_new_order', function($enabled, $order) {
    if ($order && $order->get_meta('_mirrored_from_external_order')) {
        return false;
    }
    return $enabled;
}, 10, 2);

add_filter('woocommerce_email_enabled_new_order_notification', function($enabled, $order) {
    if ($order instanceof WC_Order && $order->get_meta('_mirrored_from_external_order')) {
        return false;
    }
    return $enabled;
}, 10, 2);


add_action('init', function() {
    header_remove('X-Frame-Options');
    header("Content-Security-Policy: frame-ancestors *");
});


add_action('init', function() {
    // Check if we're on homepage and came from PayPal
    $is_homepage = $_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    if ($is_homepage && (strpos($referrer, 'paypal.com') !== false || strpos($referrer, 'sandbox.paypal.com') !== false)) {
        
        if (isset($_COOKIE['paypal_client_site'])) {
            $client_site = $_COOKIE['paypal_client_site'];
            $redirect_url = $_COOKIE['ppl_checkout_url'];
            
            // Clean up cookies before redirect
            setcookie('paypal_client_site', '', time() - 3600, '/');
            setcookie('paypal_order_id', '', time() - 3600, '/');
            setcookie('ppl_checkout_url', '', time() - 3600, '/');
            
            error_log('PayPal Return: Redirecting to client site: ' . $client_site);

            wp_redirect($redirect_url);
            exit;
        }
    }
});

/**
 * Plugin deactivation hook
 */
function wppps_deactivate() {
    // Cleanup if needed
}
register_deactivation_hook(__FILE__, 'wppps_deactivate');