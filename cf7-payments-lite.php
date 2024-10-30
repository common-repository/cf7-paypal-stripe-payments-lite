<?php
/*
Plugin Name: Payments Lite for Contact Form 7 with PayPal & Stripe
Plugin URI: https://www.wputil.com/cf7-payments
Description: Require payments on your Contact Form 7 forms with PayPal or Stripe payment gateways.
Author: wputil
Version: 0.2
Author URI: https://www.wputil.com/about
License: GPLv3
Text Domain: cf7-payments
*/

if ( ! defined ( 'WPINC' ) ) {
    exit; // direct access
}

if ( class_exists('\Cf7Payments\App') ) {
    return;
}

require_once __DIR__ . '/src/vendor/autoload.php';

(new \Cf7Payments\App(__FILE__))->setup();
