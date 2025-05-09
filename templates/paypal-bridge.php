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
$client_site = isset($client_site) ? $client_site : '';
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
        <h2>Redirecting to PayPal</h2>
        <p>Please wait while we redirect you to PayPal to complete your payment...</p>
        
        <div class="spinner"></div>
        
        <form id="paypal_form" name="paypal_form" action="https://www.sandbox.paypal.com/cgi-bin/webscr" method="post">
            <input type="hidden" name="business" value="<?php echo esc_attr($paypal_email); ?>">
            <input type="hidden" name="cmd" value="_cart">
            <input type="hidden" name="upload" value="1">
            
            <?php foreach ($paypal_args as $key => $value) : ?>
                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
            <?php endforeach; ?>
            
            <!-- Add custom field with client data -->
            <input type="hidden" name="custom" value="<?php echo esc_attr(json_encode(array(
                'client_site' => $client_site,
                'order_id' => $order_id,
                'order_key' => $order_key,
                'token' => $security_token
            ))); ?>">
            
            <!-- Return URLs back to this server -->
            <input type="hidden" name="return" value="<?php echo esc_url(site_url('/wc-api/wppps-standard-return')); ?>">
            <input type="hidden" name="cancel_return" value="<?php echo esc_url(site_url('/wc-api/wppps-standard-cancel')); ?>">
            <input type="hidden" name="notify_url" value="<?php echo esc_url(site_url('/wc-api/wppps-standard-ipn')); ?>">
            
            <noscript>
                <input type="submit" value="Click here if you are not redirected automatically" />
            </noscript>
        </form>
    </div>
    
    <script>
        // Auto-submit the form
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.getElementById('paypal_form').submit();
            }, 1000); // Submit after 1 second for better user experience
        });
    </script>
</body>
</html>