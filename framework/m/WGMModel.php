<?php
/**
 * waggo8
 * @copyright 2013-2021 CIEL, K.K., project waggo.
 * @license MIT
 * @noinspection PhpUnused
 */
declare( strict_types=1 );

require_once __DIR__ . '/../../waggo.php';
require_once __DIR__ . '/../v8/WGV8Object.php';
require_once __DIR__ . '/WGMModelField.php';
require_once __DIR__ . '/WGMModelGetKeys.php';
require_once __DIR__ . '/WGMModelFilter.php';
require_once __DIR__ . '/WGMVarsObject.php';

global $WGMModelID;
$WGMModelID = [];

/**
 * OR/M
 */
class WGMModel
{
	const N = 0, S = 1, B = 2, TD = 3, TT = 4, TS = 5, D = 6;
	const JNULL = 0, JINNER = 1, JLEFT = 2, JRIGHT = 3;

	public WGDBMS $dbms;

	public array $uniqueIds;
	public array $avars, $vars;

	/**
	 * @var WGMModelField[]
	 */
	public array $fields;

	protected bool $isModelDebug;
	public array $assign;
	public string $tableName;
	public string $aliasName;
	public array $backvars, $initYmdKeys, $updYmdKeys;
	public int $recs;

	protected WGMModelFilter $defaultFilter;
	protected array $conditions;

	protected WGV8BasicPagination|null $pager;
	protected array $joins;

	protected stdClass $dbmsProperty;

	protected array $orderByArray;
	protected int $orderOrder;
	protected string|null $offsetKeyword, $limitKeyword;

	public function __construct( string $tableName, WGDBMS|null $dbms = null )
	{
		global $WGMModelID;
		$this->isModelDebug = WG_MODELDEBUG;

		$this->uniqueIds = [];

		$kid = $tableName[0];
		if ( empty( $WGMModelID[ $kid ] ) )
		{
			$WGMModelID[ $kid ] = 1;
		}
		else
		{
			$WGMModelID[ $kid ] ++;
		}
		$id = $WGMModelID[ $kid ];

		$this->aliasName = $kid . $id;
		$this->dbms      = ( is_null( $dbms ) ) ? _QC() : $dbms;

		$this->initDBMSProperty();

		$this->defaultFilter = new WGMModelFILTER();

		$this->tableName    = $tableName;
		$this->fields       = [];
		$this->assign       = [];
		$this->vars         = [];
		$this->avars        = [];
		$this->backvars     = [];
		$this->joins        = [];
		$this->conditions   = [];
		$this->initYmdKeys  = [];
		$this->updYmdKeys   = [];
		$this->orderByArray = [];
		$this->orderOrder   = PHP_INT_MAX;
		$this->recs         = 0;
		$this->pager        = null;

		$this->offsetKeyword = null;
		$this->limitKeyword  = null;

		$this->initFields( $tableName );
	}

	protected function initDBMSProperty()
	{
		$this->dbmsProperty = new stdClass();
		if ( $this->dbms instanceof WGDBMSPostgreSQL )
		{
			$this->dbmsProperty->N  = '/^(int|smallint|bigint)/';
			$this->dbmsProperty->TD = '/^(date)/';
			$this->dbmsProperty->TT = '/^(time)/';
			$this->dbmsProperty->TS = '/^(timestamp)/';
			$this->dbmsProperty->S  = '/^(char|text|varchar|json)/';
			$this->dbmsProperty->D  = '/^(double|real|numeric)/';
			$this->dbmsProperty->B  = '/^bool/';

			$this->dbmsProperty->BOOL_TRUE = 't';
		}
		else if ( $this->dbms instanceof WGDBMSMySQL )
		{
			$this->dbmsProperty->N  = '/^(int|smallint)/';
			$this->dbmsProperty->TD = '/^(date)/';
			$this->dbmsProperty->TT = '/^(time)/';
			$this->dbmsProperty->TS = '/^(timestamp)/';
			$this->dbmsProperty->S  = '/^(char|text|varchar|json)/';
			$this->dbmsProperty->D  = '/^(double|real|numeric)/';
			$this->dbmsProperty->B  = '/^tinyint\(1\)/';

			$this->dbmsProperty->BOOL_TRUE = '1';
		}
		else
		{
			$this->logFatal( 'Unsupported DBMS' );
		}
	}

	protected function logInfo( string $msg, mixed ...$args ): void
	{
		if ( $this->isModelDebug )
		{
			wg_log_write( WGLOG_INFO, $msg, ...$args );
		}
	}

	protected function logInfoDump( string $msg, mixed ...$args ): void
	{
		if ( $this->isModelDebug )
		{
			wg_log_write( WGLOG_INFO, $msg, ...$args );
		}
	}

	protected function logWarning( string $msg, mixed ...$args ): void
	{
		wg_log_write( WGLOG_WARNING, $msg, ...$args );
	}

	protected function logError( string $msg, mixed ...$args ): void
	{
		wg_log_write( WGLOG_ERROR, $msg, ...$args );
	}

	protected function logFatal( string $msg, mixed ...$args ): void
	{
		wg_log_write( WGLOG_FATAL, $msg, ...$args );
	}

