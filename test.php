<?php
require_once __DIR__ . '/include/AdpClient.class.php';
date_default_timezone_set('America/New_York');

function handleInit()
{
	global $adp;

	$authData = explode("\n", file_get_contents(__DIR__ . '/auth'));
	$auth = explode('::', array_shift($authData));

	$opts = array();

	while (($line = array_shift($authData))) {
		$opt = explode('::', $line);
		if ($opt[0] == 'proxy') {
			$opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
			$opts[CURLOPT_PROXY] = $opt[1];
		} else {
			$opts[$opt[0]] = $opt[1];
		}
	}

	$adp = new AdpClient($auth[0], $auth[1], $opts);
}

function handleCommand()
{
	global $adp, $argv, $argc;

	if (count($argv) < 2) {
		printf("Usage: %s [command] [params]\n", $argv[0]);
		exit;
	}

	if ($argv[1] == 'clock') {
		$res = $adp->sendClock(strtoupper($argv[2]));
	} else if ($argv[1] == 'journal') {
		// shows by default
		$res = 'Timesheet below';
	}

	return $res;
}

handleInit();
$data = handleCommand();
var_dump($data);

$data = $adp->getActivityJournal();
print_r($data);


// Example call
// $data = $adp->sendClock('OUT');
