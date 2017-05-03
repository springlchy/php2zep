<?php

define('APP_DIR', dirname(__DIR__));

require APP_DIR . "/Php2Zep.php";

$obj = new Php2Zep();

$str = 'Yii::app->a[3] = 4';

echo $obj->handleLine($str);