	protected function toFlatArray( array $array ): array
	{
		$result = [];
		foreach ( $array as $v )
		{
			if ( is_array( $v ) )
			{
				$result = array_merge( $result, $this->toFlatArray( $v ) );
			}
			else
			{
				$result[] = $v;
			}
		}

		return $result;
	}

	protected function getFieldTypeFromFormat( string $fieldType ): int|false
	{
		if ( preg_match( $this->dbmsProperty->N, $fieldType ) )
		{
			return self::N;
		}
		else if ( preg_match( $this->dbmsProperty->TS, $fieldType ) )
		{
			return self::TS;
		}
		else if ( preg_match( $this->dbmsProperty->TT, $fieldType ) )
		{
			return self::TT;
		}
		else if ( preg_match( $this->dbmsProperty->TD, $fieldType ) )
		{
			return self::TD;
		}
		else if ( preg_match( $this->dbmsProperty->S, $fieldType ) )
		{
			return self::S;
		}
		else if ( preg_match( $this->dbmsProperty->D, $fieldType ) )
		{
			return self::D;
		}
		else if ( preg_match( $this->dbmsProperty->B, $fieldType ) )
		{
			return self::B;
		}
		else
		{
			return false;
		}
	}

	/** @noinspection PhpInconsistentReturnPointsInspection */
	protected function getOID( string $tableName ): array
	{
		if ( $this->dbms instanceof WGDBMSPostgreSQL )
		{
			list( $oid, $nspname, $relname ) =
				$this->dbms->QQ(
					'SELECT c.oid,n.nspname,c.relname FROM pg_catalog.pg_class c ' .
					'LEFT JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace ' .
					'WHERE pg_catalog.pg_table_is_visible(c.oid) ' .
					'AND c.relname=%s;', $this->dbms->S( $tableName ) );

			return [ $oid, $nspname, $relname ];
		}
		else if ( $this->dbms instanceof WGDBMSMySQL )
		{
			return [ $tableName, $tableName, $tableName ];
		}
		else
		{
			$this->logFatal( 'Unrecognized DBMS type' );
		}
	}

	protected function initFields( string $tableName ): void
	{
		if ( $this->dbms instanceof WGDBMSPostgreSQL )
		{
			list( $oid, , ) = $this->getOID( $tableName );
			$this->dbms->Q(
				'SELECT a.attname,pg_catalog.format_type(a.atttypid,a.atttypmod),a.attnotnull ' .
				'FROM pg_catalog.pg_attribute a ' .
				'WHERE a.attrelid=%s AND a.attnum>0 AND NOT a.attisdropped;',
				$this->dbms->S( $oid ) );

			foreach ( $this->dbms->FALL() as $f )
			{
				list( $name, $format_type, $notnull ) = $f;
				$type = $this->getFieldTypeFromFormat( $format_type );
				if ( $type === false )
				{
					$this->logFatal( 'Unrecognized field type, %s on PostgreSQL/WGMModel', $format_type );
				}

				$this->fields[ $name ] =
					new WGMModelField(
						$type, $format_type, ( $notnull === 't' ), $name
					);
				$this->logInfo( 'Fields[%s] = [Type:%s] [Format:%s] [NotNull:%s] [Func:%s]', $name, $type, $format_type, $notnull, $name );
			}
		}
		else if ( $this->dbms instanceof WGDBMSMySQL )
		{
			$this->dbms->Q( 'DESCRIBE %s', $tableName );

			foreach ( $this->dbms->FALL() as $f )
			{
				list( $name, $format_type, $null, , , ) = $f;
				$type = $this->getFieldTypeFromFormat( $format_type );
				if ( $type === false )
				{
					$this->logFatal( 'Unrecognized field type, %s on MySQL/WGMModel', $format_type );
				}

				$this->fields[ $name ] = new WGMModelField(
					$type, $format_type, ( $null === 'YES' ), $name
				);
				$this->logInfo( 'Fields[%s] = [Type:%s] [Format:%s] [Null:%s] [Func:%s]', $name, $type, $format_type, $null, $name );
			}
		}
		else
		{
			$this->logFatal( 'Unrecognized DBMS type' );
		}
	}

	/** @noinspection PhpInconsistentReturnPointsInspection */
	protected function initFieldsPrimaryKey( string $tableName ): array|false
	{
		if ( $this->dbms instanceof WGDBMSPostgreSQL )
		{
			list( $oid, , ) = $this->getOID( $tableName );
			list( $pk ) = $this->dbms->QQ(
				'SELECT c2.relname ' .
				'FROM pg_catalog.pg_class c, pg_catalog.pg_class c2, pg_catalog.pg_index i ' .
				'WHERE c.oid = %s AND c.oid = i.indrelid AND i.indexrelid = c2.oid AND i.indisprimary = true;',
				$this->dbms->S( $oid ) );
			if ( empty( $pk ) )
			{
				return false;
			}

			list( $indexOid, , ) = $this->getOID( $pk );
			$pks = [];
			$this->dbms->Q(
				'SELECT a.attname ' .
				'FROM pg_catalog.pg_attribute a, pg_catalog.pg_index i ' .
				'WHERE a.attrelid = %s AND a.attnum > 0 AND NOT a.attisdropped AND a.attrelid = i.indexrelid ' .
				'ORDER BY a.attnum;',
				$this->dbms->S( $indexOid ) );
			foreach ( $this->dbms->FALL() as $f )
			{
				$pks[] = $f['attname'];
			}

			return $pks;
		}
		else if ( $this->dbms instanceof WGDBMSMySQL )
		{
			$pks = [];
			$this->dbms->Q( 'DESCRIBE %s', $tableName );
			foreach ( $this->dbms->FALL() as $f )
			{
				list( $name, , , $key, , ) = $f;
				if ( $key === 'PRI' )
				{
					$pks[] = $name;
				}

				return $pks;
			}
		}
		else
		{
			$this->logFatal( 'Unrecognized DBMS type' );
		}
	}

