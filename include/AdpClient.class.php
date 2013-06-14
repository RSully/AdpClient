<?php
class AdpClient {
	private $endpoint = '';
	private $ch = null;
	private $cachedExtra = array();

	function __construct($user, $pass, $data_dir = __DIR__, $extraOpts = array(), $endpoint = 'https://workforcenow.adp.com') {
		$this->endpoint = $endpoint;

		$this->ch = curl_init();
		curl_setopt_array($this->ch, $extraOpts);
		curl_setopt_array($this->ch, array(
			CURLOPT_URL, $this->endpoint,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_COOKIESESSION => true,
			CURLOPT_COOKIEFILE => $data_dir . '/cookies-tmp.dat',
			CURLOPT_COOKIEJAR => $data_dir . '/cookies-tmp.dat'
			// CURLOPT_VERBOSE => true
		));
		curl_setopt($this->ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36");

		// Fetch blank request to init cookies
		$this->fetchRequest();

		// Authenticate for future requests
		$this->authenticate($user, $pass);
	}

	/**
	 * Helpers for building & parsing requests
	 */

	private function getHeaders($headers)
	{
		return array_merge(array(
			'Origin: ' . $this->endpoint
		), $headers);
	}
	private function getTrimJsChars($extra = '')
	{
		// Besides the default we need `'` and `;`
		return " \t\n\r\0\x0B" . $extra;
	}

	/**
	 * Helpers for dealing with cURL
	 */

	private function setupRequest($isPost = false, $apiPoint = '')
	{
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, '');
		curl_setopt_array($this->ch, array(
			CURLOPT_POST => (bool)$isPost,
			CURLOPT_URL => $this->endpoint . $apiPoint
		));
	}
	private function setPostData($data, $isJson)
	{
		if ($isJson && is_array($data)) {
			$data = json_encode($data);
		} else {
			$data = http_build_query($data);
		}
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);

