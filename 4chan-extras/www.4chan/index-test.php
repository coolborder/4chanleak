<?php

//if ($_SERVER['REMOTE_ADDR'] !== '62.210.138.29') {
	die();
//}

require_once 'lib/util.php';

define('IS_4CHANNEL', preg_match('/(^|\.)4channel.org$/', $_SERVER['HTTP_HOST']));

$url_domain = (IS_4CHANNEL ? '4channel.org' : '4chan.org');

$custom_header = '';

if (IS_4CHANNEL) {
  $custom_footer = "<script>var a= document.createElement('script');a.src = 'https://powerad.ai/script.js';a.setAttribute('async','');top.document.querySelector('head').appendChild(a);</script>";
}

function build_post_json($post) {
  $fields = array(
    'board', 'type', 'no', 'resto', 'name', 'email', 'sub', 'com', 'id', 'capcode', 'now', 'replies', 'images'
  );
  
  $FORCED_ANON_ARR = array(
    'b',
    'soc',
  );
  
  $COUNTRY_FLAGS_ARR = array(
    'int',
    'sp',
    'pol'
  );
  
  $json = array();
  
  foreach ($fields as $key) {
    $json[$key] = $post[$key];
  }
  
  if ($json['com']) {
    $json['com'] = preg_replace('/&gt;&gt;([0-9]+)/', '<a href="#" class="quotelink">&gt;&gt;$1</a>', $json['com']);
  }
  
  if ($post['filedeleted'] == '0') {
    if ($post['ext'] != '') {
      $fields = array('ext', 'fsize', 'tim', 'tn_w', 'tn_h', 'filename', 'w', 'h');
      
      foreach ($fields as $key) {
        $json[$key] = $post[$key];
      }
    }
  }
  else {
    $json['filedeleted'] = 1;
  }
  
  if (strpos($json['name'], '</span> <span class="postertrip">') !== false) {
    $name = explode('</span> <span class="postertrip">', $json['name']);
    $json['name'] = $name[0];
    $json['trip'] = $name[1];
  }
  
  if (strpos($json['sub'], 'SPOILER<>') === 0) {
    $json['sub'] = substr($json['sub'], 9);
    $json['spoiler'] = 1;
  }
	
  if (in_array($json['board'], $FORCED_ANON_ARR)
    && ($post['capcode'] != 'admin' && $post['capcode'] != 'admin_hl')
    ) {
    unset($json['trip']);
    unset($json['email']);
    $json['name']  = 'Anonymous';
  }
  
  if (in_array($json['board'], $COUNTRY_FLAGS_ARR) && $post['capcode'] == 'none') {
    $json['country'] = $post['country'];
  }
	
  return $json;
}

// cookies and option settings
$options = array();
function do_option($option, $cookiename, $valid_options) {
	global $options;
	if(isset($_COOKIE[$cookiename]) or isset($_GET[$option])) {
		if(isset($_GET[$option])) // new setting
			$options[$option] = $_GET[$option];
		else // cookied setting
			$options[$option] = $_COOKIE[$cookiename];
		// check validity
		if(!in_array($options[$option], $valid_options))
			$options[$option] = $valid_options[0];

		setcookie($cookiename, $options[$option], time()+365*24*3600, "/");
	}
	// no cookie or GET, use default value
	else {
		$options[$option] = $valid_options[0];
	}
}

do_option('filter_boards', 'fpb', array('all', 'ws', 'nws', 'allc'));
//do_option('filter_text', 'fptxt', array('hide','show'));
do_option('content', 'fpc', array('ws', 'nws', 'all'));

// override ws/nws filter
if (IS_4CHANNEL || (isset($_GET['c']) && $_GET['c'] === 'ws')) {
  $options['filter_boards'] = 'ws';
  $options['content'] = 'ws';
}

$options['use_catalog'] = isset($_COOKIE['fpcat']);

$options['disclaimer_done'] = false;
if(isset($_COOKIE['4chan_disclaimer'])) {
	$options['disclaimer_done'] = $_COOKIE['4chan_disclaimer'];
	setcookie('4chan_disclaimer', $_COOKIE['4chan_disclaimer'], time()+365*24*3600, "/");
}

