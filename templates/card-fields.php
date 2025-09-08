<?php
/**
 * Template for PayPal Card Fields Only
 * 
 * This template is served via an iframe to Website A
 * Shows ONLY PayPal CardFields, no PayPal buttons
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

// Obfuscate site URL (reuse existing logic)
$original_url = $site_url;
$encoded_url = base64_encode($original_url);
$timestamp = time();

$simple_key = '';
$seed = $timestamp . 'salt';
for ($i = 0; $i < 32; $i++) {
    $simple_key .= chr(((ord($seed[$i % strlen($seed)]) * 13 + $i) % 95) + 32);
}

$obfuscated_url = '';
for ($i = 0; $i < strlen($encoded_url); $i++) {
    $obfuscated_url .= chr(ord($encoded_url[$i]) ^ ord($simple_key[$i % strlen($simple_key)]));
}

$final_url = bin2hex($obfuscated_url);
?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Card Payment</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
            line-height: 1.4;
            color: #333;
            padding: 15px;
            background-color: transparent;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .card-fields-container {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .field-row {
            margin-bottom: 15px;
        }
        
        .field-row.field-row-split {
            display: flex;
            gap: 15px;
        }
        
        .field-half {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .field-container {
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            transition: border-color 0.2s;
        }
        
        .field-container:focus-within {
            border-color: #0070ba;
            box-shadow: 0 0 0 1px #0070ba;
        }
        
        .field-container.error {
            border-color: #e74c3c;
        }
        
        #card-error {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8d7da;
            border: 1px solid #f5c2c7;
            color: #842029;
            border-radius: 4px;
            display: none;
        }
        
        #card-success {
            margin-top: 10px;
            padding: 10px;
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            color: #0f5132;
            border-radius: 4px;
            display: none;
        }
        
        .processing-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.9);
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0070ba;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .processing-text {
            margin-top: 15px;
            font-size: 16px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card-fields-container">
            <div class="field-row">
                <label for="card-name-field">Cardholder Name</label>
                <div id="card-name-field-container" class="field-container"></div>
            </div>
            
            <div class="field-row">
                <label for="card-number-field">Card Number</label>
                <div id="card-number-field-container" class="field-container"></div>
            </div>
            
            <div class="field-row field-row-split">
                <div class="field-half">
                    <label for="card-expiry-field">Expiry Date</label>
                    <div id="card-expiry-field-container" class="field-container"></div>
                </div>
                <div class="field-half">
                    <label for="card-cvv-field">CVV</label>
                    <div id="card-cvv-field-container" class="field-container"></div>
                </div>
            </div>
            
            <div id="card-error"></div>
            <div id="card-success"></div>
        </div>
    </div>
    
    <div class="processing-overlay" id="processing-overlay">
        <div class="spinner"></div>
        <div class="processing-text">Processing payment...</div>
    </div>
    
    <!-- Hidden fields for JS -->
    <input type="hidden" id="api-key" value="<?php echo esc_attr($api_key); ?>">
    <input type="hidden" id="amount" value="<?php echo esc_attr($amount); ?>">
    <input type="hidden" id="currency" value="<?php echo esc_attr($currency); ?>">
    <input type="hidden" id="msg-target" value="<?php echo esc_attr($final_url); ?>">
    <input type="hidden" id="ts" value="<?php echo esc_attr($timestamp); ?>">

<?php if (!empty($client_id)): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($client_id); ?>&currency=<?php echo esc_attr($currency); ?>&intent=capture&components=card-fields"></script>
<?php endif; ?>

    <script>
   
        // Decode target URL (reuse existing logic)
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
                window.parentOrigin = urlObj.origin;
                
                return true;
            } catch (e) {
                return false;
            }
        }
        
        decodeTarget();
        
        var isProcessing = false;
        // Card fields instance
        var cardFieldsInstance = null;
        var orderData = {
            orderId: null,
            amount: document.getElementById('amount').value || '0',
            currency: document.getElementById('currency').value || 'USD',
            apiKey: document.getElementById('api-key').value || ''
        };
       function initCardFields() {
    if (typeof paypal === 'undefined' || typeof paypal.CardFields === 'undefined') {
        console.error('PayPal CardFields not available');
        showError('Payment system is not available. Please try again later.');
        return;
    }
    
    cardFieldsInstance = paypal.CardFields({
        createOrder: createOrderFromParent,
        
        onApprove: function(data) {
            console.log('Card payment approved:', data);
            showProcessing();
            
            sendMessageToParent({
                action: 'order_approved',
                payload: {
                    orderID: data.orderID,
                    transactionID: data.transactionID || data.orderID,
                    status: 'completed',
                    paymentType: 'card'
                }
            });
            isProcessing = false;
        },
        
        onError: function(err) {
            console.error('Card payment error:', err);
            hideProcessing();
            
            sendMessageToParent({
                action: 'payment_error',
                error: {
                    message: err.message || 'Card payment failed'
                }
            });
            
            showError('Card payment failed: ' + (err.message || 'Please check your card details and try again'));
            isProcessing = false;
        },
        
        style: {
            'input': {
                'font-size': '16px',
                'font-family': '"Helvetica Neue", Helvetica, Arial, sans-serif',
                'color': '#32325d',
                'height': '15px',
            },
            '.invalid': {
                'color': '#fa755a',
            }
        }
    });
    
    // Render card fields if eligible
    if (cardFieldsInstance.isEligible()) {
        cardFieldsInstance.NameField().render("#card-name-field-container");
        cardFieldsInstance.NumberField().render("#card-number-field-container");
        cardFieldsInstance.ExpiryField().render("#card-expiry-field-container");
        cardFieldsInstance.CVVField().render("#card-cvv-field-container");
        
        // Notify parent that card fields are ready
        sendMessageToParent({
            action: 'card_fields_loaded'
        });
        
        // Resize iframe to fit content
        setTimeout(function() {
            resizeIframe(document.body.scrollHeight);
        }, 500);
        
    } else {
        showError('Card payments are not available at this time.');
    }
}
      /**
 * Create order from parent - FIXED VERSION
 */
