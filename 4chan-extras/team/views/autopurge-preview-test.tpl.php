<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Autopurge</title>
  <link rel="stylesheet" type="text/css" href="/css/autopurge.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <!--<script type="text/javascript" src="/js/autopurge.js"></script>-->
</head>
<body>
<header>
  <h1 id="title">Autopurge</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<?php foreach ($this->posts as $board => $posts): ?>
<h3>/<?php echo $board ?>/</h3>
<table class="items-table results-table">
<thead>
  <tr>
    <th></th>
    <th></th>
    <th></th>
    <th></th>
  </tr>
</thead>
<tbody id="items">
  <?php foreach ($posts as $post_id => $post): ?>
    <tr>
      <td><?php if ($post['ext']): ?>
        <img src="//i.4cdn.org/<?php echo $board ?>/<?php echo $post['tim'] ?>s.jpg">
      <?php endif ?></td>
      <td class="txt-left">
        <?php if ($post['name'] !== ''): ?><strong>Name: </strong><div><span><?php echo $post['name'] ?></span></div><hr><?php endif ?>
        <?php if ($post['sub'] !== ''): ?><strong>Subject: </strong><div><?php echo $post['sub'] ?></div><hr><?php endif ?>
        <?php if ($post['com'] !== ''): ?><strong>Comment: </strong><div><?php echo $post['com'] ?></div><?php endif ?>
      </td>
      <td class="txt-left"><ul>
          <li><strong>IP: </strong><?php echo $post['host'] ?></li>
          <li><strong>Country: </strong><?php echo $post['country'] ?></li>
          <li><strong>Thread ID: </strong><?php echo $post['resto'] ?></li><?php if ($post['ext']): ?>
          <li><strong>File Name: </strong><?php echo $post['filename'] ?><span class="st"><?php echo $post['ext'] ?></span></li>
          <li><strong>File Size: </strong><?php echo $post['w'] ?>&times;<?php echo $post['h'] ?>, <?php echo $post['fsize'] ?> bytes</li>
          <li><strong>MD5: </strong><?php echo $post['md5'] ?></li><?php endif ?>
        </ul>
      </td>
      <td><?php if ($post['resto']): ?>
        <a href="//<?php echo L::d($board) ?>.4chan.org/<?php echo $board ?>/thread/<?php echo $post['resto'].'#p'.$post['no'] ?>">/<?php echo $board ?>/<?php echo $post['no'] ?></a>
      <?php else: ?>
        <a href="//<?php echo L::d($board) ?>.4chan.org/<?php echo $board ?>/thread/<?php echo $post['no'] ?>">/<?php echo $board ?>/<?php echo $post['no'] ?> (OP)</a>
      <?php endif ?></td>
    </tr>
  <?php endforeach ?>
</tbody>
</table>
<?php endforeach ?>
</div>
<footer></footer>
</body>
</html>
