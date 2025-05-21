<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_TouchPay extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'touchpay';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = __('TouchPay', 'wc-touchpay');
        $this->method_description = __('Payer via TouchPay (paiement sécurisé).', 'wc-touchpay');

        // Champs de configuration
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->agency_code = $this->get_option('agency_code');
        $this->secure_code = $this->get_option('secure_code');
        $this->mode = $this->get_option('mode');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Activer/Désactiver', 'wc-touchpay'),
                'type'    => 'checkbox',
                'label'   => __('Activer TouchPay', 'wc-touchpay'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Titre', 'wc-touchpay'),
                'type'        => 'text',
                'description' => __('Titre affiché lors du paiement.', 'wc-touchpay'),
                'default'     => __('TouchPay', 'wc-touchpay'),
                'desc_tip'    => true,
            ),
            'agency_code' => array(
                'title'       => __('Agency Code', 'wc-touchpay'),
                'type'        => 'text',
                'description' => __('Votre code agence TouchPay.', 'wc-touchpay'),
                'desc_tip'    => true,
            ),
            'secure_code' => array(
                'title'       => __('Secure Code', 'wc-touchpay'),
                'type'        => 'text',
                'description' => __('Votre code sécurisé TouchPay.', 'wc-touchpay'),
                'desc_tip'    => true,
            ),
            'mode' => array(
                'title'       => __('Mode', 'wc-touchpay'),
                'type'        => 'select',
                'description' => __('Choisissez le mode Sandbox ou Production.', 'wc-touchpay'),
                'default'     => 'sandbox',
                'desc_tip'    => true,
                'options'     => array(
                    'sandbox'    => __('Sandbox', 'wc-touchpay'),
                    'production' => __('Production', 'wc-touchpay'),
                ),
            ),
        );
    }

    public function validate_fields() {
        // Validation côté front si besoin
        return true;
    }

    public function process_admin_options() {
        // Validation et nettoyage des champs admin
        $post_data = $this->get_post_data();
        $this->settings['agency_code'] = sanitize_text_field($post_data['woocommerce_touchpay_agency_code']);
        $this->settings['secure_code'] = sanitize_text_field($post_data['woocommerce_touchpay_secure_code']);
        $this->settings['mode'] = in_array($post_data['woocommerce_touchpay_mode'], ['sandbox', 'production']) ? $post_data['woocommerce_touchpay_mode'] : 'sandbox';
        parent::process_admin_options();
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        // Redirection vers une page intermédiaire pour injecter le script TouchPay
        return array(
            'result'   => 'success',
            'redirect' => add_query_arg('touchpay_order', $order_id, wc_get_checkout_url()),
        );
    }

    // Page intermédiaire pour TouchPay
    public static function maybe_render_touchpay_page() {
        if (!isset($_GET['touchpay_order'])) {
            return;
        }
        $order_id = absint($_GET['touchpay_order']);
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        $gateway = new self();
        $agency_code = esc_js($gateway->get_option('agency_code'));
        $secure_code = esc_js($gateway->get_option('secure_code'));
        $mode = esc_js($gateway->get_option('mode'));
        $domain_name = esc_js(parse_url(get_site_url(), PHP_URL_HOST));
        $amount = esc_js($order->get_total());
        $city = esc_js($order->get_billing_city());
        $email = esc_js($order->get_billing_email());
        $first_name = esc_js($order->get_billing_first_name());
        $last_name = esc_js($order->get_billing_last_name());
        $phone = esc_js($order->get_billing_phone());
        $order_number = esc_js($order->get_id());
        $url_success = esc_js(add_query_arg('touchpay_status', 'success', $order->get_checkout_order_received_url()));
        $url_failed = esc_js(add_query_arg('touchpay_status', 'failed', $order->get_checkout_order_received_url()));
        $script_url = 'https://touchpay.gutouch.net/touchpayv2/script/touchpaynr/prod_touchpay-0.0.1.js';
        // Affichage HTML minimal
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>TouchPay</title></head><body>';
        echo '<p>' . __('Redirection vers TouchPay en cours...', 'wc-touchpay') . '</p>';
        echo '<script src="' . esc_url($script_url) . '" id="touchpay-script" type="text/javascript"></script>';
        echo '<script type="text/javascript">';
        echo 'function sendPaymentInfosWhenReady() {';
        echo '  if (typeof SendPaymentInfos === "function") {';
        echo '    SendPaymentInfos(';
        echo '      "' . $order_number . '",';
        echo '      "' . $agency_code . '",';
        echo '      "' . $secure_code . '",';
        echo '      "' . $domain_name . '",';
        echo '      "' . $url_success . '",';
        echo '      "' . $url_failed . '",';
        echo '      "' . $amount . '",';
        echo '      "' . $city . '",';
        echo '      "' . $email . '",';
        echo '      "' . $first_name . '",';
        echo '      "' . $last_name . '",';
        echo '      "' . $phone . '"';
        echo '    );';
        echo '  } else {';
        echo '    setTimeout(sendPaymentInfosWhenReady, 100);';
        echo '  }';
        echo '}';
        echo 'window.onload = sendPaymentInfosWhenReady;';
        echo '</script>';
        echo '</body></html>';
        exit;
    }
}
// Hook pour afficher la page intermédiaire TouchPay
add_action('template_redirect', array('WC_Gateway_TouchPay', 'maybe_render_touchpay_page')); 