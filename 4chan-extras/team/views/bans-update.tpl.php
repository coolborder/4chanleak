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
<body data-page="update">
<header>
  <h1 id="title">Bans</h1><div id="cfg-btn"><span>&hellip;</span><div><label><input data-cmd="toggle-dt" id="cfg-cb-dt" type="checkbox" autocomplete="off">Dark Theme</label></div></div>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
<ul class="right">
  <li><a class="button button-light" href="?action=search">Search</a></li>
</ul>
</div>
<div id="content">
<form autocomplete="off" class="form edit-form" action="<?php echo self::WEBROOT ?>" method="POST" enctype="multipart/form-data">
  <?php $item = $this->item; ?>
  <input type="hidden" name="id" value="<?php echo $item['no'] ?>">
  <table>
    <tr>
      <th>Active</th>
      <td><input type="checkbox" name="active"<?php if ($item['active']) echo ' checked="checked"' ?>></td>
    </tr>
    <?php if ($item['name'] !== ''): ?>
    <tr>
      <th>Name</th>
      <td><span class="ban-name"><?php echo $item['name'] ?></span><?php if ($item['tripcode']): ?> <span class="ban-tripcode"><?php echo $item['tripcode'] ?></span><?php endif ?></td>
    </tr>
    <?php endif ?>
    <tr>
      <th>IP</th>
      <td><span class="cnt-block"><?php echo $item['host'] ?></span><?php if ($item['ban_history']): ?><a class="note-link" target="_blank" href="?action=search&amp;ip=<?php echo $item['host'] ?>"><?php echo $item['ban_history'] ?> ban<?php echo $this->pluralize($item['ban_history']); ?></a><?php endif ?><?php if ($item['ban_history_pass']): ?><a class="note-link" target="_blank" href="?action=search&amp;pass_ref=<?php echo $item['no'] ?>"><?php echo $item['ban_history_pass'] ?> Pass ban<?php echo $this->pluralize($item['ban_history_pass']); ?></a><?php endif ?><a class="note-link"  href="/search#{%22ip%22:%22<?php echo $item['host'] ?>%22}" target="_blank">Search posts</a></td>
    </tr>
    <?php if ($item['reverse'] !== $item['host']): ?>
    <tr>
      <th>Reverse</th>
      <td><?php echo $item['reverse'] ?></td>
    </tr>
    <?php endif ?>
    <?php if ($item['location']): ?>
    <tr>
      <th>Location</th>
      <td><?php echo $item['location'] ?></td>
    </tr>
    <?php endif ?>
    <tr>
      <th>Banned on</th>
      <td><?php echo date(self::DATE_FORMAT, $item['created_on']) ?></td>
    </tr>
    <?php if (!$item['permanent'] && !$item['warn']): ?>
    <tr>
      <th>Expires on</th>
      <td><?php echo date(self::DATE_FORMAT, $item['expires_on']) ?></td>
    </tr>
    <?php endif ?>
    <tr>
      <th>Ban Length</th>
      <td><input name="days" type="text" id="field-length" <?php if ($item['permanent']): ?>value="1" disabled>
      <?php elseif ($item['warn']): ?>value="0" disabled>
      <?php else: ?>value="<?php echo $this->days_duration($item['length']); ?>"> day(s)
      <?php endif ?><label class="js-length-radio length-lbl"><input<?php if ($item['permanent']) echo ' checked'; ?> type="checkbox" name="permanent"> Permanent</label><label class="js-length-radio length-lbl"><input<?php if ($item['warn']) echo ' checked'; ?> type="checkbox" name="warn"> Warning</label><label class="length-lbl"><input type="checkbox" name="global"<?php if ($item['global']) echo ' checked="checked"' ?>> Global</label></td>
    </tr>
    <tr>
      <th>Banned by</th>
      <td><?php echo $item['created_by'] ?></td>
    </tr>
    <?php if ($item['unbanned_on']): ?>
    <tr>
      <th>Unbanned by</th>
      <td><?php echo $item['unbannedby'] ?> on <?php echo date(self::DATE_FORMAT, $item['unbanned_on']) ?></td>
    </tr>
    <?php endif ?>
    <?php if ($this->is_manager): ?>
    <tr>
      <th>Unappealable</th>
      <td><input type="checkbox" name="zonly"<?php if ($item['zonly']) echo ' checked="checked"' ?>></td>
    </tr>
    <?php endif ?>
    <?php if ($item['board']): ?>
    <tr>
      <th>Board</th>
      <td><?php if ($item['board']) echo '/' . $item['board'] . '/' ?></td>
    </tr>
    <?php endif ?>
    <?php if ($item['post_num']): ?>
    <tr>
      <th>Post No.</th>
      <td><span class="cnt-block"><?php echo $item['post_num']; ?></span><?php if ($archive_url = $this->archive_url($item['board'], $item['post_num'], $post['resto'] ? $post['resto'] : $item['post_num'])):
      ?><a class="note-link" data-tip="Third-Party Archive" target="_blank" href="<?php echo $archive_url ?>">Archived</a><?php endif ?></td>
    </tr>
    <?php endif ?>
    <?php if ($item['template_name']): ?>
    <tr>
      <th class="cell-top">Ban Template</th>
      <td><?php echo $item['template_name'] ?></td>
    </tr><?php endif ?>
    <tr>
      <th class="cell-top">Public Reason</th>
      <td><?php if ($this->contains_html($item['public_reason'])): ?><div class="field-reason"><?php echo $item['public_reason'] ?></div><?php else: ?><textarea class="field-reason" name="public_reason" rows="8" cols="40"><?php echo $this->br2nl($item['public_reason']) ?></textarea><?php endif ?></td>
    </tr>
    <tr>
      <th class="cell-top"><span data-tip="Supports placeholders:\n%now% will insert (mm/dd/yy)\n%sign% will insert (mm/dd/yy, your username)" class="wot">Private Reason</span></th>
      <td><textarea class="field-reason" name="private_reason" rows="8" cols="40"><?php echo $this->br2nl($item['private_reason']) ?></textarea></td>
    </tr>
  <?php if ($item['post_json']): $post = $item['post_json']; ?>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <?php if ((int)$item['template_id'] === self::REPORT_TEMPLATE): // REPORTER START ?>
    <tr>
      <th>Type</th>
      <td>Report — <?php
      if (isset($post['report_weight']) && $post['report_weight'] !== '') {
        echo $this->get_report_category_title($post['report_cat']) . ' — ' . $post['report_weight'] . ' pts.';
      }
      else if (isset($post['report_cat']) && $post['report_cat'] !== '') {
        echo $this->report_cats[$post['report_cat']];
      }
      ?></td>
    </tr>
    <?php if (isset($item['4pass_id']) && $item['4pass_id'] !== ''): ?>
    <tr><?php if ($this->is_manager): ?>
      <th>4chan Pass</th>
      <td><?php echo $item['4pass_id'] ?></td>
    </tr>
    <?php endif ?>
    <tr>
      <th><span data-tip="Reporter's Hashed 4chan Pass" class="wot">Hashed Pass</span></th>
      <td><?php echo $this->hash_pass_id($item['4pass_id']) ?></td>
    </tr>
    <?php endif ?>
    <?php else: ?>
    <tr>
      <th>Type</th>
      <td><?php echo $post['resto'] ? 'Reply' : 'Thread'; ?></td>
    </tr>
    <?php endif // REPORTER END ?>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <?php if ($post['resto']): ?>
    <tr>
      <th>Thread No.</th>
      <td><?php echo $post['resto'] ?></td>
    </tr>
    <?php endif ?>
    <tr>
      <th>Date</th>
      <td><?php echo date(self::DATE_FORMAT, $post['time']) ?></td>
    </tr>
    <?php if ((int)$item['template_id'] === self::REPORT_TEMPLATE): list($_name, $_trip) = $this->format_name($post['name'], true); ?>
    <tr>
      <th>Name</th>
      <td><span class="ban-name"><?php echo $_name ?></span><?php if ($_trip): ?> <span class="ban-tripcode"><?php echo $_trip ?></span><?php endif ?></td>
    </tr>
    <?php endif ?>
    <?php if (isset($post['rel_sub']) && $post['rel_sub'] !== ''): ?>
    <tr>
      <th><span data-tip="Subject of the parent thread" class="wot">Rel. Subject</span></th>
      <td><?php echo $post['rel_sub'] ?></td>
    </tr>
    <?php endif ?>
    <?php if (isset($post['sub']) && $post['sub'] !== ''):
      list($subject, $is_spoiler) = $this->format_subject($post['sub']);
      if ($subject !== ''): ?>
    <tr>
      <th>Subject</th>
      <td><?php echo $subject ?></td>
    </tr>
    <?php endif;
    else: $is_spoiler = false; endif ?>
    <?php if ($post['com'] !== ''): ?>
    <tr>
      <th class="cell-top">Comment</th>
      <td class="cnt-pre cnt-wrap"><?php echo $this->strip_html($post['com']) ?></td>
    </tr>
    <?php endif ?>
    <?php if ($post['ext'] !== ''): ?>
    <tr>
      <th>File Name</th>
      <td><?php echo $post['filename'].$post['ext'] ?></td>
    </tr>
    <tr>
      <th>File Size</th>
      <td><?php echo $post['w']."&times;".$post['h'] ?>, <span class="cnt-block"><?php echo $post['fsize'] ?></span> byte<?php echo $this->pluralize($post['fsize']) ?></td>
    </tr>
    <tr>
      <th>File MD5</th>
      <td><code><?php echo $post['md5'] ?></code></td>
    </tr>
    <?php if ($is_spoiler): ?>
    <tr>
      <th>Spoiler</th>
      <td>Yes</td>
    </tr>
    <?php endif ?>
    <tr>
      <th>Thumbnail</th>
      <td><?php echo $post['tn_w']."&times;".$post['tn_h'] ?><?php if ($post['tmd5'] && strlen("{$post['tmd5']}") < 32): ?>, <code data-tip="Perceptual Hash" class="cnt-block"><?php echo htmlspecialchars($post['tmd5']) ?></code><?php endif ?><?php if ($item['active'] && $item['has_thumbnail']): ?><a class="note-link" target="_blank" href="<?php echo $this->get_ban_thumbnail($item['board'], $item['post_num']) ?>">Link</a><?php endif ?></td>
    </tr>
      <?php if (isset($post['filedeleted']) && $post['filedeleted']): ?>
    <tr>
      <th>File Deleted</th>
      <td>Yes</td>
    </tr>
      <?php endif ?>
    <?php endif ?>
    <?php if (isset($post['pwd'])): ?>
    <tr>
      <th>Password</th>
      <td><code><?php echo $post['pwd'] ?></code></td>
    </tr>
    <?php endif ?>
    <?php if ($this->item['user_info']): ?>
    <tr>
      <?php if ($this->item['user_info']['req_sig']): ?>
      <tr>
        <th>Req. Sig.</th>
        <td><span class="cnt-block"><?php echo $this->item['user_info']['req_sig'] ?></span></td>
      </tr>
      <?php endif ?>
      <th>Browser ID</th>
      <td><span class="cnt-block"><?php echo $this->item['user_info']['browser_id'] ?></span><?php if ($this->item['user_info']['is_mobile']): ?> <span class="ptr-def" data-tip="Posted from a Mobile Device">&phone;</span><?php endif ?></td>
    </tr>
    <?php endif ?>
    <?php if (isset($post['country'])): ?>
    <tr>
      <th>Country Code</th>
      <td><?php echo $post['country'] ?></td>
    </tr>
    <?php endif ?>
    <?php if ($post['id'] !== ''): ?>
    <tr>
      <th>User ID</th>
      <td><?php echo $post['id'] ?></td>
    </tr>
    <?php endif?>
    <?php if (isset($post['4pass_id']) && $post['4pass_id'] !== ''): ?>
    <?php if ($this->is_manager): ?>
    <tr>
      <th>4chan Pass</th>
      <td><?php echo $post['4pass_id'] ?></td>
    </tr>
    <?php endif ?>
    <tr>
      <th><span class="wot" data-tip="Hashed 4chan Pass">Hashed Pass</span></th>
      <td><?php echo $this->hash_pass_id($post['4pass_id']) ?></td>
    </tr>
    <?php endif?>
    <?php if (isset($post['capcode']) && $post['capcode'] !== 'none'): ?>
    <tr>
      <th>Capcode</th>
      <td><?php echo $post['capcode'] ?></td>
    </tr>
    <?php endif?>
  <?php else: ?>
    <?php if ($item['md5'] !== ''): ?>
    <tr>
      <th>File MD5</th>
      <td><?php echo $item['md5'] ?></td>
    </tr>
    <?php endif ?>
    <?php if (isset($item['blacklisted_md5'])): ?>
    <tr>
      <th>Blacklisted MD5</th>
      <td><?php echo $item['blacklisted_md5'] ?></td>
    </tr>
    <?php endif ?>
    <?php if ($item['password'] !== ''): ?>
    <tr>
      <th>Password</th>
      <td><code><?php echo $item['password'] ?></code></td>
    </tr>
    <?php endif ?>
    <?php if (isset($item['4pass_id']) && $item['4pass_id'] !== ''): ?>
    <tr><?php if ($this->is_manager): ?>
      <th>4chan Pass</th>
      <td><?php echo $item['4pass_id'] ?></td>
    </tr>
    <?php endif ?>
    <tr>
      <th><span data-tip="Hashed 4chan Pass" class="wot">Hashed Pass</span></th>
      <td><?php echo $this->hash_pass_id($item['4pass_id']) ?></td>
    </tr>
    <?php endif ?>
  <?php endif ?>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr><?php if ($item['appeals']): ?>
    <tr>
      <th>Appeals</th>
      <td><span data-cmd="toggle-appeals" class="btn-xs">Show</span></td>
    </tr>
    <tr id="js-appeals-cnt" class="hidden">
      <th></th>
      <td><?php foreach ($item['appeals'] as $appeal): ?>
        <div class="appeal-cnt">
          <p><?php echo trim($appeal['plea']) ?></p>
          <?php if (isset($appeal['denied_on'])): ?><div class="appeal-hdr">Denied by <?php echo htmlspecialchars($appeal['denied_by']) ?> on <?php echo date(self::DATE_FORMAT, $appeal['denied_on']) ?> </div><?php endif ?>
        </div>
      <?php endforeach ?></td>
    </tr>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr><?php endif ?>
    <tfoot>
      <tr>
        <td colspan="2">
          <button class="button btn-other" type="submit" name="action" value="update">Update</button>
        </td>
      </tr>
    </tfoot>
  </table>
  <?php echo csrf_tag() ?>
</form>
</div>
<footer></footer>
</body>
</html>
