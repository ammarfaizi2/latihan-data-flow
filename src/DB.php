<?php

use System\Hub\Singleton;

class DB
{
	
	use Singleton;

	/**
	 * @var \PDO
	 */
	private $pdo;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";port=".DB_PORT, DB_USER, DB_PASS);
	}

	/**
	 * Call static pdo.
	 *
	 * @param string $method
	 * @param array  $param
	 * @return mixed
	 */
	public static function __callStatic($method, $param)
	{
		return (new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";port=".DB_PORT, DB_USER, DB_PASS))->{$method}(...$param);
	}
}

function pc($exe, $pdo)
{
	if (! $exe) {
		var_dump($pdo->errorInfo());
		die();
	}
	return true;
}