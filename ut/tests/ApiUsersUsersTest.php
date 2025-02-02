<?php

/**
 * waggo8.3
 * @copyright 2013-2024 CIEL, K.K., project waggo.
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'WG_UNITTEST' ) )
{
	define( 'WG_UNITTEST', true );
}

require_once __DIR__ . '/../unittest-config.php';
require_once __DIR__ . '/../../api/core/lib.php';
require_once __DIR__ . '/../../api/core/secure.php';
require_once __DIR__ . '/../../api/user/users.php';
require_once __DIR__ . '/../../api/dbms/interface.php';

class ApiUsersUsersTest extends TestCase
{
	public function test_wg_is_myself()
	{
		wg_set_login( 1234 );

		$this->assertEquals( true, wg_is_myself( 1234 ) );
		$this->assertEquals( false, wg_is_myself( 1235 ) );

		$this->assertEquals( true, wg_is_myself( '1234' ) );
		$this->assertEquals( false, wg_is_myself( '1235' ) );

		$this->assertEquals( true, wg_is_myself( '1234 ' ) );
		$this->assertEquals( false, wg_is_myself( '1235 ' ) );

		$this->assertEquals( true, wg_is_myself( ' 1234 ' ) );
		$this->assertEquals( false, wg_is_myself( ' 1235 ' ) );
	}

	public function test_wg_is_user()
	{
		wg_set_login( 0 );

		_E( <<<SQL
DROP VIEW IF EXISTS base_normal;
DROP TABLE IF EXISTS base;
CREATE TABLE base (
    usercd   INTEGER NOT NULL,
    login    VARCHAR(256) NOT NULL,
    password VARCHAR(256) NOT NULL,
    name     VARCHAR(256) NOT NULL,
    enabled  BOOLEAN NOT NULL,
    deny     BOOLEAN NOT NULL,
    security INTEGER NOT NULL,
    initymd  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updymd   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE base ADD PRIMARY KEY (usercd);
CREATE VIEW base_normal AS SELECT * FROM base WHERE enabled=true AND deny=false;
CREATE UNIQUE INDEX base_pkey1 ON base (login);
INSERT INTO base(usercd,login,password,name,enabled,deny,security,initymd,updymd) VALUES(0,0,'','Guest',true,false,0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP);
INSERT INTO base(usercd,login,password,name,enabled,deny,security,initymd,updymd) VALUES(10,10,'','Guest',true,false,10,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP);
INSERT INTO base(usercd,login,password,name,enabled,deny,security,initymd,updymd) VALUES(40,40,'','Guest',true,false,40,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP);
INSERT INTO base(usercd,login,password,name,enabled,deny,security,initymd,updymd) VALUES(50,50,'','Guest',true,false,50,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP);
SQL
		);

		// ここからテストコード
		$this->assertEquals( true, wg_is_user( 0 ) );
		$this->assertEquals( false, wg_is_user( 1 ) );

		$this->assertEquals( false, wg_is_admin( 0 ) );
		$this->assertEquals( false, wg_is_admin( 10 ) );
		$this->assertEquals( false, wg_is_admin( 40 ) );
		$this->assertEquals( true, wg_is_admin( 50 ) );

		$_SERVER['REMOTE_ADDR']      = true;
		$_SERVER['HTTP_X_CLIENT_IP'] = "0.0.0.0,1.2.3.4,5.6.7.8,192.168.0.0.1";
		wg_set_login( 0 );
		$this->assertFalse( wg_is_login() );
		$this->assertEquals( 0, wg_get_usercd() );
		$this->assertEquals( false, wg_is_admin() );

		wg_set_login( 10 );
		$this->assertTrue( wg_is_login() );
		$this->assertEquals( 10, wg_get_usercd() );
		$this->assertEquals( false, wg_is_admin() );

		wg_set_login( 50 );
		$this->assertTrue( wg_is_login() );
		$this->assertEquals( 50, wg_get_usercd() );
		$this->assertEquals( true, wg_is_admin() );

		wg_unset_login();
		$this->assertfalse( wg_is_login() );
		$this->assertNotEquals( 50, wg_get_usercd() );
		$this->assertEquals( false, wg_is_admin() );

		_E( <<<SQL
DROP VIEW IF EXISTS base_normal;
DROP TABLE IF EXISTS base; --終了するときにtableは消す
SQL
		);
	}
}
