<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>DMCA Tool - Search</title>
  <link rel="stylesheet" type="text/css" href="/css/dmca.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">DMCA Tool</h1>
</header>
<div id="content">
<?php if ($this->notices): ?>
<h3>Notices</h3>
<table class="items-table">
<thead>
  <tr>
    <th>ID</th>
    <th>Claimant Name</th>
    <th>Claimant Company</th>
    <th>Claimant E-Mail</th>
    <th>Created on</th>
    <th>Created by</th>
    <th></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->notices as $notice): ?>
  <tr>
    <td><?php echo ((int)$notice['id']) ?></td>
    <td><?php echo htmlspecialchars($notice['name']) ?></td>
    <td><?php echo htmlspecialchars($notice['company']) ?></td>
    <td><?php echo htmlspecialchars($notice['email']) ?></td>
    <td><?php echo date(self::DATE_FORMAT, $notice['created_on']) ?></td>
    <td><?php echo $notice['created_by'] ?></td>
    <td><a href="?action=view&amp;id=<?php echo $notice['id'] ?>">View</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<?php endif ?>
<?php if ($this->counter_notices): ?>
<h3>Counter-notices</h3>
<table class="items-table">
<thead>
  <tr>
    <th>Notice ID</th>
    <th>Name</th>
    <th>Company</th>
    <th>E-Mail</th>
    <th>Created on</th>
    <th></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->counter_notices as $notice): ?>
  <tr>
    <td><?php echo $notice['notice_id'] ?></td>
    <td><?php echo $notice['name'] ?></td>
    <td><?php echo $notice['company'] ?></td>
    <td><?php echo $notice['email'] ?></td>
    <td><?php echo date('m/d/Y H:i', $notice['created_on']) ?></td>
    <td><a href="?action=view&amp;id=<?php echo $notice['notice_id'] ?>">View Notice</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
