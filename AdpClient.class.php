<?php
require_once __DIR__ . '/AdpClient.function.php';

class AdpClient {
	private $endpoint = '';
	private $ch = null;

	function __construct($user, $pass, $extraOpts = array(), $endpoint = 'https://workforcenow.adp.com') {
		$this->endpoint = $endpoint;

		$this->ch = curl_init();
		curl_setopt_array($this->ch, $extraOpts);
		curl_setopt_array($this->ch, array(
			CURLOPT_URL, $this->endpoint,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_COOKIESESSION => true,
			CURLOPT_COOKIEFILE => __DIR__ . '/cookies-tmp.dat',
			CURLOPT_COOKIEJAR => __DIR__ . '/cookies-tmp.dat'
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
	private function getTrimJsChars()
	{
		// Besides the default we need `'` and `;`
		return "\t\n\r\0\x0B';";
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

	private function cacheMytime()
	{
		AdpClientLog('cacheMytime');

		$this->setupRequest(true, '/ezLaborManagerNet/UI4/WFN/Portlet/MyTime.aspx');
		$res = $this->fetchRequest();
		
		// We need to parse out _custID and _empID
		$find = array('custID = ', 'empID = ');
		$findRes = array();
		$resData = $res[1];
		foreach ($find as $findme) {
			$pos = strpos($resData, $findme) + strlen($findme);
			$end = strpos($resData, "\n", $pos) - $pos;
			$findRes[] = trim(substr($resData, $pos, $end), $this->getTrimJsChars());
		}

		return array(
			'res' => $res,
			'extra' => array(
				'sEmployeeID' => $findRes[1],
				'iCustID' => $findRes[0]
			)
		);
	}

	/**
	 * Activity journal
	 * 
	 * Allows you to retreive punches for today
	 */

	public function getActivityJournal($strip = true)
	{
		AdpClientLog('getTimesheet');
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

		$data = utf8_decode($data['d']);
		return $data;
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
		)));

		$res = $this->fetchRequest();
		// Debugging because we haven't seen a response yet
		print_r($res);
		var_dump($res[1]);
	}
	public function getSendClockActions()
	{
		return array(
			// value => label
			'IN' => 'Clock-in',
			'OUT' => 'Clock-out'
			// 'LUNCH' => 'Lunch'
		);
	}
}