$options['last_announce_closed'] = 0;
if(isset($_COOKIE['annc'])) {
	$options['last_announce_closed'] = $_COOKIE['annc'];
	setcookie('annc', $_COOKIE['annc'], time()+365*24*3600, "/");
}

$options['whatis4chan_closed'] = 0;
if(isset($_COOKIE['wi4c'])) {
	$options['whatis4chan_closed'] = $_COOKIE['wi4c'];
	setcookie('wi4c', $_COOKIE['wi4c'], time()+365*24*3600, "/");
}

$use_frames = !isset($_GET['noframes']);

if(isset($_COOKIE['4chan_frames']) && $_COOKIE['4chan_frames'] && $use_frames) {
	$options['4chan_frames'] = 1;
}
else {
	$options['4chan_frames'] = 0;
}
// page content
function title() {
 echo "4chan";
}


function iOSmeta() {
// echo '<meta name="apple-itunes-app" content="app-id=1124861180, affiliate-data=4chan" />';
}

function anti_adblock() { ?>

<? }


$announce_type = 0;
$announce_serial = 0;

if (!$options['whatis4chan_closed']) {
  $announce_type = 1;
}

if(file_exists('data/announce.txt')) {
	$announce_content = file('data/announce.txt');
	// first line is serial number, strip out non-digits (gets rid of newline and possible BOM etc.)
	$announce_serial = (int) preg_replace("#[^0-9]#", "", array_shift($announce_content));
	// rest is body
	$announce_content = trim(implode("", $announce_content));
	
  if ($announce_content !== '' && $options['last_announce_closed'] < $announce_serial) {
    $announce_type = 2;
  }
}

//echo "<!-- ".$options['last_announce_closed']." $announce_serial $show_announce-->";

function announce_title() {
	global $announce_type;
	if ($announce_type === 1) {
?>What is 4chan?<?
	}
	else if ($announce_type === 2) {
?>Announcement<?
	}
}

function announce_content() {
	global $options, $announce_content, $announce_type;
	if($announce_type === 1) { ?><div id="wot-cnt"></div><? }
	else if ($announce_type === 2) {
		echo $announce_content;
	}
}
/*
function mobile_ad_tag() { ?>
<div id="mobile-ad-top"></div>
<script type="text/javascript">
if (window.matchMedia && window.matchMedia('(max-device-width: 480px)').matches) {
  window.mopub = [{
    ad_unit: "b90d27bd6740472eac8ee888b73651fe",
    ad_container_id: "mobile-ad-top",
    ad_width: 320,
    ad_height: 50,
    keywords: "",
  } ];
  
  (function() {
    var mopubjs = document.createElement("script");
    mopubjs.async = true;
    mopubjs.type = "text/javascript";
    mopubjs.src = "//d1zg4cyg8u4pko.cloudfront.net/mweb/mobileweb.min.js";
    var node = document.getElementsByTagName("script")[0];
    node.parentNode.insertBefore(mopubjs, node);
  })();
}
</script>
<? }
*/
$top_box_count = 3;

$top_box_id[0] = "boards";
$top_box_button[0] = '<div id="filter-btn" data-cmd="filter">filter ▼</div>';
function top_box_title_0() {
?>Boards<?
}

$info = array();
include 'data/boards.php';

