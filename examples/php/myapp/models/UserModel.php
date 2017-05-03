<?php

namespace myapp\models;

class UserModel
{
	private $_users = [
		[
			"id"   => 1,
			"name" => "Jack",
			"job"  => "Engineer"
		],
		[
			"id"   => 2,
			"name" => "Lee",
			"job"  => "Actor"
		],
		[
			"id"   => 3,
			"name" => "Bill",
			"job"  => "Programmer"
		]
	];

	public function getAll()
	{
		return $this->_users;
	}

	public function getById($id)
	{
		$user = null;
		foreach ($this->_users as $v) {
			if ($v["id"] == $id) {
				$user = $v;
				break;
			}
		}

		return $user;
	}

	public function addOne($user)
	{
		$u = [
			"id" => count($this->_users),
			"name" => $user["name"],
			"job" => $user["job"]
		];

		$this->_users[] = $u;
		return true;
	}

	public function replaceOne($user)
	{
		$result = false;
		foreach ($this->_users as $k => $v) {
			if ($v["id"] == $user["id"]) {
				$this->_users[$k] = $user;
				$result = true;
				break;
			}
		}

		return $result;
	}

	public function dropOne($id)
	{
		$result = false;
		foreach ($this->_users as $k => $v) {
			if ($v["id"] == $id) {
				array_splice($this->_users, $k, 0);
				$result = true;
				break;
			}
		}

		return $result;
	}
}