	public function setField( string $keyField, string $formatType, $func ): void
	{
		$type = $this->getFieldTypeFromFormat( $formatType );
		if ( $type === false )
		{
			$this->logFatal( 'Unrecognized field type, %s', $formatType );
		}
		$this->fields[ $keyField ] = new WGMModelField( $type, $formatType, false, $this->expansion( $func ) );
	}

	public function getTable(): string
	{
		return $this->tableName;
	}

	public function getAlias(): string
	{
		return $this->aliasName;
	}

	public function setAlias( string $aliasName ): self
	{
		$this->aliasName = $aliasName;

		return $this;
	}

	public function getFields(): array
	{
		return array_keys( $this->fields );
	}

	public function getFieldType( string $keyField ): int|false
	{
		return ! empty( $this->fields[ $keyField ]->getType() ) ? $this->fields[ $keyField ]->getType() : false;
	}

	public function getFieldFormat( string $keyField ): string|false
	{
		return ! empty( $this->fields[ $keyField ]->getFormatType() ) ? $this->fields[ $keyField ]->getFormatType() : false;
	}

	public function IsNotNullField( string $keyField ): bool
	{
		return $this->fields[ $keyField ]->isNotNull();
	}

	public function IsAllowNullField( string $keyField ): bool
	{
		return ! $this->fields[ $keyField ]->isNotNull();
	}

	public function getPrimaryKeys(): array|false
	{
		return $this->initFieldsPrimaryKey( $this->tableName );
	}

	public function expansion( string $exp, ?string $aliasPrefix = null ): string
	{
		$r = [];
		$t = '';
		$s = preg_split( '//u', $exp, - 1, PREG_SPLIT_NO_EMPTY );

		$get   = function ( &$a ) {
			return count( $a ) > 0 ? array_shift( $a ) : '';
		};
		$peek  = function ( $a ) {
			return count( $a ) > 0 ? $a[0] : '';
		};
		$queue = function ( $f, &$r, &$t, $c = '' ) {
			if ( $t !== '' )
			{
				$r[] = [ $f, $t ];
			}
			$t = $c;
		};

		while ( count( $s ) > 0 )
		{
			$c = $get( $s );
			if ( $c === '\'' )
			{
				$x = $c;
				$queue( 1, $r, $t, $c );
				do
				{
					$c = $get( $s );
					$d = $peek( $s );
					if ( ( $c === $x && $d === $x ) || $c === '\\' )
					{
						$c .= $get( $s );
					}
					$t .= $c;
				}
				while ( $c !== '' && $c !== $x );
				$queue( 0, $r, $t );
			}
			else
			{
				$t .= $c;
			}
		}
		$queue( 1, $r, $t );

		$g = function ( $m ) use ( $aliasPrefix ) {
			return ( $aliasPrefix ?? $this->getAlias() ) . '.' . $m[1];
		};

		return implode( '', array_map( function ( $v ) use ( $g ) {
			return $v[0] === 1 ? preg_replace_callback( '/{(\w+?)}/', $g, $v[1] ) : $v[1];
		}, $r ) );
	}

	public function setAutoTimestamp( $initymds = [ 'initymd' ], $updymds = [ 'updymd' ] ): self
	{
		if ( ! is_array( $initymds ) || ! is_array( $updymds ) )
		{
			$this->logFatal( 'setAutoTimestamp is not an array' );
		}
		$this->initYmdKeys = $initymds;
		$this->updYmdKeys  = $updymds;

		return $this;
	}

	public function getRecs(): int
	{
		return $this->recs;
	}

	public function setFilter( string $keyField, WGMModelFilter $modelFilter ): self
	{
		$this->assign[ $keyField ]['filter'] = $modelFilter;

		return $this;
	}

	public function assign( string $keyField, WGV8Object $view, WGMModelFilter|null $modelFilter = null ): self
	{
		if ( ! isset( $this->fields[ $keyField ] ) )
		{
			$this->logFatal( '\'%s\' not found.', $keyField );
		}

		$this->assign[ $keyField ]['viewobj'] = $view;
		$this->assign[ $keyField ]['filter']  = ( $modelFilter instanceof WGMModelFILTER ) ? $modelFilter : $this->defaultFilter;

		return $this;
	}

	public function release( string $keyField ): self
	{
		unset( $this->assign[ $keyField ] );

		return $this;
	}

	protected function checkNullField( string $keyField, string|null $v ): self
	{
		if ( $this->fields[ $keyField ]->isNotNull() && ( strtolower( $v ) === 'null' || is_null( $v ) ) )
		{
			$this->logFatal( "Field '$keyField' does not allow NULL." );
		}

		return $this;
	}

