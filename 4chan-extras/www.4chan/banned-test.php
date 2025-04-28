<?php

//if ($_SERVER['REMOTE_ADDR'] !== '51.159.28.165') {
	die();
//}

$in_imgboard = true;
$salt = file_get_contents( '/www/keys/legacy.salt' );

define( 'IS_BANPAGE', true );
include "lib/db.php";
include 'lib/admin.php';
include 'json.php';
include 'data/boards.php';
require_once 'lib/captcha.php';
require_once 'lib/geoip2.php';
require_once 'lib/auth.php';
require_once 'lib/userpwd.php';

mysql_global_connect();

// Cache busting stuff
header( 'Cache-Control: private, no-cache, must-revalidate' );
header( 'Expires: -1' );
header( 'Vary: *' );

if (!defined('IS_4CHANNEL')) {
  define('IS_4CHANNEL', preg_match('/(^|\.)4channel.org$/', $_SERVER['HTTP_HOST']));
}

define('CAPTCHA', 1);

define('S_NOCAPTCHA', 'Error: You forgot to solve the CAPTCHA. Please try again.');
define('S_BADCAPTCHA', 'Error: You seem to have mistyped the CAPTCHA. Please try again.');
define('S_CAPTCHATIMEOUT', 'Error: This CAPTCHA is no longer valid because it has expired. Please try again.');

define('PASS_TIMEOUT', 1800);
define('S_INVALIDPASS', '4chan Pass Token or PIN is invalid. [<a href="https://sys.4chan.org/auth">Re-authorize Device</a>]');
define('S_PASSEXPIRED', 'This 4chan Pass has expired.');
define('S_PASSDISABLED', 'This 4chan Pass has been disabled.');
define('S_PASSINUSE', 'This 4chan Pass is currently in use by another IP. [<a href="https://sys.4chan.org/auth">More Info</a>]');
define('S_PASSFORMATCHANGED', 'You must re-authorize this device in order to continue using your 4chan Pass. [<a href="https://sys.4chan.org/auth">Re-authorize Device</a>]');

define('MATCHED_PWD', 2);
define('MATCHED_PASS', 4);

// ID for the suicide template to do the hotline number substitution based on country
define('SUICIDE_TPL_ID', 265);

function format_suicide_template($public_reason, $country) {
  $pattern = 'your national suicide hotline';
  
  $hotlines = [
    'US' => 'the National Suicide Prevention Hotline at 1-800-273-8255',
    'GB' => 'the Samaritans Hotline at 116 123',
    'IE' => 'the Samaritans Hotline at 116 123',
    'CA' => 'the Canada Suicide Prevention Service at 1-833-456-4566',
    'AU' => 'Lifeline Australia at 13 11 14',
    'DE' => 'Telefonseelsorge Deutschland at 0800 1110 111 or 0800 1110 222',
    'MX' => 'SAPTEL at (55) 5259-8121',
    'FR' => 'SOS Amitié at 09 72 39 40 50',
    'BR' => 'Centro de Valorização da Vida at 188',
    'PL' => 'Olsztynski Telefon Zaufania \'Anonimowy Przyjaciel\' at 52 70 000',
    'SE' => 'Nationella Hjälplinjen at 020 22 00 60',
    'IT' => 'www.telefonoamico.it at 199 284 284',
    'PT' => 'Voz de Apoio at 225 50 60 70',
    'GR' => 'your national suicide hotline at 1018',
    'JP' => 'the Tokyo Lifeline at 03 5774 0992',
    'CL' => 'Teléfono de la Esperanza at (00 56 42) 22 12 00',
    'ES' => 'the National Suicide Hotline at 914590050 or Teléfono de la Esperanza at 717 003 717',
    'HU' => 'Magyar Lelki Elsosegély at 116-123',
    'PH' => 'the Hopeline at (02) 804-HOPE (4673)',
    'DK' => 'the Livslinien at 70 201 201',
    'RS' => 'the Centar Srce at 0800-300-303',
    'IL' => 'ERAN at 1201',
    'RO' => 'the Romanian Alliance for Suicide Prevention at 0800 801 200',
    'TR' => 'the National Emergency Hotline at 182',
    'NO' => 'Kirkens SOS at 22 40 00 40',
    'HR' => 'your national helpline at 01 4833 888',
    'UA' => 'Lifeline Ukraine at 7333',
    'BA' => 'Centar SRCE at 0800 300 303',
    'KR' => 'Counsel24 at 1566-2525',
    'VE' => 'the Telefono de la Esperanza Hotline at 0241-8433308',
    'AT' => 'your national helpline at 017133374',
    'RU' => 'the Samaritans Hotline at 007 (8202) 577-577',
    'FI' => 'your national suicide hotline at 010 195 202',
    'SI' => 'the Samaritan hotline at 116 123',
    'BE' => 'Centrum ter Preventie van Zelfmoord at 02/649 95 55',
    'LV' => 'Skalbes.lv at +371 67222922 or +371 27722292',
    'PE' => 'Telefono De La Esperanza at 717 003 717 or 00 51 1 273 8026',
    'LB' => 'the Embrace Lifeline at 1564',
    'LT' => 'the Youth Psychological Aid Center at 8-800 2 8888',
    'AR' => 'Centro de Atencíon al Familiar del Suicida at (054) (011) 4 783 8888',
    'EE' => 'your local suicide prevention lifeline at +372 6558088, 126, or 127',
    'CH' => 'PARS PAS at +41 (0) 27 321 21 21',
    'CO' => 'Colombia\'s 24/7 Helpline at (57-1) 323 24 25'
  ];
  
  if (!isset($hotlines[$country])) {
    return $public_reason;
  }
  
  $str = $hotlines[$country];
  
  return str_replace($pattern, $str, $public_reason);
}

