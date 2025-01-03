<?php
namespace Hillzacky\Bca;

use DOMDocument;
date_default_timezone_set('Asia/Jakarta');
define('BCA_PARSER_DEBUG', false);

class BCAParser {
	
	private $username;
	private $password;
 protected $hasilexec;
	protected $curlHandle;

	public $_defaultTargets = [
		'loginUrl' => 'https://m.klikbca.com/login.jsp',
		'loginAction' => 'https://m.klikbca.com/authentication.do',
		'logoutAction' => 'https://m.klikbca.com/authentication.do?value(actions)=logout',
		'cekSaldoUrl' => 'https://m.klikbca.com/balanceinquiry.do'
	];
	protected $isLoggedIn = false;
	
	protected $ipAddress;
	
	public $_defaultHeaders = array(
		'GET /login.jsp HTTP/1.1',
		'Host: m.klikbca.com',
		'Connection: keep-alive',
		'Cache-Control: max-age=0',
		'Upgrade-Insecure-Requests: 1',
		'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.76 Mobile Safari/537.36',
		'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
		'Accept-Encoding: gzip, deflate, sdch, br',
		'Accept-Language: en-US,en;q=0.8,id;q=0.6,fr;q=0.4'
	);
	
	/**
	* The Constructor
	* this class will make login request to BCA when initialized
	*
	* @param string $username
	* @param string $password
	*/
	public function __construct($username, $password)
	{
		if( BCA_PARSER_DEBUG == true ) error_reporting(E_ALL);
		$this->username = $username;
		$this->password = $password;
		$this->curlHandle = curl_init();
		$this->setupCurl();
		$this->login($this->username, $this->password);
	}
	
	/**
	* Get ip address, required on login parameters
	*
	* @return String;
	*/
	private function getIpAddress()
	{
		if($this->ipAddress !== null) $this->ipAddress = json_decode( file_get_contents( 'http://myjsonip.appspot.com/' ) )->ip;
		return $this->ipAddress;

	}
  
  	public function getHasilExec() {
    	return $this->hasilexec;
    }
	
	/**
	* Execute the CURL and return result
	*
	* @return curl result
	*/
	public function exec()
	{
		$result = curl_exec($this->curlHandle);
		if( BCA_PARSER_DEBUG == true ) {
			$http_code = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
			print_r($result);

			/**
			* Perlu diwapadai jangan melakukan pengecekan dengan interval waktu dibawah 10 menit ! 
			*/
			if($http_code == 302) {
				echo 'HALAMAN DIREDIRECT, harap tunggu beberapa menit ( biasanya 10 Menit! )';
				exit;
			}

		}
      	$this->hasilexec = $result;
		return $result;
	}
	
	/**
	* Register default CURL parameters
	*/
	protected function setupCurl()
	{
		curl_setopt( $this->curlHandle, CURLOPT_URL, $this->_defaultTargets['loginUrl'] );
		curl_setopt( $this->curlHandle, CURLOPT_POST, 0 );
		curl_setopt( $this->curlHandle, CURLOPT_HTTPGET, 1 );
		curl_setopt( $this->curlHandle, CURLOPT_HTTPHEADER, $this->_defaultHeaders);
		curl_setopt( $this->curlHandle, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $this->curlHandle, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $this->curlHandle, CURLOPT_COOKIEFILE,'cookie' );
		curl_setopt( $this->curlHandle, CURLOPT_COOKIEJAR, 'cookiejar' );
	}
	
	/**
	* Set request method on CURL to GET 
	*/
	protected function curlSetGet()
	{
		curl_setopt( $this->curlHandle, CURLOPT_POST, 0 );
		curl_setopt( $this->curlHandle, CURLOPT_HTTPGET, 1 );
	}
	
	/**
	* Set request method on CURL to POST 
	*/
	protected function curlSetPost()
	{
		curl_setopt( $this->curlHandle, CURLOPT_POST, 1 );
		curl_setopt( $this->curlHandle, CURLOPT_HTTPGET, 0 );
	}
	
