<?php 

class Curl {
	public static $agent;
	public function __construct($ua){
		self::$agent = $ua;
	}
	
	public static function request($url, $proxy='', $method='get', $fields = array(), $referer=''){
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_USERAGENT, self::$agent->getOneAgent());
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		if ($referer) {
			curl_setopt ($ch,CURLOPT_REFERER, $referer);
		}
		if ($proxy) {
			curl_setopt($ch, CURLOPT_PROXY, $proxy);
		}
		
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		if ($method === 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, true );
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		}
		$result = curl_exec($ch);
		return $result;
		curl_close($ch);
	}

	public static function getMultiRequest($url_arr,$proxy){
		if (!is_array($url_arr)) {
	        $temp[] = $url_arr;
	        $url_arr = $temp;
	    }
	    $handle = array();
	    $data    = array();
	    $mh = curl_multi_init();
	    $i = 0;
	    //$url_handle = [];
	    foreach ($url_arr as $url) {
	            $ch = curl_init();
	            curl_setopt($ch, CURLOPT_URL, $url);
	            curl_setopt($ch, CURLOPT_HEADER, 0);
	            //curl_setopt($ch, CURLOPT_PROXY, $proxy);
	            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return don't print
	            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
	            curl_setopt($ch, CURLOPT_USERAGENT, self::$agent->getOneAgent());
	            curl_multi_add_handle($mh, $ch); 
	            $handle[$i++] = $ch;
	            //$url_handle[$ch] = $url;       
	        }
	    $active = null;
	    do {
	        $mrc = curl_multi_exec($mh, $active);
	    } while ($mrc == CURLM_CALL_MULTI_PERFORM);


	    while ($active and $mrc == CURLM_OK) {

	        if(curl_multi_select($mh) === -1){
	            usleep(100);
	        }
	        do {
	            $mrc = curl_multi_exec($mh, $active);
	        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

	    }

	    foreach($handle as $j => $ch) {
	        $content  = curl_multi_getcontent($ch);
	        if (curl_errno($ch) == 0) {
	            $data[$j] = $content;
	        }
	        
	    }

	    foreach ($handle as $ch) {
	        curl_multi_remove_handle($mh, $ch);
	    }

	    curl_multi_close($mh);
	    //var_dump($data);
	    return $data;//返回抓取到的内同
		}
}

 ?>