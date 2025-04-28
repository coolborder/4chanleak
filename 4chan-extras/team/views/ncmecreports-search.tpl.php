<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>NCMEC Reports</title>
  <link rel="stylesheet" type="text/css" href="/css/ncmecreports.css?1">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/ncmecreports.js"></script>
</head>
<body>
<header>
  <h1 id="title">NCMEC Reports</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<?php if (!isset($this->items)): ?>
<form id="search-form" class="form" action="<?php echo self::WEBROOT ?>" method="GET">
  <table>
    <tr>
      <th>Board</th>
      <td><input class="value-field search-field" type="text" name="board"></td>
    </tr>
    <tr>
      <th>Post No.</th>
      <td><input class="value-field search-field" type="text" name="pid"></td>
    </tr>
    <tr>
      <th>Post Data</th>
      <td><input placeholder="Property" class="value-field search-field" type="text" name="props[]"> : <input placeholder="Value" class="value-field search-field" type="text" name="vals[]"></td>
    </tr>
    <tr id="add-field-root">
      <th></th>
      <td><button type="button" class="button btn-other" id="add-field">Add Field</button></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2">
          <button class="button btn-other" type="submit" name="action" value="search">Search</button>
        </td>
      </tr>
    </tfoot>
  </table>
</form>
<?php else: ?>
<table class="items-table" id="items">
<thead>
  <tr>
    <th>ID</th>
    <th>ID NCMEC</th>
    <th>Board</th>
    <th>Post No.</th><?php if ($this->has_props): ?>
    <th>Post Data</th><?php endif ?>
    <th>Sent on</th>
  </tr>
</thead>
<tbody>
  <?php $ids = array(); foreach ($this->items as $item): $ids[] = $item['ncmec_id']; $old_qs = $item['old'] ? 'old&amp;' : ''; ?>
  <tr>
    <td><a href="?<?php echo $old_qs ?>action=view&amp;id=<?php echo $item['id'] ?>"><?php echo $item['id'] ?></a></td>
    <td><?php echo $item['ncmec_id'] ?></td>
    <td><?php echo $item['board'] ?></td>
    <td><?php echo $item['post_num'] ?></td><?php if (isset($item['props'])): ?>
    <td>
      <table class="wire-table">
      <?php foreach ($item['props'] as $key => $value): ?>
      <tr><th><?php echo $key ?></th><td><?php echo $value ?></td></tr>
      <?php endforeach ?>
      </table>
    </td>
    <?php endif ?>
    <td><?php if ($item['report_sent']) echo $item['sent_on'] ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<div class="id-list">
<h4>IDs NCMEC</h4>
<div class="mini-list"><?php echo implode(', ', $ids) ?></div>
</div>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
