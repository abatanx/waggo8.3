<?php
/**
 * waggo8
 * @copyright 2013-2021 CIEL, K.K., project waggo.
 * @license MIT
 */
declare( strict_types=1 );

require_once __DIR__ . '/../../waggo.php';
require_once __DIR__ . '/../v8/WGV8Object.php';
require_once __DIR__ . '/WGMModelFilter.php';
require_once __DIR__ . '/WGMVarsObject.php';

global $WGMModelID;
$WGMModelID = [];

//
class WGMModelGetKeys
{
	private array $keys;
	private WGMModel $model;

	public function __construct( WGMModel $model )
	{
		$this->keys  = [];
		$this->model = $model;
	}

	/**
	 * @return WGMModel
	 */
	public function getModel(): WGMModel
	{
		return $this->model;
	}

	public function setKeys( array $keys )
	{
		$this->keys = $keys;
	}

	public function getKeys(): array
	{
		return $this->keys;
	}
}

/**
 * SQL基底モデル(O/Rマッピング用基底モデル)
 */
class WGMModel
{
	const N = 0, S = 1, B = 2, T = 3, D = 4, P = 5;
	const JNULL = 0, JINNER = 1, JLEFT = 2, JRIGHT = 3;

	public array $uniqueids;
	public array $avars, $vars;
	public array $fields;
	public WGDBMS $dbms;

	protected bool $isModelDebug;
	public array $assign;
	public string $tablename;
	public string $alias;
	public array $backvars, $initymds, $updymds;
	public int $recs;
	protected WGMModelFilter $defaultfilter;
	protected array $conditions;

	/**
	 * @var WGV8BasicPagination
	 */
	protected WGV8BasicPagination $pager;

	protected array $joins;

	// SQL dependency
	protected stdClass $depconf;

	// SQL params
	protected array $a_orderby;
	protected int $p_order;
	protected string $s_offset, $s_limit;

	public function __construct( $tablename, $dbms = null )
	{
		global $WGMModelID;

		$this->uniqueids = [];

		$kid = $tablename[0];
		if ( empty( $WGMModelID[ $kid ] ) )
		{
			$WGMModelID[ $kid ] = 1;
		}
		else
		{
			$WGMModelID[ $kid ] ++;
		}
		$id = $WGMModelID[ $kid ];

		$this->alias = $kid . $id;
		$this->dbms  = ( is_null( $dbms ) ) ? _QC() : $dbms;

		$this->initDBMSConf();

		$this->defaultfilter = new WGMModelFILTER();

		$this->tablename = $tablename;
		$this->fields    = [];
		$this->assign    = [];
		$this->vars      = [];
		$this->avars     = [];
		$this->backvars  = [];
		$this->joins     = [];
		$this->initFields( $tablename );
		$this->recs         = 0;
		$this->isModelDebug = WG_MODELDEBUG;
		$this->conditions   = [];
		$this->initymds     = [];
		$this->updymds      = [];
		$this->a_orderby    = [];
		$this->p_order      = 1 << 31;
	}

	protected function initDBMSConf()
	{
		$this->depconf = new stdClass();
		if ( $this->dbms instanceof WGDBMSPostgreSQL )
		{
			$this->depconf->N = '/^(int|smallint|bigint)/';
			$this->depconf->T = '/^(date|timestamp)/';
			$this->depconf->S = '/^(char|text|time|varchar|json)/';
			$this->depconf->D = '/^(double|real|numeric)/';
			$this->depconf->B = '/^bool/';
			$this->depconf->P = '/^point/';

			$this->depconf->BOOL_TRUE = 't';
		}
		else if ( $this->dbms instanceof WGDBMSMySQL )
		{
			$this->depconf->N = '/^(int|smallint)/';
			$this->depconf->T = '/^(date|timestamp)/';
			$this->depconf->S = '/^(char|text|time|varchar|json)/';
			$this->depconf->D = '/^(double|real|numeric)/';
			$this->depconf->B = '/^tinyint\(1\)/';
			$this->depconf->P = '/^point/';

			$this->depconf->BOOL_TRUE = '1';
		}
		else
		{
			$this->logFatal( "Unsupported DBMS" );
		}
	}

	protected function logInfo( string $msg ): void
	{
		if ( $this->isModelDebug )
		{
			wg_log_write( WGLOG_INFO, $msg );
		}
	}

	protected function logInfoDump( string $msg ): void
	{
		if ( $this->isModelDebug )
		{
			wg_log_write( WGLOG_INFO, $msg );
		}
	}

