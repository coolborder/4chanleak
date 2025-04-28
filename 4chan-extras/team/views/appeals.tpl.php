<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Appeals (<?php echo count($this->appeals) ?>)</title>
  <link rel="stylesheet" type="text/css" href="/css/appeals.css?105">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?3"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/appeals.js?34"></script>
  <?php if ($this->references): ?>
  <script id="js-refs" type="application/json"><?php echo json_encode($this->references) ?></script>
  <?php endif ?>
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Appeals</h1><div id="cfg-btn"><span>&hellip;</span><div><label><input data-cmd="toggle-dt" id="cfg-cb-dt" type="checkbox" autocomplete="off">Dark Theme</label></div></div>
</header>
<div id="menu">
  <?php if ($this->search_query): ?>
  <a href="/appeals" class="button button-light">Back</a>
  <?php else: ?>
  <span id="refresh-btn" data-cmd="refresh" class="button button-light">Refresh</span>
  <?php endif ?>
  <div class="right">
    <span id="search-field"><form action="" method="GET"><input name="q" type="text" placeholder="Appeal ID, IP or Pass" value="<?php echo $this->search_query ?>"> <button class="button button-light" type="submit">Search</button></form></span>
    <?php if ($this->isManager): ?><a href="/appeals?action=stats" class="button button-light">Stats</a><?php endif ?>
    <span id="settings-btn" data-cmd="show-settings" class="button button-light">Settings</span>
  </div>
</div>
<table id="appeals-table" class="items-table">
<thead>
  <tr>
    <th class="col-board">Board</th>
    <th class="col-post">Post</th>
    <th class="col-reason">Reason</th>
    <th class="col-plea">Plea</th>
    <th class="col-length">Length</th>
    <th class="col-ctrl"></th>
  </tr>
</thead>
<tbody id="items">
<?php foreach ($this->appeals as $appeal): $can_deny = $appeal['admin'] != $_COOKIE['4chan_auser'] || $this->isManager; ?>
  <tr id="appeal-<?php echo $appeal['no'] ?>" data-len="<?php echo($appeal['ban_end'] ? $this->days_duration($appeal['ban_end'] - $appeal['ban_start']) : '-1') ?>">
    <td class="col-board" data-cmd="focus"><?php if ($appeal['board']): ?><div class="ban-board">/<?php echo $appeal['board'] ?>/</div><?php endif; if ($appeal['global']): ?><div class="ban-global">Global</div><?php endif ?></td>
    <td class="col-post">
      <?php if (isset($appeal['post'])): $post = $appeal['post']; ?>
      <span class="post-no"><?php if ($appeal['link']) {
        echo '<a target="_blank" href="https://www.4chan.org/derefer?url=' . $appeal['link'] . '">No.' . $post['no'] . '</a>';
      }
      else {
        echo 'No.' . $post['no'];
      } ?></span>
      <?php if (!$post['resto']): ?><span class="post-isop">OP</span><?php endif ?>
      <?php else: $post = null; endif ?>
      <?php if ($appeal['name']): ?><span class="user-name"><?php echo $appeal['name'] ?><?php if (isset($appeal['tripcode'])): ?> <span class="user-tripcode"><?php echo $appeal['tripcode'] ?></span><?php endif ?></span><?php endif ?>
      <?php if ($appeal['4pass_id'] !== ''): ?><div class="user-pass">Posted with a 4chan Pass</div><?php endif ?>
      <?php if ($appeal['closed'] > 1): ?><div class="match-cnt"><?php if ($appeal['closed'] & self::MATCHED_PWD): ?><span data-tip="The password in the appeal matches the password in the ban">Password &check;</span><?php endif ?><?php if ($appeal['closed'] & self::MATCHED_PASS): ?><span data-tip="The 4chan Pass in the appeal matches the 4chan Pass in the ban">4chan Pass &check;</span><?php endif ?></div><?php endif ?>
      <div class="user-net-cnt"><div class="user-ip"><span class="user-host"><?php echo $appeal['host'] ?></span><?php if ($appeal['geo_loc']): ?><span class="user-country">(<?php echo htmlspecialchars($appeal['geo_loc']) ?>)</span><?php endif ?><?php if (isset($post['user_info'])): ?><span data-tip="Browser ID" class="sxt">(<?php echo htmlspecialchars($post['user_info']['browser_id']) ?>)</span><?php endif ?><?php if (isset($appeal['is_rangebanned'])): ?><span class="str-accept">(rangebanned)</span><?php endif ?></div><?php if ($appeal['host'] != $appeal['reverse']): ?><div class="user-reverse"><?php echo $appeal['reverse'] ?></div><?php endif ?>
      <?php if (isset($appeal['asn_name'])): ?><div class="user-asn"><?php echo htmlspecialchars($appeal['asn_name']) ?></div><?php endif ?>
      <?php if ($appeal['xff'] !== '' && $appeal['xff'] != $appeal['host']): ?><div class="user-xff">Via: <?php echo $appeal['xff'] ?></div><?php endif ?></div>
      <?php if ($post): ?>
      <div class="ban-post">
        <?php if ($post['sub']): ?><div class="post-subject"><?php echo $post['sub'] ?></div>
        <?php elseif ($post['rel_sub']): ?><div class="post-subject post-rel-sub"><?php echo $post['rel_sub'] ?></div>
        <?php endif ?>
        <?php if (isset($post['ban_thumb'])): ?>
          <div class="filename"><?php echo $post['filename'].$post['ext'] ?></div>
          <img src="https://i.4cdn.org/bans/thumb/<?php echo $appeal['board'] ?>/<?php echo $post['ban_thumb'] ?>s.jpg" class="ban-thumb<?php if ($post['spoiler']): ?> img-spoiler<?php endif ?>">
        <?php endif ?>
        <div class="ban-comment"><?php echo $post['com'] ?></div>
      </div>
      <?php endif ?>
    </td>
    <td class="col-reason"><?php if ($appeal['template_id']): ?><div class="template-name"><?php echo $this->templates[$appeal['template_id']] ?></div>
    <?php if ((int)$appeal['template_id'] === self::REPORT_TEMPLATE): // REPORTER START ?>
    <div class="rep-cat">Reported as <?php echo $this->get_report_category_title($post['report_cat']); ?></div>
    <?php endif ?><?php endif ?>
    <div class="public-reason"><?php echo $appeal['reason'] ?></div><?php if ($appeal['private_reason']): ?><div class="private-reason">(<?php echo $appeal['private_reason'] ?>)</div><?php endif ?>
    </td>
    <td class="col-plea"><?php echo $appeal['plea'] ?></td>
    <td class="col-length"><?php if ($appeal['active']): ?>
      <div class="ban-left" data-tip="Issued on <?php echo $appeal['ban_date'] ?> (<?php echo $appeal['ban_ago'] ?> ago) by <?php echo $appeal['admin'] ?>"><?php if (isset($appeal['ban_left'])) { echo $appeal['ban_left'] . ' left'; } else { echo 'Permanent'; } ?></div><div class="sxt"><?php echo $appeal['ban_ago'] ?> ago</div>
      <?php if ($appeal['appealcount']): ?><div class="user-appealcount sxt"><?php echo $appeal['appealcount'] ?> appeal<?php if ($appeal['appealcount'] != 1) echo 's' ?></div><?php endif ?><?php if ($appeal['closed'] == 1): ?><div class="appeal-denied sxt">Denied by <?php echo $appeal['closedby'] ?></div><?php endif ?>
    <?php else: ?><div class="ban-left" data-tip="Issued on <?php echo $appeal['ban_date'] ?> (<?php echo $appeal['ban_ago'] ?> ago) by <?php echo $appeal['admin'] ?>">Inactive</div>
    <?php endif ?>
    </td>
    <td class="col-ctrl"><span data-cmd="accept" class="button btn-accept">Accept &check;</span><?php if ($can_deny): ?> <span data-cmd="deny" class="button btn-deny">Deny &cross;</span><?php if ($appeal['admin'] === self::BY_AUTOBAN): ?><div class="button-tree-wrap"><span data-cmd="deny" data-amend="+1" data-tip="Deny and unban in 1 day" class="button btn-deny">+1 day</span></div><div class="button-tree-wrap"><span data-cmd="deny" data-amend="+3" data-tip="Deny and unban in 3 days" class="button btn-deny">+3 days</span></div><?php endif ?> <span data-start="<?php echo $appeal['ban_start'] ?>" data-cmd="edit" class="button btn-other">Edit</span><?php endif ?> <span data-tip data-tip-cb="APP.showBanTip" data-cmd="details" class="button btn-other">History<?php if ($appeal['ban_history']['total'] > 0) { echo(' <small>(' . $appeal['ban_history']['total'] . ')</small>'); } ?></span> <?php if ($appeal['email'] && $this->isManager): ?><span data-email="<?php echo htmlspecialchars($appeal['email'], ENT_QUOTES) ?>" data-cmd="contact" class="button btn-other">Contact</span> <?php endif ?><a href="/bans?action=update&amp;id=<?php echo $appeal['no'] ?>" target="_blank" class="edit-link">Ban Details</a><a class="edit-link" data-tip="Link to this Appeal" href="?q=<?php echo $appeal['no'] ?>">Appeal Link</a><script type="application/json"><?php echo json_encode($appeal['ban_history']) ?></script></td>
  </tr>
