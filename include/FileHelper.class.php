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

	public static function clear($path)
	{
		return clearstatcache(true, $path);
	}
}
