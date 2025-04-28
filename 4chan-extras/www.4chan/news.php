<?php

die();

define('IS_4CHANNEL', preg_match('/(^|\.)4channel.org$/', $_SERVER['HTTP_HOST']));

function title() {
 echo "News - 4chan";
}

function stylesheet() {
  if (IS_4CHANNEL) {
    echo('//s.4cdn.org/css/news_blue.1.css');
  }
  else {
    echo('//s.4cdn.org/css/news.12.css');
  }
}

$top_box_count = 1;
function top_box_title_0() {
?>News<?
}

function top_box_content_0() {
  if (isset($_GET['all'])) $all=1;
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


$left_box_count = 0;

$right_box_count = 0;

include 'frontpage_template.php';