function get_report_category_title($cat_id) {
  $cat_id = (int)$cat_id;
  
  if (!$cat_id) {
    return null;
  }
  
  $query = "SELECT title FROM report_categories WHERE id = " . $cat_id;
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    return null;
  }
  
  $cat = mysql_fetch_row($res);
  
  if (!$cat) {
    return null;
  }
  
  return $cat[0];
}

function get_appeal_wait_time() {
  $range = 30; // days
  
  $query =<<<SQL
SELECT AVG(delay) FROM appeal_stats
WHERE created_on >= DATE_SUB(NOW(), INTERVAL $range DAY)
SQL;
  
  $res = mysql_global_call($query);
  
  if (!$res || mysql_num_rows($res) < 1) {
    return null;
  }
  
  $sec = (int)mysql_fetch_row($res)[0];
  
  if ($sec < 1) {
    return null;
  }
  
  return getPreciseDuration($sec);
}

/**
 * "Days/Hours/Minutes ago"
 */
function getPreciseDuration($delta) {
  if ($delta < 1) {
    return 'moments';
  }
  
  if ($delta < 60) {
    return $delta . ' seconds';
  }
  
  if ($delta < 3600) {
    $count = floor($delta / 60);
    
    if ($count > 1) {
      return $count . ' minutes';
    }
    else {
      return 'one minute';
    }
  }
  
  if ($delta < 86400) {
    $count = floor($delta / 3600);
    
    if ($count > 1) {
      $head = $count . ' hours';
    }
    else {
      $head = 'one hour';
    }
    
    $tail = floor($delta / 60 - $count * 60);
    
    if ($tail > 1) {
      $head .= ' and ' . $tail . ' minutes';
    }
    
    return $head;
  }
  
  $count = floor($delta / 86400);
  
  if ($count > 1) {
    $head = $count . ' days';
  }
  else {
    $head = 'one day';
  }
  
  $tail = floor($delta / 3600 - $count * 24);
  
  if ($tail > 1) {
    $head .= ' and ' . $tail . ' hours';
  }
  
  return $head;
}

function pluralize( $count, $singular )
{
  if ($count == 0) {
    return 'less than a day';
  }
	if( $count == 1 )
		return sprintf( $singular, $count );
	if( substr( $singular, -1 ) == "s" )
		return sprintf( $singular . "es", $count );

	return sprintf( $singular . "s", $count );
}

function title()
{
	global $warned, $not_banned, $needs_verification;
	
	if ($needs_verification) {
	  echo '4chan - Verification';
	  return;
	}
	
	echo "4chan";
	if ($not_banned) {
	  echo " - Not Banned";
	  return;
	}
	echo $warned ? " - Warned" : " - Banned";
}

function stylesheet()
{
	?>//s.4cdn.org/css/banned.14.css<?
}

$top_box_count = 1;
function top_box_title_0()
{
	global $warned, $not_banned, $needs_verification;
	
	if ($needs_verification) {
	  echo 'Verification';
	  return;
	}
	
	echo "You ";
	
	if ($not_banned) {
	  echo 'are not banned';
	  return;
	}
	
	echo $warned ? "were issued a <span class=\"banType\">warning</span>" : "are <span class=\"banType\">banned</span>";
	echo "! ;_;";
}

function top_box_content_0() {
  global $top_box_content;
  
	echo $top_box_content;
}

/**
 * Error function, doesn't die
 */
function soft_error($msg) {
  echo '<div id="error">' . $msg . '</div>';
  return false;
}

/**
 * Error function, dies
 */
function error($msg) {
  echo '<div id="error">' . $msg . '</div>';
  die();
}

function generate_captcha_form() {
  global $recaptcha_public_key;
?>
<form action="https://www.<?php echo IS_4CHANNEL ? '4channel' : '4chan' ?>.org/banned" method="post">
  <table id="captcha-table">
    <tr>
      <td>
        <div id="captcha-form">
          <?php echo captcha_form(true, true) ?>
          <button id="verify-btn" type="submit">Verify</button>
        </div>
      </td>
      <td id="captcha-desc">
        You must solve the CAPTCHA in order to view your IP's ban status.
      </td>
    </tr>
  </table>
</form>
<?php }

function can_bypass_captcha() {
  auth_user();
  
  return valid_captcha_bypass_banned() || valid_captcha_bypass();
}

function valid_captcha_bypass_banned() {
  $longip = ip2long($_SERVER['REMOTE_ADDR']);
  
  if (!$longip) {
    return false;
  }
  
  $query = "SELECT ip FROM user_actions WHERE ip = $longip AND action = 'is_banned' AND time >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1";
  
  $result = mysql_global_call($query);
  
  if (mysql_num_rows($result) > 0) {
    return true;
  }
  else {
    return false;
  }
}

/**
 * Captcha verification
 */
