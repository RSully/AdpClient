<?php
error_reporting(-1);

/* Example2
**
** Here we extend the JavaScript runtime environment with additional objects and methods.
** Since the speed of execution is slower than php speed, you can use this to expose 
** resource-intensive functions for JavaScript to call directly.
** 
*/

#-- Include the js library.
include "js.php";

#-- Define our js code
$code = <<<EOD

external.testShit([5255789, "", false, false, false, false, false, "06%2f13%2f2013%2012%3a00%3a00%20AM", ["06%2f13%2f2013", "08%3a14%20AM"], "IN", "", 0, "", "001004", "ID15591719", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "N", "E", 0, "", 1, false, "", 0, false, false, false, false, false, , "190937_46", "", ]);

// external.testShit({"nom": ["test", 1, false, true]});

// external.testShit([5153687, "", false, false, false, false, false, "06%2f10%2f2013%2012%3a00%3a00%20AM", ["06%2f10%2f2013", "08%3a13%20AM"], "IN", "04%3a46%20PM", 8.55, "", "001004", "ID15591719", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "N", "P", 0, "", 3, false, "", 0, false, false, false, false, false, , "190937_46", "", ]);
// external.testShit([5187509, "", false, false, false, false, false, "06%2f11%2f2013%2012%3a00%3a00%20AM", ["06%2f11%2f2013", "08%3a22%20AM"], "IN", "11%3a42%20AM", 3.3333, "", "001004", "ID15591719", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "N", "P", 0, "", 3, false, "", 0, false, false, false, false, false, , "190937_46", "", ]);
// external.testShit([5197859, "", false, false, false, false, false, "06%2f11%2f2013%2012%3a00%3a00%20AM", ["06%2f11%2f2013", "11%3a53%20AM"], "IN", "04%3a37%20PM", 4.7333, "", "001004", "ID15591719", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "N", "P", 0, "", 3, false, "", 0, false, false, false, false, false, , "190937_46", "", ]);
// external.testShit([5225217, "", false, false, false, false, false, "06%2f12%2f2013%2012%3a00%3a00%20AM", ["06%2f12%2f2013", "08%3a20%20AM"], "IN", "11%3a43%20AM", 3.3833, "", "001004", "ID15591719", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "N", "P", 0, "", 3, false, "", 0, false, false, false, false, false, , "190937_46", "", ]);
// external.testShit([5235629, "", false, false, false, false, false, "06%2f12%2f2013%2012%3a00%3a00%20AM", ["06%2f12%2f2013", "11%3a56%20AM"], "IN", "", 0, "", "001004", "ID15591719", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "N", "E", 0, "", 3, false, "", 0, false, false, false, false, false, , "190937_46", "", ]);

EOD;

#-- Define two functions meant to be called from javascript
#-- note the use of php_int() and php_str() to convert a js value to a php value, and the
#-- use of js_int() and js_str() to convert a php value to a js value.
function js_sha1($str) {
  return js_str(sha1(php_str($str)));
}
function js_add($a, $b) {
  return js_int(php_int($a) + php_int($b));
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
// function print_js_array($arr) {
// 	for ($i = 0; $i < $arr->get('length')->value; $i++) {
// 		var_dump(get_class($arr->get($i)));
// 		if (get_class($arr->get($i)) == 'js_array') {
// 			print_js_array($arr->get($i));
// 		} else {
// 			var_dump($arr->get($i)->value);
// 		}
// 	}
// }

function js_testShit($input) {
	print_r(js_to_php_array($input));
}

$nomnomnom = function($input) {
	print_r(js_to_php_array($input));
};


#-- Define a javascript object named "external" with member functions "sha1" and 
#-- "add", and a property "PI"
js::define("external", array("testShit" => $nomnomnom));

#-- Run the js code.
js::run($code);

?>
