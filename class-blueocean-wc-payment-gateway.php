<?php
if (!defined('ABSPATH')) {
    exit ();
} // Exit if accessed directly

class BlueOceanWCPaymentGateway extends WC_Payment_Gateway
{

    private $pay_url = 'http://api.hk.blueoceanpay.com/payment/pay';
    private $refund_url = 'http://api.hk.blueoceanpay.com/payment/refund';

    public function __construct()
    {
        //支持退款
        array_push($this->supports, 'refunds');

        $this->id         = WC_BlueOcean_ID;
        $this->icon       = WC_BlueOcean_URL . '/images/logo.png';
        $this->has_fields = false;

        $this->method_title       = '蓝海支付'; // checkout option title
        $this->method_description = '用户可通过手机微信或支付宝 APP 扫描二维码为订单完成付款。';

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');

        $lib = WC_BlueOcean_DIR . '/lib';

        include_once($lib . '/Library.php');

        if (!class_exists('BLogHandler')) {
            include_once($lib . '/log.php');
        }
    }

    function init_form_fields()
    {
        $this->form_fields = array(
            'enabled'            => array(
                'title'   => __('Enable/Disable', 'blueoceanpay'),
                'type'    => 'checkbox',
                'label'   => __('Enable BlueOceanPay Payment', 'blueoceanpay'),
                'default' => 'no'
            ),
            'title'              => array(
                'title'       => __('Title', 'blueoceanpay'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'blueoceanpay'),
                'default'     => __('BlueOceanPay', 'blueoceanpay'),
                'css'         => 'width:400px'
            ),
            'description'        => array(
                'title'       => __('Description', 'blueoceanpay'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'blueoceanpay'),
                'default'     => __("Pay via Wechat or AliPay scanning QR code", 'blueoceanpay'),
                'css'         => 'width:400px'
            ),
            'blueoceanpay_appID' => array(
                'title'       => __('Application ID', 'blueoceanpay'),
                'type'        => 'text',
                'description' => __('Please enter the Application ID,If you don\'t have one, <a href="https://admin.hk.blueoceanpay.com/apply" target="_blank">click here</a> to get.', 'blueoceanpay'),
                'css'         => 'width:400px'
            ),

            'blueoceanpay_key' => array(
                'title'       => __('BlueOceanPay Key', 'blueoceanpay'),
                'type'        => 'text',
                'description' => __('Please enter your BlueOceanPay Key; this is needed in order to take payment.', 'blueoceanpay'),
                'css'         => 'width:400px',
            ),
            'debug'            => array(
                'title'       => __('Debug log', 'blueoceanpay'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'blueoceanpay'),
                'default'     => 'no',
                'description' => __('Log payment events, such as payment requests. Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'blueoceanpay'),
            ),
        );

    }

    public function process_payment($order_id)
    {
        $order = new WC_Order ($order_id);
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    public function woocommerce_blueoceanpay_add_gateway($methods)
    {
        $methods[] = $this;
        return $methods;
    }

    public function get_order_status()
    {
        file_put_contents('get_order_status.txt', print_r($_POST, true));

        $order_id = isset($_POST ['orderId']) ? $_POST ['orderId'] : '';
        $order    = new WC_Order ($order_id);
        $isPaid   = !$order->needs_payment();

        echo json_encode(array(
            'status' => $isPaid ? 'paid' : 'unpaid',
            'url'    => $this->get_return_url($order)
        ));

        exit;
    }

    function wp_enqueue_scripts()
    {
        $orderId        = get_query_var('order-pay');
        $order          = new WC_Order ($orderId);
        $payment_method = method_exists($order, 'get_payment_method') ? $order->get_payment_method() : $order->payment_method;
        if ($this->id == $payment_method) {
            if (is_checkout_pay_page() && !isset ($_GET ['pay_for_order'])) {

                wp_enqueue_script('WECHAT_JS_QRCODE', WC_BlueOcean_URL . '/js/qrcode.js', array(), WC_BlueOcean_VERSION);
                wp_enqueue_script('WECHAT_JS_CHECKOUT', WC_BlueOcean_URL . '/js/checkout.js', array('jquery', 'WECHAT_JS_QRCODE'), WC_BlueOcean_VERSION);

            }
        }
    }

    public function check_blueoceanpay_response()
    {
        if (defined('WP_USE_THEMES') && !WP_USE_THEMES) {
            return;
        }

        $order_id       = $_POST['out_trade_no'];
        $transaction_id = $_POST['transaction_id'];

        file_put_contents('./post.txt', print_r($_POST, true));

        try {
            $order = new WC_Order ($order_id);
            if ($order->needs_payment()) {
                $order->payment_complete($transaction_id);
            }
        } catch (\Exception $e) {
            file_put_contents('./exception.txt', $e->getMessage());
        }

    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = new WC_Order ($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', '错误的订单');
        }

        $transaction_id = $order->get_transaction_id();
        if (empty ($transaction_id)) {
            return new WP_Error('invalid_order', '未找到支付交易号或订单未支付');
        }

        $total      = $order->get_total();
        $total_fee  = ( int )($total * 100);
        $refund_fee = ( int )($amount * 100);

        if ($refund_fee <= 0 || $refund_fee > $total_fee) {
            return new WP_Error('invalid_order', __('Invalid refused amount!', 'blueoceanpay'));
        }

        // 请求数据
        $requestData = [
            'appid'        => $this->get_option('blueoceanpay_appID'),
            'out_trade_no' => $order_id,
            'refund_fee'   => $refund_fee,
            'refund_desc'  => $reason,
        ];

        $app_key             = $this->get_option('blueoceanpay_key');
        $requestData['sign'] = sign($requestData, $app_key);

        try {
            $result     = httpPost($this->refund_url, json_encode($requestData));
            $returnData = json_decode($result, true);
            if ($returnData ['code'] != '200') {
                BLog::DEBUG("process_refund:" . json_encode($result));
                throw new Exception ("return_msg:" . $result ['code'] . ';err_code_des:' . $result ['message']);
            }
        } catch (Exception $e) {
            return new WP_Error('invalid_order', $e->getMessage());
        }

        return true;
    }

    /**
     * 处理支付
     * @param WC_Order $order
     */
    function receipt_page($order_id)
    {
        $order = new WC_Order($order_id);
        if (!$order || !$order->needs_payment()) {
            wp_redirect($this->get_return_url($order));
            exit;
        }

        echo '<p>' . __('Please scan the QR code with WeChat or AliPay to finish the payment.', 'blueoceanpay') . '</p>';

        // 请求数据
        $requestData         = [
            'appid'        => $this->get_option('blueoceanpay_appID'),
            'payment'      => 'blueocean.qrcode',
            'body'         => $this->get_order_title($order),
            'out_trade_no' => $order_id,
            'attach'       => $order_id,
            'total_fee'    => $order->get_total() * 100,
            'notify_url'   => get_option('siteurl'),
        ];
        $app_key             = $this->get_option('blueoceanpay_key');
        $requestData['sign'] = sign($requestData, $app_key);

        try {
            $result     = httpPost($this->pay_url, json_encode($requestData));
            $returnData = json_decode($result, true);
        } catch (Exception $e) {
            echo $e->getMessage();
            return;
        }

        $data['error']    = '';
        $data['code_url'] = '';

        if ($returnData['code'] != 200) {
            echo "return_msg:" . $returnData['code'] . ': ' . $returnData['message'];
            return;
        } else {
            $data['code_url'] = $returnData['data']['qrcode'];
        }

        $url = isset($data['code_url']) ? $data ["code_url"] : '';
        echo '<input type="hidden" id="blueoceanpay-payment-pay-url" value="' . $url . '"/>';
        echo '<div style="width:200px;height:200px" id="blueoceanpay-payment-pay-img" data-oid="' . $order_id . '"></div>';
    }

    /**
     *
     * @param WC_Order $order
     * @param number $limit
     * @param string $trimmarker
     */
    public function get_order_title($order, $limit = 32, $trimmarker = '...')
    {
        $id    = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
        $title = "#{$id}|" . get_option('blogname');

        $order_items = $order->get_items();
        if ($order_items && count($order_items) > 0) {
            $title = "#{$id}|";
            $index = 0;
            foreach ($order_items as $item_id => $item) {
                $title .= $item['name'];
                if ($index++ > 0) {
                    $title .= '...';
                    break;
                }
            }
        }

        return apply_filters('blueoceanpay_wc_get_order_title', mb_strimwidth($title, 0, 32, '...', 'utf-8'));
    }
}

