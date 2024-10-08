<?php
/**
 * waggo8.3
 * @copyright 2013-2024 CIEL, K.K., project waggo.
 * @license MIT
 */

namespace htmltemplate;

use HtmlTemplateTestUnit;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/local-common.php';

class ApiHtmlTemplateVarReferenceTest extends TestCase
{
	use HtmlTemplateTestUnit;

	public function test_var_reference1()
	{
		$html = <<<PHP
[<?={\$date}?>:<?={\$status}?>:<?={\$count}?>]
PHP;

		$data = [
				'date'   => '2021-01-01',
				'status' => 30,
				'count'  => 30,
			];

		$r    = $this->ht( __METHOD__, $html, $data );
		$this->assertSame( '[2021-01-01:30:30]', $r );
	}

	public function test_var_reference2()
	{
		$data = array(
			'views' => [
				[
					'view'         => 'view1'
				],
				[
					'view'         => 'view2'
				],
				[
					'view'         => 'view3'
				],
				[
					'view'         => 'view4'
				],
				[
					'view'         => 'view5'
				]
			],
			'view1:item_notice'    => 'test1',
			'view2:item_notice'    => 'test2',
			'view3:item_notice'    => 'test3',
			'view4:item_notice'    => 'test4',
			'view5:item_notice'    => 'test5',
		);


		$html = <<<HTML
<!--{each views}-->
<?={\$%views/view:item_notice}?>
<?=match({\$%views/view:item_notice}){
'test1'=>'TEST1',
'test2'=>'TEST2',
'test3'=>'TEST3',
'test4'=>'TEST4',
'test5'=>'TEST5'};
?>
<!--{/each}-->
HTML;

		$r = trim( $this->ht( __METHOD__, $html, $data ) );

		$this->assertSame( 'test1TEST1test2TEST2test3TEST3test4TEST4test5TEST5', $r);
	}
}
