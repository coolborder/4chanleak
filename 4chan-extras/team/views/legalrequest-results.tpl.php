<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Legal Requests</title>
  <link rel="stylesheet" type="text/css" href="/css/legalrequest.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/legalrequest.js"></script>
</head>
<body data-page="results">
<header>
  <h1 id="title">Legal Requests</h1>
</header>
<div id="menu">
<ul class="right">
  <li><span class="button button-light" id="debug-toggle">Toggle Debug Output</span></li>
</ul>
<ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<div class="pre-block"><?php echo htmlspecialchars($this->report) ?></div>
<?php if ($this->old_report_ids): ?>
<h3>Related Reports</h3><ul class="rel-rep-list">
<?php foreach ($this->old_report_ids as $id): ?>
<li><a href="?action=view&amp;id=<?php echo $id ?>"><?php echo $id ?></a></li>
<?php endforeach ?></ul>
<?php endif ?>
<?php if ($this->req_type === self::TYPE_EM_DISC && $this->post_contents): ?>
<h3>Post Contents</h3>
<div class="pre-block"><?php echo htmlspecialchars($this->post_contents) ?></div>
<?php endif ?>
<h3>Request Information</h3>
<form id="form-save" class="form" action="<?php echo self::WEBROOT ?>" method="POST" enctype="multipart/form-data">
  <input type="hidden" name="description" value="<?php echo htmlspecialchars($this->description, ENT_QUOTES) ?>">
  <input type="hidden" name="req_type" value="<?php echo $this->req_type; ?>">
  <input type="hidden" name="report" value="<?php echo htmlspecialchars($this->report, ENT_QUOTES) ?>">
  <input type="hidden" id="raw-data" name="raw_report" value="<?php echo $this->previous_reports . htmlspecialchars($this->get_mysql_query_log(), ENT_QUOTES) ?>">
  <input type="hidden" name="MAX_FILE_SIZE" value="25165824"> 
  <table>
    <tr>
      <th>Request Type</th>
      <td><?php echo $this->valid_types[$this->req_type] ?></td>
    </tr>
    <tr>
      <th>Request Date</th>
      <td><input required pattern="\d\d/\d\d/\d\d\d\d" placeholder="MM/DD/YYYY" value="<?php echo date(self::DATE_FORMAT_SHORT) ?>" type="text" name="req_date"></td>
    </tr>
    <tr>
      <th>Requester Name</th>
      <td><input type="text" name="req_name" required></td>
    </tr>
    <tr>
      <th>Requester E-mail</th>
      <td><input type="text" name="req_email" required></td>
    </tr>
    <tr>
      <th>Cc: E-mail(s)</th>
      <td><input type="text" name="req_cc_emails"></td>
    </tr>
    <tr>
      <th>Case ID</th>
      <td><input type="text" name="doc_id"></td>
    </tr>
    <tr>
      <th>Copy of Request E-mail</th>
      <td><textarea required name="email_content" rows="8" cols="40"></textarea></td>
    </tr>
    <tr>
      <th class="cell-top cell-file">Copy of Attachment(s)</th>
      <td id="file-cnt"><input type="file" name="doc_file[]"></td>
    </tr>
    <tr>
      <th></th>
      <td><span id="add-file" class="button btn-other">Add Another File</span></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2"><button class="button btn-other" type="submit" name="action" value="save">Save Report</button></td>
      </tr>
    </tfoot>
  </table>
  <?php echo csrf_tag() ?>
</form>
</div><div id="debug-data" class="hidden"><?php echo implode("\n", $this->debug_log) ?></div>
<footer></footer>
</body>
</html>
