<?php
namespace Hillzacky\Paydia;

class Paydia {
	static $development = 'https://api.paydia.co.id';
	static $production = 'https://api.paydia.id';
	static $status = true; // production = true || development = false
	static $host = 'www.paydia.id';
	static $ua = 'Mozilla/5.0 (x64) AppleWebKit/537.36 (KHTML, like Gecko) Paydia/537.36';

	static function host(){
		return (Paydia::$status!=false) ? Paydia::$production : Paydia::$development;
	}

	static function auth($cid,$sk,$mid){
		$token = base64_encode('$cid:$sk:$mid');
		return [
			"Accept: application/json"
			"Content-Type: application/json"
			"Authorization: Bearer " . $token
		];
	}

  static function post($url,$data,$type=true){
    $ch = curl_init();
    $headers = [
      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Accept-Encoding: gzip, deflate',
      'Accept-Language: en-US,en;q=0.5',
      'Cache-Control: no-cache',
      'Content-Type: application/json; charset=utf-8',
      'Host: ' . Paydia::$host,
      'Referer: https://' . Paydia::$host,
      'User-Agent: ' . Paydia::$ua,
      'X-MicrosoftAjax: Delta=true'
    ];
    $options = [
      CURLOPT_URL => $url,
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => json_encode($data),
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_HTTPHEADER => $headers
    ];
    curl_setopt_array($ch, $options);
    $res = curl_exec ($ch);
    curl_close ($ch);
    return json_decode($res,$type);
  }

  static function qrisGen($mid,$v,$tip,$ref,$cb,$exp=5){
  	$ep = Paydia::host() . '/qris/generate/';
  	return Paydia::post($ep,[
  		"merchantid" => $mid,
		  "nominal" => $v,
		  "tip" => $tip,
		  "ref" => $ref,
		  "callback" => $cb,
		  "expire" => $exp
  	]);
  }

  static function qrisStatus($refid){
  	$ep = Paydia::host() . '/qris/check-status/';
  	return Paydia::post($ep,[
		  "refid" => $refid,
  	]);
  }

  static function qrisCallback($refid){
  	$ep = Paydia::host() . '/qris/callback';
  	return Paydia::post($ep,[
		  "merchant_trxid" => $mid,
		  "refid" => $refid,
		  "ref" => $ref,
		  "nominal" => $v,
		  "tip" => $tip,
		  "status" => "success",
		  "signature" => $sign,
		  "trx_date" => "2022-02-14 12:37:30",
		  "customer_data" => "Liesabeth Edwin 22",
		  "customer_pan" => "936001290000000026",
		  "merchant_pan" => "936001290220203001",
		  "acquirer_id" => "93600129",
		  "issuer_name" => "Paydia",
		  "mdr" => "70",
		  "rrn" => "220401000001",
		  "layanan" => 0,
		  "nominal_paid" => "10000",
		  "total_paid" => "10000",
		  "total_receive" => "9930"
  	]);
  }

  static function qrisRefund($refid,$v,$cb){
  	$ep = Paydia::host() . '/qris/refund';
  	return Paydia::post($ep,[
		  "refid" => $refid,
		  "nominal" => $v,
		  "callback" => $cb
  	]);
  }
}