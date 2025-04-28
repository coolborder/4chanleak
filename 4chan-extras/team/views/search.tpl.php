<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="never">
  <title>Search</title>
  <link rel="stylesheet" type="text/css" href="/css/admincore.css?3">
  <link rel="stylesheet" type="text/css" href="/css/search.css?30">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/search.js?45"></script>
  <script id="data-boards" type="application/json"><?php echo $this->get_boards_json(); ?></script>
</head>
<body data-maxboardres="<?php echo self::MAX_BOARD_RESULTS ?>" data-maxres="<?php echo self::MAX_RESULTS ?>" data-maxipbans="<?php echo self::MAX_IP_BANS ?>" <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Search</h1><div id="cfg-btn"><span>&hellip;</span><div><label><input data-cmd="toggle-ih" id="cfg-cb-ih" checked type="checkbox">Enable Image Hover</label><label><input data-cmd="toggle-dt" id="cfg-cb-dt" type="checkbox" autocomplete="off">Dark Theme</label></div></div>
</header>
<div id="content">
<form id="search-form" name="search" class="form search-form" action="<?php echo self::WEBROOT ?>" method="GET">
  <fieldset id="search-fields">
  <table class="form-grp-tbl">
    <tr>
      <th><span data-type="boards" class="wot" data-tip data-tip-cb="APP.showFieldTip">Boards</span></th>
      <td><input id="boards-field" type="text"></td>
    </tr>
    <tr>
      <th><span data-type="tid" class="wot" data-tip data-tip-cb="APP.showFieldTip">Thread ID</span></th>
      <td><input class="s-p" type="text" id="js-tid-field" name="thread_id"></td>
    </tr>
    <tr>
      <th><span data-type="net" class="wot" data-tip data-tip-cb="APP.showFieldTip">IP or CIDR</span></th>
      <td><input class="s-p" type="text" name="ip"></td>
    </tr>
    <tr>
      <th><span data-type="txt" class="wot" data-tip data-tip-cb="APP.showFieldTip">Nametrip</span></th>
      <td><input class="s-p" type="text" name="nametrip"></td>
    </tr>
    <tr>
      <th><span data-type="txt" class="wot" data-tip data-tip-cb="APP.showFieldTip">Subject</span></th>
      <td><input class="s-p" type="text" name="subject"></td>
    </tr>
    <tr>
      <th><span data-type="txt" class="wot" data-tip data-tip-cb="APP.showFieldTip">Comment</span></th>
      <td><input class="s-p" type="text" name="comment"></td>
    </tr>
    <tr>
      <th><span data-type="str" class="wot" data-tip data-tip-cb="APP.showFieldTip">Password</span></th>
      <td><input class="s-p" type="text" name="password"></td>
    </tr>
  </table>
  <table class="form-grp-tbl">
    <tr>
      <th><span data-type="file" class="wot" data-tip data-tip-cb="APP.showFieldTip">File name</span></th>
      <td><input class="s-p field-80-xs" type="text" name="filename"><div class="form-inner-grp"><label data-type="fext" class="wot" data-tip data-tip-cb="APP.showFieldTip">Ext.</label><input class="s-p field-50-xs" type="text" name="ext"></div></td>
    </tr>
    <tr>
      <th><span data-type="int" class="wot" data-tip data-tip-cb="APP.showFieldTip">File dimensions</span></th>
      <td><input class="s-p f-si" type="text" name="img_w"><span class="s-times">×</span><input class="s-p f-si" type="text" name="img_h"></td>
    </tr>
    <tr>
      <th><span data-type="intbytes" class="wot" data-tip data-tip-cb="APP.showFieldTip">File size</span></th>
      <td><input class="s-p" type="text" name="filesize"></td>
    </tr>
    <tr>
      <th><span data-type="str" class="wot" data-tip data-tip-cb="APP.showFieldTip">File MD5</span></th>
      <td><input class="s-p" type="text" name="md5"></td>
    </tr>
    <tr>
      <th><span data-type="phash" class="wot" data-tip data-tip-cb="APP.showFieldTip">File similarity</span></th>
      <td><input id="js-phash-field" class="s-p" type="text" name="phash" pattern="[<>0-9a-f]{14,19}"></td>
    </tr>
    <tr>
      <th><span data-type="tim" class="wot" data-tip data-tip-cb="APP.showFieldTip">File timestamp</span></th>
      <td><input class="s-p" type="text" name="fileuid"></td>
    </tr>
    <tr>
      <th><span data-type="ago" class="wot" data-tip data-tip-cb="APP.showFieldTip">Post time</span></th>
      <td><span class="st">Less than </span><input class="s-p f-ti" name="ago" type="text"><span class="st"> hour(s) ago</span></td>
    </tr>
  </table>
  <table class="form-grp-tbl">
    <tr>
      <th><span data-type="country" class="wot" data-tip data-tip-cb="APP.showFieldTip">Country code</span></th>
      <td><input class="s-p field-50-xs" type="text" name="country"><div class="form-inner-grp"><label data-type="loc" class="wot" data-tip data-tip-cb="APP.showFieldTip">Loc.</label><input class="s-p field-80-xs" type="text" id="js-loc-field" name="loc"></div></td>
    </tr>
    <?php if ($this->is_manager): ?>
    <tr>
      <th><span data-type="pass" class="wot" data-tip data-tip-cb="APP.showFieldTip">4chan Pass</span></th>
      <td><input class="s-p field-80-xs" type="text" name="pass_id"><div class="form-inner-grp"><label data-type="passref" class="wot" data-tip data-tip-cb="APP.showFieldTip">Ref.</label><input class="s-p field-50-xs" type="text" name="pass_ref"></div><input class="hidden s-p" type="text" name="_meta"></td>
    </tr>
    <?php else: ?>
    <tr>
      <th><span data-type="passref" class="wot" data-tip data-tip-cb="APP.showFieldTip">Pass Ref.</span></th>
      <td><input class="s-p" type="text" name="pass_ref"></td>
    </tr>
    <?php endif ?>
    <tr>
      <th><span data-type="reqsig" class="wot" data-tip data-tip-cb="APP.showFieldTip">Req. sig.</span></th>
      <td><input class="s-p field-80-xs" type="text" name="req_sig"><div class="form-inner-grp"><label data-type="browserid" class="wot" data-tip data-tip-cb="APP.showFieldTip">UA</label><input class="s-p field-50-xs" type="text" name="browser_id"></div></td>
    </tr>
    <tr>
      <th>User status</th>
      <td>
      <select name="usrs" class="s-p" id="user-status-field">
        <option value=""></option>
        <option value="n">New</option>
        <?php if ($this->is_manager): ?><option value="u">Untrusted</option><?php endif ?>
      </select>
      </td>
    </tr>
    <tr>
      <th><label for="arc-field">Archived</label></th>
      <td><input id="arc-field" class="s-p" type="checkbox" name="archived"><?php if ($this->is_manager): ?>
      <div class="form-inner-grp"><label data-type="opt" class="wot" data-tip data-tip-cb="APP.showFieldTip" for="spec-field">Opts</label><input id="spec-field" class="s-p" type="checkbox" name="has_opts"></div>
      <div class="form-inner-grp"><label for="capcode-field">Capcode</label><input id="capcode-field" class="s-p" type="checkbox" name="has_capcode"></div>
      <?php endif ?></td>
    </tr>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <tr>
      <th>Group by</th>
      <td>
      <select id="group-field">
        <option value="board">Board</option>
        <option data-int value="resto">Thread ID</option>
        <option value="host">IP</option>
        <option value="pwd">Password</option>
        <option value="4pass_id">4chan Pass</option>
        <option value="country">Country</option>
      </select>
      </td>
    </tr>
    <tr class="thin-row">
      <th></th>
      <td><div class="tree-wrap st"><input id="group-sort-field" class="s-p" type="checkbox" name="gss"> <label for="group-sort-field">Sort by post count</label></div></td>
    </tr>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2">
          <input id="search-btn-dummy" type="submit"><span id="search-btn" class="button btn-accept">Search</span> <span id="reset-btn" class="button btn-other">Reset</span> <span data-cmd="save-search" class="button btn-other">Save</span>
        </td>
      </tr>
    </tfoot>
  </table>
  <div id="js-saved-searches" class="form-grp-tbl"></div>
  </fieldset>
