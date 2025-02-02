<?php
/**
 * waggo8.3
 * @copyright 2013-2024 CIEL, K.K., project waggo.
 * @license MIT
 */

require_once __DIR__ . '/../core/check.php';

/**
 * @param int $v
 * @param int $b
 *
 * @return int
 * @internal
 */
function __datetime_bval( int $v, int $b ): int
{
	return ( $b + ( $v % $b ) ) % $b;
}

/**
 * 0001年01月01日からの日数を計算する。
 *
 * @param int $yy 西暦年
 * @param int $mm 月
 * @param int $dd 日
 *
 * @return int 日数
 * @internal
 */
function __datetime_AD( int $yy, int $mm, int $dd ): int
{
	if ( $mm <= 2 )
	{
		$yy --;
		$mm += 12;
	}

	return ( $yy * 365
			 + (int) ( $yy / 4 )
			 - (int) ( $yy / 100 )
			 + (int) ( $yy / 400 )
			 + (int) ( ( 13 * $mm + 8 ) / 5 )
			 + ( 28 * $mm ) - 34 - 365 + $dd );
}

/**
 * 現在日時を取得する。
 * @return string 年/月/日 時:分:秒
 */
function wg_now(): string
{
	return date( "Y-m-d H:i:s" );
}

/**
 * 曜日を取得する。
 *
 * @param int $yy 西暦年(1..2100)
 * @param int $mm 月。
 * @param int $dd 日。
 *
 * @return int|false 曜日(0:Sun..6:Sat)、失敗した場合はFalseを返す。
 */
function wg_dayofweekymd( int $yy, int $mm, int $dd ): int|false
{
	if ( $mm < 3 )
	{
		$yy --;
		$mm += 12;
	}

	return ( ( $yy + (int) ( $yy / 4 ) - (int) ( $yy / 100 ) + (int) ( $yy / 400 ) + (int) ( ( 13 * $mm + 8 ) / 5 ) + $dd ) % 7 );
}

/**
 * 曜日を取得する。
 *
 * @param string $ymd タイムスタンプ文字列。
 *
 * @return int|false 曜日(0:Sun..6:Sat)、失敗した場合はFalseを返す。
 */
function wg_dayofweek( string $ymd ): int|false
{
	if ( ( $dd = wg_split_datetime( $ymd ) ) === false )
	{
		return false;
	}

	return wg_dayofweekymd( $dd["yy"], $dd["mm"], $dd["dd"] );
}

/**
 * 指定年月の月末日を計算する。
 *
 * @param int $yy 西暦年。
 * @param int $mm 月。
 *
 * @return int|false 計算ができた場合日付(1〜31)を返し、計算不可能だった場合Falseを返す。
 */
function wg_finalday( int $yy, int $mm ): int|false
{
	if ( $mm < 1 || $mm > 12 )
	{
		return false;
	}
	$tt = array(
		31,
		( ( $yy % 4 == 0 ) && ( $yy % 100 != 0 || $yy % 400 == 0 ) ) ? 29 : 28,
		31,
		30,
		31,
		30,
		31,
		31,
		30,
		31,
		30,
		31
	);

	return $tt[ $mm - 1 ];
}

/**
 * タイムスタンプ配列の内容アップデート
 *
 * @param array &$dd タイムスタンプ配列
 *
 * @see wg_check_datetime
 * @internal
 */
function wg_update_datetime( array &$dd )
{
	$ww              = array( "日", "月", "火", "水", "木", "金", "土" );
	$dd["date"]      = sprintf( "%04d-%02d-%02d", $dd["yy"], $dd["mm"], $dd["dd"] );
	$dd["time"]      = sprintf( "%02d:%02d:%02d", $dd["hh"], $dd["nn"], $dd["ss"] );
	$dd["timehhnn"]  = sprintf( "%02d:%02d", $dd["hh"], $dd["nn"] );
	$dd["datetime"]  = "$dd[date] $dd[time]";
	$dd["dayofweek"] = wg_dayofweekymd( $dd["yy"], $dd["mm"], $dd["dd"] );
	$dd["week"]      = $ww[ $dd["dayofweek"] ];
}

