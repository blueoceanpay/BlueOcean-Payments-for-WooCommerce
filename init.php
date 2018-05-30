<?php
/*
 * Plugin Name: BlueOceanPay Payments for WooCommerce
 * Plugin URI: 
 * Description: 为 WooCommerce 系统添加微信和支付宝支付功能。
 * Version: 1.0.0
 * Author: 蓝海网络 
 * Author URI:http://www.blueoceanpay.com
 * Text Domain: BlueOceanPay Payments for WooCommerce
 */
if (!defined('ABSPATH')) {
    exit ();
} // Exit if accessed directly

if (!defined('WC_BLUEOCEANPAY')) {
    define('WC_BLUEOCEANPAY', 'WC_BLUEOCEANPAY');
} else {
    return;
}
define('WC_BlueOcean_VERSION', '1.0.0');
define('WC_BlueOcean_ID', 'blueoceanwcpaymentgateway' /*'blueocean'*/);
define('WC_BlueOcean_DIR', rtrim(plugin_dir_path(__FILE__), '/'));
define('WC_BlueOcean_URL', rtrim(plugin_dir_url(__FILE__), '/'));
load_plugin_textdomain('blueoceanpay', false, dirname(plugin_basename(__FILE__)) . '/lang/');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'blueocean_wc_payment_gateway_plugin_edit_link');
add_action('init', 'blueocean_wc_payment_gateway_init');

register_activation_hook(__FILE__, function () {
    global $wpdb;
    $wpdb->query(
        "update {$wpdb->prefix}postmeta
        set meta_value='blueocean'
        where meta_key='_payment_method'
        and meta_value='blueoceanwcpaymentgateway';");
});

if (!function_exists('blueocean_wc_payment_gateway_init')) {
    function blueocean_wc_payment_gateway_init()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        require_once WC_BlueOcean_DIR . '/class-blueocean-wc-payment-gateway.php';
        $api = new BlueOceanWCPaymentGateway();

        $api->check_blueoceanpay_response();

        add_filter('woocommerce_payment_gateways', array($api, 'woocommerce_blueoceanpay_add_gateway'), 10, 1);
        add_action('wp_ajax_WECHAT_PAYMENT_GET_ORDER', array($api, "get_order_status"));
        add_action('wp_ajax_nopriv_WECHAT_PAYMENT_GET_ORDER', array($api, "get_order_status"));
        add_action('woocommerce_receipt_' . $api->id, array($api, 'receipt_page'));
        add_action('woocommerce_update_options_payment_gateways_' . $api->id, array($api, 'process_admin_options')); // WC >= 2.0
        add_action('woocommerce_update_options_payment_gateways', array($api, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($api, 'wp_enqueue_scripts'));
    }
}

function blueocean_wc_payment_gateway_plugin_edit_link($links)
{
    return array_merge(
        array(
            'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . WC_BlueOcean_ID) . '">' . __('Settings', 'blueoceanpay') . '</a>'
        ),
        $links
    );
}

?>