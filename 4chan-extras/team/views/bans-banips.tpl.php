<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Bans</title>
  <link rel="stylesheet" type="text/css" href="/css/bans.css?25">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/bans.js?12"></script>
</head>
<body data-page="search">
<header>
  <h1 id="title">Bans</h1><div id="cfg-btn"><span>&hellip;</span><div><label><input data-cmd="toggle-dt" id="cfg-cb-dt" type="checkbox" autocomplete="off">Dark Theme</label></div></div>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<?php if (isset($this->status)): ?>
<ul>
  <?php foreach ($this->status as $status): ?>
  <li><?php echo $status ?></li>
  <?php endforeach ?>
</ul>
<?php else: ?>
<form class="form ban-ips-form" action="" method="post" enctype="multipart/form-data">
  <table>
    <tr>
      <th class="cell-top">IP(s)</th>
      <td><textarea name="ips" required></textarea></td>
    </tr>
    <tr>
      <th>Public Reason</th>
      <td><textarea name="public_reason" required></textarea></td>
    </tr>
    <tr>
      <th>Private Reason</th>
      <td><input type="text" name="private_reason"></td>
    </tr>
    <tr>
      <th>Length</th>
      <td><input id="field-length" type="text" name="days"> day(s). Defaults to a permanent ban.</td>
    </tr>
    <tr>
      <th>Board</th>
      <td><input id="field-length" type="text" name="board"> Defaults to a global ban.</td>
    </tr>
    <?php if ($this->is_developer): ?>
    <tr>
      <th>4chan Pass</th>
      <td><input class="col-length" pattern="[A-Z0-9]{10}" type="text" name="passid"></td>
    </tr>
    <?php endif ?>
    <?php if ($this->is_developer): ?>
    <tr>
      <th>Password</th>
      <td><input class="col-length" pattern="[a-f0-9]{32}" type="text" name="pwd"></td>
    </tr>
    <?php endif ?>
    <tr>
      <th>Force</th>
      <td><label><input type="checkbox" name="force" value="1"> Ban even if the IP is already banned.</label></td>
    </tr>
    <tr>
      <th>No Reverse</th>
      <td><label><input type="checkbox" name="no_reverse" value="1"> Don't perform reverse lookups to speed things up.</label></td>
    </tr>
    <tr>
      <th>No Rangebans</th>
      <td><label><input type="checkbox" name="no_rangebans" value="1"> Skip rangebanned IPs.</label></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2"><button class="button btn-deny" name="action" value="ban_ips" type="submit">Ban</button></td>
      </tr>
    </tfoot>
  </table><?php echo csrf_tag() ?>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
