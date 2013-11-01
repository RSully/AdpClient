<?php
require_once __DIR__ . '/include.php';

/**
 * Show welcome lines
 */

printf("AdpClient CLI\n\n");

/**
 * Set flags and arguments
 */

$args = new \cli\Arguments();

$args->addFlag(array('help', 'h'), 'Show this help screen');

$args->addOption(array('configfile', 'c'), 'Path to config file');

$args->addOption(array('username', 'u'), 'Username');
$args->addOption(array('password', 'p'), 'Password');

$args->addOption(array('proxy'), 'SOCKS5 proxy');
$args->addOption(array('interface'), 'Networking interface');

$args->addFlag(array('adp-timesheet'), 'Show ADP timesheet');
$args->addFlag(array('adp-journal'), 'Show ADP journal');
$args->addFlag(array('adp-clock-in'), 'Clock-in');
$args->addFlag(array('adp-clock-out'), 'Clock-out');

$args->addOption(array('at-date-begin'), '--adp-timesheet date begin');
$args->addOption(array('at-date-end'), '--adp-timesheet date end');

/**
 * Parse arguments
 */

$args->parse();

/**
 * Load extra options from authfile
 */

if (isset($args['configfile']))
{
	$authfile = json_decode(file_get_contents($args['configfile']), true);
	foreach ($authfile as $k => $v)
	{
		if (!isset($args[$k]))
		{
			$args[$k] = $v;
		}
	}
}

/**
 * Handle arguments:
 */

$required = array(
	'username,password',
	'adp-timesheet|adp-journal|adp-clock-in|adp-clock-out'
);

if ($args['help'] || count($args->getArguments()) < 1 || ($missing = missingArgs($args, $required)))
{
	printf("Quick docs:\n\n");
	echo $args->getHelpScreen() . "\n\n";

	if (isset($missing) && count($missing) > 0)
	{
		echo "Missing:\n\n";
		echo implode("\n", $missing) . "\n\n";
	}
	exit;
}


$user = null;
$pass = null;
$options = array();

if ($args['username'] && $args['password'])
{
	$user = $args['username'];
	$pass = $args['password'];
}


if ($args['proxy'])
{
	$options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
	$options[CURLOPT_PROXY] = $args['proxy'];
}

if ($args['interface'])
{
	$options[CURLOPT_INTERFACE] = $args['interface'];
}

/**
 * Setup AdpClient
 */

$adp = new AdpClient($user, $pass, $options);

if ($args['adp-timesheet'])
{
	$timesheet_date_begin = new DateTime('Monday this week');
	$timesheet_date_end = null;

	if ($args['at-date-begin'])
	{
		$timesheet_date_begin = new DateTime($args['at-date-begin']);
		if ($args['at-date-end'])
		{
			$timesheet_date_end = new DateTime($args['at-date-end']);
		}
	}
	$adp->showTimesheet($timesheet_date_begin, $timesheet_date_end);
}

if ($args['adp-clock-in'])
{
	$adp->sendClock("IN");
}
else if ($args['adp-clock-out'])
{
	$adp->sendClock("OUT");
}

if ($args['adp-journal'])
{
	$adp->showActivityJournal();
}

/** ******************************************************* **/


/**
 * Helper functions
 */

function missingArgs($args, $required)
{
	$missing = array();

	foreach ($required as $reqStr)
	{
		$req = explode('|', $reqStr);
		$reqGood = false;

		foreach ($req as $optionsStr)
		{
			$options = explode(',', $optionsStr);
			$optionsNeed = count($options);
			$optionsHave = 0;

			foreach ($options as $option)
			{
				if ($args[$option])
				{
					$optionsHave++;
				}
			}

			if ($optionsNeed == $optionsHave)
			{
				$reqGood = true;
			}
		}

		if (!$reqGood)
		{
			$missing[] = $reqStr;
		}
	}
	return $missing;
}