/**
 * 日付・時間チェック及び西暦変換を行う。
 *
 * @param int $g 元号(0:西暦,1:明治,2:大正,3:昭和,4:平成,5:令和)
 * @param int $yy 年(1..2100)
 * @param int $mm 月(1..12)
 * @param int $dd 日(1..31)
 * @param int $hh 時(0..23)
 * @param int $nn 分(0..59)
 * @param int $ss 秒(0..59)
 *
 * @return array|false 成功した場合、連想配列を返す。それ以外の場合Falseを返す。
 * @return int $ret["g"]=>0
 * @return int $ret["yy"]=>西暦年(1800..2100)
 * @return int $ret["mm"]=>月(1..12)
 * @return int $ret["dd"]=>日(1..31)
 * @return int $ret["hh"]=>時(0..23)
 * @return int $ret["nn"]=>分(0..59)
 * @return int $ret["ss"]=>秒(0..59)
 */
function wg_check_datetime( int $g, int $yy, int $mm, int $dd, int $hh = 0, int $nn = 0, int $ss = 0 ): array|false
{
	$dd = [ "g" => $g, "yy" => $yy, "mm" => $mm, "dd" => $dd, "hh" => $hh, "nn" => $nn, "ss" => $ss ];
	$mm = [
		"g"  => [ 0, 4 ],
		"yy" => [ 1, 2100 ],
		"mm" => [ 1, 12 ],
		"dd" => [ 1, 31 ],
		"hh" => [ 0, 23 ],
		"nn" => [ 0, 59 ],
		"ss" => [ 0, 59 ]
	];
	$gm = array(
		0 => [ 18000101, 21001231, 0 ], //syymmdd,eyymmdd,yydelta
		1 => [ 10908, 450729, 1867 ],
		2 => [ 10730, 151214, 1911 ],
		3 => [ 11225, 640107, 1925 ],
		4 => [ 10108, 310430, 1988 ],
		5 => [ 10501, 991231, 2019 ]
	);

	foreach ( $dd as $k => $d )
	{
		if ( ! is_numeric( $d ) || $d < $mm[ $k ][0] || $d > $mm[ $k ][1] )
		{
			return false;
		}
	}

	$chk = $dd["yy"] * 10000 + $dd["mm"] * 100 + $dd["dd"];
	if ( $chk < $gm[ $dd["g"] ][0] || $chk > $gm[ $dd["g"] ][1] )
	{
		return false;
	}

	$dd["yy"] += $gm[ $dd["g"] ][2];
	$dd["g"]  = 0;
	if ( ! checkdate( $dd["mm"], $dd["dd"], $dd["yy"] ) )
	{
		return false;
	}
	wg_update_datetime( $dd );

	return $dd;
}

/**
 * タイムスタンプ文字列を、タイムスタンプ配列に変換する。
 *
 * @param string $ymdhns タイムスタンプ文字列
 * @param bool $is_check Trueの場合タイムスタンプの正規性を確認する。
 *
 * @return array|false タイムスタンプ配列
 * @see wg_check_datetime
 */
function wg_split_datetime( string $ymdhns, bool $is_check = true ): array|false
{
	$ymdhns = wg_toank( $ymdhns );
	$ss     = preg_split( '/\s+/', $ymdhns );
	$d      = array( "g" => 0, "yy" => 0, "mm" => 0, "dd" => 0, "hh" => 0, "nn" => 0, "ss" => 0 );
	foreach ( $ss as $s )
	{
		$s = trim( $s ); // 念のためTRIMするよ
		if ( preg_match( '/:/', $s ) )
		{
			$r = wg_split_time( $s );
			if ( $r != false )
			{
				foreach ( $r as $k => $v )
				{
					$d[ $k ] = $v;
				}
			}
		}
		else if ( preg_match( '/[\/\-\.]/', $s ) )
		{
			$r = wg_split_date( $s );
			if ( $r != false )
			{
				foreach ( $r as $k => $v )
				{
					$d[ $k ] = $v;
				}
			}
		}
	}

	return ( $is_check ) ? wg_check_datetime( $d["g"], $d["yy"], $d["mm"], $d["dd"], $d["hh"], $d["nn"], $d["ss"] ) : $d;
}

/**
 * 日付文字列を、日付配列に変換する。
 * ただし、不正(存在しないなど)な日付であっても、エラーチェックはしない。
 *
 * @param string $ymd 日付文字列
 *
 * @return array|false 変換できた場合は日付配列、できなかった場合はfalseを返す
 */
