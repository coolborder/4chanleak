<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Autopurge</title>
  <link rel="stylesheet" type="text/css" href="/css/autopurge.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/autopurge.js"></script>
</head>
<body data-inittip>
<header>
  <h1 id="title">Autopurge</h1>
</header>
<div id="menu">
<ul>
  <li><a class="button button-light" href="?action=update">Add</a></li>
</ul>
</div>
<div id="content">
<table class="items-table">
<thead>
  <tr>
    <th>ID</th>
    <th>Active</th>
    <th>Boards</th>
    <th>Patterns</th>
    <th>Public Reason</th>
    <th>Ban Length</th>
    <th>Description</th>
    <th>Updated on</th>
    <th></th>
  </tr>
</thead>
<tbody id="items">
  <?php foreach ($this->rules as $rule): ?>
  <tr>
    <td class="col-active"><?php echo $rule['id'] ?></td>
    <td class="col-active"><?php if ($rule['active']): ?>&check;<?php endif ?></td>
    <td class="col-boards"><?php echo str_replace(',', ',<wbr>', $rule['boards']) ?></td>
    <td class="col-pattern"><ul class="patterns-list"><?php foreach ($rule['patterns'] as $field => $value): ?>
      <li><strong><?php echo isset($this->field_map[$field]) ? $this->field_map[$field]['label'] : $field ?>: </strong><span><?php echo htmlspecialchars($value) ?></span></li>
    <?php endforeach ?></ul>
    </td>
    <td class="col-reason"><?php echo $rule['public_reason'] ?></td>
    <td class="col-length"><div><?php echo $rule['ban_days'] < 0 ? 'Permanent' : $rule['ban_days'] ?></div>
      <?php if ($rule['global_ban']): ?><div class="note">Global</div><?php endif ?>
    </td>
    <td class="col-description"><?php echo $rule['description'] ?></td>
    <td class="col-date"><span data-tip="<?php echo $rule['updated_by'] ?>"><?php echo date($this->date_format, $rule['updated_on']) ?></span></td>
    <td class="col-meta"><a href="?action=update&amp;id=<?php echo $rule['id'] ?>">Edit</a> <a href="?action=preview&amp;id=<?php echo $rule['id'] ?>">Preview</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
