<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html class="thin-scroll">
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Team 4chan</title>
  <link rel="stylesheet" type="text/css" href="/css/index.css?3">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <base target="content">
</head>
<body>
<ul id="nav-menu">
  <?php if ($this->is_manager || $this->is_dev): // Manager ?>
    <li><a href="?action=dashboard">Overview</a></li>
  <?php endif ?>
    <li><a href="?action=scoreboard">Scoreboard</a></li>
  <li class="nav-menu-sep"></li>
    <li><a href="/search">Search</a></li>
  <li class="nav-menu-sep"></li>
    <li><a target="_blank" href="https://reports.4chan.org/">Reports</a></li>
    <li><a target="_blank" href="https://reports.4chan.org/?action=ban_requests">Ban Requests</a></li>
    <li><a href="/bans">Bans</a></li>
    <li><a href="/appeals">Appeals</a></li>
    <li><a href="/staffmessages.php">Staff Messages</a></li>
  <li class="nav-menu-sep"></li>
  <?php if ($this->is_manager || has_flag('postfilter')): // Manager + Postfilter ?>
    <li><a href="/postfilter">Post Filter</a></li>
  <?php else: ?>
    <li><a href="/postfilter?action=view">Post Filter</a></li>
  <?php endif // Manager + Postfilter ?>
  <?php if ($this->is_manager || has_flag('blacklist') || $this->is_dev): // Manager + Blacklist ?>
  <li class="nav-menu-sep"></li>
    <li><a href="/blacklist">Blacklist</a></li>
  <?php endif // Manager + Blacklist ?>
  <li class="nav-menu-sep"></li>
    <li><a href="/stafflog">Staff Action Log</a></li>
  <?php if ($this->is_manager || $this->is_dev): // Manager ?>
    <li><a href="/userdellog.php">User Deletion Log</a></li>
  <?php endif // Manager ?>
    <li><a href="/checkmd5">Check MD5</a></li>
    <li><a href="/postfilter?action=test">Check Filter</a></li>
    <li><a href="/janitorapps">Janitor Applications</a></li>
    <li><a href="/stats">Board Stats</a></li>
  <?php if ($this->is_manager || $this->is_dev): // Manager ?>
  <li class="nav-menu-sep"></li>
    <li><a href="/manager/staffroster">Staff Roster</a></li>
    <li><a href="/manager/addaccount">Add Account</a></li>
    <li><a href="/manager/capcodes.php">Capcodes</a></li>
    <li><a href="/manager/resetpass">Reset Password</a></li>
    <li><a href="/manager/contacttool">Contact Tool</a></li>
    <li><a href="https://team.4chan.org/manager/feedback?action=review">Feedback Tool</a></li>
  <li class="nav-menu-sep"></li>
    <li><a href="/manager/ban_templates.php">Ban Templates</a></li>
    <li><a href="/manager/report_categories.php">Report Categories</a></li>
    <li><a href="/manager/iprangebans">IP Range Bans</a></li>
    <li><a href="/manager/autopurge">Autopurge</a></li>
    <li><a href="/manager/floodlog.php">Flood Logs</a></li>
    <li><a href="/manager/dmcatool">DMCA Tool</a></li>
    <li><a href="/manager/iplookup">IP Lookup</a></li>
    <li><a href="/manager/maintenance">Maintenance Tools</a></li>
    <li><a href="/manager/contest_banners.php">Contest Banners</a></li>
  <li class="nav-menu-sep"></li>
    <li><a href="/manager/view4chanpass">Search 4chan Passes</a></li>
    <li><a href="/manager/reset4chanpass">Reset 4chan Pass</a></li>
    <?php if ($this->is_admin || $this->is_dev): // Admin ?>
    <li><a href="/admin/create4chanpass">Create 4chan Pass</a></li>
    <li><a href="/admin/refund4chanpass">Refund 4chan Pass</a></li>
    <?php endif // Admin ?>
  <?php endif // Manager ?>
  <?php if ($this->is_admin || $this->is_dev): // Admin ?>
  <li class="nav-menu-sep"></li>
    <li><a href="/admin/blotter">Blotter</a></li>
    <li><a href="/admin/globalmsgedit">Site Messages</a></li>
    <li><a target="_blank" href="/developer/pma/">phpMyAdmin</a></li>
  <?php endif // Admin ?>
  <?php if ($this->is_admin || ($this->is_manager && has_flag('legal')) || $this->is_dev): ?>
    <li><a href="/manager/legalrequest">Legal Requests</a></li>
  <?php endif ?>
  <?php if ($this->is_admin || ($this->is_manager && has_flag('ncmec')) || $this->is_dev): ?>
    <li><a href="/manager/ncmecreports">NCMEC Reports</a></li>
  <?php endif ?>
  <li class="nav-menu-sep"></li>
    <li><a target="_blank" href="https://reports.4chan.org/changepass">Change Password</a></li>
    <li><a target="_blank" href="https://reports.4chan.org/login?action=do_logout">Logout</a></li>
</ul>
</body>
</html>
