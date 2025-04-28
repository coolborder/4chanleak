<?
require_once 'lib/util.php';

define('IS_4CHANNEL', preg_match('/(^|\.)4channel.org$/', $_SERVER['HTTP_HOST']));

extract($_COOKIE);
extract($_GET);

$url_domain = (IS_4CHANNEL ? '4channel.org' : '4chan.org');

include("data/boards.php");

function print_boards($boards,$dirs,$target,$index='',$new_posts_check=0) {
	global $wsonly, $url_domain;
	echo "<ul>";

	foreach($boards as $board) {
		$domain = "{$board['domain']}.$url_domain/";

		if($board['nws'] && $board['domain'] != 'dis' && $wsonly)
			continue;

		$b_b = $b_e = "";
		if($new_posts_check && isset($new_posts)) {
			$i = array_search($board['dir'],$new_posts);
			if ($i !== FALSE) {
				$b_b = "<b>"; $b_e = "</b>";
			}
		}

		echo "<li><a href=\"//".$domain.$board['dir']."/$index\" target=\"".$target."\" title=\"".$board['name']."\"";
		if($board["highlight"]==1) echo " class=\"hl\"";
		echo ($dirs) ?
			">$b_b/".$board['dir']."/ - ".$board['name']."$b_e</a></li>\n"
			: ">$b_b".$board['name']."$b_e</a></li>\n";
	}
	echo "</ul>";
}

if($disclaimer=="accept") {
	setcookie("4chan_disclaimer","1",time()+7*24*3600,"/");
}

if($nav) $disclaimer="accept";


if($_GET['frames']!="no") {
	setcookie("4chan_frames","yes",time()+365*24*3600,"/");
	$frames="yes";
} else {
  	setcookie("4chan_frames",FALSE,time()+365*24*3600,"/");
		$frames="no";
	header('Location: //www.' . $url_domain . '/?noframes');
	die();
}


if(isset($_COOKIE['4chan_dirs'])) {
	$dirs=$_COOKIE['4chan_dirs'];
} else {
	$dirs="no";
}
if($_GET['dirs']=="yes") {
	setcookie("4chan_dirs","yes",time()+365*24*3600,"/");
	$dirs="yes";
} elseif($_GET['dirs']=="no") {
  	setcookie("4chan_dirs","no",time()+365*24*3600,"/");
		$dirs="no";
}

if(isset($_COOKIE['4chan_wsonly'])) {
	$wsonly=(int)$_COOKIE['4chan_wsonly'];
} else {
	$wsonly=0;
}
if($_GET['wsonly']=="yes") {
	setcookie("4chan_wsonly","1",time()+365*24*3600,"/");
	$wsonly=1;
} elseif($_GET['wsonly']=="no") {
  	setcookie("4chan_wsonly",FALSE,time()+365*24*3600,"/");
	
		$wsonly=0;
}

if (IS_4CHANNEL) {
	$wsonly = 1;
}

$styles = array();
$hideable_lists = array('dis', 'img', 'draw', 'up');
foreach($hideable_lists as $name) {
	// if cookie not set, or set and true, show it
	if(!isset($_COOKIE['nav_show_' . $name]) || $_COOKIE['nav_show_' . $name])
		$styles[$name] = '';
	else
		$styles[$name] = 'display: none';
}
// temporary: hide dis by default
if(!isset($_COOKIE['nav_show_dis'])) $styles['dis'] = 'display: none';

