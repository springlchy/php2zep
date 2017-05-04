<?php

namespace myapp;

class Application
{
	private $_defaultControllerNameSpace = "\Myapp\Controllers";
	private $_defaultController = "user";
	private $_DefaultAction = "index";

	private $_controllerSuffix = "Controller";
	private $_actionSuffix = "Action";

	public function __construct()
	{
	}

	public function parseRoute()
	{
		$controller = $this->_defaultController;
		$action = $this->_DefaultAction;

		$r = isset($_GET['r']) ? $_GET['r'] : '';
		if (!empty($r)) {
			$segments = explode("/", $r);
			if (count($segments) == 1) {
				$controller = $segments[0];
			} else {
                list($controller, $action) = $segments;
			}
		}

		return [$controller, $action];
	}

	public function run()
	{
		list($controller, $action) = $this->parseRoute();

		$className = $this->_defaultControllerNameSpace . "\\" . ucfirst($controller) . $this->_controllerSuffix;

		try {
			if (!class_exists($className)) {
				throw new \Exception($className . " does not exist!");
			}

			$methodName = $action . $this->_actionSuffix;

			$controllerRef = new \ReflectionClass($className);
			if (!($controllerRef->hasMethod($methodName))) {
				throw new \Exception($className . " does not have method " . $methodName);
			}

			$controllerObj = $controllerRef->newInstance();
			$methodRef = $controllerRef->getMethod($methodName);
			if (!($methodRef->isPublic())) {
				throw new \Exception($methodName . " is not public!");
			}			
		} catch (\Exception $e) {
			// do nothing
		}


		return $methodRef->invoke($controllerObj);
	}
}