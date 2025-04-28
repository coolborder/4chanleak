<?php
die();

define('IS_4CHANNEL', preg_match('/(^|\.)4channel.org$/', $_SERVER['HTTP_HOST']));

if(isset($_COOKIE['4chan_wsonly'])) {
  $wsonly=$_COOKIE['4chan_wsonly'];
} else {
  $wsonly="no";
}

if($wsonly=="no") {
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

$colour = $ws_css ? '#54a' : '#800';

$url_domain = IS_4CHANNEL ? '//www.4channel.org' : '//www.4chan.org';

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">
<html>
<head>
<title>Frames - 4chan</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="description" content="4chan is the largest English imageboard on the web.">
<?php if (IS_4CHANNEL): ?>
<meta name="keywords" content="imageboard,image board,forum,bbs,anonymous,chan,anime,manga,video games,english,japan" />
<?php else: ?>
<meta name="keywords" content="imageboard,image board,forum,bbs,anonymous,chan,anime,manga,ecchi,hentai,video games,english,japan" />
<?php endif ?>
<meta name="robots" content="noarchive">
<base href="<?php echo $url_domain ?>">
</head>
<frameset cols="200px,*" frameborder="1" border="1" bordercolor="<?=$colour;?>">
<frame src="<?php echo $url_domain ?>/frames_navigation">
<frame src="<?php echo $url_domain ?>/" name="main">
<noframes>
<h1>4chan</h1>
<p>This page uses frames!</p>
<p>To view 4chan without frames, click <a href="/?frames=no" target="_top">here</a>. To view a list of the boards, click <a href="/frames_navigation" target="_top">here</a>.</p>
</noframes>
</frameset>
</html>
