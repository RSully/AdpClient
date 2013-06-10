<?php
// File to GET from cron
require_once __DIR__ . '/../include/ListenerWebQueue.class.php';

$queue = ListenerWebQueue::shared();

if (($queue->lock())) {
	echo $queue->read();
	$queue->empty();

	$queue->unlock();
} else {
	echo json_encode(false);
}
