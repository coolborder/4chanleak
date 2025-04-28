<?php
//if ($_SERVER['REMOTE_ADDR'] !== '51.159.28.165') {
  die();
//}

header( 'Cache-Control: private, no-cache, no-store' );
header( 'Expires: -1' );
header( 'Vary: *' );
header( 'Strict-Transport-Security: max-age=15768000' );

$mysql_suppress_err = true;

require_once 'lib/db.php';
require_once 'lib/util.php';
require_once 'lib/geoip2.php';

define('IS_4CHANNEL', preg_match('/(^|\.)4channel.org$/', $_SERVER['HTTP_HOST']));

$url_domain = (IS_4CHANNEL ? '4channel.org' : '4chan.org');

/* COUNTRY BLOCK */
$_blocked_countries = array('CI', 'CU', 'IR', 'KP', 'MM', 'SY');

$geo_data = GeoIP2::get_country($_SERVER['REMOTE_ADDR']);

if ($geo_data && isset($geo_data['country_code'])) {
  $_country = $geo_data['country_code'];
}
else {
  $_country = 'XX';
}

if ($_country && in_array($_country, $_blocked_countries)) {
  die('Purchases from your country have been blocked due to US sanctions.');
}

function error($msg, $stealth = false) { ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="pragma" content="no-cache">
  <title>4chan Pass - Reset</title>
  <link rel="stylesheet" style="text/css" href="//s.4cdn.org/css/yotsubanew.361.css">
  <style type="text/css">
  #error {
    font-size: x-large;
    color: red;
    text-align: center;
  }
  #success {
    font-size: x-large;
    text-align: center;
  }
  </style>
</head>
<body>
<div class="boardBanner">
  <div class="boardTitle">4chan Pass Reset</div>
</div>
<hr style="width: 90%">
<br>
<div id="<?php if ($stealth) { echo 'success'; } else { echo 'error'; } ?>"><?php echo $msg ?></div>
</body>
</html>
<?php
  die();
}

/*
function bottom_ad_728x90() {

}
*/

if (isset($_GET['req_renew'])) {
require_once 'lib/captcha.php';
/**
 *
 *
 * Request renewal
 *
 *
 */
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="pragma" content="no-cache">
  <title>4chan Pass - Renew</title>
  <link rel="stylesheet" style="text/css" href="//s.4cdn.org/css/yotsubanew.361.css">
  <style type="text/css">
  #recaptcha_area .recaptchatable {background-color: transparent !important; border: none !important;}
  #recaptcha_table .recaptcha_image_cell {
    background-color: transparent !important;
    padding: 0 !important;
  }
  #recaptcha_div {height: 107px; width: 442px;}
  #recaptcha_challenge_field {width: 400px}
  
  .recaptcha_input_area {
    padding: 0!important;
  }
  #recaptcha_table tr:first-child {
    height: auto!important;
  }

  #recaptcha_table tr:first-child > td:not(:first-child) {
    padding: 0 7px 0 7px!important;
  }

  #recaptcha_table tr:last-child td:last-child {
    padding-bottom: 0!important;
  }

  #recaptcha_table tr:last-child td:first-child {
    padding-left: 0!important;
  }
  #recaptcha_response_field {
    width: 292px !important;
    margin-right: 0px !important;
    font-size: 10pt !important;
  }
  input:-moz-placeholder { color: gray !important; }
  #recaptcha_image {
    border: 1px solid #aaa !important;
  }
  #recaptcha_table tr > td:last-child {
    display: none !important;
  }
  #captchaContainer {
    height: 81px;
  }
  .postForm {
    width: 436px;
  }
  </style>
  <script type="text/javascript">
  function onCaptchaReady() {
  var el;
  
  if (el = document.getElementById('recaptcha_image')) {
    el = document.getElementById('recaptcha_response_field');
    el.setAttribute('placeholder', 'Type the text (Required)');
    el.setAttribute('spellcheck', 'false');
    el.setAttribute('autocorrect', 'off');
    el.setAttribute('autocapitalize', 'off');
    }
  }
  </script>
</head>
<body>
<div class="boardBanner">
  <div class="boardTitle">4chan Pass Renew</div>
</div>
<hr style="width: 90%">
<br>
<div style="text-align: center;"></div><form action="https://www.<?php echo (IS_4CHANNEL ? '4channel.org' : '4chan.org') ?>/pass" method="post">
  <input type="hidden" name="req_renew" value="1" />
  <table class="postForm">
    <tbody>
      <tr>
        <td style="text-align: center">Token</td>
        <td><input type="text" name="pass_id" style="width: 292px; text-align: center;" /></td>
      </tr>
      <tr>
        <td style="text-align: center">E-mail</td>
        <td><input type="text" name="email" placeholder="E-mail associated with your 4chan Pass" style="width: 292px; text-align: center;" /></td>
      </tr>
      
      <tr>
        <td style="text-align: center;width: 80px">Verification</td>
        <td>
          <div id="captcha-form"><?php echo captcha_form(true, true) ?></div>
        </td>
      </tr>
      
      <tr>
        <td colspan="2" style="padding: 5px 0; border: none; background: none; text-align: center; font-weight: normal; padding-bottom: 20px;">
          <input type="submit" value="Submit" style="margin: 0px;" />
        </td>
      </tr>

      <tr>
          <td colspan="2" style="padding: 5px 0; border: none; background: none; text-align: center; font-weight: normal;">
            You can only renew your Pass if it expires in 6 months or less.<br>You will receive an e-mail containing the instructions<br>on how to renew your 4chan Pass.
          </td>
      </tr>

    </tbody>
  </table>
