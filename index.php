<?php
include_once('libpacman.php');
define("EOL", PHP_EOL);
define("TAB", "\t");
?>

<html>
<head>
	<title>phpcman</title>
	
	<meta charset='UTF-8' />
	
	<meta name="description" content="See your Archlinux system updates right from your browser!" />
	<meta name="author" content="pictuga" />
	
	<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0;" />
	
	<!--
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
	
	<link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />
	<link rel="apple-touch-icon" href="logo.png" />
	<link rel="apple-touch-startup-image" href="startup.png" />
	-->
	
	<style type="text/css">
		body *
		{
			padding: 0;
			margin: 0;
		}
		
		body
		{
			max-width: 100%;
			background-color: white;
			padding-bottom: 1em;
		}
		
		h1
		{
			text-align: center;
			font-weight: bold;
			font-size: 2em;
			color: green;
			margin: 2em auto .5em auto;
		}
		
		p
		{
			text-align: justify;
			padding: 0;
			margin: 0;
			text-indent: 1em;
		}
		
		#count > span
		{
			font-weight: bold;
		}
		
		#list
		{
			margin-top: 1em;
		}
		
		#list > div
		{
			border-radius: 10px;
			border: 1px solid gray;
			padding: 10px;
			margin-bottom: .5em;
			font-family: sans-serif;
			transition: background-color 0.5s;
		}

		#list > div:hover
		{
			background-color: #eeeeee;
		}
		
		#list span.name
		{
			display: block;
			min-width: 33%;
			font-weight: bold;
		}
		
		#list span.repo
		{
			float: right;
			content: '[';
			font-family: monospace;
		}
		
		#list span.repo:before
		{
			content: '[';
		}
		
		#list span.repo:after
		{
			content: ']';
		}
		
		#list span.link:before
		{
			content: " ⋅ ";
		}
		
		#list span.version
		{
			font-style: italic;
		}
		
		#list span.link a, #list span.link a:visited
		{
			color: inherit;
			text-decoration: inherit;
		}
		
		#calc
		{
			text-align: center;
			font-size: .5em;
			font-family: sans-serif;
		}
		
		#intro
		{
			padding-bottom: 1em;
			margin-bottom: 1em;
			border-bottom: 1px solid black;
		}
		
		#intro form
		{
			margin: 1em 0;
			text-align: center;
		}
		
		#intro:after
		{
			position: relative;
			bottom: -1.5em;
			content: "Below, a demo.";
			display: block;
			color: blue;
			text-align: center;
			font-style: italic;
			margin-bottom: .5em;
		}
		
		form label
		{
			font-family: monospace;
			font-weight: bold;
		}
		
		form label:after
		{
			content: " : ";
			font-weight: normal;
		}
		
		form label:before
		{
			content: " $ ";
			font-weight: normal;
		}
		
		div.warning
		{
			background-color: pink;
			border: 1px solid red;
			padding: .5em;
		}
		
		input[type=checkbox].toggle
		{
			display: none;
		}
		
		input[type=checkbox].toggle + label
		{
			cursor: pointer;
		}
		
		input[type=checkbox].toggle + label:before
		{
			content: "→";
		}
		
		input[type=checkbox].toggle:checked + label:before
		{
			content: "↓";
		}

		input[type=checkbox].toggle + label + div
		{
			display: none;
			border-left: 1px solid gray;
			margin: 0 0 1em 1em;
			padding-left: 1em;
		}

		input[type=checkbox].toggle:checked + label + div
		{
			display: block;
		}
		
		#ad
		{
			padding: 0 !important;
			margin: 0 !important;
			position: absolute !important;
			top: 0 !important;
			left: 0 !important;
		}
		
		@media screen and (min-width : 800px)
		{
			body
			{
				max-width : 50%;
				margin : auto;
			}
		}
	</style>
</head>
<body>

	<h1>phpcman</h1>

<?php
main();

function main()
{
	if(!isset($_GET['id']))
		addNew();
	
	$id = (isset($_GET['id']) && is_numeric($_GET['id'])) ? $_GET['id'] : 0;
	displayId($id);
}

