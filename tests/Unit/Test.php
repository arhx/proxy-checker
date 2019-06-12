<?php

namespace Arhx\ProxyChecker\Test;


use Arhx\ProxyChecker\ProxyChecker;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class Test extends TestCase
{

	public function testCheckProxy()
	{
		$checker = new ProxyChecker('http://proxy.tables.co.ua/api/proxy/test');
		$result = $checker->checkProxy(false);
		$checkArray = [
			'allowed' => ['get','post','cookie','referer','user_agent'],
			'disallowed' => [],
			'proxy_level' => 'elite',
		];
		$this->assertArraySubset($checkArray,$result);
	}
}