	/**
	* Login to BCA
	*/
	private function login($username, $password)
	{
		//Just to Get Cookies
		curl_setopt( $this->curlHandle, CURLOPT_URL, $this->_defaultTargets['loginUrl'] );
		$this->curlSetGet();
		$this->exec();
		
		//Sending Login Info
		$this->getIpAddress();
		$params = array(
			"value(user_id)={$username}",
			"value(pswd)={$password}",
			'value(Submit)=LOGIN',
			'value(actions)=login',
			"value(user_ip)={$this->ipAddress}",
			"user_ip={$this->ipAddress}",
			'value(mobile)=true',
			'mobile=true'
		);
		$params = implode( '&', $params );
		$this->curlSetPost();
		curl_setopt( $this->curlHandle, CURLOPT_URL, $this->_defaultTargets['loginAction'] );
		curl_setopt( $this->curlHandle, CURLOPT_REFERER, $this->_defaultTargets['loginUrl'] );
		curl_setopt( $this->curlHandle, CURLOPT_POSTFIELDS, $params );
		$this->exec();
		$this->isLoggedIn = true;
	}

	/**
	 * Get saldo rekening pages
	 *
	 * @return string
	 */
	public function getSaldo()
	{
		if( !$this->isLoggedIn ) $this->login( $this->username, $this->password );
		
		$this->curlSetPost();
		
		curl_setopt( $this->curlHandle, CURLOPT_URL, 'https://m.klikbca.com/accountstmt.do?value(actions)=menu' );
		curl_setopt( $this->curlHandle, CURLOPT_REFERER, $this->_defaultTargets['loginAction'] );
		$this->exec();
 		curl_setopt( $this->curlHandle, CURLOPT_URL, $this->_defaultTargets['cekSaldoUrl'] );
		curl_setopt( $this->curlHandle, CURLOPT_REFERER, 'https://m.klikbca.com/accountstmt.do?value(actions)=menu' );
		$result = $this->exec();
 		$result = $this->getSaldoRekeningTable($result);
		$result = $this->getArrayValuesSaldo($result);
 		return $result;
	}
	
 	/**
	* Parse the pages on saldo rekening
	* this method will return only elements on <table> tag that contain only rekening and its saldo
	*
	* @param string $html
	* @return string
	*/
	private function getSaldoRekeningTable($html)
	{
		$dom = new DOMDocument();
	
		if ( BCA_PARSER_DEBUG ) {
			$dom->loadHTML($html);	
		} else {
			@$dom->loadHTML($html);	
		}
		
		$dom->getElementById('pagebody');
		
		$table = $dom->getElementsByTagName('table');
		$table = $table->item(3);
		return $dom->saveHTML($table);
	}

 	/**
	 * Get array value from data saldo page
	 * 
	 * @param  string $html
	 * @return array 
	 *  {
	 *     {'rekening'=>'norek1', 'saldo'=>'100.000'},
	 *     {'rekening'=>'norek2', 'saldo'=>'100.000'}
	 *  }
	 */
	private function getArrayValuesSaldo($html)
	{
		$dom = new DOMDocument();
		$dom->loadHTML($html);
		$table = $dom->getElementsByTagName('table');
		$rows = $dom->getElementsByTagName('tr');
 		$datas = [];
		for ($i = 0; $i < $rows->length; $i++) {
			if($i == 0) continue; // skip head
		    
		    $cols = $rows->item($i)->getElementsbyTagName("td");
 		    $rekening = $cols->item(0)->nodeValue;
		    $saldo = $cols->item(2)->nodeValue;
 		    $data = compact('rekening','saldo');
		    $datas[] = $data;
		}
		return $datas;
	}
	