	protected function posValue( string $pos ): array|null
	{
		if ( preg_match( '/\(([\-0-9.]+),([\-0-9.]+)\)/', $pos, $m ) )
		{
			return [ $m[1], $m[2] ];
		}
		else
		{
			return null;
		}
	}

	protected function fieldValue( string $keyField, mixed $value, string $direction ): mixed
	{
		if ( $direction !== 'PHP' && $direction !== 'DB' )
		{
			$this->logFatal( 'Internal error on fieldValue.' );
		}

		$isAllowNULL = ! $this->fields[ $keyField ]->isNotNull();
		$v           = null;
		switch ( $this->fields[ $keyField ]->getType() )
		{
			case self::N:
				$v = ( $direction === 'DB' ) ? $this->dbms->N( $value, $isAllowNULL ) :
					( is_null( $value ) && $isAllowNULL ? null : (int) $value );
				break;
			case self::S:
				$v = ( $direction === 'DB' ) ? $this->dbms->S( $value, $isAllowNULL ) :
					( is_null( $value ) && $isAllowNULL ? null : (string) $value );
				break;
			case self::B:
				$v = ( $direction === 'DB' ) ? $this->dbms->B( $value, $isAllowNULL ) :
					( is_null( $value ) && $isAllowNULL ? null : ( (string) $value === $this->dbmsProperty->BOOL_TRUE ) );
				break;
			case self::TD:
				$v = ( $direction === 'DB' ) ? $this->dbms->TD( $value, $isAllowNULL ) :
					( is_null( $value ) && $isAllowNULL ? null : (string) $value );
				break;
			case self::TT:
				$v = ( $direction === 'DB' ) ? $this->dbms->TT( $value, $isAllowNULL ) :
					( is_null( $value ) && $isAllowNULL ? null : (string) $value );
				break;
			case self::TS:
				$v = ( $direction === 'DB' ) ? $this->dbms->TS( $value, $isAllowNULL ) :
					( is_null( $value ) && $isAllowNULL ? null : (string) $value );
				break;
			case self::D:
				$v = ( $direction === 'DB' ) ? $this->dbms->D( $value, $isAllowNULL ) :
					( is_null( $value ) && $isAllowNULL ? null : (float) $value );
				break;
			default:
				$this->logFatal( 'Field \'%s\' conversion failed.', $keyField );
		}

		$this->logInfo( '[%s] %s.%s src[%s] [to %s] dst[%s]',
			$this->tableName, $this->aliasName,
			$keyField, $value, $direction,
			$v );

		if ( $direction === 'DB' && $this->fields[ $keyField ]->isNotNull() && $v === 'null' )
		{
			$this->logFatal( 'Field \'%s\' does not allow NULL.', $keyField );
		}

		return $v;
	}

	/** @noinspection PhpInconsistentReturnPointsInspection */
	protected function compareField( string $keyField, mixed $v1, mixed $v2 ): bool
	{
		switch ( $this->fields[ $keyField ]->getType() )
		{
			case self::N:
				return ( $v1 == $v2 );
			case self::S:
			case self::B:
			case self::D:
				return ( $v1 === $v2 );
			case self::TD:
				return ( wg_timediff_second( $v1 ?? '0001-01-01', $v2 ?? '0001-01-01' ) === 0 );
			case self::TT:
				return ( wg_timediff_second( $v1 ?? '00:00:00', $v2 ?? '00:00:00' ) === 0 );
			case self::TS:
				return ( wg_timediff_second( $v1 ?? '0001-01-01 00:00:00', $v2 ?? '0001-01-01 00:00:00' ) === 0 );
		}
		$this->logFatal( 'Unrecognized field type, \'%s\'.', $keyField );
	}

	protected function setAssignedValue( string $keyField, mixed $value ): self
	{
		if ( isset( $this->assign[ $keyField ]['viewobj'] ) )
		{
			$this->assign[ $keyField ]['filter']->modelToView(
				$this->assign[ $keyField ]['viewobj'],
				$this->assign[ $keyField ]['filter']->output( $value )
			);
		}
		else
		{
			$this->vars[ $keyField ] = $value;
		}

		return $this;
	}

	protected function getAssignedValue( string $keyField ): mixed
	{
		if ( isset( $this->assign[ $keyField ]['viewobj'] ) &&
			 ! $this->assign[ $keyField ]['viewobj']->isShowOnly() )
		{
			return $this->assign[ $keyField ]['filter']->input(
				$this->assign[ $keyField ]['filter']->viewToModel(
					$this->assign[ $keyField ]['viewobj']
				)
			);
		}
		else
		{
			return $this->vars[ $keyField ] ?? null;
		}
	}

	public function unJoin(): self
	{
		$this->joins = [];

		return $this;
	}

	public function left( WGMModel $model, array $on ): self
	{
		$this->joins[] = [ self::JLEFT, $model, $on ];

		return $this;
	}

	public function right( WGMModel $model, array $on ): self
	{
		$this->joins[] = [ self::JRIGHT, $model, $on ];

		return $this;
	}

	public function inner( WGMModel $model, array $on ): self
	{
		$this->joins[] = [ self::JINNER, $model, $on ];

		return $this;
	}

