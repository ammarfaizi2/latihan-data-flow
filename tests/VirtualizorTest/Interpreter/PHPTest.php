<?php

namespace VirtualizorTest\Interpreter;

use Plugins\Virtualizor\Bridge;
use PHPUnit\Framework\TestCase;

class PHPTest extends TestCase
{

	/**
	 * @var \Plugin\Virtualizor\Bridge
	 */
	private $instance;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->instance = new Bridge(
			"<?php print \"Hello World!\";"
			, "php"
		);
	}

	public function test1()
	{
		$out = $this->instance->exec();
		$this->assertTrue(
			$out === "Hello World!" || strpos($out, "Failed to connect to ") === 0
		);
	}
}
