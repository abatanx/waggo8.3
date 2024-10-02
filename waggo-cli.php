<?php
/**
 * waggo8.3
 * @copyright 2013-2024 CIEL, K.K., project waggo.
 * @license MIT
 */

const WG_CLI = true;
error_reporting( error_reporting() & ~E_NOTICE );

if ( count( $argv ) <= 1 )
{
	die( "Usage: $argv[0] domain[:port] [parameters...]\n" );
}

list( $host, $port ) = explode( ':', $argv[1] );

$_SERVER['SERVER_NAME'] = ! empty( $host ) ? $host : '';
$_SERVER['SERVER_PORT'] = ! empty( $port ) ? $port : 80;

$_SERVER["REQUEST_URI"]     = $_SERVER["REQUEST_URI"] ?? null;
$_SERVER["REQUEST_METHOD"]  = $_SERVER["REQUEST_METHOD"] ?? null;
$_SERVER["SERVER_PROTOCOL"] = $_SERVER["SERVER_PROTOCOL"] ?? null;
$_SERVER["HTTP_USER_AGENT"] = $_SERVER["HTTP_USER_AGENT"] ?? null;
$_SERVER["REMOTE_PORT"]     = $_SERVER["REMOTE_PORT"] ?? null;

require_once __DIR__ . '/waggo.php';
