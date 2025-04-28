<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Janitor Boards</title>
  <link rel="stylesheet" type="text/css" href="/css/staffroster.css?12">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?1"></script>
  <script type="text/javascript" src="/js/staffroster.js?11"></script>
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Manage Janitors</h1>
</header>
<div id="menu"><ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>/staffroster">Roster</a></li>
  <li><a class="button button-light" href="?action=activity">Activity</a></li>
  <li><a class="button button-light" href="?action=scoreboard">Scoreboard</a></li>
  <li><a class="button button-light" href="?action=manage">Manage</a></li>
  <li><a class="button button-light" href="?action=flags">Flags</a></li>
  <li><a class="button button-light" href="?action=j_names">/j/ Names</a></li>
  <li><a class="button button-light" href="?action=check_email">Check E-Mail</a></li>
</ul></div>
<div id="content">
<table class="items-table">
<thead>
  <tr>
    <th>User</th>
    <th>Boards</th>
    <th></th>
  </tr>
</thead>
<tbody id="items" class="col-center">
  <?php foreach ($this->janitors as $janitor): ?>
  <tr id="item-<?php echo $janitor['id'] ?>">
    <td class="js-username"><?php echo $janitor['username'] ?></td>
    <td id="boards-<?php echo $janitor['id'] ?>"><?php echo $janitor['allow'] ?></td>
    <td><span data-cmd="update-boards" class="button btn-other">Edit Boards</span><span data-cmd="update-email" class="button btn-other">Edit E-mail</span><span data-cmd="promote-janitor" class="button btn-other">Promote</span><span data-cmd="remove-janitor" class="button btn-deny">Delete</span></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