	/**
	* Get mutasi rekening pages
	*
	* @param string $from 'Y-m-d'
	* @param string $to 'Y-m-d'
	* @return string
	*/
	public function getMutasiRekening($from, $to)
	{
		if( !$this->isLoggedIn ) $this->login( $this->username, $this->password );
		
		$this->curlSetPost();
		
		curl_setopt( $this->curlHandle, CURLOPT_URL, 'https://m.klikbca.com/accountstmt.do?value(actions)=menu' );
		curl_setopt( $this->curlHandle, CURLOPT_REFERER, $this->_defaultTargets['loginAction'] );
		$this->exec();

		curl_setopt( $this->curlHandle, CURLOPT_URL, 'https://m.klikbca.com/accountstmt.do?value(actions)=acct_stmt' );
		curl_setopt( $this->curlHandle, CURLOPT_REFERER, 'https://m.klikbca.com/accountstmt.do?value(actions)=menu' );
		$this->exec();
		
		$params = array( 
				'value(r1)=1', 
				'value(D1)=0', 
				'value(startDt)=' . date( 'd', strtotime($from) ), 
				'value(startMt)=' . date( 'm', strtotime($from) ), 
				'value(startYr)=' . date( 'Y', strtotime($from) ),
				'value(endDt)=' . date( 'd', strtotime($to) ),
				'value(endMt)=' . date( 'm', strtotime($to) ), 
				'value(endYr)=' . date( 'Y', strtotime($to) ) 
				);
		$params = implode( '&', $params );
		
		curl_setopt( $this->curlHandle, CURLOPT_URL, 'https://m.klikbca.com/accountstmt.do?value(actions)=acctstmtview' );
		curl_setopt( $this->curlHandle, CURLOPT_REFERER, 'https://m.klikbca.com/accountstmt.do?value(actions)=acct_stmt' );
		curl_setopt( $this->curlHandle, CURLOPT_POSTFIELDS, $params );
		$html = $this->exec();

		return $this->getMutasiRekeningTable($html);
	}
	
	/**
	* Parse the pages on mutasi rekening
	* this method will return only elements on <table> tag that contain only list of transaction
	*
	* @param string $html
	* @return string
	*/
	private function getMutasiRekeningTable($html)
	{
		$dom = new DOMDocument();
	
		if ( BCA_PARSER_DEBUG ) {
			$dom->loadHTML($html);	
		} else {
			@$dom->loadHTML($html);	
		}
		
		$dom->getElementById('pagebody');
		
		$table = $dom->getElementsByTagName('table');
		$table = $table->item(4);
		return $dom->saveHTML($table);
	}

	/**
	 * Get Array Values from an HTML <table> element
	 *
	 * @param string $html
	 * @return array
	 */
	private function getArrayValues($html)
	{
		$dom = new DOMDocument();
		$dom->loadHTML($html);
		$table = $dom->getElementsByTagName('table');
		$rows = $dom->getElementsByTagName('tr');

		$datas = [];
		for ($i = 0; $i < $rows->length; $i++) {
			if($i== 0 ) continue;
		    $cols = $rows->item($i)->getElementsbyTagName("td");

			// PEND menunjukkan transaksi telah berhasil dilakukan namun belum dibukukan oleh pihak BCA
			// https://twitter.com/HaloBCA/status/993661368724156416
		    $date = trim( $cols->item(0)->nodeValue );
			if ( $date != 'PEND' ) {
				$date = explode('/', $date);
				$date = date('Y') . '-' . $date[1] . '-' . $date[0];
			}
		    
		    $description = $cols->item(1);
		    $flows = trim( $cols->item(2)->nodeValue );
		    $descriptionText = $dom->saveHTML($description);

		    $descriptionText = str_replace('<td>', '', $descriptionText);
		    $descriptionText = str_replace('</td>', '', $descriptionText);
		    $description = explode('<br>', $descriptionText);
			
			// Trim array Values
			if ( is_array( $description ) ) {
				$description = array_map('trim', $description);
			}
			
		    $data = compact('date','description', 'flows');
		    $datas[] = $data;
		}
		
		return $datas;
	}


	/**
	* Ambil daftar transaksi pada janga waktu tertentu
	*
	*
	* @param string $from 'Y-m-d'
	* @param string $to 'Y-m-d'
	* @return array
	**/
	public function getListTransaksi($from, $to)
	{
		$result = $this->getMutasiRekening($from, $to);
		$result = $this->getArrayValues($result);
		return $result;
	}