</form>
<div class="hidden" id="results-ctrl"><span id="js-results-ok"></span><span id="js-results-err"></span><span class="grp-ctrl"><span data-cmd="pre-del-all" class="button btn-other">Delete All</span><span data-cmd="pre-del-all" data-fileonly class="button btn-other">Delete All Files</span><span id="ban-all-btn" data-all data-cmd="ban-multi" class="button btn-other">Ban All</span></span></div>
<div id="search-results"></div>
</div>
<footer></footer>
<div class="hidden">
<div id="tip-boards"><div class="ftt">Leave blank to search on all boards. Supports the negation (!) prefix.</div></div>
<div id="tip-tid"><div class="ftt">Thread ID, URL or subject.<br>OPs have an ID of 0 (zero).<br>To search only replies use <b>&gt;0</b><br>Searching by subject uses the<br>same syntax as the Subject field.</div></div>
<div id="tip-int"><div class="ftt">Supports comparison operators<br><b>&gt; &lt; &gt;= &lt;=</b> and ranges <b>x-y</b>.</div></div>
<div id="tip-intbytes"><div class="ftt">Supports comparison operators<br><b>&gt; &lt; &gt;= &lt;=</b> and ranges <b>x-y</b>. Units can be bytes, KB or MB.</div></div>
<div id="tip-ago"><div class="ftt">Accepts integer or decimal values</div></div>
<div id="tip-txt"><div class="ftt">Substring match.<br>Also supports<br>"exact matches" and /regex[ep]s/b<br>No need to escape slashes inside regex patterns.<br>Use the "b" flag for case-sensitive (binary) regex searches.</div></div>
<div id="tip-file"><div class="ftt">Substring match without the extension.<br>Also supports<br>"exact matches" and /regex[ep]s/b<br>No need to escape slashes inside regex patterns.<br>Use the "b" flag for case-sensitive (binary) regex searches.</div></div>
<div id="tip-str"><div class="ftt">Exact match.<br>Also supports multiple<br>comma-separated values.</div></div>
<div id="tip-phash"><div class="ftt">Perceptual hash of the thumbnail.<br>Can be used to find visually similar files.<br>Found on ban pages in the thumbnail section.<br>Prefix the hash with up to 3 <b>&gt;</b> or <b>&lt;</b> signs for broader or stricter matches.</div></div>
<div id="tip-fext"><div class="ftt">Exact match including the dot prefix.<br>Also supports multiple<br>comma-separated values.</div></div>
<div id="tip-pass"><div class="ftt">Exact match or * for any post with a pass.<br>Also supports multiple<br>comma-separated values and the negation (!) prefix.</div></div>
<div id="tip-strnot"><div class="ftt">Exact match.<br>Also supports multiple<br>comma-separated values and the negation (!) prefix.</div></div>
<div id="tip-country"><div class="ftt">Two-letter code.<br>Supports multiple comma-separated values and the negation (!) prefix.<br>The <b>_safe_</b> placeholder expands to a short list of countries from NA and EU.</div></div>
<div id="tip-tim"><div class="ftt">Internal numeric file IDs<br>or file URLs</div></div>
<div id="tip-opt"><div class="ftt">Threads with modified options (sticky, closed, etc)</div></div>
<div id="tip-net"><div class="ftt">Supports multiple<br>comma-separated values<br>and trailing wildcards (*)</div></div>
<div id="tip-loc"><div class="ftt">State or City name. Exact match.<br>Can only be used when Board and Thread ID are provided.</div></div>
<div id="tip-passref"><div class="ftt">Post or Ban referencing a 4chan Pass.<br>Can be a post URL, ban URL,<br>ban ID or /board/post_id</div></div>
<div id="tip-reqsig"><div class="ftt">Signature of the HTTP request.<br>Can be found inside the Ban Panel in the More Info section.<br>Can not be used to reliably identify users.<br>Using the wildcard (*) operator allows to only search posts made by fresh IPs.</div></div>
<div id="tip-browserid"><div class="ftt">Browser ID.<br>Can be found inside the Ban Panel in the More Info section.<br>Can not be used to reliably identify users.<br>Supports multiple comma-separated values</div></div>

