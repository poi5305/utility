<?php
include_once("simple_html_dom.php");


class curl_utility
{
	var $cookie_file_path = "cookie.txt";
	var $is_save = true;
	var $save_path = "tmp_saved";
	
	function curl_utility()
	{
		@mkdir($this->save_path, 0755, true);
	}
	function get_page($url)
	{
		$url_md5 = md5($url);
		if(is_file( "{$this->save_path}/{$url_md5}" ))
		{
			//echo "Get url $url ...file exists...\n";
			return file_get_contents("{$this->save_path}/{$url_md5}");
		}
		else
		{
			echo "Get url $url ...\n";
			$result = $this->curl_page( array( CURLOPT_URL => $url) );
			if($this->is_save)
			{
				file_put_contents("{$this->save_path}/{$url_md5}", $result);
			}
			return $result;
		}
	}
	function curl_page($opts)
	{
		$ch = curl_init();
		$opts[CURLOPT_COOKIEJAR] = $this->cookie_file_path;
		$opts[CURLOPT_COOKIEFILE] = $this->cookie_file_path;
		$opts[CURLOPT_RETURNTRANSFER] = 1;
		$opts[CURLOPT_FOLLOWLOCATION] = true;
		curl_setopt_array($ch, $opts);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
}


class web_spider
{
	var $curl = NULL;
	var $base_url = NULL;
	var $parsed_base_url = NULL;
	var $level = 1;
	var $md5_url_mapping = array();
	var $spider_ptree = array();
	var $spider_tree = array();
	
	var $tag_array = array("a"=>"href", "link"=>"href");
	//  
	function web_spider($url, $level = 2)
	{
		$this->curl = new curl_utility();
		$this->base_url = $url;
		$this->parsed_base_url = parse_url($url);
		$this->level = $level;
		//$this->spider_tree = $this->spider($url, 0);
		//print_r($this->spider_ptree);
		//print_r($this->spider_tree);
		
	}
	
	function spider($source_url, $level = 0)
	{
		$source_md5 = md5($source_url);
		if($level == $this->level)
			return array("level" => $level, "md5"=>$source_md5, "child" => NULL );
		
		$page_urls = array();
		if(!isset($this->spider_ptree[$source_md5]))
		{
			$page_urls = $this->get_page_url($source_url);
			$this->spider_ptree[md5($source_url)] = array(
				 "url" => $source_url
				,"level" => $level
				,"child" => $page_urls
			);		
		}
		$results = array(
			"level" => $level
			,"md5" => $source_md5
			,"child" => array()
		);
		
		foreach($this->spider_ptree[$source_md5]["child"] as $md5 => $content)
		{
			$results["child"][] = $this->spider($this->md5_url_mapping[$md5], $level+1);
		}
		return $results;
	}
	
	function full_url($url, $limit_host = NULL ,$source = NULL)
	{	
		$url = htmlspecialchars_decode($url);
		$parsed_url = parse_url($url);
		// limit hosts
		if(isset($parsed_url["host"]) && $parsed_url["host"] != $limit_host && $limit_host != NULL)
			return "";
		
		if(isset($parsed_url["scheme"]))
		{
			if($parsed_url["scheme"] == "javascript")
				return NULL;
			else
				return $url;
		}
		if(isset($parsed_url["host"]))
			return "http:" . $url;
		
		if($source == NULL)
			$source = $this->base_url;
		$parsed_source = parse_url($source);
		
		// #xxxx
		if(!isset($parsed_url["path"]))
		{
			return "";
			//return $source.$url;
		}
		if(substr($parsed_url["path"], 0, 1) == "/")
		{
			return "{$parsed_source['scheme']}://{$parsed_source['host']}{$url}";
		}
		else
		{
			$tmp = explode("/", $parsed_source['path']);
			$tmp[count($tmp)-1] = $url;
			$url = implode("/", $tmp);
			return "{$parsed_source['scheme']}://{$parsed_source['host']}{$url}";
		}
	}
	function get_page_url($url)
	{
		//$tag = "a";
		//$tag_attr = $this->tag_array[$tag];
		$result = array();
		$html = str_get_html( $this->curl->get_page($this->full_url($url) ) );
		if(!is_object($html))
			return $result;
		$i=0;
		foreach($this->tag_array as $tag => $tag_attr)
		{
			foreach( $html->find($tag) as $tag_a )
			{
				$full_url = $this->full_url($tag_a->$tag_attr, $this->parsed_base_url["host"], $url);
				if($full_url == "")
					continue;
				$url_md5 = md5($full_url);
				$result[$url_md5] = true;
				$this->md5_url_mapping[$url_md5] = $full_url;
				$i++;
				//if($i > 100)
				//	break;
			}
		}
		
		return $result;
	}
}

class web_downloader extends web_spider
{
	var $download_path = "download";
	var $tmp_download_path = "tmp_saved";
	
