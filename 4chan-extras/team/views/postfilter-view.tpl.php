<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Post Filter</title>
  <link rel="stylesheet" type="text/css" href="/css/postfilter.css?9">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Post Filter</h1>
</header>
<div id="menu">
<form action="<?php echo self::WEBROOT ?>" method="GET"><input type="hidden" name="action" value="view"><input placeholder="Filter ID" name="id" type="text" id="view-id-field"><button class="button button-light" type="submit">View</button></form>
</div>
<div id="content">
<div class="cnt-col">
<?php if ($this->item): ?>
<form class="form"><fieldset>
  <input type="hidden" name="id" value="<?php echo $this->item['id'] ?>">
  <table>
    <tr>
      <th>ID</th>
      <td><?php echo ($this->item['id']) ?></td>
    </tr>
    <tr>
      <th>Active</th>
      <td><input type="checkbox" name="active"<?php if ($this->item['active']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th>Pattern</th>
      <td><input class="value-field" type="text" name="pattern" value="<?php echo htmlspecialchars($this->item['pattern'], ENT_QUOTES) ?>"></td>
    </tr>
    <tr>
      <th>Regex</th>
      <td><input id="field-regex" type="checkbox" name="regex"<?php if ($this->item['regex']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th>Description</th>
      <td><input class="value-field" type="text" name="description" value="<?php echo $this->item['description'] ?>"></td>
    </tr>
    <tr>
      <th>Created on</th>
      <td><?php echo date(self::DATE_FORMAT, $this->item['created_on']) ?></td>
    </tr>
    <?php if ($this->item['updated_on']): ?>
    <tr>
      <th>Updated on</th>
      <td><?php echo date(self::DATE_FORMAT, $this->item['updated_on']) ?></td>
    </tr>
    <?php endif ?>
  </table></fieldset>
</form>
<?php endif ?>
</div>
</div>
<footer></footer>
</body>
</html>
