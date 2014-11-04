<?php
include("simple_html_dom.php");
function &get_option($url)
{
	$opt = array(
		CURLOPT_URL => "$url"
		,CURLOPT_VERBOSE => false
		,CURLOPT_COOKIEJAR => "cookie.txt"
		,CURLOPT_COOKIEFILE => "cookie.txt"
		,CURLOPT_RETURNTRANSFER => true
		,CURLOPT_FOLLOWLOCATION => true
		,CURLOPT_HTTPHEADER => array(
			"accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8"
			//,"accept-encoding:gzip,deflate,sdch"
			,"accept-language:zh-TW,zh;q=0.8,en-US;q=0.6,en;q=0.4"
			,"user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.104 Safari/537.36"
		)
	);
	return $opt;
}
//PREF	ID=5d58fc5b7b3299ca:U=ac2ee1563dfbeeec:FF=0:LD=zh-TW:TM=1411060727:LM=1415081528:GBV=1:S=YWGoPkhPtc--2kwZ	
function &get_search($key, $page=1)
{
	$key = str_replace(" ", "+", $key);
	$start = ($page-1)*10;
	$url = "https://www.google.com.tw/search?q=$key&gbv=1&start=$start";
	//$url = "https://www.google.com.tw/webhp?sourceid=chrome-instant&ion=1&espv=2&ie=UTF-8#q=$key&start=$start";
	//$url = "http://www.google.com.tw/search?hl=zh-TW&source=hp&q=$key&btnG=Google+%E6%90%9C%E5%B0%8B&gbv=1&start=$start";
	echo "$url\n";
	$ch = curl_init();
	curl_setopt_array($ch, get_option($url));
	$html = curl_exec($ch);
	curl_close($ch);
	file_put_contents("google.html", $html);
	
	while(strstr($html, "如要繼續，請輸入以下字元"))
	{
		echo "Need input number\n";
		image_handler($html, $url);
	}
	file_put_contents("google.html", $html);
	return $html;
}
function image_handler(&$html_text, &$url)
{
	
	$html = str_get_html($html_text);
	$img_src = $html->find("img", 0)->src;
	$jpg = get_url("https://ipv4.google.com$img_src");
	//echo "https://ipv4.google.com$img_src\n";
	file_put_contents("google.jpeg", $jpg);
	$jpg = "";
	$number = 0;
	$get = array();
	//https://ipv4.google.com/sorry/image?id=17275262888834956797&hl=zh-TW
	//CaptchaRedirect?continue=https%3A%2F%2Fwww.google.com.tw%2Fsearch%3Fq%3DSmall%2BRNA%2BPipeline%26gbv%3D1%26start%3D110%26sei%3DO4BYVPLrN5Df8AWXg4LwDg&id=3344572785930807144&captcha=34243&submit=提交
	//CaptchaRedirect
	foreach( $html->find("input") as $input)
		$get[ $input->name ] = $input->value;
	//print_r($get);
	$number = cin_line();
	//unlink("google.jpeg");
	$get["captcha"] = $number;
	$new_url = "";
	
	foreach($get as $key=>$value)
	{
		$new_url .= "$key=". urlencode($value) ."&";
	}
	$new_url = "https://ipv4.google.com/sorry/CaptchaRedirect?$new_url";
	
	//echo $new_url;
	$html_text = get_url($new_url);
}
function cin_line()
{
	$handle = fopen ("php://stdin","r");
	$text = fgets($handle);
	fclose($handle);
	return trim($text);
}
function get_google()
{
	$url = "https://www.google.com.tw";
	$ch = curl_init();
	curl_setopt_array($ch, get_option($url));
	$html = curl_exec($ch);
	curl_close($ch);
	file_put_contents("google.html", $html);
}
function get_url($url)
{
	$ch = curl_init();
	curl_setopt_array($ch, get_option($url));
	$html = curl_exec($ch);
	curl_close($ch);
	return $html;
}
function find_keyword($key, $find, $page_f=1, $page_t=20)
{
	for($i=$page_f; $i<=$page_t;$i++)
	{
		$html = get_search($key, $i);
		if(strstr($html, $find))
		{
			echo "Find '$key' at $i page\n";
			break;
		}	
		//sleep(1);
	}
}

//get_google();
find_keyword($argv[1], $argv[2], 1, 30);


?>
