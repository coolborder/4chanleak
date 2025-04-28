<?php
function title() {
 echo "News - 4chan";
}


function iOSmeta() {
 echo '<meta name="apple-itunes-app" content="app-id=1124861180, affiliate-data=4chan" />
';
}


function stylesheet() {
?>//s.4cdn.org/css/news.12.css<?
}

$top_box_count = 1;
function top_box_title_0() {
?>News<?
}

function top_box_content_0() { ?>
  <script type="text/javascript">
  if (document.location.protocol === "http:" && window.matchMedia && (window.matchMedia('(max-width: 480px)').matches
    || window.matchMedia('(max-device-width: 480px)').matches)) {
    document.write('<div style="margin: 10px 0"><scr' + 'ipt type="text/javascript" src="http://js.medi-8.net/t/082/340/a1082340.js"></scr' + 'ipt></div>');
  }
  </script>
  <?
	if($_SERVER['QUERY_STRING']=="all") $all=1;
	if(!$all) {
	  $file="data/newscontent.html";
	} else {
	  $file="data/newscontent_all.html";
	}

	echo file_get_contents($file);

	if(!$all) { ?>
	<div class="content" id="oldnews" style="text-align: center;">
	<h3>OLDER NEWS...</h3>
	<br />
	<span style="font-size: larger;"><a href="?all#oldnews">Click here</a> to view all news posts.</span>
	</div>



<?
	}
}

function bottom_ad_728x90() {
?>

<script type="text/javascript">
var ad_idzone = "1742748",
	 ad_width = "728",
	 ad_height = "90";
</script>
<script type="text/javascript" src="https://ads.exoclick.com/ads.js"></script>
<noscript><a href="http://main.exoclick.com/img-click.php?idzone=1742748" target="_blank"><img src="https://syndication.exoclick.com/ads-iframe-display.php?idzone=1742748&output=img&type=728x90" width="728" height="90"></a></noscript>

<?
}



$left_box_count = 0;

$right_box_count = 0;

include 'frontpage_template.php';
