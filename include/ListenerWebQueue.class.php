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

	public function lock($wait = 10, $interval = 0.1)
	{
		$iteration = 0;
		while (FileHelper::exists($this->lockPath)) {
			if ((++$iteration * $interval) >= $wait) {
				$this->locked = false;
				return $this->locked;
			}

			// Sleep for 0.1 seconds
			usleep(1000000 * $interval);
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

	private function read()
	{
		if (!$this->locked) return false;
		return FileHelper::read($this->queuePath);
	}
	private function write($data)
	{
		if (!$this->locked) return false;
		return FileHelper::write($this->queuePath, $data);
	}

	/**
	 * Queue operations
	 */

	public function append($item)
	{
		$json = json_decode($this->read(), true);
		$json[] = $item;
		return $this->write(json_encode($json));
	}
	public function empty()
	{
		return $this->write('');
	}
}
