
namespace Myapp;

class Application
{
	private _defaultControllerNameSpace = "\Myapp\Controllers";
	private _defaultController = "user";
	private _DefaultAction = "index";

	private _controllerSuffix = "Controller";
	private _actionSuffix = "Action";

	public function __construct()
	{
	}

	public function parseRoute()
	{
	    var controller, action, r, segments;
		let controller =  this->_defaultController;
		let action =  this->_DefaultAction;

		let r =  (isset _GET["r"]) ? _GET["r"] : "";
		if (!(empty r)) {
			let segments =  explode("/", r);
			if (count(segments) == 1) {
				let controller =  segments[0];
			} else {
				let controller =  segments[0];
				let action =  segments[1];
			}
		}

		return [controller, action];
	}

	public function run()
	{
	    var routes, controller, action, className, methodName, controllerRef, controllerObj, methodRef, e;
		let routes =  this->parseRoute();
		let controller =  routes[0];
		let action =  routes[1];

		let className =  this->_defaultControllerNameSpace . "\\" . ucfirst(controller) . this->_controllerSuffix;

		try {
			if (!class_exists(className)) {
				throw new \Exception(className . " does not exist!");
			}

			let methodName =  action . this->_actionSuffix;

			let controllerRef =  new \ReflectionClass(className);
			if (!(controllerRef->hasMethod(methodName))) {
				throw new \Exception(className . " does not have method " . methodName);
			}

			let controllerObj =  controllerRef->newInstance();
			let methodRef =  controllerRef->getMethod(methodName);
			if (!(methodRef->isPublic())) {
				throw new \Exception(methodName . " is not public!");
			}			
		} catch \Exception, e {
			// do nothing
		}


		return methodRef->invoke(controllerObj);
	}
}