<?php

abstract class TelegramBotCore {

    protected $host;
    protected $port;
    protected $apiUrl;
    public $botId;
    public $botUsername;
    protected $botToken;
    protected $handle;
    protected $inited = false;
    protected $lpDelay = 1;
    protected $netDelay = 1;
    protected $updatesOffset = false;
    protected $updatesLimit = 1;
    protected $updatesTimeout = 0;
    protected $netTimeout = 10;
    protected $netConnectTimeout = 5;

    public function __construct($token, $options = array()) {
        $options += array(
            'host' => 'api.telegram.org',
            'port' => 443,
        );

        $this->host = $host = $options['host'];
        $this->port = $port = $options['port'];
        $this->botToken = $token;

        $proto_part = ($port == 443 ? 'https' : 'http');
        $port_part = ($port == 443 || $port == 80) ? '' : ':' . $port;

        $this->apiUrl = "{$proto_part}://{$host}{$port_part}/bot{$token}";
    }

    public function init() {
        if ($this->inited) {
            return true;
        }

        $this->handle = curl_init();

        $response = $this->request('getMe');
        if (!$response['ok']) {
            throw new Exception("Can't connect to server");
        }

        $bot = $response['result'];
        $this->botId = $bot['id'];
        $this->botUsername = $bot['username'];

        $this->inited = true;
        return true;
    }

    public function runLongpoll() {
        $this->init();
        $this->longpoll();
    }

    public function setWebhook($url) {
        $this->init();
        $result = $this->request('setWebhook', array('url' => $url));
        return $result['ok'];
    }

    public function removeWebhook() {
        $this->init();
        $result = $this->request('setWebhook', array('url' => ''));
        return $result['ok'];
    }

    public function request($method, $params = array(), $options = array()) {
        $options += array(
            'http_method' => 'GET',
            'timeout' => $this->netTimeout,
        );
        $params_arr = array();
        foreach ($params as $key => &$val) {
            if (!is_numeric($val) && !is_string($val)) {
                $val = json_encode($val);
            }
            $params_arr[] = urlencode($key) . '=' . urlencode($val);
        }
        $query_string = implode('&', $params_arr);

        $url = $this->apiUrl . '/' . $method;

        if ($options['http_method'] === 'POST') {
            curl_setopt($this->handle, CURLOPT_SAFE_UPLOAD, false);
            curl_setopt($this->handle, CURLOPT_POST, true);
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, $query_string);
        } else {
            $url .= ($query_string ? '?' . $query_string : '');
            curl_setopt($this->handle, CURLOPT_HTTPGET, true);
        }

        $connect_timeout = $this->netConnectTimeout;
        $timeout = $options['timeout'] ? : $this->netTimeout;
        //echo $url;
        curl_setopt($this->handle, CURLOPT_URL, $url);
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
        curl_setopt($this->handle, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($this->handle, CURLOPT_SSL_VERIFYPEER, false);

        $response_str = curl_exec($this->handle);
        //echo $response_str;
        $errno = curl_errno($this->handle);
        $http_code = intval(curl_getinfo($this->handle, CURLINFO_HTTP_CODE));
        //echo $errno;
        if ($http_code == 401) {
            throw new Exception('Invalid access token provided');
        } else if ($http_code >= 500 || $errno) {
            sleep($this->netDelay);
            if ($this->netDelay < 30) {
                $this->netDelay *= 2;
            }
        }

        $response = json_decode($response_str, true);

        return $response;
    }

    protected function longpoll() {
        $params = array(
            'limit' => $this->updatesLimit,
            'timeout' => $this->updatesTimeout,
        );
        if ($this->updatesOffset) {
            $params['offset'] = $this->updatesOffset;
        }
        $options = array(
            'timeout' => $this->netConnectTimeout + $this->updatesTimeout + 2,
        );
        $response = $this->request('getUpdates', $params, $options);
        if ($response['ok']) {
            $updates = $response['result'];
            if (is_array($updates)) {
                foreach ($updates as $update) {
                    $this->updatesOffset = $update['update_id'] + 1;
                    $this->onUpdateReceived($update);
                }
            }
        }
        $this->longpoll();
    }

    abstract public function onUpdateReceived($update);
}
