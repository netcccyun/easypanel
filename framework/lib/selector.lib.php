<?php
class SelectorEvent
{
	public $fd = null;

	public function __construct(&$fd)
	{
		$this->fd = $fd;
	}

	public function read(&$selector)
	{
	}

	public function write(&$selector)
	{
	}
}

class Selector
{
	private $rev = array();
	private $wev = array();

	public function addRead(SelectorEvent $event)
	{
		$k = (string) $event->fd;
		$this->rev[$k] = $event;
	}

	public function addWrite(SelectorEvent $event)
	{
		$k = (string) $event->fd;
		$this->wev[$k] = $event;
	}

	public function removeRead(SelectorEvent $event)
	{
		$k = (string) $event->fd;
		unset($this->rev[$k]);
	}

	public function removeWrite(SelectorEvent $event)
	{
		$k = (string) $event->fd;
		unset($this->wev[$k]);
	}

	public function step($tv_sec, $tv_usec = 0)
	{
		$rfds = array();

		foreach ($this->rev as $k => $ev) {
			$rfds[] = $ev->fd;
		}

		$wfds = array();

		foreach ($this->wev as $k => $ev) {
			$wfds[] = $ev->fd;
		}

		$efds = array();
		$num_changed_streams = stream_select($rfds, $wfds, $efds, $tv_sec, $tv_usec);

		foreach ($rfds as $fd) {
			$ev = $this->rev[(string) $fd];
			$ev->read($this);
		}

		foreach ($wfds as $fd) {
			$ev = $this->wev[(string) $fd];
			$ev->write($this);
		}
	}

	public function isEmpty()
	{
		return empty($this->rev) && empty($this->wev);
	}
}


?>