function top_box_content_0() {
	global $info, $options;
	global $boards;
	
	$catalog = $options['use_catalog'] ? 'catalog' : '';
	
	foreach($boards['img'] as $board) {
		$info[$board['dir']] = array(
			'path' => '//' . $board['domain'] . '.' . L::d($board['dir']) . '/' . $board['dir'] . '/' . $catalog,
			'title' => $board['name'],
			'domain' => $board['domain'],
			'nws' => $board['nws']
		);
	}

	foreach($boards['upload'] as $board) {
		$info[$board['dir']] = array(
			'path' => '//' . $board['domain'] . '.' . L::d($board['dir']) . '/' . $board['dir'] . '/',
			'title' => $board['name'],
			'domain' => $board['domain'],
			'nws' => $board['nws']
		);
	}

	if($options['filter_boards'] == 'allc') {
		$board_categories = array(
			"Image Boards" => array("3", "a", "aco", "adv", "an", "asp", "b", "bant", "biz", "c", "cgl", "ck", "cm", "co", "d"),
			"Image Boards+" => array("diy", "e", "fa", "fit", "g", "gd", "gif", "h", "hc", "hm", "hr", "i", "ic", "his"),
			"Image Boards++" => array("int", "jp", "k", "lit", "lgbt", "m", "mlp", "mu", 'news', "p", "po", "pol", "qst", "r"),
			"Image Boards+++" => array("r9k", "s4s", "s", "sci", "soc", "sp", "t", "tg", "toy", 'trash', "trv", "tv", "n"),
			"Image Boards++++" => array("news", "o", "out", "u", "v", "vg", "vip", "vp", "vr", "w", "wg", "wsg", 'wsr', "x", "y"),
			"Upload Boards" => array("f")
			);
		$column_numbers = array(
			"Image Boards" => 1,
			"Image Boards+" => 2,
			"Image Boards++" => 3,
			"Image Boards+++" => 4,
			"Image Boards++++" => 5,
			"Upload Boards" => 5
			);
	}
	else {
		$board_categories = array(
			"Japanese Culture" => array("a", "c", "w", "m", "cgl", "cm", "f", "n", "jp"),
			"Video Games" => array('v', 'vg', 'vp', 'vr'),
			"Interests" => array("co", "g", "tv", "k", "o", "an", "tg", "sp", "asp", "sci", "his", "int", "out", "toy"),
			"Creative" => array("i", "po", "p", "ck", "ic", "wg", "lit", "mu", "fa", "3", "gd", "diy", "wsg", "qst"),
			"Adult(NSFW)" => array("s", "hc", "hm", "h", "e", "u", "d", "y", "t", "hr", "gif", "aco", "r"),
			"Other" => array("biz", "trv", "fit", "x", "adv", "lgbt", "mlp", "news", 'wsr', 'vip'),
			"Misc.(NSFW)" => array("b", "r9k", "pol", "bant", "soc", "s4s")
		);
		
		$column_numbers = array(
			"Japanese Culture" => 0,
			'Video Games' => 0,
			"Interests" => 1,
			"Creative" => 2,
      "Other" => 3,
      "Misc.(NSFW)" => 3,
			"Adult(NSFW)" => 4
		);
	}
	
	$sfw_only = ($options['filter_boards'] == 'ws');
	$nsfw_only = ($options['filter_boards'] == 'nws');
	
	$columns = array();
	foreach($column_numbers as $cat=>$col) {
		if(!isset($columns[$col]))
			$columns[$col] = array();
		$columns[$col][] = $cat;
	}
	foreach($columns as $categories) {
		$any_categories_printed = false;

		foreach($categories as $category) {
			
			$display_category = $category;
			$display_category = preg_replace("#[+]+$#"," (cont.)", $display_category);
			$display_category = str_replace("(NSFW)","</h3> <h3 style=\"display: inline;\"><span class=\"warning\" title=\"Not Safe For Work\"><sup style=\"vertical-align: text-bottom;\">(NSFW)</sup></span>",$display_category);

			$any_boards_printed = false;
			foreach($board_categories[$category] as $board) {
				if (isset($info[$board]['text']) && $options['filter_text'] == 'hide') {
					continue;
				}
				
				if ($sfw_only && $info[$board]['nws']) {
					continue;
				}
				
				if ($nsfw_only && !$info[$board]['nws']) {
					continue;
				}
				
				if(!$any_categories_printed) {
					$any_categories_printed = true;
?>
<div class="column">
<?
}
if(!$any_boards_printed) {
	$any_boards_printed = true;
?>
<h3 style="text-decoration: underline; display: inline;"><?=$display_category?></h3>
<ul>
<?
}
?>
<li><a href="<?=$info[$board]['path']?>" class="boardlink"><?=htmlspecialchars($info[$board]['title'])?></a></li>
<?
}
if($any_boards_printed) {
?>
</ul>
<?
			}
		}
		if($any_categories_printed) {
?>
</div>
<?
		}
	}
?>
<br class="clear-bug"/>
<?
}

