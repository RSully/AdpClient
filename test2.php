<?php
require_once __DIR__ . '/include.php';

$table = new cli\Table;
$table->setHeaders(array('test'));
$table->setRows(array(array('testtt')));
$table->display();

$test = function($arg) {
	echo "test from JS\n";
	print_r(js_to_php_array($arg));
};

js::define('external', array('tester' => $test));
js::run('external.tester(["nom"]);');
