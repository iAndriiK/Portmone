<?php
/*
 * Author: Andrii K (andrey.kovt@gmail.com)
 * Date: 16.03.2023
 * Time: 10:56:06
 */

class PortmoneSDK {
    const CURRENCY_UAH = 'UAH';
    const CURRENCY_USD = 'USD';
    const CURRENCY_EUR = 'EUR';
    const CURRENCY_GBP = 'GBP';
    const CURRENCY_PLN = 'PLN';
    const CURRENCY_KZT = 'KZT';

    const LANGUAGE_UA = 'uk';
    const LANGUAGE_EN = 'en';

    private $_api_url = 'https://www.portmone.com.ua/r3/api/';
    private $_checkout_url = 'https://www.portmone.com.ua/gateway/';
    private $_result_url = 'https://www.portmone.com.ua/';

    private $_login;
    private $_password;
    private $_payee_id;
    private $_server_response_code = null;

    protected $_supportedCurrencies = array(
        self::CURRENCY_UAH,
        self::CURRENCY_USD,
        self::CURRENCY_EUR,
        self::CURRENCY_GBP,
        self::CURRENCY_PLN,
        self::CURRENCY_KZT,
    );

    protected $_supportedLanguages = array(
        self::LANGUAGE_UA,
        self::LANGUAGE_EN,
    );

    public function __construct($login, $password, $payee_id, $api_url = null) {
        if (empty($login)) {
            throw new InvalidArgumentException('login is empty');
        }

        if (empty($password)) {
            throw new InvalidArgumentException('password is empty');
        }

        if (empty($payee_id)) {
            throw new InvalidArgumentException('payee_id is empty');
        }

        $this->_login = $login;
        $this->_password = $password;
        $this->_payee_id = $payee_id;

        if (null !== $api_url) {
            $this->_api_url = $api_url;
        }
    }

    public function get_checkout_url() {
        return $this->_checkout_url;
    }

    public function get_response_code() {
        return $this->_server_response_code;
    }

    public function api($path, $method, $params = array(), $timeout = 5) {
        $login      = $this->_login;
        $password   = $this->_password;
        $payee_id   = $this->_payee_id;

        $query = new stdClass();
        $query->method = $method;
        $query->id = '1';

        switch ($method) {
            case 'result':
                $url = $this->_result_url . $path . '/';
                $params['login'] = $login;
                $params['password'] = $password;
                $params['payeeId'] = $payee_id;
                $query->params->data = $params;
                break;
            default:
                $url = $this->_api_url . $path . '/';
                $query->params = new stdClass();
                $query->params->authData = [
                    'login' => $login,
                    'password' => $password,
                    'payeeId' => $payee_id,
                ];
                $query->params->data = [
                    $params,
                ];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, !empty($query));
        if (!empty($query)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query, JSON_UNESCAPED_UNICODE));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        $response = curl_exec($ch);
        $this->_server_response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return json_decode($response);
    }

    public function cnb_currency($currency) {
        if (in_array($currency, $this->_supportedCurrencies)) {
            return $currency;
        }
        return false;
    }

    public function cnb_language($language) {
        if (in_array($language, $this->_supportedLanguages)) {
            return $language;
        }
        return reset($this->_supportedLanguages);
    }

    public function cnb_params($params) {
        if (!isset($params['amount'])) {
            throw new InvalidArgumentException('amount is null');
        }
        if (!isset($params['ccy'])) {
            throw new InvalidArgumentException('currency is null');
        }
        if (!in_array($params['ccy'], array_keys($this->_supportedCurrencies))) {
            throw new InvalidArgumentException('currency is not supported');
        }
        $params['ccy'] = $this->_supportedCurrencies[$params['ccy']];
        if (isset($params['merchantPaymInfo']) && !isset($params['merchantPaymInfo']['destination'])) {
            throw new InvalidArgumentException('description is null');
        }
        return $params;
    }

}
