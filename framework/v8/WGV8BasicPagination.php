<?php
/**
 * waggo8.3
 * @copyright 2013-2022 CIEL, K.K., project waggo.
 * @license MIT
 */

abstract class WGV8BasicPagination extends WGV8Object
{
	abstract public function offset(): int;

	abstract public function limit(): int;

	abstract public function setTotal( $total ): self;

}
