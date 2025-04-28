<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Board Weights - Report Categories</title>
  <link rel="stylesheet" type="text/css" href="/css/report_categories.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Board Weights - Report Categories</h1>
</header>
<div id="menu" class="center-txt">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
<ul>
  <li><a class="button button-light" href="?action=settings_update">Add Coefficients</a></li>
</ul>
</div>
<div id="content">
<table class="items-table items-table-compact">
<thead>
  <tr>
    <th>ID</th>
    <th>Boards</th>
    <th>Coefficient</th>
    <th></th>
  </tr>
</thead>
<tbody id="items">
  <?php foreach ($this->entries as $entry): ?>
  <tr>
    <td class="col-id"><?php echo $entry['id'] ?></td>
    <td class="col-board"><?php echo $entry['boards'] ?></td>
    <td class="col-title"><?php echo $entry['coef'] ?></td>
    <td class="col-meta"><a href="?action=settings_update&amp;id=<?php echo $entry['id'] ?>">Edit</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
