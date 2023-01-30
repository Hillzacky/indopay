<?php

class Config {

  const SANDBOX_BASE_URL    = 'https://api-sandbox.doku.com';
  const PRODUCTION_BASE_URL = 'https://api.doku.com';

  /**
   * @return string Doku API URL, depends on $state
   */
  public static function getBaseUrl($state)
  {
    return $state ? Config::PRODUCTION_BASE_URL : Config::SANDBOX_BASE_URL;
  }
}
class Client
{
    /**
     * @var array
     */
    private $config = array();

    public function isProduction($value)
    {
        $this->config['environment'] = $value;
    }

    public function setClientID($clientID)
    {
        $this->config['client_id'] = $clientID;
    }

    public function setSharedKey($key)
    {
        $this->config['shared_key'] = $key;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function generateMandiriVa($params)
    {
        $this->config = $this->getConfig();
        return MandiriVa::generated($this->config, $params);
    }

    public function generateDokuVa($params)
    {
        $this->config = $this->getConfig();
        return DokuVa::generated($this->config, $params);
    }

    public function generateBsiVa($params)
    {
        $this->config = $this->getConfig();
        return BsiVa::generated($this->config, $params);
    }

    public function generateBcaVa($params)
    {
        $this->config = $this->getConfig();
        return BcaVa::generated($this->config, $params);
    }

    public function generateBriVa($params)
    {
        $this->config = $this->getConfig();
        return BriVa::generated($this->config, $params);
    }

    public function generateCreditCard($params)
    {
        $this->config = $this->getConfig();
        return CreditCard::generated($this->config, $params);
    }

    public function generateDokuWallet($params)
    {
        $this->config = $this->getConfig();
        return DokuWallet::generated($this->config, $params);
    }

    public function generateShopeePay($params)
    {
        $this->config = $this->getConfig();
        return ShopeePay::generated($this->config, $params);
    }

    public function generateOvo($params)
    {
        $this->config = $this->getConfig();
        return Ovo::generated($this->config, $params);
    }
}

class PaycodeGenerator
{
    public static function cc($config, $params)
    {
        $header = array();
        $data = array(
            "customer" => array(
                "id" => $params['customerId'],
                "name" => trim($params['customerName']),
                "email" => $params['customerEmail'],
                "phone" => $params['phone'],
                "country" => $params['country'],
                "address" => $params['address']
            ),
            "order" => array(
                "invoice_number" => $params['invoiceNumber'],
                "amount" => $params['amount'],
                "line_items" => $params['lineItems'],
                "failed_url" => $params['urlFail'],
                "callback_url" => $params['urlSuccess'],
                "auto_redirect" => false
            ),
            "override_configuration" => array(
                "themes" => array(
                    "language" => $params['language'] != "" ? $params['language'] : "" ,
                    "background_color" => $params['backgroundColor'] != "" ? $params['backgroundColor'] : "" ,
                    "font_color" => $params['fontColor'] != "" ? $params['fontColor'] : "" ,
                    "button_background_color" => $params['buttonBackgroundColor'] != "" ? $params['buttonBackgroundColor'] : "" ,
                    "button_font_color" => $params['buttonFontColor'] != "" ? $params['buttonFontColor'] : "" ,
                )
            ),
            "additional_info" => array(
                "integration" => array(
                    "name" => "php-library",
                    "version" => "2.1.0"
                )
            )
        );

        if (isset($params['amount'])) {
            $data['order']["amount"] = $params['amount'];
        } else {
            $data['order']["min_amount"] = $params['min_amount'];
            $data['order']["max_amount"] = $params['min_amount'];
        }

        $requestId = rand(1, 100000);
        $dateTime = gmdate("Y-m-d H:i:s");
        $dateTime = date(DATE_ISO8601, strtotime($dateTime));
        $dateTimeFinal = substr($dateTime, 0, 19) . "Z";

        $getUrl = Config::getBaseUrl($config['environment']);

        $targetPath = $params['targetPath'];
        $url = $getUrl . $targetPath;

        $header['Client-Id'] = $config['client_id'];
        $header['Request-Id'] = $requestId;
        $header['Request-Timestamp'] = $dateTimeFinal;
        $signature = Utils::generateSignature($header, $targetPath, json_encode($data), $config['shared_key']);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Signature:' . $signature,
            'Request-Id:' . $requestId,
            'Client-Id:' . $config['client_id'],
            'Request-Timestamp:' . $dateTimeFinal,
            'Request-Target:' . $targetPath,

        ));

