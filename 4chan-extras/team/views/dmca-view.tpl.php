<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>DMCA Tool</title>
  <link rel="stylesheet" type="text/css" href="/css/dmca.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/dmca.js"></script>
</head>
<body>
<header>
  <h1 id="title">DMCA Tool</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<h3>DMCA Notice</h3>
<table class="dmca-notice"><?php $n = $this->notice ?>
  <tr>
    <th>Takedown Notice ID</th>
    <td><?php echo (int)$n['id'] ?></td>
  </tr>
  <tr>
    <th>Created on</th>
    <td><?php echo date(self::DATE_FORMAT, $n['created_on']) ?> by <?php echo htmlspecialchars($n['created_by']) ?></td>
  </tr>
  <tr>
    <th>Copyright Owner</th>
    <td><?php echo $n['company'] !== '' ? htmlspecialchars($n['company']) : htmlspecialchars($n['name']) ?></td>
  </tr>
  <tr>
    <th>Authorized Representative</th>
    <td><?php echo htmlspecialchars($n['representative']) ?></td>
  </tr>
  <tr>
    <th>Address</th>
    <td><?php echo htmlspecialchars($n['address']) ?></td>
  </tr>
  <tr>
    <th>Phone</th>
    <td><?php echo htmlspecialchars($n['phone']) ?></td>
  </tr>
  <tr>
    <th>Fax</th>
    <td><?php echo htmlspecialchars($n['fax']) ?></td>
  </tr>
  <tr>
    <th>E-mail</th>
    <td><?php echo htmlspecialchars($n['email']) ?></td>
  </tr>
  <tr>
    <th>Keep Identity Private</th>
    <td><?php echo $n['hide_name'] ? 'Yes' : 'No' ?></td>
  </tr>
  <tr>
    <th>4chan URL(s)</th>
    <td><?php echo nl2br(htmlspecialchars($n['urls']), false) ?></td>
  </tr>
  <tr>
    <th>Copy of Notice</th>
    <td><?php echo nl2br(htmlspecialchars($n['notice_content']), false) ?></td>
  </tr>
  <tr>
    <th>File Attachment</th>
    <td><?php if ($n['file_data'] !== ''): ?><a href="?action=attachment&amp;id=<?php echo $n['id'] ?>"><?php echo $n['file_name'] !== '' ? htmlspecialchars($n['file_name']) : "untitled_{$n['id']}" ?></a><?php endif ?></td>
  </tr>
</table>
<h3>Affected Content</h3>
<table class="items-table"><tbody>
  <tr>
    <th><span data-tip="Content ID">ID</span></th>
    <th>Board</th>
    <th>Post No.</th>
    <th>Thread No.</th>
    <th>Date</th>
    <th>Name</th>
    <th>Subject</th>
    <th>Comment</th>
    <th>File</th>
    <th>Restored on</th>
  </tr>
  <?php foreach ($this->dmca_actions as $action):
  $post = json_decode($action['content'], true) ?>
  <tr>
    <td class="col-id"><?php echo $action['id'] ?></td>
    <td class="col-board"><?php echo $post['board'] ?></td>
    <td class="col-post"><?php echo $post['no'] ?></td>
    <td class="col-post"><?php echo $post['resto'] ? $post['resto'] : '' ?></td>
    <td class="col-date"><?php echo date(self::DATE_FORMAT, $post['time']) ?></td>
    <td class="col-name"><?php echo $post['name'] ?></td>
    <td><?php echo $post['sub'] ?></td>
    <td class="col-com"><?php echo $post['com'] ?></td>
    <td><?php if (isset($post['ext'])): $file_urls = $this->get_backup_img_urls($post['board'], $post['tim'], $post['ext'], $n['backup_key']); ?>
    <a class="thumb-link" href="<?php echo $file_urls[1] ?>" target="_blank"><img alt="" src="<?php echo $file_urls[0] ?>"></a><div class="act-file"><span data-tip="File Name"><?php echo $post['filename'].$post['ext'] ?></span></div><div class="act-file"><span data-tip="File MD5"><?php echo $post['md5'] ?></span></div><?php endif ?></td>
    <td class="col-date"><?php if ($action['restored_on']) {
      echo date(self::DATE_FORMAT, $action['restored_on']);
    }
    ?></td>
  </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php if ($this->counter_notices): ?>
