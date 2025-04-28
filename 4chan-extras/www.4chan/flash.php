<?php

$file_list = array(
  'megaloop3.swf' => 'Megaloop v3.0',
  'megaloop.swf' => 'Megaloop v1.1',
  'megaloop2.swf' => 'Megaloop v2.0',
  'ML3preview.swf' => 'Megaloop v3.0 (teaser)',
  'MegaSlotsv1.0.swf' => 'Megaslots v1.0',
  'Shii64.swf' => 'LongCat\'s Song (C64 Graphic Mix)',
  '10millionget.swf' => '10M GET Game',
  'sagequest.swf' => 'Sage Quest',
  'superwahagame.swf' => 'Super Waha Game',
  '#4chan.swf' => '#4chan',
  'complaining.swf' => 'Complaining',
  'complaining2013.swf' => 'Complaining (2013 Edition)',
  'panelflash.swf' => 'The 4chan Otakon 2005 Panel Intro',
  '4chan_otakon2006-low.swf' => 'The 4chan Otakon 2006 Panel Intro',
  'otakon07-web.swf' => 'The 4chan Otakon 2007 Panel Intro',
  'LONGCATBELOOOOOOONG.swf' => 'LONGCAT BE LOOOOOOONG',
  'w4ch_christmas.swf' => 'world4ch Christmas',
  '4chancity.swf' => '4chan City',
  'sagemangame.swf' => 'Sage Man Game',
  'sagemansauce.swf' => 'Sage Man Sauce (.swf)',
  '4chancity-craptastrophe.swf' => '4chan City: CRAPTASTROPHE',
  'SageMan.swf' => 'Sage Man',
  'emo.swf' => 'Emo',
  'longcat_song.swf' => 'LongCat\'s Song',
  '4chanmod.swf' => '4chan Mod',
  'cakeformoot.swf' => 'Cake For Moot',
  'adventure.swf' => 'Adventure Quest: The Movie',
  '4chan4evar.swf' => '4chan 4evar',
  'mootykins.swf' => 'mootykins',
  'snacks_is_back.swf' => 'SNACKS IS BACK!',
  'mootsnacks_silentnight.swf' => 'Silent Night',
  'snacks_loudenough.swf' => 'Loud Enough',
  'mootsnacksxmas.swf' => 'Merry Christmas!',
  'drama2.swf' => '4chan Drama',
  'drama.swf' => '/f/ Drama',
  'comeback4chan.swf' => 'Come Back 4chan',
  'thisis4chan.swf' => 'This is 4chan!',
  'dsfargeg.swf' => 'dsfargeg'
);

$swf_sizes = array(
  'MegaSlotsv1.0.swf' => array(600, 400),
  'Shii64.swf' => array(720, 480),
  '10millionget.swf' => array(600, 400),
  '4chan_otakon2006-low.swf' => array(640, 480),
  'otakon07-web.swf' => array(550, 420),
  'LONGCATBELOOOOOOONG.swf' => array(300, 300),
  'w4ch_christmas.swf' => array(480, 360),
  '4chancity.swf' => array(450, 350),
  'sagemansauce.swf' => array(600, 600),
  '4chancity-craptastrophe.swf' => array(550, 400),
  'longcat_song.swf' => array(600, 500),
  'cakeformoot.swf' => array(640, 360)
);

if ($_GET['file'] && isset($file_list[$_GET['file']])) {
  $swf_file = $_GET['file'];
  $swf_title = $file_list[$_GET['file']];
}
else {
  $swf_file = null;
  $swf_title = null;  
}

function title() {
  global $swf_title;
  
  if ($swf_title) {
    echo "Flash - " . htmlspecialchars($swf_title) . " - 4chan";
  }
  else {
    echo "Flash - 4chan";
  }
}

$top_box_count = 1;
function top_box_title_0() {
  global $swf_title;
  
  if ($swf_title) {
    echo "Flash - " . htmlspecialchars($swf_title) . " - 4chan";
  }
  else {
    echo "Flash";
  }
}

