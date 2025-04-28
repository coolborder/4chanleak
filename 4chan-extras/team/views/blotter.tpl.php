<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Edit Blotter</title>
  <link rel="stylesheet" type="text/css" href="/css/blotter.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/blotter.js"></script>
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Edit Blotter</h1>
</header>
<div id="content">
<form id="post-form" action="" method="post">
  <input id="post-msg" type="text" name="content">
  <span data-cmd="preview" class="button btn-other">Preview</span>
  <span data-cmd="submit" class="button btn-accept">Submit</span>
  <div id="preview"></div>
</form>
<table id="blotter-table" class="items-table">
<thead>
  <tr>
    <th>Date</th>
    <th>Message</th>
    <th>Author</th>
    <th></th>
  </tr>
</thead>
<tbody id="items">
  <?php foreach ($this->messages as $message): ?>
  <tr id="item-<?php echo $message['id'] ?>">
    <td class="col-date"><?php echo date('m/d/Y H:i', $message['date']) ?></td>
    <td class="col-msg"><?php echo $message['content'] ?></td>
    <td class="col-author"><?php echo $message['author'] ?></td>
    <td class="col-ctrl"><span data-id="<?php echo $message['id'] ?>" data-cmd="delete" class="button btn-deny">Delete</span></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
