<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>DMCA Tool</title>
  <link rel="stylesheet" type="text/css" href="/css/dmca.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">DMCA Tool</h1>
</header>
<div id="content">
<form autocomplete="off" id="search-form" action="" method="get" enctype="multipart/form-data">
  <input type="text" name="q" placeholder="Takedown Notice ID, Counter-Notice ID, Blacklist ID or E-mail" required>
  <button class="button btn-other" name="action" value="search" type="submit">Search</button>
</form>
<div id="add-notice-btn"><a class="button btn-other" href="?action=create_notice">Add Notice</a></div>
<?php if ($this->items): ?>
<table class="items-table">
<thead>
  <tr>
    <th>Takedown ID</th>
    <th>Copyright Owner</th>
    <th>Claimant E-mail</th>
    <th>Created on</th>
    <th>Created by</th>
    <th></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $notice): ?>
  <tr>
    <td><?php echo ((int)$notice['id']) ?></td>
    <td><?php echo $notice['company'] ? htmlspecialchars($notice['company']) : htmlspecialchars($notice['name']) ?></td>
    <td><?php echo htmlspecialchars($notice['email']) ?></td>
    <td><?php echo date(self::DATE_FORMAT, $notice['created_on']) ?></td>
    <td><?php echo htmlspecialchars($notice['created_by']) ?></td>
    <td><a href="?action=view&amp;id=<?php echo ((int)$notice['id']) ?>">View</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
<tfoot>
  <tr>
    <td colspan="10" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
</table>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
