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
  <script type="text/javascript" src="/js/stats.js?5"></script>
  <?php if ($this->stats_mode): ?>
  <script id="stats-data" type="application/json"><?php echo $this->plot_data; ?></script>
  <?php endif ?>
</head>
<body data-global>
<header>
  <h1 id="title">Global Stats</h1>
</header>
<div id="menu">
  <div class="select-box"><select autocomplete="off" id="board-select">
  <option value="">Select a board</option>
  <option selected value="global">Global</option><?php if ($this->is_manager): ?>
  <option value="monthly">Monthly</option><?php endif ?>
  <?php foreach ($this->boards as $board => $title): ?>
  <option value="<?php echo $board ?>">/<?php echo $board ?>/ - <?php echo $title ?></option>
  <?php endforeach ?>
  </select></div>
</div>
<div id="content">
<div class="stat-section">
  <div class="stat-grp">
    <h4>Posts</h4>
    <table class="stat-table">
      <tr>
        <th>Live posts</th>
        <td id="stats-live-posts"></td>
      </tr>
      <tr>
        <th>Archived posts</th>
        <td id="stats-archived-posts"></td>
      </tr>
      <tr>
        <th><span class="wot" data-tip="Individual reports in the past <?php echo $this->pretty_ttl(self::TIME_RANGE_2 * 3600) ?>">Reports</span></th>
        <td id="stats-reports"></td>
      </tr>
    </table>
  </div>
  <div class="stat-grp">
    <h4><span class="wot" data-tip="Past <?php echo self::TIME_RANGE ?> hours">Reply Types</span></h4>
    <canvas id="reply-types-chart" width="140" height="140"></canvas>
  </div>
  <div class="stat-grp">
    <h4><span class="wot" data-tip="Live posts + <?php echo self::TIME_RANGE ?> hours of archives">File Types</span></h4>
    <canvas id="file-types-chart" width="140" height="140"></canvas>
  </div>
</div>
<div class="stat-section">
  <h4><span id="stats-new-threads" class="wot" data-tip="Past 24 hours">New Threads</span></h4>
  <canvas id="threads-chart" width="820" height="340"></canvas>
</div>
<div class="stat-section">
  <h4><span id="stats-new-replies" class="wot" data-tip="Past 24 hours">New Replies</span></h4>
  <canvas id="replies-chart" width="820" height="340"></canvas>
</div>
</div>
<footer></footer>
</body>
</html>
