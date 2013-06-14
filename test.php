<?php
require_once __DIR__ . '/include.php';

function handleInit()
{
	global $adp;

	$authData = explode("\n", file_get_contents(__DIR__ . '/test-auth'));
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

	$adp = new AdpClient($auth[0], $auth[1], __DIR__ . '/data', $opts);
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
	} else if ($argv[1] == 'timesheet') {
		$res = $adp->showTimesheet();
	}

	return $res;
}

handleInit();
$data = handleCommand();
var_dump($data);

$adp->showActivityJournal();

// Example call
// $data = $adp->sendClock('OUT');
