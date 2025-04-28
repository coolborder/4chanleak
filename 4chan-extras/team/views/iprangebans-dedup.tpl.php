<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Range Ban Tools</title>
  <link rel="stylesheet" type="text/css" href="/css/iprangebans.css?25">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Range Ban Tools</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="?action=tools">Return</a></li>
  <li><a class="button button-light" href="?action=update">Add</a></li>
</ul>
</div>
<div id="content">
<?php $css_cls = ''; if ($this->mode === 'dedup'): ?>
<h4>The following ranges are not banned yet:</h4>
<?php elseif ($this->mode === 'calculate'): $css_cls = ' range-list-calc'; ?>
<h4>Broad match (<?php echo $this->ip_min_broad ?> → <?php echo $this->ip_max_broad ?>):</h4>
<form action="?action=update" method="POST"><button class="button btn-other range-list-btn" type="submit" name="action" value="update">Ban</button><?php echo csrf_tag() ?><textarea name="from_ranges" class="range-list range-list-broad"><?php echo $this->result_broad ?></textarea></form>
<h4>Exact match (<?php echo $this->ip_min ?> → <?php echo $this->ip_max ?>):</h4>
<?php else: ?>
<h4>Condensed ranges:</h4>
<?php endif ?>
<?php if (!empty($this->results)): ?>
<form action="?action=update" method="POST" enctype="multipart/form-data">
<button class="button btn-other range-list-btn" type="submit" name="action" value="update">Ban Ranges (<?php echo count($this->results) ?>)</button><?php echo csrf_tag() ?>
<textarea name="from_ranges" class="range-list<?php echo $css_cls ?>">
<?php foreach ($this->results as $res): ?><?php echo $res ?>

<?php endforeach ?>
</textarea>
</form>
<?php else: ?>
<h5 class="no-results">No results.</h5>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