        $responseJson = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (is_string($responseJson) && $httpcode == 200) {
            return json_decode($responseJson, true);
        } else {
            echo $responseJson;
            return null;
        }
    }

    public static function eMoney($config, $params)
    {
        $header = array();
        if ($params['channel'] === 'shopeepay') {
        $data =  array(
            "order" => array(
                "invoice_number" => $params['invoiceNumber'],
                "amount" => $params['amount'],
                "callback_url" => $params['callbackUrl'],
                "expired_time" => $params['expiredTime']
            ),
            "additional_info" => array(
                "integration" => array(
                    "name" => "php-library",
                    "version" => "2.1.0"
                )
            )
        );
    } else if ($params['channel'] === 'ovo') {    
        $data =  array(
            "client"=> array(
                "id"=> $params['clientId']
            ),
            "order"=> array(
                "invoice_number"=> $params['invoiceNumber'],
                "amount"=> $params['amount']
            ),
            "ovo_info"=> array(
                "ovo_id"=> $params['ovoId']
            ),
            "security" => array(
                "check_sum"=>$params['checkSum']
            ),
            "additional_info" => array(
                "integration" => array(
                    "name" => "php-library",
                    "version" => "2.1.0"
                )
            )
        ) ;
    } else if ($params['channel'] === 'dw') {  
        $data =  array(
            "order" => array (
                "invoice_number" => $params['invoiceNumber'],
                "amount" => $params['amount'],
                "success_url" => $params['callbackUrl'],
                "failed_url" => $params['urlFail'],
                "notify_url" => $params['notifyUrl'],
                "auto_redirect" => false
            ),
            "additional_info" => array(
                "integration" => array(
                    "name" => "php-library",
                    "version" => "2.1.0"
                )
            )
        );
    }
        
        if (isset($params['amount'])) {
            $data['order']["amount"] = $params['amount'];
        } else {
            $data['order']["min_amount"] = $params['min_amount'];
            $data['order']["max_amount"] = $params['min_amount'];
        }

        $requestId = rand(1, 100000);
        $dateTime = gmdate("Y-m-d H:i:s");
        $dateTime = date(DATE_ISO8601, strtotime($dateTime));
        $dateTimeFinal = substr($dateTime, 0, 19) . "Z";

        $getUrl = Config::getBaseUrl($config['environment']);

        $targetPath = $params['targetPath'];
        $url = $getUrl . $targetPath;

        $header['Client-Id'] = $config['client_id'];
        $header['Request-Id'] = $requestId;
        $header['Request-Timestamp'] = $dateTimeFinal;
        $signature = Utils::generateSignature($header, $targetPath, json_encode($data), $config['shared_key']);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Signature:' . $signature,
            'Request-Id:' . $requestId,
            'Client-Id:' . $config['client_id'],
            'Request-Timestamp:' . $dateTimeFinal,
            'Request-Target:' . $targetPath,

        ));

        $responseJson = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (is_string($responseJson) && $httpcode == 200) {
            return json_decode($responseJson, true);
        } else {
            echo $responseJson;
            return null;
        }
    }

    public static function va($config, $params)
    {
        $header = array();
        $data = array(
            "order" => array(
                "invoice_number" => $params['invoiceNumber'],
            ),
            "virtual_account_info" => array(
                "expired_time" => $params['expiryTime'],
                "reusable_status" => $params['reusableStatus'],
                "info1" => $params['info1'],
                "info2" => $params['info2'],
                "info3" => $params['info3'],
            ),
            "customer" => array(
                "name" => trim($params['customerName']),
                "email" => $params['customerEmail']
            ),
            "additional_info" => array(
                "integration" => array(
                    "name" => "php-library",
                    "version" => "2.1.0"
                )
            )
        );

        if (isset($params['amount'])) {
            $data['order']["amount"] = $params['amount'];
        } else {
            $data['order']["min_amount"] = $params['min_amount'];
            $data['order']["max_amount"] = $params['min_amount'];
        }

        $requestId = rand(1, 100000);
        $dateTime = gmdate("Y-m-d H:i:s");
        $dateTime = date(DATE_ISO8601, strtotime($dateTime));
        $dateTimeFinal = substr($dateTime, 0, 19) . "Z";

        $getUrl = Config::getBaseUrl($config['environment']);

        $targetPath = $params['targetPath'];
        $url = $getUrl . $targetPath;

        $header['Client-Id'] = $config['client_id'];
        $header['Request-Id'] = $requestId;
        $header['Request-Timestamp'] = $dateTimeFinal;
        $signature = Utils::generateSignature($header, $targetPath, json_encode($data), $config['shared_key']);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Signature:' . $signature,
            'Request-Id:' . $requestId,
            'Client-Id:' . $config['client_id'],
            'Request-Timestamp:' . $dateTimeFinal,
            'Request-Target:' . $targetPath,

        ));

        $responseJson = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (is_string($responseJson) && $httpcode == 200) {
            return json_decode($responseJson, true);
        } else {
            echo $responseJson;
            return null;
        }
    }
}

