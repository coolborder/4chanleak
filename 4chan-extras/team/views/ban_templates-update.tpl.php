<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Ban Templates</title>
  <link rel="stylesheet" type="text/css" href="/css/ban_templates.css?3">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/ban_templates.js?4"></script>
</head>
<body data-tips>
<header>
  <h1 id="title">Ban Templates</h1>
</header>
<div id="menu">
<ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<form id="form-edit-rule" class="form" action="" method="POST" enctype="multipart/form-data">
  <table>
    <?php if ($this->tpl): ?>
    <tr>
      <th>ID</th>
      <td><?php echo $this->tpl['no'] ?></td>
    </tr>
    <?php endif ?>
    <tr>
      <th><span data-tip="Must start with a board short name or 'global', followed by an alphanumeric ID. Example: a1, global6" class="wot">Rule ID</span></th>
      <td><input type="text" name="rule" pattern="[a-z0-9]+" value="<?php if ($this->tpl) echo $this->tpl['rule'] ?>" required></td>
    </tr>
    <tr>
      <th>Name</th>
      <td><input type="text" name="name" value="<?php if ($this->tpl) echo $this->tpl['name'] ?>" required></td>
    </tr>
    <tr>
      <th>Ban Type</th>
      <td><select name="ban_type" size="1">
        <?php foreach ($this->ban_types as $ban_type => $label): ?>
        <option<?php if ($this->tpl && $this->tpl['bantype'] === $ban_type) echo ' selected' ?> value="<?php echo $ban_type ?>"><?php echo $label ?></option>
        <?php endforeach ?>
      </select></td>
    </tr>
    <tr>
      <th>Ban Length</th>
      <td><input class="ban-len-field" type="text" name="ban_days" value="<?php if ($this->tpl) echo (int)$this->tpl['days'] ?>" required> day(s). -1 for a permaban, 0 for a warning.</td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Template can be used as a Warning">Can Warn</span></th>
      <td><input type="checkbox" value="1" name="can_warn"<?php if ($this->tpl && $this->tpl['can_warn']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Add a public ban message to the banned post">Public Ban</span></th>
      <td><input type="checkbox" value="1" name="publicban"<?php if ($this->tpl && $this->tpl['publicban']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Allow bans to be displayed publicly on the /bans page">Display Publicly</span></th>
      <td><input type="checkbox" value="1" name="is_public"<?php if ($this->tpl && $this->tpl['is_public']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th class="cell-top">Public Reason</th>
      <td><textarea name="public_reason" required><?php if ($this->tpl) echo htmlspecialchars($this->tpl['publicreason']); ?></textarea></td>
    </tr>
    <tr>
      <th class="cell-top">Private Reason</th>
      <td><textarea name="private_reason" required><?php if ($this->tpl) echo htmlspecialchars($this->tpl['privatereason']); else echo htmlspecialchars(self::DEFAULT_PRIVATE_REASON) ?></textarea></td>
    </tr>
    <tr>
      <th>Post-ban Action</th>
      <td><select id="js-postban-sel" name="postban" size="1">
        <?php foreach ($this->postban_types as $postban_type => $label): ?>
        <option<?php if ($this->tpl && $this->tpl['postban'] === $postban_type) echo ' selected' ?> value="<?php echo $postban_type ?>"><?php echo $label ?></option>
        <?php endforeach ?>
      </select></td>
    </tr>
    <tr id="js-postban-arg-cnt" style="display:none">
      <th>Post-ban Param</th>
      <td><input id="js-postban-arg-field" type="text" name="postban_arg" value="<?php if ($this->tpl && $this->tpl['postban_arg']) echo htmlspecialchars($this->tpl['postban_arg']) ?>"></td>
    </tr>
    <tr>
      <th>Blacklist File</th>
      <td><select name="blacklist" size="1">
        <?php foreach ($this->blacklist_types as $blacklist_type => $label): ?>
        <option<?php if ($this->tpl && $this->tpl['blacklist'] === $blacklist_type) echo ' selected' ?> value="<?php echo $blacklist_type ?>"><?php echo $label ?></option>
        <?php endforeach ?>
      </select></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="'Quarantine' will revoke Passes if used by a Manager">Special Action</span></th>
      <td><select name="special_action" size="1">
        <?php foreach ($this->action_types as $action_type => $label): ?>
        <option<?php if ($this->tpl && $this->tpl['special_action'] === $action_type) echo ' selected' ?> value="<?php echo $action_type ?>"><?php echo $label ?></option>
        <?php endforeach ?>
      </select></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="For ban requests">Save Post</span></th>
      <td><select name="save_post" size="1">
        <?php foreach ($this->save_types as $save_type => $label): ?>
        <option<?php if ($this->tpl && $this->tpl['save_post'] === $save_type) echo ' selected' ?> value="<?php echo $save_type ?>"><?php echo $label ?></option>
        <?php endforeach ?>
      </select></td>
    </tr>
    <tr>
      <th>Required Level</th>
      <td><select name="level" size="1">
        <?php foreach ($this->access_types as $access_type => $label): ?>
        <option<?php if ($this->tpl && $this->tpl['level'] === $access_type) echo ' selected' ?> value="<?php echo $access_type ?>"><?php echo $label ?></option>
        <?php endforeach ?>
      </select></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2"><button class="button btn-other" name="action" value="update" type="submit">Save</button></td>
      </tr>
    <tfoot>
  </table>
  <?php if ($this->tpl): ?>
  <input type="hidden" name="id" value="<?php echo $this->tpl['no'] ?>">
  <?php endif ?>
  <?php echo csrf_tag() ?>
</form>
</div>
<footer></footer>
</body>
</html>
