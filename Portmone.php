<?php
/*
 * Author: Andrii K (andrey.kovt@gmail.com)
 * Date: 16.03.2023
 * Time: 10:56:06
 */

require_once('api/Home.php');
require_once('payment/Portmone/PortmoneSDK.php');

class Portmone extends Home {

    private $sdk;

    public function checkout_form($order_id) {
        $res = array();

        try {
            if (empty($order_id) || !($order = $this->orders->get_order((int)$order_id))) {
                throw new Exception('order not found');
            }

            if (!($this->orders->get_purchases(array('order_id' => intval($order->id))))) {
                throw new Exception('empty purchases');
            }

            if (empty($order->payment_method_id) || !($payment_method = $this->payment->get_payment_method(intval($order->payment_method_id)))) {
                throw new Exception('empty payment method');
            }

            if (!($settings = $this->payment->get_payment_settings(intval($payment_method->id)))) {
                throw new Exception('empty settings');
            }

            if (empty($payment_method->currency_id) || !($payment_currency = $this->money->get_currency(intval($payment_method->currency_id)))) {
                throw new Exception('empty currency');
            }

            if ($order->paid) {
                throw new Exception('already paid');
            }

            $curr_lang_id = $this->languages->lang_id();
            $payment_language = $this->languages->get_language(intval($order->lang_id));
            $this->languages->set_lang_id(intval($payment_language->id));

            $description = '';

            if ($order_labels = $this->orderlabels->get_order_labels($order->id)) {
                $description = [];
                foreach ($order_labels as $order_label) {
                    if (!empty($order_label->label_1c)) {
                        $description[] = $order_label->name;
                    }
                }
                $description = implode(', ', $description);
            }

            if (empty($description)) {
                $lang = $this->design->get_var('lang');
                if (!empty($lang->order_description)) {
                    $description = $lang->order_description . $order->id;
                } else {
                    $description = 'Order payment #' . $order->id;
                }
            }

            $amount = (float)round($this->money->convert($order->total_price, $payment_method->currency_id, false), 2);
            $currency = $payment_currency->code;

            $this->sdk = new PortmoneSDK($settings['portmone_login'], $settings['portmone_password'], $settings['portmone_payeeId']);

            $currency = $this->sdk->cnb_currency($currency);
            if (empty($currency)) {
                throw new InvalidArgumentException('currency is empty');
            }

            $language = $this->sdk->cnb_language($payment_language->href_lang);
            if (empty($language)) {
                throw new InvalidArgumentException('language is empty');
            }

            $res['payee_id'] = $settings['portmone_payeeId'];
            $res['shop_order_number'] = $order->id . '#' . time();
            $res['bill_amount'] = $amount;
            $res['bill_currency'] = $currency;
            $res['description'] = trim($description);
            $res['success_url'] = $this->config->root_url . '/payment/' . basename(__DIR__) . '/callback.php';
            $res['failure_url'] = $this->config->root_url . '/' . $this->languages->get_lang_link() . 'order/' . $order->url;
            $res['order_lang_link'] = $language;
            $res['ipn_url'] = $this->sdk->get_checkout_url();

            $this->languages->set_lang_id(intval($curr_lang_id));

        } catch (Exception $e) {
            file_put_contents('payment/' . basename(__DIR__) . '/log.txt', date("m.d.Y H:i:s") . ' ' . $e->getMessage() . "\n", FILE_APPEND);
        }

        return $res;
    }



}
 