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
  <script type="text/javascript" src="/js/autopurge.js"></script>
</head>
<body>
<header>
  <h1 id="title">Autopurge</h1>
</header>
<div id="menu">
<ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<form id="form-edit-rule" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr>
      <th>Active</th>
      <td><input type="checkbox" name="active"<?php if ($this->rule['active']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Comma-separated list of boards">Boards</span></th>
      <td><input type="text" name="boards" value="<?php if ($this->rule) echo $this->rule['boards'] ?>"></td>
    </tr>
    <tr>
      <th>Description</th>
      <td><input type="text" name="description" value="<?php if ($this->rule) echo $this->rule['description'] ?>"></td>
    </tr>
    <tr class="row-sep"><td colspan="2"><hr></td></tr>
    <?php foreach ($this->field_map as $name => $meta): ?>
    <tr>
      <th><?php if (isset($this->field_type_desc[$meta['type']])): ?><span class="wot" data-tip="<?php echo htmlspecialchars($this->field_type_desc[$meta['type']], ENT_QUOTES) ?><?php if (isset($meta['desc'])) echo "\n" . $meta['desc'] ?>"><?php echo htmlspecialchars($meta['label']) ?></span><?php else: ?><?php echo htmlspecialchars($meta['label']) ?><?php endif ?></th>
      <td><input type="text" maxlength="255" name="<?php echo $name ?>" value="<?php if ($this->rule && isset($this->patterns[$name])) echo htmlspecialchars($this->patterns[$name], ENT_QUOTES) ?>"></td>
    </tr>
    <?php endforeach ?>
    <tr class="row-sep"><td colspan="2"><hr></td></tr>
      <th>Public reason</th>
      <td><input type="text" name="public_reason" value="<?php if ($this->rule) echo $this->rule['public_reason']; else echo $this->default_reason ?>" required></td>
    </tr>
    <tr>
      <th>Ban length</th>
      <td><input class="ban-len-field" type="text" name="ban_days" value="<?php if ($this->rule) echo $this->rule['ban_days']; else echo $this->default_ban_days ?>" required> day(s). "-1" for a permaban.</td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2"><button class="button btn-other" name="action" value="update" type="submit">Save</button></td>
      </tr>
    <tfoot>
  </table>
  <?php if ($this->rule): ?>
  <input type="hidden" name="id" value="<?php echo $this->rule['id'] ?>">
  <?php endif ?>
  <?php echo csrf_tag() ?>
</form>
<?php if ($this->rule): ?>
<form id="form-del-rule" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr>
      <td><button class="button btn-deny" name="action" value="delete" type="submit">Delete</button></td>
    </tr>
  </table>
  <input type="hidden" name="id" value="<?php echo $this->rule['id'] ?>">
  <?php echo csrf_tag() ?>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