if(!$wsonly) {
	$ws_css = false;
	$style = $_COOKIE['nws_style'];
	if($_COOKIE['nws_style'] == "Yotsuba B" ||
		$_COOKIE['nws_style'] == "Burichan")
		$ws_css = true;
}
else {
	$ws_css = true;
	if($_COOKIE['ws_style'] == "Yotsuba" ||
		$_COOKIE['ws_style'] == "Futaba")
		$ws_css = false;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Navigation - 4chan</title>
<style type="text/css">
#logo h1 { margin-left: -200px; width: 100%; height: 100%; display: block; }
#logo h1 a { width: 100%; height: 100%; display: block; padding-right: 247px; }
#logo { font-size: 1px; line-height: 0px; height: 46px; overflow: hidden; margin: 0 auto; padding-bottom: 15px; width: 47px; }
body { font-family: sans-serif; font-size: 9pt; background: #ffe url('//s.4cdn.org/image/fp/fade.png') top repeat-x; color: #800; }
/* a { text-decoration: none; color: #550 } */
a { color: #800; text-decoration:none; }
a:hover { color:#e00; text-decoration:underline; }
h1 { font-family: sans; font-weight: bold; margin: 0px; margin-top: 0px; margin-bottom: 2px; padding: 2px; }
h1 { font-size: 150% }

.hl { font-style: italic }
.plus { float: right; font-size: 10px; font-weight: normal; padding: 0 4px; margin: 0px 0px; background: #eb9; border: 1px solid #d8a787; cursor: hand; cursor: pointer }
.plus:hover { background: #da8; border: 1px solid #c97 }
ul { list-style: none; padding-left: 0px; margin: 0px }

/* li:hover { background: #fec; } */
li:hover { color:#e00; text-decoration:underline; }
li a { display: block; width: 100%; }

#img li, #dis li {
	line-height: 110%;
}

h2 {
	background-color: #FCA;
	padding: 2px 4px;
	padding-right: 2px;

	border-bottom: 1px solid #d8a787;
	border-top: 1px solid #d8a787;

	font-weight: bold;
	margin-bottom: 4px;
	
	margin-left: -8px;
	margin-right: -8px;
	
	font-size: 9pt;
}
<? if($ws_css): ?>
body { background: #EEF2FF url('//s.4cdn.org/image/fp/fade-blue.png') top center repeat-x; color:#000; }
a { color: #34345c; }
a:hover { color: #e00; }
h2 { background: #d1d5ee; border-color: #98e; }
	
.plus { background: #B9BEDD; border: 1px solid #98E; }
.plus:hover { background: #a9aed0; border: 1px solid #806fd8; }
<? endif; ?>
</style>
<script type="text/javascript">
function createCookie(name,value,days) {
  if (days) {
    var date = new Date();
    date.setTime(date.getTime()+(days*24*60*60*1000));
    var expires = "; expires="+date.toGMTString();
  }
  else expires = "";
  document.cookie = name+"="+value+expires+"; path=/";
}

function readCookie(name) {
  var nameEQ = name + "=";
  var ca = document.cookie.split(';');
  for(var i=0;i < ca.length;i++) {
    var c = ca[i];
    while (c.charAt(0)==' ') c = c.substring(1,c.length);
    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
  }
  return null;
}

function toggle(button,area) {
	var tog=document.getElementById(area);
	if(tog.style.display)	{
		tog.style.display="";
	}	else {
		tog.style.display="none";
	}
	button.innerHTML=(tog.style.display)?'+':'&minus;';
	createCookie('nav_show_'+area, tog.style.display?'0':'1', 365);
}

</script>
</head>
<body>
<div id="logo">
<a href="//www.<?php echo $url_domain ?>/" target="main" title="Home"><img alt="4chan" src="//s.4cdn.org/image/fp/minileaf-transparent.png"></a>
</div>
<ul>
<? if (($disclaimer=="accept")||isset($_COOKIE['4chan_disclaimer'])) {
if(!$nav) {
	if($_GET['disclaimer'] == 'accept' || !isset($_COOKIE['4chan_disclaimer'])) {
		$disclaimerlink = "&disclaimer=accept";
	}
	else {
		$disclaimerlink = "";
	}

	  $target="main";
	  echo "<li><a href=\"?frames=no$disclaimerlink\" target=\"_top\">[Remove Frames]</a></li>\n";

	if ($dirs=='yes')
		echo "<li><a href=\"?dirs=no$disclaimerlink\">[Hide Directories]</a></li>\n";
	else
		echo "<li><a href=\"?dirs=yes$disclaimerlink\">[Show Directories]</a></li>\n";
	
	if (!IS_4CHANNEL) {
		if ($wsonly) {
			echo "<li><a href=\"?wsonly=no$disclaimerlink\">[Show All Boards]</a></li>\n";
		}
		else {
			echo "<li><a href=\"?wsonly=yes$disclaimerlink\">[Show Worksafe Only]</a></li>\n";
		}
	}
} else {
	$target="main";
} ?>
</ul>
<h2><span class="plus" onclick="toggle(this,'img');" title="Toggle Image Boards"><?=$styles['img']?'+':'&minus;'?></span>Image Boards</h2>
<div id="img" style="<?=$styles['img']?>">
<?
	print_boards($boards['img'], $dirs=='yes', $target);
 ?>
</div>
<? if($wsonly=='no') { ?>

<h2><span class="plus" onclick="toggle(this,'up');" title="Toggle Upload Boards"><?=$styles['up']?'+':'&minus;'?></span>Upload Boards</h2>
<div id="up" style="<?=$styles['up']?>">
<? print_boards($boards['upload'], $dirs=='yes', $target); ?>
</div>

<? } ?>
<h2>IRC</h2>
<ul>
<li><a href="irc://irc.rizon.net/4chan" title="#4chanˇ">#4chan @ Rizon</a></li>
</ul>
<? } else { ?>
<h2>Disclaimer</h2>
<p style="margin: 0 0 0 0;">To access this section of 4chan (the "website"), you understand and agree to the following:<br /><br />
1. The content of this website is for mature audiences only and may not be suitable for minors. If you are a minor or it is illegal for you to access mature images and language, do not proceed.<br /><br />
2. This website is presented to you AS IS, with no warranty, express or implied. By clicking "I Agree," you agree not to hold 4chan responsible for any damages from your use of the website, and you understand that the content posted is not owned or generated by 4chan, but rather by 4chan's users.<br /><br />
3. As a condition of using this website, you agree to comply with the "<a href="/rules" target="_blank" title="4chan Rules">Rules</a>" of 4chan, which are also linked on the home page. Please read the Rules carefully, because they are important.<br /><br />
[<a style="color: #00E;" href="?disclaimer=accept">I Agree</a>]</p>
<? } ?>
</body>
</html>