<div id="js-err-loc">The Board and Thread ID fields cannot be empty when searching by location.</div>
<div id="js-err-phash">File similarity search can only be used on live posts.</div>
</div>
<div id="ban-form-cnt" class="hidden"><form id="ban-form" autocomplete="off" action="" method="post" class="form">
  <table>
    <tbody>
    <tr>
      <th>Template</th>
      <td><select id="ban-templates-sel" class="ban-field"><option value="">Loading…</option></select></td>
    </tr>
    <tr>
      <th>Pub. reason</th>
      <td><textarea id="js-ban-reason" class="ban-field" name="public_reason" type="text" required></textarea></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Search terms will be included if left empty">Priv. reason</span></th>
      <td><input class="ban-field" name="private_reason" type="text"></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="-1 for a permanent ban">Ban days</span></th>
      <td><input id="js-ban-days" class="ban-field" name="days" type="text" required></td>
    </tr>
    <tr>
      <th><label for="js-btn-global">Global</label></th>
      <td><input id="js-btn-global" class="ban-field" name="global" type="checkbox" value="1" checked></td>
    </tr>
    </tbody>
    <tfoot>
      <tr>
        <th><label for="js-btn-no-reverse"><span class="wot" data-tip="Don't perform reverse IP lookup">No reverse</span></label></th>
        <td>
          <input id="js-btn-no-reverse" class="left" name="no_reverse" value="1" type="checkbox">
          <input id="ban-btn-dummy" type="submit"><span data-cmd="submit-ban" class="button btn-deny">Ban</span> <span data-cmd="cancel-ban" class="button btn-other">Cancel</span><input class="ban-field" type="hidden" name="ips" id="ban-ips-field">
        </td>
      </tr>
    </tfoot>
  </table>
</form></div></body>
</html>
