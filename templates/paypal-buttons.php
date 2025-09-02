<?php
/**
 * Template for PayPal Buttons
 * 
 * This template is served via an iframe to Website A
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If accessed directly, return minimal HTML with error
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
//$callback_url = isset($callback_url) ? $callback_url : (isset($_GET['callback_url']) ? sanitize_text_field($_GET['callback_url']) : '');
$site_url = isset($site_url) ? $site_url : (isset($_GET['site_url']) ? sanitize_text_field($_GET['site_url']) : '');

$card = isset($card) ? $card : (isset($_GET['card']) ? sanitize_text_field($_GET['card']) : '');

// Format amount for display
$formatted_amount = number_format((float)$amount, 2, '.', ',');



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







// Set DOCTYPE to HTML5
?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Payment</title>
    
    <!-- Add styling -->
    <style>
        /* Basic reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
            line-height: 1.4;
            color: #333;
            padding: 10px;
            background-color: transparent;
        }
        
        /* Container */
        .container {
            width: 100%;
            position: relative;
        }
        
        /* PayPal buttons container */
        #paypal-buttons-container {
            width: 100%;
            min-height: 45px;
        }
        
        /* Messages */
        #paypal-message, 
        #paypal-error,
        #paypal-success {
            margin: 10px 0;
            padding: 10px;
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
        
        /* Processing overlay */
        #paypal-processing {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 10;
        }
        
        .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 10px;
        }
        
        .processing-text {
            font-size: 14px;
            color: #333;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Amount display */
        .amount-container {
            text-align: center;
            margin-bottom: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .amount-label {
            font-size: 14px;
            color: #555;
            margin-right: 5px;
        }
        
        .amount {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .currency {
            font-size: 14px;
            color: #666;
            margin-left: 3px;
        }
        /* Payment separator */
        .payment-separator {
            margin: 20px 0;
            text-align: center;
            position: relative;
        }
        
        .payment-separator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #ddd;
        }
        
        .payment-separator span {
            background: white;
            padding: 0 15px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
       
        
        <!-- PayPal buttons container -->
        <?php if ($card != 1): ?>
            <div id="paypal-buttons-container"></div>
        <?php endif; ?>
        <!-- Card Fields Container (only show when card=1) -->
    <?php if ($card == 1): ?>
    <div id="card-form" class="card-form-container" style="margin-top: 15px;">
    <div id="paypal-payment" class="payment-method">
        <!-- PayPal buttons will be inserted here -->
    </div>
    
    <div class="payment-separator">
        <span>Or pay with credit card</span>
    </div>
    
    <div id="card-payment" class="payment-method" style="margin-top: 20px;">
        <div id="card-name-field-container"></div>
        <div id="card-number-field-container"></div>
        <div id="card-expiry-field-container"></div>
        <div id="card-cvv-field-container"></div>
        
        <button id="card-field-submit-button" type="button" style="margin-top: 15px; padding: 10px 20px; background: #0070ba; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Pay with Credit Card
        </button>
    </div>
</div>
<?php endif; ?>


        <!-- Message containers -->
        <div id="paypal-message"></div>
        <div id="paypal-error"></div>
        <div id="paypal-success"></div>
        
        <!-- Processing overlay -->
        <div id="paypal-processing">
            <div class="spinner"></div>
            <div class="processing-text"><?php _e('Processing payment...', 'woo-paypal-proxy-server'); ?></div>
        </div>
    </div>
    
    <!-- Add hidden fields for JS -->
    <input type="hidden" id="api-key" value="<?php echo esc_attr($api_key); ?>">
    <input type="hidden" id="amount" value="<?php echo esc_attr($amount); ?>">
    <input type="hidden" id="currency" value="<?php echo esc_attr($currency); ?>">
    <input type="hidden" id="msg-target" value="<?php echo esc_attr($final_url); ?>">
    <input type="hidden" id="ts" value="<?php echo esc_attr($timestamp); ?>">
  <input type="hidden" id="crd" value="<?php echo esc_attr($card); ?>">

<?php
if (!empty($client_id)) {
    if ($card == 0) {
        // PayPal only
        ?>
        <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($client_id); ?>&currency=<?php echo esc_attr($currency); ?>&components=buttons&disable-funding=sepa,card"></script>
        <?php
    } else {
        // PayPal + Card Fields
        ?>
        <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($client_id); ?>&currency=<?php echo esc_attr($currency); ?>&intent=capture&components=buttons,card-fields&disable-funding=sepa"></script>
        <?php
    }
}
?>

    <script>
    
// Client-side decoding using built-in functions
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

decodeTarget();
        
        // Store order data
        var orderData = {
            orderId: null,
            amount: document.getElementById('amount').value || '0',
            currency: document.getElementById('currency').value || 'USD',
            apiKey: document.getElementById('api-key').value || '',
            //callbackUrl: document.getElementById('callback-url').value || '',
        };
        
        /**
         * Initialize PayPal buttons
         */
   var selectedFundingSource = null;
var cardFieldsInstance = null;
var billingAddress = null;
var shippingAddress = null;

/**
 * Initialize PayPal buttons and card fields
 */
function initPayPalButtons() {
    // Check if PayPal SDK is available
    if (typeof paypal === 'undefined') {
        console.error('PayPal SDK not loaded');
        showError('PayPal SDK could not be loaded. Please try again later.');
        return;
    }
    
    // Notify parent window that buttons are loaded
    sendMessageToParent({
        action: 'button_loaded',
        status: 'success'
    });
    
    // Initialize PayPal Buttons
    initPayPalButtonsOnly();
    
    // Initialize Card Fields if card support is enabled
    var cardEnabled = document.getElementById('crd').value;
    if (cardEnabled == '1') {
        initCardFields();
    }
}

/**
 * Initialize PayPal Buttons only
 */
function initPayPalButtonsOnly() {
    var containerSelector = document.getElementById('crd').value == '1' ? '#paypal-payment' : '#paypal-buttons-container';
    
    paypal.Buttons({
        style: {
            layout: 'vertical',
            color: 'gold',
            shape: 'rect',
            label: 'paypal',
            tagline: false,
        },
        
        onClick: function(data) {
            clearMessages();
            selectedFundingSource = data.fundingSource;
            if (data.fundingSource === 'paypal' || data.fundingSource === 'paylater') {
                if (document.getElementById('crd').value == '1') {
                    document.getElementById('card-form').style.display = 'none';
                } else {
                    document.getElementById('paypal-buttons-container').style.display = 'none';
                }

                sendMessageToParent({
                    action: 'expand_iframe'
                });
            }
            
        },
        
        createOrder: function(data, actions) {
            return createOrderFromParent();
        },
        
        onApprove: function(data, actions) {
            return handlePaymentApproval(data, actions);
        },
        
        onCancel: function(data) {
             if (document.getElementById('crd').value == '1') {
                    document.getElementById('card-form').style.display = 'block';
                } else {
                    document.getElementById('paypal-buttons-container').style.display = 'block';
                }
            handlePaymentCancel(data);
        },
        
        onError: function(err) {
            if (document.getElementById('crd').value == '1') {
                    document.getElementById('card-form').style.display = 'block';
                } else {
                    document.getElementById('paypal-buttons-container').style.display = 'block';
                }
            handlePaymentError(err);
        }
    }).render(containerSelector);
}

/**
 * Initialize Card Fields
 */
function initCardFields() {
    if (typeof paypal.CardFields === 'undefined') {
        console.error('PayPal Card Fields not available');
        return;
    }
    
    cardFieldsInstance = paypal.CardFields({
        createOrder: function() {
            return createOrderFromParent();
        },
        
        onApprove: function(data) {
            // Card payments are automatically captured
            sendMessageToParent({
                action: 'order_approved',
                payload: {
                    orderID: data.orderID,
                    transactionID: data.transactionID || data.orderID,
                    status: 'completed',
                    paymentType: 'card'
                }
            });
            showSuccess('Payment successful! Finalizing your order...');
        },
        
        onError: function(err) {
            console.error('Card payment error:', err);
            sendMessageToParent({
                action: 'payment_error',
                error: {
                    message: err.message || 'Card payment failed'
                }
            });
            showError('Card payment failed: ' + (err.message || 'Please try again'));
        },
        
        style: {
            'input': {
                'font-size': '16px',
                'font-family': '"Helvetica Neue", Helvetica, Arial, sans-serif',
                'color': '#32325d',
            },
            '.invalid': {
                'color': '#fa755a',
            }
        }
    });
    
    // Render individual card fields
    if (cardFieldsInstance.isEligible()) {
        const nameField = cardFieldsInstance.NameField();
        nameField.render("#card-name-field-container");
        
        const numberField = cardFieldsInstance.NumberField();
        numberField.render("#card-number-field-container");
        
        const cvvField = cardFieldsInstance.CVVField();
        cvvField.render("#card-cvv-field-container");
        
        const expiryField = cardFieldsInstance.ExpiryField();
        expiryField.render("#card-expiry-field-container");
        
       // Add submit button handler
document.getElementById('card-field-submit-button').addEventListener('click', function() {
    showProcessing();
    
    // FIRST: Request billing address from parent
    sendMessageToParent({
        action: 'get_billing_data'
    });
    
    // Wait for billing address response before submitting
    var messageHandler = function(event) {
        var data = event.data;
        if (!data || !data.action || data.source !== 'woocommerce-site') {
            return;
        }
        
        if (data.action === 'billing_data_response') {
            window.removeEventListener('message', messageHandler);
            clearTimeout(timeoutId);
            
            // Now we have the billing address - submit the card
            var billingData = data.billingAddress || {};
            
            cardFieldsInstance.submit({
                billingAddress: {
                    addressLine1: billingData.addressLine1 || '',
                    addressLine2: billingData.addressLine2 || '',
                    adminArea1: billingData.adminArea1 || '',
                    adminArea2: billingData.adminArea2 || '',
                    countryCode: billingData.countryCode || 'US',
                    postalCode: billingData.postalCode || ''
                }
            }).then(function() {
                // Success handled in onApprove
            }).catch(function(err) {
                hideProcessing();
                console.error('Card submit error:', err);
                showError('Payment failed: ' + (err.message || 'Please check your card details'));
            });
        }
    };
    
    window.addEventListener('message', messageHandler);
    
    var timeoutId = setTimeout(function() {
        window.removeEventListener('message', messageHandler);
        hideProcessing();
        showError('Timeout getting billing data. Please try again.');
    }, 10000);
});
}
}

/**
 * Create order from parent (unified for both PayPal and Card)
 */
function createOrderFromParent() {
    console.log('Creating order from parent...');
    
    sendMessageToParent({
        action: 'button_clicked',
        fundingSource: selectedFundingSource || 'card'
    });
    
    return new Promise(function(resolve, reject) {
        var messageHandler = function(event) {
            var data = event.data;
            if (!data || !data.action || data.source !== 'woocommerce-site') {
                return;
            }
            
            if (data.action === 'create_paypal_order') {
                window.removeEventListener('message', messageHandler);
                clearTimeout(timeoutId);
                
                // Store addresses for card fields
                if (data.billing_address) {
                    billingAddress = data.billing_address;
                }
                if (data.shipping_address) {
                    shippingAddress = data.shipping_address;
                }
                
                orderData.orderId = data.order_id;
                orderData.orderKey = data.order_key;
                
                createPayPalOrder(orderData)
                    .then(function(paypalOrderId) {
                        resolve(paypalOrderId);
                    })
                    .catch(function(error) {
                        showError('Error creating order: ' + error.message);
                        reject(error);
                    });
            } else if (data.action === 'order_creation_failed') {
                window.removeEventListener('message', messageHandler);
                clearTimeout(timeoutId);
                
                var error = new Error(data.message || 'Failed to create order');
                showError('Order creation failed: ' + error.message);
                reject(error);
            }
        };
        
        window.addEventListener('message', messageHandler);
        
        var timeoutId = setTimeout(function() {
            window.removeEventListener('message', messageHandler);
            var error = new Error('Timeout waiting for order data');
            showError('Timeout waiting for order data. Please try again.');
            reject(error);
        }, 30000);
    });
}

/**
 * Handle payment approval (unified)
 */
function handlePaymentApproval(data, actions) {
    showProcessing();
    
    return capturePayPalPayment(data.orderID)
        .then(function(captureData) {
            sendMessageToParent({
                action: 'order_approved',
                payload: {
                    orderID: data.orderID,
                    transactionID: captureData.transaction_id,
                    status: captureData.status,
                    paymentType: 'paypal'
                }
            });
        })
        .catch(function(error) {
            console.error('Error capturing payment:', error);
            sendMessageToParent({
                action: 'payment_error',
                error: {
                    message: error.message || 'Payment failed'
                }
            });
            hideProcessing();
            showError('Error capturing payment: ' + error.message);
            throw error;
        });
}

/**
 * Handle payment cancel
 */
function handlePaymentCancel(data) {
    console.log('Payment cancelled:', data);
    sendMessageToParent({
        action: 'payment_cancelled',
        payload: data
    });
    sendMessageToParent({
        action: 'resize_iframe_normal'
    });
    showMessage('Payment cancelled. You can try again when you\'re ready.');
}

/**
 * Handle payment error
 */
function handlePaymentError(err) {
    console.error('PayPal error:', err);
    sendMessageToParent({
        action: 'payment_error',
        error: {
            message: err.message || 'An error occurred'
        }
    });
    sendMessageToParent({
        action: 'resize_iframe_normal'
    });
    showError('Payment error. Please try again.');
}



/**
 * Get billing address from stored data
 */
function getBillingAddressData() {
    // This will be populated when parent sends order data
    return window.storedBillingAddress || {};
}
        
        /**
         * Create PayPal order via REST API
         */
        function createPayPalOrder(orderData) {
            return new Promise(function(resolve, reject) {
                // Calculate timestamp for security
                var timestamp = Math.floor(Date.now() / 1000);
                
                // Create request data
                var data = {
                    api_key: orderData.apiKey,
                    order_id: orderData.orderId,
                    amount: orderData.amount,
                    currency: orderData.currency,
                    timestamp: timestamp
                };
                
                // Make the request
                fetch('/wp-json/wppps/v1/create-paypal-order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(function(responseData) {
                    if (!responseData.success) {
                        throw new Error(responseData.message || 'Failed to create PayPal order');
                    }
                    
                    // Return the PayPal order ID
                    resolve(responseData.order_id);
                })
                .catch(function(error) {
                    reject(error);
                });
            });
        }
        
        /**
         * Capture PayPal payment via REST API
         */
        function capturePayPalPayment(paypalOrderId) {
            return new Promise(function(resolve, reject) {
                // Calculate timestamp for security
                var timestamp = Math.floor(Date.now() / 1000);
                
                // Create request data
                var data = {
                    api_key: orderData.apiKey,
                    paypal_order_id: paypalOrderId,
                    order_id: orderData.orderId,
                    timestamp: timestamp
                };
                
                // Make the request
                fetch('/wp-json/wppps/v1/capture-payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(function(responseData) {
                    if (!responseData.success) {
                        throw new Error(responseData.message || 'Failed to capture payment');
                    }
                    
                    // Return the capture data
                    resolve(responseData);
                })
                .catch(function(error) {
                    reject(error);
                });
            });
        }
        
        /**
         * Send message to parent window
         */
        function sendMessageToParent(message) {
            // Add source identifier
            
            message.source = 'paypal-proxy';
             console.log('Sending message to parent:', message);
            
            // Send message
            window.parent.postMessage(message, '*');
        }
        
        /**
         * Show error message
         */
        function showError(message) {
            var errorContainer = document.getElementById('paypal-error');
            if (errorContainer) {
                errorContainer.textContent = message;
                errorContainer.style.display = 'block';
            }
        }
        
        /**
         * Show success message
         */
        
        
        /**
         * Show general message
         */
        function showMessage(message) {
            var messageContainer = document.getElementById('paypal-message');
            if (messageContainer) {
                messageContainer.textContent = message;
                messageContainer.style.display = 'block';
            }
        }
        
        /**
         * Show processing indicator
         */
       // Update showProcessing function 
function showProcessing() {
    var processingContainer = document.getElementById('paypal-processing');
    if (processingContainer) {
        // Ensure the processing overlay covers the entire viewport
        processingContainer.style.position = 'fixed';
        processingContainer.style.top = '0';
        processingContainer.style.left = '0';
        processingContainer.style.width = '100vw';
        processingContainer.style.height = '100vh';
        processingContainer.style.display = 'flex';
        processingContainer.style.zIndex = '10000';
        processingContainer.style.backgroundColor = 'rgba(128, 128, 128, 0.6)';
        
        // Center the spinner and message
        var spinnerEl = processingContainer.querySelector('.spinner');
        var textEl = processingContainer.querySelector('.processing-text');
        
        if (textEl) {
            textEl.style.fontSize = '18px';
            textEl.style.fontWeight = 'bold';
        }
    }
}

// showSuccess function to display more prominently
function showSuccess(message) {
    hideProcessing(); // Hide the processing spinner first
    
    var successContainer = document.getElementById('paypal-success');
    if (successContainer) {
        successContainer.style.position = 'fixed';
        successContainer.style.top = '50%';
        successContainer.style.left = '50%';
        successContainer.style.transform = 'translate(-50%, -50%)';
        successContainer.style.padding = '20px';
        successContainer.style.borderRadius = '5px';
        successContainer.style.fontSize = '18px';
        successContainer.style.fontWeight = 'bold';
        successContainer.style.zIndex = '10001';
        successContainer.textContent = message;
        successContainer.style.display = 'block';
        successContainer.style.backgroundColor = 'rgba(128, 128, 128, 0.6)';
    }
}
        
        /**
         * Hide processing indicator
         */
        function hideProcessing() {
            var processingContainer = document.getElementById('paypal-processing');
            if (processingContainer) {
                processingContainer.style.display = 'none';
            }
        }
        
        /**
         * Initialize when DOM is ready
         */
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPayPalButtons);
        } else {
            initPayPalButtons();
        }
        
        
        /**
 * Communicate iframe size to parent
 */