function addNew()
{
	if(isset($_FILES['file'])
	&& $_FILES['file']['error'] === 0
	&& $_FILES['file']['size'] < 100000
	&& explode('/', mime_content_type($_FILES['file']['tmp_name']))[0] == 'text')
	{
		$tmp = $_FILES['file']['tmp_name'];
		
		$diff = array_diff(getListFile($tmp), array_keys(getCoreInfo(getListFile($tmp))));
		$aur = checkAUR($diff);
		$list = kasort(array_merge($aur, getCoreInfo(getListFile($tmp))));
		
		$id = ((int) explode('.', array_slice(scandir('list'), -1)[0])[0] ) +1;
		
		file_put_contents('list/' . $id . '.list', implode("\n", array_keys($list)));
		file_put_contents('list/' . $id . '.time', time());
		
		$depends = addDepends(getExtraInfo(getCoreInfo(getListFile($tmp))));
		$list = kasort(array_merge($aur, $depends));
		
		file_put_contents('list/' . $id . '.cache', implode("\n", array_keys($list)));
		
		ob_clean();
		header('Location: ' . $_SERVER['SCRIPT_URI'] . '?id=' . $id);
	}
	else
	{
		echo '<div id="intro">' . EOL;
			echo '<p><i>What\'s this?</i> This website intends to show you your Archlinux system updates, so that you know whether
			it\'s worth checking them at home. The main use-case is to check this website on your smartphone, 
			at work, or anywhere else.</p>' . EOL;
			echo '<p>Of course, you can add your own configuration! Result sample below! 
			Therefore you have to upload a list of your installed packages to this website. That\'s all!</p>' . EOL;
			echo '<p>The website actually shows installed packages ordered by "builddate" (or upload for AUR)(most recent up). 
			Architectures: <a href="/phpcman/32bit/">32bit</a> or <a href="/phpcman/">64bit</a> or <a href="/phpcman/rpi/">rpi</a>.</p>' . EOL;
			
			echo '<form method="post" action="?" enctype="multipart/form-data">' . EOL;
			echo TAB . '<input type="hidden" name="MAX_FILE_SIZE" value="50000" />' . EOL;
			echo TAB . '<label for="file">pacman -Qqe > ~/tmp</label><input type="file" id="file" name="file">' . EOL;
			echo TAB .  '<input type="submit" value="Add">' . EOL;
			echo '</form>' . EOL;
			
			if(isset($_FILES['file']))
				echo '<div class="warning">Looks like you tried to upload your package list, but that it failed. 
				Please check you\'re uploading the right file. The list file is supposed to be small (&lt;100ko), 
				any bigger file will be rejected.</div>' . EOL;
		echo '</div>' . EOL;
	}
}

function displayId($id)
{
	if(!is_file('list/' . $id . '.list') || !is_file('list/' . $id . '.cache') || !is_file('list/' . $id . '.time'))
	{
		echo '<div class="warning">The requested setup cannot be found. Try uploading a new package list.</div>';
		return false;
	}
	
	$START_TIME = microtime(true);
	updateCache();
	
	$installed = getExtraInfo(getCoreInfo(getListFile('list/' . $id . '.cache')));
	
	$diff = array_diff(getListFile('list/' . $id . '.list'), array_keys(getCoreInfo(getListFile('list/' . $id . '.list'))));
	$aur = checkAUR($diff);
	$all = kasort(array_merge($aur, $installed));
	
	$installed = $all;
	
	$new = array_slice(listByBuild($installed), 0, 100);
	
	echo '<input type="checkbox" class="toggle" id="toggle" /><label for="toggle">More info</label>' . EOL;
	echo '<div>' . EOL;
		echo TAB . '<p>You have <b>' . count($installed) . '</b> packages installed including dependencies.</p>';
		echo TAB . '<p>Your personal result page is <b>' . $_SERVER['SCRIPT_URI'] . '?id=' . $id . '</b>.
		You can access it from any browser, any time you want. You can find a nice QRCode to quickly access this page 
		<a href="https://www.wolframalpha.com/input/?i=qrcode+' . $_SERVER['SCRIPT_URI'] . '?id=' . $id . '">here</a>.</p>' . EOL;
	echo '</div>' . EOL;
	
	echo '<div id="list">' . EOL;
	
	foreach($new as $package)
	{
		echo '<div>' . EOL;
			echo TAB . '<span class="repo">' . $package['repo'] . '</span>' . EOL;
			echo TAB . '<span class="name">' . $package['name'] . '</span>' . EOL;
			echo TAB . '<span class="date">' . date('Y/m/d H:i', $package['desc']['BUILDDATE']) . '</span>' . EOL;
			echo TAB . '<span class="version">' . $package['version'] . '</span>' .EOL;
			echo TAB . '<span class="link"><a href="' . $package['page'] . '" target="_blank" title="Info page">i</a></span>' . EOL;
			echo TAB . '<span class="link"><a href="' . $package['pkgbuild'] . '" target="_blank" title="PKGBUILD">p</a></span>' . EOL;
		echo '</div>' . EOL;
	}
	
	echo '</div>' . EOL;
	
	echo '<div id="calc">⌚ ' . (microtime(true) - $START_TIME) . '</div>' . EOL;
}
?>
</body>