function wg_split_date( string $ymd ): array|false
{
	$gt = array(
		"m"  => 1,
		"t"  => 2,
		"s"  => 3,
		"h"  => 4,
		"r"  => 5,
		"M"  => 1,
		"T"  => 2,
		"S"  => 3,
		"H"  => 4,
		"R"  => 5,
		"明治" => 1,
		"大正" => 2,
		"昭和" => 3,
		"平成" => 4,
		"令和" => 5,
		"明"  => 1,
		"大"  => 2,
		"昭"  => 3,
		"平"  => 4,
		"令"  => 5
	);
	$d  = array( "g" => 0, "yy" => 0, "mm" => 0, "dd" => 0 );
	if ( preg_match( '/^(\d+)[\/\-\.](\d+)[\/\-\.](\d+)$/', $ymd, $match ) )
	{
		$d["g"]  = 0;
		$d["yy"] = (int) $match[1];
		$d["mm"] = (int) $match[2];
		$d["dd"] = (int) $match[3];
	}
	else if ( preg_match( '/^(\d+)[\/\-\.](\d+)$/', $ymd, $match ) )
	{
		if ( $match[1] >= 1 && $match[1] <= 12 )
		{
			$d["g"]  = 0;
			$d["yy"] = (int) date( "Y" );
			$d["mm"] = (int) $match[1];
			$d["dd"] = (int) $match[2];
		}
		else
		{
			$d["g"]  = 0;
			$d["yy"] = (int) $match[1];
			$d["mm"] = (int) $match[2];
			$d["dd"] = 1;
		}
	}
	else if ( preg_match( '/^(\d+)$/', $ymd, $match ) )
	{
		$d["g"]  = 0;
		$d["yy"] = (int) date( "Y" );
		$d["mm"] = (int) date( "m" );
		$d["dd"] = (int) $match[1];
	}
	else
	{
		return false;
	}

	return $d;
}

/**
 * 時間文字列を、時間配列に変換する。
 * ただし、不正(存在しないなど)な時刻であっても、エラーチェックはしない。
 *
 * @param string $hns 時間文字列
 *
 * @return array|false 変換できた場合は時間配列、できなかった場合はfalseを返す
 */
function wg_split_time( string $hns ): array|false
{
	$d = array( "hh" => 0, "nn" => 0, "ss" => 0 );
	if ( preg_match( '/^(\d+):(\d+):(\d+)\..+$/', $hns, $match ) )
	{
		$d["hh"] = (int) $match[1];
		$d["nn"] = (int) $match[2];
		$d["ss"] = (int) $match[3];
	}
	else if ( preg_match( '/^(\d+):(\d+):(\d+)$/', $hns, $match ) )
	{
		$d["hh"] = (int) $match[1];
		$d["nn"] = (int) $match[2];
		$d["ss"] = (int) $match[3];
	}
	else if ( preg_match( '/^(\d+):(\d+)$/', $hns, $match ) )
	{
		$d["hh"] = (int) $match[1];
		$d["nn"] = (int) $match[2];
		$d["ss"] = 0;
	}
	else if ( preg_match( '/^(\d+)$/', $hns, $match ) )
	{
		$d["hh"] = (int) $match[1];
		$d["nn"] = 0;
		$d["ss"] = 0;
	}
	else
	{
		return false;
	}

	return $d;
}

/**
 * 日付文字列の日付のチェックを行う。
 *
 * @param string $ymd 日付文字列
 *
 * @return string|false 正規化した日付文字列(%d/%d/%d)。チェックを行った結果不正な日付であれば False を返す。
 */
