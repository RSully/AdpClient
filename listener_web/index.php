<?php
// File that adds to the queue

// Used for getting actions to render
require_once __DIR__ . '/../include/AdpClient.class.php';
require_once __DIR__ . '/../include/PageHelper.class.php';
?>
<!DOCTYPE html>
<html>
<head>
	<title>ADP Clock</title>
	<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js"></script>
	<script type="text/javascript" src="script.js"></script>
</head>
<body>
	<h1>ADP Clock System</h1>

	<div id="page">
		<?php
		$page = $_GET['p'];
		if (!isset($page) || !PageHelper::exists($page)) {
			$page = 'clock';
		}

		include PageHelper::path($page);
		?>
	</div>
</body>
</html>
