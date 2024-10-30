<?php

namespace Cf7Payments\Admin\Screen;

class Settings extends Screen
{
    public function render()
    {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            $_POST = (array) get_option('cf7-payments_lite_settings');
        }

        return $this->renderTemplate('settings.php', [
            'nonce' => wp_create_nonce('cf7-payments'),
            'forms' => get_posts(['post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1]),
        ]);
    }

    public function update()
    {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cf7-payments' ) )
            return $this->error( __('Invalid request, authorization check failed. Please try again.', 'cf7-payments') );

        $form_id = intval($_POST['form_id']);
        $form_id = $form_id && get_post($form_id) ? $form_id : null;
        $amount = (float) ($_POST['amount'] ?? '');
        $currency = sanitize_text_field( $_POST['currency'] ?? '' );
        $stripe_key = sanitize_text_field( $_POST['stripe_key'] ?? '' );
        $stripe_secret = sanitize_text_field( $_POST['stripe_secret'] ?? '' );
        $paypal_client = sanitize_text_field( $_POST['paypal_client'] ?? '' );
        $paypal_secret = sanitize_text_field( $_POST['paypal_secret'] ?? '' );
        $paypal_sandbox = isset( $_POST['paypal_sandbox'] );

        update_option('cf7-payments_lite_settings', compact('amount', 'currency', 'stripe_key', 'stripe_secret', 'paypal_client', 'paypal_secret', 'paypal_sandbox', 'form_id'));

        return $this->success( __('Settings updated successfully.', 'cf7-payments') );
    }

    public function scripts()
    {
        wp_enqueue_style('cf7-payments-forms', plugin_dir_url( $this->appContext->getPluginFile() ) . '/src/assets/admin.css', [], $this->appContext::SCRIPTS_VERSION);
    }
}