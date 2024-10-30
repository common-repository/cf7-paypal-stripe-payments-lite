<?php defined('WPINC') || exit; ?>

<div class="wrap cf7-payments">
    <h2><?php echo __('CF7 Payments &lsaquo; Settings', 'cf7-payments'); ?></h2>

    <form method="post">
        <div>
            <p>
                <strong style="display:table;margin-bottom:.5rem">
                    <?php _e('Contact Form:', 'cf7-payments'); ?>
                </strong>

                <select name="form_id">
                    <option value=""><?php _e('&mdash; Select Form &mdash;', 'cf7-payments'); ?></option>
                    <?php foreach ( $forms as $form ) : ?>
                        <option value="<?php echo intval($form->ID); ?>" <?php selected( $form->ID == ($_POST['form_id'] ?? '') ); ?>><?php echo esc_attr($form->post_title); ?></option>
                    <?php endforeach; ?>
                </select>

                <small style="margin-top:10px;display:table"><?php _e('Payments will be collected for the selected contact form.', 'cf7-payments'); ?></small>
                
                <div style="background:#03a9f4;padding:13px 18px;color:#fff;display:inline-flex;align-items:center;border-radius:3px;-webkit-border-radius:3px">
                    <span class="dashicons dashicons-awards" style="font-size:25px;margin:-3px 0 0 -5px"></span>
                    <span style="margin-left:14px;"><?php _e('Need more forms? <a target="_new" href="https://www.wputil.com/cf7-payments" style="color: inherit;">Upgrade</a> to monetize more forms and unlock more features!', 'cf7-payments'); ?></span>
                </div>
            </p>

            <p>
                <label>
                    <strong style="display:table;margin-bottom:.5rem">
                        <?php _e('Payment Amount:', 'cf7-payments'); ?>
                    </strong>
                    
                    <input type="text" size="50" name="amount" value="<?php echo esc_attr( $_POST['amount'] ?? '' ); ?>" placeholder="e.g 19.99" />
                </label>
            </p>

            <p>
                <label>
                    <strong style="display:table;margin-bottom:.5rem">
                        <?php _e('Currency Code:', 'cf7-payments'); ?>
                    </strong>
                    
                    <input type="text" size="50" name="currency" value="<?php echo esc_attr( $_POST['currency'] ?? '' ); ?>" placeholder="<?php esc_attr_e('USD', 'cf7-payments'); ?>" />
                    <small style="display:table"><?php esc_attr_e('3-letter currency ISO code, defaults to USD', 'cf7-payments'); ?></small>
                </label>
            </p>

            <p>
                <label>
                    <strong style="display:table;margin-bottom:.5rem">
                        <?php _e('Stripe API Key:', 'cf7-payments'); ?>
                    </strong>
                    
                    <input type="text" size="50" name="stripe_key" value="<?php echo esc_attr( $_POST['stripe_key'] ?? '' ); ?>" placeholder="pk_xxxxx" />
                </label>
            </p>

            <p>
                <label>
                    <strong style="display:table;margin-bottom:.5rem">
                        <?php _e('Stripe API Secret:', 'cf7-payments'); ?>
                    </strong>
                    
                    <input type="text" size="50" name="stripe_secret" value="<?php echo esc_attr( $_POST['stripe_secret'] ?? '' ); ?>" placeholder="sk_xxxxx" />
                </label>
            </p>

            <p>
                <label>
                    <strong style="display:table;margin-bottom:.5rem">
                        <?php _e('PayPal client ID:', 'cf7-payments'); ?>
                    </strong>
                    
                    <input type="text" size="50" name="paypal_client" value="<?php echo esc_attr( $_POST['paypal_client'] ?? '' ); ?>" />
                </label>
            </p>

            <p>
                <label>
                    <strong style="display:table;margin-bottom:.5rem">
                        <?php _e('PayPal client secret:', 'cf7-payments'); ?>
                    </strong>
                    
                    <input type="text" size="50" name="paypal_secret" value="<?php echo esc_attr( $_POST['paypal_secret'] ?? '' ); ?>" />
                </label>
            </p>

            <p>
                <strong style="display:table;margin-bottom:.5rem">
                    <?php _e('PayPal environment:', 'cf7-payments'); ?>
                </strong>

                <label style="display:table;margin-bottom:.5rem">
                    <input type="checkbox" name="paypal_sandbox" <?php echo checked( !!($_POST['paypal_sandbox'] ?? '') ); ?>" />
                    <?php _e('Enable PayPal sandbox mode.', 'cf7-payments'); ?>
                </label>
            </p>

            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>" />
            <?php submit_button(); ?>
        </div>

        <div style="background:#607d8b;padding:13px 18px;color:#fff;display:inline-flex;align-items:center;border-radius:3px;-webkit-border-radius:3px">
            <span class="dashicons dashicons-superhero-alt" style="font-size:35px;margin:-14px 0 0 -5px"></span>
            <ul style="margin: 0 0 0 2.5rem">
                <li><h3 style="margin:0 0 7px;color:#f0f0f1"><?php _e('More features are awaiting!', 'cf7-payments'); ?></h3></li>
                <li style="list-style:inside;margin-bottom:0"><?php _e('Unlimited payment forms', 'cf7-payments'); ?></li>
                <li style="list-style:inside;margin-bottom:0"><?php _e('Priority support', 'cf7-payments'); ?></li>
                <li style="list-style:inside"><?php _e('Payments reports in your dashboard', 'cf7-payments'); ?></li>
                <li style="color:#f0f0f1"><?php _e('Upgrade to <a target="_new" href="https://www.wputil.com/cf7-payments" style="color: inherit;">Contact Form 7 PayPal & Stripe Payments</a> today!', 'cf7-payments'); ?></li>
            </ul>
        </div>
    </form>
</div>
