<?php 
require_once(dirname(__FILE__).'/curl_multi.php');
require_once(dirname(__FILE__).'/config.php');
require_once(dirname(__FILE__).'/ua.php');
require_once(dirname(__FILE__).'/mysqls.php');

$agent_class = new Agent();
$curl_class = new Curl($agent_class);
$sql_class = new Mysqlis();

/**proxy IP 
* @param url your proxy_url
*return json 
**/
$proxy_url = 'http://112.124.117.191/workerman/get_proxy.php?count=50';
$proxys = json_decode($curl_class->request($proxy_url), true); //json2Array
/**random proxy IP index
*
*return a num
**/
function random_proxy(){
	global $proxy_url;
	global $proxys;
	global $curl_class;

	$count = count($proxys);
	if ($count > 1) {
		return  mt_rand(0, ($count-1)) ;
	}else{
		$proxys = json_decode($curl_class->request($proxy_url), true);
		random_proxy();
	}
}

$start_time = microtime(true)*1000;

$keyword = urlencode('耳机'); //config

$url = 'http://search.jd.com/Search?keyword='.$keyword.'&enc=utf-8';
$html = getIndex($url); //the index
//$html = file_get_contents('jd.html');
if ($html) {
	echo 'getIndex__OK!'.PHP_EOL;
}else{
	echo 'getIndex__fail'.PHP_EOL;
	exit();
}

$brand = getBrand($html);//get brand page
//$brand = file_get_contents('brand.html');
if ($brand) {
	echo 'getBrand__OK';
}else{
	echo 'getBrand__fail';
	exit();
}

$len = getBrandURL($brand); //get all brand url
if ($len) {
	echo 'countBrand__OK:'.$len.PHP_EOL;
}else{
	echo 'countBrand__fail'.PHP_EOL;
	exit();
}

$detail = getDetail(); //
if ($detail) {
	echo 'detail__OK:'.PHP_EOL;
}else{
	echo 'detail__fail'.PHP_EOL;
	exit();
}

getPageURL($detail);

$end_time = microtime(true)*1000;
echo 'Time:'.($end_time-$start_time).PHP_EOL;


function getContent($page_url, $tag){
	global $sql_class;
	global $curl_class;
	global $proxys;
	$index = random_proxy();
	
	
	$html = $curl_class->request($page_url, $proxys[$index]);
	if (!$html) {
		array_splice($proxys, $index, 1); 
		getContent($page_url, $tag);
		return;
		
	}

	//$html = file_get_contents('detail.html');
	//file_put_contents('detail.html', $html);
	preg_match_all('#data-price="(.*?)"[\s\S]*?title="(.*?)" href="(.*?)"[\s]*?onclick="searchlog#', $html, $out);
	if (!$out) {
		getContent($page_url, $tag);
		return;
	}
	$len = count($out[0]);
	echo 'countPageURL:'.$len.PHP_EOL;

	for ($i=0; $i < $len; $i++) {
		$sql = "insert into detail(name, price, detailurl, md5url, tag) values('".trim($out[2][$i])."','".$out[1][$i]."', '".$out[3][$i]."', '".md5($out[3][$i])."', ".$tag.")";
		//var_dump($sql);exit();
		$res = $sql_class->insertContent(md5($out[3][$i]), 'detail', $sql);
		if (!$res) {
			file_put_contents('err.log', 'PageURL_'.$tag.'_'.$page_url.PHP_EOL, FILE_APPEND);
			
		}

	}
	echo 'one_page_url'.PHP_EOL;
	return $len;
}




function getPageURL($detail){
	$page_goods_count = ceil($detail[2]/$detail[1]);
	$page = 1;
	$real_page = 1;
	$s = 1;
	$count = intval($page_goods_count);
	for ($i=0; $i < $count; $i++) { 
		$url = 'http://search.jd.com/s_new.php?keyword='.$detail[0][1].'&enc=utf-8&qrst=1&rt=1&stop=1&vt=2&sttr=1&offset=1&ev='.$detail[0][3].'&page='.$real_page.'&s='.$s.'&click=0';
		//echo 'page_url:'.$url.PHP_EOL;
		$len = getContent($url, $detail[3]);
		$page++;
		$real_page = 2*$page-1;
		$s += 2*$len;

		/**
		*
		*
		**/
		//$real_page = $page;
		//$s += $len;
		
	}
	echo 'page_url_end'.PHP_EOL;
}

