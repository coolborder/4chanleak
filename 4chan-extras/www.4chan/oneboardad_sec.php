<?php
/*
require_once 'lib/admin.php';
require_once 'lib/auth.php';

auth_user();

if (!has_flag('developer')) {
  die('404');
}
*/

die();

require_once 'lib/ini.php';
load_ini_file('payments_config.ini');

function title() {
 echo "Advertise - 4chan";
}

function stylesheet() {
?>//s.4cdn.org/css/advertise.css<?
}

$top_box_count = 1;
function top_box_title_0() {
?>Advertise<img class="retina" style="width: 12px; height: 14px; float: right; margin: 6px 6px 0 0;" title="Secure Transaction" alt="Secure Transaction" src="//s.4cdn.org/image/lock.gif"><?
}

function top_box_content_0() {
  // ---
  // Don't forget to edit payments/oneboardad.php in the yotsuba repo
  // ---
  $price_cents = 50000;
  $description = 'Campaign - October 2016';
  $stripe_public_key = STRIPE_API_KEY_PUBLIC;
?>
<h3 style="margin:10px 0">Campaign - October 2016</h3>
<form action="https://www.4chan.org/payments/oneboardad_sec.php" method="POST">
<script type="text/javascript" 
  src="https://checkout.stripe.com/checkout.js" class="stripe-button"
  data-key="<?php echo $stripe_public_key ?>"
  data-amount="<?php echo $price_cents ?>"
  data-currency="usd"
  data-name="Campaign"
  data-description="<?php echo $description ?>"
  data-image="//s.4cdn.org/image/apple-touch-icon-iphone-retina.png"
  data-allow-remember-me="false"
  data-locale="auto">
</script>
</form>
<?
}

$left_box_count = 0;

$right_box_count = 0;

include 'frontpage_template.php';

?>
