<?php
class PageHelper {
	public static function path($p)
	{
		return sprintf('%s/%s.page.php', realpath(__DIR__ . '/../listener_web'), $p);
	}

	public static function exists($p)
	{
		return file_exists(self::path($p));
	}
}