</form><div></div>
</body>
</html>
<?php
}
else if (isset($_POST['req_renew'])) {
/**
 *
 *
 * Renew request processing
 *
 *
 */
require_once 'lib/captcha.php';

// Error messages
define('S_NOCAPTCHA', 'Error: You forgot to solve the CAPTCHA. Please try again.');
define('S_BADCAPTCHA', 'Error: You seem to have mistyped the CAPTCHA. Please try again.');
define('S_CAPTCHATIMEOUT', 'Error: This CAPTCHA is no longer valid because it has expired. Please try again.');
define('S_FLOOD', 'Error: You must wait longer before submitting another reset request.');
define('S_NOEMAIL', 'Error: The E-mail field cannot be empty.');
define('S_NOTOKEN', 'Error: The Token field cannot be empty.');
define('S_NOACCOUNT', 'If there is a 4chan Pass with this e-mail address associated with it, an e-mail containing reset instructions will be sent. Please be sure to check your Spam folder, and if you do not receive an e-mail, contact <a href="mailto:4chanpass@4chan.org?subject=4chan%20Pass%20Reset%20-%20No%20E-mail%20Found">4chanpass@4chan.org</a> for assistance.');
define('S_DBERROR', 'Error: Database error.');
define('S_MULTIPASS', 'If there is a 4chan Pass with this e-mail address associated with it, an e-mail containing renewal instructions will be sent. Please be sure to check your Spam folder, and if you do not receive an e-mail, contact <a href="mailto:4chanpass@4chan.org?subject=4chan%20Pass%20Renewal%20-%20No%20E-mail%20Found">4chanpass@4chan.org</a> for assistance.');
// ---

function check_flood() {
  $ip = ip2long($_SERVER['REMOTE_ADDR']);
  
  $res = mysql_global_call("SELECT ip FROM user_actions WHERE ip = $ip AND action='pass_reset' AND time >= SUBDATE(NOW(), INTERVAL 5 MINUTE)");
  
  if (mysql_num_rows($res) > 0) {
    return false;
  }
  
  $res = mysql_global_call("INSERT INTO user_actions (ip,board,action,time) values ($ip,'','pass_reset',NOW())");
  
  return true;
}

if (!isset($_POST['email']) || $_POST['email'] === '') {
  error(S_NOEMAIL);
}

if (!isset($_POST['pass_id']) || $_POST['pass_id'] === '') {
  error(S_NOTOKEN);
}

mysql_global_connect();

$pass_id = $_POST['pass_id'];
$email = $_POST['email'];

$now = $_SERVER['REQUEST_TIME'];

// Captcha 
start_recaptcha_verify();

$ret = rpc_finish_request($recaptcha_ch, $error, $httperror);

// BAD
// 413 Request Too Large is bad; it was caused intentionally by the user.
if ($httperror == 413) {
  error(S_BADCAPTCHA);
}

// BAD
// Network issue.
if ($ret == null) {
  error(S_BADCAPTCHA);
}

$resp = json_decode($ret, true);

// BAD
// Malformed JSON response from Google
if (json_last_error() !== JSON_ERROR_NONE) {
  error(S_BADCAPTCHA);
}

// BAD
if (!$resp['success']) {
  error(S_BADCAPTCHA);
}

// Flood check
if (!check_flood()) {
  error(S_FLOOD);
}

$query =<<<SQL
SELECT pending_id, UNIX_TIMESTAMP(expiration_date) as expiration_timestamp
FROM pass_users
WHERE user_hash = '%s'
AND (email = '%s' OR gift_email = '%s')
AND status IN(0,1,6)
AND pin != ''
AND expiration_date <= DATE_ADD(NOW(), INTERVAL 6 MONTH)
LIMIT 1
SQL;

$res = mysql_global_call($query, $pass_id, $email, $email);

if (!$res) {
  error(S_DBERROR);
}
  
if (mysql_num_rows($res) < 1) {
  error(S_NOACCOUNT, true);
}

$row = mysql_fetch_assoc($res);

if (!$row) {
  error(S_DBERROR);
}

/**
 * Email
 */
if ((int)$row['expiration_timestamp'] <= $now) {
  $exp_notice = 'Pass will add 12 additional months from the date of your renewal payment.';
}
else {
  $exp_notice = 'Renewing your Pass will add 12 additional months to your current expiration date.';
}

$pending_id = $row['pending_id'];

// Subject
$subject = "Your 4chan Pass Renewal Request";

// Message
$message =<<<MSG
You're receiving this e-mail because you or someone on your behalf requested your 4chan Pass to be renewed.

If you did not make this request, please ignore and delete this message.

$exp_notice

You can renew your Pass by visiting the following link: https://www.4channel.org/pass?renew=$pending_id

If you have any questions or problems renewing, please e-mail 4chanpass@4chan.org
MSG;

// From:
$headers = "From: 4chan Pass <4chanpass@4chan.org>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Envelope
$opts = '-f 4chanpass@4chan.org';

set_time_limit(0);

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="pragma" content="no-cache">
  <title>4chan Pass - Renew</title>
  <link rel="stylesheet" style="text/css" href="//s.4cdn.org/css/yotsubanew.361.css">
  <style type="text/css">
  #success {
    font-size: x-large;
    text-align: center;
  }
  </style>
</head>
<body>
<div class="boardBanner">
  <div class="boardTitle">4chan Pass Renewal</div>
</div>
<hr style="width: 90%">
<br>
<div id="success">If there is a 4chan Pass with this e-mail address associated with it, an e-mail containing renewal instructions will be sent. Please be sure to check your Spam folder, and if you do not receive an e-mail, contact <a href="mailto:4chanpass@4chan.org?subject=4chan%20Pass%20Renewal%20-%20No%20E-mail%20Found">4chanpass@4chan.org</a> for assistance.
</div>
</body>
</html>
<?php

flush_output_buffers();
mail($email, $subject, $message, $headers, $opts);

}
else if (isset($_GET['reset'])) {
require_once 'lib/captcha.php';
/**
 *
 *
 * Reset token/pin prompt
 *
 *
 */
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="pragma" content="no-cache">
  <title>4chan Pass - Reset</title>
  <link rel="stylesheet" style="text/css" href="//s.4cdn.org/css/yotsubanew.361.css">
  <style type="text/css">
  #recaptcha_area .recaptchatable {background-color: transparent !important; border: none !important;}
  #recaptcha_table .recaptcha_image_cell {
    background-color: transparent !important;
    padding: 0 !important;
  }
  #recaptcha_div {height: 107px; width: 442px;}
  #recaptcha_challenge_field {width: 400px}
  
  .recaptcha_input_area {
    padding: 0!important;
  }
  #recaptcha_table tr:first-child {
    height: auto!important;
  }

  #recaptcha_table tr:first-child > td:not(:first-child) {
    padding: 0 7px 0 7px!important;
  }

  #recaptcha_table tr:last-child td:last-child {
    padding-bottom: 0!important;
  }

  #recaptcha_table tr:last-child td:first-child {
    padding-left: 0!important;
  }
  #recaptcha_response_field {
    width: 292px !important;
    margin-right: 0px !important;
    font-size: 10pt !important;
  }
  input:-moz-placeholder { color: gray !important; }
  #recaptcha_image {
    border: 1px solid #aaa !important;
  }
  #recaptcha_table tr > td:last-child {
    display: none !important;
  }
  #captchaContainer {
    height: 81px;
  }
  .postForm {
    width: 436px;
  }
  </style>
  <script type="text/javascript">
  function onCaptchaReady() {
  var el;
  
  if (el = document.getElementById('recaptcha_image')) {
    el = document.getElementById('recaptcha_response_field');
    el.setAttribute('placeholder', 'Type the text (Required)');
    el.setAttribute('spellcheck', 'false');
    el.setAttribute('autocorrect', 'off');
    el.setAttribute('autocapitalize', 'off');
    }
  }
  </script>
</head>
<body>
<div class="boardBanner">
  <div class="boardTitle">4chan Pass Reset</div>
</div>
<hr style="width: 90%">
<br>
<div style="text-align: center;"></div><form action="https://www.<?php echo (IS_4CHANNEL ? '4channel.org' : '4chan.org') ?>/pass" method="post">
  <input type="hidden" name="reset" value="1" />
  <table class="postForm">
    <tbody>
      <tr>
        <td style="text-align: center">E-mail</td>
        <td><input type="text" name="email" placeholder="E-mail associated with your 4chan Pass" style="width: 292px; text-align: center;" /></td>
      </tr>
      
      <tr>
        <td style="text-align: center;width: 80px">Verification</td>
        <td>
          <div id="captcha-form"><?php echo captcha_form(true, true) ?></div>
        </td>
      </tr>
      
      <tr>
        <td colspan="2" style="padding: 5px 0; border: none; background: none; text-align: center; font-weight: normal; padding-bottom: 20px;">
          <input type="submit" value="Submit" style="margin: 0px;" />
        </td>
      </tr>

            <tr>
                <td colspan="2" style="padding: 5px 0; border: none; background: none; text-align: center; font-weight: normal;">
                    Note: You must enter the e-mail address associated<br>with your 4chan Pass in order to reset its PIN.
                    <br><br>Don't have a 4chan Pass? <a href="https://www.<?php echo (IS_4CHANNEL ? '4channel.org' : '4chan.org') ?>/pass">Click here</a> to learn more.
                </td>
            </tr>

    </tbody>
  </table>
