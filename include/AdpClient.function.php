<?php
function AdpClientLog() {
	$date = time();

	$args = func_get_args();
	$args[0] = sprintf("[%s] %s\n", $date, $args[0] ?: '(Empty)');
	call_user_func_array('printf', $args);
}
