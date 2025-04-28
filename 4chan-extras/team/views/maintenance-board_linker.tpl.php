<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Maintenance Tools</title>
  <link rel="stylesheet" type="text/css" href="/css/maintenance.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-tips>
<header>
  <h1 id="title">Maintenance Tools</h1>
</header>
<div id="content">
  <h4>Safe For Work</h4>
  <p><pre><?php echo $this->sfw ?></pre></p>
  <p><pre><?php echo $this->sfw_map ?></pre></p>
  <p><pre><?php echo $this->sfw_regex ?></pre></p>
  <p><pre><?php echo $this->sfw_php ?></pre></p>
  <h4>Not Safe For Work</h4>
  <p><pre><?php echo $this->nsfw ?></pre></p>
  <p><pre><?php echo $this->nsfw_map ?></pre></p>
  <p><pre><?php echo $this->nsfw_regex ?></pre></p>
  <p><pre><?php echo $this->nsfw_php ?></pre></p>
  <h4>Boards JSON for 404 pages</h4>
  <p><pre><?php echo $this->all_boards_json ?></pre></p>
</div>
<footer></footer>
</body>
</html>
