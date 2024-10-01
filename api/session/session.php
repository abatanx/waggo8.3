<?php
/**
 * waggo8.3
 * @copyright 2013-2024 CIEL, K.K., project waggo.
 * @license MIT
 */

function wg_unset_session( $regex ): void
{
	foreach ( $_SESSION as $k => $v )
	{
		if ( preg_match( $regex, $k ) )
		{
			$_SESSION[ $k ] = null;
			unset( $_SESSION[ $k ] );
		}
	}
}
