<?php
/**
 * ExtDateTime is an extented version of php5 DateTime class that adds some more functionality to it
 * 		and makes it extensible for other calendar systems.
 * ExtDateTime is not compatible with PHP 5.2.6 because of php bug #45038 (http://bugs.php.net/bug.php?id=45038)
 * 
 * @package     ExtDateTime
 * @version     1.0
 * @copyright  	GNU General Public License 3.0 (http://www.gnu.org/licenses/gpl.html)
 * @author      Ali Farhadi <ali@farhadi.ir>
 * @example
 * 		$date = ExtDateTime::factory('Persian');
 * 		echo $date->format('Y-m-d H:i:s'); 	
 * 
 */
class ExtDateTime extends DateTime
{
	/**
	 * @var callback $translator Translator function (such as gettext) to run on format results
	 */
	protected $translator = null;

	/**
	 * @var array $translations An associative array of words and their respective translations that is used in backTranslate
	 */
	protected $translations = null;
	
	/**
	 * creates a new instance of ExtDateTime
	 * @param mixed $time Unix timestamp or strtotime() compatible string or another DateTime object
	 * @param mixed $timezone DateTimeZone object or timezone identifier as full name (e.g. Asia/Tehran) or abbreviation (e.g. IRDT). 
	 * @return ExtDateTime
	 */
	public function __construct($time = null, $timezone = null, $translator = null)
	{
		$this->setTranslator($translator);
		if (!isset($timezone)) $timezone = new DateTimeZone(date_default_timezone_get());
		elseif (is_string($timezone)) $timezone = new DateTimeZone($timezone);

		if (is_a($time, 'DateTime') || preg_match('/^[-+]?\d+$/', $time)) {
			parent::__construct(null, $timezone);
			$this->set($time);
		} else {
			if (is_string($time)) $time = $this->backTranslate($time);
			$date = parent::__construct($time, $timezone);
		}
	}
	
	/**
	 * Create a new ExtDateTime object for the specified calendar type
	 * @param string $type calendar type
	 * @param mixed $time Unix timestamp (integer) or strtotime() compatible string or another DateTime object
	 * @param mixed $timezone DateTimeZone object or timezone identifier as full name (e.g. Asia/Tehran) or abbreviation (e.g. IRDT).
	 * @return ExtDateTime a newly created ExtDateTime object, or false on error 
	 */
	static public function factory($type, $time = null, $timezone = null, $translator = null)
	{
		$type = ucfirst($type); 
		$classname = 'ExtDateTime_'.$type;
		$filename = dirname(__FILE__)."/Calendars/$type.php";
		if (!class_exists($classname)) {
			@include_once($filename);
			if (!class_exists($classname)) {
				if (!file_exists($filename)) {
					$error = "unable to find class '$classname' file '$filename'";
				} else {
					$error = "unable to load class '$classname' from file '$filename'";
				}
				throw new Exception($error); 
			}
		}
		
		return new $classname($time, $timezone, $translator);
	}
	
	/**
	 * Magic __call to implement methods that is not exist in php versions older than 5.3
	 * @param string $name method name
	 * @param array $arguments method arguments
	 * @return mixed
	 */
	public function __call($method, $arguments) {
		if (in_array($method, array('getTimestamp', 'setTimestamp'))) {
			 return call_user_func_array(array($this, '_'.$method), $arguments);
		}
		
		throw new Exception(sprintf('Unknown method %s::%s', get_class($this), $method));
	}
	
	/**
	 * Gets the Unix timestamp
	 * @return int Unix timestamp representing the date. 
	 */
	private function _getTimestamp() {
		return floatval(parent::format('U'));
	}
	
	/**
	 * Sets the date and time based on an Unix timestamp
	 * @param int $unixtimestamp Unix timestamp representing the date.
	 * @return ExtDateTime the modified DateTime.  
	 */
	private function _setTimestamp($unixtimestamp) {
		$diff = $unixtimestamp - $this->getTimestamp(); 
		$days = floor($diff / 86400);
		$seconds = $diff - $days * 86400;
		parent::modify("$days days $seconds seconds");
		return $this;
	}

	/**
	 * Alters object's internal timestamp with a string acceptable by strtotime() or a Unix timestamp or a DateTime object
	 * @param mixed $time Unix timestamp or strtotime() compatible string or another DateTime object
	 * @param mixed $timezone DateTimeZone object or timezone identifier as full name (e.g. Asia/Tehran) or abbreviation (e.g. IRDT).
	 * @return ExtDateTime the modified DateTime.
	 */
	public function set($time = null, $timezone = null)
	{
		if (is_a($time, 'DateTime')) {
			$time = $time->format('U');
		} elseif (!preg_match('/^[-+]?\d+$/', $time)) {
			$time = new ExtDateTime($time, $timezone ? $timezone : $this->getTimezone());
			$time = $time->getTimestamp();
		}

		$this->setTimestamp($time);
		
		return $this;
	}