	/**
	 * 特定のフィールドのみのデータを、配列として生成する。
	 *
	 * @param string $dataField データとなるフィールド
	 *
	 * @return array データ配列[$dataFieldの値, ...]
	 */
	public function getFieldVars( string $dataField ): array
	{
		$result = [];
		foreach ( $this->avars as $vars )
		{
			$result[] = $vars[ $dataField ];
		}

		return $result;
	}

	/**
	 * 選択肢用の連想配列を生成する。
	 *
	 * @param string $keyField 選択肢のキーとなるフィールド
	 * @param string $dataField 選択肢のデータとなるフィールド
	 *
	 * @return array 選択肢を構成する連想配列[$keyFieldの値=>$dataFieldの値, ...]
	 */
	public function getSelectVars( string $keyField, string $dataField ): array
	{
		$result = [];
		foreach ( $this->avars as $vars )
		{
			$result[ $vars[ $keyField ] ] = $vars[ $dataField ];
		}

		return $result;
	}

	/**
	 * 追加WHERE句を設定する。追加される条件はすべて AND で組み合わされます。
	 *
	 * @param string $where 条件。フィールド名は{}で囲むことによって、実行時に実際のフィールド名に変換されます。
	 *
	 * @return string 追加した conditionId 識別子
	 */
	public function addCondition( string $where ): string
	{
		if ( ! isset( $this->uniqueIds['cond'] ) )
		{
			$this->uniqueIds['cond'] = 0;
		}

		$conditionId = sprintf( 'cond-%d', $this->uniqueIds['cond'] ++ );

		$this->conditions[ $conditionId ] = $this->expansion( $where );

		return $conditionId;
	}

	/**
	 * 追加WHERE句を削除する。
	 *
	 * @param string $conditionId この条件の識別名(任意)
	 *
	 * @return self
	 */
	public function delCondition( string $conditionId ): self
	{
		$this->conditions[ $conditionId ] = null;
		unset( $this->conditions[ $conditionId ] );

		return $this;
	}

	/**
	 * 追加WHERE句の条件を配列で取得する。
	 * @return array 追加WHERE句の文字列。
	 */
	public function getConditions(): array
	{
		return $this->conditions;
	}

	/**
	 * 追加WHERE句をすべてクリアする。
	 * @return self
	 */
	public function clearConditions(): self
	{
		$this->conditions = [];

		return $this;
	}

	public function orderby( ...$args ): self
	{
		$keys = $this->toFlatArray( $args );
		$this->logInfo( 'WGMModel::orderby( %s )', implode( ' , ', $keys ) );

		$orders = [];
		foreach ( $keys as $key )
		{


			if ( is_int( $key ) )
			{
				$this->orderOrder = $key;
			}
			else
			{
				if ( preg_match( '/^(\w+)(\s+\w+)?$/', trim( $key ), $m ) )
				{
					$e        = [ trim( $m[1] ), trim( $m[2] ?? '' ) ];
					$e[0]     = $this->getAlias() . '.' . $e[0];
					$orders[] = implode( ' ', $e );
				}
				else
				{
					$orders[] = $this->expansion( $key );
				}
			}
		}

		$this->orderByArray = $orders;

		return $this;
	}

	public function offset( int|null $offset = null, int|null $limit = null ): self
	{
		$this->logInfo( 'WGMModel::offset( %s )', $offset ?? '', $limit ?? '' );

		if ( wg_is_dbms_postgresql() )
		{
			$this->offsetKeyword = ! is_null( $offset ) ? " OFFSET $offset" : '';
			$this->limitKeyword  = ! is_null( $limit ) ? " LIMIT $limit" : '';
		}
		else if ( wg_is_dbms_mysql() || wg_is_dbms_mariadb() )
		{
			if ( ! is_null( $limit ) )
			{
				$this->offsetKeyword = '';
				$this->limitKeyword  = ! is_null( $offset ) ? " LIMIT $offset,$limit" : "LIMIT $limit";
			}
		}

		return $this;
	}

	public function pager( WGV8BasicPagination $pager ): self
	{
		$this->pager = $pager;

		return $this;
	}

	public function findJoinModel( string $tableName ): WGMModel|false
	{
		/**
		 * @var WGMModel[] $jm
		 */
		foreach ( $this->joins as $jm )
		{
			if ( $jm[1]->getTable() === $tableName )
			{
				return $jm[1];
			}
		}

		return false;
	}


	public function whereOptCondExpression(): array
	{
		/**
		 * @var WGMModel[] $jm
		 */
		$wheres = [];
		foreach ( $this->joins as $jm )
		{
			$wheres = array_merge( $wheres, $jm[1]->whereOptCondExpression() );
		}
		foreach ( $this->getConditions() as $w )
		{
			$wheres[] = $w;
		}

		return $wheres;
	}

	public function whereCondExpression( array $keys ): array
	{
		$wheres = [];
		foreach ( $keys as $k )
		{
			if ( $k instanceof WGMModelGetKeys )
			{
				$m      = $k->getModel();
				$wheres = array_merge( $wheres, $m->whereCondExpression( $k->getKeys() ) );
			}
			else
			{
				$av = $this->getAssignedValue( $k );
				$v  = $this->fieldValue( $k, $av, 'DB' );
				$this->checkNullField( $k, $v );
				$wheres[] = $this->aliasName . '.' . $k . '=' . $v;
			}
		}

		return array_merge( $wheres, $this->whereOptCondExpression() );
	}