<?php endforeach ?>
</tbody>
</table>
<footer></footer>
<div id="edit-form-cnt" class="hidden">
  <table>
    <tbody>
    <tr>
      <th>Issued</th>
      <td><span id="js-eb-fa"></span></td>
    </tr>
    <tr>
      <th>Expires in</th>
      <td><span id="js-eb-ne"></span> day(s)</td>
    </tr>
    <tr>
      <th>Length</th>
      <td><input id="edit-len-field" class="ban-field" name="len" type="text" required> day(s)</td>
    </tr>
    <tr class="hidden">
      <td class="edit-presets-row" colspan="2"><span data-cmd="edit-preset" data-len="3" class="button btn-other">3 days</span> <span data-cmd="edit-preset" data-len="7" class="button btn-other">7 days</span> <span data-cmd="edit-preset" data-len="14" class="button btn-other">14 days</span> <span data-cmd="edit-preset" data-len="30" class="button btn-other">30 days</span></td>
    </tr>
    <tr>
      <td class="edit-presets-row" colspan="2"><span class="edit-presets-lbl">Unban in:</span><span data-cmd="edit-preset" data-len="+1" class="button btn-other">1 day</span> <span data-cmd="edit-preset" data-len="+3" class="button btn-other">3 days</span> <span data-cmd="edit-preset" data-len="+7" class="button btn-other">7 days</span> <span data-cmd="edit-preset" data-len="+30" class="button btn-other">30 days</span></td>
    </tr>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2">
          <span data-cmd="edit-deny" class="button btn-deny">Deny</span> <span data-cmd="edit-cancel" class="button btn-other">Cancel</span><input type="hidden" id="edit-id-field">
        </td>
      </tr>
    </tfoot>
  </table>
</div>
<div data-cmd="shift-panel" id="panel-stack" tabindex="-1"></div>
</body>
</html>
