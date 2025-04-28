<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Report Categories</title>
  <link rel="stylesheet" type="text/css" href="/css/report_categories.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Report Categories</h1>
</header>
<div id="menu" class="center-txt">
<ul>
  <li><a class="button button-light" href="?action=update">Add Category</a></li>
  <li><a class="button button-light" href="?action=settings">Board Coefficients</a></li>
</ul>
</div>
<div id="content">
<table class="items-table items-table-compact">
<thead>
  <tr>
    <th>ID</th>
    <th>Board</th>
    <th>Title</th>
    <th>Weight</th>
    <th>Options</th>
    <th></th>
  </tr>
</thead>
<tbody id="items">
  <?php foreach ($this->categories as $cat): ?>
  <tr>
    <td class="col-id"><?php echo $cat['id'] ?></td>
    <td class="col-board"><?php echo $cat['board'] ?></td>
    <td class="col-title"><?php echo $cat['title'] ?></td>
    <td class="col-meta"><?php echo (float)$cat['weight'] ?></td>
    <td class="col-options"><ul class="note"><?php if ($cat['op_only']): ?><li class="val-blue">OPs only</li><?php endif ?><?php if ($cat['reply_only']): ?><li class="val-blue">Replies only</li><?php endif ?><?php if ($cat['image_only']): ?><li class="val-blue">Images only</li><?php endif ?><?php if ($cat['filtered']): ?><li class="val-green">Filtered</li><?php endif ?></ul><?php if ($cat['exclude_boards']): ?><ul class="note"><li class="val-hdr val-bold">Not on</li><li><?php echo implode('</li><li>', explode(',', $cat['exclude_boards'])) ?></li></ul>
      
    <?php endif ?></td>
    <td class="col-meta"><a href="?action=update&amp;id=<?php echo $cat['id'] ?>">Edit</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