function wordwrap2( $str, $cols, $cut )
{
	// if there's no runs of $cols non-space characters, wordwrap is a no-op
	if( mb_strlen( $str ) < $cols || !preg_match( '/[^ <>]{' . $cols . '}/', $str ) ) {
		return $str;
	}
	$sections = preg_split( '/[<>]/', $str );
	$str      = '';
	for( $i = 0; $i < count( $sections ); $i++ ) {
		if( $i % 2 ) { // inside a tag
			$str .= '<' . $sections[$i] . '>';
		} else { // outside a tag
			$words   = explode( ' ', $sections[$i] );
			$exclude = array(
				'http://',
				'https://',
				'www.'
			);

			foreach( $words as &$word ) {
				foreach( $exclude as $match ) {
					if (stripos($word, $match) === 0 && (stripos($word, '4chan.org') !== false || stripos($word, '4channel.org') !== false)) continue 2;
				}

				$word = htmlspecialchars_decode( $word, ENT_QUOTES );
				$word = utf8_wordwrap( $word, $cols, $cut, true );
				$word = htmlspecialchars( $word, ENT_QUOTES );

			}

			$str .= implode( ' ', $words );
		}
	}

	return $str;
}
/*
function utf8_wordwrap( $string, $width = 75, $break = "\n", $cut = false )
{
	if( $cut ) {
		// Cut lines that are too long by hand, even if they aren't official break opportunities
		$search  = '/(.{' . $width . '})/uS';
		$replace = '$1$2' . $break;
	}

	return preg_replace( $search, $replace, $string );
}
*/

function render_tooltip_contents($post) {
global $info;
$fsize = (int)($post['fsize']/1024);
$thumburl = '//i.4cdn.org/' . $post['board'] . '/' . $post['tim'] . 's.jpg';
$post['com'] = wordwrap2($post['com'], 50, "<br />");
$com = explode("<br />", $post['com']);
if(count($com) > 7) {
	$com = array_slice($com, 0, 7);
	$com[] = "...";
}
$com = implode("<br />", $com);

// quote lines
$com = preg_replace("!(^|>)(&gt;[^<]*)!", "\\1<span class=\"unkfunc\">\\2</span>", $com);
// quote links
$com = preg_replace("!((&gt;)?&gt;&gt;(/?\w+/)?\d+)!",'<a href="#">$1</a>', $com);

	
	
$title = "/{$post['board']}/ - " . htmlspecialchars( $info[$post['board']]['title'] );

$html = "";
	
$FORCED_ANON_ARR = array(
	'b',
	'soc'
);

$META_BOARD_ARR = array(
	'q'
);

$board = $post['board'];
	
if( in_array( $board, $FORCED_ANON_ARR ) || in_array( $board, $META_BOARD_ARR ) ) {
	$post['name'] = 'Anonymous';
}
	
if( in_array( $board, $FORCED_ANON_ARR ) ) {
	$post['sub'] = '';
}

$html .=  <<<EOHTML
<h2>$title</h2>
<div class="post">
EOHTML;
$wh = ($post['tn_w'] > 1) ? "width={$post['tn_w']} height={$post['tn_h']}" : "";
if($post['fsize'])
$html .= <<<EOHTML
	<span class="p_filesize">File: <a href="#">{$post['time']}{$post['ext']}</a>-($fsize KB, {$post['w']}x{$post['h']}, {$post['filename']}{$post['ext']})</span><br>
<img src="$thumburl" align=left $wh hspace=20 alt="Thumbnail unavailable">
EOHTML;
$html .= <<<EOHTML
<span class="p_filetitle">{$post['sub']}</span>
<span class="p_postername">{$post['name']}</span>
{$post['now']}
No.{$post['no']}<blockquote>$com</blockquote>
</div>
EOHTML;
echo htmlspecialchars($html);
}

