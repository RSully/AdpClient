<?php
require_once __DIR__ . '/FileHelper.class.php';

class ListenerWebQueue {
	private $queuePath = '', $lockPath = '';

	function __construct($dir, $queueFile = 'listener_web-queue', $lockFile = 'listener_web-queue.lock')
	{
		$this->queuePath = $dir . '/' . $queueFile;
		$this->lockPath = $dir . '/' . $lockFile;
	}

	/**
	 * Lock operations
	 */

	public function lock()
	{
		while (FileHelper::exists($this->lockPath)) {
			// Sleep for 0.1 seconds
			usleep(1000000 * 0.1);
		}
		$this->locked = FileHelper::touch($this->lockPath);
		return $this->locked;
	}
	public function unlock()
	{
		$this->locked = ! FileHelper::unlink($this->lockPath);
		return $this->locked;
	}

	/**
	 * IO operations
	 */

	public function read()
	{
		if (!$this->locked) return false;
		return file_get_contents($this->queuePath);
	}
	public function write($data)
	{
		if (!$this->locked) return false;
		return file_put_contents($this->queuePath, $data);
	}
	public function append($data)
	{
		return $this->write($this->read() . $data);
	}
	public function empty()
	{
		return $this->write('');
	}
}