<h3>Counter-Notices</h3>
<?php foreach ($this->counter_notices as $n): ?>
<div class="counter-notice-cnt">
<table class="dmca-notice">
  <tr>
    <th>Counter-Notice ID</th>
    <td><?php echo $n['id'] ?></td>
  </tr>
  <tr>
    <th>Created on</th>
    <td><?php echo date(self::DATE_FORMAT, $n['created_on']) ?> by <?php echo htmlspecialchars($n['created_by']) ?></td>
  </tr>
  <tr>
    <th>Name</th>
    <td><?php echo htmlspecialchars($n['name']) ?></td>
  </tr>
  <tr>
    <th>Company</th>
    <td><?php echo htmlspecialchars($n['company']) ?></td>
  </tr>
  <tr>
    <th>Address</th>
    <td><?php echo htmlspecialchars($n['address']) ?></td>
  </tr>
  <tr>
    <th>Phone</th>
    <td><?php echo htmlspecialchars($n['phone']) ?></td>
  </tr>
  <tr>
    <th>Fax</th>
    <td><?php echo htmlspecialchars($n['fax']) ?></td>
  </tr>
  <tr>
    <th>E-mail</th>
    <td><?php echo htmlspecialchars($n['email']) ?></td>
  </tr>
  <tr>
    <th>Content ID(s) to Restore</th>
    <td><?php echo nl2br(htmlspecialchars($n['content_ids']), false) ?></td>
  </tr>
  <tr>
    <th>Copy of Counter-Notice</th>
    <td><?php echo nl2br(htmlspecialchars($n['notice_content']), false) ?></td>
  </tr>
</table>
<h4>Resolution</h4>
<?php if ($n['resolved_on']): ?>
<table class="dmca-notice">
  <tr>
    <th>Resolved <?php if ($n['resolution_content'] === '') { echo 'automatically '; } else echo 'via court order '; ?>on</th>
    <td><?php echo date(self::DATE_FORMAT, $n['resolved_on']) ?></td>
  </tr>
  <?php if ($n['resolution_content'] !== ''): ?>
  <tr>
    <th>Copy of E-mail:</th>
    <td><?php echo nl2br(htmlspecialchars($n['resolution_content']), false) ?></td>
  </tr>
  <?php endif ?>
</table>
<?php endif ?>
<?php if ($n['resolution_content'] === ''): ?>
<h4>Add Court Order / Injunction</h4>
<span data-cmd="toggle-resolve-form" class="button btn-other">Show Form</span>
<div class="hidden">
<form class="dmca-form dmca-form-left" action="?" method="post" enctype="multipart/form-data"><input type="hidden" name="counter_id" value="<?php echo((int)$n['id']) ?>">
  <div class="field-grp">
    <label>Copy of E-mail:</label>
    <textarea name="resolution_content" required></textarea>
  </div>
  <div class="submit-cnt"><button class="button btn-accept" type="submit" name="action" value="resolve_counter">Submit</button></div><?php echo csrf_tag() ?>
</form>
</div>
<?php endif ?>
</div>
<?php endforeach ?>
<?php endif ?>
<h3>Add Counter-Notice</h3>
<span data-cmd="toggle-resolve-form" class="button btn-other btn-pad">Show Form</span>
<div class="hidden">
<form class="dmca-form dmca-form-left" action="?" method="post" enctype="multipart/form-data"><input type="hidden" name="notice_id" value="<?php echo((int)$this->notice['id']) ?>">
  <div class="field-grp">
    <label>Name:</label>
    <input type="text" name="name" required>
  </div>
  <div class="field-grp">
    <label>Company:</label>
    <input type="text" name="company">
  </div>
  <div class="field-grp">
    <label>Address:</label>
    <input type="text" name="address">
  </div>
  <div class="field-grp">
    <label>Telephone Number:</label>
    <input type="text" name="phone">
  </div>
  <div class="field-grp">
    <label>Fax Number:</label>
    <input type="text" name="fax">
  </div>
  <div class="field-grp">
    <label>E-mail Address:</label>
    <input type="text" name="email">
  </div>
  <div class="field-grp">
    <label>Content ID(s) to Restore:</label>
    <textarea name="content_ids" required></textarea>
  </div>
  <div class="field-grp">
    <label>Copy of Counter-Notice:</label>
    <textarea name="notice_content" required></textarea>
  </div>
  <div class="submit-cnt"><button class="button btn-other" type="submit" name="action" value="create_counter">Submit</button></div><?php echo csrf_tag() ?>
</form>
</div>
</div>
<footer></footer>
</body>
</html>