class Utils
{
    public static function generateSignature($headers, $targetPath, $body, $secret)
    {
        $digest = base64_encode(hash('sha256', $body, true));
        $rawSignature = "Client-Id:" . $headers['Client-Id'] . "\n"
            . "Request-Id:" . $headers['Request-Id'] . "\n"
            . "Request-Timestamp:" . $headers['Request-Timestamp'] . "\n"
            . "Request-Target:" . $targetPath . "\n"
            . "Digest:" . $digest;

        $signature = base64_encode(hash_hmac('sha256', $rawSignature, $secret, true));
        return 'HMACSHA256=' . $signature;
    }
}

class Service
{

    public static function bcaVa($config, $params)
    {
        $params['targetPath'] = '/bca-virtual-account/v2/payment-code';
        return PaycodeGeneratorVa::post($config, $params);
    }

    public static function briVa($config, $params)
    {
        $params['targetPath'] = '/bri-virtual-account/v2/payment-code';
        return PaycodeGeneratorVa::post($config, $params);
    }

    public static function mandiriVa($config, $params)
    {
        $params['targetPath'] = '/mandiri-virtual-account/v2/payment-code';
        return PaycodeGeneratorVa::post($config, $params);
    }

    public static function bsiVa($config, $params)
    {
        $params['targetPath'] = '/bsm-virtual-account/v2/payment-code';
        return PaycodeGeneratorVa::post($config, $params);
    }

    public static function cc($config, $params)
    {
        $params['targetPath'] = '/credit-card/v1/payment-page';
        return PaycodeGeneratorCc::post($config, $params);
    }

    public static function dokuVa($config, $params)
    {
        $params['targetPath'] = "/doku-virtual-account/v2/payment-code";
        return PaycodeGeneratorVa::post($config, $params);
    }

    public static function dokuWallet($config, $params)
    {
        $params['targetPath'] = '/dokuwallet-emoney/v1/payment';
        return PaycodeGeneratorEmoney::post($config, $params);
    }

    public static function ovo($config, $params)
    {
        $params['targetPath'] = '/ovo-emoney/v1/payment';
        return PaycodeGeneratorEmoney::post($config, $params);
    }

    public static function shopeePay($config, $params)
    {
        $params['targetPath'] = '/shopeepay-emoney/v2/order';
        return PaycodeGeneratorEmoney::post($config, $params);
    }
}



