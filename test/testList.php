<?php

define('APP_DIR', dirname(__DIR__));

require APP_DIR . "/Php2Zep.php";

$obj = new Php2Zep();

$str = '                list($controller, $action) = $segments;';

echo $obj->convertList($str);