function createOrderFromParent() {
    console.log('Creating PayPal order from cart data...');
    
    return new Promise(function(resolve, reject) {
        // Get cart data from parent
        var cartData = window.cartData;
        
        if (!cartData || !cartData.billing_address) {
            reject(new Error('Cart data not available'));
            return;
        }
        
        // Send request to server to create PayPal order
        fetch('/wp-json/wppps/v1/create-paypal-order', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                api_key: orderData.apiKey,
                amount: orderData.amount,
                currency: orderData.currency,
                order_id: 'card_' + Date.now(),
                cart_data: cartData
            })
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                resolve(data.order_id);
            } else {
                reject(new Error(data.message || 'Failed to create PayPal order'));
            }
        })
        .catch(function(error) {
            reject(error);
        });
    });
}
        
        /**
 * Process card payment (called from parent) 
 */
function processCardPayment(cartData) {
    if (!cardFieldsInstance || !cardFieldsInstance.isEligible()) {
        showError('Card fields are not ready. Please try again.');
        return;
    }
    isProcessing = true;
    showProcessing();
    
    // Store cart data for order creation
    window.cartData = cartData;
    
    var billingData = cartData.billing_address;
    
    // Submit card with billing address directly - DON'T call submit() multiple times
    cardFieldsInstance.submit({
        billingAddress: {
            addressLine1: billingData.address_1 || '',
            addressLine2: billingData.address_2 || '',
            adminArea1: billingData.state || '',
            adminArea2: billingData.city || '',
            countryCode: billingData.country || 'US',
            postalCode: billingData.postcode || ''
        }
    }).then(function(orderID) {
        console.log('Card payment approved with order ID:', orderID);
        
        sendMessageToParent({
            action: 'order_approved',
            payload: {
                orderID: orderID,
                transactionID: orderID,
                status: 'completed',
                paymentType: 'card'
            }
        });
        isProcessing = false;
    }).catch(function(err) {
        console.error('Card submit error:', err);
        hideProcessing();
        
        sendMessageToParent({
            action: 'payment_error',
            error: {
                message: err.message || 'Card payment failed'
            }
        });
        
        showError('Card payment failed: ' + (err.message || 'Please check your card details and try again'));
    });
}
        
        /**
         * Send message to parent window
         */
        function sendMessageToParent(message) {
            message.source = 'paypal-card-proxy';
            window.parent.postMessage(message, '*');
        }
        
        /**
         * Resize iframe
         */
        function resizeIframe(height) {
            sendMessageToParent({
                action: 'resize_iframe',
                height: height
            });
        }
        
        /**
         * Show error message
         */
        function showError(message) {
            var errorContainer = document.getElementById('card-error');
            if (errorContainer) {
                errorContainer.textContent = message;
                errorContainer.style.display = 'block';
            }
        }
        
        /**
         * Show success message
         */
        function showSuccess(message) {
            hideProcessing();
            var successContainer = document.getElementById('card-success');
            if (successContainer) {
                successContainer.textContent = message;
                successContainer.style.display = 'block';
            }
        }
        
        /**
         * Show processing overlay
         */
        function showProcessing() {
            var overlay = document.getElementById('processing-overlay');
            if (overlay) {
                overlay.style.display = 'flex';
            }
        }
        
        /**
         * Hide processing overlay
         */
        function hideProcessing() {
            var overlay = document.getElementById('processing-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        
        // Listen for messages from parent
        window.addEventListener('message', function(event) {
            if (window.parentOrigin !== '*' && event.origin !== window.parentOrigin) {
                return;
            }
            
            var data = event.data;
            if (!data || !data.action || data.source !== 'woocommerce-card-site') {
                return;
            }
            
            switch (data.action) {
                case 'initialize_card_fields':
                    initCardFields();
                    break;
                    
                case 'process_cart_payment': 
                     processCardPayment(data.cart_data);  
                    break;
            }
        });
        
        // Initialize when DOM is ready AND PayPal SDK is loaded
function initializeWhenReady() {
    if (typeof paypal !== 'undefined' && typeof paypal.CardFields !== 'undefined') {
        console.log('PayPal SDK loaded, initializing card fields...');
        initCardFields();
    } else {
        console.log('PayPal SDK not ready, retrying...');
        setTimeout(initializeWhenReady, 100);
    }
}
        
        if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initializeWhenReady();
        
        // Also notify parent that iframe is loaded
        sendMessageToParent({
            action: 'card_fields_ready'
        });
    });
} else {
    initializeWhenReady();
    
    // Notify parent immediately if DOM already loaded
    sendMessageToParent({
        action: 'card_fields_ready'
    });
}
    </script>
</body>
</html>