	/**
	* getTransaksiCredit
	*
	* Ambil semua list transaksi credit (kas Masuk)
	*
	* @param string $from 'Y-m-d'
	* @param string $to 'Y-m-d'
	* @return array
	*/
	public function getTransaksiCredit($from, $to)
	{
		$result = $this->getListTransaksi($from, $to);
		$result = array_filter($result, function($row){
			return $row['flows'] == 'CR';
		});
		return $result;
	}

	/**
	* getTransaksiDebit
	*
	* Ambil semua list transaksi debit (kas Keluar)
	* Struktur data tidak konsisten !, tergantung dari jenis transaksi
	*
	* @param string $from 'Y-m-d'
	* @param string $to 'Y-m-d'
	* @return array
	*/
	public function getTransaksiDebit($from, $to)
	{
		$result = $this->getListTransaksi($from, $to);
		$result = array_filter($result, function($row){
			return $row['flows'] == 'DB';
		});
		return $result;
	}


	/**
	* Logout
	* 
	* Logout from KlikBca website
	* Lakukan logout setiap transaksi berakhir!
	*
	* @return string
	*/
	public function logout()
	{
		$this->curlSetGet();
		curl_setopt( $this->curlHandle, CURLOPT_URL, $this->_defaultTargets['logoutAction'] );
		curl_setopt( $this->curlHandle, CURLOPT_REFERER, $this->_defaultTargets['loginUrl'] );
		return $this->exec();
	}
  
  	public function getTransaksiCreditToday()
	{
		$result = $this->getListTransaksiToday();
		$result = array_filter($result, function($row){
			return $row['flows'] == 'CR';
		});
		return $result;
	}
  
  	public function getListTransaksiToday()
	{
		$result = $this->getMutasiRekeningToday();
		$result = $this->getArrayValues($result);
		return $result;
	}
  
  	public function getMutasiRekeningToday()
	{
		if( !$this->isLoggedIn ) $this->login( $this->username, $this->password );
		
		$this->curlSetPost();
		
		curl_setopt( $this->curlHandle, CURLOPT_URL, 'https://m.klikbca.com/accountstmt.do?value(actions)=menu' );
		curl_setopt( $this->curlHandle, CURLOPT_REFERER, $this->_defaultTargets['loginAction'] );
		$this->exec();

		curl_setopt( $this->curlHandle, CURLOPT_URL, 'https://m.klikbca.com/accountstmt.do?value(actions)=acct_stmt' );
		curl_setopt( $this->curlHandle, CURLOPT_REFERER, 'https://m.klikbca.com/accountstmt.do?value(actions)=menu' );
		$this->exec();
      
      	$jkt_time = time();
    	$t1 = explode('-', date('Y-m-d', $jkt_time));
    	$t0 = explode('-', date('Y-m-d', $jkt_time - (3600 * 6)));
		
		$params = array( 
				'value(r1)=1', 
				'value(D1)=0', 
				'value(startDt)=' . $t0[2], 
				'value(startMt)=' . $t0[1], 
				'value(startYr)=' . $t0[0],
				'value(endDt)=' . $t1[2],
				'value(endMt)=' . $t1[1], 
				'value(endYr)=' . $t1[0] 
				);
		$params = implode( '&', $params );
		
		curl_setopt( $this->curlHandle, CURLOPT_URL, 'https://m.klikbca.com/accountstmt.do?value(actions)=acctstmtview' );
		curl_setopt( $this->curlHandle, CURLOPT_REFERER, 'https://m.klikbca.com/accountstmt.do?value(actions)=acct_stmt' );
		curl_setopt( $this->curlHandle, CURLOPT_POSTFIELDS, $params );
		$html = $this->exec();

		return $this->getMutasiRekeningTable($html);
	}
  
  	public function fix_angka($string)
    {
        $string = str_replace(',', '', $string);
        $string = strtok($string, '.');
        return $string;
    }
  
  	public function check_bca($jumlah, $source){
      	echo "jumlah: ".$jumlah.", source: ". $source ."<br />";
      	foreach ($source as $value) {
          //$nilai = $this->fix_angka($value['description'][4]);
          
          foreach($value['description'] as $ivalue) {
          	$nilai = $this->fix_angka($ivalue);
            
            echo "jumlah: ".$jumlah.", nilai: ". $nilai ."<br />";
            if($nilai == $jumlah){
              $result = 'sukses';
              echo 'cocok';
            } else {
              echo 'tidak cocok';
            }
          }
        }
      
      	return $result;
    }

}

