<?php
/*
 * Author: Andrii K (andrey.kovt@gmail.com)
 * Date: 16.03.2023
 * Time: 11:53:44
 */

/**
 * IPN Script for Portmone
 */

// Working in root dir
chdir(dirname(dirname(__DIR__)));
require_once('api/Home.php');
require_once('payment/Portmone/PortmoneSDK.php');

class PortmoneCallback extends Home {

    private $sdk;
    private $url;

    public function fetch() {
        try {

            if (!$this->request->method('post')) {
				throw new Exception('unknown request');
			}

            $bill_id = $this->request->post('SHOPBILLID');
            $bill_number = $this->request->post('SHOPORDERNUMBER');
            $amount = $this->request->post('BILL_AMOUNT');
            $result = $this->request->post('RESULT');

            file_put_contents('payment/' . basename(__DIR__) . '/log.txt', date("m.d.Y H:i:s") . ' ' . json_encode($_POST, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

            if (empty($bill_number)) {
				throw new Exception('empty order id');
			}

            if ((string)$result !== '0') {
				throw new Exception('bad status');
			}

            list($order_id) = explode('#', $bill_number);
			if (empty($order_id) || !($order = $this->orders->get_order(intval($order_id)))) {
				throw new Exception('empty order');
			}

            $payment_language = $this->languages->get_language(intval($order->lang_id));
            $this->languages->set_lang_id(intval($payment_language->id));

            $this->url = $this->config->root_url . '/' . $this->languages->get_lang_link() . 'order/' . $order->url;

			if (empty($order->payment_method_id) || !($payment_method = $this->payment->get_payment_method(intval($order->payment_method_id)))) {
				throw new Exception('empty payment method');
			}

			if (empty($payment_method->settings)) {
				throw new Exception('empty settings');
			}

            if ($order->paid) {
				throw new Exception('order already paid');
			}

			if (empty($payment_method->currency_id) || !($this->money->get_currency(intval($payment_method->currency_id)))) {
				throw new Exception('empty currency');
			}

            if ($amount != round($this->money->convert($order->total_price, $payment_method->currency_id, false), 2) || $amount <= 0) {
                throw new Exception('incorrect price');
            }

			$settings = unserialize($payment_method->settings);

			$this->sdk = new PortmoneSDK($settings['portmone_login'], $settings['portmone_password'], $settings['portmone_payeeId']);

            $res = $this->sdk->api('gateway', 'result', [
                'shopOrderNumber' => $bill_number,
                'shopbillId' => $bill_id,
            ]);

            if (empty($res)) {
				throw new Exception('empty order');
			}

            $res = reset($res);

            if (!in_array($res->status, ['PAYED'])) {
                throw new Exception('bad status');
            }

            if ($amount != $res->billAmount || $res->billAmount <= 0) {
                throw new Exception('incorrect price');
            }

            // Установим статус оплачен
			$this->orders->update_order(intval($order->id), array('payment_date' => date('Y-m-d H:i:s'), 'paid' => 1));

			// Спишем товары  
			$this->orders->close(intval($order->id));

			// Отправим уведомление на email
			$this->notify->email_order_user(intval($order->id));
			$this->notify->email_order_admin(intval($order->id));

            $result = [
                'status' => 'success',
                'url' => $this->url,
            ];

        } catch (Exception $e) {
            file_put_contents('payment/' . basename(__DIR__) . '/log.txt', date("m.d.Y H:i:s") . ' ' . $e->getMessage() . "\n", FILE_APPEND);
            $result = [
                'status' => $e->getMessage(),
                'url' => $this->url,
            ];
        }

        return $result;
    }

}

$results = new PortmoneCallback();
if ($result = $results->fetch()) {
    if (!empty($result['url'])) {
        header("HTTP/1.1 302 Found");
        header('Location: ' . $result['url']);
    } else {
        header("Content-Type: text/plain; charset=UTF-8");
        header("Cache-Control: must-revalidate");
        header("Pragma: no-cache");
        header("Expires: -1");
        print $result['status'];
    }
}
exit();