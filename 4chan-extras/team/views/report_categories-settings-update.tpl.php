<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Board Weights - Report Categories</title>
  <link rel="stylesheet" type="text/css" href="/css/report_categories.css">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-tips>
<header>
  <h1 id="title">Board Weights - Report Categories</h1>
</header>
<div id="menu">
<ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>?action=settings">Return</a></li>
</ul>
</div>
<div id="content">
<form id="form-edit-rule" class="form" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr><?php if ($this->entry['id']): ?>
      <th>ID</th>
      <td><?php echo $this->entry['id'] ?></td>
    </tr><?php endif ?>  
    <tr>
      <th><span data-tip="Comma-separated list of boards." class="wot">Boards</span></th>
      <td><input type="text" name="boards" value="<?php echo $this->entry['boards'] ?>"></td>
    </tr>
    <tr>
      <th><span data-tip="Decimal multiplier ranging from 0.01 to 100.00" class="wot">Coefficient</span></th>
      <td><input class="board-field" type="number" min="0.01" max="100.00" step="0.01" name="coef" value="<?php echo $this->entry['coef'] ?>" required></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2"><button class="button btn-other" name="action" value="settings_update" type="submit">Save</button></td>
      </tr>
    <tfoot>
  </table>
  <?php if ($this->entry['id']): ?><input type="hidden" name="id" value="<?php echo $this->entry['id'] ?>"><?php endif ?>
  <?php echo csrf_tag() ?>
</form>
<?php if ($this->entry['id']): ?>
<form id="form-del-rule" action="" class="form" method="POST" enctype="multipart/form-data">
  <table><tr><td>
  <input type="hidden" name="id" value="<?php echo $this->entry['id'] ?>">
  <button class="button btn-deny" type="submit" name="action" value="settings_delete">Delete</button><?php echo csrf_tag() ?></td></tr></table>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
