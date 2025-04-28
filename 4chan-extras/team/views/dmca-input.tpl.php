<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Add DMCA Notice</title>
  <link rel="stylesheet" type="text/css" href="/css/dmca.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/dmca.js"></script>
</head>
<body>
<header>
  <h1 id="title">Add DMCA Notice</h1>
</header>
<div id="content">
<form id="dmca-notice-form" class="dmca-form" action="" method="post" enctype="multipart/form-data">
  <div class="field-grp">
    <label>Copyright Owner Name:</label>
    <input type="text" name="name">
  </div>
  <div class="field-grp">
    <label>Copyright Owner Company:</label>
    <input type="text" name="company">
  </div>
  <div class="field-grp">
    <label>Authorized Representative:</label>
    <input type="text" name="representative">
  </div>
  <div class="field-grp">
    <label>Notifier Address:</label>
    <input type="text" name="address">
  </div>
  <div class="field-grp">
    <label>Notifier Telephone Number:</label>
    <input type="text" name="phone">
  </div>
  <div class="field-grp">
    <label>Notifier Fax Number:</label>
    <input type="text" name="fax">
  </div>
  <div class="field-grp">
    <label>Notifier E-mail Address:</label>
    <input type="text" id="dmca-email-field" name="email">
  </div>
  <div class="field-grp">
    <label>Keep Identity Private:</label>
    <input type="checkbox" name="hide_name" value="1"> Keep the copyright owner/authorized representative/notifier's information private.
  </div>
  <div class="field-grp">
    <label>4chan URL(s):</label>
    <textarea name="urls" required></textarea>
  </div>
  <div class="field-grp">
    <label>Copy of Takedown Notice:</label>
    <textarea name="notice_content" required></textarea>
  </div>
  <div class="field-grp">
    <label>File Attachment:</label>
    <input type="file" name="doc_file">
  </div>
  <div class="submit-cnt"><button class="button btn-other" type="submit" name="action" value="create_notice">Submit</button></div><?php echo csrf_tag() ?>
</form>
</div>
<footer></footer>
</body>
</html>