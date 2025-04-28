<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Team 4chan</title>
  <link rel="stylesheet" type="text/css" href="/css/index.css?7">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/dashboard.js?2"></script>
</head>
<body data-tips>
<header>
  <h1 id="title">Overview</h1>
</header>
<div id="content">
<div class="widget-cnt">
<div class="widget">
  <div class="widget-hdr"><span>Self Clears</span><span class="qot" data-tip="Janitors clearing reports for their own posts (top 10, past <?php echo self::STAFF_STATS_DAYS ?> days)">?</span></div>
  <div id="js-widget-clr">Loading&hellip;</div>
</div>
<div class="widget">
  <div class="widget-hdr"><span>Self Deletions</span><span class="qot" data-tip="Janitors deleting their own posts (top 10, past <?php echo self::STAFF_STATS_DAYS ?> days)">?</span></div>
  <div id="js-widget-del">Loading&hellip;</div>
</div>
<div class="widget">
  <div class="widget-hdr"><span>Super Deletions</span><span class="qot" data-tip="Janitors deleting posts without a BR outside of their assigned boards (top 10, past <?php echo self::STAFF_STATS_DAYS ?> days)">?</span></div>
  <div id="js-widget-fence_skip">Loading&hellip;</div>
</div>
</div>
</div>
</body>
</html>
