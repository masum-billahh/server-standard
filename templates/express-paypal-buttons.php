<?php
/**
 * Template for Express PayPal Buttons (No Patching Version)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    if (empty($_GET['rest_route'])) {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>Direct access not allowed.</body></html>';
        exit;
    }
}

// Get parameters
$amount = isset($amount) ? $amount : (isset($_GET['amount']) ? sanitize_text_field($_GET['amount']) : '0.00');
$currency = isset($currency) ? $currency : (isset($_GET['currency']) ? sanitize_text_field($_GET['currency']) : 'USD');
$api_key = isset($api_key) ? $api_key : (isset($_GET['api_key']) ? sanitize_text_field($_GET['api_key']) : '');
$client_id = isset($client_id) ? $client_id : '';
$environment = isset($environment) ? $environment : 'sandbox';
$site_url = isset($site_url) ? $site_url : (isset($_GET['site_url']) ? sanitize_text_field($_GET['site_url']) : '');
$needs_shipping = isset($needs_shipping) ? $needs_shipping : (isset($_GET['needs_shipping']) && $_GET['needs_shipping'] === 'yes');
$context = isset($_GET['context']) ? sanitize_text_field($_GET['context']) : 'default';

//security for postmessage
$original_url = $site_url;
$encoded_url = base64_encode($original_url);
$timestamp = time();

// Create a key from timestamp
$simple_key = '';
$seed = $timestamp . 'salt';
for ($i = 0; $i < 32; $i++) {
    $simple_key .= chr(((ord($seed[$i % strlen($seed)]) * 13 + $i) % 95) + 32); // printable chars
}

// XOR with key
$obfuscated_url = '';
for ($i = 0; $i < strlen($encoded_url); $i++) {
    $obfuscated_url .= chr(ord($encoded_url[$i]) ^ ord($simple_key[$i % strlen($simple_key)]));
}

// Convert to hex for storage
$final_url = bin2hex($obfuscated_url);


?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Express Checkout</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: transparent;
        }
        
        .container {
            width: 100%;
            position: relative;
        }
        
        #paypal-express-button-container {
            width: 100%;
            min-height: 45px;
        }
        
        #paypal-message, 
        #paypal-error,
        #paypal-success {
            margin: 8px 0;
            padding: 8px;
            border-radius: 4px;
            font-size: 14px;
            display: none;
        }
        
        #paypal-message {
            background-color: #f8f9fa;
            border: 1px solid #d6d8db;
            color: #1e2125;
        }
        
        #paypal-error {
            background-color: #f8d7da;
            border: 1px solid #f5c2c7;
            color: #842029;
        }
        
        #paypal-success {
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            color: #0f5132;
        }
    </style>
</head>
<body>
    <div class="container">
        <div id="paypal-express-button-container"></div>
        <div id="paypal-message"></div>
        <div id="paypal-error"></div>
        <div id="paypal-success"></div>
    </div>
    
    <input type="hidden" id="api-key" value="<?php echo esc_attr($api_key); ?>">
    <input type="hidden" id="amount" value="<?php echo esc_attr($amount); ?>">
    <input type="hidden" id="currency" value="<?php echo esc_attr($currency); ?>">
    <input type="hidden" id="needs-shipping" value="<?php echo $needs_shipping ? 'yes' : 'no'; ?>">
    <input type="hidden" id="context" value="<?php echo esc_attr($context); ?>">
    <input type="hidden" id="msg-target" value="<?php echo esc_attr($final_url); ?>">
    <input type="hidden" id="ts" value="<?php echo esc_attr($timestamp); ?>">
    
    <?php if (!empty($client_id)) : ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($client_id); ?>&currency=<?php echo esc_attr($currency); ?>&intent=capture&components=buttons"></script>
    <?php endif; ?>
    <script>
        // Simple variables for iframe communication
        var parentOrigin = '*';
        var context = document.getElementById('context').value || 'default';
        var iframeId = 'paypal-express-iframe-' + context;
        
         function decodeTarget() {
        var encodedTarget = document.getElementById('msg-target').value;
        var timestamp = document.getElementById('ts').value;
        
        var seed = timestamp + 'salt';
        var simpleKey = '';
        for (var i = 0; i < 32; i++) {
            var charCode = ((seed.charCodeAt(i % seed.length) * 13 + i) % 95) + 32;
            simpleKey += String.fromCharCode(charCode);
        }
        
        try {
            var obfuscatedData = '';
            for (var i = 0; i < encodedTarget.length; i += 2) {
                obfuscatedData += String.fromCharCode(parseInt(encodedTarget.substr(i, 2), 16));
            }
            
            var encodedUrl = '';
            for (var i = 0; i < obfuscatedData.length; i++) {
                encodedUrl += String.fromCharCode(
                    obfuscatedData.charCodeAt(i) ^ simpleKey.charCodeAt(i % simpleKey.length)
                );
            }
            
            var url = atob(encodedUrl);
            
            var urlObj = new URL(url);
            parentOrigin = urlObj.origin;
            
            return true;
        } catch (e) {
            //console.error('Error decoding target:', e);
            return false;
        }
    }
    
    //call it
    decodeTarget();
        
        
        // Utility functions
        function sendMessageToParent(message) {
            message.source = 'paypal-express-proxy';
            message.iframeId = iframeId;
            window.parent.postMessage(message, parentOrigin);
        }
        
        function resizeIframe() {
            var height = document.body.scrollHeight + 20;
            sendMessageToParent({
                action: 'resize_iframe',
                height: height
            });
        }
        
        function showError(message) {
            document.getElementById('paypal-error').textContent = message;
            document.getElementById('paypal-error').style.display = 'block';
            resizeIframe();
        }
        
function showSuccess(message) {
    document.getElementById('paypal-success').textContent = '';
    
    var successContainer = document.getElementById('paypal-success');
    if (successContainer) {
        // Create a full-page overlay for the background
        var overlay = document.createElement('div');
        overlay.id = 'paypal-express-overlay';
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100vw';
        overlay.style.height = '100vh';
        overlay.style.backgroundColor = 'rgba(128, 128, 128, 0.6)'; // Semi-transparent white
        overlay.style.zIndex = '9999';
        document.body.appendChild(overlay);
        
        // Style the success message box
        successContainer.style.position = 'fixed';
        successContainer.style.top = '50%';
        successContainer.style.left = '50%';
        successContainer.style.transform = 'translate(-50%, -50%)';
        successContainer.style.padding = '25px';
        successContainer.style.borderRadius = '5px';
        successContainer.style.zIndex = '10001'; // Higher than the overlay
        successContainer.style.backgroundColor = '#ffffff';
        successContainer.style.border = '1px solid #d6d8db';
        successContainer.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
        successContainer.style.minWidth = '320px';
        successContainer.style.textAlign = 'center';
        successContainer.style.display = 'block';
        
        // Create spinner
        var spinner = document.createElement('div');
        spinner.className = 'express-spinner';
        spinner.style.width = '40px';
        spinner.style.height = '40px';
        spinner.style.margin = '0 auto 15px auto';
        spinner.style.border = '4px solid #f3f3f3';
        spinner.style.borderTop = '4px solid #3498db';
        spinner.style.borderRadius = '50%';
        spinner.style.animation = 'express-spin 1s linear infinite';
        
        // Add spin animation if it doesn't exist
        if (!document.getElementById('express-spin-style')) {
            var style = document.createElement('style');
            style.id = 'express-spin-style';
            style.textContent = '@keyframes express-spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }
        
        // Create message element
        var messageEl = document.createElement('div');
        messageEl.style.fontSize = '18px';
        messageEl.style.fontWeight = 'bold';
        messageEl.style.color = '#333';
        messageEl.textContent = message;
        
        // Clear the container and add new elements
        successContainer.innerHTML = '';
        successContainer.appendChild(spinner);
        successContainer.appendChild(messageEl);
    }
}
        
        
        // Initialize PayPal buttons
        function initPayPalButtons() {
            if (typeof paypal === 'undefined') {
                showError('PayPal SDK could not be loaded.');
                return;
            }
            
            sendMessageToParent({ action: 'button_loaded' });
            
            paypal.Buttons({
                style: {
                    layout: 'horizontal',
                    color: 'gold',
                    shape: 'rect',
                    label: 'paypal',
                    tagline: false
                },
                
                createOrder: function() {
                    // Add validation check first
                    sendMessageToParent({ action: 'validate_before_paypal' });
                    
                    return new Promise(function(resolve, reject) {
                        var validationHandler = function(event) {
                            if (!event.data || event.data.source !== 'woocommerce-client') return;
                            
                            if (event.data.action === 'validation_failed') {
                                window.removeEventListener('message', validationHandler);
                                reject(new Error('Validation failed'));
                                return;
                            }
                            
                            if (event.data.action === 'validation_passed') {
                                window.removeEventListener('message', validationHandler);
                                
                                sendMessageToParent({ action: 'button_clicked' });
                                document.getElementById('paypal-express-button-container').style.display = 'none';
                                sendMessageToParent({ action: 'expand_iframe' });
                                
                                var messageHandler = function(event) {
                                    var data = event.data;
                                    if (!data || !data.action || data.source !== 'woocommerce-client') {
                                        return;
                                    }
                                    
                                    if (data.action === 'create_paypal_order') {
                                        window.removeEventListener('message', messageHandler);
                                        resolve(data.paypal_order_id);
                                    }
                                };
                                
                                window.addEventListener('message', messageHandler);
                                
                                setTimeout(function() {
                                    window.removeEventListener('message', messageHandler);
                                    reject(new Error('Timeout waiting for order data'));
                                }, 30000);
                            }
                        };
                        
                        window.addEventListener('message', validationHandler);
                        setTimeout(() => reject(new Error('Validation timeout')), 10000);
                    });
                },
                
                onApprove: function(data, actions) {
                    sendMessageToParent({
                        action: 'payment_approved',
                        payload: {
                            orderID: data.orderID,
                            paypalData: data
                        }
                    });
                    
                    /*
                    document.getElementById('paypal-express-button-container').style.display = 'block';
                    sendMessageToParent({
                            action: 'resize_iframe_normal'
                        });
                    
                    */    
                    
                    showSuccess('Payment successful! Finalizing your order...');
                    
                    return actions.order.capture().then(function(captureData) {
                        console.log('PayPal capture complete:', captureData);
                    });
                },
                
                onCancel: function(data) {
                    sendMessageToParent({
                        action: 'payment_cancelled',
                        payload: data
                    });
                    
                     document.getElementById('paypal-express-button-container').style.display = 'block';
                    sendMessageToParent({
                            action: 'resize_iframe_normal'
                        });
                },
                
                onError: function(err) {
                    sendMessageToParent({
                        action: 'payment_error',
                        error: {
                            message: err.message || 'An error occurred'
                        }
                    });
                    
                    document.getElementById('paypal-express-button-container').style.display = 'block';
                    sendMessageToParent({
                            action: 'resize_iframe_normal'
                        });
                }
            }).render('#paypal-express-button-container');
            
            setTimeout(resizeIframe, 500);
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPayPalButtons);
        } else {
            initPayPalButtons();
        }
    </script>
</body>
</html>