<?php

namespace Cf7Payments\Admin;

use Cf7Payments\App;

use Cf7Payments\Admin\Screen\Settings;

class Admin
{
    private $appContext;

    public function __construct( App $appContext )
    {
        $this->appContext = $appContext;

        if ( is_admin() && ( ! defined('DOING_AJAX') || ! DOING_AJAX ) ) {
            // menu
            add_action('admin_menu', [$this, 'pages']);

            // headers
            add_action('admin_menu', [$this, 'init']);

            // update settings
            $_POST && add_action('admin_menu', [$this, 'maybeUpdate']);

            // scripts
            add_action('admin_enqueue_scripts', [$this, 'scripts']);
        }

        if ( is_admin() ) {
            // meta links
            add_filter('plugin_action_links_' . plugin_basename($this->appContext->getPluginFile()), [$this, 'metaLinks']);
        }

        return $this;
    }

    public function pages()
    {
        add_submenu_page(
            'options-general.php',
            __('CF7 Payments &mdash; Settings', 'cf7-payments'),
            __('CF7 Payments', 'cf7-payments'),
            'manage_options',
            'cf7-payments-settings',
            [$this->getScreenObject( Settings::class ), 'render']
        );
    }

    private function callPageScreenMethod(string $method)
    {
        switch ( $_REQUEST['page'] ?? null ) {
            case 'cf7-payments-settings':
                return call_user_func([$this->getScreenObject( Settings::class ), $method]);
        }
    }

    public function init()
    {
        return $this->callPageScreenMethod('init');
    }

    public function scripts()
    {
        return $this->callPageScreenMethod('scripts');
    }

    public function maybeUpdate()
    {
        return $this->callPageScreenMethod('update');
    }

    public function getScreenObject( string $class )
    {
        return $this->screenContext[$class] ?? ( $this->screenContext[$class] = new $class( $this->appContext ) );
    }

    public function metaLinks( array $links )
    {
        return array_merge([
            'settings' => '<a href="options-general.php?page=cf7-payments-settings">' . __( 'Settings', 'cf7-payments' ) . '</a>',
            'upgrade' => '<a href="https://www.wputil.com/cf7-payments" target="_blank" style="font-weight:600">' . __( 'Upgrade to PRO', 'cf7-payments' ) . '</a>',
        ], $links);
    }
}