	protected function logWarning( string $msg ): void
	{
		wg_log_write( WGLOG_WARNING, $msg );
	}

	protected function logError( string $msg ): void
	{
		wg_log_write( WGLOG_ERROR, $msg );
	}

	protected function logFatal( string $msg ): void
	{
		wg_log_write( WGLOG_FATAL, $msg );
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
		if ( preg_match( $this->depconf->N, $fieldType ) )
		{
			return self::N;
		}
		else if ( preg_match( $this->depconf->T, $fieldType ) )
		{
			return self::T;
		}
		else if ( preg_match( $this->depconf->S, $fieldType ) )
		{
			return self::S;
		}
		else if ( preg_match( $this->depconf->D, $fieldType ) )
		{
			return self::D;
		}
		else if ( preg_match( $this->depconf->B, $fieldType ) )
		{
			return self::B;
		}
		else if ( preg_match( $this->depconf->P, $fieldType ) )
		{
			return self::P;
		}
		else
		{
			return false;
		}
	}

	/** @noinspection PhpInconsistentReturnPointsInspection */
	protected function getOID( string $tablename ): array
	{
		if ( $this->dbms instanceof WGDBMSPostgreSQL )
		{
			list( $oid, $nspname, $relname ) =
				$this->dbms->QQ(
					'SELECT c.oid,n.nspname,c.relname FROM pg_catalog.pg_class c ' .
					'LEFT JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace ' .
					'WHERE pg_catalog.pg_table_is_visible(c.oid) ' .
					'AND c.relname=%s;', $this->dbms->S( $tablename ) );

			return [ $oid, $nspname, $relname ];
		}
		else if ( $this->dbms instanceof WGDBMSMySQL )
		{
			return [ $tablename, $tablename, $tablename ];
		}
		else
		{
			$this->logFatal( "Unrecognized DBMS type" );
		}
	}

