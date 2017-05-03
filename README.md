# php2zep
php2zep is a tool that translate php language into zephir language

# Install & Usage

> ```
> git clone https://github.com/springlchy/php2zep
> ```

​	Then, require the Php2Zep.php.

> ```
> <?php
>
> define('APP_DIR', dirname(__DIR__));
>
> require APP_DIR . "/Php2Zep.php";
>
> $inputDir = APP_DIR . "/examples/php/myapp"; // assume that this is the directory of your application which is written by php
> $outputDir = APP_DIR . "/examples/zep/myapp"; // destination directory, .zep
>
> $obj = new Php2Zep();
>
> $obj->handleDir($inputDir, $outputDir);
> ```

# Example

In this example, we'll write a simple MVC applicataion using PHP, and then, translate them into zephir scripts, then build an extension. And at last, run the application with several lines of code. (Linux Only)

## Step 1: Install Zephir

Of course we must have Zephir installed. See [Zephir](https://zephir-lang.com)

## Step 2: Initialize a Zephir extension

> ``` zephir myapp
> cd /home/theta/zephir
> zephir myapp
> ```

Then we have the directory structure as below:

  >\+ myapp
  >
  >   | - config.json
  >
  >   | + ext
  >
  >   | + myapp (*we'll put translated zephir scripts here, assume this directory is /home/theta/zephir/myapp/myapp*)

## Step 3: create php application

We have the structure as below:

>\+ myapp (*assume this directory is /home/theta/php/myapp*)
>
>​    | + controllers
>
>​         | - UserController.php
>
>​    | + models
>
>​         | - UserModel.php
>
>​    | - Application.php

* UserModel.php

  > ``` php
  > <?php
  >
  > namespace myapp\models;
  >
  > class UserModel
  > {
  > 	...
  > }
  > ```

  See the **"examples"** directory for more detail.

* UserController.php

  > ``` php
  > <?php
  >
  > namespace myapp\controllers;
  >
  > use myapp\models\UserModel;
  >
  > class UserController
  > {
  > 	private $_model;
  >
  > 	public function __construct()
  > 	{
  > 		$this->_model = new UserModel();
  > 	}
  >     ...
  > }
  > ```

  See the **"examples"** directory for more detail.

* Application.php

  > ``` php
  > <?php
  >
  > namespace myapp;
  >
  > class Application
  > {
  > 	private $_defaultControllerNameSpace = "\Myapp\Controllers";
  > 	private $_defaultController = "user";
  > 	private $_DefaultAction = "index";
  >
  > 	private $_controllerSuffix = "Controller";
  > 	private $_actionSuffix = "Action";
  >
  > 	public function __construct()
  > 	{
  > 	}
  >   	public function parseRoute()
  >     {
  >       ...
  >     }
  >   
  >     public function run()
  >     {
  >       ...
  >     }
  > }
  > ```
  >

  See the **"examples"** directory for more detail.


## Step 4: Translate php into zephir using php2zep

​	convert.php

> ``` php
> $inputDir = "/home/theta/php/myapp";
> $outputDir = "/home/theta/zephir/myapp/myapp";
>
> $obj = new Php2Zep();
>
> $obj->handleDir($inputDir, $outputDir);
> ```

​      run.

> ``` shell
> php convert.php
> ```

​       move to /home/theta/zephir/myapp/myapp, we can see:

>\+ myapp
>
>​    | + controllers
>
>​         | - UserController.zep
>
>​    | + models
>
>​         | - UserModel.zep
>
>​    | - Application.zep

## Step 5: Install Extension

> ```
> cd /home/theta/zephir/myapp
> sudo zephir install
> ```

​       Add **myapp.so** to your php.ini

> ```
> extension=myapp.so
> ```

​       Check

> ```
> php -m | grep "myapp" // output: myapp
> ```

## Step 6: Test
​       Write a bootstrap script ( saving as index.php):

> ``` php
> <?php
>   $app = new \Myapp\Application();
>   echo json_encode($app->run());
> ```

​       Restart phpfpm

> ```shell
> killall phpfpm
> phpfpm
> ```

​      Visit `http://localhost/myapp/index.php`, output:

> ```
> [{"id":1,"name":"Jack","job":"Engineer"},{"id":2,"name":"Lee","job":"Actor"},{"id":3,"name":"Bill","job":"Programmer"}]
> ```

  **Congratulations! It Works!**

