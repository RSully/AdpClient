<?php
require_once __DIR__ . '/AdpClient.class.php';
date_default_timezone_set('America/New_York');
$tmp = __DIR__ . '/tmp-data';


$adp = new AdpClient('USERNAME', 'PASSWORD');
$data = $adp->getActivityJournal();
// $data = $adp->sendClock('OUT');


// Formatting test

if (isset($data)) {
	file_put_contents($tmp, $data);
	file_put_contents($tmp . '-' . time(), $data);
} else {
	$data = file_get_contents($tmp);
}

var_dump($data);