	public function whereExpression( array $keys ): array
	{
		$wheres = [];
		foreach ( $keys as $k )
		{
			$av = $this->getAssignedValue( $k );
			$v  = $this->fieldValue( $k, $av, 'DB' );
			$this->checkNullField( $k, $v );
			$wheres[] = $k . '=' . $v;
		}

		return $wheres;
	}

	public function getJoinExternalFields(): array
	{
		/**
		 * @var WGMModel[] $jm
		 */
		$fields = [];
		foreach ( $this->getFields() as $f )
		{
			$fields[] = [
				$this->fields[ $f ]->getNameAppendingPrefix( $this->getAlias() ),
				$this->fields[ $f ]->getNameAppendingPrefix( $this->getAlias() )
			];
		}
		foreach ( $this->joins as $jm )
		{
			$fields = array_merge( $fields, $jm[1]->getJoinExternalFields() );
		}

		return $fields;
	}

	public function getJoinTables( string $base ): string
	{
		/**
		 * @var WGMModel[] $jm
		 */
		foreach ( $this->joins as $jm )
		{
			$on = [];
			foreach ( $jm[2] as $l => $r )
			{
				$l = is_int( $l ) ? $r : $l;
				if ( ! in_array( $l, $this->getFields() ) )
				{
					$this->logFatal( 'Joined LEFT, no \'%s\' field.', $l );
				}
				if ( ! in_array( $r, $jm[1]->getFields() ) )
				{
					$this->logFatal( 'Joined RIGHT, no \'%s\' field.', $r );
				}
				$on[] = $this->getAlias() . '.' . $l . '=' . $jm[1]->getAlias() . '.' . $r;
			}
			$on = implode( ' AND ', $on );
			$j  = '';
			switch ( $jm[0] )
			{
				case self::JLEFT:
					$j = 'LEFT JOIN';
					break;
				case self::JRIGHT:
					$j = 'RIGHT JOIN';
					break;
				case self::JINNER:
					$j = 'INNER JOIN';
					break;
				default:
					$this->logFatal( 'Unrecognized join type.' );
			}
			$base = '(' . $base . ' ' . $j . ' ' . $jm[1]->getTable() . ' AS ' . $jm[1]->getAlias() . ' ON ' . $on . ')';
			$base = $jm[1]->getJoinTables( $base );
		}

		return $base;
	}

	public function getJoinOrders( array $orders ): array
	{
		/**
		 * @var WGMModel[] $jm
		 */
		if ( count( $this->orderByArray ) > 0 )
		{
			$orders[] = [ $this->orderOrder, $this->orderByArray ];
		}
		foreach ( $this->joins as $jm )
		{
			$orders = $jm[1]->getJoinOrders( $orders );
		}

		return $orders;
	}

	public function getJoinModels(): array
	{
		/**
		 * @var WGMModel[] $jm
		 */
		$ret = [ $this ];
		foreach ( $this->joins as $jm )
		{
			$ret = array_merge( $ret, $jm[1]->getJoinModels() );
		}

		return $ret;
	}

	//    aaaa as t1
	//    aaaa as t1 inner join bbbb as t2 on t1.id=t2.id
	//               ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//   (aaaa as t1 inner join bbbb as t2 on t1.id=t2.id) inner join cccc as t3 on t1.id=t3.id
	//   =           ------------------------------------======================================
	//  ((aaaa as t1 inner join bbbb as t2 on t1.id=t2.id) inner join cccc as t3 on t1.id=t3.id
	// (((aaaa as t1 inner join bbbb as t2 on t1.id=t2.id) inner join cccc as t3 on t1.id=t3.id
	public function getJoinExternalTables(): array
	{
		/**
		 * @var WGMModel[] $jm
		 */
		$ret = [];
		foreach ( $this->joins as $jm )
		{
			$ret = array_merge( $ret, $jm[1]->getJoinExternalTables() );
		}

		return $ret;
	}

	public function keys(): WGMModelGetKeys
	{
		$k = new WGMModelGetKeys( $this );
		$k->setKeys( func_get_args() );

		return $k;
	}

	protected function dumpKeys( $keys ): void
	{
		foreach ( $keys as $k )
		{
			if ( $k instanceof WGMModelGetKeys )
			{
				foreach ( $k->getKeys() as $kk )
				{
					$this->logInfo( $k->getModel()->getAlias() . '.' . $kk );
				}
			}
			else
			{
				$this->logInfo( $this->getAlias() . '.' . $k );
			}
		}
	}

	/**
	 * SELECTクエリ用パラメータ生成
	 *
	 * @param mixed $keys
	 *
	 * @return array クエリパラメータ配列
	 */
	protected function makeQuery( array $keys ): array
	{
		// Entry all fields
		$fields = [];
		foreach ( $this->getJoinExternalFields() as $f )
		{
			$fields[] = $f[1] . ' AS "' . $f[0] . '"';
		}

		// Entry all joined tables
		$tables = $this->getJoinTables( $this->getTable() . ' AS ' . $this->getAlias() );
		$orders = $this->getJoinOrders( [] );
		usort( $orders, function ( $a, $b ) {
			return $a[0] == $b[0] ? 0 : ( $a[0] < $b[0] ? - 1 : 1 );
		} );

		$fieldOrders = [];
		foreach ( $orders as $o )
		{
			$fieldOrders[] = $o[1];
		}
		$orderby = count( $fieldOrders ) > 0 ? ' ORDER BY ' . implode( ',', $fieldOrders ) : '';

		$this->recs  = 0;
		$this->avars = [];

		// 条件式作成
		$wheres = $this->whereCondExpression( $keys );
		$wheres = count( $wheres ) > 0 ? ' WHERE ' . implode( ' AND ', $wheres ) : '';

		return [ implode( ',', $fields ), $tables, $wheres, $orderby, $this->offsetKeyword, $this->limitKeyword ];
	}