function format_comment($str) {
  // remove sjis
  /*
  if (SJIS_TAGS && strpos($str, '<span class="sjis"') !== false) {
    $str = preg_replace('/<span class="sjis".+?<\/span>/', '[SJIS]', $str);
  }
  */
  
  $str = preg_replace('/(<br>)+/', "\n", $str);
  
  // remove html tags
  $str = preg_replace('/<[^>]*(>|$)/', '', $str);
  
  $len = mb_strlen($str);
  
  $length = 100;
  
  if ($len <= $length) {
    return $str;
  }
  
  $str = mb_substr($str, 0, $length);
  
  // remove truncated html entities
  $str = preg_replace('/&[^;]*$/', '', $str);
  
  $str .= '...';
  
  return $str;
}

function summarize($post) {
  if($post['sub']) {
    $com = $post['sub'];
  }
  else {
    $com = $post['com'];
    // strip out URLs...
    $com = preg_replace('{//[\S"\'<]+}','',$com);
    // remove linebreaks
    $com = preg_replace('{<br ?/?>}',"\n",$com);
    // take the first sentence that's longer than 6 letters...
    $sentences = preg_split('{[\n.]+}',$com);
    $com = '';
    foreach($sentences as $sent) {
      if(strlen($sent) > 6 && strpos($sent,"&gt;")!==0 && strpos($sent,"EXIF data")!==0 && strpos($sent,"Oekaki post")!==0) {
        $com = $sent;
        break;
      }
    }
  }
  // unescape html entities
  $com = htmlspecialchars_decode($com, ENT_QUOTES);
  
  // replace nonsensical escaped commas
  $com = str_replace("&#44;", ",", $com);

  // and get the first X chars of it, making sure that words don't get cut off
  // all-caps subjects are wider
  if ($com == strtoupper($com)) {
    $com = preg_replace('{^(.{20,}?)(?:[\s\n.]|$).*}','$1',$com);
    
    if (strlen($com) >= 20) {
      $com .= '...';
    }
  }
  else {
    $com = preg_replace('{^(.{30,}?)(?:[\s\n.]|$).*}','$1',$com);
    
    if (strlen($com) >= 30) {
      $com .= '...';
    }
  }
  // defeat superlong words
  if (mb_strlen($com) > 43) {
    $com = mb_substr($com, 0, 40) . "...";
  }
  
  // escape html entities
  if ($com) {
    $com = strip_tags($com);
    return htmlspecialchars($com, ENT_QUOTES);
  }
  
  if ($post['name'] && mb_strlen($post['name'] < 40)) {
    return strip_tags("No.{$post['no']} by {$post['name']}");
  }
  
  return "No.{$post['no']}";
}

function calc_thumbnail_size($post, $max_size = 150) {
  $w = $post['tn_w'];
  $h = $post['tn_h'];
  
  if ($w > $max_size) {
    $ratio = $max_size / (float)$w;
    $w = $max_size;
    $h = round($h * $ratio);
  }
  
  if ($h > $max_size) {
    $ratio = $max_size / (float)$h;
    $h = $max_size;
    $w = round($w * $ratio);
  }
  
  return array($w, $h);
}

$top_box_id[1] = 'popular-threads';

if (IS_4CHANNEL) {
	$top_box_button[1] = '';
}
else {
	$top_box_button[1] = '<div id="opts-btn" data-cmd="opts">options ▼</div>';
}

function top_box_title_1() {
?>Popular Threads<?
}

