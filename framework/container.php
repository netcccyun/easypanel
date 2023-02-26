<?php
class Container
{
	static private $container = NULL;
	private $objectPool = array();

	private function __construct()
	{
	}

	public function getInstance()
	{
		if (self::$container === NULL) {
			self::$container = new Container();
		}

		return self::$container;
	}

	private function& getObj($className)
	{
		if (isset($this->objectPool[$className])) {
			return $this->objectPool[$className];
		}

		return false;
	}

	private function setObj($className, $classObj)
	{
		$this->objectPool[$className] = $classObj;
	}

	public function newObj($module, $className, $mul_mod)
	{
		$object = false;

		if ($mul_mod) {
			$object = $this->newObject($className);
		}
		else {
			$object = $this->getObj($module . '/' . $className);

			if ($object === false) {
				$object = $this->newObject($className);
				$this->setObj($module . '/' . $className, $object);
			}
		}

		return $object;
	}

	/**
	 * 新建对象
	 */
	private function newObject($className)
	{
		$object = new $className();

		if (!$object) {
			trigger_error($className . '新建对象错误', E_USER_ERROR);
			exit();
		}

		return $object;
	}
}


?>