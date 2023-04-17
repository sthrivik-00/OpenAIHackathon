<?php
$m = new SWFMovie();
$m->setDimension(400, 100);
$m->setBackground(0xff, 0xff, 0xff);
$m->add(new SWFBitmap(fopen("../img/init_vodafone.png","rb")));
for ($i = 0; $i <= 50; $i++) {
    $m->add(new SWFAction("alpha = {$i};"));
    $m->nextFrame();
}
for ($i = 0; $i <= 250; $i++) {
    $m->nextFrame();
for ($i = 0; $i <= 100; $i++) {
    $m->add(new SWFAction("alpha = {$i};"));
	for ($j=1; $j<=100; $j++)
		$m->nextFrame();
}


  header('Content-type: application/x-shockwave-flash');
  $m->output();
 