	/**
	 * Sets the timezone for the object
	 * @param mixed $timezone DateTimeZone object or timezone identifier as full name (e.g. Asia/Tehran) or abbreviation (e.g. IRDT).
	 * @return void NULL on success or FALSE on failure. 
	 */
	public function setTimezone($timezone)
	{
		if (!is_a($timezone, 'DateTimeZone')) $timezone = new DateTimeZone($timezone);
		return parent::setTimezone($timezone);
	}

	/**
	 * Alter the timestamp by incrementing or decrementing in a format accepted by strtotime().
	 * @param string $modify a string in a relative format accepted by strtotime(). 
	 * @return ExtDateTime the modified DateTime.
	 */
	public function modify($modify)
	{
		$modify = $this->backTranslate($modify);
		parent::modify($modify);
		return $this;
	}

	/**
	 * Returns date formatted according to given format
	 * @param string $format Format accepted by date(). 
	 * @param mixed $timezone DateTimeZone object or timezone identifier as full name (e.g. Asia/Tehran) or abbreviation (e.g. IRDT). 
	 * @return string Formatted date on success or FALSE on failure. 
	 */
	public function format($format, $timezone = null)
	{
		if (isset($timezone)) {
			$tempTimezone = $this->getTimezone();
			$this->setTimezone($timezone);
		}
		
		$result = "";
		
		for ($i = 0; $i < strlen($format); $i++) {
			switch ($format[$i]) {
				case "\\":
					if ($i+1 < strlen($format)) $result .= $format[++$i];
					else $result .= $format[$i];
					break;
				case "M": //A short textual representation of a month, three letters (Jan through Dec) 
				case "F": //A full textual representation of a month, such as January or March
				case "D": //A textual representation of a day, three letters (Mon through Sun)
				case "l": //A full textual representation of the day of the week (Sunday through Saturday)
				case "S": //English ordinal suffix for the day of the month, 2 characters (st, nd, rd or th. Works well with j)
				case "a": //Lowercase Ante meridiem and Post meridiem (am or pm)
				case "A": //Uppercase Ante meridiem and Post meridiem (AM or PM)
					$result .= $this->translate(parent::format($format[$i]));
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

	/**
	 * Sets translator function to be used when formatting
	 * @param callback $translator Translator function (such as gettext) to run on format results
	 * @return void
	 */
	public function setTranslator($translator = null) {
		if ($this->translator !== $translator) { 
			$this->translator = $translator;
			$this->translations = null;
		}
	}
	
	/**
	 * Translate a string using user defined translator
	 * @param string $str
	 * @return string Translated string
	 */
	protected function translate($str)
	{
		if ($this->translator) {
			$str = call_user_func($this->translator, $str);
		}

		return $str;
	}
	
	/**
	 * Fills $translations with proper data.
	 * @return void
	 */
	protected function buildTranslations() {
		$keywords = array(
			'Friday', 'Fri', 'Saturday', 'Sat', 'Sunday', 'Sun', 'Monday', 'Mon',
			'Tuesday', 'Tue', 'Wednesday', 'Wed', 'Thursday', 'Thu', 
			'August', 'Aug', 'September', 'Sep', 'October', 'Oct', 'November', 'Nov',
			'December', 'Dec', 'January', 'Jan', 'February', 'Feb', 'March', 'Mar',
			'April', 'Apr', 'May', 'June', 'Jun', 'July', 'Jul', 'Today', 
			'Yesterday', 'Tomorrow', 'Next', 'Last', 'Previous', 'Year', 
			'Month', 'Week', 'Day', 'Hour', 'Minute', 'Second',
			'st', 'nd', 'rd', 'th', 'am', 'AM', 'pm', 'PM');

		foreach ($keywords as $key) {
			$this->translations[$key] = $this->translate($key);
		}
	}

	/**
	 * Back-translate a string using user defined translator
	 * @param string $str
	 * @return string Back-translated string
	 */
	protected function backTranslate($str)
	{
		if (!$this->translator || !$str || preg_match('@^[-\\\\/\s\d]*$@', $str)) return $str;

		if (!$this->translations) {
			$this->buildTranslations();
		}

		return str_replace(array_values($this->translations), array_keys($this->translations), $str);
	}
}
?>