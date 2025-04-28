<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Board Stats</title>
  <link rel="stylesheet" type="text/css" href="/css/stats.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Board Stats</h1>
</header>
<div id="content">
  <table class="stat-table">
    <tr><th>Board</th><td>/v/</td></tr>
    <tr><th>Start time</th><td><?php echo $this->time_lim; ?></td></tr>
    <tr><th></th><td></td></tr>
    <tr><th>Total posts</th><td><?php echo $this->total_posts; ?></td></tr>
    <tr><th>Total unique IPs</th><td><?php echo $this->total_ips; ?></td></tr>
    <tr><th></th><td></td></tr>
    <tr><th>Banned unique IPs</th><td><?php echo $this->total_bans; ?></td></tr>
    <tr><th><span class="wot" title="non-permanent only, no ops only, no img only">Rangebanned unique IPs</span></th><td><?php echo $this->total_rangebans; ?></td></tr>
    <tr><th></th><td></td></tr>
    <tr><th>Ban rate vs posts</th><td><?php echo round(($this->total_bans / ($this->total_posts + $this->total_bans)) * 100, 2) ?> %</td></tr>
    <tr><th>Ban rate vs unique IPs</th><td><?php echo round(($this->total_bans / ($this->total_ips + $this->total_bans)) * 100, 2) ?> %</td></tr>
    <tr><th></th><td></td></tr>
    <tr><th>Rangeban rate vs posts</th><td><?php echo round(($this->total_rangebans / ($this->total_posts + $this->total_rangebans)) * 100, 2) ?> %</td></tr>
    <tr><th>Rangeban rate vs unique IPs</th><td><?php echo round(($this->total_rangebans / ($this->total_ips + $this->total_rangebans)) * 100, 2) ?> %</td></tr>
    <tr><th></th><td></td></tr>
    <tr><th>Ban+Rangeban rate vs posts</th><td><?php echo round((($this->total_rangebans + $this->total_bans) / ($this->total_posts + $this->total_rangebans + $this->total_bans)) * 100, 2) ?> %</td></tr>
    <tr><th>Ban+Rangeban rate vs unique IPs</th><td><?php echo round((($this->total_rangebans + $this->total_bans) / ($this->total_ips + $this->total_rangebans + $this->total_bans)) * 100, 2) ?> %</td></tr>
  </table>
  
  <h3>Rangebans (temporary, img only or ops only)</h3>
  <table class="stat-table">
    <tr>
      <th>CIDR</th>
      <th>Description</th>
      <th>Hit count</th>
    </tr>
    <?php foreach ($this->rangebans_meta as $cidr => $count): ?>
    <tr>
      <td><?php echo $cidr ?></td>
      <td><?php echo $this->rangebans_desc[$cidr] ?></td>
      <td><?php echo $count ?></td>
    </tr>
    <?php endforeach ?>
  </table>
</div>
<footer></footer>
</body>
</html>
