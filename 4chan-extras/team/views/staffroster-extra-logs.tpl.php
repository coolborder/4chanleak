<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Extra Logs</title>
  <link rel="stylesheet" type="text/css" href="/css/staffroster.css?15">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/staffroster.js?8"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-tips>
<header>
  <h1 id="title">Extra Logs</h1>
</header>
<div id="menu"><ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>/staffroster">Roster</a></li>
  <li><a class="button button-light" href="?action=activity">Activity</a></li>
  <li><a class="button button-light" href="?action=scoreboard">Scoreboard</a></li>
  <li><a class="button button-light" href="?action=manage">Manage</a></li>
  <li><a class="button button-light" href="?action=flags">Flags</a></li>
  <li><a class="button button-light" href="?action=j_names">/j/ Names</a></li>
  <li><a class="button button-light" href="?action=check_email">Check E-Mail</a></li>
</ul></div>
<div id="content">
<div class="jump-menu"><ul>
  <li><a href="#clr">Clears (<?php echo count($this->data_clr) ?>)</a></li>
  <li><a href="#del">Deletions (<?php echo count($this->data_del) ?>)</a></li>
  <li><a href="#superdel">Super Deletions (<?php echo count($this->data_super_del) ?>)</a></li>
</ul></div>
<?php if (!empty($this->data_clr)): ?>
<div class="log-cnt">
<h3 id="clr">Cleared Reports for Own Posts</h3>
<?php foreach ($this->data_clr as $item): $post = $item['meta'] ;?>
<table>
  <tr><th>Date</th><td><?php echo $item['created_on'] ?></td></tr>
  <tr><th>Post</th><td><span class="as-iblk">/<?php echo $item['board'] ?>/<?php echo (int)$item['post_id'] ?></span><?php if ($item['arc']): ?> <a target="_blank" href="<?php echo $item['arc'] ?>">Archive</a><?php endif ?></td></tr>
  <?php if (isset($post['fsize']) && $post['fsize'] > 0): ?>
  <tr><th>File</th><td><?php echo $post['filename'].$post['ext'] ?></td></tr>
  <tr><th>File MD5</th><td><?php echo bin2hex(base64_decode($post['md5'])) ?></td></tr>
  <?php endif ?>
  <tr><th>Name</th><td><span><?php echo $post['name'] ?></span></td></tr>
  <?php if (isset($post['sub']) && $post['sub'] !== ''): ?><tr><th>Subject</th><td><?php echo $post['sub'] ?></td></tr><?php endif ?>
  <?php if (isset($post['com']) && $post['com'] !== ''): ?><tr><th>Comment</th><td class="pierce"><?php echo $post['com'] ?></td></tr><?php endif ?>
</table>
<?php endforeach ?>
</div>  
<?php endif ?>
<?php if (!empty($this->data_del)): ?>
<div class="log-cnt">
<h3 id="del">Deleted Own Posts</h3>
<?php foreach ($this->data_del as $item): $post = $item['meta'] ;?>
<table>
  <tr><th>Date</th><td><?php echo $item['created_on'] ?></td></tr>
  <tr><th>Post</th><td><span class="as-iblk">/<?php echo $item['board'] ?>/<?php echo (int)$item['post_id'] ?></span><?php if ($item['arc']): ?> <a target="_blank" href="<?php echo $item['arc'] ?>">Archive</a><?php endif ?></td></tr>
  <?php if (isset($post['fsize']) && $post['fsize'] > 0): ?>
  <tr><th>File</th><td><?php echo $post['filename'].$post['ext'] ?></td></tr>
  <tr><th>File MD5</th><td><?php echo bin2hex(base64_decode($post['md5'])) ?></td></tr>
  <?php endif ?>
  <tr><th>Name</th><td><span><?php echo $post['name'] ?></span></td></tr>
  <?php if (isset($post['sub']) && $post['sub'] !== ''): ?><tr><th>Subject</th><td><?php echo $post['sub'] ?></td></tr><?php endif ?>
  <?php if (isset($post['com']) && $post['com'] !== ''): ?><tr><th>Comment</th><td class="pierce"><?php echo $post['com'] ?></td></tr><?php endif ?>
</table>
<?php endforeach ?>
</div>
<?php endif ?>
<?php if (!empty($this->data_super_del)): ?>
<div class="log-cnt">
<h3 id="superdel">Deletions Outside of Assigned Boards</h3>
<?php foreach ($this->data_super_del as $item): ?>
<table>
  <tr><th>Date</th><td><?php echo $item['created_on'] ?></td></tr>
  <tr><th>Post</th><td><span class="as-iblk">/<?php echo $item['board'] ?>/<?php echo (int)$item['post_id'] ?></span><?php if ($item['arc']): ?> <a target="_blank" href="<?php echo $item['arc'] ?>">Archive</a><?php endif ?></td></tr>
  <?php if ($item['filename'] !== ''): ?>
  <tr><th>File</th><td><?php echo $item['filename'] ?></td></tr>
  <?php endif ?>
  <?php if ($item['imgonly']): ?><tr><th>Image Only</th><td>Yes</td></tr><?php endif ?>
  <tr><th>Name</th><td><span><?php echo $item['name'] ?></span></td></tr>
  <?php if (isset($item['sub']) && $item['sub'] !== ''): ?><tr><th>Subject</th><td><?php echo $item['sub'] ?></td></tr><?php endif ?>
  <?php if (isset($item['com']) && $item['com'] !== ''): ?><tr><th>Comment</th><td class="pierce"><?php echo $item['com'] ?></td></tr><?php endif ?>
</table>
<?php endforeach ?>
</div>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