		if ($isJson) {
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders(array(
				'Content-Type: application/json', 
				'Content-Length: ' . strlen($data)
			)));
		}
	}
	private function fetchRequest()
	{
		$response = curl_exec($this->ch);
		$info = curl_getinfo($this->ch);
		return array($info, $response);
	}

	/**
	 * Authenticate
	 * 
	 * Begin the session and get cookies for subsequent requests
	 */

	private function authenticate($user, $pass)
	{
		AdpClientLog('authenticate');

		$this->setupRequest(true, '/siteminderagent/forms/login.fcc');
		$this->setPostData(array(
			'USER' => $user,
			'PASSWORD' => $pass,
			'target' => 'https://workforcenow.adp.com/portal/theme'
		), false);

		$res = $this->fetchRequest();
	}

	/**
	 * MyTime
	 * 
	 * Main page for any/all timeclock requests
	 * We need the custID and empID from this page
	 * We also used this to reverse-engineer the POSTs
	 */

	private function cacheMytime($forced = false)
	{
		AdpClientLog('cacheMytime');
		if (!$forced && count($this->cachedExtra)) {
			AdpClientLog('returning cached');
			return $this->cachedExtra;
		}


		$this->setupRequest(true, '/ezLaborManagerNet/UI4/WFN/Portlet/MyTime.aspx');
		$res = $this->fetchRequest();
		
		// We need to parse out _custID and _empID
		$find = array('custID = ', 'empID = ');
		$findRes = array();
		$resData = $res[1];
		foreach ($find as $findme) {
			$pos = strpos($resData, $findme) + strlen($findme);
			$end = strpos($resData, "\n", $pos) - $pos;
			$findRes[] = trim(substr($resData, $pos, $end), $this->getTrimJsChars("';"));
		}

		$this->cachedExtra = array(
			'res' => $res,
			'extra' => array(
				'sEmployeeID' => $findRes[1],
				'iCustID' => $findRes[0]
			)
		);

		return $this->cachedExtra;
	}

	/**
	 * Activity journal
	 * 
	 * Allows you to retreive punches for today
	 */

	public function fetchActivityJournalPage($strip = true)
	{
		AdpClientLog('fetchActivityJournalPage');
		$mytime = $this->cacheMytime();

		$this->setupRequest(true, '/ezLaborManagerNet/UI4/Common/TLMRevitServices.asmx/GetActivityJournal');
		$this->setPostData($mytime['extra'], true);

		$res = $this->fetchRequest();

		// Get data with the right element/etc
		if (!isset($res[1])) {
			return false;
		}
		$data = json_decode($res[1], true);
		if ($data === false || !isset($data['d'])) {
			return false;
		}

		return utf8_decode($data['d']);
	}
	public function getActivityJournal($getHtml = false)
	{
		$journal = $this->fetchActivityJournalPage();
		if ($journal === false || $getHtml) {
			return $journal;
		}

		// Parse the journal

		$dom = new simple_html_dom();
		$dom->load($journal);

		$entries = array();

		foreach ($dom->find('tr') as $row) {
			$action = $row->find('td', 0)->plaintext;
			$time = $row->find('td', 1)->plaintext;
			$entries[] = array($action, $time);
		}
		return $entries;
	}
	public function showActivityJournal()
	{
		$data = $this->getActivityJournal();

		$headers = array(
			'Action',
			'When'
		);
		$rows = array();

		foreach ($data as $d) {
			$d_time = new DateTime($d[1]);

			$rows[] = array(
				$d[0],
				$d_time->format('m/d/Y h:i A')
			);
		}

		$table = new \cli\Table();
		$table->setHeaders($headers);
		$table->setRows($rows);
		$table->display();

		return true;
	}

	private function fetchTimesheetPage()
	{
		AdpClientLog('fetchTimesheetPage');

		$this->setupRequest(false, '/ezLaborManagerNet/iFrameRedir.aspx?pg=122');
		$res = $this->fetchRequest();

		// Get data with the right element/etc
		if (!isset($res[1])) {
			return false;
		}
		return $res[1];
	}
	public function getTimesheet($getHtml = false)
	{
		// HTML lines with TCMS.oTD.push
		$html = $this->fetchTimesheetPage();
		if ($getHtml || $html === false) {
			return $html;
		}

		// We're going to be parsing the JS
		$objs = array();
		$addObj = function($input) use(&$objs) {
			$objs[] = js_to_php_array($input);
		};
		js::define('external', array(
			'addObj' => $addObj
		));


		foreach (explode("\n", $html) as $line) {
			$line = trim($line);
			if (has_prefix('TCMS.oTD.push', $line)) {
				$line = str_replace('TCMS.oTD.push', '', $line); // Get rid of function call
				$line = strip_tags($line); // Get rid of ending </script>

				$line = 'external.addObj' . $line . ';';
				// echo "Running JS:\n\t" . $line . "\n";
				js::run($line);
			}
		}
		
		array_walk_recursive($objs, function(&$a){
			$a = is_string($a) ? urldecode($a) : $a;
		});
		return $objs;
	}
	public function showTimesheet()
	{
		$data = $this->getTimesheet();
		print_r($data);

		$headers = array(
			'Day',
			'Date-in',
			'Time-in',
			'Time-out',
			'Hours',
			'Daily Total'
		);
		$rows = array();

		foreach ($data as $d) {
			$d_date = new DateTime($d[7]);
			$d_time_in = new DateTime($d[8][0] . ' ' . $d[8][1]);
			$d_time_out = null;
			if (!empty($d[10])) {
				$d_time_out = new DateTime($d[8][0] . ' ' . $d[10]);
			}

			$rows[] = array(
				$d_date->format('D'),
				$d_date->format('m/d/Y'),
				$d_time_in->format('h:i A'),
				$d_time_out ? $d_time_out->format('h:i A') : '',
				'TODO',
				'TODO'
			);
		}

		$table = new \cli\Table();
		$table->setHeaders($headers);
		$table->setRows($rows);
		$table->display();

		return true;
	}

	/**
	 * Clocking
	 * 
	 * Allows you to clock in or out
	 */

	public function sendClock($action)
	{
		AdpClientLog('sendClock %s', $action);
		$mytime = $this->cacheMytime();

		$this->setupRequest(true, '/ezLaborManagerNet/UI4/Common/TLMRevitServices.asmx/ProcessClockFunctionAndReturnMsg');
		$this->setPostData(array_merge($mytime['extra'], array(
			'sCulture' => 'en-US',
			'sEvent' => $action
		)), true);

		$res = $this->fetchRequest();
		$data = $res[1];

		$data_pipe = explode('|', $data);
		$success = strtolower(array_shift($data_pipe)) == 'true';

		return array($success, utf8_decode(implode('|', $data_pipe)));
	}

	/**
	 * Static helpers
	 */

	public static function getSendClockActions()
	{
		return array(
			// value => label
			'IN' => 'Clock-in',
			'OUT' => 'Clock-out'
			// 'LUNCH' => 'Lunch'
		);
	}
}
