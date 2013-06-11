<?php
/**
 * Copyright 2012-2013 Ryan. All rights reserved.
 * https://github.com/rsully
 * You may use this code provided this message and the above copyright are kept in place.
 **/
class DB extends PDO
{
	private $prepared = null;
	
	public static function create($db = '', $user = '', $pass = '', $host = '127.0.0.1', $opts = array()) {
		// PDO::MYSQL_ATTR_INIT_COMMAND == 1002
		// 	http://stackoverflow.com/questions/2424343/undefined-class-constant-mysql-attr-init-command-with-pdo
		$myOpts = array(1002 => 'SET NAMES utf8');
		foreach ($opts as $k => $v) {
			$myOpts[$k] = $v;
		}
		return new DB(
			"mysql:dbname=$db;host=$host", 
			$user, $pass, 
			$myOpts
		);
	}

	// Should only be called with options the first time.
	public static function shared($db = '', $user = '', $pass = '', $host = '127.0.0.1')
	{
		static $conn = null;
		if (!$conn && $db) {
			$conn = self::create($db, $user, $pass, $host);
		}
		return $conn;
	}
	
	// A combination of sprintf with PDO prepared statements
	public function preparedQuery(/*[options,] query [, $arg1...$argN]*/) {
		if (!$this->prepared) $this->prepared = array();
		$args = func_get_args();
		// print_r($args);
		$opts = array();
		$query = array_shift($args);

		// Optional arg 1: options
		if (is_array($query)) {
			$opts = $query;
			$query = array_shift($args);
		}

		$hash = md5($query . serialize($opts));
		
		if (!isset($this->prepared[$hash])) {
			$this->prepared[$hash] = $this->prepare($query, $opts);
		}
		$prep = $this->prepared[$hash];
		
		if (count($args) == 1 && is_array($args[0])) {
			// Allow vals to be passed as array
			$args = $args[0];
		}

		$exec = $prep->execute($args);
		return array($exec, $prep);
	}
}
