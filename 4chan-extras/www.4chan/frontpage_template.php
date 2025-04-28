<?
if (!isset($no_cache_control)){
	header("Cache-Control: public, max-age=120, s-maxage=120");
	header("Vary: Cookie");
} 

if (!defined('IS_4CHANNEL')) {
  define('IS_4CHANNEL', preg_match('/(^|\.)4channel.org$/', $_SERVER['HTTP_HOST']));
}

$url_domain = (IS_4CHANNEL ? '4channel.org' : '4chan.org');

include 'frontpage_footer.php';

$current_page = basename($_SERVER['SCRIPT_FILENAME']);
$current_page = str_replace("-test", "", $current_page); // XXX temporary test pages start with new-
$current_page = str_replace("index.php", "", $current_page);
$current_page = str_replace(".php", "", $current_page);
if($current_page == 'frontpage_template.php') die();

$force_logo = true; //$current_page !== '';
/*
in_array($current_page, array(
  'oneboardad', 'pass', 'advertise'
));
*/
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<? if(function_exists('meta_keywords')): ?>
<meta name="keywords" content="<? meta_keywords(); ?>" />
<? else: ?>
<?php if (IS_4CHANNEL): ?>
<meta name="keywords" content="imageboard,image board,forum,bbs,anonymous,chan,anime,manga,video games,english,japan" />
<?php else: ?>
<meta name="keywords" content="imageboard,image board,forum,bbs,anonymous,chan,anime,manga,ecchi,hentai,video games,english,japan" />
<?php endif ?>
<? endif; ?>
<? if(function_exists('meta_description')): ?>
<meta name="description" content="<? meta_description(); ?>" />
<? elseif(!$current_page): ?>
<meta name="description" content="4chan is a simple image-based bulletin board where anyone can post comments and share images anonymously." />
<? endif;
if(function_exists('iOSmeta')):
?><? iOSmeta(); ?>
<?
endif;
?>
<meta name="robots" content="noarchive" />
<?
if(function_exists('title')):
?><title><? title(); ?></title><?
else:
?>
<title>4chan</title>
<?
endif;
if ($current_page === '') { ?>
<?php if (IS_4CHANNEL): ?>
<link rel="stylesheet" type="text/css" href="//s.4cdn.org/css/frontpage_blue.3.css" />
<?php else: ?>
<link rel="stylesheet" type="text/css" href="//s.4cdn.org/css/frontpage.12.css" />
<?php endif ?>
<? if(function_exists('anti_adblock')) { anti_adblock(); } ?>
<?php } else { ?>
<link rel="stylesheet" type="text/css" href="//s.4cdn.org/css/yui.8.css" />
<?php if (IS_4CHANNEL): ?>
<link rel="stylesheet" type="text/css" href="//s.4cdn.org/css/global_blue.3.css" />
<?php else: ?>
<link rel="stylesheet" type="text/css" href="//s.4cdn.org/css/global.61.css" />
<?php endif ?>
<?php } ?>
<link rel="shortcut icon" href="//s.4cdn.org/image/favicon.ico" />
<link rel="apple-touch-icon" href="//s.4cdn.org/image/apple-touch-icon-iphone.png" />
<link rel="apple-touch-icon" sizes="72x72" href="//s.4cdn.org/image/apple-touch-icon-ipad.png" />
<link rel="apple-touch-icon" sizes="114x114" href="//s.4cdn.org/image/apple-touch-icon-iphone-retina.png" />
<link rel="apple-touch-icon" sizes="144x144" href="//s.4cdn.org/image/apple-touch-icon-ipad-retina.png" />
<? if(function_exists('stylesheet')): ?>
<link rel="stylesheet" type="text/css" href="<? stylesheet(); ?>" />
<? elseif($current_page): ?>
<link rel="stylesheet" type="text/css" href="//s.4cdn.org/css/generic.1.css" />
<? endif; ?>
<?php if (IS_4CHANNEL): ?>
<script>var danbo_rating = '__SFW__', danbo_page = 'homews';</script>
<?php else: ?>
<script>var danbo_rating = '__NSFW__', danbo_page = 'homenws';</script>
<?php endif ?>
<script src="https://static.danbo.org/publisher/q2g345hq2g534-4chan/js/preload.4chan.js" defer></script>
<style type="text/css">
.danbo-slot {
 width:728px;
 height:90px;
 margin:10px auto;
 overflow:hidden
}
@media only screen and (max-width:480px) {
 .danbo-slot {
  width:300px;
  height:250px
 }
}
</style>
<script type="text/javascript">(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
var tid = /(^|\.)4channel.org$/.test(location.host) ? 'UA-166538-5' : 'UA-166538-1';
ga('create', tid, {'sampleRate': 1});
ga('set', 'anonymizeIp', true);
ga('send','pageview')</script>
<?php if( isset( $custom_header ) ) echo $custom_header; ?>
</head>
<body>
<div id="doc">
  <div id="hd">
    <div id="logo-fp">
      <a href="//www.<?php echo $url_domain ?>/" title="Home"><img alt="4chan" src=<?php if (!$force_logo): ?>"http://img.2ch.sc/img/4_0217.png" width="448" height="240"<?php else: ?>"//s.4cdn.org/image/fp/logo-transparent.png" width="300" height="120"<?php endif ?>></a>
    </div>
  </div>
<? if (function_exists('top_ad_728x90')) { call_user_func("top_ad_728x90"); } ?>
<? if(isset($announce_type) && $announce_type !== 0): ?>
<div id="bd">
  <div class="box-outer" id="announce">
    <div class="box-inner">
      <div class="boxbar">
        <h2><? announce_title() ?></h2>
        <?php if ($announce_type === 1): ?>
        <a data-cmd="x-wot" href="#" class="closebutton"></a>
        <?php elseif ($announce_type === 2): ?>
        <a data-cmd="x-wot" href="#" class="closebutton" data-annc="1" name="annc<?= $announce_serial ?>"></a>
        <?php endif ?>
      </div>
      <div class="boxcontent">
        <? announce_content() ?>
      </div>
    </div>
  </div>
<? endif; ?>
<? if (function_exists('mobile_ad_tag')) { call_user_func("mobile_ad_tag"); } ?>
<? for($i=0;$i < $top_box_count;$i++): ?>
<? if ($current_page === ''):
if ($top_box_id[$i] === 'boards'): ?>
<div id="danbo-s-t" class="danbo-slot"></div>
<script>
function initAdsFallback() {
  let cnt = document.getElementById('danbo-s-t');
  
  if (!cnt) {
    return;
  }
  
  cnt.style.display = 'none';
}
function initAdsDanbo() {
  if (!window.Danbo) {
    return;
  }
  
  let pubid = 27;
  
  let b = window.danbo_page;
  
  let nodes = document.getElementsByClassName('danbo-slot');
  
  let m = window.matchMedia && window.matchMedia('(max-width: 480px)').matches;
  
  for (let cnt of nodes) {
    let s = cnt.id === 'danbo-s-t';
    
    let el = document.createElement('div');
    el.className = 'danbo_dta';
    
    if (m) {
      if (s) {
        s = '3';
      }
      else {
        s = '4';
      }
      el.setAttribute('data-danbo', `${pubid}-${b}-${s}-300-250`);
      el.classList.add('danbo-m');
    }
    else {
      if (s) {
        s = '1';
      }
      else {
        s = '2';
      }
      el.setAttribute('data-danbo', `${pubid}-${b}-${s}-728-90`);
      el.classList.add('danbo-d');
    }
    
    cnt.appendChild(el);
  }
  
  window.addEventListener('message', function(e) {
    if (e.origin === 'https://hakurei.danbo.org' && e.data && e.data.origin === 'danbo') {
      window.initAdsFallback(e.data.unit_id);
    }
  });
  
  window.Danbo.initialize();
}
document.addEventListener('DOMContentLoaded', initAdsDanbo, false);
</script>
<? endif ?>
<? endif ?>
<div class="box-outer top-box" <? if(isset($top_box_id[$i])) echo "id=\"{$top_box_id[$i]}\"" ?>>
  <div class="box-inner">
    <div class="boxbar">
      <h2><? call_user_func("top_box_title_$i") ?></h2>
      <? if(isset($top_box_button[$i])) echo $top_box_button[$i]. "\n"; ?>
    </div>
    <div class="boxcontent">
      <? call_user_func("top_box_content_$i") ?>
    </div>
  </div>
</div>
<? endfor; ?>
<div class="yui-g">
  <div class="yui-u first">
<? for($i=0;$i < $left_box_count;$i++): ?>
<div class="box-outer left-box" <? if(isset($left_box_id[$i])) echo "id=\"{$left_box_id[$i]}\"" ?>>
  <div class="box-inner">
    <div class="boxbar">
      <h2><? call_user_func("left_box_title_$i") ?></h2>
      <? if(isset($left_box_button[$i])) echo $left_box_button[$i]. "\n"; ?>
    </div>
    <div class="boxcontent">
      <? call_user_func("left_box_content_$i") ?>
    </div>
  </div>
</div>
<? endfor; ?>
</div><div class="yui-u">
<? for($i=0;$i < $right_box_count;$i++): ?>
<div class="box-outer right-box" <? if(isset($right_box_id[$i])) echo "id=\"{$right_box_id[$i]}\"" ?>>
  <div class="box-inner">
    <div class="boxbar">
      <h2><? call_user_func("right_box_title_$i") ?></h2>
      <? if(isset($right_box_button[$i])) echo $right_box_button[$i] . "\n"; ?>
    </div>
    <div class="boxcontent">
      <? call_user_func("right_box_content_$i") ?>
    </div>
  </div>
</div>
<?
endfor;
?>
    </div>
  </div>
</div>
<? if (function_exists('bottom_ad_728x90')) { call_user_func("bottom_ad_728x90"); } ?>
<div id="ft"><ul><li class="fill" />
<?
$first = true;
foreach($frontpage_footer as $title=>$page):
	$classes = array();
	if($first) { $classes[] = 'first'; $first = false; }
	if( $current_page == $page) {
		$classes[] = 'current';
	}
	$class = '';
	if(count($classes))
		$class = ' class="' . implode(' ', $classes) . '"';
 ?>
<li<?=$class?>><a href="<?=$page?>"><?=$title?></a></li>
<? endforeach; ?>
</ul>
<br class="clear-bug" />
<div id="copyright"><a href="/faq#what4chan">About</a> &bull; <a href="/feedback">Feedback</a> &bull; <a href="/legal">Legal</a> &bull; <a href="/contact">Contact</a><br /><br /><br />
    Copyright &copy; 2003-<?php echo date('Y', $_SERVER['REQUEST_TIME']) ?> 4chan community support LLC. All rights reserved.
    </div>
  </div>
</div>

<? if($include_yui): ?>
<script type="text/javascript" src="//s.4cdn.org/js/yui.2.js"></script>
<? endif; ?>
<? if(function_exists('external_script')): ?>
<script type="text/javascript" src="<?= external_script() ?>"></script>
<? endif; ?>
<? if(function_exists('inline_script')): ?>
<script type="text/javascript"><? inline_script(); ?></script>
<? endif; ?>
<?php if( isset( $custom_footer ) ) echo $custom_footer; ?>
<div id="modal-bg"></div>
</body>
</html>
