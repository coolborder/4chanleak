<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Range Bans</title>
  <link rel="stylesheet" type="text/css" href="/css/iprangebans.css?25">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <?php if (!$this->ids): ?><script type="text/javascript" src="/js/cidr.js"></script><?php endif; ?>
  <script type="text/javascript" src="/js/iprangebans.js?23"></script>
</head>
<body>
<header>
  <h1 id="title">Range Bans</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<div class="form-cnt">
<form id="form-edit-rule" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr>
      <th>Active</th>
      <td><input type="checkbox" name="active"<?php if (!$this->range || $this->range['active']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th><span data-tip="Enter _ws_ for all work safe boards." class="wot">Boards</span></th>
      <td><input type="text" name="boards" value="<?php if ($this->range) echo $this->range['boards'] ?>"></td>
    </tr>
    <tr>
      <th>OPs only</th>
      <td><input type="checkbox" name="ops_only"<?php if ($this->range && $this->range['ops_only']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th>Images only</th>
      <td><input type="checkbox" name="img_only"<?php if ($this->range && $this->range['img_only']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Only block when reporting posts. Can only be combined with the Lenient option.">Report only</span></th>
      <td><input type="checkbox" name="report_only"<?php if ($this->range && $this->range['report_only']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Only block users with no known posting history">Lenient</span></th>
      <td><input type="checkbox" name="lenient"<?php if ($this->range && $this->range['lenient']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Can be found inside the Ban Panel in the More Info Section">Browser IDs</span></th>
      <td><input class="short-value" type="text" name="ua_ids" value="<?php if ($this->range) echo $this->range['ua_ids'] ?>"></td>
    </tr>
    <tr>
      <th>Description</th>
      <td><input type="text" id="field-desc" required name="description" value="<?php if ($this->range) echo $this->range['description'] ?>"<?php if ($this->ids): ?> class="field-desc-edit"<?php endif ?>><?php if ($this->ids): ?> <input data-tip="Overwrite descriptions" id="js-update-desc" type="checkbox" name="update_desc" value="1"><?php endif ?></td>
    </tr>
    <tr>
    <?php if ($this->range): ?>
      <th>Expires on</th>
      <td><input pattern="\d\d/\d\d/\d\d \d\d:\d\d" placeholder="MM/DD/YY HH:MM" type="text" name="expires" value="<?php if ($this->range && $this->range['expires_on']) echo date(self::DATE_FORMAT, $this->range['expires_on']) ?>"></td>
    <?php else: ?>
      <th>Expires in</th>
      <td><input class="int-value" type="text" name="expires"> hour(s)</td>
    <?php endif ?>
    </tr>
    <?php if ($this->range && !$this->ids): ?>
    <tr>
      <th>CIDR</th>
      <td><input type="text" name="ranges" value="<?php echo $this->range['cidr'] ?>"></td>
    </tr>
    <?php elseif ($this->ids): ?>
    <tr>
      <th>CIDR</th>
      <td><?php $aff = count($this->ranges); echo $aff ?> entr<?php echo($aff == 1 ? 'y' : 'ies') ?> will be affected.</td>
    </tr>
    <?php else: ?>
    <tr>
      <th><span class="wot" data-tip="CIDR notation, one entry per line">IP ranges</span></th>
      <td><textarea id="js-ranges-field" name="ranges" rows="8" cols="40"><?php if ($this->from_ranges) echo $this->from_ranges; ?></textarea></td>
    </tr>
    <?php endif ?>
    <?php if ($this->range && $this->range['updated_on']): ?>
    <tr class="row-sep">
      <th><hr>Updated on</th>
      <td><hr><?php echo date(self::DATE_FORMAT, $this->range['updated_on']) ?> (<?php echo $this->getDuration($_SERVER['REQUEST_TIME'] - $this->range['updated_on']) ?> ago)</td>
    <?php endif ?>
    </tr>
  </table>
  <?php if ($this->ids): ?>
  <input type="hidden" name="ids" value="<?php echo $this->ids ?>">
  <?php elseif ($this->range): ?>
  <input type="hidden" name="id" value="<?php echo $this->range['id'] ?>">
  <?php endif ?>
  <div class="txt-right"><button class="button btn-other" type="submit" name="action" value="update">Save</button></div>
  <?php echo csrf_tag() ?>
</form>
<?php if ($this->range || $this->ids): ?>
<form id="form-del-rule" action="" method="POST" enctype="multipart/form-data">
  <?php if ($this->ids): ?>
  <input type="hidden" name="ids" value="<?php echo $this->ids ?>">
  <?php else: ?>
  <input type="hidden" name="id" value="<?php echo $this->range['id'] ?>">
  <?php endif ?>
  <div class="txt-right"><button class="button btn-deny"type="submit" name="action" value="delete">Delete</button></div>
  <?php echo csrf_tag() ?>
</form>
<?php endif ?>
</div>
<?php if (!$this->ids): ?>
<div class="calc-cnt">
  <div class="calc-title">Subnet Calculator</div>
  <div class="calc-form-grp">
    <div class="calc-lbl">CIDR to Range</div>
    <form autocomplete="off" action="" id="js-calc-cidr-form"><input placeholder="CIDR" type="text" class="calc-input" id="js-calc-cidr">
    <button class="button btn-other" id="js-calc-cidr-btn">Calculate</button></form>
  </div>
  <div class="calc-form-grp">
    <div class="calc-lbl">Range to CIDR</div>
    <form autocomplete="off" action="" id="js-calc-ip-form"><input placeholder="Start IP" type="text" class="calc-input" id="js-calc-ip-s">
    <input placeholder="End IP" type="text" class="calc-input" id="js-calc-ip-e">
    <button class="button btn-other" id="js-calc-ip-btn">Calculate</button></form>
  </div>
  <div class="calc-res-cnt hidden">
    <div class="calc-res" id="js-calc-res"></div>
    <button data-cmd="use-calc-res" data-tip="Use this result" class="button btn-other" id="js-res-btn"><<<</button>
  </div>
</div>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