function top_box_content_1($async = false) {
  global $info, $options, $boards_flat;
  $posts = unserialize(file_get_contents('data/.popular_threads.cgi'));
  shuffle($posts);
  
  $total = 0;
  $max_threads = 8;
  $count = count($posts);
  
  $dup_boards = array();
  
  echo '<div id="c-threads">';
  
  for ($i = 0; $i < $count && $total < $max_threads; $i++) {
    $posts[$i]['domain'] = $info[$posts[$i]['board']]['domain'];
    $ws = $boards_flat[$posts[$i]['board']]['nws'] ? 'nws' : 'ws';
    
    if ($options['content'] != $ws && $options['content'] != 'all') {
      continue;
    }
    
    $picky = ($count - $i) > ($max_threads - $total);
    
    if ($picky && isset($dup_boards[$posts[$i]['board']])) {
      continue;
    }
    
    $dup_boards[$posts[$i]['board']] = true;
    
    $url = '//' . $posts[$i]['domain'] . '.' . L::d($posts[$i]['board']) . '/'
      . $posts[$i]['board'] . '/thread/' . $posts[$i]['no'];
    
    $thumb = '//i.4cdn.org/' . $posts[$i]['board'] . '/' . $posts[$i]['tim'] . 's.jpg';
    
    $teaser = '';
    
    if ($posts[$i]['sub'] !== '') {
      $teaser .= '<b>' . $posts[$i]['sub'] . '</b>';
    }
    
    if ($posts[$i]['com'] !== '') {
      if ($teaser !== '') {
        $teaser .= ': ';
      }
      
      $teaser .= format_comment($posts[$i]['com']);
    }
    
    list($w, $h) = calc_thumbnail_size($posts[$i]);
    
    ?>
<div class="c-thread"><div class="c-board"><?php echo htmlspecialchars($info[$posts[$i]['board']]['title']) ?></div><a href="<?php echo $url; ?>" class="boardlink"><img alt="" class="c-thumb" src="<?php echo $thumb ?>" width="<?php echo $w ?>" height="<?php echo $h ?>"></a><div class="c-teaser"><?php echo $teaser ?></div></div>
    <?
    $total++;
    $posts[$i]['type'] = $ws;
  }
  
  echo '</div>';
}

$top_box_id[2] = 'site-stats';

function top_box_title_2() {
?>Stats<?
}

function top_box_content_2($async = false) {
	$stats = unserialize(file_get_contents('data/.stats.cgi'));
?>
<div class="stat-cell"><b>Total Posts:</b> <?php echo number_format($stats['post_total']) ?></div>
<div class="stat-cell"><b>Current Users:</b> <?php echo number_format($stats['ips_total']) ?></div>
<div class="stat-cell"><b>Active Content:</b> <?php echo (int)($stats['size_total']/1024/1024/1024) ?> GB</div>

<?
}

function bottom_ad_728x90() { /*
?>
<script type="text/javascript">
	atOptions = {
		'key' : '460b6aa5e0f6699fc014b783299dad5a',
		'format' : 'iframe',
		'height' : 90,
		'width' : 728,
		'params' : {}
	};
	document.write('<scr' + 'ipt type="text/javascript" src="http' + (location.protocol === 'https:' ? 's' : '') + '://www.bnhtml.com/invoke.js"></scr' + 'ipt>');
</script><? */
}

$include_yui = false;
function external_script() {
?>//s.4cdn.org/js/frontpage.min.7.js<?
}

function inline_script() {
global $show_announce, $options;

// these have to match the order of the menu items (which could be different from the valid_options at the top

$opts_json = array();
if (isset($options['filter_boards'])) {
  $opts_json['fpb'] = $options['filter_boards'];
}
else {
  $opts_json['fpb'] = 'all';
}

if (isset($options['filter_boards'])) {
  $opts_json['fpc'] = $options['content'];
}
else {
  $opts_json['fpc'] = 'ws';
}
if ($options['4chan_frames']) {
  $opts_json['4chan_frames'] = 1;
}
if ($options['use_catalog']) {
  $opts_json['fpcat'] = 1;
}

echo "var Opts = " . json_encode($opts_json) . ";";

}

if($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
	ob_start();
		top_box_content_0();
		$boardlist = str_replace("\n"," ", addslashes(ob_get_contents()));
	ob_end_clean();
	ob_start();
		top_box_content_1(true);
		$popularthreads = str_replace("\n"," ", addslashes(ob_get_contents()));
	ob_end_clean();
	header('Cache-Control: no-cache');

	if($_GET['filter_boards']) {
		echo 'YAHOO.util.Dom.getElementsByClassName("boxcontent","div","boards")[0].innerHTML="';
		echo ($boardlist);
		echo '";';
	}
	else if($_GET['content']) {
		echo 'YAHOO.util.Dom.getElementsByClassName("boxcontent","div","popular-threads")[0].innerHTML="';
		echo ($popularthreads);
		echo '";';
	}
	die('');
}
include 'frontpage_template-test.php';