function getDetail(){
	global $sql_class;
	$sql = "select * from brand where status = 0";
	$res = $sql_class->querys($sql);
	
	foreach ($res as $key => $value) {
		if ($value['id']) {
			$err_count = 0;
			
			detailParse($value['id'], $err_count);
		}else{
			echo $key.'_'.json_encode($value).PHP_EOL;
		}
	}
}

function detailParse($id, &$err_count){
	global $sql_class;
	global $curl_class;
	global $proxys;

	if ($err_count > 3) {
		file_put_contents('err.log', 'detail__'.$id.PHP_EOL, FILE_APPEND);
		detailParse($id+1, $err_count=0);
	}

	$sql = "select * from brand where status = 0 and id=".$id;
	$res = $sql_class->querys($sql);
	$url = $res[0]['url'];
	
	preg_match('#keyword=(.*?)&enc=utf-8&.*?&offset=(.*?)&ev=(.*?)&uc=0#', $url, $out);
	//$url = explode('#', $url);
	
	$url_full = 'http://search.jd.com/'.$url;
	$index = random_proxy();

	$html = $curl_class->request($url_full, $proxys[$index]);
	//$html = file_get_contents('detail.html');
	if ($html) {
		//file_put_contents('detail.html', $html);
		preg_match('#<b>\d<\/b>[\s]*?<em>\/<\/em>[\s]*?<i>(.*?)<\/i>#', $html, $num);
		if (!$num) {
			$err_count +=1;
			detailParse($id, $err_count);
			return;
		}
		$page_num = $num[1];
		preg_match('#<span id="J_resCount" class="num">(.*?)<\/span>#', $html, $count);
		if (!$count) {
			$err_count +=1;
			detailParse($id, $err_count);
			return;
		}
		$goods_num = $count[1];
		$detail_res = array($out,$page_num,$goods_num, $res[0]['id']);
		file_put_contents('log',  $res[0]['title'].'goodsnum__'.$goods_num.PHP_EOL, FILE_APPEND);
		getPageURL($detail_res);
	}else{
		$err_count +=1;
		array_splice($proxys, $index, 1); 
		detailParse($id, $err_count);
		return;	
	}
}

function getBrandURL($brand){
	global $sql_class;

	preg_match_all('#<a href="(.*?)" rel="nofollow" onclick=".*?" title="(.*?)">#', $brand, $out);
	$len = count($out[0]);
	echo 'countBrand:'.$len.PHP_EOL;

	for ($i=0; $i < $len; $i++) {
		$sql = "insert into brand(title, url) values('".trim($out[2][$i])."', '".$out[1][$i]."')";
		$res = $sql_class->insert($sql);
		if (!$res) {
			file_put_contents('err.log', 'brand_'.$out[1][$i].PHP_EOL, FILE_APPEND);
		}
	}
	//file_put_contents('log',  'brand_'.$len.PHP_EOL, FILE_APPEND);
	return $len;


}

function getBrand($html){
	global $curl_class;
	global $keyword;
	global $proxys;
	$index = random_proxy();

	preg_match('#data-url="brand.php\?(.*?)">#', $html, $out);
	$url_full = 'http://search.jd.com/brand.php?'.$out[1].'wq='.$keyword;
	echo 'getBrandURL:'.$url_full.PHP_EOL;
	$url_param_arr = explode('&', $out[1]);
	$param_arr = array();

	foreach ($url_param_arr as $key => $value) {
		$params = explode('=', $value);
		$param_arr[$params[0]] = $params[1];
	}

	$wq = array('wq'=>$keyword);
	$filed = array_merge($param_arr, $wq);
	//always no brand.you need write pvid to here.
	$f12_pvid = '&pvid=9u904ysi.qyzap3'; //config
	if (empty($f12_pvid)) {
		$pvid = '';
	}else{
		$pvid = $f12_pvid;
	}
	$referer = 'http://search.jd.com/Search?keyword='.$filed['keyword'].'&enc='.$filed['enc'].'&wq='.$filed['keyword'].$pvid;

	$res = $curl_class->request($url_full, $proxys[$index], 'POST', $filed, $referer);

	if ($res) {
		//file_put_contents('brand.html', $res);
		return $res;
	}else{		
		return false;
	}

}


function getIndex($url){
	global $curl_class;

	$html = $curl_class->request($url);
	if ($html) {
		//file_put_contents('jd.html', $html);
		return $html;
	}else{		
		return false;
	}
}
?>