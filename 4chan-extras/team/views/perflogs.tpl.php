<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Posting perf</title>
  <link rel="stylesheet" type="text/css" href="/css/iprangebans.css?10">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Posting perf</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="?board=a">/a/</a></li>
  <li><a class="button button-light" href="?board=v">/v/</a></li>
</ul>
</div>
<div id="content">
<h3>/<?php echo $this->board ?>/</h3>
<table class="items-table" id="items">
<thead>
  <tr>
    <th class="col-id">F</th>
    <th>Total</th>
    <th>Min.</th>
    <th>Avg.</th>
    <th>Max.</th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->counts as $label => $count): ?>
  <tr>
    <td><i><?php echo $label ?></i></td>
    <td><?php echo round($this->totals[$label], $this->precision) ?> (<?php echo round($this->totals[$label] * (100 / $this->total_avg), 2) ?>%)</td>
    <td><?php echo round($this->min[$label], $this->precision) ?></td>
    <td><?php echo round($this->totals[$label] / $count, $this->precision) ?></td>
    <td><?php echo round($this->max[$label], $this->precision) ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<h4>Total number of entries: <b><?php echo $this->total_entries ?></b></h4>
<h4>Average time to "Post Successful": <b><?php echo round($this->total_avg / $this->total_entries, $this->precision) ?></b></h4>
<?php foreach ($this->type_avg as $ext => $time): ?>
<h4> - for <?php echo $ext ?> posts: <b><?php echo round($time / $this->type_counts[$ext], $this->precision) ?></b></h4>
<?php endforeach ?>
</div>
<footer></footer>
</body>
</html>
