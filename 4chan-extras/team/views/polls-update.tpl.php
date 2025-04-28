<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Polls</title>
  <link rel="stylesheet" type="text/css" href="/css/polls.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/polls.js"></script>
</head>
<body>
<header>
  <h1 id="title">Polls</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<?php if ($this->poll): ?>
<h4>Link</h4>
<pre class="poll-link"><?php echo $this->generate_link($this->poll['id']) ?></pre>
<?php endif ?>
<?php if ($this->poll && $this->poll['vote_count'] > 0): ?>
<div id="poll-results">
<h4>Results</h4>
<table class="poll-res-tbl">
<?php foreach ($this->options as $option):
if (isset($this->scores[$option['id']])) {
  $score = $this->scores[$option['id']];
  $perc = round($score / $this->poll['vote_count'] * 100, 2);
}
else {
  $score = 0;
  $perc = 0;
}
?>
  <tr>
    <th><?php echo $perc ?>% (<?php echo $score ?>)</th>
    <td><?php echo $option['caption'] ?></td>
  </tr>
<?php endforeach ?>
<tr class="poll-res-total">
  <td></td>
  <td>Total votes: <?php echo $this->poll['vote_count'] ?></td>
</tr>
</table>
</div>
<?php endif ?>
<form id="form-edit-rule" action="" method="POST" enctype="multipart/form-data"><?php if ($this->poll && $this->poll['vote_count'] > 0): ?>
  <input type="hidden" name="title" value="<?php echo $this->poll['title'] ?>">
  <input type="hidden" name="description" value="<?php echo $this->poll['description'] ?>">
  <?php foreach ($this->options as $option): ?><input type="hidden" name="options[<?php echo $option['id'] ?>]" value="<?php echo $option['caption'] ?>"><?php endforeach ?>
<?php endif ?>
  <table>
    <tr>
      <th>Active</th>
      <td><input type="checkbox" name="active"<?php if (!$this->poll || $this->poll['status']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th>Title</th>
      <?php if ($this->poll): ?>
      <td><input required type="text" name="title" value="<?php echo $this->poll['title'] ?>"<?php if ($this->poll['vote_count'] > 0): ?> disabled<?php endif ?>></td>
      <?php else: ?>
      <td><input required type="text" name="title" value=""></td>
      <?php endif ?>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Optional">Description</span></th>
      <?php if ($this->poll): ?>
      <td><div class="desc-protip">Supports raw HTML</div><textarea<?php if ($this->poll['vote_count'] > 0 && $this->poll['description'] !== ''): ?> disabled<?php endif ?> name="description" rows="8" cols="40"><?php echo $this->poll['description'] ?></textarea></td>
      <?php else: ?>
      <td><textarea name="description" rows="8" cols="40"></textarea></td>
      <?php endif ?>
    </tr>
    <tr>
    <th>Expires on</th>
    <td><input pattern="\d\d/\d\d/\d\d" placeholder="MM/DD/YY" type="text" name="expires" value="<?php if ($this->poll && $this->poll['expires_on']) echo date(self::DATE_FORMAT, $this->poll['expires_on']) ?>"></td>
    </tr>
    <tr>
      <th><span data-tip="Blank entries will be deleted" class="wot">Options</span></th>
    <?php if ($this->poll): ?>
      <td><fieldset<?php if ($this->poll['vote_count'] > 0): ?> disabled<?php endif ?>><ol id="opts-cnt"><?php foreach ($this->options as $option): ?>
      <li class="poll-opt"><input type="text" name="options[<?php echo $option['id'] ?>]" value="<?php echo $option['caption'] ?>"></li>
      <?php endforeach ?></ol></fieldset></td>
    <?php else: ?>
      <td><fieldset><ol id="opts-cnt"><li class="poll-opt"><input type="text" name="new_options[]" value=""></li></ol></fieldset></td>
      <?php endif ?>
    </tr>
    <tr>
      <th></th>
      <td><?php if ($this->poll['vote_count'] == 0): ?><button type="button" id="add-option" class="button btn-other">Add Option</button><?php endif ?></td>
    </tr>
  </table>
  <?php if ($this->poll): ?>
  <input type="hidden" name="id" value="<?php echo $this->poll['id'] ?>">
  <?php endif ?>
  <button id="save-btn" class="button btn-other" type="submit" name="action" value="update">Save</button><?php echo csrf_tag() ?>
</form>
<?php if ($this->poll): ?>
<form id="form-del-rule" action="" method="POST" enctype="multipart/form-data">
  <input type="hidden" name="id" value="<?php echo $this->poll['id'] ?>">
  <button class="button btn-deny" type="submit" name="action" value="delete">Delete</button><?php echo csrf_tag() ?>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
