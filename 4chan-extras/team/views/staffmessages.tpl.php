<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Staff Messages</title>
  <link rel="stylesheet" type="text/css" href="/css/staffmessages.css?4">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/staffmessages.js"></script>
</head>
<body data-inittip>
<header>
  <h1 id="title">Staff Messages</h1>
</header>
<div id="content">
<form id="form-msg" action="?create" method="POST" enctype="multipart/form-data">
  <table>
    <tbody>
      <tr>
        <th><span class="wot" data-tip="Comma-separated list of boards">Boards</span></th>
        <td><input type="text" name="boards"></td>
      </tr>
      <tr>
        <th>Message</th>
        <td><textarea name="content" rows="4" cols="40" required></textarea></td>
      </tr>
    </tbody>
    <tfoot>
      <tr><td colspan="2"><button class="button btn-other" type="submit" name="action" value="create">Submit</button><?php echo csrf_tag() ?></td></tr>
    </tfoot>
  </table>
</form>
<ul id="form-disc">
<li>Capcode rules apply to the staff message system.</li>
<li>Please do not include any identifying information in messages. This includes, but is not limited to, IP addresses, hostnames, locations, ISP names, etc.</li>
<li>Please do not include any mod names in messages. You don't need to tell janitors who wrote the message; messages should be written with the authority of the entire mod team.</li>
<li>Please do not include any janitor names in messages. Singling out individual janitors for praise or criticism can be divisive.</li>
<li>Links to full images uploaded to /j/ will be embedded automatically.</li>
</ul>
<table class="items-table">
<thead>
  <tr>
    <th>Boards</th>
    <th>Message</th>
    <th>Created on</th>
    <th></th>
  </tr>
</thead>
<tbody id="items">
  <?php foreach ($this->messages as $msg): ?>
  <tr>
    <td class="col-boards"><?php echo $msg['boards'] ?></td>
    <td class="col-reason"><?php echo $this->format_staff_message($msg['content']) ?></td>
    <td class="col-date"><span data-tip="<?php echo $msg['created_by'] ?>"><?php echo date(self::DATE_FORMAT, $msg['created_on']) ?></span></td>
    <td class="col-meta"><a href="?action=delete&amp;id=<?php echo $msg['id'] ?>" class="button btn-deny">Delete</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
