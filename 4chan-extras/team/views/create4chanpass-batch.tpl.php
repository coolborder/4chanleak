<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Create 4chan Passes</title>
  <link rel="stylesheet" type="text/css" href="/css/addaccount.css?3">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Create 4chan Passes</h1>
</header>
<div id="menu"><ul>
  <li><a class="button button-light" href="/admin/create4chanpass">Return</a></li>
</ul>
<div id="content">
<?php if (isset($this->user_hash)): ?>
<div id="success">Done.</div>
<?php else: ?>
<form id="form-add-account" autocomplete="off" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr>
      <th>Number of Passes to generate</th>
      <td><input class="batch-size" required pattern="[0-9]+" type="text" name="batch_size"> (max. <?php echo self::MAX_BATCH_SIZE ?>)</td>
    </tr>
    <tr>
      <th>Description (optional)</th>
      <td><input type="text" name="description"></td>
    </tr>
    <tr class="otp-row">
      <th><label for="otp" title="One-Time Password">2FA OTP</label></th>
      <td><input id="otp" maxlength="6" required type="text" name="otp"></td>
    </tr>
  </table><input type="hidden" name="action" value="create_batch">
  <button class="button btn-other" type="submit">Create</button><?php echo csrf_tag() ?>
</form>
<?php if (!empty($this->batches)): ?>
<table class="items-table" id="items">
<thead>
  <tr>
    <th class="col-id">Batch ID</th>
    <th class="col-size">Size</th>
    <th>Description</th>
    <th class="col-date">Created on</th>
    <th class="col-author">Created by</th>
    <th class="col-sel"></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->batches as $batch): ?>
  <tr>
    <td><?php echo $batch['batch_id'] ?></td>
    <td class="col-size"><?php echo $batch['size'] ?></td>
    <td><?php echo $batch['description'] ?></td>
    <td><?php echo date(self::DATE_FORMAT, $batch['created_on']) ?></td>
    <td><?php echo $batch['created_by'] ?></td>
    <td><a href="?action=export_batch&amp;id=<?php echo $batch['id'] ?>">Export</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<?php endif ?>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
