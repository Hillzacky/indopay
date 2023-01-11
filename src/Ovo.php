<?php
namespace Hillzacky\Ovo;

class Ovo {
  static function post($url,$data,$type=true){
    $ch = curl_init();
    $headers = [
      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Accept-Encoding: gzip, deflate',
      'Accept-Language: en-US,en;q=0.5',
      'Cache-Control: no-cache',
      'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
      'Host: ' . Ovo::$host,
      'Referer: https://' . Ovo::$host,
      'User-Agent: ' . Ovo::$ua,
      'X-MicrosoftAjax: Delta=true'
    ];
    $options = [
      CURLOPT_URL => $url,
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => $data,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_HTTPHEADER => $headers
    ];
    curl_setopt_array($ch, $options);
    $res = curl_exec ($ch);
    curl_close ($ch);
    return json_decode($res,$type)['data'];
  }
}