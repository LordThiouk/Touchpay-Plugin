<?php
/*
Plugin Name: WooCommerce TouchPay Gateway
Description: Passerelle de paiement TouchPay pour WooCommerce.
Version: 1.0.0
Author: LordThiouk
Text Domain: wc-touchpay
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Charger le text domain pour la traduction
add_action('plugins_loaded', function() {
    load_plugin_textdomain('wc-touchpay', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Ajouter la passerelle TouchPay à WooCommerce
add_filter('woocommerce_payment_gateways', function($gateways) {
    $gateways[] = 'WC_Gateway_TouchPay';
    return $gateways;
});

// Inclure la classe principale de la passerelle
add_action('plugins_loaded', function() {
    if (class_exists('WC_Payment_Gateway')) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-touchpay.php';
    }
}); 