	/**
	 * テーブルを指定されたキーで検索する。
	 *
	 * @param string|array... キー文字列、配列
	 *
	 * @return WGMModel インスタンス
	 */
	public function select( ...$keys ): self
	{
		$keys = $this->toFlatArray( $keys );
		$this->logInfo( 'WGMModel::select( %s )', implode( ' , ', $keys ) );

		if ( $this->pager )
		{
			$count = $this->count( $keys );
			$this->pager->setTotal( $count );
			$ofs = $this->pager->offset();
			$lim = $this->pager->limit();

			$this->logInfo( 'Pager %s(rows) offset %s limit %s', $count, $ofs, $lim );
			$this->offset( $ofs, $lim );
		}

		list( $f, $t, $w, $ord, $ofs, $lim ) = $this->makeQuery( $keys );

		$q = sprintf( 'SELECT %s FROM %s%s%s%s%s;', $f, $t, $w, $ord, $ofs, $lim );
		$this->dbms->E( $q );
		$this->recs = $this->dbms->RECS();

		$joinedModels = $this->getJoinModels();

		$n = 0;
		while ( $f = $this->dbms->F() )
		{
			foreach ( $joinedModels as $joinedModel )
			{
				foreach ( $joinedModel->getFields() as $k )
				{
					$joinedModel->avars[ $n ][ $k ] = $joinedModel->fieldValue( $k, $f[ $joinedModel->aliasName . '.' . $k ], 'PHP' );
				}
			}
			$n ++;
		}
		foreach ( $joinedModels as $joinedModel )
		{
			if ( isset( $joinedModel->avars[0] ) )
			{
				foreach ( $joinedModel->getFields() as $k )
				{
					$joinedModel->setAssignedValue( $k, $joinedModel->avars[0][ $k ] );
				}
			}
			else
			{
				foreach ( $joinedModel->getFields() as $k )
				{
					$joinedModel->setAssignedValue( $k, null );
				}
			}
		}

		return $this;
	}

	/**
	 * テーブルを指定されたキーで検索し、検索された件数を返す。
	 *
	 * @param string|array... キー文字列、配列
	 *
	 * @return int 件数
	 */
	public function get(): int
	{
		return $this->select( func_get_args() )->recs;
	}

	public function check( mixed ...$args ): bool
	{
		$keys = $this->toFlatArray( $args );
		$this->logInfo( 'WGMModel::check( %s )', implode( ' , ', $keys ) );

		list( , $t, $w ) = $this->makeQuery( $keys );

		return ( $this->dbms->QQ( 'SELECT TRUE FROM %s%s;', $t, $w ) !== false );
	}

	public function count( mixed ...$args ): int
	{
		$keys = $this->toFlatArray( $args );
		$this->logInfo( 'WGMModel::count( %s )', implode( ' , ', $keys ) );

		list( , $t, $w ) = $this->makeQuery( $keys );
		list( $count ) = $this->dbms->QQ( 'SELECT COUNT(*) FROM %s%s;', $t, $w );

		return (int) $count;
	}

	/**
	 * テーブルを指定されたキーで追記する。
	 *
	 * @param string|array... キー文字列、配列
	 *
	 * @return WGMModel インスタンス
	 */
	public function insert(): self
	{
		$this->recs = 0;
		$flds       = $this->getFields();

		$dd = [];
		foreach ( $flds as $k )
		{
			$dd[ $k ] = $this->getAssignedValue( $k );
		}
		foreach ( array_merge( $this->initYmdKeys, $this->updYmdKeys ) as $ff )
		{
			$dd[ $ff ] = 'CURRENT_TIMESTAMP';
		}

		$fs = [];
		$vs = [];
		foreach ( $dd as $f => $v )
		{
			if ( ! in_array( $f, $flds ) )
			{
				continue;
			}
			$fs[] = $f;
			$vs[] = $this->fieldValue( $f, $v, 'DB' );
		}
		if ( count( $fs ) == 0 )
		{
			$q = false;
		}
		else
		{
			$q = sprintf( 'INSERT INTO %s(%s) VALUES(%s);',
				$this->tableName,
				implode( ',', $fs ),
				implode( ',', $vs )
			);
		}

		if ( $q )
		{
			$this->dbms->E( $q );
			if ( ! $this->dbms->OK() )
			{
				$this->logFatal( "Can't insert into '%s'.\n%s", $this->tableName, $q );
			}
		}

		return $this;
	}

