<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Board Stats<?php echo $this->page_title ?></title>
  <link rel="stylesheet" type="text/css" href="/css/stats.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/Chart.min.js"></script>
  <script type="text/javascript" src="/js/stats.js?2"></script>
  <script id="monthly-data" type="application/json"><?php echo $this->plot_data; ?></script>
</head>
<body data-monthly>
<header>
  <h1 id="title">Monthly Stats</h1>
</header>
<div id="menu">
  <div class="select-box"><select autocomplete="off" id="board-select">
  <option value="">Select a board</option>
  <option value="global">Global</option><?php if ($this->is_manager): ?>
  <option selected value="monthly">Monthly</option><?php endif ?>
  <?php foreach ($this->boards as $board => $title): ?>
  <option value="<?php echo $board ?>">/<?php echo $board ?>/ - <?php echo $title ?></option>
  <?php endforeach ?>
  </select></div>
</div>
<div id="content">
<div class="stat-section">
  <h4>Posts Per Month</h4>
  <canvas id="monthly-chart" width="820" height="340"></canvas>
</div>
<footer></footer>
</body>
</html>
