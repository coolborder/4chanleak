<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Ban Templates</title>
  <link rel="stylesheet" type="text/css" href="/css/ban_templates.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-inittip>
<header>
  <h1 id="title">Ban Templates</h1>
</header>
<div id="menu">
<ul class="right">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>?action=stats">Stats</a></li>
</ul>
<ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>?action=update">Add</a></li>
</ul>
</div>
<div id="content">
<table class="items-table">
<thead>
  <tr>
    <th>ID</th>
    <th>Rule</th>
    <th>Name</th>
    <th>Public Reason</th>
    <th>Ban Length</th>
    <th></th>
  </tr>
</thead>
<tbody id="items">
  <?php foreach ($this->templates as $tpl): ?>
  <tr id="tpl-<?php echo $tpl['no'] ?>">
    <td class="col-id"><?php echo $tpl['no'] ?></td>
    <td class="col-rule"><?php echo $tpl['rule'] ?></td>
    <td class="col-name"><?php echo $tpl['name'] ?></td>
    <td class="col-reason"><?php echo nl2br($tpl['publicreason']) ?></td>
    <td class="col-length"><div><?php
     if ($tpl['days'] < 0) {
       echo 'Permanent';
     }
    else if ($tpl['days'] == 0) {
      echo 'Warning';
    }
    else {
      echo $tpl['days'] . ' day' . $this->pluralize($rule['days']);
    }
    ?></div>
      <?php if ($tpl['bantype'] === 'global'): ?><div class="note">Global</div><?php endif ?>
    </td>
    <td class="col-meta"><a href="?action=update&amp;id=<?php echo $tpl['no'] ?>">Edit</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
