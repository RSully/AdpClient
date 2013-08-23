<?php
class AdpClient {
	private $endpoint = '';
	private $ch = null;
	private $cachedExtra = array();

	function __construct($user, $pass, $extraOpts = array(), $endpoint = 'https://workforcenow.adp.com') {
		$this->endpoint = $endpoint;

		$this->ch = curl_init();
		curl_setopt_array($this->ch, $extraOpts);
		curl_setopt_array($this->ch, array(
			CURLOPT_URL => $this->endpoint,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_AUTOREFERER => true,
			// CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_COOKIESESSION => true,
			CURLOPT_COOKIEFILE => '/dev/null',
			// CURLOPT_COOKIEJAR => '/dev/null',
			// CURLOPT_VERBOSE => true,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_CONNECTTIMEOUT => 10
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
		if ($response === false) {
			AdpClientLog('curl failure: ' . curl_error($this->ch) . ' - ' . print_r($info, true));
		}
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
		return !static::responseContainsError($res);
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

		if (static::responseContainsError($res)) {
			AdpClientLog('cacheMytime contained error');
			var_dump($res);
			return false;
		}

		// We need to parse out _custID and _empID
		$find = array('custID = ', 'empID = ');
		$findRes = array();
		$resData = $res[1];
		foreach ($find as $findme) {
			$pos = strpos($resData, $findme) + strlen($findme);
			$end = strpos($resData, "\n", $pos) - $pos;
			$findRes[] = trim(substr($resData, $pos, $end), static::getTrimJsChars("';"));
		}

		return $this->cachedExtra = array(
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

	public function fetchActivityJournalPage()
	{
		AdpClientLog('fetchActivityJournalPage');
		$mytime = $this->cacheMytime();
		if (!$mytime) {
			return false;
		}

		$this->setupRequest(true, '/ezLaborManagerNet/UI4/Common/TLMRevitServices.asmx/GetActivityJournal');
		$this->setPostData($mytime['extra'], true);

		$res = $this->fetchRequest();
		if (static::responseContainsError($res)) {
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
		if (!$data) return false;

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

	private function fetchTimesheetPage(DateTime $date_begin = null, DateTime $date_end = null)
	{
		AdpClientLog('fetchTimesheetPage');

		$date_cli = true;
		if ($date_begin === null) {
			$date_cli = false;
			$date_begin = new DateTime('Monday this week');
		}
		if ($date_end === null) {
			$date_end = clone $date_begin;
			$date_end->add(DateInterval::createFromDateString('1 week'));
		}

		// Load initial page
		$this->setupRequest(false, '/ezLaborManagerNet/iFrameRedir.aspx?pg=122');
		$res = $this->fetchRequest();
		if (static::responseContainsError($res)) {
			return false;
		}

		if ($date_cli) {
			AdpClientLog('fetchTimesheetPage POST');
			
			$res_get = $res;
			$res_form = static::serializeForm($res_get[1], '#form1');
			$res = null;

			// Then load with correct date
			// /ezLaborManagerNet/UI4/Standard/EmployeeServices/TimeEntry/EmployeeTimeSheet.aspx
			$this->setupRequest(true, $res_form['action']);

			$this->setPostData(array_merge($res_form['fields'], array(
				'UI4:ctBody:ctrlDtRangeSelector:seldaterange' => 'UserDefined',
				'UI4:ctBody:ctrlDtRangeSelector:BeginDate' => $date_begin->format('Y-m-d'),
				'UI4:ctBody:ctrlDtRangeSelector:EndDate' => $date_end->format('Y-m-d')
			)), false);

			$res = $this->fetchRequest();
			if (static::responseContainsError($res)) {
				return false;
			}
		}
		return $res[1];
	}
	public function getTimesheet($getHtml = false, array $date_ranges_orig)
	{
		// Split up the date ranges into slots of 30 days
		$date_ranges = array();
		for ($i = 0; $i < count($date_ranges_orig); $i++)
		{
			$date_range_orig = $date_ranges_orig[$i];

			$date_range_begin = $date_range_orig[0];
			$date_range_end = $date_range_orig[1];

			if ($date_range_begin === null || $date_range_end === null)
			{
				continue;
			}

			$diff = $date_range_end->diff($date_range_begin);
			$days = ceil($diff->format('%a'));

			if ($days < 0)
			{
				continue;
			}
			if ($days <= 30)
			{
				$date_ranges[] = $date_range_orig;
				continue;
			}

			/**
			 * Setup for adding ranges
			 */

			$day_interval = 30;

			$date_interval = DateInterval::createFromDateString('30 days');
			$date_interval_1d = DateInterval::createFromDateString('1 day');
			$days_extra = $days % 30;
			$date_interval_extra = DateInterval::createFromDateString(sprintf('%d days', $days_extra));

			$date_a = null;
			$date_b = clone $date_range_begin;

			/**
			 * Add ranges for 30-day intervals
			 */

			for ($d = 0; $d < floor($days / 30); $d++)
			{
				$date_a = clone $date_b;
				$date_b = clone $date_a;

				if ($d > 0)
				{
					$date_a->add($date_interval_1d);
				}
				$date_b->add($date_interval);

				$date_ranges[] = array($date_a, $date_b);
			}

			/**
			 * Add interval for extra days
			 */

			$date_a = clone $date_b;
			$date_b = clone $date_a;
			$date_a->add($date_interval_1d);
			$date_b->add($date_interval_extra);
			$date_ranges[] = array($date_a, $date_b);
		}

		// print_r($date_ranges); die;

		if (count($date_ranges) < 1)
		{
			$date_ranges[] = array(null, null);
		}

		// We're going to be parsing the JS
		$objs = array();
		$addObj = function($input) use(&$objs) {
			$objs[] = js_to_php_array($input);
		};
		js::define('external', array(
			'addObj' => $addObj
		));


		foreach ($date_ranges as $date_range)
		{
			$html = $this->fetchTimesheetPage($date_range[0], $date_range[1]);
			if ($getHtml || $html === false) {
				return $html;
			}

			foreach (explode("\n", $html) as $line) {
				$line = trim($line);
	
				// HTML lines with TCMS.oTD.push
				if (has_prefix('TCMS.oTD.push', $line)) {
					$line = str_replace('TCMS.oTD.push', '', $line); // Get rid of function call
					$line = strip_tags($line); // Get rid of ending </script>

					$line = 'external.addObj' . $line . ';';

					// echo "Running JS:\n\t" . $line . "\n";
					js::run($line);
				}
			}

		}
		
		array_walk_recursive($objs, function(&$a){
			$a = is_string($a) ? urldecode($a) : $a;
		});
		return $objs;
	}
	public function showTimesheet(DateTime $date_begin = null, DateTime $date_end = null)
	{
		$data = $this->getTimesheet(false, array(array($date_begin, $date_end)));
		if (!$data) return false;

		$headers = array(
			'Day',
			'Date-in',
			'Time-in',
			'Time-out',
			'Hours',
			'Daily Total',
			'Total'
		);
		$rows = array();

		$total_seconds = 0;
		$daily_seconds = array();

		/**
		 * https://workforcenow.adp.com/ezLaborManagerNet/UI4/js/TimecardManagerTable.js?20.10.28.004
		 * 
		 * Column listing
		 * 
		 * ObjectID: 0,
		 * Errors: 1,
		 * IsNew: 2,
		 * IsUpdate: 3,
		 * IsDelete: 4,
		 * SupvApprovalFlag: 5,
		 * LoanApprovalFlag: 6,
		 * PayDate: 7,
		 * PunchOrInTime: 8,
		 * InType: 9,
		 * OutTime: 10,
		 * TotalHours: 11,
		 * OutType: 12,
		 * DepartmentID: 13,
		 * EmployeeID: 14,
		 */

		foreach ($data as $d) {
			$d_date = new DateTime($d[7]);
			$d_date_unique = $d_date->format('Y-m-d');

			$d_time_in = new DateTime($d[8][0] . ' ' . $d[8][1]);
			$d_time_out = null;
			$d_timeinout_diff = null;
			if (!empty($d[10])) {
				$d_time_out = new DateTime($d[8][0] . ' ' . $d[10]);
			}

			$d_timeinout_diff = $d_time_in->diff($d_time_out ?: new DateTime);
			$d_timeinout_diff_seconds = dateinterval_to_seconds($d_timeinout_diff);

			if (!isset($daily_seconds[$d_date_unique])) {
				$daily_seconds[$d_date_unique] = 0;
			}
			$daily_seconds[$d_date_unique] += $d_timeinout_diff_seconds;
			$total_seconds += $d_timeinout_diff_seconds;

			$rows[] = array(
				$d_date->format('D'),
				$d_date->format('m/d/Y'),
				$d_time_in->format('h:i A'),
				$d_time_out ? $d_time_out->format('h:i A') : '(now)',
				$d_timeinout_diff ? sprintf('%.02f', $d_timeinout_diff_seconds / (60 * 60)) : '',
				sprintf('%.02f', $daily_seconds[$d_date_unique] / (60 * 60)),
				sprintf('%.02f', $total_seconds / (60 * 60))
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
		if (!$mytime) {
			return false;
		}

		$this->setupRequest(true, '/ezLaborManagerNet/UI4/Common/TLMRevitServices.asmx/ProcessClockFunctionAndReturnMsg');
		$this->setPostData(array_merge($mytime['extra'], array(
			'sCulture' => 'en-US',
			'sEvent' => $action
		)), true);

		$res = $this->fetchRequest();
		$data = $res[1];

		$data_pipe = explode('|', $data);
		$success = strtolower(array_shift($data_pipe)) == 'true';

		return array(
			$success,
			utf8_decode(implode('|', $data_pipe))
		);
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

	public static function serializeForm($html, $ident)
	{
		$dom = new simple_html_dom();
		$dom->load($html);

		$form = $dom->find($ident, 0);
		if (!$form) return false;

		$retForm = array(
			'action' => html_entity_decode($form->action),
			'fields' => array()
		);

		foreach ($form->find('input') as $input) {
			$retForm['fields'][$input->name] = html_entity_decode($input->value);
		}
		return $retForm;
	}

	public static function responseContainsError($res) {
		return (
			$res[1] === false ||
			$res[0]['http_code'] != 200 ||
			strpos($res[1], 'System Error') !== false ||
			strpos($res[0]['redirect_url'], 'Error.aspx') !== false
		);
	}

	static private function getTrimJsChars($extra = '')
	{
		// Besides the default we need `'` and `;`
		return " \t\n\r\0\x0B" . $extra;
	}
}