function top_box_content_0() {
  global $swf_file, $swf_title, $swf_sizes;
  
if ($swf_file):
	$w = 550;
	$h = 400;
  
  if (isset($swf_sizes[$swf_file])) {
    list($_w, $_h) = $swf_sizes[$swf_file];
    
    if ($_w > $w) {
      $w = $_w;
    }
    
    if ($_h > $h) {
      $h = $_h;
    }
  }
  
  $file_url = urlencode($swf_file);
  $file_html = htmlspecialchars($swf_file);
?>
<iframe style="display:block;margin:auto" allow="autoplay; fullscreen" sandbox="allow-scripts allow-same-origin" scrolling="no" frameborder="0" width="<?=$w?>" height="<?=$h?>" src="//s.4cdn.org/media/flash/embed.html?3#<?php echo "$w,$h,$file_url"; ?>"></iframe>
<div style="margin-top: 24px;margin-bottom: 16px;text-align:center"><a target="_blank" href="https://s.4cdn.org/media/flash/<?php echo $file_url ?>"><?php echo $file_html ?></a></div>
<?
else :
?>
              <h3>Anonymous D</h3>
              <p><a href="?file=megaloop3.swf">Megaloop v3.0</a><br />
              Other versions: <a href="?file=megaloop.swf">v1.1</a>, <a
              href="?file=megaloop2.swf">v2.0</a>, <a
              href="?file=ML3preview.swf">v3.0 (teaser)</a></p>
              <p><a href="?file=MegaSlotsv1.0.swf">Megaslots v1.0</a></p>
              <p><a href="?file=Shii64.swf">LongCat's Song (C64 Graphic Mix)</a></p>
              <p><a href="?file=10millionget.swf">10M GET Game</a></p>
              <hr />
              <h3>coda</h3>
              <p><a href="?file=sagequest.swf">Sage Quest</a></p>
              <p><a href="?file=superwahagame.swf">Super Waha Game</a></p>
              <p><a href="?file=%234chan.swf">#4chan</a></p>
              <p><a href="?file=complaining.swf">Complaining</a></p>
              <p><a href="?file=complaining2013.swf">Complaining (2013 Edition)</a></p>
              <p><a href="?file=panelflash.swf">The 4chan Otakon 2005 Panel Intro</a> [<a href="//www.youtube.com/watch?v=2mRp3QNkhrc" target="_blank">Panel Video</a>]</p>
              <p><a href="?file=4chan_otakon2006-low.swf">The 4chan Otakon 2006 Panel
              Intro</a> [<a href="//www.youtube.com/watch?v=DR3YsEn_jiY" target="_blank">Panel Video</a>]</p>
              <p><a href="?file=otakon07-web.swf">The 4chan Otakon 2007 Panel
              Intro</a> [<a href="//www.youtube.com/watch?v=wIuw_Ut3unA" target="_blank">Panel Video</a>]</p>
              <p><a href="//www.youtube.com/watch?v=f3DME_VYUwY">The 4chan 10th Anniversary Panel Intro</a> [<a href="//www.youtube.com/watch?v=b0QRnufms-0" target="_blank">Panel Video</a>]</p>
              <hr />
              <h3>/i/</h3>
              <p><a href="?file=LONGCATBELOOOOOOONG.swf">LONGCAT BE LOOOOOOONG</a></p>
              <hr />
              <h3>MILKRIBS4k</h3>
			  <p><a href="?file=w4ch_christmas.swf">world4ch Christmas</a></p>
			  <hr />
              <h3>NCH</h3>
              <p><a href="?file=4chancity.swf">4chan City</a></p>
              <p><a href="?file=sagemangame.swf">Sage Man Game</a></p>
              <p><a href="?file=sagemansauce.swf">Sage Man Sauce (.swf)</a> <a href="//i.4cdn.org/flash/sagemansauce.fla">(.fla)</a></p>
              <p><a href="?file=4chancity-craptastrophe.swf">4chan City:
              CRAPTASTROPHE</a></p>
              <hr />
              <h3>Brian "Okk" Raddatz</h3>
              <p><a href="?file=SageMan.swf">Sage Man</a></p>
              <p><a href="?file=emo.swf">Emo</a></p>
              <p><a href="?file=longcat_song.swf">LongCat's Song</a></p>
              <p><a href="?file=4chanmod.swf">4chan Mod</a></p>
              <p><a href="?file=cakeformoot.swf">Cake For Moot</a></p>
              <hr />
              <h3>Xenon</h3>
              <p><a href="?file=adventure.swf">Adventure Quest: The Movie</a></p>
              <hr />
              <h3>ZONE</h3>
              <p><a href="?file=4chan4evar.swf">4chan 4evar</a></p>
              <p><a href="?file=mootykins.swf">mootykins</a></p>
              <p><a href="?file=snacks_is_back.swf">SNACKS IS BACK!</a></p>
              <p><a href="?file=mootsnacks_silentnight.swf">Silent Night</a></p>
              <p><a href="?file=snacks_loudenough.swf">Loud Enough</a></p>
              <p><a href="?file=mootsnacksxmas.swf">Merry Christmas!</a></p>
              <p><a href="?file=drama2.swf">4chan Drama</a></p>
              <p><a href="?file=drama.swf">/f/ Drama</a></p>
              <p><a href="?file=comeback4chan.swf">Come Back 4chan</a></p>
              <p><a href="?file=thisis4chan.swf">This is 4chan!</a></p>
              <p><a href="?file=dsfargeg.swf">dsfargeg</a></p>

<?
endif;
}

$left_box_count = 0;

$right_box_count = 0;

include 'frontpage_template.php';
