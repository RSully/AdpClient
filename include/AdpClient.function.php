<?php

function AdpClientLog() {
	$date = time();

	$args = func_get_args();
	$args[0] = sprintf("[%s] %s\n", $date, $args[0] ?: '(Empty)');
	call_user_func_array('printf', $args);
}

function has_prefix($prefix, $string) {
	return substr($string, 0, strlen($prefix)) == $prefix;
}

function js_to_php_array($arr) {
	$newArr = array();
	$keys = array_keys($arr->slots);

	foreach ($keys as $key) {
		$value = $arr->get($key);

		switch ($value->type) {
			case js_val::UNDEFINED:
			case js_val::NULL:
				$value = null;
				break;
			case js_val::BOOLEAN:
				$value = (bool)$value->toBoolean()->value;
				break;
			case js_val::NUMBER:
				$value = (int)$value->toNumber()->value;
				break;
			case js_val::STRING:
				$value = (string)$value->toStr()->value;
				break;
			case js_val::OBJECT:
				$value = js_to_php_array($value);
				break;
			default:
				$value = 'unknown';
		}
		$newArr[$key] = $value;
	}
	return $newArr;
}

function dateinterval_to_seconds($di) {
	return ($di->y * 365 * 24 * 60 * 60) + 
           ($di->m * 30 * 24 * 60 * 60) + 
           ($di->d * 24 * 60 * 60) + 
           ($di->h * 60 * 60) + 
           ($di->i * 60) + 
           $di->s;
}
function dateinterval_to_hours($di) {
	return dateinterval_to_seconds($di) / (60 * 60);
}
