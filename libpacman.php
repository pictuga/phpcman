<?php
//init

chdir(dirname(__FILE__));
safetyCheck();
parseConf();

//rand tools

function makeSimpleAssoc($array)
{
	$out = array();
	foreach($array as $key => $value)
		$out[$key] = $value[0];
	return $out;
}

function safetyCheck()
{
	if(is_file('cache_lock'))
	{
		if(file_get_contents('cache_lock') > time()+60*10)
		{
			echo "update failed...";
			unlink('cache_lock');
			updateCache();
		}
		else
		{
			echo "updating db...";
			exit;
		}
	}
	return true;
}

function kasort($array)
{
	ksort($array);
	return $array;
}

//file tools

function ddlFile($url, $path=null, $etag=null)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5);
	if($etag !== null && is_file($etag))
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('If-None-Match: ' . file_get_contents($etag)));
	$data = curl_exec($ch);
	
	$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$header = substr($data, 0, $header_size);
	$body = substr($data, $header_size);
	
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	if($etag !== null)
	{
		preg_match_all('#"[0-9a-z-]{6,}"#', $header, $match);
		file_put_contents($etag, $match[0][0]);
	}
	
	if($http_code == 304)
		return "unchanged";
	
	if($path != null && $http_code == 200)
		return file_put_contents($path, $body);
	
	if($path == null)
		return $body;
}

//conf

function parseConf($file='settings.ini')
{
	$GLOBALS['CONF'] = @parse_ini_file($file, true);
	$GLOBALS['PACCONF'] = @parse_ini_file($GLOBALS['CONF']['server']['pacconf'], true);
	unset($GLOBALS['PACCONF']['options']);
	
	foreach($GLOBALS['PACCONF'] as $name => $repo)
		$GLOBALS['PACCONF'][$name]['url'] = parseRepoUrl($name, $GLOBALS['CONF']);
}

//repo tools

function parseRepoUrl($name)
{
	$url = $GLOBALS['CONF']['server']['url'];
	$url = str_replace('$repo', $name, $url);
	$url = str_replace('$arch', $GLOBALS['CONF']['server']['arch'], $url);
	
	return $url;
}

function updateCache()
{
	file_put_contents('cache_lock', time());
	
	foreach($GLOBALS['PACCONF'] as $name => $repo)
	{
		$url	= $repo['url'] . '/' . $name . '.db';
		$dest	= $GLOBALS['CONF']['cache']['path'] . $name . '.db';
		$etag	= $GLOBALS['CONF']['cache']['path'] . $name . '.etag';
		
		if(!is_dir($GLOBALS['CONF']['cache']['path']))
			mkdir($GLOBALS['CONF']['cache']['path']);
		
		ddlFile($url, $dest, $etag);
	}
	
	unlink('cache_lock');
}

function listRepoPackage($name)
{
	$dir	= 'phar://' . $GLOBALS['CONF']['cache']['path'] . $name . '.db';
	$list	= scandir($dir);
		//unset($list[0]); unset($list[1]);//only for real fs
	$out	= array();
	
	foreach($list as $file)
	{
		preg_match_all('#^(.+)-([0-9a-zA-Z_.:~]+-[0-9.]+)$#' , $file, $match);
		$page = 'https://www.archlinux.org/packages/' . $name . '/' . $GLOBALS['CONF']['server']['arch'] . '/' . $match[1][0] . '/';
		$pkgbuild = 'https://projects.archlinux.org/svntogit/'
			. ($name == 'community' ? 'community' : 'packages')
			. '.git/plain/trunk/PKGBUILD?h=packages/' . $match[1][0];

		$out[$match[1][0]] = array(
			'name'	=> $match[1][0],
			'repo'	=> $name,
			'version' => $match[2][0],
			'file'	=> $match[0][0],
			'page'	=> $page,
			'pkgbuild' => $pkgbuild);
	}
	
	//print_r($out);
	return $out;
}

function listPackagesByRepo()
{
	static $cache = 0;
	if(!$cache)
	{
		$cache = array();
		foreach($GLOBALS['PACCONF'] as $name => $repo)
			$cache[$name] = listRepoPackage($name);
	}
	
	return $cache;
}

function listPackages()
{
	static $cache = 0;
	if(!$cache)
		$cache = call_user_func_array('array_merge', listPackagesByRepo());
	return $cache;
}

function getExtraInfo($list, $batch=true)
{
	if(!$batch)
		$list = array($list);
	
	foreach($list as &$package)
	{
		if(count($package) == 6)
		{
			$package['depends'] = parsePackageInfo($package['file'], $package['repo'], 'depends');
			$package['desc'] = makeSimpleAssoc(parsePackageInfo($package['file'], $package['repo'], 'desc'));
		}
	}
	
	return $batch ? $list : $list[0];
}


//aur tools

function checkAUR($list)
{
	if(!count($list))
		return;
	
	$url = "https://aur.archlinux.org/rpc.php?type=multiinfo&arg[]=";
	$args = implode('&arg[]=', $list);
	$url = $url . $args;
	
	$aur = json_decode(ddlFile($url), true)['results'];
	
	$out = array();
	foreach($aur as $package)
	{
		$out[$package['Name']] = array(
			'name'	=> $package['Name'],
			'version' => $package['Version'],
			'repo'	=> 'aur',
			'desc'	=> array('BUILDDATE' => $package['LastModified']),
			'page'	=> 'https://aur.archlinux.org/packages/' . $package['Name'] . '/',
			'pkgbuild' => 'https://aur.archlinux.org/packages/' . substr($package['Name'], 0, 2) . '/' . $package['Name'] . '/PKGBUILD'
			);
	}
	
	return $out;
}

//package tools

function parsePackageInfo($file, $repo, $info)
{
	//$file = $GLOBALS['CONF']['cache']['path'] . $repo . '.out/' . $file . '/' . $info;
	$file = 'phar://' . $GLOBALS['CONF']['cache']['path'] . $repo . '.db/' . $file . '/' . $info;
	
	$out = array();
	$cur = "";
	
	$handle = @fopen($file, "r");
	if ($handle)
	{
		while (($line = fgets($handle, 4096)) !== false)
			if($line != "\n")
			{
				$line = rtrim($line, "\r\n");
				
				if($line[0] == '%' && substr($line, -1) == "%")
				{
					$cur = substr($line, 1, -1);
					$out[$cur] = array();
				}
				else
				{
					$out[$cur][] = $line;
				}
			}
		if (!feof($handle))
			return false;
		fclose($handle);
	}
	else	return false;
	
	return $out;
}

function addDepends($list)
{
	while(list($name, $package) = each($list)) 
	{
		if(array_key_exists('DEPENDS', $package['depends']))
		{
			foreach($package['depends']['DEPENDS'] as $depend)
			{
				$depend = strtok($depend,'>=<');
				if(!array_key_exists($depend, $list))
				{
					if(array_key_exists($depend, listPackages()))
						$list[$depend] = getExtraInfo(listPackages()[$depend], false);
				}
			}
		}
	}
	
	return $list;
}

//installed info

function getListFile($file)
{
	return explode("\n", rtrim(file_get_contents($file), "\r\n"));
}

function getCoreInfo($list)
{
	$byrepo = listPackages();
	$out = array();
	
	foreach($list as $package)
	{
		if(array_key_exists($package, $byrepo))
			$out[$package] = $byrepo[$package];
	}
	
	return $out;
}

function listByBuild($list)
{
	//losing keys
	usort($list, function($a, $b)
		{
			return $a['desc']['BUILDDATE'] - $b['desc']['BUILDDATE'];
		});
	return array_reverse($list);
}
?>
