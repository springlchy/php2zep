<?php

namespace myapp\controllers;

use myapp\models\UserModel;

class UserController
{
	private $_model;

	public function __construct()
	{
		$this->_model = new UserModel();
	}

	public function indexAction()
	{
		if (isset($_GET['id'])) {
			return $this->_model->findById(intval($_GET['id']));
		}

		return $this->_model->getAll();
	}

	public function addAction()
	{
		if (!isset($_POST["name"]) || !isset($_POST["job"])) {
			return [1, "name or job is empty"];
		}

		$this->_model->addOne($_POST);
		return $this->_model->getAll();
	}

	public function editAction()
	{
		if (!isset($_POST["name"]) || !isset($_POST["job"])) {
			return [1, "name or job is empty"];
		}

		$this->_model->replaceOne($_POST);
		return $this->_model->getAll();
	}

	public function dropAction()
	{
		$this->_model->dropOne(intval($_GET["id"]));
		return $this->_model->getAll();
	}
}