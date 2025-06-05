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
    </style>
</head>
<body>
    <div class="container">
       
        
        <!-- PayPal buttons container -->
        <div id="paypal-buttons-container"></div>
        
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
        // Run alternate PayPal script
        ?>
        <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($client_id); ?>&currency=<?php echo esc_attr($currency); ?>&components=buttons&disable-funding=sepa,card"></script>
        <?php
    } else {
        // Default PayPal script
        ?>
        <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($client_id); ?>&currency=<?php echo esc_attr($currency); ?>&intent=capture&components=buttons&disable-funding=sepa"></script>
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
    
    // Render PayPal buttons
    paypal.Buttons({
        // Style the buttons
        style: {
            layout: 'vertical',  // horizontal | vertical
            color: 'gold',         // gold | blue | silver | black
            shape: 'rect',         // pill | rect
            label: 'paypal',       // pay | checkout | paypal | buynow
            tagline: false,
            //fundingSource: paypal.FUNDING.PAYPAL
        },
        //fundingSource: paypal.FUNDING.PAYPAL,
        
         onClick: function(data) {
             clearMessages();
                if (data.fundingSource === 'paypal' || data.fundingSource === 'paylater') {
                    document.getElementById('paypal-buttons-container').style.display = 'none';
                    console.log("Clicked funding source:", data.fundingSource);
                    sendMessageToParent({
                        action: 'expand_iframe'
                    });
                }
            },
        
        // Create order
        createOrder: function(data, actions) {
            console.log('PayPal button clicked, notifying parent window');
            
            // Notify parent window that button was clicked
            sendMessageToParent({
                action: 'button_clicked'
            });
            
            // Wait for order data from parent
            return new Promise(function(resolve, reject) {
                console.log('Waiting for order data from parent window...');
                
                // Create message handler
                var messageHandler = function(event) {
                    console.log('Received message from parent:', event.data);
                    
                    // Validate origin if possible (use * for testing)
                    // if (parentOrigin !== '*' && event.origin !== parentOrigin) {
                    //     console.log('Origin mismatch, ignoring message');
                    //     return;
                    // }
                    
                    // Check if message is for us
                    var data = event.data;
                    if (!data || !data.action || data.source !== 'woocommerce-site') {
                        console.log('Not a valid message for us, ignoring');
                        return;
                    }
                    
                    // Handle create_paypal_order action
                    if (data.action === 'create_paypal_order') {
                        console.log('Received order data from parent');
                        // Clear timeout
                        clearTimeout(timeoutId);
                        
                        // Remove event listener
                        window.removeEventListener('message', messageHandler);
                        
                        // Store order data
                        orderData.orderId = data.order_id;
                        orderData.orderKey = data.order_key;
                        
                        console.log('Creating PayPal order with data:', orderData);
                        
                        // Create PayPal order
                        createPayPalOrder(orderData)
                            .then(function(paypalOrderId) {
                                console.log('PayPal order created:', paypalOrderId);
                                resolve(paypalOrderId);
                            })
                            .catch(function(error) {
                                console.error('Error creating PayPal order:', error);
                                showError('Error creating PayPal order: ' + error.message);
                                reject(error);
                            });
                    } else if (data.action === 'order_creation_failed') {
                        console.log('Parent reported order creation failed');
                        // Clear timeout
                        clearTimeout(timeoutId);
                        
                        // Remove event listener
                        window.removeEventListener('message', messageHandler);
                        
                        // Show error
                        var error = new Error(data.message || 'Failed to create order');
                        showError('Order creation failed: ' + error.message);
                        reject(error);
                    }
                };
                
                // Add message listener
                window.addEventListener('message', messageHandler);
                
                // Set timeout for order creation (30 seconds)
                var timeoutId = setTimeout(function() {
                    console.log('Timeout waiting for order data');
                    window.removeEventListener('message', messageHandler);
                    var error = new Error('Timeout waiting for order data');
                    showError('Timeout waiting for order data. Please try again.');
                    reject(error);
                }, 30000);
            });
        },
        
        // On approval
        onApprove: function(data, actions) {
            // Show processing message
            showProcessing();
            
            // Capture the payment
            return capturePayPalPayment(data.orderID)
                .then(function(captureData) {
                    // Notify parent window
                    sendMessageToParent({
                        action: 'order_approved',
                        payload: {
                            orderID: data.orderID,
                            transactionID: captureData.transaction_id,
                            status: captureData.status
                        }
                    });
                    
                    /*
                    document.getElementById('paypal-buttons-container').style.display = 'block';
                    sendMessageToParent({
                            action: 'resize_iframe_normal'
                        });
                    */
                    // Show success message
                    
                    //showSuccess('Payment successful! Finalizing your order...');
                })
                .catch(function(error) {
                    // Handle error
                    console.error('Error capturing payment:', error);
                    
                    // Notify parent window of error
                    sendMessageToParent({
                        action: 'payment_error',
                        error: {
                            message: error.message || 'Payment failed'
                        }
                    });
                    
                    // Show error message
                    hideProcessing();
                    showError('Error capturing payment: ' + error.message);
                    
                    throw error;
                });
        },
        
        // On cancel
        onCancel: function(data) {
            console.log('Payment cancelled:', data);
            
            // Notify parent window
            sendMessageToParent({
                action: 'payment_cancelled',
                payload: data
            });
            document.getElementById('paypal-buttons-container').style.display = 'block';
            sendMessageToParent({
                            action: 'resize_iframe_normal'
                        });
            
            showMessage('Payment cancelled. You can try again when you\'re ready.');
        },
        
        // On error
        onError: function(err) {
            console.error('PayPal error:', err);
            
            // Notify parent window
            sendMessageToParent({
                action: 'payment_error',
                error: {
                    message: err.message || 'An error occurred'
                }
            });
            document.getElementById('paypal-buttons-container').style.display = 'block';
            
            // Reset iframe to normal size
            sendMessageToParent({
                action: 'resize_iframe_normal'
            });
            showError('PayPal window was closed. Please try again.');
            //showError('PayPal error: ' + (err.message || 'An error occurred'));
        }
    }).render('#paypal-buttons-container');
    
    

    
    // Listen for messages from parent window
    window.addEventListener('message', function(event) {
        // Validate origin if possible
        if (parentOrigin !== '*' && event.origin !== parentOrigin) {
            return;
        }
        
        // Check if message is for us
        var data = event.data;
        if (!data || !data.action || data.source !== 'woocommerce-site') {
            return;
        }
        
        // Handle trigger_paypal_button action
        if (data.action === 'trigger_paypal_button') {
            // Find PayPal button and click it
            var paypalButton = document.querySelector('.paypal-button');
            if (paypalButton) {
                paypalButton.click();
            }
        }
    });
    
    // Watch for DOM changes and resize when payment form changes
    const observer = new MutationObserver(function(mutations) {
        for (let mutation of mutations) {
            if (mutation.type === 'childList' && mutation.addedNodes.length) {
                // Look for card form elements
                const cardForm = document.querySelector('.paypal-card-form') || 
                                document.querySelector('.paypal-card-context') ||
                                document.querySelector('.card-fields-container');
                
                if (cardForm) {
                    //console.log('Credit card form detected, resizing iframe');
                    // Add extra height to accommodate the form
                    resizeIframe(document.body.scrollHeight);
                }
            }
        }
    });
    
    // Start observing
    observer.observe(document.body, { 
        childList: true, 
        subtree: true 
    });
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