function resizeIframe(height) {
    sendMessageToParent({
        action: 'resize_iframe',
        height: height
    });
}

/**
 * Check if PayPal card form elements are present in the DOM
 * @return {boolean} True if card form elements are found
 */
function hasCardFormElements() {
    // Look for standard PayPal card form elements
    const cardFormSelectors = [
        // Standard PayPal card form containers
        '#card-fields-container',
        '#card-expiry',
        '#card-cvc'
    ];
    
    // Check if any of these elements exist
    for (let i = 0; i < cardFormSelectors.length; i++) {
        if (document.querySelector(cardFormSelectors[i])) {
            //console.log('Card form element found:', cardFormSelectors[i]);
            return true;
        }
    }
    
    // Also check if any iframe contains card form content
    const iframes = document.querySelectorAll('iframe');
    for (let i = 0; i < iframes.length; i++) {
        try {
            // Try to access iframe content - this may fail due to same-origin policy
            const iframeDoc = iframes[i].contentDocument || iframes[i].contentWindow.document;
            if (iframeDoc && iframeDoc.querySelector('.card-fields-container')) {
                
                return true;
            }
        } catch (e) {
            // Ignore cross-origin errors
        }
    }
    
    // No card form elements found
    return false;
}

// More aggressive height adjustment
function adjustHeight() {
    // If card form is visible, use a larger height
    if (hasCardFormElements()) {
        // Add significantly more space for the card form
        resizeIframe(document.body.scrollHeight);
    } else {
        // Regular height for PayPal buttons
        resizeIframe(document.body.scrollHeight);
    }
}

/**
 * Clear all message containers
 */
function clearMessages() {
    var messageContainer = document.getElementById('paypal-message');
    var errorContainer = document.getElementById('paypal-error');
    var successContainer = document.getElementById('paypal-success');
    
    if (messageContainer) {
        messageContainer.style.display = 'none';
        messageContainer.textContent = '';
    }
    if (errorContainer) {
        errorContainer.style.display = 'none';
        errorContainer.textContent = '';
    }
    if (successContainer) {
        successContainer.style.display = 'none';
        successContainer.textContent = '';
    }
}

// Watch for DOM changes
const observer = new MutationObserver(function(mutations) {
    adjustHeight();
});

// Start observing with more complete coverage
observer.observe(document.body, { 
    childList: true,
    subtree: true,
    attributes: true,
    characterData: true
});

// Also set up periodic checking as backup
setInterval(adjustHeight, 1000);

// Initial height adjustment
setTimeout(adjustHeight, 500);

    </script>
</body>
</html>