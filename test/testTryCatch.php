<?php

define('APP_DIR', dirname(__DIR__));

require APP_DIR . "/Php2Zep.php";

$obj = new Php2Zep();

$strArr = [
	'} catch (\yii\base\Excpetion $e)',
	'} catch(Excpetion $e) {',
	'} catch(\Excpetion $e) {',
	'} catch (\yii\base\Excpetion $e)'
];

foreach ($strArr as $str) {
	echo $obj->convertTryCatch($str), PHP_EOL;
}
