<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Staff Log</title>
  <link rel="stylesheet" type="text/css" href="/css/stafflog.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/stafflog-test.js?3"></script>
</head>
<body>
<header>
  <h1 id="title">Staff Log</h1>
</header>
<div id="menu"><form id="filter-form" action="">
<?php
  if (isset($this->url_params['type'])) { $q_type = $this->url_params['type']; } else { $q_type = null; }
  if (isset($this->url_params['user'])) { $q_user = $this->url_params['user']; } else { $q_user = null; }
  if (isset($this->url_params['board'])) { $q_board = $this->url_params['board']; } else { $q_board = null; }
?>
<ul>
  <li><span class="form-label">Action</span><div class="select-box"><select id="filter-type">
  <?php foreach ($this->actionLabels as $key => $value): ?>
  <option <?php if ($q_type === $key) echo 'selected ' ?>value="<?php echo $key ?>"><?php echo $value ?></option>
  <?php endforeach ?>
  </select></div></li>
  <li><span class="form-label">User</span><div class="select-box"><select id="filter-user">
  <option>All</option>
  <option<?php if ($q_user === 'autopurge') echo ' selected' ?>>autopurge</option>
  <?php foreach ($this->users as $username): ?>
  <option<?php if ($q_user === $username) echo ' selected' ?>><?php echo $username ?></option>
  <?php endforeach ?>
  </select></div></li>
  <li><span class="form-label">Board</span><div class="select-box"><select id="filter-board">
  <option>All</option>
  <?php foreach ($this->boards as $board): ?>
  <option<?php if ($q_board === $board) echo ' selected' ?>><?php echo $board ?></option>
  <?php endforeach ?>
  </select></div></li>
  <li><span class="form-label">Post No.</span><input value="<?php if (isset($this->url_params['post'])) echo (int)$this->url_params['post'] ?>" type="text" id="filter-post"></li>
  <li><span class="form-label">Date</span><input value="<?php if (isset($this->url_params['date'])) echo htmlspecialchars($this->url_params['date']) ?>" type="text" placeholder="MM/DD[/YY]" id="filter-date"></li>
  <li><label><span class="form-label">OPs Only</span><input<?php if (isset($this->url_params['ops'])) echo ' checked' ?> type="checkbox" id="filter-ops"></label></li>
  <li><label><span data-tip="Skip automatic deletions" class="form-label">Manual Only</span><input<?php if (isset($this->url_params['manual'])) echo ' checked' ?> type="checkbox" id="filter-manual"></label></li>
  <li><span id="filter-apply" class="button button-light">Apply</span><button id="filter-submit" type="submit"></button></li>
</ul>
</form></div>
<table id="log-table" class="items-table">
<thead>
  <tr>
    <th>Date</th>
    <th>Action</th>
    <th>Board</th>
    <th>Post</th>
    <th>User</th>
  </tr>
</thead>
<tbody id="items">
<?php foreach ($this->entries as $entry): $action = $this->formatAction($entry); ?>
  <tr>
    <td class="col-date"><?php echo $entry['date'] ?></td>
    <td class="col-action"><?php if ($entry['template_id'] && isset($this->templates[$entry['template_id']])): ?>
      <a href="/bans?action=search&amp;board=<?php echo $entry['board'] ?>&amp;post_id=<?php echo $entry['postno'] ?>" data-tip="<?php echo $this->templates[$entry['template_id']] ?>" class="via-template"><?php echo $action ?></a>
    <?php else: ?>
      <span><?php echo $action ?></span>
    <?php endif ?>
    </td>
    <td class="col-board">/<?php echo $entry['board'] ?>/</td>
    <td class="col-post"><span class="post-no">No.<span class="as-iblk"><?php echo $entry['postno'] ?></span> <?php if (isset($entry['link'])): ?><a data-tip="Archive Link" target="_blank" href="https://www.4chan.org/derefer?url=<?php echo rawurlencode($entry['link']) ?>">→</a><?php endif ?></span><?php if (!$entry['resto']): ?><span class="post-isop">OP</span><?php endif ?><div><span class="name"><?php echo $entry['name'] ?><?php if ($entry['tripcode']): ?><span class="tripcode">!<?php echo $entry['tripcode'] ?></span><?php endif ?></span><?php if ($entry['filename']): ?><span class="filename"><?php echo $entry['filename'] ?></span><?php endif ?></div><?php if ($entry['sub']): ?><div class="subject"><?php echo $entry['sub'] ?></div><?php endif ?><div class="comment"><?php echo $entry['com'] ?></div></td>
    <td class="col-user"><?php echo htmlspecialchars($entry['admin']) ?></td>
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
