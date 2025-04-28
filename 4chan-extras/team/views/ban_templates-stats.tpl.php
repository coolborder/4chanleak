<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Ban Templates Stats</title>
  <link rel="stylesheet" type="text/css" href="/css/ban_templates.css">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-tips>
<header>
  <h1 id="title">Ban Templates Stats</h1>
</header>
<div id="menu" class="center-txt">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
<ul>
  <li><a href="<?php echo self::WEBROOT ?>?action=stats">All</a></li>
  <?php foreach ($this->all_boards as $board): ?>
  <li><a href="<?php echo self::WEBROOT ?>?action=stats&amp;board=<?php echo $board ?>"><?php echo $board ?></a></li>
  <?php endforeach ?>
</ul>
</div>
<div id="content">
<h4>BR Acceptance Rate for <?php if ($this->board !== 'global') { echo '/' . htmlspecialchars($this->board) . '/'; } else { echo 'global'; } ?> templates (%)</h4>
<table class="items-table compact-table">
<thead>
  <tr>
    <th></th>
    <th data-tip="All Boards">~</th>
    <?php foreach ($this->boards as $board): ?>
    <th class="s-col"><?php echo $board ?></th>
    <?php endforeach ?>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->global_templates as $tpl_id): $board_data = $this->board_acceptance_rates[$tpl_id]; ?>
  <tr>
    <th class="s-row"><?php echo ($tpl_name = $this->ban_templates[$tpl_id]) ?></th><?php if (isset($this->acceptance_rates[$tpl_id])): ?>
    <?php
    if ($this->acceptance_rates[$tpl_id] <= 50) {
      $cls = 'type-deny';
    }
    else if ($this->acceptance_rates[$tpl_id] <= 75) {
      $cls = 'type-other';
    }
    else {
      $cls = 'type-accept';
    }
    ?>
    <td class="row-all <?php echo $cls; ?>" data-tip="×<?php echo (int)$this->global_sample_sizes[$tpl_id] ?>"><?php echo $this->acceptance_rates[$tpl_id] ?></td>
    <?php else: ?>
    <td class="type-deny" data-tip="×<?php echo (int)$this->global_sample_sizes[$tpl_id] ?>"><?php if ($this->global_sample_sizes[$tpl_id]): ?>0<?php endif ?></td>
    <?php endif ?>
    <?php foreach ($this->boards as $board): ?>
    <?php if (isset($board_data[$board])): ?>
      <?php
      if ($board_data[$board] <= 50) {
        $cls = 'type-deny';
      }
      else if ($board_data[$board] <= 75) {
        $cls = 'type-other';
      }
      else {
        $cls = 'type-accept';
      }
      ?>
    <td class="<?php echo $cls; ?>" data-tip="<?php echo $tpl_name ?> on /<?php echo $board ?>/ ×<?php echo (int)$this->sample_sizes[$tpl_id][$board] ?>"><?php echo $board_data[$board]; ?>
    <?php else: ?>
    <td class="type-deny" data-tip="×<?php echo $tpl_name ?> on /<?php echo $board ?>/ ×<?php echo (int)$this->sample_sizes[$tpl_id][$board] ?>"><?php if ($this->sample_sizes[$tpl_id][$board]): ?>0<?php endif ?></td>
    <?php endif ?>
    </td>
    <?php endforeach ?>
  </tr>
  <?php endforeach ?>
</tbody>
<tfoot>
  <tr>
    <th></th>
    <th data-tip="All Boards">~</th>
    <?php foreach ($this->boards as $board): ?>
    <th><?php echo $board ?></th>
    <?php endforeach ?>
  </tr>
</tfoot>
</table>
</div>
<footer></footer>
</body>
</html>
