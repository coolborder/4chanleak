<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>User Post Deletion Log</title>
  <link rel="stylesheet" type="text/css" href="/css/stafflog.css?5">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/userdellog.js"></script>
</head>
<body>
<header>
  <h1 id="title">User Post Deletion Log</h1>
</header>
<div id="menu"><form id="filter-form" action="">
<?php if (isset($this->url_params['board'])) { $q_board = $this->url_params['board']; } else { $q_board = null; } ?>
<ul>
  <li><span class="form-label">Board</span><div class="select-box"><select id="filter-board">
  <option>All</option>
  <?php foreach ($this->boards as $board): ?>
  <option<?php if ($q_board === $board) echo ' selected' ?>><?php echo $board ?></option>
  <?php endforeach ?>
  </select></div></li>
  <li><span class="form-label">Date</span><input value="<?php if (isset($this->url_params['date'])) echo htmlspecialchars($this->url_params['date']) ?>" type="text" placeholder="MM/DD[/YY]" id="filter-date"></li>
  <li><span class="form-label">Post No.</span><input value="<?php if (isset($this->url_params['post'])) echo (int)$this->url_params['post'] ?>" type="text" id="filter-post"></li>
  <li><span id="filter-apply" class="button button-light">Apply</span><button id="filter-submit" type="submit"></button></li>
</ul>
</form></div>
<table class="items-table items-table-compact">
<thead>
  <tr>
    <th>Date</th>
    <th>Board</th>
    <th>Post No.</th>
    <th>IP</th>
    <th>Country</th>
  </tr>
</thead>
<tbody id="items">
<?php foreach ($this->entries as $entry): ?>
  <tr>
    <td class="col-date"><?php echo $entry['date'] ?></td>
    <td class="col-board">/<?php echo $entry['board'] ?>/</td>
    <td class="col-post"><span class="as-iblk"><?php echo $entry['postno'] ?></span><?php if (isset($entry['link'])): ?> <a data-tip="Archive Link" target="_blank" href="https://www.4chan.org/derefer?url=<?php echo $entry['link'] ?>">→</a><?php endif ?></td>
    <td class="col-user"><?php echo $entry['ip'] ?></td>
    <td class="col-board"><?php echo $entry['country'] ?></td>
  </tr>
<?php endforeach ?>
</tbody>
<tfoot>
  <tr>
    <td colspan="5" class="page-nav"><?php if ($this->next_offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
</table>
<footer></footer>
</body>
</html>
