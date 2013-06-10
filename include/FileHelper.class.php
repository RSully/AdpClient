<?php
class FileHelper {
	public static function exists($path)
	{
		self::clear($path);
		return file_exists($path);
	}

	public static function touch($path)
	{
		self::clear($path);
		return touch($path);
	}
	public static function unlink($path)
	{
		self::clear($path);
		return unlink($path);
	}

	public static function read($path)
	{
		self::clear($path);
		return file_get_contents($path);
	}
	public static function write($path, $data)
	{
		self::clear($path);
		return file_put_contents($path, $data);
	}

	public static function clear($path)
	{
		return clearstatcache(true, $path);
	}
}
