<?php

namespace Cf7Payments;

use Cf7Payments\Admin\Admin;
use WPCF7_ContactForm;
use WPCF7_Submission;
use WP_REST_Request;
use WP_REST_Response;
use Stripe\{Stripe,PaymentIntent};
use Exception;

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

class App
{
    private $plugin_file;
    private $adminContext;

    const PAYMENTS_TABLE = 'cf7_payments_items';
    const PAYMENTS_PER_PAGE = 20;
    const SCRIPTS_VERSION = 1621554724;

    public function __construct( string $plugin_file )
    {
        $this->plugin_file = $plugin_file;
        $this->adminContext = new Admin( $this );
    }

    public function getPluginFile() : string
    {
        return $this->plugin_file;
    }

    public function setup()
    {
        add_action('plugins_loaded', [ $this, 'loaded' ]);

        // activation
        register_activation_hook( $this->getPluginFile(), [ $this, 'activation' ]);
    }

    public function loaded()
    {
        // i18n
        load_plugin_textdomain(
            'cf7-payments', false,
            basename(dirname( $this->getPluginFile() )) . '/languages'
        );

        // add module
        add_action('wpcf7_init', [ $this, 'contactForm7Init' ], 0, 0);

        // enqueue scripts
        add_action('wpcf7_enqueue_scripts', [ $this, 'scripts' ]);

        // rest api
        add_action('rest_api_init', [ $this, 'restRoutes' ]);

        // validate forms
        add_action('wpcf7_validate', [ $this, 'validateForm' ]);

        // mark payment used
        add_action('wpcf7_mail_sent', [ $this, 'claimPayment' ]);
    }

    public function activation()
    {
        // payments table
        Payments::setupDb();

        // pagination settings
        add_option('cf7-payments_payments_per_page', 20);
        add_option('cf7-payments_forms_per_page', 20);
    }

    public function contactForm7Init()
    {
        wpcf7_add_form_tag('submit', function($tag)
        {
            $form_id = WPCF7_ContactForm::get_current() ? WPCF7_ContactForm::get_current()->id() : null;
            $settings = $this->getFormSettings($form_id);
            $html = '';

            if ( $settings && $settings['enabled'] && $settings['amount'] > 0 ) {
                $plugin_url = plugin_dir_url( $this->getPluginFile() );

                ob_start();
                include __DIR__ . '/../templates/payment-module.php';
                $html = ob_get_clean();
            }

            return $html . wpcf7_submit_form_tag_handler($tag);
        });
    }

    public function scripts()
    {
        wp_enqueue_script('cf7-payments', plugin_dir_url( $this->getPluginFile() ) . 'src/assets/payment.js', ['jquery'], $this::SCRIPTS_VERSION);

        wp_localize_script('cf7-payments', 'CF7_PAYMENTS_I18N', [
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'rest_url' => rest_url('cf7-payments/v1/'),
            'browser_payments' => $this->getBrowserPayments(),
        ]);

        wp_enqueue_style('cf7-payments', plugin_dir_url( $this->getPluginFile() ) . 'src/assets/style.css', [], $this::SCRIPTS_VERSION);

        // stripe.js
        wp_enqueue_script('stripe-elements', 'https://js.stripe.com/v3/');
    }