</form><div></div>
</body>
</html>
<?php
}
else if (isset($_POST['reset'])) {
/**
 *
 *
 * Reset token/pin processing
 *
 *
 */
require_once 'lib/captcha.php';

// Error messages
define('S_NOCAPTCHA', 'Error: You forgot to solve the CAPTCHA. Please try again.');
define('S_BADCAPTCHA', 'Error: You seem to have mistyped the CAPTCHA. Please try again.');
define('S_CAPTCHATIMEOUT', 'Error: This CAPTCHA is no longer valid because it has expired. Please try again.');
define('S_FLOOD', 'Error: You must wait longer before submitting another reset request.');
define('S_NOEMAIL', 'Error: The E-mail field cannot be empty.');
define('S_NOACCOUNT', 'If there is a 4chan Pass with this e-mail address associated with it, an e-mail containing reset instructions will be sent. Please be sure to check your Spam folder, and if you do not receive an e-mail, contact <a href="mailto:4chanpass@4chan.org?subject=4chan%20Pass%20Reset%20-%20No%20E-mail%20Found">4chanpass@4chan.org</a> for assistance.');
define('S_DBERROR', 'Error: Database error.');
define('S_MULTIPASS', 'If there is a 4chan Pass with this e-mail address associated with it, an e-mail containing reset instructions will be sent. Please be sure to check your Spam folder, and if you do not receive an e-mail, contact <a href="mailto:4chanpass@4chan.org?subject=4chan%20Pass%20Reset%20-%20No%20E-mail%20Found">4chanpass@4chan.org</a> for assistance.');
// ---

function check_flood() {
  $ip = ip2long($_SERVER['REMOTE_ADDR']);
  
  $res = mysql_global_call("SELECT ip FROM user_actions WHERE ip = $ip AND action='pass_reset' AND time >= SUBDATE(NOW(), INTERVAL 5 MINUTE)");
  
  if (mysql_num_rows($res) > 0) {
    return false;
  }
  
  $res = mysql_global_call("INSERT INTO user_actions (ip,board,action,time) values ($ip,'','pass_reset',NOW())");
  
  return true;
}

if (!isset($_POST['email']) || $_POST['email'] === '') {
  error(S_NOEMAIL);
}

mysql_global_connect();

$email = mysql_real_escape_string($_POST['email']);

$now = $_SERVER['REQUEST_TIME'];

// Captcha 
start_recaptcha_verify();

$ret = rpc_finish_request($recaptcha_ch, $error, $httperror);

// BAD
// 413 Request Too Large is bad; it was caused intentionally by the user.
if ($httperror == 413) {
  error(S_BADCAPTCHA);
}

// BAD
// Network issue.
if ($ret == null) {
  error(S_BADCAPTCHA);
}

$resp = json_decode($ret, true);

// BAD
// Malformed JSON response from Google
if (json_last_error() !== JSON_ERROR_NONE) {
  error(S_BADCAPTCHA);
}

// BAD
if (!$resp['success']) {
  error(S_BADCAPTCHA);
}

// Flood check
if (!check_flood()) {
  error(S_FLOOD);
}

// Check multi accounts
$query = "SELECT * FROM pass_users WHERE (email = '$email' OR gift_email = '$email') AND status = 0";

$res = mysql_global_call($query);

if (!$res) {
  error(S_DBERROR);
}
  
if (mysql_num_rows($res) < 1) {
  error(S_NOACCOUNT, true);
}
else if (mysql_num_rows($res) > 1) {
  error(S_MULTIPASS);
}

// Try email first
$query = "SELECT * FROM pass_users WHERE email = '$email' AND gift_email = '' AND status = 0";

$res = mysql_global_call($query);

if (!$res) {
  error(S_DBERROR);
}
  
// Now try gift_email
if (mysql_num_rows($res) < 1) {
  $query = "SELECT * FROM pass_users WHERE gift_email = '$email' AND status = 0";
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    error(S_DBERROR);
  }
  
  if (mysql_num_rows($res) < 1) {
    error(S_NOACCOUNT, true);
  }
  
  $email_col = 'gift_email';
}
else {
  $email_col = 'email';
}

$pass = mysql_fetch_assoc($res);

if (!$pass) {
  error(S_DBERROR);
}

$last_reset = (int)$pass['reset_timestamp'];

if ($last_reset && $last_reset > ($now - 5 * 60)) {
  error(S_FLOOD);
}

// Token
$token = md5(mt_rand(1, 1000000) . $_SERVER['REMOTE_ADDR'] . microtime());

$query = "UPDATE pass_users SET reset_token = '$token', reset_timestamp = $now WHERE $email_col = '$email' AND (status = 0 OR status = 6) LIMIT 1";

$res = mysql_global_call($query);

/**
 * Email
 */

// Subject
$subject = "Your 4chan Pass PIN Reset Request";

// Message
$message =<<<MSG
You're receiving this e-mail because you or someone on your behalf requested your 4chan Pass PIN be reset.

If you did not make this request, please ignore and delete this message.

You may reset your 4chan Pass PIN by visting the following URL: 

https://www.4channel.org/pass?confirm_reset=$token

Need help? Please contact 4chanpass@4chan.org for assistance.
MSG;

// From:
$headers = "From: 4chan Pass <4chanpass@4chan.org>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Envelope
$opts = '-f 4chanpass@4chan.org';

set_time_limit(0);

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="pragma" content="no-cache">
  <title>4chan Pass - Reset</title>
  <link rel="stylesheet" style="text/css" href="//s.4cdn.org/css/yotsubanew.361.css">
  <style type="text/css">
  #success {
    font-size: x-large;
    text-align: center;
  }
  </style>
</head>
<body>
<div class="boardBanner">
  <div class="boardTitle">4chan Pass Reset</div>
</div>
<hr style="width: 90%">
<br>
<div id="success">If there is a 4chan Pass with this e-mail address associated with it, an e-mail containing reset instructions will be sent. Please be sure to check your Spam folder, and if you do not receive an e-mail, contact <a href="mailto:4chanpass@4chan.org?subject=4chan%20Pass%20Reset%20-%20No%20E-mail%20Found">4chanpass@4chan.org</a> for assistance.
</div>
</body>
</html>
<?php

flush_output_buffers();
mail($email, $subject, $message, $headers, $opts);

}
else if (isset($_GET['confirm_reset']) && preg_match('/^[a-f0-9]{32}$/', $_GET['confirm_reset'])) {
/**
 *
 *
 * Pass reset confirmation
 *
 *
*/

define('S_BADTOKEN', 'Error: Wrong token.<br /><br />However, it\'s possible this transaction was successful. Please check your e-mail account for a purchase or renewal receipt, and if no receipt, contact <a href="mailto:4chanpass@4chan.org?subject=4chan%20Pass%20Renewal%20-%20Wrong%20Token">4chanpass@4chan.org</a> for assistance.');

mysql_global_connect();

$token = mysql_real_escape_string($_GET['confirm_reset']);

$query = "SELECT * FROM pass_users WHERE reset_token = '$token' AND status = 0";

$res = mysql_global_call($query);

if (!$res || mysql_num_rows($res) !== 1) {
  error(S_BADTOKEN);
}

$pass = mysql_fetch_assoc($res);

if ($pass['gift_email'] !== '') {
  $email = $pass['gift_email'];
}
else {
  $email = $pass['email'];
}

$plainpin = rand(100000, 999999);
$pin = crypt($plainpin, substr($pass['user_hash'], 4, 9));

$query = "UPDATE pass_users SET pin = '$pin', reset_token = '', session_id = NULL WHERE reset_token = '$token' LIMIT 1";

$res = mysql_global_call($query);

/**
 * Email
 */
// Subject
$subject = "Your 4chan Pass Token/PIN";

// Message
$user_hash = $pass['user_hash'];

$message =<<<MSG
You're receiving this e-mail because you recently requested your 4chan Pass PIN be reset.

Below are your new 4chan Pass login details:

4chan Pass Details
==================
Your Pass Token: $user_hash (Your Token is unchanged.)
Your Pass PIN: $plainpin

Please update your records and use these new details to authenticate at https://sys.4channel.org/auth

Need help? Please contact 4chanpass@4chan.org for assistance.
MSG;

// From:
$headers = "From: 4chan Pass <4chanpass@4chan.org>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Envelope
$opts = '-f 4chanpass@4chan.org';

set_time_limit(0);

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="pragma" content="no-cache">
  <title>4chan Pass - Reset</title>
  <link rel="stylesheet" style="text/css" href="//s.4cdn.org/css/yotsubanew.361.css">
  <style type="text/css">
  #success {
    font-size: x-large;
    text-align: center;
  }
  </style>
</head>
<body>
<div class="boardBanner">
  <div class="boardTitle">4chan Pass Reset</div>
</div>
<hr style="width: 90%">
<br>
<div id="success"><strong>Your 4chan Pass PIN has been successfully reset!</strong><br><br>Please check your inbox for your updated 4chan Pass login details.<br><br>
Didn't receive an e-mail? Be sure to check your spam folder, and if no luck, contact <a href="mailto:4chanpass@4chan.org">4chanpass@4chan.org</a> for assistance.</div>
</body>
</html>
<?php