	function web_downloader($url, $level)
	{
		$this->is_save = true;
		$this->save_path = $this->tmp_download_path;
		$this->web_spider($url, $level);

		$this->download_path .= "/" . $this->parsed_base_url["host"];
		@mkdir($this->download_path, 0755, true);
		$this->spider_tree = $this->spider($url, 0);
		//print_r($this->spider_ptree);
		//print_r($this->spider_tree);
		echo "memory ". memory_get_usage() . "\n";
		$this->download($this->spider_tree);
	}
	function download($page)
	{	
		$level = $page["level"];
		$md5 = $page["md5"];
		
		if(isset($this->spider_ptree[$md5]))
		{
			$ptree = $this->spider_ptree[$md5];
			$this->download_impl($md5);
		}
		
		if($page["child"] == NULL)
			return;
		foreach($page["child"] as $child_page)
		{
			$this->download($child_page);
		}
	}
	function download_impl($md5)
	{
		$ptree = $this->spider_ptree[$md5];
		if(isset($ptree["saved"]))
			return;
		
		$url = $ptree["url"];
		if(strstr($url, "http://www.php.net/manual/vote-note.php?id=106731&page=function.parse-url&vote=up"))
		{
			echo $url."\n";
		}
		$html = str_get_html( $this->curl->get_page($url) );
		if(!is_object($html))
			return $result;
		foreach($html->find("script") as $item)
			$item->outertext = "";
		
		foreach($html->find("base") as $item)
			$item->outertext = "";
		
		foreach($this->tag_array as $tag => $tag_attr)
		{
			foreach( $html->find($tag) as $tag_a )
			{
				$child_full_url = $this->full_url($tag_a->$tag_attr, $this->parsed_base_url["host"], $url);
				$child_url_md5 = md5($child_full_url);
				if( isset($this->spider_ptree[$child_url_md5]) )
				{
					$tag_a->$tag_attr = $this->get_relative_path($url, $child_full_url);	
				}
				else
				{
					if($tag == "link")
					{
						
					}
					else
					{
						$tag_a->$tag_attr = "#";
					}
						
				}
			}
		}
		// this page setting
		$new_file_name = $this->url2filename($url, true);
		$html->save($new_file_name);
		$this->spider_ptree[$md5]["saved"] = true;
	}
	function get_sub_filename($url)
	{
		$tmp = explode(".", $url);
		return $tmp[count($tmp)-1];
	}
	function get_relative_path($ori_url, $go_url)
	{
		$ori_parsed_url = parse_url($ori_url);
		$go_parsed_url = parse_url($go_url);

		if($ori_parsed_url["host"] != $go_parsed_url["host"])
			return $go_url;
		
		//$magic = "e4jnkrdfodk0m";
		//if(strstr($ori_parsed_url["path"], $go_parsed_url["path"]))
		//{
		//	$relative_path = $this->getRelativePath($ori_parsed_url["path"], $go_parsed_url["path"].$magic);
		//}
		//elseif(strstr($go_parsed_url["path"], $ori_parsed_url["path"]))
		//{
		//	$relative_path = $this->getRelativePath($ori_parsed_url["path"].$magic, $go_parsed_url["path"]);
		//}
		//else
		//{
		//	$relative_path = $this->getRelativePath($ori_parsed_url["path"], $go_parsed_url["path"]);
		//}
		//$relative_path = str_replace($magic, "", $relative_path);
		
		$relative_path = $this->get_relative_path_impl($ori_parsed_url["path"], $go_parsed_url["path"]);
		
		if(isset($go_parsed_url["query"]))
			$relative_path .= "_" .htmlspecialchars_decode($go_parsed_url["query"]);
		if(isset($go_parsed_url["fragment"]))
			$relative_path .= "#".$go_parsed_url["fragment"];

		//for http://server/?key=value
		if($relative_path == "" || $relative_path == "../")
			$relative_path = $relative_path . basename($go_parsed_url["path"]);
		
		//for http://server/
		if(str_replace("./", "", $relative_path) == "")
			return "{$relative_path}index.html";
		if(str_replace("../", "", $relative_path) == "")
			return "{$relative_path}index.html";
			
		return $relative_path.".html";
			
	}
	function url2filename($url, $is_mkdir = false)
	{
		$parsed_url = parse_url($url);
		if($parsed_url["path"] == "/" || $parsed_url["path"] == "")
			$parsed_url["path"] = "/index";
		$new_file_name = $this->download_path . $parsed_url["path"];
		if(isset($parsed_url["query"]))
			$new_file_name .= "_" .htmlspecialchars_decode($parsed_url["query"]);
		if(isset($parsed_url["fragment"]))
			$new_file_name .= "#".$parsed_url["fragment"];
		$new_file_name .= ".html";
		if($is_mkdir)
			@mkdir(dirname($new_file_name), 0755, true);
		echo "saving " . $new_file_name . "\n";
		return $new_file_name;
	}
	
