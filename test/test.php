<?php

define('APP_DIR', dirname(__DIR__));

require APP_DIR . "/Php2Zep.php";

$inputDir = APP_DIR . "/examples/php/myapp";
$outputDir = APP_DIR . "/examples/zep/myapp";

$obj = new Php2Zep();

$obj->handleDir($inputDir, $outputDir);