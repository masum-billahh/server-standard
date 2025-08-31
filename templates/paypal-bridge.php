<?php
/**
 * Template for PayPal Bridge
 * This page auto-redirects to PayPal Standard
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$order_id = isset($order_id) ? $order_id : '';
$order_key = isset($order_key) ? $order_key : '';
//$client_site = isset($client_site) ? $client_site : '';
$api_key = isset($api_key) ? $api_key : '';
$client_return_url = isset($client_return_url) ? $client_return_url : '';
$client_cancel_url = isset($client_cancel_url) ? $client_cancel_url : '';
$security_token = isset($security_token) ? $security_token : '';
$paypal_args = isset($paypal_args) ? $paypal_args : array();
$paypal_email = isset($paypal_email) ? $paypal_email : get_option('wppps_paypal_standard_email', '');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to PayPal...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            text-align: center;
            padding: 40px 20px;
            background: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        .spinner {
            margin: 20px auto;
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <img src="<?php echo WPPPS_PLUGIN_URL . 'assets/images/paypal.svg'; ?>" alt="PayPal logo" />
        <p>Please wait while we redirect you to PayPal to complete your payment...</p>
        
        <div class="spinner"></div>
        

<form id="paypal_form" name="paypal_form" action="<?php echo $environment === 'sandbox' ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr'; ?>" method="post">

    <input type="hidden" name="business" value="<?php echo esc_attr($paypal_email); ?>">
    <input type="hidden" name="invoice" value="<?php echo esc_attr($order_id); ?>">
    <input type="hidden" name="cmd" value="_cart">
    <input type="hidden" name="upload" value="1">
    <input type="hidden" name="address_override" value="1">
    <input type="hidden" name="no_shipping" value="1">
    
    
    
    <?php 
    // Add all PayPal args as hidden fields
    foreach ($paypal_args as $key => $value) : 
    ?>
        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
    <?php endforeach; ?>
    
    <input type="hidden" name="custom" value="<?php echo esc_attr(json_encode(array(
        'api_key' => $api_key,
        'order_id' => $order_id,
        'order_key' => $order_key,
        'token' => $security_token,
        'session_id' => isset($_GET['session_id']) ? $_GET['session_id'] : ''
    ))); ?>">
     <?php 
    // Only add shipping address directly if it's not already in paypal_args
    if (isset($shipping_address) && is_array($shipping_address) && 
        !isset($paypal_args['first_name'])) : 
    ?>
        <input type="hidden" name="first_name" value="<?php echo esc_attr($shipping_address['first_name']); ?>">
        <input type="hidden" name="last_name" value="<?php echo esc_attr($shipping_address['last_name']); ?>">
        <input type="hidden" name="address1" value="<?php echo esc_attr($shipping_address['address_1']); ?>">
        <input type="hidden" name="address2" value="<?php echo esc_attr($shipping_address['address_2']); ?>">
        <input type="hidden" name="city" value="<?php echo esc_attr($shipping_address['city']); ?>">
        <input type="hidden" name="state" value="<?php echo esc_attr($shipping_address['state']); ?>">
        <input type="hidden" name="zip" value="<?php echo esc_attr($shipping_address['postcode']); ?>">
        <input type="hidden" name="country" value="<?php echo esc_attr($shipping_address['country']); ?>">
    <?php endif; ?>
   
    
    <!-- Return URLs back to this server -->
    <input type="hidden" name="return" value="<?php echo esc_url(add_query_arg('session_id', isset($_GET['session_id']) ? $_GET['session_id'] : '', rest_url('wppps/v1/standard-return'))); ?>">
     <!-- <input type="hidden" name="cancel_return" value="<?php echo esc_url(rest_url('wppps/v1/standard-cancel/' . (isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : ''))); ?>">    
     -->
     <input type="hidden" name="notify_url" value="<?php echo esc_url(rest_url('wppps/v1/standard-ipn')); ?>">
    
    <noscript>
        <input type="submit" value="Click here if you are not redirected automatically" />
    </noscript>
</form>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('paypal_form').submit();
        });
</script>
</body>
</html>