	function get_relative_path_impl($f, $t)
	{
		if(substr($f, 0, 2) != "./") $f = "./$f";
		if(substr($t, 0, 2) != "./") $t = "./$t";
		$f = str_replace(array("http://", "//"), "/", $f);
		$t = str_replace(array("http://", "//"), "/", $t);
		$fs = explode("/", $f);
		$ts = explode("/", $t);
		
		$same_num = 0;
		$fmt_num = count($fs) - count($ts);
		for($i=0;$i < min(count($fs), count($ts))-1; $i++)
			if($fs[$i] == $ts[$i])
				$same_num ++;
		
		$relate_path = "";
		for($i = $fmt_num;$i > 0; $i--)
			$relate_path .= "../";
		
		for($i = $same_num; $i < count($ts); $i++)
		{
			if($relate_path != "" &&  $relate_path[strlen($relate_path)-1] != "/")
				$relate_path .= "/";
			$relate_path .= $ts[$i];
		}
		return $relate_path;
	}
	
	function find_relative_path ( $frompath, $topath ) 
	{
		$from = explode( DIRECTORY_SEPARATOR, $frompath ); // Folders/File
		$to = explode( DIRECTORY_SEPARATOR, $topath ); // Folders/File
		$relpath = '';
		
		$i = 0;
		// Find how far the path is the same
		while ( isset($from[$i]) && isset($to[$i]) ) 
		{
		    if ( $from[$i] != $to[$i] ) break;
		    $i++;
		}
		$j = count( $from ) - 1;
		// Add '..' until the path is the same
		while ( $i < $j ) {
		    if ( !empty($from[$j]) ) $relpath .= '..'.DIRECTORY_SEPARATOR;
		    $j--;
		}
		// Go to folder from where it starts differing
		while ( isset($to[$i]) ) {
		    if ( !empty($to[$i]) ) $relpath .= $to[$i].DIRECTORY_SEPARATOR;
		    $i++;
		}
		
		// Strip last separator
		return substr($relpath, 0, -1);
	}
	function getRelativePath($from, $to)
	{
	    // some compatibility fixes for Windows paths
	    $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
	    $to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
	    $from = str_replace('\\', '/', $from);
	    $to   = str_replace('\\', '/', $to);
	
	    $from     = explode('/', $from);
	    $to       = explode('/', $to);
	    $relPath  = $to;
	
	    foreach($from as $depth => $dir) {
	        // find first non-matching dir
	        if($dir === $to[$depth]) {
	            // ignore this directory
	            array_shift($relPath);
	        } else {
	            // get number of remaining dirs to $from
	            $remaining = count($from) - $depth;
	            if($remaining > 1) {
	                // add traversals up to first matching dir
	                $padLength = (count($relPath) + $remaining - 1) * -1;
	                $relPath = array_pad($relPath, $padLength, '..');
	                break;
	            } else {
	                $relPath[0] = './' . $relPath[0];
	            }
	        }
	    }
	    return implode('/', $relPath);
	}
	
	
}
//print_r(parse_url("vote-note.php_id=96433&page=function.parse-url&vote=down.html"));
$ws = new web_downloader("http://www.php.net/manual/en/function.parse-url.php", 2);
$ws = new web_downloader("http://www.jhhlab.tw/jhhlab/?page_id=92", 2);


?>