	/**
	 * テーブルを指定されたキーで更新する。キーが存在しない場合は追加レコードが生成される。
	 *
	 * @param string|array... キー文字列、配列
	 *
	 * @return WGMModel インスタンス
	 */
	public function update( ...$args ): self
	{
		$keys = $this->toFlatArray( $args );
		$this->logInfo( 'WGMModel::update( %s )', implode( ' , ', $keys ) );

		$fields = $this->getFields();

		$this->recs = 0;

		// 条件式作成
		$wheres = $this->whereExpression( $keys );
		$wheres = count( $wheres ) > 0 ? ' WHERE ' . implode( ' AND ', $wheres ) : '';

		$q = sprintf( 'SELECT %s FROM %s%s;',
			implode( ',', $fields ),
			$this->tableName,
			$wheres
		);
		$this->dbms->Q( '%s', $q );

		if ( ( $r = $this->dbms->RECS() ) > 1 )
		{
			$this->logFatal( "Can't select the unique record from '%s' on update.\n%s", $this->tableName, $q );
		}

		$isInsert = ( $r === 0 );
		if ( $r === 1 )
		{
			$f = $this->dbms->F();
			foreach ( $fields as $k )
			{
				$this->backvars[ $k ] = $this->fieldValue( $k, $f[ $k ], 'PHP' );
			}
		}

		$dd = [];
		if ( $isInsert )
		{
			$this->logInfo( 'Insert mode' );

			foreach ( $fields as $k )
			{
				$dd[ $k ] = $this->getAssignedValue( $k );
			}
			foreach ( array_merge( $this->initYmdKeys, $this->updYmdKeys ) as $ff )
			{
				$dd[ $ff ] = 'CURRENT_TIMESTAMP';
			}

			$fs = [];
			$vs = [];
			foreach ( $dd as $f => $v )
			{
				if ( ! in_array( $f, $fields ) )
				{
					continue;
				}
				$fs[] = $f;
				$vs[] = $this->fieldValue( $f, $v, 'DB' );
			}
			if ( count( $fs ) == 0 )
			{
				$q = false;
			}
			else
			{
				$q = sprintf( 'INSERT INTO %s(%s) VALUES(%s);',
					$this->tableName,
					implode( ',', $fs ),
					implode( ',', $vs )
				);
			}
		}
		else
		{
			$this->logInfo( 'Update mode' );

			$d1 = [];

			$ws = array_intersect( array_unique( array_merge( array_keys( $this->assign ), array_keys( $this->vars ) ) ), $fields );
			foreach ( $ws as $k )
			{
				$d1[ $k ] = $this->getAssignedValue( $k );
			}
			foreach ( $d1 as $k => $v )
			{
				if ( ! $this->compareField( $k, $this->backvars[ $k ], $v ) )
				{
					$dd[ $k ] = $v;
				}
			}

			foreach ( $this->initYmdKeys as $ff )
			{
				unset( $dd[ $ff ] );
			}
			foreach ( $this->updYmdKeys as $ff )
			{
				$dd[ $ff ] = 'CURRENT_TIMESTAMP';
			}

			$ss = [];
			foreach ( $dd as $f => $v )
			{
				if ( in_array( $f, $keys ) )
				{
					continue;
				}
				if ( ! in_array( $f, $fields ) )
				{
					continue;
				}
				$ss[] = $f . '=' . $this->fieldValue( $f, $v, 'DB' );
			}
			if ( count( $ss ) == 0 )
			{
				$q = false;
			}
			else
			{
				$q = sprintf( 'UPDATE %s SET %s%s;', $this->tableName, implode( ',', $ss ), $wheres );
			}
		}

		if ( $q )
		{
			$this->dbms->E( $q );
			if ( ! $this->dbms->OK() )
			{
				$this->logFatal( "Can't update '$this->tableName'.\n$q" );
			}
		}

		return $this;
	}

	/**
	 * テーブルを指定されたキーで削除する。
	 *
	 * @param string|array... キー文字列、配列
	 *
	 * @return WGMModel インスタンス
	 */
	public function delete( ...$args ): self
	{
		$keys = $this->toFlatArray( $args );
		$this->dumpKeys( $keys );
		$this->logInfo( 'WGMModel::delete( %s )', implode( ' , ', $keys ) );

		$this->recs = 0;

		// 条件式作成
		$wheres = $this->whereExpression( $keys );
		$wheres = count( $wheres ) > 0 ? ' WHERE ' . implode( ' AND ', $wheres ) : '';

		$q = sprintf( 'DELETE FROM %s%s;',
			$this->tableName,
			$wheres
		);
		$this->dbms->E( $q );
		if ( ! $this->dbms->OK() )
		{
			$this->logFatal( "Can't delete from '%s'.\n%s", $this->tableName, $q );
		}

		return $this;
	}

	public function result(): int
	{
		return count( $this->avars );
	}

	public function setVars( $vars = [] ): self
	{
		$this->vars = $vars;
		foreach ( $vars as $k => $v )
		{
			$this->setAssignedValue( $k, $v );
		}

		return $this;
	}

	public function getVars( $vars = [] ): int
	{
		return $this->setVars( $vars )->get( array_keys( $vars ) );
	}

	public function getJoinedAvars(): array
	{
		$r  = [];
		$jm = $this->getJoinModels();
		$n  = $this->result();
		foreach ( $jm as $m )
		{
			$tn = $m->getTable();
			for ( $i = 0; $i < $n; $i ++ )
			{
				$r[ $i ][ $tn ] = $m->avars[ $i ];
			}
		}

		return $r;
	}
}