function verify_recaptcha() {
  global $recaptcha_private_key;
  global $captcha_bypass;
  
  if (can_bypass_captcha()) {
    return true;
  }
  
  $response = $_POST["g-recaptcha-response"];
  
  if (!$response) {
    if ($return_error == false) {
      error(S_NOCAPTCHA);
    }
    return S_NOCAPTCHA;
  }
  
  $response = urlencode($response);
  
  $rlen = strlen($response);
  
  if ($rlen > 4096) {
    return recaptcha_bad_captcha($return_error);
  }
  
  $api_url = "https://www.google.com/recaptcha/api/siteverify?secret=$recaptcha_private_key&response=$response";
  
  $recaptcha_ch = rpc_start_request($api_url, null, null, false);
  
  if (!$recaptcha_ch) {
  	return soft_error(S_BADCAPTCHA); // not really
  }
  
  $ret = rpc_finish_request($recaptcha_ch, $error, $httperror);
  
  // BAD
  // 413 Request Too Large is bad; it was caused intentionally by the user.
  if ($httperror == 413) {
    return soft_error(S_BADCAPTCHA);
  }
  
  // BAD
  if ($ret == null) {
    return soft_error(S_BADCAPTCHA);
  }
  
  $resp = json_decode($ret, true);
  
  // BAD
  // Malformed JSON response from Google
  if (json_last_error() !== JSON_ERROR_NONE) {
    return soft_error(S_BADCAPTCHA);
  }
  
  // GOOD
  if ($resp['success']) {
    return true;
  }
  
  // BAD
  return soft_error(S_BADCAPTCHA);
}