flush_output_buffers();
mail($email, $subject, $message, $headers, $opts);

}
else {
/**
 *
 *
 * Pass purchases and renewals
 *
 *
 */
require_once 'lib/ini.php';
load_ini_file('payments_config.ini');

$COINBASE_PRICE_HASH = COINBASE_PRICE_HASH;
$STRIPE_API_KEY_PUBLIC = STRIPE_API_KEY_PUBLIC;
$PASS_PRICE_AMOUNT_IN_DOLLARS = PASS_PRICE_AMOUNT_IN_DOLLARS;
$PASS_PRICE_AMOUNT_IN_DOLLARS_NO_CENTS = PASS_PRICE_AMOUNT_IN_DOLLARS_NO_CENTS;

//Uncomment to disable Pass purchases
//die('<html><head><title>4chan Pass - 4chan</title></head><body><p>4chan Pass purchasing is temporarily disabled. Please check back later.<br><br>Already have a Pass and need to log in? Go here: <a href="https://sys.4chan.org/auth" target="_blank">https://sys.4chan.org/auth</a></p></body></html>');

$hash = md5( substr( str_shuffle( 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890-' ), 0, 20 ) );
setcookie( 'temp_id', $hash, time() + 24 * ( 60 * 60 ), '/', (IS_4CHANNEL ? '4channel.org' : '4chan.org'), true );

define( 'TEMP_ID', $hash );

function maskEmail($email) {
  $bits = explode('@', $email);
  return substr($bits[0], 0, 3) . '*****@' . $bits[1];
}

function maskString($str) {
  return substr($str, 0, 3) . '*******';
}

function title()
{
  if (isset($_GET['renew'])) {
      echo "4chan Pass Renewal - 4chan";
  }
  else {
      echo "4chan Pass - 4chan";
  }
}

function stylesheet()
{
    ?>//s.4cdn.org/css/pass.10.css<?
}

$top_box_count = 1;
function top_box_title_0()
{
  if (isset($_GET['renew'])) {
    echo 'Support 4chan&mdash;renew a 4chan Pass';
  }
  else {
    echo 'Support 4chan&mdash;buy a 4chan Pass';
  }
}

function top_box_content_0()
{
  if (!isset($_GET['renew'])): ?>
<p>A 4chan Pass ("Pass") allows users to bypass typing a CAPTCHA verification when posting and reporting posts on the
  4chan image and discussion boards. The idea for Passes came directly from the community
  and were introduced as a way for users to show their support and receive a convenient feature in return. Passes
  cost <?=PASS_PRICE_CURRENCY_SYMBOL?><?=PASS_PRICE_AMOUNT_IN_DOLLARS_NO_CENTS?> <!--<span style="text-decoration: line-through; color: grey;">$20</span> $15 <em>(Discounted&mdash;25%
    Off)</em>--> per year,
  which is about $1.67<!--<span style="text-decoration: line-through; color: grey;">$1.67</span> $1.25-->
  per month&mdash;or less than a single 20oz bottle of soda.</p>
<p>See below for more information and payment instructions, or <a href="https://sys.<?php echo (IS_4CHANNEL ? '4channel.org' : '4chan.org') ?>/auth" target="_blank">click
  here if you've already purchased a 4chan Pass.</a></p>
<p>Forgot your PIN? <a href="https://www.<?php echo (IS_4CHANNEL ? '4channel.org' : '4chan.org') ?>/pass?reset">Reset it here.</a></p>

<p>If your Pass has expired or is expiring in less than 6 months, you can <a href="https://www.<?php echo (IS_4CHANNEL ? '4channel.org' : '4chan.org') ?>/pass?req_renew">renew it here.</a></p>

<p>If you have trouble purchasing a 4chan Pass, please e-mail <a href="mailto:4chanpass@4chan.org">4chanpass@4chan.org</a></p>

<hr style="border: 0; color: #800; background: #800; height: 1px; margin-top: 1.0em; margin-bottom: 1.0em;" />

<!--<p><span style="text-decoration: underline;">Note for international customers</span>: We accept most major international
  cards (this includes bank, credit, charge, debit, and prepaid cards).</p>
<p><span style="text-decoration: underline;">Note for customers failing ZIP/Postal Code check</span>: If you receive an
  error
  stating payment was declined due to a bad ZIP/Postal Code, please call your card's issuer and verify what they
  have on file.</p>
<p><strong>If you have any trouble purchasing a Pass, or have questions before purchasing, please e-mail us at: 
  <a href="mailto:4chanpass@4chan.org?subject=4chan%20Pass%20-%20Purchase%20Support">4chanpass@4chan.org</a></strong></p>-->
<?
  else: ?>
<p>This page allows you to renew your 4chan Pass ("Pass"). If you would like to purchase a new Pass (and thus receive a new Token and PIN) instead of renewing your existing 
  Pass, <a href="https://www.<?php echo (IS_4CHANNEL ? '4channel.org' : '4chan.org') ?>/pass">please make your purchase here</a>.</p>

<p>Thanks again for your support!</p>

<hr style="border: 0; color: #800; background: #800; height: 1px; margin-top: 1.0em; margin-bottom: 1.0em;" />

<!--<p><span style="text-decoration: underline;">Note for international customers</span>: We accept most major international
  cards (this includes bank, credit, debit, and prepaid cards).</p>
<p><span style="text-decoration: underline;">Note for customers failing ZIP/Postal Code check</span>: If you receive an
  error
  stating payment was declined due to a bad ZIP/Postal Code, please call your card's issuing bank and verify what they
  have on file.</p>
<p><strong>If you have any trouble purchasing a Pass, or have questions before purchasing, please e-mail
  <a href="mailto:4chanpass@4chan.org?subject=4chan%20Pass%20-%20Purchase%20Support">4chanpass@4chan.org</a></strong></p>-->
<?php
  endif;
}

$left_box_count = 3;
function left_box_title_0()
{
    if (isset($_GET['renew'])) {
        echo 'Renew a 4chan Pass';
    }
    else {
        echo 'Purchase a 4chan Pass';
    }
    echo '<img src="//s.4cdn.org/image/lock.gif" alt="Secure Transaction" title="Secure Transaction" style="width: 12px; height: 14px; float: right; margin: 6px 6px 0 0;" class="retina"/>';
}

function left_box_content_0()
{
  $url_domain = IS_4CHANNEL ? '4channel.org' : '4chan.org';
  //echo '<div style="text-align:center;color:red;font-weight:bold;font-size:14px;margin-top:5px">4chan Pass purchases are currently disabled.</div>';
  //return; 
?>
<form data-cg-action="https://www.<?php echo $url_domain ?>/payments/coinbase.php?action=order" data-pp-action="https://www.<?php echo $url_domain ?>/payments/paypal.php?action=order" action="https://www.<?php echo $url_domain ?>/payments/" method="post" id="payment-form">

<div id="email_collection_form">
<?php
  if (isset($_GET['renew'])) {
    mysql_global_connect();
    
    $pending_id = mysql_real_escape_string($_GET['renew']);
    
    $query = "SELECT user_hash, email, gift_email FROM pass_users WHERE pending_id = '$pending_id' AND expiration_date <= DATE_ADD(NOW(), INTERVAL 6 MONTH) AND status IN(0,1,6) AND pin != '' LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      echo 'Database Error';
      echo '</div>';
      return;
    }
    
    if (mysql_num_rows($res) < 1) {
      echo 'You cannot renew this Pass yet. Renewals are only permitted within 6 months of Pass expiration.';
      echo '</div>';
      return;
    }
    
    $pass = mysql_fetch_assoc($res);
    
    if ($pass['gift_email'] !== '') {
      $owner_email = $pass['gift_email'];
    }
    else {
      $owner_email = $pass['email'];
    }
?>
  <script type="text/javascript">
    window.ownerEmail = '<?php echo htmlspecialchars($owner_email, ENT_QUOTES) ?>';
  </script>
  <div class="form-row email-row">
    <div class="left-column">
      <h3 id="email-label">Your E-mail</h3>
      <div><?php echo maskEmail($owner_email) ?></div>
    </div>
    
    <div class="right-column">
      <h3 id="token-label">Your 4chan Pass Token</h3>
      <div><?php echo maskString($pass['user_hash']) ?></div>
    </div>
  </div>
  
  <div style="text-align: center;">
    <input type="checkbox" autocomplete="off" name="giftpass" id="giftpass" value="1" onclick="giftToggle(this);" />&nbsp;

    <label for="giftpass">
      I am renewing the above Pass as a gift for someone else.
    </label>
  </div>
  
  <div class="form-row email-row" id="gift_email" style="display: none;">
    <div class="left-column">
      <h3>Your E-mail</h3>
      <input type="text" size="17" autocomplete="off" name="giftemail" id="giftemail"
           title="E-mail of the gift sender." value="" />
    </div>

    <div class="right-column">
      <h3>Verify Your E-mail</h3>
      <input type="text" size="17" autocomplete="off" id="giftemailverify" name="giftemailverify"
           title="Verify gift sender's e-mail address." value="" />
    </div>
  </div>
<?php
}
else { ?>
  <div class="form-row email-row">
    <div class="left-column">
      <h3>Your E-mail</h3>
      <input type="text" size="17" autocomplete="off" name="email" id="email"
           title="E-mail address must be valid." />
    </div>

    <div class="right-column">
      <h3>Verify E-mail</h3>
      <input type="text" size="17" autocomplete="off" name="emailverify" id="emailverify"
           title="Verify your e-mail address." />
    </div>
  </div>

  <div style="text-align: center;">
    <input type="checkbox" autocomplete="off" name="giftpass" id="giftpass" value="1" onclick="giftToggle(this);" />&nbsp;

    <label for="giftpass">
      Purchase this Pass as a gift for someone else?
    </label>
  </div>

  <div class="form-row email-row" id="gift_email" style="display: none;">
    <div class="left-column">
      <h3>Gift Recipient E-mail</h3>
      <input type="text" size="17" autocomplete="off" name="giftemail" id="giftemail"
           title="E-mail for the gift recipient." value="" />
    </div>

    <div class="right-column">
      <h3>Verify Gift Recipient E-mail</h3>
      <input type="text" size="17" autocomplete="off" id="giftemailverify" name="giftemailverify"
           title="Verify gift recipient's e-mail address." value="" />
    </div>
  </div>
<?php 
}
?>
  <hr/>

  <div class="form-row" style="text-align: center;" id="priceinfo">
    <h3>Cost</h3>
    <h2 style="padding-top: 5px;"><?=PASS_PRICE_CURRENCY_SYMBOL?><?=PASS_PRICE_AMOUNT_IN_DOLLARS?> <?=PASS_PRICE_CURRENCY?></h2>

    <!--<h3 style="text-decoration: line-through; color: grey; padding-top: 5px;">$20.00 USD</h3>

    <h2 style="color: red;">$15.00 USD</h2>

    <h3 style="color: red;">(25% Off)</h3>-->
  </div>

  <hr/>

  <div class="form-row" style="text-align: center;">
    <input type="checkbox" autocomplete="off" name="acceptterms" id="acceptterms" value="accept"
         onclick="buttonClick(this);" />&nbsp;
    <label for="acceptterms">
      I have read and agree to the <br/><a href="#termsofsale">Terms of Sale</a> and <a href="#termsofuse">Terms
      of Use</a>.
    </label>
    <br><br>
    <?php if (false && IS_4CHANNEL): ?>
    <button type="submit" class="submit-button stripe-button-el" disabled="disabled" id="pbpp-button"><span>Pay with PayPal</span></button>
    <br><br>
    <?php endif ?>
    <input type="hidden" name="temp_id" value="<?php echo TEMP_ID ?>">
    <button type="button" class="submit-button-cg stripe-button-el" disabled="disabled" id="pbac-button"><span>Pay with Digital Currency</span></button>
    <div style="margin-top: 10px">We currently accept<br>Bitcoin, Bitcoin Cash, Ethereum and Litecoin.</div>
    <script type="text/javascript">
      function appendToken(res) {
        var el, form;
        
        form = document.getElementById('payment-form');
        
        if (el = document.getElementById('stripeToken')) {
          el.parentNode.removeChild(el);
        }
        
        if (el = document.getElementById('stripeTokenType')) {
          el.parentNode.removeChild(el);
        }
        
        el = document.createElement('input');
        el.type = 'hidden';
        el.name = 'stripeToken';
        el.id = 'stripeToken';
        el.value = res.id;
        
        form.appendChild(el);
        
        el = document.createElement('input');
        el.type = 'hidden';
        el.name = 'stripeTokenType';
        el.id = 'stripeTokenType';
        el.value = res.type;
        
        form.appendChild(el);
        
        form.submit();
      }
      
      function onPayClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (!checkEmail()) {
          return;
        }
        
        if (this.disabled) {
          return;
        }
        
        var email;
        
        if (window.isRenewal) {
          if (document.getElementById('giftpass').checked) {
            email = document.getElementById('giftemail').value;
          }
          else {
            email = window.ownerEmail;
          }
        }
        else {
          email = document.getElementById('email').value;
        }
        
        StripeCheckout.open({
          key: '<?php echo(STRIPE_API_KEY_PUBLIC) ?>',
          amount: '<?php echo(STRIPE_PRICE_AMOUNT_IN_CENTS) ?>',
          currency: 'usd',
          name: '4chan',
          description: '1x 4chan Pass ($<?php echo(STRIPE_EMAIL_AMOUNT_IN_DOLLARS) ?>)',
          image: 'https://s.4cdn.org/image/apple-touch-icon-iphone-retina.png',
          panelLabel: 'Pay {{amount}}',
          allowRememberMe: false,
          zipCode: true,
          email: email,
          token: appendToken
        });
      }
      
      function onCoinbaseClick(e) {
        var el;
        
        e.preventDefault();
        e.stopPropagation();
        
        
        if (!checkEmail()) {
          return;
        }
        
        if (this.disabled) {
          return;
        }
        
        el = document.getElementById('payment-form');
        
        if (!el.hasAttribute('data-stripe-action')) {
          el.setAttribute('data-stripe-action', el.action);
        }
        
        el.action = el.getAttribute('data-cg-action');
        el.submit();
      }
      
      function onPayPalClick(e) {
        var el;
        
        e.preventDefault();
        e.stopPropagation();
        
        
        if (!checkEmail()) {
          return;
        }
        
        if (this.disabled) {
          return;
        }
        
        el = document.getElementById('payment-form');
        
        if (!el.hasAttribute('data-stripe-action')) {
          el.setAttribute('data-stripe-action', el.action);
        }
        
        el.action = el.getAttribute('data-pp-action');
        el.submit();
      }
      
      //document.getElementById('pbc-button').addEventListener('click', onPayClick, false);
      document.getElementById('pbac-button').addEventListener('click', onCoinbaseClick, false);
      
      var el = document.getElementById('pbpp-button');
      el && el.addEventListener('click', onPayPalClick, false);
    </script>
    
    <br /><br />
    
    <div style="text-align: center; font-size: smaller;">Note: You must have JavaScript and browser cookies
      enabled in order to complete this purchase. Please provide a valid e-mail address, as you may have trouble receiving your Pass credentials if you fail to do so.
    </div>
    <div class="email-errors"></div>
  </div>

  <?php if (isset($_GET['renew'])): ?>
  <input type="hidden" name="renew" value="<?php echo htmlspecialchars($_GET['renew'], ENT_QUOTES) ?>" />
  <?php endif ?>
</div>
</form>
<?
}

function left_box_title_1()
{
  ?><a name="termsofsale">Terms of Sale</a><?
}

function left_box_content_1()
{
  ?>
<ol>
  <li>Your Pass will be valid for 12 months from the date of purchase.</li>
  <li>Use of your Pass is bound by the 4chan Pass <a href="#termsofuse">Terms of Use</a>.</li>
    <li>You are encouraged to read and understand the 4chan Pass <a href="#faq">FAQ</a> before making your purchase.</li>
  <li>This payment will be listed on your billing statement as "4CHAN.ORG".</li>
  <li>All sales are final. No refunds will be issued.</li>
</ol>
<?
}

function left_box_title_2()
{
  ?><a name="termsofuse">Terms of Use</a><?
}

function left_box_content_2()
{
  ?>
<ol>
  <li>You understand that the service being offered <em>only</em> allows you to bypass entering a CAPTCHA verification
    on the 4chan image and discussion boards while using an authorized device.
  </li>
  <li>A Pass may be used to authorize multiple devices, but can only be associated with one IP address at a time. This IP may only be changed once every 30 minutes.</li>
  <li>Passes are for individual use by the purchaser only.</li>
  <li>Passes may not be shared, transferred, or sold. Passes that are found to violate this term will be revoked.</li>
  <li>Posting spam messages, advertising of any kind, or other content that violates United States law to 4chan will
    result in immediate revocation of the Pass with no refund given.
  </li>
  <li>You must have browser cookies enabled to use your Pass. JavaScript is optional, but recommended.</li>
  <li>Passes are valid for 12 months from date of purchase.</li>
  <li>You agree to comply with the <a href="/rules" target="_blank">Rules</a> of 4chan, and understand that failure to do so may result
    in the temporary or permanent suspension, in 4chan's sole discretion, of your posting privileges. Suspended
    ("banned") users shall not be eligible for a refund and will not have lost time credited to their Pass account.
  </li>
  <li>Passes and all related services offered by 4chan are provided "as is" and without any warranty of any kind.
    4chan makes no guarantee that Passes or the use thereof will be available at any particular time or that the
    results of using the Pass will meet your requirements.
  </li>
  <li>In no event will 4chan or any of its employees or affiliates be liable for any damages relating to the Passes in
    excess of the prorated portion&mdash;in relation to the date you bought the Pass&mdash;of the amount (in USD)
    you spent to buy the Pass.
  </li>
  <li>These terms are subject to change without notice.</li>
</ol>
<?
}

$right_box_count = 1;
function right_box_title_0()
{
  ?><a name="faq">Frequently Asked Questions</a><?
}

function right_box_content_0()
{
  ?>

<dl>
  <dt class="first" id="alloweduses">What exactly does a Pass allow me to do?</dt>
  <dd>
    <p>A 4chan Pass allows you to bypass typing a CAPTCHA verification when posting and reporting posts on the 4chan image and discussion boards. 4chan Pass users have reduced post cooldown timers. 4chan Passes also bypass IP range and country blocks.</p>
  </dd>
  <dt id="disalloweduses">What doesn't a Pass allow me to do?</dt>
  <dd>
    <p>A Pass does not confer any additional privileges beyond bypassing the CAPTCHA verification. You will still be
      subject to various post timers and must comply with the <a href="/rules" target="_blank">Rules</a> of 4chan.</p>
  </dd>
  <dt id="knowuse">Will other people know I'm using a Pass?</dt>
  <dd>
    <p>No. Unless you enter <i>since4pass</i> in the Options field, there will be no indication that you are using a Pass to other users, and your posts will display exactly the same as any other. The <i>since4pass</i> Options command allows you to publicly display since when you have been using a 4chan Pass.</p>
  </dd>
  <dt id="howuse">How will I receive and use my Pass?</dt>
  <dd>
    <p>Upon successful payment, you will receive a unique 10-character Token and 6-digit PIN. You may then authorize
      your devices by visiting <a href="https://sys.<?php echo (IS_4CHANNEL ? '4channel.org' : '4chan.org') ?>/auth" target="_blank">sys.<?php echo (IS_4CHANNEL ? '4channel.org' : '4chan.org') ?>/auth</a>. A receipt containing
      this information will also be e-mailed to the address you provide.</p>
  </dd>
  <dt id="validfor">How long is my Pass valid for?</dt>
  <dd>
    <p>Your Pass is valid for 12 months from the date of purchase.</p>
  </dd>
  <dt id="passlength">Can I purchase a Pass for more or less than 12 months?</dt>
  <dd>
    <p>No, we only offer annual Passes at a cost of $<?=PASS_PRICE_AMOUNT_IN_DOLLARS_NO_CENTS?> per year. You will be notified when your Pass is due for renewal.</p>
  </dd>
    <dt id="passrenewal">How do I renew my Pass?</dt>
    <dd>
        <p>You will be e-mailed with a renewal link when your Pass is due to expire in one week, and will receive a follow-up e-mail once it expires. When you renew your Pass with this link, its expiration will be extended by one year. You cannot renew Passes that are not due to expire in less than one week.</p>
    </dd>
  <dt id="multiple">Can I use my Pass on multiple devices?</dt>
  <dd>
    <p>Passes may be used on multiple devices (computers, tablets, phones, etc), but can only be associated with one IP address at a time. For
      customers with dynamic IP addresses or who wish to use their Pass while on the move, you may update this IP
      address by re-authenticating from a new IP (currently limited to once every 30 minutes, subject to change).
      Note this is done automatically on devices that have already been authorized and are cookied.</p>
  </dd>
  <dt id="paymentsecure">Do you store payment information on your servers?</dt>
  <dd>
    <p>No. All payment information is stored securely by our <a href="//en.wikipedia.org/wiki/Payment_Card_Industry_Data_Security_Standard">PCI</a>-certified payment provider. More information is
      available <a href="https://www.paypal.com/us/webapps/mpp/paypal-safety-and-security" target="_blank">here</a>.</p>
  </dd>
  <dt id="paymentmethods">What payment methods do you accept?</dt>
  <dd>
    <p>We accept <?php if (false && IS_4CHANNEL): ?>PayPal, <?php endif ?>Bitcoin, Bitcoin Cash, Ethereum and Litecoin.<?php if (!IS_4CHANNEL): ?> Unfortunately we cannot accept Credit Cards at this time.<?php endif ?></p>
  </dd>
  <dt id="countryban">I can't post because my ISP, IP range, or country is blocked&mdash;can I use a 4chan Pass?</dt>
  <dd>
    <p>Yes. 4chan Pass users may bypass ISP, IP range, and country blocks, but are still subject to the same rules and restrictions as any other user. Pass users cannot bypass individual (regular) IP bans.<br /><br />Don't know the difference? <em>ISP, IP range, and country blocks</em> display red "Error" messages when attempting to post (these <u>can</u> be bypassed with a 4chan Pass), whereas <em>individual IP bans</em> redirect you to www.<?php echo (IS_4CHANNEL ? '4channel.org' : '4chan.org') ?>/banned (these <u>cannot</u> be bypassed with a 4chan Pass).</p>
  </dd>
  <dt id="spam">What if spammers abuse this?</dt>
  <dd>
    <p>Passes used for spam or advertising of any kind will be permanently suspended. Furthermore, Pass users are still
      subject to post timers and a Pass may only be associated with one IP address at a time.</p>
  </dd>
  <dt id="banned">What happens if I am banned?</dt>
  <dd class="last">
    <p>Your Pass will be permanently suspended for posting spam messages, advertising of any kind, or posting content that violates United States law. Regular bans and auto-bans will not revoke your Pass, however you may not use your Pass while banned. Only senior moderators may revoke Passes for the aforementioned violations, and must do so manually. No refund is provided for suspended Passes, nor will time be credited in the event you are not able to use your Pass due to a ban or for any other reason.</p>
  </dd>
</dl>
<?
}

$temp_id = TEMP_ID;

if (isset($_GET['renew'])) {
  $isRenewal = 'var isRenewal = true;';
  $renewToken = htmlspecialchars($_GET['renew'], ENT_QUOTES);
}
else {
  $isRenewal = 'var isRenewal = false;';
}

$url_domain = IS_4CHANNEL ? '4channel.org' : '4chan.org';

$custom_header = <<<HTML
<!--<script type="text/javascript" src="https://checkout.stripe.com/checkout.js"></script>-->
<script type="text/javascript" src="//s.4cdn.org/js/jquery-1.8.0.min.js"></script>
<script type="text/javascript">

var temp_id = '$hash';
var hasCookie = true;
var coinbase_interval = false;
$isRenewal

document.addEventListener('DOMContentLoaded', function() {
  $('.email-errors').text('');
  hasCookie = readCookie("temp_id") !== null;
  if( !hasCookie ) {
    // Block stuff and show error.
    $('.email-errors').text('You have cookies blocked. You must enable cookies in order to purchase a 4chan Pass.');
    $('.submit-button').attr("disabled", "disabled");
    $('.submit-button-cg').attr("disabled", "disabled");
    return;
  }

  $("#payment-form").submit(function(event) {
    if (this.action.indexOf('/payments/coinbase') !== -1) {
      var btn = document.getElementById('pbac-button');
      if (btn.disabled) {
        return false;
      }
      btn.disabled = true;
      return true;
    }
    
    // disable the submit button to prevent repeated clicks
    if ($('.submit-button').attr("disabled") == "disabled") return false;
    $('.submit-button').attr("disabled", "disabled");
    var month = $('.card-expiry-month').val();
    month = month.replace(/^[0]+/, '');
    
    createCookie( 'temp_id', '$temp_id', 1, '$url_domain' );

    Stripe.createToken({
        number: $('.card-number').val(),
        exp_month: parseInt(month),
        exp_year: parseInt("20" + "" + $('.card-expiry-year').val()),
        cvc: $('.card-cvc').val(),
        address_zip: $('.address-zip').val()
    }, stripeResponseHandler);

    // prevent the form from submitting with the default action
    return false;
  });

  $('#modalprice').html($('#priceinfo').html());

  $(document).on('coinbase_payment_complete', function(event, code){
     window.location = "https://www.$url_domain/payments?act=pending&amp;method=bc";
  });

  $('#pbb-button').click(function() {
    if( !checkEmail() ) return;

    var build;
    
    if (window.isRenewal) {
      build = '4chan Pass renewal for ' + window.ownerEmail;
      createCookie( 'pass_renew', '$renewToken', 1, '$url_domain' );
    }
    else {
      build = '4chan Pass purchase for ' + $('#email').val();
      createCookie( 'pass_email', $('#email').val(), 1, '$url_domain' );
    }
    
    if( $('#giftemail').val() != '' && $('#get').val() == '1' ) {
      build += ' (Gift from: ' + $('#giftemail').val() + ')';
    }
    
    createCookie( 'pass_giftemail', $('#giftemail').val(), $('#giftemail').val() ? 1 : -1, '$url_domain' );

    build += ' || ' + temp_id;
    
    if (window.isRenewal) {
      build += ' ($renewToken)';
    }
    
    createCookie( 'temp_id', build, 1, '$url_domain' );

    var buildn = encodeURIComponent(build);
    // Cleanup any previous modals that got generated
     
    while( $('#coinbase_modal_iframe_'+item_id).attr('id') ) $('#coinbase_modal_iframe_'+item_id).get(0).remove();
    $('#coinbase_js').remove();

    // Load the modal with our new custom param
    $('#coinbase_dynamic_load').html(dynamicu.replace('CUSTOM_REPLACE', build));


    // Wait until the modal has finished loading, then trigger it
    //setTimeout( "$(document).trigger('coinbase_show_modal', 'ee545c6d1c1a5d5aaa3d0acaf1808652');", 2000 );
    coinbase_interval = setInterval(function() {
      if( $('#coinbase_modal_iframe_'+item_id).attr('id') ) {
        $('#coinbase_modal_iframe_'+item_id).load(function(){
        console.log("Modal has finished loading.  Triggering...");
        $(document).trigger('coinbase_show_modal', item_id);
        });

        clearInterval(coinbase_interval);

      }
    }, 50);
  });

  if (!window.isRenewal) {
    $('#email').keyup(doCheckEmail);
    $('#emailverify').keyup(doCheckEmail);
  }
  
  $('#giftemail').keyup(doCheckEmail);
  $('#giftemailverify').keyup(doCheckEmail);

  $(document).click(function(elem) {
    var targ = elem.target;

    if( targ.id && (targ.id == 'modal-bg' || targ.id == 'stripe-close-button') ) {
        $('#stripe_payment_form').attr('style', 'display: none;');
        $('#modal-bg').attr('style', 'display: none;');
    }
  });
  
  for (let k of ['email', 'emailverify', 'giftemail', 'giftemailverify']) {
    let el = document.getElementById(k);
    el && el.addEventListener('paste', onDummy, false);
  }
}, false);

function onDummy(e) {
  e.preventDefault();
}

function buttonClick(evt) {
  if( !hasCookie ) return;

    if( !checkEmail() ) {
        disableButton();
        return;
    }
    !evt.checked ? disableButton() : undisableButton();
}

function giftToggle(evt) {
  evt.checked ? showGift(evt) : hideGift();
}

function showGift(evt) {
    if (window.isRenewal) {
      if (!confirm('Only check this box when renewing a Pass for ANOTHER person (not yourself). Is this renewal for someone else?')) {
        evt.checked = false;
        return;
      }
    }
    $('#email-label').text('Gift Recipient E-mail');
    $('#token-label').text('Gift Recipient 4chan Pass Token');
    $('#gift_email').css('display', 'block');
    $('#get').val('1');
}

function hideGift() {
    $('#email-label').text('Your E-mail');
    $('#token-label').text('Your 4chan Pass Token');
    $('#gift_email').css('display', 'none');
    $('#get').val('0');
    createCookie( 'pass_giftemail', '', -1, '$url_domain' );
}

undisableButton = function()
{
    $('.submit-button').removeAttr('disabled');
    $('.submit-button-cg').removeAttr('disabled');
};

disableButton = function()
{
    $('.submit-button').attr("disabled", "disabled");
    $('.submit-button-cg').attr("disabled", "disabled");
};

function stripeResponseHandler(status, response)
{
    //console.log(status, response);

    if( response.error ) {
        $('.payment-errors').text(response.error.message + '.');
        undisableButton();
    } else {
        var form$ = $('#payment-form');
        var token = response['id'];
        if (document.getElementById('id_stripeToken')) return;
        if (token == '') return;
        form$.append('<input type="hidden" name="stripeToken" id="id_stripeToken" value="' + token + '" />');
        $('.payment-errors').text('Processing payment. Please wait...').css('color', 'red');
        
        var el = form$.get(0);
        
        if (el.hasAttribute('data-stripe-action') && el.getAttribute('data-stripe-action') !== el.action) {
          el.action = el.getAttribute('data-stripe-action');
        }
        
        el.submit();
    }
}

function createCookie(name, value, days, domain) {
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        var expires = "; expires=" + date.toGMTString();
    } else expires = "";
    if (domain) domain = "; domain=" + domain;
    else domain = "";
    document.cookie = name + "=" + value + expires + "; path=/" + domain;
}

function readCookie(name) {
  var nameEQ = name + "=";
  var ca = document.cookie.split(';');
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) == ' ') c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
  }
  return null;
}

