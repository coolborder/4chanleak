<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Range Ban Tools</title>
  <link rel="stylesheet" type="text/css" href="/css/iprangebans.css?25">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
</head>
<body data-tips>
<header>
  <h1 id="title">Range Ban Tools</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
  <li><a class="button button-light" href="?action=update">Add</a></li>
</ul>
</div>
<div id="content">
<form id="form-edit-rule" class="form-tools" action="" method="POST">
  <table>
    <tr>
      <th>Mode</th>
      <td>
        <label class="as-blk"><input type="radio" name="mode" value="dedup" checked>Trim &mdash; Remove already banned ranges. To be considered, a rangeban must be active, full, global and without an expiration date.</label>
        <label class="as-blk"><input type="radio" name="mode" value="aggregate">Aggregate &mdash; Compress a list of ranges by removing superfluous entries and combining adjacent prefixes.</label>
        <label class="as-blk"><input type="radio" name="mode" value="calculate">Calculate &mdash; Find the smallest possible ranges which contain a given list of IPs.</label>
      </td>
    </tr>
    <tr>
      <th>Data</th>
      <td><label class="as-blk">IPs or ranges in CIDR notation. One per line. Invalid data will be discarded.</label><textarea name="data" rows="8" cols="40"></textarea></td>
    </tr>
    <tr>
      <th></th>
      <td><button class="button btn-other" type="submit" name="action" value="tools">Process</button><?php echo csrf_tag() ?></td>
    </tr>
  </table>
</form>
</div>
<footer></footer>
</body>
</html>