// mostly because $warned needs to be set before calling title()
// called at the bottom
function generate_content()
{
  global $boards_flat;
	global $warned;
	global $not_banned;
	global $needs_verification;
  
	ob_start();
	
  $needs_verification = true;
  
	if (!isset($_POST['task'])) {
	  if (isset($_POST['g-recaptcha-response'])) {
      if (!verify_recaptcha()) {
        return;
      }
	  }
	  else if (!can_bypass_captcha()) {
	    generate_captcha_form();
	    return;
	  }
	}
	
	$needs_verification = false;
	
// The number of days you need to wait until you can appeal a permaban.
	$INCUBATION_DAYS = 3;

// For auto-bans
	$INCUBATION_DAYS_AUTO = 1;

// The maximum ban length for a (non-perma) ban to be non-appealable.
	$SHORT_BAN_DAYS = 3;

	$ALLOW_PERMABAN_REAPPEAL_AFTER = 30;
	$ALLOW_OTHER_REAPPEAL_AFTER    = 14;
	
	// Template id for the "False report" bans.
	$FALSE_REPORT_TPL = 190;

	function reformat_reason( $reason )
	{
		$reasons = explode( "<>", $reason, 2 );
		if( count( $reasons ) == 2 && trim( $reasons[0] ) ) { // new-style ban
			list( $pubreason, $pvtreason ) = $reasons;

			return $pubreason;
		} else { // old (no public reason) ban
			return "No reason available.";
		}
	}

	function format_name( $name )
	{
	  $name = str_replace('&#039;', "'", $name);
		list( $first, $second ) = explode( '#', $name, 2 );
		$first = trim( $first );
		if( $second )
			return "$first</span> <span class=\"postertrip\">!$second";

		return "$first";
	}

	function too_many_urls( $plea )
	{
		$dehtml = html_entity_decode( $plea );

		return preg_match( "/(<a href)|(\[url)/", $dehtml ) == 1;
	}
	
	function auto_link($proto, $resno) {
	}
	
	function format_links($proto)
	{
		if( strpos( $proto, '&gt;&gt;' ) !== false ) {
			$proto = preg_replace(
				'#(&gt;&gt;[0-9]+|&gt;&gt;&gt;/[a-z0-9]+/[a-z0-9+/-]*)#',
				'<span class="deadlink">$1</span>',
				$proto
			);
		}
		
		return $proto;
	}
	
	$pass_clause = array();
	
  if (isset($_COOKIE['pass_id']) && $_COOKIE['pass_id'] !== '') {
    $pass_id = explode('.', $_COOKIE['pass_id']);
    
    if ($pass_id[0]) {
      $pass_id = $pass_id[0];
      $pass_clause[] = "banned_users.4pass_id = '" . mysql_real_escape_string($pass_id) . "'";
    }
    else {
      $pass_id = null;
    }
  }
  else {
    $pass_id = null;
  }
  
  if (isset($_COOKIE['4chan_pass']) && $_COOKIE['4chan_pass']) {
    $pwd = UserPwd::decodePwd($_COOKIE['4chan_pass']);
    $pass_clause[] = "banned_users.password = '" . mysql_real_escape_string($pwd) . "'";
  }
  else {
    $pwd = null;
  }
  
  if ($pass_clause) {
    $pass_clause = ' OR ' . implode(' OR ', $pass_clause);
  }
  else {
    $pass_clause = '';
  }
  
	$ip = $_SERVER["REMOTE_ADDR"];
  
	if( $_GET['ip'] && $_SERVER['PHP_SELF'] != '/php-banned/banned.php' ) $ip = $_GET['ip'];
  
	$query = mysql_global_do( "SELECT banned_users.no,banned_users.zonly,banned_users.host,banned_users.4pass_id,banned_users.password,banned_users.admin,banned_users.template_id,banned_users.post_json,banned_users.rule,UNIX_TIMESTAMP(appeals.updated) as appealtime,appeals.appealcount,name,board,global,reason,email,plea,closed,unix_timestamp(date(now)) as start,unix_timestamp(length) as end FROM banned_users LEFT OUTER JOIN appeals ON banned_users.no=appeals.no WHERE active = 1 AND (host = '%s' $pass_clause)", $ip );
  
	$bans    = array();
	$appeals = array();
	
	$warned = false;
	$not_banned = false;
	
	while( $row = mysql_fetch_array( $query ) ) {
		$no                     = intval( $row['no'] );
		
		// ban by pass id
    if ($ip != $row['host']) {
      // don't show ip
      $bans[$no]['hide_ip'] = true;
    }
	  
		$bans[$no]['startdate'] = date( 'F jS, Y', $row['start'] ); //e.g. Smarch 31st, 2006
		$bans[$no]['host'] = $row['host'];
		
		// Permaban
		if( $row['end'] == 0 ) {
			$bans[$no]['enddate']    = '0';
			if ($row['admin'] === 'Auto-ban') {
			  $bans[$no]['incubation'] = $INCUBATION_DAYS_AUTO - intval( ( time() - $row['start'] ) / ( 24 * 60 * 60 ) ); // incubation days remaining
			}
			else {
			  $bans[$no]['incubation'] = $INCUBATION_DAYS - intval( ( time() - $row['start'] ) / ( 24 * 60 * 60 ) ); // incubation days remaining
			}
			$bans[$no]['length']     = '999999';
		}
		// Not permaban
		else {
			$banlen = intval( ( $row['end'] - $row['start'] ) / ( 24 * 60 * 60 ) );
			$inc_len = $banlen >= 14 ? $INCUBATION_DAYS - intval( ( time() - $row['start'] ) / ( 24 * 60 * 60 ) ) : 0;
			if ($row['admin'] === 'Auto-ban') {
			  $bans[$no]['incubation'] = $INCUBATION_DAYS_AUTO - intval( ( time() - $row['start'] ) / ( 24 * 60 * 60 ) );
			}
			else {
			  $bans[$no]['incubation'] = $inc_len;
		  }
			$bans[$no]['enddate']    = date( 'F jS, Y \a\t H:i \E\T', $row['end'] );
			$bans[$no]['length']     = $banlen;
			
			if( !$banlen ) $warned = true;
		}
		
		$bans[$no]['endtimestamp']  = (int)$row['end'];
		$bans[$no]['daysremaining'] = ceil( ( $row['end'] - time() ) / ( 24 * 60 * 60 ) );
		$bans[$no]['name']          = format_name( $row['name'] );
		$bans[$no]['boards']        = ( $row['global'] ) ? "<b class=\"board\">all boards</b>" : "<b class=\"board\">/{$row['board']}/</b>";
		$bans[$no]['intboard']      = $row['global'] ? 'global' : $row['board'];
		$bans[$no]['board'] = $row['board'];
		$bans[$no]['zonly'] = $row['zonly'];
		$bans[$no]['matched_pwd'] = $pwd && $pwd === $row['password'];
		$bans[$no]['matched_pass'] = $pass_id && $pass_id === $row['4pass_id'];
    
		if( $row['rule'] && $row['rule'] != 'global99' && $row['rule'] != 'global98' && $row['rule'] != 'global97' ) {
		  $show_json = true;
		  
		  if ($row['template_id']) {
        $tpl_q = "SELECT no FROM ban_templates WHERE save_post = 'everything' AND no = " . (int)$row['template_id'];
        $tpl_res = mysql_global_call($tpl_q);
        $show_json = $tpl_res && mysql_num_rows($tpl_res) === 1;
        
        if ($row['template_id'] == $FALSE_REPORT_TPL) {
          $bans[$no]['false_report'] = true;
          $show_json = true;
        }
		  }
		  
			if( $show_json && $row['post_json'] != '') {
				$json = json_decode( $row['post_json'], true );
				$json['board'] = $row['board'];
        
				$json = generate_post_json( $json, ( $row['resto'] ? $row['resto'] : 0 ), array(), true );
				$json['com'] = format_links($json['com']);
				//unset( $json['board'] );
				unset( $json['resto'] );
				unset( $json['time'] );
				
				if ($bans[$no]['false_report'] === true && $json['report_cat']) {
					$bans[$no]['report_cat_title'] = get_report_category_title($json['report_cat']);
				}
				
				$bans[$no]['postno'] = $json['no'];

				$json['ws_board'] = (int)(!$boards_flat[$row['board']]['nws']);

				$bans[$no]['json'] = $row['post_json'] ? json_encode( $json ): '';
			}


			if( strpos( $row['rule'], '3' ) === 0 ) {
				$rule = substr( $row['rule'], 1 );
			} else {
				preg_match( '#([0-9]+)$#', $row['rule'], $matches );
				$rule = $matches[1];
			}

			$rulelang          = ( strpos( $row['rule'], 'global' ) === 0 ) ? "Global Rule $rule" : "Rule $rule";
			//Unlink rules for now since we already tell the rule in the ban reason, and it looks kinda ugly with the banned post links...
			//$bans[$no]['rule'] = '<a href="//www.4chan.org/rules#' . $row["rule"] . '" target="_blank" class="rule">' . $rulelang . '</a>';
			$bans[$no]['rule'] = '<b>' . $rulelang . '</b>';
		} else {
			$bans[$no]['rule'] = '';
		}

		$bans[$no]['reason']   = reformat_reason( $row['reason'] );
    
    // Substitue suicide hotlines
    if ($row['post_json'] && $row['template_id'] && $row['template_id'] == SUICIDE_TPL_ID) {
      $_json = json_decode($row['post_json'], true);
      $bans[$no]['reason'] = format_suicide_template($bans[$no]['reason'], $_json['country']);
    }
    
		$bans[$no]['md5alert'] = strpos( $row['reason'], 'Blacklisted md5' ) !== false;

		$appeals[$no]['email']       = $row['email'];
		$appeals[$no]['plea']        = $row['plea'];
		$appeals[$no]['closed']      = $row['closed'];
		$appeals[$no]['canpleanext'] = 0;

		if( $row['closed'] == 1 && $row['appealcount'] < 4 ) {
			//$ALLOW_OTHER_REAPPEAL_AFTER;
			//$ALLOW_PERMABAN_REAPPEAL_AFTER;
			$appeals[$no]['can_appeal_later'] = true;
			$daysremaining                    = ceil( ( $row['end'] - time() ) / ( 24 * 60 * 60 ) );
			$have_cleared                     = 0;
			if( $bans[$no]['length'] == '999999' ) {
				$len = $ALLOW_PERMABAN_REAPPEAL_AFTER * 86400;
			} else {
				$len = $ALLOW_OTHER_REAPPEAL_AFTER * 86400;
				// Will our ban end before we could appeal again (overridden if we can appeal now)
				if( $daysremaining < $ALLOW_OTHER_REAPPEAL_AFTER ) {
					$appeals[$no]['can_appeal_later'] = false;
				}
			}

			// Can we appeal again yet?
			if( $len + $row['appealtime'] < time() ) {
				$appeals[$no]['email']    = '';
				$appeals[$no]['plea']     = '';
				$appeals[$no]['closed']   = 0;
				$appeals[$no]['reappeal'] = 1;

				// We're appealing now, not later. Block the message from showing
				$appeals[$no]['can_appeal_later'] = false;
			}

			if( $appeals[$no]['can_appeal_later'] ) {
				// How long until we can appeal?
				$appeals[$no]['can_appeal_in'] = ceil( ( ( $row['appealtime'] + $len ) - time() ) / ( 24 * 60 * 60 ) );

			}
		}

		$appeals[$no]['appealcount'] = $row['appealcount'];

		if( $appeals[$no]['plea'] ) {
		  $wait_time = get_appeal_wait_time();
		  
		  if ($wait_time) {
		    $wait_time = "Currently, the average wait time is $wait_time. ";
		  }
		  else {
		    $wait_time = '';
		  }
		  
			$bans[$no]['postmessage'] = "Your appeal (ID: $no) has been submitted and will be reviewed by a moderator. {$wait_time}You can use the form below to update your appeal if necessary.<br /><br />";
			$bans[$no]['submit']      = 'Update';
		} else {
			$bans[$no]['postmessage'] = '';
			$bans[$no]['submit']      = 'Submit';
		}
	}

	$thisban = $bans[intval( $_POST['no'] )];
  
	if( $thisban && $_POST['task'] == 'appeal' && $thisban['appealcount'] >= 4 ) {
		echo 'You cannot appeal this ban again.';

		return;
	}

// if there's actually a ban in the DB and we're submitting an appeal...
	if( $thisban && $_POST['task'] == 'appeal' && $_POST['plea'] && $thisban['incubation'] < 1 && $thisban['length'] > $SHORT_BAN_DAYS ) {
    if (isset($appeals[$_POST['no']]) && $appeals[$_POST['no']]['closed'] == 1) {
      // bad request
      return;
    }
    
		if( strlen( $_POST['plea'] ) > 5000 ) {
			echo( "Your message was too long. Please type less than 5,000 characters. [<a href=\"javascript:history.go(-1)\">Back</a>]" );

			return;
		}
		if (isset($_POST['email']) && $_POST['email'] != '') {
			if (!preg_match('/[^\s]+@[^\s]+\.[^\s]+/', $_POST['email'])) {
				echo("Invalid e-mail address. [<a href=\"javascript:history.go(-1)\">Back</a>]");
				return;
			}
			if (stripos($_POST['email'], '@4chan.org') !== false) {
				$_POST['email'] = '';
			}
		}
		if( too_many_urls( $_POST['plea'] ) ) {
			echo( "You seem to be a spambot." );

			return;
		}
    
    /**
     * The 'closed' column also contains additional info such as whether the password or the 4chan pass were matched in the ban
     * Only closed = 1 means the appeal was denied
     */
    $_closed = 0;
    
    if ($thisban['matched_pwd']) {
      $_closed = $_closed | MATCHED_PWD;
    }
    
    if ($thisban['matched_pass']) {
      $_closed = $_closed | MATCHED_PASS;
    }
    
		if( $appeals[$_POST['no']]['plea'] || isset( $appeals[$_POST['no']]['reappeal'] ) ) { //updating
			mysql_global_do( "UPDATE appeals set email = '%s', plea = '%s', closed = $_closed where no = %d",
				htmlspecialchars($_POST['email']),
				htmlspecialchars($_POST['plea']),
				$_POST['no'] );
		} else { //inserting
			mysql_global_do( "INSERT INTO appeals (no,email,plea,closed,appealcount) VALUES (%d,'%s','%s', $_closed, %d)",
				$_POST['no'],
				htmlspecialchars($_POST['email']),
				htmlspecialchars($_POST['plea']),
				0 );
		}
		$script_name = basename($_SERVER['SCRIPT_NAME']);
		echo( "Appeal submitted. Click <a href=\"{$_SERVER['SCRIPT_NAME']}\">here</a> if you are not automatically redirected back...<meta http-equiv=\"refresh\" content=\"1;URL={$script_name}\">" );

		return;
	}

	/*function rebuild_bans( $boards )
	{
		$boards = implode( " ", array_keys( $boards ) );
		
		$cmd    = "nohup /usr/local/bin/suid_run_global bin/rebuildbans $boards >/dev/null 2>&1 &";
		exec( $cmd );
	}*/

	function unban( $id )
	{

		global $salt, $configdir;

    require_once 'lib/ini.php';
    load_ini( "$configdir/cloudflare_config.ini" );
    finalize_constants();
    
    define('CLOUDFLARE_EMAIL', 'cloudflare@4chan.org');
    define('CLOUDFLARE_ZONE', '4chan.org');
    define('CLOUDFLARE_ZONE_2', '4cdn.org');

		$ids        = explode( ",", $id );
		$needupdate = array();
//	if(count($ids) == 0) die("You must select something to unban!</body></html>");
		foreach( $ids as $id ) {
			$result = mysql_global_do( "select active,board,global,zonly,host,post_json,post_num from banned_users where no=%d", $id );
			list( $active, $board, $globalban, $zonly, $host, $json, $no ) = mysql_fetch_row( $result );
//  if($active==0) echo "Ban $id is inactive?";

			$json = json_decode($json, true);
			//$no = $json['no'];
			$hash = sha1( $board . $no . $salt );
			@unlink( "/www/4chan.org/web/images/bans/thumb/$board/{$hash}s.jpg" );
			cloudflare_purge_url( "http://i.4cdn.org/bans/thumb/$board/{$hash}s.jpg", true );

			if( preg_match( '/([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})/', $host ) ) {
				$displayhost = gethostbyaddr( $host );
			} else {
				$displayhost = $host;
			}
			if( $host == "" ) {
				mysql_global_do( "UPDATE banned_users SET now=now, length=length, active='0' WHERE no=%d", $id );
//  echo "<META http-equiv=\"refresh\" content=\"0;URL=".$_SERVER['HTTP_REFERER']."\">";
			} else {
				mysql_global_do( "UPDATE banned_users SET now=now, length=length, unbannedon=now(), unbannedby='expiration', active='0' WHERE no=%d", $id );
//  echo $displayhost."<br />has been ";
				if( $globalban == 1 ) {
					$needupdate["global"] = 1;
//		echo "unbanned from the entirety of 4chan<br />";
				} elseif( $zonly == 1 ) {
					$needupdate["z"] = 1;
//		echo "unconfined from /z/.<br />";
				} else {
					$needupdate[$board] = 1;
//		echo "unbanned from /".$board."/.<br />";
				}
			}

		}
		//foreach
		
		$boards = implode( " ", array_keys( $needupdate ) );
		rebuild_bans($boards);

//echo "Rebuilding " . implode(", ", array_keys($needupdate)) . "...<br />";
		//rebuild_bans( $needupdate );
//echo "<br /><br /><i>Redirecting back...</i>\n<META http-equiv=\"refresh\" content=\"5;URL=".$_SERVER['HTTP_REFERER']."\">";
	}


// if they're not really banned, and there's no referer,
// send them to 4chan.org -- this is mostly to stop rapididx from fucking with us
/*
	$is_sys = strpos( $_SERVER['HTTP_REFERER'], "sys.4chan.org" ) !== false;
*/
	if( /*!$is_sys && !$is_dis && */count( $bans ) == 0 ) {
	  echo 'You are not currently banned from posting on 4chan.';
	  $not_banned = true;
	  return;
		//header( 'Location: http://www.4chan.org/' );
	}


	?>
<img src="https://sys.4chan.org/image/error/banned/250/rid.php" width="250" alt="Banned"
	 style="float: right; padding-left: 10px; min-height: 150px;"/>
<? if( count( $bans ) == 0 ): ?>
There was no entry in our database for your ban.
	<? if( $_SERVER['ban'] ): //a user-agent ban... ?>It is likely that your browser issued a bad request.<br /><br />
		<? else: //probably a country ban or directly accessing banned.php  ?>It is possible that your ISP is caching a
		ban intended for someone else. Please try clearing your browser cache and try again.
		<? endif; ?>
	<br /><br />If you are refreshing this page, please try visiting <a href="//boards.4chan.org/v/" title="/v/ - Video Games">a
	board</a> instead.
	<meta http-equiv="refresh" content="15;URL=http://www.4chan.org/">
<br /><br /><br />
<? return; endif; ?>

<?
	$firstban = 1; // certain messages get printed or omitted on the first ban...
	$i = 0;
// Loop through bans
	foreach( $bans as $no => $ban ):
	if( isset( $ban['json'] ) ):
		echo '<script type="text/javascript">var banjson_' . $i . ' = ' . $ban['json'] . '</script>';
		$i++;
	endif;
		?>
	<? if( !$firstban ) print "<br /><br /><hr /><br />"; ?>
	<? /* Temporary ban */
		if( $ban['enddate'] != "0" && $ban['endtimestamp'] > $_SERVER['REQUEST_TIME'] ): ?>

		<?php
			$rule = $ban['rule'];
      
      $rep_cat_str = '';
      
			//You have been banned from /q/ for posting /q/123, a violation of Rule 1:
			$also    = $firstban ? '' : ' also';

			if( isset( $ban['json'] ) ) {
				$link = $ban['intboard'] == 'global' ? '&gt;&gt;&gt;/' . $ban['board'] . '/' : '&gt;&gt;';
				$link .= $ban['postno'];

				$link = '<a href="#" class="bannedPost_' . ($i-1) . '">' . $link . '</a>';
				
        if ($ban['false_report']) {
          $banlang = "from {$ban['boards']} for reporting $link, a violation of $rule";
          
          if ($ban['report_cat_title']) {
            $rep_cat_str = "<br><br>The report type you used was: <b>{$ban['report_cat_title']}</b>";
          }
        }
        else {
          $banlang = "from {$ban['boards']} for posting $link, a violation of $rule";
        }
				
			} else {
				$banlang = ( $rule ) ? "from {$ban['boards']} for breaking $rule" : "from {$ban['boards']} for the following reason";

			}


			echo "You have$also been banned $banlang:";

			?><br /><br />
		<b class="reason"><?php echo $ban['reason']; ?></b><?php echo $rep_cat_str; ?><br /><br />
		Your ban was filed on <b class="startDate"><?=$ban['startdate']?></b> and expires on <b
				class="endDate"><?=$ban['enddate']?></b>, which is <?php echo(getPreciseDuration($ban['endtimestamp'] - $_SERVER['REQUEST_TIME'])) ?> from now.
		<br /><br />

		<? /* Temporary ban that just expired */ elseif( $ban['enddate'] != "0" && $ban['length'] > 0 ): ?>
		<?php
			$rule = $ban['rule'];
			
			$rep_cat_str = '';
			
			$also    = $firstban ? '' : ' also';
			if( isset( $ban['json'] ) ) {
				$link = $ban['intboard'] == 'global' ? '&gt;&gt;&gt;/' . $ban['board'] . '/' : '&gt;&gt;';
				$link .= $ban['postno'];

				$link = '<a href="#" class="bannedPost_' . ($i-1) . '">' . $link . '</a>';
				
        if ($ban['false_report']) {
          $banlang = "from {$ban['boards']} for reporting $link, a violation of $rule";
          
          if ($ban['report_cat_title']) {
            $rep_cat_str = "<br><br>The report type you used was: <b>{$ban['report_cat_title']}</b>";
          }
        }
        else {
          $banlang = "from {$ban['boards']} for posting $link, a violation of $rule";
        }
			} else {
				$banlang = ( $rule ) ? "from {$ban['boards']} for breaking $rule" : "from {$ban['boards']} for the following reason";

			}
			
			echo "You were$also banned $banlang:";

			?><br /><br />
		<b class="reason"><?php echo $ban['reason']; ?></b><?php echo $rep_cat_str; ?><br /><br />
		Your ban was filed on <b class="startDate"><?=$ban['startdate']?></b> and expired on <b
				class="endDate"><?=$ban['enddate']?></b>. The name you were posting with was <span
				class="nameBlock"><span class="name"><?=$ban['name']?></span></span>.<br /><br />
		Now that you have seen this message, this ban is now no longer active.<br /><br />
		Click <a href="javascript:history.back()">here</a> to return, or wait 30 seconds...
		<meta http-equiv="refresh" content="30;URL=javascript:history.back()"><br /><br />
		<?
			unban( $no );
			continue; // do next ban
			?>

		<? /* Warning */ elseif( $ban['enddate'] != "0" && $ban['length'] < 1 ):
      $rep_cat_str = '';
      
			if (isset($ban['json'])) {
				$link = '&gt;&gt;&gt;/' . $ban['board'] . '/' . $ban['postno'];
				$link = 'for ' . ($ban['false_report'] ? 'reporting' : 'posting') . ' <a href="#" class="bannedPost_' . ($i-1) . '">' . $link . '</a>.';
        
        if ($ban['false_report']) {
          if ($ban['report_cat_title']) {
            $rep_cat_str = "<br><br>The report type you used was: <b>{$ban['report_cat_title']}</b>";
          }
        }
			}
      else {
				$link = 'on ' . $ban['boards'] . ' with the following message:';
			}
      
		?>

		You were <? if( !$firstban ) print 'also '; ?>issued a warning <?= $link ?>
		<br /><br />
		<b class="reason"><?php echo $ban['reason']; ?></b><?php echo $rep_cat_str; ?><br /><br />
		Your warning was issued on <b class="startDate"><?=$ban['startdate']?></b>. The name you were posting with was
		<span class="nameBlock"><span class="name"><?=$ban['name']?></span></span>.
		In addition to heeding this warning, please review the <a href="//www.4chan.org/rules">rules</a> and <a
				href="//www.4chan.org/faq">FAQ</a>.<br /><br />
		Now that you have seen this message, you should be able to post again. Click <a
				href="javascript:history.back()">here</a> to return.<br /><br />
		<?
			unban( $no );
			continue; // do next ban
			?>

		<? /* Permanent ban */ else: ?>

		<?php
			$rule = $ban['rule'];
      
      $rep_cat_str = '';
      
			$also    = $firstban ? '' : ' also';
			if( isset( $ban['json'] ) ) {
				$link = $ban['intboard'] == 'global' ? '&gt;&gt;&gt;/' . $ban['board'] . '/' : '&gt;&gt;';
				$link .= $ban['postno'];

				$link = '<a href="#" class="bannedPost_' . ($i-1) . '">' . $link . '</a>';
        if ($ban['false_report']) {
          $banlang = "from {$ban['boards']} for reporting $link, a violation of $rule";
          
          if ($ban['report_cat_title']) {
            $rep_cat_str = "<br><br>The report type you used was: <b>{$ban['report_cat_title']}</b>";
          }
        }
        else {
          $banlang = "from {$ban['boards']} for posting $link, a violation of $rule";
        }
			} else {
				$banlang = ( $rule ) ? "from {$ban['boards']} for breaking $rule" : "from {$ban['boards']} for the following reason";

			}
			
			echo "You have$also been permanently banned $banlang:";
			?>
		<br /><br />
		<b class="reason"><?php echo $ban['reason']; ?></b><?php echo $rep_cat_str; ?><br /><br />Your ban was filed on <b
				class="startDate"><?=$ban['startdate']?></b>. <span class="endDate">This ban will not expire.</span>
		<br /><br />
		<?    endif; ?>
	<?php if (!$ban['hide_ip']): ?>According to our server, your IP is: <b class="bannedIp"><?=$ban['host']?></b>.<?php endif ?>
	<?php if (!$ban['false_report']): ?>The name you were posting with was <span class="nameBlock"><span class="name"><?=$ban['name']?></span></span>.<?php endif ?><br />
	<br />
    <?php if( $ban['md5alert'] && false ): ?><!--<b>Note:</b> We are currently experiencing some issues with automatic bans, which you may be affected by. Please contact <a href="mailto:appeals@4chan.org">appeals@4chan.org</a> with your IP address (
    <b class="bannedIp"><?=$ip?></b>) for more information.
	<br /><br />--><?php endif; ?>
	<?php if (!$ban['zonly']): ?>
	<? if( $appeals[$no]['closed'] == 1 ) {
		if( $appeals[$no]['can_appeal_later'] && $appeals[$no]['appealcount'] < 6 ) {
			$daysleft = $appeals[$no]['can_appeal_in'];
			$lang     = $daysleft == 1 ? '1 day' : $daysleft . ' days';

			echo '<span style="color: red; font-weight: bold;">Your appeal (ID: ' .$no . ') was reviewed and denied. You may re-appeal this ban in ' . $lang . '.</span>';
		} else {
			print( "<b><font color=\"red\">Your appeal (ID: $no) was reviewed and denied. You may not appeal this ban again.</font></b>" );
		}
	} elseif( $ban['incubation'] < 1 ) { /* Incubation period on permaban is is up or ban is temporary */
		?>
  
	<? if( $ban['length'] > $SHORT_BAN_DAYS ): // temporary ban minimum length for appeal ?>

		Because your ban is longer than <?= $SHORT_BAN_DAYS ?> days in length, you may appeal the ban using the form below.
		Please explain why you believe you should be unbanned. Rude, poorly written, or offensive appeals will be rejected.
		Submitting an appeal does not guarantee your ban will be lifted prior to its listed expiration date.
		E-mail address is optional, however if you do not provide one, we will be unable to contact you with questions.
		<br /><br />
		<em>Note: If your IP is dynamic, or your ISP uses a proxy cache, you might be affected by a ban meant for
			somebody else. If you believe this to be the case, please state so in your appeal. Tor and proxy
			users should note our policy <a href="//www.4chan.org/faq#torproxy">here</a></em>.
		<br /><br />
		<b><?=$ban['postmessage']?></b>
		<form method="post">
			<table style="border-spacing: 1px !important; border-collapse: separate !important;">
				<tr>
					<td class="postb" align="center">E-mail</td>
					<td><input type="text" name="email" size="35" value="<?=$appeals[$no]['email']?>"/><input
							type="submit" value="<?=$ban['submit']?>"/></td>
				</tr>
				<tr>
					<td class="postb" align="center">Plea</td>
					<td><textarea name="plea" cols="48" rows="4"><?=$appeals[$no]['plea']?></textarea>

					</td>
				</tr>
			</table>
			<input type="hidden" name="task" value="appeal"/>
			<input type="hidden" name="no" value="<?=$no?>"/>
		</form>
		<? else: ?>
		Because of the short length of your ban, you may not appeal it. Please check back when your ban has expired.
		<? endif; ?>
	<? } else { /* Ban is a permaban and incubation time isn't up */ ?>
	Please check back in <b class="appealDays"><?=$ban['incubation']?></b> days when you may <a
			href="//www.4chan.org/faq#banappeal">appeal</a> your ban.<br /><br />
	<? } // if(closed)... ?>
	<?php endif // if (!$ban['zonly']) ?>
	<? $firstban = 0; endforeach; ?>
<br class="clear-bug"/>
<?
  
}

$no_cache_control = 1;

$custom_header = <<<HTML
<script type="text/javascript" src="//s.4cdn.org/js/banned.14.js"></script>
<style type="text/css">#verify-btn { margin-top: 15px; }</style>
HTML;

generate_content();

$top_box_content = ob_get_contents();
ob_end_clean();

include 'frontpage_template.php';
