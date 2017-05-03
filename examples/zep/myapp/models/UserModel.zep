
namespace Myapp\Models;

class UserModel
{
	private _users = [
		[
			"id"   : 1,
			"name" : "Jack",
			"job"  : "Engineer"
		],
		[
			"id"   : 2,
			"name" : "Lee",
			"job"  : "Actor"
		],
		[
			"id"   : 3,
			"name" : "Bill",
			"job"  : "Programmer"
		]
	];

	public function getAll()
	{
		return this->_users;
	}

	public function getById( var id)
	{
	    var user, v;
		let user =  null;
		for v in this->_users {
			if (v["id"] == id) {
				let user =  v;
				break;
			}
		}

		return user;
	}

	public function addOne( var user)
	{
	    var u;
		let u =  [
			"id" : count(this->_users),
			"name" : user["name"],
			"job" : user["job"]
		];

		let this->_users[] =  u;
		return true;
	}

	public function replaceOne( var user)
	{
	    var result, k, v;
		let result =  false;
		for k,v in this->_users {
			if (v["id"] == user["id"]) {
				let this->_users[k] =  user;
				let result =  true;
				break;
			}
		}

		return result;
	}

	public function dropOne( var id)
	{
	    var result, k, v;
		let result =  false;
		for k,v in this->_users {
			if (v["id"] == id) {
				array_splice(this->_users, k, 0);
				let result =  true;
				break;
			}
		}

		return result;
	}
}