    public function restRoutes()
    {
        register_rest_route('cf7-payments/v1', '/(?P<form_id>\d+)/payment-intent', [
            'methods' => 'GET',
            'callback' => [$this, 'stripeIntent'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('cf7-payments/v1', '/(?P<form_id>\d+)/payment-intent/validate', [
            'methods' => 'POST',
            'callback' => [$this, 'stripeIntentValidate'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('cf7-payments/v1', '/(?P<form_id>\d+)/payment-id/(?P<payment_id>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'checkPaymentId'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('cf7-payments/v1', '/(?P<form_id>\d+)/paypal/checkout', [
            'methods' => 'GET',
            'callback' => [$this, 'paypalRedirectCheckout'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('cf7-payments/v1', '/(?P<form_id>\d+)/paypal/process-payment', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'paypalHandlePayment'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function stripeIntent( WP_REST_Request $request ) : WP_REST_Response
    {
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'wp_rest' ) )
            return new WP_REST_Response(null, 401);

        if ( ! $form_id = (int) $request->get_param('form_id') )
            return new WP_REST_Response(null, 404);

        $settings = $this->getFormSettings($form_id);

        if ( ! $settings || ! ( $settings['enabled'] ?? '' ) )
            return new WP_REST_Response(null, 404);

        Stripe::setApiKey( $settings['stripe_secret'] );

        try {
            $intent = PaymentIntent::create([
                'amount' => $settings['amount'] *100,
                'currency' => $settings['currency'],
                'payment_method_types' => ['card'],
                'metadata' => [
                    'cf7_payments_form_id' => $form_id,
                ]
            ]);

            if ( ! ( $intent->client_secret ?? null ) ) {
                error_log('stripe intent error: could not create intent.');
            } else {
                return new WP_REST_Response($intent->client_secret);
            }
        } catch( \Exception $e ) {
            error_log('stripe intent error: ' . $e->getMessage());
        }

        return new WP_REST_Response(null, 500);
    }

    public function stripeIntentValidate( WP_REST_Request $request ) : WP_REST_Response
    {
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'wp_rest' ) )
            return new WP_REST_Response(null, 401);

        if ( ! $form_id = (int) $request->get_param('form_id') )
            return new WP_REST_Response(null, 404);

        if ( ! $intent_id = $_POST['intent_id'] ?? '' )
            return new WP_REST_Response(null, 400);

        $settings = $this->getFormSettings($form_id);

        if ( ! $settings || ! ( $settings['enabled'] ?? '' ) )
            return new WP_REST_Response(null, 404);

        Stripe::setApiKey( $settings['stripe_secret'] );

        try {
            $intent = PaymentIntent::retrieve($intent_id);
        } catch ( Exception $e ) {
            error_log('stripe intent validation error: ' . $e->getMessage());
            return new WP_REST_Response(null, 500);
        }

        $intent_form_id = intval($intent->metadata->cf7_payments_form_id ?? '');

        if ( $intent_form_id != $form_id )
            return new WP_REST_Response(null, 404);

        $insert = Payments::insert([
            'id' => $intent_id,
            'amount' => $intent->amount /100,
            'claimed' => 0,
            'form_id' => $form_id,
            'date' => time(),
            'processor' => 'stripe',
            'payer' => $this->getPayerBrowserInfo(),
        ]);

        setcookie("cf7-payment_{$form_id}", $intent_id, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);

        return new WP_REST_Response($insert);
    }

    public function checkPaymentId( WP_REST_Request $request ) : WP_REST_Response
    {
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'wp_rest' ) )
            return new WP_REST_Response(null, 401);

        if ( ! $form_id = (int) $request->get_param('form_id') )
            return new WP_REST_Response(null, 404);

        if ( ! $payment_id = $request->get_param('payment_id') )
            return new WP_REST_Response(null, 400);

        $settings = $this->getFormSettings($form_id);

        if ( ! $settings || ! ( $settings['enabled'] ?? '' ) )
            return new WP_REST_Response(null, 404);

        $payment = Payments::queryOne([
            'id' => $payment_id,
            'form_id' => $form_id,
            'claimed' => 0,
        ]);

        unset($payment['payer']);

        return new WP_REST_Response($payment, $payment ? 200 : 404); 
    }

    public function getBrowserPayments() : array
    {
        $payments = [];
        
        array_map(function($key) use (&$payments)
        {
            $form_id = str_replace('cf7-payment_', '', $key);
            $payments[$form_id] = $_COOKIE[ $key ];
        }, array_filter(array_keys($_COOKIE), function($k)
        {
            return preg_match('/^cf7\-payment_([0-9]+)$/', $k);
        }));

        return $payments;
    }

    public function validateForm( $result )
    {
        $form_id = WPCF7_ContactForm::get_current()->id();

        $settings = $this->getFormSettings($form_id);

        if ( ! $settings || ! ( $settings['enabled'] ?? '' ) )
            return $result;

        $payment_id = $this->getBrowserPayments()[$form_id] ?? '';
        $submission = WPCF7_Submission::get_instance();
        $payment = $payment_id ? Payments::queryOne([
            'id' => $payment_id,
            'form_id' => $form_id,
            'claimed' => 0,
        ]) : null;

        if ( ! $payment ) {
            $submission->set_status( 'validation_failed' );
            $submission->set_response( __('Payment is required. For subsequent submissions, please refresh the page and try submitting another payment.', 'cf7-payments') );
            // unset cookie
            setcookie("cf7-payment_{$form_id}", ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            return $result;
        }

        return $result;
    }

    public function claimPayment( $form )
    {
        $form_id = $form->get_current()->id();

        $settings = $this->getFormSettings($form_id);

        if ( ! $settings || ! ( $settings['enabled'] ?? '' ) )
            return $result;

        $payment_id = $this->getBrowserPayments()[$form_id] ?? '';
        $payment = $payment_id ? Payments::queryOne([
            'id' => $payment_id,
            'form_id' => $form_id,
            'claimed' => 0,
        ]) : null;

        if ( $payment ) {
            // mark payment as claimed
            Payments::update($payment['id'], [ 'claimed' => 1 ]);

            // unset cookie
            setcookie("cf7-payment_{$form_id}", ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        }
    }

    public function paypalRedirectCheckout( WP_REST_Request $request ) : WP_REST_Response
    {
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'wp_rest' ) )
            return new WP_REST_Response(null, 401);

        if ( ! $form_id = (int) $request->get_param('form_id') )
            return new WP_REST_Response(null, 404);

        $settings = $this->getFormSettings($form_id);

        if ( ! $settings || ! ( $settings['enabled'] ?? '' ) )
            return new WP_REST_Response(null, 404);

        $environment = ($settings['paypal_sandbox'] ?? null) ? SandboxEnvironment::class : ProductionEnvironment::class;
        $environment = new $environment($settings['paypal_client'], $settings['paypal_secret']);
        $client = new PayPalHttpClient($environment);

        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => "cf7-payment_{$form_id}",
                'amount' => [
                    'value' => (string) $settings['amount'],
                    'currency_code' => $settings['currency'],
                ]
            ]],
            'application_context' => [
                'cancel_url' => rest_url("cf7-payments/v1/{$form_id}/paypal/process-payment?cancel=1"),
                'return_url' => rest_url("cf7-payments/v1/{$form_id}/paypal/process-payment"),
            ] 
        ];

        try {
            $response = $client->execute($request);
            setcookie('paypal-order-id', $response->result->id);
            exit(wp_redirect(current(array_filter($response->result->links, function($p) { return 'approve' == $p->rel; }))->href ?? ''));
        } catch ( Exception $e ) {
            error_log('paypal checkout error: ' . $e->getMessage());
        }

        return new WP_REST_Response(null, 500);
    }

    public function paypalHandlePayment( WP_REST_Request $request ) : WP_REST_Response
    {
        if ( ! $form_id = (int) $request->get_param('form_id') )
            return new WP_REST_Response(null, 404);

        $settings = $this->getFormSettings($form_id);

        if ( ! $settings || ! ( $settings['enabled'] ?? '' ) )
            return new WP_REST_Response(null, 404);

        if ( ! ( $_COOKIE['paypal-order-id'] ?? '' ) )
            return new WP_REST_Response(null, 400);

        $request = new OrdersCaptureRequest($_COOKIE['paypal-order-id']);
        $request->prefer('return=representation');
        $environment = ($settings['paypal_sandbox'] ?? null) ? SandboxEnvironment::class : ProductionEnvironment::class;
        $environment = new $environment($settings['paypal_client'], $settings['paypal_secret']);
        $client = new PayPalHttpClient($environment);

        $closeWindow = function()
        {
            header('content-type:text/html');
            exit('<script>window.close()</script><h3>' . __('Please close this window to continue.', 'cf7-payments') . '</h3>');
        };

        try {
            $response = $client->execute($request);

            if ( $response->result->id ?? '' ) {
                $payment_item = $response->result->purchase_units[0] ?? new \stdClass;
                $ref_form_id = (int) str_replace('cf7-payment_', '', $payment_item->reference_id ?? '');

                if ( ! $ref_form_id ) {
                    error_log('Could not fetch payment reference form id from payload');
                    return $closeWindow();
                }

                $insert = Payments::insert([
                    'id' => $response->result->id,
                    'amount' => floatval($payment_item->amount->value ?? '') ?: $response->result->amount,
                    'claimed' => 0,
                    'form_id' => $ref_form_id,
                    'date' => time(),
                    'processor' => 'paypal',
                    'payer' => $this->getPayerBrowserInfo(),
                ]);

                setcookie("cf7-payment_{$ref_form_id}", $response->result->id, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            } else {
                error_log('Could not fetch payment reference form id from payload');
                return $closeWindow();
            }
        } catch ( Exception $e ) {
            error_log('paypal checkout return error: ' . $e->getMessage());
        }

        return $closeWindow();
    }

    protected function getFormSettings( int $form_id ) : array
    {
        $settings = (array) get_option('cf7-payments_lite_settings');

        if ( ! $settings )
            return [];

        if ( $form_id != ( $settings['form_id'] ?? '' ) )
            return [];

        $settings['enabled'] = true;

        return apply_filters('cf7_payments_get_form_settings', $settings, $form_id);
    }

    private function getPayerBrowserInfo() : string
    {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED'])) $ip = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED'])) $ip = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR'])) $ip = $_SERVER['REMOTE_ADDR'];
        else $ip = null;
        $user_id = get_current_user_id();
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        return json_encode(compact('user_agent', 'user_id', 'ip'));
    }
}