timeoutHold = null;
function doCheckEmail()
{
  if( !hasCookie ) return;

    if( timeoutHold != null ) clearTimeout(timeoutHold);
    timeoutHold = setTimeout(checkEmail, 1000);
}

function validateEmail(email) {
    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
}

function checkEmail()
{
    var isOk = true, isOkG = true;
    
    if (!window.isRenewal) {
      isOk = checkRegularEmail();
      $('#he').val($('#email').val());
      $('#hev').val($('#emailverify').val());
      if( !isOk ) return;
    }
    
    if( $('#giftpass').attr('checked') ) {
        isOkG = checkGiftEmail();
        $('#hge').val($('#giftemail').val());
        $('#hgev').val($('#giftemailverify').val());

        if( !isOkG ) return;
    }

    if( $('#acceptterms').attr('checked') ) undisableButton();
    return true;
}

function checkRegularEmail()
{
    var m, email = $('#email').val();
    
    if (m = email.match(/@(sbcglobal\.net|aol\.com|mail\.com)/i)) {
      $('.email-errors').text("We're sorry, but " + m[1] + " email addresses are not permitted. Please try again using another email account.");
      disableButton();
      return false;
    }
    
    if( validateEmail(email) ) {
        $('.email-errors').text('');

        if( email != $('#emailverify').val() ) {
            $('.email-errors').text('Your e-mail addresses do not match.');
            disableButton();
            return false;
        }
        return true;
    }

    $('.email-errors').text('Your e-mail address is invalid.');
    disableButton();

    return false;
}

