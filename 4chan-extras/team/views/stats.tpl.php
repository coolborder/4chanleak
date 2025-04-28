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
<body data-page="index" <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Board Stats<?php echo $this->page_title ?></h1>
</header>
<div id="menu">
  <div class="select-box"><select autocomplete="off" id="board-select">
  <option value="">Select a board</option><?php if ($this->is_manager): ?>
  <option value="global">Global</option>
  <option value="monthly">Monthly</option><?php endif ?>
  <?php foreach ($this->boards as $board => $title): ?>
  <option value="<?php echo $board ?>"<?php if ($this->stats_mode && $this->active_board === $board): ?> selected="selected"<?php endif ?>>/<?php echo $board ?>/ - <?php echo $title ?></option>
  <?php endforeach ?>
  </select></div>
</div>
<div id="content">
<?php if ($this->stats_mode): ?>
<div class="stat-section">
  <div class="stat-grp">
    <h4>Posts</h4>
    <table class="stat-table">
      <tr>
        <th>Live posts</th>
        <td><?php echo number_format($this->post_stats['live_posts']) ?></td>
      </tr>
      <tr>
        <th>Archived posts</th>
        <td><?php echo number_format($this->post_stats['archived_posts']) ?></td>
      </tr>
      <?php if ($this->ua_stats && $this->ua_stats['1'] > 0 && $this->post_stats['live_posts'] > 0): ?>
      <tr>
        <th>Mobile UA</th>
        <td><?php echo number_format($this->ua_stats['1']) ?> (<?php echo round(($this->ua_stats['1'] / $this->post_stats['live_posts']) * 100, 2) ?>%)</td>
      </tr>
      <?php endif ?>
      <tr>
        <th><span class="wot" data-tip="For posts made in the past <?php echo self::TIME_RANGE_3 ?> hours">Unique IPs</span></th>
        <td><?php echo number_format($this->post_stats['unique_ips']) ?></td>
      </tr>
      <tr>
        <th><span class="wot" data-tip="Individual reports in the past <?php echo $this->pretty_ttl(self::TIME_RANGE_2 * 3600) ?>">Reports</span></th>
        <td><?php echo number_format($this->mod_stats['reports']) ?></td>
      </tr>
      <tr>
        <th><span class="wot" data-tip="Past <?php echo $this->pretty_ttl(self::TIME_RANGE_2 * 3600) ?>">Deletions</span></th>
        <td><?php echo number_format($this->mod_stats['deletions']) ?></td>
      </tr>
    </table>
  </div>
  <div class="stat-grp">
    <h4>Threads</h4>
    <table class="stat-table">
      <tr>
        <th><span class="wot" data-tip="Time before a thread is archived. Based on <?php echo $this->pretty_ttl(self::TIME_RANGE_2 * 3600) ?> of archives">Thread Age</span></th>
        <td></td>
      </tr>
      <tr>
        <th><span class="subgrp">Min</span></th>
        <td><?php echo $this->pretty_ttl($this->post_stats['thread_ttl']['min_ttl']) ?></td>
      </tr>
      <?php if ($this->post_stats['thread_ttl']['min_ttl_low']): ?>
      <tr>
        <th><span class="subgrp">Min <small>(1% lows)</small></span></th>
        <td><?php echo $this->pretty_ttl($this->post_stats['thread_ttl']['min_ttl_low']) ?></td>
      </tr>
      <?php endif ?>
      <tr>
        <th><span class="subgrp">Avg</span></th>
        <td><?php echo $this->pretty_ttl($this->post_stats['thread_ttl']['avg_ttl']) ?></td>
      </tr>
      <tr>
        <th><span class="subgrp">Max</span></th>
        <td><?php echo $this->pretty_ttl($this->post_stats['thread_ttl']['max_ttl']) ?></td>
      </tr>
      <tr>
        <th><span class="wot" data-tip="Time since last bump before a thread is archived. Based on <?php echo $this->pretty_ttl(self::TIME_RANGE * 3600) ?> of archived threads below bump limit">Inactivity Time</span></th>
        <td></td>
      </tr>
      <?php if ($this->ttl_stats): ?>
      <tr>
        <th><span class="subgrp">Min</span></th>
        <td><?php echo $this->pretty_ttl($this->ttl_stats['min_ttl']) ?></td>
      </tr>
      <tr>
        <th><span class="subgrp">Min <small>(1% lows)</small></span></th>
        <td><?php echo $this->pretty_ttl($this->ttl_stats['min_ttl_low']) ?></td>
      </tr>
      <?php endif ?>
      <tr>
        <th><span class="wot" data-tip="Based on live threads and <?php echo $this->pretty_ttl(self::TIME_RANGE * 3600) ?> of archived threads">Thread Size</span></th>
        <td></td>
      </tr>
      <tr>
        <th><span class="subgrp">Median</span></th>
        <td><?php echo $this->thread_size_stats['med'] ?></td>
      </tr>
      <tr>
        <th><span class="subgrp">Average</span></th>
        <td><?php echo $this->thread_size_stats['avg'] ?></td>
      </tr>
    </table>
  </div>
  <div class="stat-grp">
    <h4><span class="wot" data-tip="Past <?php echo self::TIME_RANGE ?> hours">Reply Types</span></h4>
    <canvas id="reply-types-chart" width="140" height="140"></canvas>
  </div>
  <div class="stat-grp">
    <h4><span class="wot" data-tip="Past <?php echo self::TIME_RANGE ?> hours">File Types</span></h4>
    <canvas id="file-types-chart" width="140" height="140"></canvas>
  </div>
</div>
<div class="stat-section">
  <h4><span class="wot" data-tip="Past 24 hours">New Threads (<?php echo number_format($this->posting_rate['total_threads']) ?>)</span></h4>
  <canvas id="threads-chart" width="820" height="340"></canvas>
</div>
<div class="stat-section">
  <h4><span class="wot" data-tip="Past 24 hours">New Replies (<?php echo number_format($this->posting_rate['total_replies']) ?>)</span></h4>
  <canvas id="replies-chart" width="820" height="340"></canvas>
</div>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