	protected function initFields( string $tablename ): void
	{
		if ( $this->dbms instanceof WGDBMSPostgreSQL )
		{
			list( $oid, , ) = $this->getOID( $tablename );
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
					$this->logFatal( "Unrecognized field type, $format_type on PostgreSQL/WGMModel" );
				}

				$this->fields[ $name ] = [
					$type,
					$format_type,
					( $notnull == 't' ),
					$this->getAlias() . "." . $name
				];
				$this->logInfo( "Fields[$name] = [Type:$type] [Format:$format_type] [NotNull:$notnull] [Func:$name]" );
			}
		}
		else if ( $this->dbms instanceof WGDBMSMySQL )
		{
			$this->dbms->Q( "DESCRIBE %s", $tablename );

			foreach ( $this->dbms->FALL() as $f )
			{
				list( $name, $format_type, $null, , , ) = $f;
				$type = $this->getFieldTypeFromFormat( $format_type );
				if ( $type === false )
				{
					$this->logFatal( "Unrecognized field type, $format_type on MySQL/WGMModel" );
				}

				$this->fields[ $name ] = array(
					$type,
					$format_type,
					( $null == "YES" ),
					$this->getAlias() . "." . $name
				);
				$this->logInfo( "Fields[$name] = [Type:$type] [Format:$format_type] [Null:$null] [Func:$name]" );
			}
		}
		else
		{
			$this->logFatal( "Unrecognized DBMS type" );
		}
	}

	/** @noinspection PhpInconsistentReturnPointsInspection */
	protected function initFieldsPrimaryKey( string $tablename ): array|false
	{
		if ( $this->dbms instanceof WGDBMSPostgreSQL )
		{
			list( $oid, , ) = $this->getOID( $tablename );
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
				$pks[] = $f["attname"];
			}

			return $pks;
		}
		else if ( $this->dbms instanceof WGDBMSMySQL )
		{
			$pks = [];
			$this->dbms->Q( "DESCRIBE %s", $tablename );
			foreach ( $this->dbms->FALL() as $f )
			{
				list( $name, , , $key, , ) = $f;
				if ( $key == "PRI" )
				{
					$pks[] = $name;
				}

				return $pks;
			}
		}
		else
		{
			$this->logFatal( "Unrecognized DBMS type" );
		}
	}

	public function setField( string $keyField, string $formatType, $func ): void
	{
		$type = $this->getFieldTypeFromFormat( $formatType );
		if ( $type === false )
		{
			$this->logFatal( "Unrecognized field type, $formatType" );
		}
		$this->fields[ $keyField ] = array( $type, $formatType, false, $this->expansion( $func ) );
	}

	public function getTable(): string
	{
		return $this->tablename;
	}

	public function getAlias(): string
	{
		return $this->alias;
	}

	public function getFields(): array
	{
		return array_keys( $this->fields );
	}

	public function getFieldType( $keyField ): string|false
	{
		return ! empty( $this->fields[ $keyField ][0] ) ? $this->fields[ $keyField ][0] : false;
	}

	public function getFieldFormat( $keyField ): string|false
	{
		return ! empty( $this->fields[ $keyField ][1] ) ? $this->fields[ $keyField ][1] : false;
	}

	public function IsAllowNullField( $keyField ): bool
	{
		return ! empty( $this->fields[ $keyField ][2] ) ? $this->fields[ $keyField ][2] : false;
	}

	public function getPrimaryKeys(): array|false
	{
		return $this->initFieldsPrimaryKey( $this->tablename );
	}

	public function expansion( string $exp ): string
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

		$g = function ( $m ) {
			return $this->getAlias() . "." . $m[1];
		};

		return implode( '', array_map( function ( $v ) use ( $g ) {
			return $v[0] === 1 ? preg_replace_callback( '/{(\w+?)}/', $g, $v[1] ) : $v[1];
		}, $r ) );
	}

	public function setAutoTimestamp( $initymds = [ 'initymd' ], $updymds = [ 'updymd' ] ): self
	{
		if ( ! is_array( $initymds ) || ! is_array( $updymds ) )
		{
			$this->logFatal( "setAutoTimestamp is not an array" );
		}
		$this->initymds = $initymds;
		$this->updymds  = $updymds;

		return $this;
	}

	public function getRecs(): int
	{
		return $this->recs;
	}

	public function setFilter( string $keyField, WGMModelFilter $modelFilter ): self
	{
		$this->assign[ $keyField ]["filter"] = $modelFilter;

		return $this;
	}

	public function assign( string $keyField, WGV8Object $view, WGMModelFilter|null $modelFilter = null ): self
	{
		if ( ! isset( $this->fields[ $keyField ] ) )
		{
			$this->logFatal( "'$keyField' not found." );
		}

		$this->assign[ $keyField ]['viewobj'] = $view;
		$this->assign[ $keyField ]['filter']  = ( $modelFilter instanceof WGMModelFILTER ) ? $modelFilter : $this->defaultfilter;

		return $this;
	}

	public function release( string $keyField ): self
	{
		unset( $this->assign[ $keyField ] );

		return $this;
	}

	protected function checkNullField( string $keyField, string|null $v ): self
	{
		if ( $this->fields[ $keyField ][2] && ( strtolower( $v ) === 'null' || is_null( $v ) ) )
		{
			$this->logFatal( "Field '$keyField' does not allow NULL." );
		}

		return $this;
	}

	protected function checkNull( array $d, $isAllFields = false ): self
	{
		$scan = ( $isAllFields ) ? $this->fields : $d;
		foreach ( $scan as $k => $v )
		{
			$this->checkNullField( $k, $v );
		}

		return $this;
	}

	protected function posValue( string $pos ): array|null
	{
		if ( preg_match( '/\(([\-0-9\.]+),([\-0-9\.]+)\)/', $pos, $m ) )
		{
			return array( $m[1], $m[2] );
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

		$anl = ! $this->fields[ $keyField ][2];
		$v   = null;
		switch ( $this->fields[ $keyField ][0] )
		{
			case self::N:
				$v = ( $direction === 'DB' ) ? $this->dbms->N( $value, $anl ) : (int) $value;
				break;
			case self::S:
				$v = ( $direction === 'DB' ) ? $this->dbms->S( $value, $anl ) : $value;
				break;
			case self::B:
				$v = ( $direction === 'DB' ) ? $this->dbms->B( $value, $anl ) : ( $value == $this->depconf->BOOL_TRUE );
				break;
			case self::T:
				$v = ( $direction === 'DB' ) ? $this->dbms->T( $value, $anl ) : $value;
				break;
			case self::D:
				$v = ( $direction === 'DB' ) ? $this->dbms->D( $value, $anl ) : (double) $value;
				break;
			case self::P:
				$v = ( $direction === 'DB' ) ? $this->dbms->P( $value, $anl ) : $this->posValue( $value );
				break;
			default:
				$this->logFatal( "Field '$keyField' conversion failed." );
		}

		$this->logInfo( "[$this->tablename] $this->alias.$keyField src[$value] [to $direction] dst[$v]" );

		if ( $direction === 'DB' && $this->fields[ $keyField ][2] && $v === 'null' )
		{
			$this->logFatal( "Field '$keyField' does not allow NULL." );
		}

		return $v;
	}

	/** @noinspection PhpInconsistentReturnPointsInspection */
	protected function compareField( string $keyField, mixed $v1, mixed $v2 ): bool
	{
		switch ( $this->fields[ $keyField ][0] )
		{
			case self::N:
				return ( $v1 == $v2 );
			case self::S:
			case self::B:
			case self::D:
				return ( $v1 === $v2 );
			case self::T:
				return ( wg_timediff_second( $v1, $v2 ) === 0 );
			case self::P:
				return ( $v1[0] == $v2[0] && $v1[1] == $v2[1] );
		}
		$this->logFatal( "Unrecognized field type, '$keyField'." );
	}

	protected function setAssignedValue( string $keyField, mixed $value ): self
	{
		if ( isset( $this->assign[ $keyField ]["viewobj"] ) )
		{
			$this->assign[ $keyField ]["filter"]->modelToView( $this->assign[ $keyField ]["viewobj"], $this->assign[ $keyField ]["filter"]->output( $value ) );
		}
		else
		{
			$this->vars[ $keyField ] = $value;
		}

		return $this;
	}

	protected function getAssignedValue( string $keyField ): mixed
	{
		if ( isset( $this->assign[ $keyField ]['viewobj'] ) && ! $this->assign[ $keyField ]['viewobj']->isShowOnly() )
		{
			return $this->assign[ $keyField ]['filter']->input( $this->assign[ $keyField ]['filter']->viewToModel( $this->assign[ $keyField ]['viewobj'] ) );
		}
		else
		{
			return $this->vars[ $keyField ];
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
		foreach ( $this->avars as $av )
		{
			$result[] = $av[ $dataField ];
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
		foreach ( $this->avars as $av )
		{
			$result[ $av[ $keyField ] ] = $av[ $dataField ];
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
		if ( ! isset( $this->uniqueids['cond'] ) )
		{
			$this->uniqueids['cond'] = 0;
		}

		$conditionId = sprintf( 'cond-%d', $this->uniqueids['cond'] ++ );

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
		$this->logInfo( "**** ORDER BY" );
		$keys = $this->toFlatArray( $args );
		$this->dumpKeys( $keys );
		$this->logInfo( "****" );

		$orders = [];
		foreach ( $keys as $key )
		{
			if ( is_int( $key ) )
			{
				$this->p_order = $key;
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

		$this->a_orderby = $orders;

		return $this;
	}

	public function offset( int|null $offset = null, int|null $limit = null ): self
	{
		$this->logInfo( "**** OFFSET LIMIT ($offset) ($limit)" );
		if ( wg_is_dbms_postgresql() )
		{
			$this->s_offset = ! is_null( $offset ) ? " OFFSET $offset" : '';
			$this->s_limit  = ! is_null( $limit ) ? " LIMIT $limit" : '';
		}
		else if ( wg_is_dbms_mysql() || wg_is_dbms_mariadb() )
		{
			if ( ! is_null( $limit ) )
			{
				$this->s_offset = '';
				$this->s_limit  = ! is_null( $offset ) ? " LIMIT $offset,$limit" : "LIMIT $limit";
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
				$wheres[] = "$this->alias.$k=$v";
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
			$wheres[] = "$k=$v";
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
			$fields[] = array( $this->getAlias() . '.' . $f, $this->fields[ $f ][3] );
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
					$this->logFatal( "Joined LEFT, no '$l' field." );
				}
				if ( ! in_array( $r, $jm[1]->getFields() ) )
				{
					$this->logFatal( "Joined RIGHT, no '$r' field." );
				}
				$on[] = $this->getAlias() . ".$l=" . $jm[1]->getAlias() . ".$r";
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
			$base = '(' . $base . " $j " . $jm[1]->getTable() . ' AS ' . $jm[1]->getAlias() . " ON $on)";
			$base = $jm[1]->getJoinTables( $base );
		}

		return $base;
	}

	public function getJoinOrders( array $orders ): array
	{
		/**
		 * @var WGMModel[] $jm
		 */
		if ( count( $this->a_orderby ) > 0 )
		{
			$orders[] = [ $this->p_order, $this->a_orderby ];
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
		// フィールド結合
		$fields = [];
		foreach ( $this->getJoinExternalFields() as $f )
		{
			$fields[] = "$f[1] AS \"$f[0]\"";
		}

		// テーブル結合
		$tables = $this->getJoinTables( $this->getTable() . ' AS ' . $this->getAlias() );
		$orders = $this->getJoinOrders( [] );
		$ford   = [];
		usort( $orders, function ( $a, $b ) {
			return $a[0] == $b[0] ? 0 : ( $a[0] < $b[0] ? - 1 : 1 );
		} );
		foreach ( $orders as $v )
		{
			$ford += $v[1];
		}
		$orderby = count( $ford ) > 0 ? ' ORDER BY ' . implode( ',', $ford ) : '';

		$this->recs  = 0;
		$this->avars = [];

		// 条件式作成
		$wheres = $this->whereCondExpression( $keys );
		$wheres = count( $wheres ) > 0 ? ' WHERE ' . implode( ' AND ', $wheres ) : "";

		return array( implode( ',', $fields ), $tables, $wheres, $orderby, $this->s_offset, $this->s_limit );
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
		// キー解析
		$this->logInfo( '**** GET' );
		$keys = $this->toFlatArray( $keys );
		$this->dumpKeys( $keys );
		$this->logInfo( '****' );

		// ページャーがモデルにアサインされている
		if ( $this->pager )
		{
			$count = $this->count( $keys );
			$this->pager->setTotal( $count );
			$ofs = $this->pager->offset();
			$lim = $this->pager->limit();
			$this->logInfo( "**** PAGER $count(rows) offset $ofs limit $lim" );
			$this->offset( $ofs, $lim );
		}

		// クエリ実行
		list( $f, $t, $w, $ord, $ofs, $lim ) = $this->makeQuery( $keys );

		$q = sprintf( "SELECT %s FROM %s%s%s%s%s;", $f, $t, $w, $ord, $ofs, $lim );
		$this->dbms->E( $q );
		$this->recs = $this->dbms->RECS();

		// データ振分用モデル列挙
		$m = $this->getJoinModels();

		// 結合先
		$n = 0;
		while ( $f = $this->dbms->F() )
		{
			foreach ( $m as $jm )
			{
				foreach ( $jm->getFields() as $k )
				{
					$jm->avars[ $n ][ $k ] = $jm->fieldValue( $k, $f["{$jm->alias}.{$k}"], "PHP" );
				}
			}
			$n ++;
		}
		foreach ( $m as $jm )
		{
			if ( isset( $jm->avars[0] ) )
			{
				foreach ( $jm->getFields() as $k )
				{
					$jm->setAssignedValue( $k, $jm->avars[0][ $k ] );
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
	public function get()
	{
		return $this->select( func_get_args() )->recs;
	}

	public function check()
	{
		// キー解析
		$this->logInfo( "**** CHECK\n" );
		$keys = $this->toFlatArray( func_get_args() );
		$this->dumpKeys( $keys );
		$this->logInfo( "****\n" );

		// クエリ作成
		list( , $t, $w ) = $this->makeQuery( $keys );

		return ( $this->dbms->QQ( "SELECT true FROM %s%s;", $t, $w ) != false );
	}

	public function count()
	{
		// キー解析
		$this->logInfo( "**** COUNT\n" );
		$keys = $this->toFlatArray( func_get_args() );
		$this->dumpKeys( $keys );
		$this->logInfo( "****\n" );

		// クエリ作成
		list( , $t, $w ) = $this->makeQuery( $keys );
		list( $count ) = $this->dbms->QQ( "SELECT count(*) FROM %s%s;", $t, $w );

		return (int) $count;
	}

	/**
	 * テーブルを指定されたキーで追記する。
	 *
	 * @param string|array... キー文字列、配列
	 *
	 * @return WGMModel インスタンス
	 */
	public function insert()
	{
		$this->logInfo( "**** INSERT\n" );
		$this->recs = 0;

		$flds = $this->getFields();

		$dd = [];
		foreach ( $flds as $k )
		{
			$dd[ $k ] = $this->getAssignedValue( $k );
		}
		foreach ( array_merge( $this->initymds, $this->updymds ) as $ff )
		{
			$dd[ $ff ] = "CURRENT_TIMESTAMP";
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
			$vs[] = $this->fieldValue( $f, $v, "DB" );
		}
		if ( count( $fs ) == 0 )
		{
			$q = false;
		}
		else
		{
			$q = sprintf( "INSERT INTO %s(%s) VALUES(%s);", $this->tablename, implode( ",", $fs ), implode( ",", $vs ) );
		}

		if ( $q )
		{
			$this->dbms->E( $q );
			if ( ! $this->dbms->OK() )
			{
				$this->logFatal( "Can't insert into '{$this->tablename}'.\n{$q}" );
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
	public function update()
	{
		$this->logInfo( "**** UPDATE\n" );
		$keys = $this->toFlatArray( func_get_args() );
		$this->dumpKeys( $keys );
		$flds = $this->getFields();
		$this->logInfo( "****\n" );

		$this->recs = 0;

		// 条件式作成
		$wheres = $this->whereExpression( $keys );
		$wheres = count( $wheres ) > 0 ? " WHERE " . implode( " AND ", $wheres ) : "";

		$q = sprintf( "SELECT %s FROM %s%s;", implode( ",", $flds ), $this->tablename, $wheres );
		$this->dbms->Q( "%s", $q );

		if ( ( $r = $this->dbms->RECS() ) > 1 )
		{
			$this->logFatal( "Can't select the unique record from '{$this->tablename}' on update.\n{$q}" );
		}

		$is_insert = ( $r == 0 );
		if ( $r == 1 )
		{
			$f = $this->dbms->F();
			foreach ( $flds as $k )
			{
				$this->backvars[ $k ] = $this->fieldValue( $k, $f[ $k ], "PHP" );
			}
		}

		if ( $is_insert )
		{
			$this->logInfo( "---> INSERT MODE\n" );

			$dd = [];
			foreach ( $flds as $k )
			{
				$dd[ $k ] = $this->getAssignedValue( $k );
			}
			foreach ( array_merge( $this->initymds, $this->updymds ) as $ff )
			{
				$dd[ $ff ] = "CURRENT_TIMESTAMP";
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
				$vs[] = $this->fieldValue( $f, $v, "DB" );
			}
			if ( count( $fs ) == 0 )
			{
				$q = false;
			}
			else
			{
				$q = sprintf( "INSERT INTO %s(%s) VALUES(%s);", $this->tablename, implode( ",", $fs ), implode( ",", $vs ) );
			}
		}
		else
		{
			$this->logInfo( "---> UPDATE MODE\n" );

			$d1 = [];
			$dd = [];

			$ws = array_intersect( array_unique( array_merge( array_keys( $this->assign ), array_keys( $this->vars ) ) ), $flds );
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

			foreach ( $this->initymds as $ff )
			{
				unset( $dd[ $ff ] );
			}
			foreach ( $this->updymds as $ff )
			{
				$dd[ $ff ] = "CURRENT_TIMESTAMP";
			}

			$ss = [];
			foreach ( $dd as $f => $v )
			{
				if ( in_array( $f, $keys ) )
				{
					continue;
				}
				if ( ! in_array( $f, $flds ) )
				{
					continue;
				}
				$ss[] = "{$f}=" . $this->fieldValue( $f, $v, "DB" );
			}
			if ( count( $ss ) == 0 )
			{
				$q = false;
			}
			else
			{
				$q = sprintf( "UPDATE %s SET %s%s;", $this->tablename, implode( ",", $ss ), $wheres );
			}
		}

		if ( $q )
		{
			$this->dbms->E( $q );
			if ( ! $this->dbms->OK() )
			{
				$this->logFatal( "Can't update '{$this->tablename}'.\n{$q}" );
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
	public function delete()
	{
		$this->logInfo( "**** DELETE\n" );
		$keys = $this->toFlatArray( func_get_args() );
		$this->dumpKeys( $keys );
		$this->logInfo( "****\n" );

		$this->recs = 0;

		// 条件式作成
		$wheres = $this->whereExpression( $keys );
		$wheres = count( $wheres ) > 0 ? " WHERE " . implode( " AND ", $wheres ) : "";

		$q = sprintf( "DELETE FROM %s%s;", $this->tablename, $wheres );
		$this->dbms->E( $q );
		if ( ! $this->dbms->OK() )
		{
			$this->logFatal( "Can't delete from '{$this->tablename}'.\n{$q}" );
		}

		return $this;
	}

	public function result()
	{
		return count( $this->avars );
	}

	public function setVars( $vars = [] )
	{
		$this->vars = $vars;
		foreach ( $vars as $k => $v )
		{
			$this->setAssignedValue( $k, $v );
		}

		return $this;
	}

	public function getVars( $vars = [] )
	{
		return $this->setVars( $vars )->get( array_keys( $vars ) );
	}

	public function getJoinedAvars()
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