function checkGiftEmail()
{
    var email = $('#giftemail').val();
    
    if (/@sbcglobal\.net/.test(email)) {
      $('.email-errors').text("We're sorry, but sbcglobal.net email addresses are not permitted. Please try again using another email account.");
      disableButton();
      return false;
    }
    
    if( validateEmail(email) ) {
        $('.email-errors').text('');

        if( email != $('#giftemailverify').val() ) {
            $('.email-errors').text('Your gift e-mail addresses do not match.');
            disableButton();
            return false;
        }

        return true;
    }

    $('.email-errors').text('Your gift e-mail address is invalid.');
    disableButton();

    return false;
}
</script>
<script type="text/javascript">
function setRetinaIcons() {
  if (window.devicePixelRatio < 2) {
    return;
  }

  var i, j, nodes;

  nodes = document.getElementsByClassName('retina');

  for (i = 0; j = nodes[i]; ++i) {
    j.src = j.src.replace(/\.(gif|png)$/, "@2x.$1");
  }
}

document.addEventListener('DOMContentLoaded', setRetinaIcons, true);
</script>
<style type="text/css">
.stripe-button-el {
    overflow: hidden;
    display: inline-block;
    visibility: visible !important;
    background-image: -webkit-linear-gradient(#28a0e5,#015e94);
    background-image: -moz-linear-gradient(#28a0e5,#015e94);
    background-image: -ms-linear-gradient(#28a0e5,#015e94);
    background-image: -o-linear-gradient(#28a0e5,#015e94);
    background-image: -webkit-linear-gradient(#28a0e5,#015e94);
    background-image: -moz-linear-gradient(#28a0e5,#015e94);
    background-image: -ms-linear-gradient(#28a0e5,#015e94);
    background-image: -o-linear-gradient(#28a0e5,#015e94);
    background-image: linear-gradient(#28a0e5,#015e94);
    -webkit-font-smoothing: antialiased;
    border: 0;
    padding: 1px;
    text-decoration: none;
    -webkit-border-radius: 5px;
    -moz-border-radius: 5px;
    -ms-border-radius: 5px;
    -o-border-radius: 5px;
    border-radius: 5px;
    -webkit-box-shadow: 0 1px 0 rgba(0,0,0,0.2);
    -moz-box-shadow: 0 1px 0 rgba(0,0,0,0.2);
    -ms-box-shadow: 0 1px 0 rgba(0,0,0,0.2);
    -o-box-shadow: 0 1px 0 rgba(0,0,0,0.2);
    box-shadow: 0 1px 0 rgba(0,0,0,0.2);
    -webkit-touch-callout: none;
    -webkit-tap-highlight-color: transparent;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    -o-user-select: none;
    user-select: none;
    cursor: pointer;
}

.stripe-button-el::-moz-focus-inner {
    border: 0;
    padding: 0;
}

.stripe-button-el span {
    display: block;
    position: relative;
    padding: 0 12px;
    height: 30px;
    line-height: 30px;
    background: #1275ff;
    background-image: -webkit-linear-gradient(#7dc5ee,#008cdd 85%,#30a2e4);
    background-image: -moz-linear-gradient(#7dc5ee,#008cdd 85%,#30a2e4);
    background-image: -ms-linear-gradient(#7dc5ee,#008cdd 85%,#30a2e4);
    background-image: -o-linear-gradient(#7dc5ee,#008cdd 85%,#30a2e4);
    background-image: -webkit-linear-gradient(#7dc5ee,#008cdd 85%,#30a2e4);
    background-image: -moz-linear-gradient(#7dc5ee,#008cdd 85%,#30a2e4);
    background-image: -ms-linear-gradient(#7dc5ee,#008cdd 85%,#30a2e4);
    background-image: -o-linear-gradient(#7dc5ee,#008cdd 85%,#30a2e4);
    background-image: linear-gradient(#7dc5ee,#008cdd 85%,#30a2e4);
    font-size: 14px;
    color: #fff;
    font-weight: bold;
    font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
    text-shadow: 0 -1px 0 rgba(0,0,0,0.25);
    -webkit-box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
    -moz-box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
    -ms-box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
    -o-box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
    -webkit-border-radius: 4px;
    -moz-border-radius: 4px;
    -ms-border-radius: 4px;
    -o-border-radius: 4px;
    border-radius: 4px;
}

.stripe-button-el:not(:disabled):active,.stripe-button-el.active {
    background: #005d93;
}

.stripe-button-el:not(:disabled):active span,.stripe-button-el.active span {
    color: #eee;
    background: #008cdd;
    background-image: -webkit-linear-gradient(#008cdd,#008cdd 85%,#239adf);
    background-image: -moz-linear-gradient(#008cdd,#008cdd 85%,#239adf);
    background-image: -ms-linear-gradient(#008cdd,#008cdd 85%,#239adf);
    background-image: -o-linear-gradient(#008cdd,#008cdd 85%,#239adf);
    background-image: -webkit-linear-gradient(#008cdd,#008cdd 85%,#239adf);
    background-image: -moz-linear-gradient(#008cdd,#008cdd 85%,#239adf);
    background-image: -ms-linear-gradient(#008cdd,#008cdd 85%,#239adf);
    background-image: -o-linear-gradient(#008cdd,#008cdd 85%,#239adf);
    background-image: linear-gradient(#008cdd,#008cdd 85%,#239adf);
    -webkit-box-shadow: inset 0 1px 0 rgba(0,0,0,0.1);
    -moz-box-shadow: inset 0 1px 0 rgba(0,0,0,0.1);
    -ms-box-shadow: inset 0 1px 0 rgba(0,0,0,0.1);
    -o-box-shadow: inset 0 1px 0 rgba(0,0,0,0.1);
    box-shadow: inset 0 1px 0 rgba(0,0,0,0.1);
}

.stripe-button-el:disabled,.stripe-button-el.disabled {
    background: rgba(0,0,0,0.2);
    -webkit-box-shadow: none;
    -moz-box-shadow: none;
    -ms-box-shadow: none;
    -o-box-shadow: none;
    box-shadow: none;
}

.stripe-button-el:disabled span,.stripe-button-el.disabled span {
    color: #999;
    background: #f8f9fa;
    text-shadow: 0 1px 0 rgba(255,255,255,0.5);
}
.form-row {
    padding: 10px;
    overflow: hidden;
}

.form-row .left-column {
    float: left;
    margin-right: 35px;
}

.email-row .left-column {
margin-right: 15px;
}

.form-row .right-column {
    float: left;
}

.form-row .imgrow {
    padding-left: 5px;
    height: 24px!important;

    display: block;
    float: left;
}

.form-row .imgrow img {
    margin-top: -1px;
}

.form-row .stripelogo {
  padding-left: 5px;
  height: 26px!important;

  display: block;
  float: right;
  padding-right: 45px;
}

.form-row .stripelogo img {
  margin-top: -3px;
}

.form-row h3 {
    margin-top: -5px;
    margin-bottom: 0px;
}

ol ul li {
list-style: none!important;
padding: 5px;
}

.form-row input[type=text] {
    text-align: center;
}

.payment-errors, .email-errors {
    font-size: larger;
    font-weight: bold;
    color: red;
    text-align: center;
    margin-top: 15px;
}

#stripe-close-button:hover {
    cursor: pointer;
}

#coinbase_payment_form iframe {
height: 28px!important;
width: 150px!important;
}

#stripe_payment_form {
display: none;
top: 50%;
left: 50%;

margin-top: -184px;
margin-left: -184px;

position: fixed;
z-index: 2000;
}

#modal-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;

    background-color: #000;
    opacity: 0.8;

    display: none;

    z-index: 100;
}
</style>
HTML;

// Dies if cannot connect to MySQL
mysql_global_connect();

include 'frontpage_template.php';

}
