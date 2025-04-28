<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Report Categories</title>
  <link rel="stylesheet" type="text/css" href="/css/report_categories.css">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-tips>
<header>
  <h1 id="title">Report Categories</h1>
</header>
<div id="menu">
<ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<form id="form-edit-rule" class="form" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr><?php if ($this->cat['id']): ?>
      <th>ID</th>
      <td><?php echo $this->cat['id'] ?></td>
    </tr><?php endif ?>  
    <tr>
      <th>Board</th>
      <td><select name="board" size="1">
        <option value="">All</option>
        <option <?php if ($this->cat['board'] === self::WS_BOARD_TAG) echo 'selected ' ?>value="<?php echo self::WS_BOARD_TAG ?>">All Worksafe</option>
        <option <?php if ($this->cat['board'] === self::NWS_BOARD_TAG) echo 'selected ' ?>value="<?php echo self::NWS_BOARD_TAG ?>">All Not Worksafe</option><?php if (has_flag('developer')): ?>
        <option <?php if ($this->cat['board'] === 'test') echo 'selected ' ?> value="test">test</option>
        <?php endif ?>
        <?php foreach ($this->board_list as $board => $_): ?>
        <option <?php if ($this->cat['board'] === $board) echo 'selected ' ?> value="<?php echo $board ?>"><?php echo $board ?></option>
        <?php endforeach ?>
      </select>
      </td>
    </tr>
    <tr>
      <th>Title</th>
      <td><input type="text" name="title" value="<?php echo $this->cat['title'] ?>" required></td>
    </tr>
    <tr>
      <th>Weight</th>
      <td><input class="decimal-field" type="number" max="<?php echo self::MAX_WEIGHT ?>" min="0.00" step="0.01" name="weight" value="<?php echo $this->cat['weight'] ? $this->cat['weight'] : 1 ?>" required></td>
    </tr>
    <tr>
      <th><span data-tip="Comma-separated list of boards" class="wot">Exclude boards</span></th>
      <td><input type="text" name="exclude_boards" value="<?php echo $this->cat['exclude_boards'] ?>"></td>
    </tr>
    <tr>
      <th><span data-tip="Sets the threshold for the number of recently cleared reports before an IP is considered abusive. Reports from IPs with a known history of abuse will have a weight < 1. Doesn't apply to OPs. A value of 0 disables the auto-ignore feature for this category." class="wot">Filtered</span></th>
      <td><input class="decimal-field" type="number" min="0" name="filtered" value="<?php echo (int)$this->cat['filtered'] ?>"></td>
    </tr>
    <tr>
      <th>OPs only</th>
      <td><input type="checkbox" name="op_only" value="1"<?php if ($this->cat['op_only']) echo ' checked' ?>></td>
    </tr>
    <tr>
      <th>Replies only</th>
      <td><input type="checkbox" name="reply_only" value="1"<?php if ($this->cat['reply_only']) echo ' checked' ?>></td>
    </tr>
    <tr>
      <th>Images only</th>
      <td><input type="checkbox" name="image_only" value="1"<?php if ($this->cat['image_only']) echo ' checked' ?>></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2"><button class="button btn-other" name="action" value="update" type="submit">Save</button></td>
      </tr>
    <tfoot>
  </table>
  <?php if ($this->cat['id']): ?><input type="hidden" name="id" value="<?php echo $this->cat['id'] ?>"><?php endif ?>
  <?php echo csrf_tag() ?>
</form>
<?php if ($this->cat['id']): ?>
<form id="form-del-rule" action="" class="form" method="POST" enctype="multipart/form-data">
  <table><tr><td>
  <input type="hidden" name="id" value="<?php echo $this->cat['id'] ?>">
  <button class="button btn-deny" type="submit" name="action" value="delete">Delete</button><?php echo csrf_tag() ?></td></tr></table>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