function wg_datetime_checkdate( string $ymd ): string|false
{
	$ymd = trim( $ymd );
	if ( preg_match( '/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $ymd, $m ) )
	{
		list( , $yy, $mm, $dd ) = $m;
	}
	else
	{
		return false;
	}

	// 10進整数に念のため変換 (0で始まる場合の 8進数扱い)
	$yy = (int) $yy;
	$mm = (int) $mm;
	$dd = (int) $dd;

	if ( ! checkdate( $mm, $dd, $yy ) )
	{
		return false;
	}

	return sprintf( "%d/%d/%d", $yy, $mm, $dd );
}

/**
 * 時間文字列の時間のチェックを行う。
 *
 * @param string $time 時間文字列
 *
 * @return string|false 正規化した時間文字列(%d:%d:%d)。チェックを行った結果不正な日付であれば False を返す。
 */
function wg_datetime_checktime( string $time ): string|false
{
	$time = trim( $time );

	$hh = $mm = $ss = 0;
	if ( preg_match( '/^(\d{1,2}):(\d{1,2}):(\d{1,2})/', $time, $m ) )
	{
		list( , $hh, $mm, $ss ) = $m;
	}
	else if ( preg_match( '/^(\d{1,2}):(\d{1,2})/', $time, $m ) )
	{
		list( , $hh, $mm ) = $m;
	}
	else
	{
		return false;
	}

	// 10進整数に念のため変換 (0で始まる場合の 8進数扱い)
	$hh = (int) $hh;
	$mm = (int) $mm;
	$ss = (int) $ss;

	// 数値範囲確認
	if ( $hh < 0 || $hh > 23 || $mm < 0 || $mm > 59 || $ss < 0 || $ss > 59 )
	{
		return false;
	}

	return sprintf( "%d:%d:%d", $hh, $mm, $ss );
}

/**
 * タイムスタンプ文字列から日付部分(2012/03/04)だけを返す。
 *
 * @param string $ymdhns タイムスタンプ文字列。
 *
 * @return string|false 正常に取り出せた場合日付文字列を、それ以外の場合はFalseを返す。
 */
function wg_datepart( string $ymdhns ): string|false
{
	$dd = wg_split_datetime( $ymdhns );

	return ( $dd != false ) ? sprintf( "%04d/%02d/%02d", $dd["yy"], $dd["mm"], $dd["dd"] ) : false;
}

/**
 * タイムスタンプ文字列から時間部分(23:45:12)だけを返す。
 *
 * @param string $ymdhns タイムスタンプ文字列。
 *
 * @return string|false 正常に取り出せた場合時間文字列を、それ以外の場合はFalseを返す。
 */
function wg_timepart( string $ymdhns ): string|false
{
	$dd = wg_split_datetime( $ymdhns );

	return ( $dd != false ) ? sprintf( "%02d:%02d:%02d", $dd["hh"], $dd["nn"], $dd["ss"] ) : false;
}

/**
 * タイムスタンプ配列からタイムスタンプ文字列に変換。
 *
 * @param array $dd タイムスタンプ配列(wg_check_datetimeを参照)
 *
 * @return string タイムスタンプ文字列
 */
function wg_join_datetime( array $dd ): string
{
	return sprintf( "%04d/%02d/%02d %02d:%02d:%02d", $dd["yy"], $dd["mm"], $dd["dd"], $dd["hh"], $dd["nn"], $dd["ss"] );
}

/**
 * タイムスタンプ文字列で示された月の１日(時間は00:00:00時点)を取得する。
 *
 * @param string $ymdhns タイムスタンプ文字列
 *
 * @return string|false タイムスタンプ文字列
 */
function wg_firstday( string $ymdhns ): string|false
{
	if ( ( $dd = wg_split_datetime( $ymdhns ) ) === false )
	{
		return false;
	}
	$dd["dd"] = 1;
	$dd["hh"] = 0;
	$dd["nn"] = 0;
	$dd["ss"] = 0;

	return wg_join_datetime( $dd );
}

/**
 * タイムスタンプ文字列で示された月の末日(時間は23:59:59時点)を取得する。
 *
 * @param string $ymdhns タイムスタンプ文字列。
 *
 * @return string|false タイムスタンプ文字列。
 */
function wg_lastday( string $ymdhns ): string|false
{
	if ( ( $dd = wg_split_datetime( $ymdhns ) ) === false )
	{
		return false;
	}
	$dd["dd"] = wg_finalday( $dd["yy"], $dd["mm"] );
	$dd["hh"] = 23;
	$dd["nn"] = 59;
	$dd["ss"] = 59;

	return wg_join_datetime( $dd );
}

/**
 * 指定日週の日曜日(週初め)を取得する。
 *
 * @param string $ymdhns タイムスタンプ文字列。
 *
 * @return string|false タイムスタンプ文字列(日付部分のみ)。
 */
function wg_firstweekday( string $ymdhns ): string|false
{
	if ( ( $dd = wg_split_datetime( $ymdhns ) ) === false )
	{
		return false;
	}
	$w = wg_dayofweekymd( $dd["yy"], $dd["mm"], $dd["dd"] );

	return wg_datepart( wg_join_datetime( wg_calc_datetime( $dd, 0, 0, - $w ) ) );
}

/**
 * 指定日週の土曜日(週終り)を取得する。
 *
 * @param string $ymdhns タイムスタンプ文字列。
 *
 * @return string|false タイムスタンプ文字列(日付部分のみ)。
 */
function wg_lastweekday( string $ymdhns ): string|false
{
	if ( ( $dd = wg_split_datetime( $ymdhns ) ) === false )
	{
		return false;
	}
	$w = wg_dayofweekymd( $dd["yy"], $dd["mm"], $dd["dd"] );

	return wg_datepart( wg_join_datetime( wg_calc_datetime( $dd, 0, 0, 6 - $w ) ) );
}

/**
 * 日付計算を行う。
 *
 * @param string $ymdhns タイムスタンプ文字列
 * @param int $yy 加減する年数
 * @param int $mm 加減する月数
 * @param int $dd 加減する日数
 * @param int $hh 加減する時数
 * @param int $nn 加減する分数
 * @param int $ss 加減する秒数
 *
 * @return string|false 正常に計算できたら計算後のタイムスタンプ文字列を返し、計算できない場合にはFalseを返す。
 */
function wg_calc_datetime(
	string $ymdhns,
	int $yy = 0, int $mm = 0, int $dd = 0,
	int $hh = 0, int $nn = 0, int $ss = 0
): string|false {

	if ( ( $pp = wg_split_datetime( $ymdhns ) ) === false )
	{
		return false;
	}

	// Add hour and minute and second number.
	$hms      = ( $pp["hh"] * 60 + $pp["nn"] ) * 60 + $pp["ss"] + ( $hh * 60 + $nn ) * 60 + $ss;
	$nnn      = __datetime_bval( $hms, 86400 );
	$pp["ss"] = $nnn % 60;
	$pp["nn"] = (int) ( $nnn / 60 ) % 60;
	$pp["hh"] = (int) ( $nnn / 3600 );
	$dd       += (int) ( floor( $hms / 86400 ) );

	// Add year and month number.
	$ym       = $pp["yy"] * 12 + ( $pp["mm"] - 1 ) + ( $yy * 12 + $mm );
	$pp["yy"] = (int) ( $ym / 12 );
	$pp["mm"] = $ym % 12 + 1;
	$lastdd   = wg_finalday( $pp["yy"], $pp["mm"] );
	if ( $pp["dd"] > $lastdd )
	{
		$pp["dd"] = $lastdd;
	}

	// Add day number loop
	$pp["dd"] = $pp["dd"] + $dd;

	do
	{
		if ( $pp["dd"] < 1 )
		{
			$pm = - 1;
		}
		else if ( $pp["dd"] > wg_finalday( $pp["yy"], $pp["mm"] ) )
		{
			$pm = 1;
		}
		else
		{
			$pm = 0;
		}
		if ( $pm > 0 )
		{
			$pp["dd"] = $pp["dd"] - wg_finalday( $pp["yy"], $pp["mm"] );
			$pp["mm"] ++;
			if ( $pp["mm"] > 12 )
			{
				$pp["yy"] ++;
				$pp["mm"] = 1;
			}
		}
		else if ( $pm < 0 )
		{
			$pp["mm"] --;
			if ( $pp["mm"] < 1 )
			{
				$pp["yy"] --;
				$pp["mm"] = 12;
			}
			$pp["dd"] = $pp["dd"] + wg_finalday( $pp["yy"], $pp["mm"] );
		}
	}
	while ( $pm != 0 );

	return wg_join_datetime( $pp );
}

/**
 * 日数差(日付1−日付2)を計算する。
 *
 * @param string $symd タイムスタンプ(日付1)文字列。
 * @param string $eymd タイムスタンプ(日付2)文字列。
 *
 * @return int|false 計算できた場合日付１-日付2を計算した後の日数を、それ以外の場合はFalseを返す。
 */
function wg_datediff( string $symd, string $eymd ): int|false
{
	if ( ( $sdd = wg_split_datetime( $symd ) ) === false )
	{
		return false;
	}
	if ( ( $edd = wg_split_datetime( $eymd ) ) === false )
	{
		return false;
	}
	$ss = __datetime_AD( $sdd["yy"], $sdd["mm"], $sdd["dd"] );
	$ee = __datetime_AD( $edd["yy"], $edd["mm"], $edd["dd"] );

	return $ss - $ee;
}

/**
 * 分単位の時間差(日付1-日付2)を計算する。
 *
 * @param string $symdhns タイムスタンプ(日付1)文字列。
 * @param string $eymdhns タイムスタンプ(日付2)文字列。
 *
 * @return int|false 計算できた場合日付１-日付2を計算した後の時間差(分)を、それ以外の場合はFalseを返す。
 */
function wg_timediff( string $symdhns, string $eymdhns ): int|false
{
	if ( ( $sdd = wg_split_datetime( $symdhns ) ) === false )
	{
		return false;
	}
	if ( ( $edd = wg_split_datetime( $eymdhns ) ) === false )
	{
		return false;
	}
	$ss = __datetime_AD( $sdd["yy"], $sdd["mm"], $sdd["dd"] ) * 3600 + $sdd["hh"] * 60 + $sdd["nn"];
	$ee = __datetime_AD( $edd["yy"], $edd["mm"], $edd["dd"] ) * 3600 + $edd["hh"] * 60 + $edd["nn"];

	return $ss - $ee;
}

/**
 * 秒単位の時間差(日付1-日付2)を計算する。
 *
 * @param string $symdhns タイムスタンプ(日付1)文字列。
 * @param string $eymdhns タイムスタンプ(日付2)文字列。
 *
 * @return int|false 計算できた場合日付１-日付2を計算した後の時間差(秒)を、それ以外の場合はFalseを返す。
 */
function wg_timediff_second( string $symdhns, string $eymdhns ): int|false
{
	if ( ( $sdd = wg_split_datetime( $symdhns ) ) === false )
	{
		return false;
	}
	if ( ( $edd = wg_split_datetime( $eymdhns ) ) === false )
	{
		return false;
	}
	$ss = __datetime_AD( $sdd["yy"], $sdd["mm"], $sdd["dd"] ) * 86400 + $sdd["hh"] * 3600 + $sdd["nn"] * 60 + $sdd['ss'];
	$ee = __datetime_AD( $edd["yy"], $edd["mm"], $edd["dd"] ) * 86400 + $edd["hh"] * 3600 + $edd["nn"] * 60 + $edd['ss'];

	return $ss - $ee;
}

/**
 * 現在の年齢の計算を計算する。
 *
 * @param string 生年月日のタイムスタンプ文字列。
 *
 * @return int|false 計算できた場合年齢を、それ以外の場合はFalseを返す。
 */
function wg_age( string $date ): int|false
{
	$t         = time();
	$now["yy"] = (int) date( "Y", $t );
	$now["mm"] = (int) date( "m", $t );
	$now["dd"] = (int) date( "d", $t );

	if ( ( $bir = wg_split_datetime( $date ) ) === false )
	{
		return false;
	}

	$nd = $now["mm"] * 100 + $now["dd"];
	$bd = $bir["mm"] * 100 + $bir["dd"];

	if ( $nd < $bd )
	{
		$age = $now["yy"] - $bir["yy"] - 1;
	}
	else
	{
		$age = $now["yy"] - $bir["yy"];
	}

	return $age;
}

/**
 * 時間文字列を正規化する。
 * Y{4}/m{2}/d{2} の形式に正規化する。
 *
 * @param string タイムスタンプ文字列。
 *
 * @return string 変換が成功した場合には日付文字列を、そうでない場合には空白文字列を返す。
 */
function wg_datetime_regularize_date( string $ymdhns ): string
{
	if ( ( $dd = wg_split_datetime( $ymdhns ) ) === false )
	{
		return "";
	}

	return $dd["date"];
}

/**
 * 時間文字列を正規化する。
 * Y{4}/m{2}/d{2} h{2}:i{2}:s{2} の形式に正規化する。
 *
 * @param string タイムスタンプ文字列。
 *
 * @return string 変換が成功した場合には日付文字列を、そうでない場合には空白文字列を返す。
 */
function wg_datetime_regularize_datetime( string $ymdhns ): string
{
	if ( ( $dd = wg_split_datetime( $ymdhns ) ) === false )
	{
		return "";
	}

	return $dd["datetime"];
}

/**
 * タイムスタンプ文字列から、ユーザ表示用の日付文字列を生成する。
 *
 * @param string $ymdhns タイムスタンプ文字列。
 * @param bool $is_hastime 時間を表示する場合Trueを、表示しない場合はFalseを指定する。
 * @param bool $is_short 短い形式で表示する場合Trueを、表示しない場合はFalseを指定する。
 * @param bool $is_autoyear 年を自動的に隠す場合はTrueを、隠さない場合はFalseを指定する。
 * @param bool $is_abouttime 時間の表示をアバウトに表現するならTrueを、そうでない場合はFalseを指定する。
 *
 * @return string 変換が成功した場合には日付文字列を、そうでない場合には空白文字列を返す。
 */
function wg_datetime_format(
	string $ymdhns, bool $is_hastime = true, bool $is_short = false, bool $is_autoyear = true,
	bool $is_abouttime = false
): string {
	$about =
		[
			"未明",
			"未明",
			"未明",
			"未明",
			"早朝",
			"早朝",
			"早朝",
			"朝",
			"朝",
			"午前",
			"午前",
			"昼前",
			"お昼",
			"昼過",
			"午後",
			"午後",
			"午後",
			"夕方",
			"夕方",
			"夜",
			"夜",
			"夜",
			"夜中",
			"夜中"
		];

	$local    = localtime( time(), 1 );
	$now_year = $local["tm_year"] + 1900;
	if ( ( $dd = wg_split_datetime( $ymdhns ) ) === false )
	{
		return "";
	}
	if ( ! $is_short )
	{
		if ( ! $is_abouttime )
		{
			$time = ( $is_hastime ) ? sprintf( "%d時%02d分", $dd["hh"], $dd["nn"] ) : "";
		}
		else
		{
			$time = ( $is_hastime ) ? $about[ $dd["hh"] ] : "";
		}

		$date = ( $now_year == $dd["yy"] && $is_autoyear ) ?
			sprintf( "%d月%d日(%s)", $dd["mm"], $dd["dd"], $dd["week"] ) :
			sprintf( "%d年%d月%d日(%s)", $dd["yy"], $dd["mm"], $dd["dd"], $dd["week"] );
	}
	else
	{
		if ( ! $is_abouttime )
		{
			$time = ( $is_hastime ) ? sprintf( " %02d:%02d", $dd["hh"], $dd["nn"] ) : "";
		}
		else
		{
			$time = ( $is_hastime ) ? $about[ $dd["hh"] ] : "";
		}

		$date = ( $now_year == $dd["yy"] && $is_autoyear ) ?
			sprintf( "%02d/%02d", $dd["mm"], $dd["dd"] ) :
			sprintf( "%04d/%02d/%02d", $dd["yy"], $dd["mm"], $dd["dd"] );
	}

	return $date . $time;
}


/**
 * タイムスタンプ文字列から、シーケンス用の日付文字列(20091203122345)を生成する。
 *
 * @param string $ymdhns タイムスタンプ文字列。
 * @param bool $is_hastime 時間を含めるか(デフォルトはTrue)。
 *
 * @return string 変換が成功した場合には日付期間文字列を、そうでない場合には空白文字列を返す。
 */
function wg_format_seqdate( string $ymdhns, bool $is_hastime = true ): string
{
	if ( ( $dd = wg_split_datetime( $ymdhns ) ) === false )
	{
		return "";
	}
	$time = ( $is_hastime ) ? sprintf( "%02d%02d%02d", $dd["hh"], $dd["nn"], $dd["ss"] ) : "";
	$date = sprintf( "%04d%02d%02d", $dd["yy"], $dd["mm"], $dd["dd"] );

	return $date . $time;
}

/**
 * RFC822形式から、通常のタイムスタンプ文字列に変換する。
 *
 * @param string RFC822タイムスタンプ文字列。
 *
 * @return string|false 変換が成功した場合には日付期間文字列を、そうでない場合には false を返す。
 */
function wg_datetime_rfc822( string $datestring ): string|false
{
	$datestring = preg_replace( '/\+\d{4}/', '', $datestring );

	return date( 'Y-m-d H:i:s', strtotime( $datestring ) );
}

/**
 * W3C-DTF形式から、通常のタイムスタンプ文字列に変換する。
 *
 * @param string W3C-DTFタイムスタンプ文字列。
 *
 * @return string|false 変換が成功した場合には日付期間文字列を、そうでない場合には false を返す。
 */
function wg_datetime_w3cdtf( string $datestring ): string|false
{
	$datestring = preg_replace( '/\+\d{2}:\d{2}/', '', $datestring );
	$datestring = preg_replace( '/T/', ' ', $datestring );

	return date( 'Y-m-d H:i:s', strtotime( $datestring ) );
}