if (!function_exists('fix_angka'))
{
    function fix_angka($string)
    {
        $string = str_replace(',', '', $string);
        $string = strtok($string, '.');
        return $string;
    }
}
function grab_bca($user, $pass)
{
    $user_ip = '114.125.55.02';
    $ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36";
    $cookie = 'bca-cookie.txt';

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
   // curl_setopt($ch, CURLOPT_PROXY, '341yfg:hYUzA9@138.128.98.140:8000');
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, 'https://ibank.klikbca.com');
    $info = curl_exec($ch);

    $a = strstr($info, 'var s = document.createElement(\'script\'), attrs = { src: (window.location.protocol ==',
        1);
    $a = strstr($a, 'function getCurNum(){');

    $b = array(
        'return "',
        'function getCurNum(){',
        '";',
        '}',
        '{',
        '(function()',
        );

    $b = str_replace($b, '', $a);
    $curnum = trim($b);
    $params = 'value(actions)=login&value(user_id)=' . $user .
        '&value(CurNum)=' . $curnum . '&value(user_ip)=' . $user_ip .
        '&value(browser_info)=' . $ua . '&value(mobile)=true&value(pswd)=' .
        $pass . '&value(Submit)=LOGIN';
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, 'https://ibank.klikbca.com/authentication.do');
    curl_setopt($ch, CURLOPT_REFERER, 'https://ibank.klikbca.com');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_POST, 1);
    echo $info = curl_exec($ch);

    // Buka menu
    curl_setopt($ch, CURLOPT_URL,
        'https://ibank.klikbca.com/nav_bar_indo/menu_bar.htm');
    curl_setopt($ch, CURLOPT_REFERER, 'https://ibank.klikbca.com/authentication.do');
    $info = curl_exec($ch);

    // Buka Informasi Rekening
    curl_setopt($ch, CURLOPT_URL,
        'https://ibank.klikbca.com/nav_bar_indo/account_information_menu.htm');
    curl_setopt($ch, CURLOPT_REFERER, 'https://ibank.klikbca.com/authentication.do');
    $info = curl_exec($ch);

    // Buka Mutasi Rekening
    curl_setopt($ch, CURLOPT_URL,
        'https://ibank.klikbca.com/accountstmt.do?value(actions)=acct_stmt');
    curl_setopt($ch, CURLOPT_REFERER,
        'https://ibank.klikbca.com/nav_bar_indo/account_information_menu.htm');
    curl_setopt($ch, CURLOPT_POST, 1);
    $info = curl_exec($ch);

    // Parameter untuk Lihat Mutasi Rekening
    $params = array();
    $jkt_time = time();
    $t1 = explode('-', date('Y-m-d', $jkt_time));
    $t0 = explode('-', date('Y-m-d', $jkt_time - (3600 * 6)));

    $params[] = 'value(startDt)=' . $t0[2];
    $params[] = 'value(startMt)=' . $t0[1];
    $params[] = 'value(startYr)=' . $t0[0];
    $params[] = 'value(endDt)=' . $t1[2];
    $params[] = 'value(endMt)=' . $t1[1];
    $params[] = 'value(endYr)=' . $t1[0];
    $params[] = 'value(D1)=0';
    $params[] = 'value(r1)=1';
    $params[] = 'value(fDt)=';
    $params[] = 'value(tDt)=';
    $params[] = 'value(submit1)=Lihat+Mutasi+Rekening';
     //$params[] = 'value(status)=success';
    

    $params = implode('&', $params);

    // Buka Lihat Mutasi Rekening & simpan hasilnya di $source
   // curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    // curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL,
        'https://ibank.klikbca.com/accountstmt.do?value(actions)=acctstmtview');
    curl_setopt($ch, CURLOPT_REFERER,
        'https://ibank.klikbca.com/nav_bar_indo/account_information_menu.htm');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_POST, 1);

    $source = curl_exec($ch);
    
    /*
    
    // Buka Lihat Mutasi Rekening & simpan hasilnya di $source
     $params = "value(D1)=0&value(r1)=1&value(startDt)=".$t0[2]."&value(startMt)=".$t0[1]."&value(startYr)=".$t0[0]."&value(endDt)=".$t1[2]."&value(endMt)=".$t1[1]."&value(endYr)=".$t1[0]."&value(fDt)=&value(tDt)=&value(requestDate)=".$t0[2].$t0[1].$t0[0]."&value(isMonthly)=0&value(acct_no)=8525240149&value(from_date)=".$t0[2].$t0[1].$t0[0]."&value(to_date)=".$t0[2].$t0[1].$t0[0]."&value(inputEndDt)=".$t0[2].$t0[1].$t0[0]."&value(status)=SUCCESS&value(directDownload)=0";
     curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
     curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL,
        'https://ibank.klikbca.com/accountstmt.do?value(actions)=acctstmt_req');
    curl_setopt($ch, CURLOPT_REFERER,
        'https://ibank.klikbca.com/accountstmt.do?value(actions)=acctstmtview');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_POST, 1);
    
    

    $source = curl_exec($ch);
    
     // Buka Lihat Mutasi Rekening & simpan hasilnya di $source
     $params = "value(D1)=0&value(r1)=1&value(startDt)=".$t0[2]."&value(startMt)=".$t0[1]."&value(startYr)=".$t0[0]."&value(endDt)=".$t1[2]."&value(endMt)=".$t1[1]."&value(endYr)=".$t1[0]."&value(fDt)=&value(tDt)=&value(refNo)=201911191046053520&value(requestDate)=".$t0[2].$t0[1].$t0[0]."&value(isMonthly)=0&value(acct_no)=8525240149&value(from_date)=".$t0[2].$t0[1].$t0[0]."&value(to_date)=".$t0[2].$t0[1].$t0[0]."&value(inputEndDt)=".$t0[2].$t0[1].$t0[0]."&value(status)=SUCCESS&value(directDownload)=0";
     curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
     curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL,
        'https://ibank.klikbca.com/accountstmt.do?value(actions)=acctstmt_async');
    curl_setopt($ch, CURLOPT_REFERER,
        'https://ibank.klikbca.com/accountstmt.do?value(actions)=acctstmtview');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_POST, 1);
    
    

   // $source = curl_exec($ch);
    
    */
    
    

    // Logout, cURL close, hapus cookies
    curl_setopt($ch, CURLOPT_URL,
        'https://ibank.klikbca.com/authentication.do?value(actions)=logout');
    curl_setopt($ch, CURLOPT_REFERER,
        'https://ibank.klikbca.com/nav_bar_indo/account_information_menu.htm');
    $info = curl_exec($ch);
    curl_close($ch);
    @unlink($cookie);
    return $source;
}

    function check_bca($jumlah, $source){
    global $pdo;
    $user = ""; // USERID 
    $pass = ""; // PASS  
    //echo $source = grab_bca($user, $pass);
    $exp = explode('<b>Saldo</b></font></div></td>', $source);
    $invoices = array();
    $lunas = array();
    $jkt_time = time() + (3600 * 7);
    $tahun = date('Y', $jkt_time);
    if (isset($exp[1]))
    {
        $table = explode("</table>", $exp[1]);
        $tr = explode("<tr>", $table[0]);
        for ($i = 1; $i < count($tr); $i++)
        {
            $str = str_ireplace('</font>', '#~#~#</font>', $tr[$i]);
            $str = str_ireplace('<br>', '<br> ', $str);
            $str = preg_replace('!\s+!', ' ', trim(strip_tags($str)));
            $arr = array_map('trim', explode("#~#~#", $str));
            $tgl = $arr[0] . '/' . $tahun;
            $keterangan = $arr[1];
            $kredit = fix_angka($arr[3]);
            $status = $arr[4];
            if($kredit == $jumlah){
            $result = 'sukses';
            }
        }
        return $result;
    }
    }

?>