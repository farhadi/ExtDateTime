<?php

//TODO: add documentation comments
 
class ExtDateTime_Persian extends ExtDateTime 
{
	static private $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
	static private $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29); 

	static private $j_months = array('Farvardin', 'Ordibehesht', 'Khordad', 'Tir', 'Mordad', 'Shahrivar', 'Mehr', 'Aban', 'Azar', 'Dey', 'Bahman', 'Esfand');

	public function __construct($time = null, $timezone = null, $translator = null)
	{
		$this->setTranslator($translator);
		$modify = false;
		if (is_string($time) && !is_numeric($time)) {
			$time = $this->backTranslate($time);
			$time = self::jalaliToGregorianStr($time);
			$modify = $time;
			$time = preg_replace('/((?:[+-]?\d+)|next|last|previous)\s*(year|month)/i', '', $time);
			if ($modify == $time) $modify = false;
		}

		parent::__construct($time, $timezone, $translator);

		if ($modify) {
			preg_replace_callback('/((?:[+-]?\d+)|next|last|previous)\s*(year|month)/i', array($this, 'modifyCallback'), $modify);
		}
	}

	protected function buildTranslations() {
		parent::buildTranslations();
		
		foreach (self::$j_months as $month) {
			$this->translations[$month] = $this->translate($month);
		}
	}

	public function set($time = null, $timezone = null) {
		if (is_string($time) && !is_numeric($time)) {
			$time = $this->backTranslate($time);
			$class = __CLASS__;
			$time = new $class($time, $timezone ? $timezone : $this->getTimezone());
		}

		return parent::set($time, $timezone);
	}

	public function setDate($year, $month, $day)
	{
		list( $year, $month, $day ) = self::jalaliToGregorian($year, $month, $day);
		return parent::setDate( $year, $month, $day );
	}
	
	protected function modifyCallback($matches) {
		list($y, $m, $d) = explode('-', $this->format('Y-n-j'));
		$change = strtolower($matches[1]);
		$unit = strtolower($matches[2]);

		switch ($change) {
			case "next":
				$change = '+1';
				break;

			case "last":
			case "previous":
				$change = '-1';
				break;
		}

		switch ($unit) {
			case "month":
				$m += $change;
				if ($m > 12) {
					$y += floor($m/12);
					$m = $m % 12;
				} elseif ($m < 1) {
					$y += ceil($m/12) - 1;
					$m = $m % 12 + 12;
				}
				break;

			case "year":
				$y += $change;
				break; 
		}

		$this->setDate($y, $m, $d);

		return '';
	}
	
	public function modify($modify) {
		$modify = $this->backTranslate($modify);
		$modify = preg_replace_callback('/((?:[+-]?\d+)|next|last|previous)\s*(year|month)/i', array($this, 'modifyCallback'), $modify);
		parent::modify($modify);
		return $this;
	}

	public function format($format, $timezone = null)
	{
 		if (isset($timezone)) {
			$tempTimezone = $this->getTimezone();
			$this->setTimezone($timezone);
		}

		$result = "";

		list( $year, $month, $day ) = explode('-', parent::format("Y-n-j"));
		list( $jyear, $jmonth, $jday ) = self::gregorianToJalali($year, $month, $day);

		for ($i = 0; $i < strlen($format); $i++) {
			switch ($format[$i]) {
				case "y": //A two digit representation of a year (Examples: 99 or 03)
					$result .= substr($jyear, 2, 4);
					break;

				case "Y": //A full numeric representation of a year, 4 digits (Examples: 1999 or 2003)
					$result .= $jyear;
					break;

				case "M": //There is no short textual representation of months in persian so we use full textual representaions instead.
				case "F": //A full textual representation of a month (Farvardin through Esfand)
					$result .= $this->translate(self::$j_months[$jmonth-1]);
					break;

				case "m": //Numeric representation of a month, with leading zeros (01 through 12)
					$result .= sprintf('%02d', $jmonth);
					break;

				case "n": //Numeric representation of a month, without leading zeros (1 through 12)
					$result .= $jmonth;
					break;

				case "d": //Day of the month, 2 digits with leading zeros (01 to 31)
					$result .= sprintf('%02d', $jday);
					break;

				case "j": //Day of the month without leading zeros (1 to 31)
					$result .= $jday;
					break;

				case "w": //Numeric representation of the day of the week (0 (for Saturday) through 6 (for Friday))
					$result .= (parent::format("w") + 1) % 7;
					break;

				case "t": //Number of days in the given month (29 through 31)
					if ($jmonth < 12) $result .= self::$j_days_in_month[$jmonth-1];
					else if (self::jalaliCheckDate($jmonth, 30, $jyear)) $result .= '30';
					else $result .= '29';
					break;

				case "z": //The day of the year starting from 0 (0 through 365)
					$day_of_year = 0;
					for ($n=0; $n<$jmonth-1; $n++) {
						$day_of_year += self::$j_days_in_month[$n];
					}
					$day_of_year += $jday-1;
					$result .= $day_of_year;
					break;

				case "L": //Whether it's a leap year (1 if it is a leap year, 0 otherwise.)
					$result .= self::jalaliCheckDate(12, 30, $jyear) ? '1' : '0' ;
					break;

				case "W": //Week number of year, weeks starting on Saturday
					$z = $this->format('z');
					$firstSaturday = ($z - $this->format('w') + 7) % 7;
					$days = $z - $firstSaturday; //Number of days after the first Saturday of the year
					if ($days < 0) {
						$z += self::jalaliCheckDate(12, 30, $jyear-1) ? 366 : 365;
						$firstSaturday = ($z - $this->format('w') + 7) % 7;
						$days = $z - $firstSaturday; 
					}
					$result .= floor($days / 7) + 1;
					break;
					
				case "\\":
					if ($i+1 < strlen($format)) $result .= $format[++$i];
					else $result .= $format[$i];
					break;

				default:
					$result .= parent::format($format[$i]);
			}
		}

		if (isset($timezone)) {
			$this->setTimezone($tempTimezone);
		}

		return $result;
	}

	static public function jalaliCheckDate($j_m, $j_d, $j_y)
	{
		if ($j_y < 0 || $j_y > 32767 || $j_m < 1 || $j_m > 12 || $j_d < 1 || $j_d >
			(self::$j_days_in_month[$j_m-1] + ($j_m == 12 && !(($j_y-979)%33%4))))
			return false;
		return true;
	}

	static public function jalaliToGregorian($j_y, $j_m, $j_d)
	{
		$jy = $j_y-979;
		$jm = $j_m-1;
		$jd = $j_d-1;

		$j_day_no = 365*$jy + (int)($jy / 33)*8 + (int)(($jy%33+3) / 4);
		for ($i=0; $i < $jm; ++$i)
		$j_day_no += self::$j_days_in_month[$i];

		$j_day_no += $jd;

		$g_day_no = $j_day_no+79;

		$gy = 1600 + 400*(int)($g_day_no / 146097); /* 146097 = 365*400 + 400/4 - 400/100 + 400/400 */
		$g_day_no = $g_day_no % 146097;

		$leap = true;
		if ($g_day_no >= 36525) /* 36525 = 365*100 + 100/4 */
		{
			$g_day_no--;
			$gy += 100 * (int)($g_day_no / 36524); /* 36524 = 365*100 + 100/4 - 100/100 */
			$g_day_no = $g_day_no % 36524;

			if ($g_day_no >= 365)
			$g_day_no++;
			else
			$leap = false;
		}

		$gy += 4*(int)($g_day_no / 1461); /* 1461 = 365*4 + 4/4 */
		$g_day_no %= 1461;

		if ($g_day_no >= 366) {
			$leap = false;
	
			$g_day_no--;
			$gy += (int)($g_day_no / 365);
			$g_day_no = $g_day_no % 365;
		}

		for ($i = 0; $g_day_no >= self::$g_days_in_month[$i] + ($i == 1 && $leap); $i++)
		$g_day_no -= self::$g_days_in_month[$i] + ($i == 1 && $leap);
		$gm = $i+1;
		$gd = $g_day_no+1;

		return array($gy, $gm, $gd);
	}

	static public function gregorianToJalali($g_y, $g_m, $g_d)
	{
		$gy = $g_y-1600;
		$gm = $g_m-1;
		$gd = $g_d-1;

		$g_day_no = 365*$gy+(int)(($gy+3) / 4)-(int)(($gy+99) / 100)+(int)(($gy+399) / 400);

		for ($i=0; $i < $gm; ++$i)
		$g_day_no += self::$g_days_in_month[$i];
		if ($gm>1 && (($gy%4==0 && $gy%100!=0) || ($gy%400==0)))
		/* leap and after Feb */
		++$g_day_no;
		$g_day_no += $gd;

		$j_day_no = $g_day_no-79;

		$j_np = (int)($j_day_no / 12053);
		$j_day_no %= 12053;

		$jy = 979+33*$j_np+4*(int)($j_day_no / 1461);

		$j_day_no %= 1461;

		if ($j_day_no >= 366) {
			$jy += (int)(($j_day_no-1) / 365);
			$j_day_no = ($j_day_no-1)%365;
		}

		for ($i = 0; $i < 11 && $j_day_no >= self::$j_days_in_month[$i]; ++$i) {
			$j_day_no -= self::$j_days_in_month[$i];
		}
		$jm = $i+1;
		$jd = $j_day_no+1;

		return array($jy, $jm, $jd);
	}

	static public function jalaliToGregorianStr($str)
	{
		$months = implode('|', self::$j_months);
		if (preg_match('@\d{2,4}(-|\\\\|/)\d{1,2}\1\d{1,2}@i', $str, $res) || 
			preg_match('@\d{1,2}(-| )(?:'.$months.')\1\d{2,4}@i', $str, $res)) {
			$j = explode($res[1], $res[0]);
			$month = array_search($j[1], self::$j_months);
			if ($month !== false) {
				$j[1] = $month + 1;
				$y = $j[2];
				$j[2] = $j[0];
				$j[0] = $y;
			}
			if (strlen($j[0]) == 2) $j[0] = '13'.$j[0];
			$g = self::jalaliToGregorian($j[0], $j[1], $j[2]);
			$gstr = implode('-', $g);
			$str = str_replace($res[0], $gstr, $str);
		}
		return $str;
	}
}
?>