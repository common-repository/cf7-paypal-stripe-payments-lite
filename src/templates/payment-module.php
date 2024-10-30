<?php defined('WPINC') || exit; ?>

<div id="cf7-payments" class="loading" data-stripe_key="<?php echo esc_attr($settings['stripe_key'] ?? ''); ?>">
    <h5><?php printf(__('Payment Required: %s %s', 'cf7-payments'), $settings['amount'], $settings['currency'] ?: 'USD'); ?></h5>
    <h5 class="cf7-payment-methods-header"><?php _e('Payment Method:', 'cf7-payments'); ?></h5>

    <p class="cf7-payment-methods">
        <label>
            <input type="radio" name="cf7_paymens_method" value="stripe" <?php echo ($settings['stripe_key'] ?? '') ? '' : 'disabled="disabled"'; ?> />
            <?php _e('Credit Card', 'cf7-payments'); ?>
        </label>

        <label>
            <input type="radio" name="cf7_paymens_method" value="paypal" <?php echo ($settings['paypal_client'] ?? '') && ($settings['paypal_secret'] ?? '') ? '' : 'disabled="disabled"'; ?> />
            <?php _e('PayPal', 'cf7-payments'); ?>
        </label>
    </p>

    <div id="cf7-payments-paypal" style="display:none">
        <div id="paypal-submit-cont">
            <a href="javascript:">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M22 9.761c0 .536-.065 1.084-.169 1.627-.847 4.419-3.746 5.946-7.449 5.946h-.572c-.453 0-.838.334-.908.789l-.803 5.09c-.071.453-.456.787-.908.787h-2.736c-.39 0-.688-.348-.628-.732l1.386-8.88.062-.056h2.155c5.235 0 8.509-2.618 9.473-7.568.812.814 1.097 1.876 1.097 2.997zm-14.216 4.252c.116-.826.459-1.177 1.385-1.179l2.26-.002c4.574 0 7.198-2.09 8.023-6.39.8-4.134-2.102-6.442-6.031-6.442h-7.344c-.517 0-.958.382-1.038.901-2.304 14.835-2.97 18.607-3.038 19.758-.021.362.269.672.635.672h3.989l1.159-7.318z"/></svg>
                <span><?php _e('Pay with PayPal', 'cf7-payments'); ?></span>
            </a>
            <img src="<?php echo $plugin_url, 'src/assets/ajax-loader.gif'; ?>" alt="loading" style="display:none" />
        </div>
    </div>

    <div id="cf7-payments-stripe" style="display:none">
        <label for="card-element"><?php _e('Credit or debit card:', 'cf7-payments'); ?></label>
        <div id="card-element"></div>
        <div id="card-errors" role="alert"></div>
        <div id="stripe-submit-cont">
            <a href="javascript:" class="stripe-submit">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M0 8v-2c0-1.104.896-2 2-2h18c1.104 0 2 .896 2 2v2h-22zm24 7.5c0 2.485-2.015 4.5-4.5 4.5s-4.5-2.015-4.5-4.5 2.015-4.5 4.5-4.5 4.5 2.015 4.5 4.5zm-2.156-.882l-.696-.696-2.116 2.169-.991-.94-.696.697 1.688 1.637 2.811-2.867zm-8.844.882c0 1.747.696 3.331 1.82 4.5h-12.82c-1.104 0-2-.896-2-2v-7h14.82c-1.124 1.169-1.82 2.753-1.82 4.5zm-5 .5h-5v1h5v-1zm3-2h-8v1h8v-1z"/></svg>
                <span><?php _e('Submit Payment', 'cf7-payments'); ?></span>
            </a>
            <img src="<?php echo $plugin_url, 'src/assets/ajax-loader.gif'; ?>" alt="loading" style="display:none" />
        </div>
    </div>

    <div class="cf7-payment-loading">
        <img src="<?php echo $plugin_url, 'src/assets/ajax-loader.gif'; ?>" alt="loading" />
        <small><?php _e('Your payment is being processed...', 'cf7-payments'); ?></small>
    </div>

    <div class="cf7-payment-success">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M23.334 11.96c-.713-.726-.872-1.829-.393-2.727.342-.64.366-1.401.064-2.062-.301-.66-.893-1.142-1.601-1.302-.991-.225-1.722-1.067-1.803-2.081-.059-.723-.451-1.378-1.062-1.77-.609-.393-1.367-.478-2.05-.229-.956.347-2.026.032-2.642-.776-.44-.576-1.124-.915-1.85-.915-.725 0-1.409.339-1.849.915-.613.809-1.683 1.124-2.639.777-.682-.248-1.44-.163-2.05.229-.61.392-1.003 1.047-1.061 1.77-.082 1.014-.812 1.857-1.803 2.081-.708.16-1.3.642-1.601 1.302s-.277 1.422.065 2.061c.479.897.32 2.001-.392 2.727-.509.517-.747 1.242-.644 1.96s.536 1.347 1.17 1.7c.888.495 1.352 1.51 1.144 2.505-.147.71.044 1.448.519 1.996.476.549 1.18.844 1.902.798 1.016-.063 1.953.54 2.317 1.489.259.678.82 1.195 1.517 1.399.695.204 1.447.072 2.031-.357.819-.603 1.936-.603 2.754 0 .584.43 1.336.562 2.031.357.697-.204 1.258-.722 1.518-1.399.363-.949 1.301-1.553 2.316-1.489.724.046 1.427-.249 1.902-.798.475-.548.667-1.286.519-1.996-.207-.995.256-2.01 1.145-2.505.633-.354 1.065-.982 1.169-1.7s-.135-1.443-.643-1.96zm-12.584 5.43l-4.5-4.364 1.857-1.857 2.643 2.506 5.643-5.784 1.857 1.857-7.5 7.642z"/></svg>
        <small><?php _e('Your payment was received!', 'cf7-payments'); ?></small>
    </div>

    <div class="cf7-payment-cookies-notice" style="display:none;color:#f44336">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M12.078 0c6.587.042 11.922 5.403 11.922 12 0 6.623-5.377 12-12 12s-12-5.377-12-12c3.887 1.087 7.388-2.393 6-6 4.003.707 6.786-2.722 6.078-6zm1.422 17c.828 0 1.5.672 1.5 1.5s-.672 1.5-1.5 1.5-1.5-.672-1.5-1.5.672-1.5 1.5-1.5zm-6.837-3c1.104 0 2 .896 2 2s-.896 2-2 2-2-.896-2-2 .896-2 2-2zm11.337-3c1.104 0 2 .896 2 2s-.896 2-2 2-2-.896-2-2 .896-2 2-2zm-6-1c.552 0 1 .448 1 1s-.448 1-1 1-1-.448-1-1 .448-1 1-1zm-9-3c.552 0 1 .448 1 1s-.448 1-1 1-1-.448-1-1 .448-1 1-1zm13.5-2c.828 0 1.5.672 1.5 1.5s-.672 1.5-1.5 1.5-1.5-.672-1.5-1.5.672-1.5 1.5-1.5zm-15-2c.828 0 1.5.672 1.5 1.5s-.672 1.5-1.5 1.5-1.5-.672-1.5-1.5.672-1.5 1.5-1.5zm6-2c.828 0 1.5.672 1.5 1.5s-.672 1.5-1.5 1.5-1.5-.672-1.5-1.5.672-1.5 1.5-1.5zm-3.5-1c.552 0 1 .448 1 1s-.448 1-1 1-1-.448-1-1 .448-1 1-1z"></path></svg>
        <small><?php _e('You must <a href="https://wordpress.org/support/article/cookies/#enable-cookies-in-your-browser">enable cookies</a> in your browser to submit a payment.', 'cf7-payments'); ?></small>
    </div>
</div>