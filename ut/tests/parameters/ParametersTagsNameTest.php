<?php
/**
 * waggo8.3
 * @copyright 2013-2024 CIEL, K.K., project waggo.
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/local-common.php';

require_once __DIR__ . '/../../../framework/gauntlet/WGGInt.php';
require_once __DIR__ . '/../../../framework/parameters/WGParameters.php';

class ParametersTagsNameParam extends WGParameters
{
	#[WGPara( tags: ['user'], name: 'u1', gauntlet: new WGGInt( - 10, 10 ), default: 0 )]
	public int $user_var1;

	#[WGPara( tags: ['user'], name: 'u2', gauntlet: new WGGInt( - 10, 10 ), default: 0 )]
	public int $user_var2;

	#[WGPara( tags: ['group'], name: 'g1', gauntlet: new WGGInt( - 10, 10 ), default: 0 )]
	public int $group_vars1;

	#[WGPara( tags: ['group'], name: 'g2', gauntlet: new WGGInt( - 10, 10 ), default: 0 )]
	public int $group_vars2;

	#[WGPara( name: 'c1', gauntlet: new WGGInt( - 10, 10 ), default: 0 )]
	public int $company_vars1;

	#[WGPara( name: 'c2', gauntlet: new WGGInt( - 10, 10 ), default: 0 )]
	public int $company_vars2;

	#[WGPara( gauntlet: new WGGInt( - 10, 10 ), default: 0 )]
	public int $company_vars3;

	#[WGPara( gauntlet: new WGGInt( - 10, 10 ), default: 0 )]
	public int $company_vars4;
}

class ParametersTagsNameTest extends TestCase
{
	public function test_int_v1()
	{
		$_GET = [
			'u1' => 1,
			'u2' => 2,
			'g1' => 3,
			'g2' => 4,
			'c1' => 5,
			'c2' => 6,
			'company_vars3' => 7,
			'company_vars4' => 8
		];

		$g = new ParametersTagsNameParam( null );
		$g->initByDefault();

		$this->assertEquals( 'u1=0&u2=0&g1=0&g2=0&c1=0&c2=0&company_vars3=0&company_vars4=0', $g->getParamString() );

//		$g->initByDefault(['user']);
//		$this->assertEquals( 'u1=0&u2=0', $g->getParamString(['user']) );
//		$this->assertEquals( 'g1=3&g2=4', $g->getParamString(['group']) );
//		$this->assertEquals( 'c1=5&c2=6', $g->getParamString(['company']) );
//		$this->assertEquals( 'u1=0&u2=0&g1=3&g2=4&c1=5&c2=6', $g->getParamString() );
	}
}
