<?php
namespace gimle\sql;

use gimle\Config;
use gimle\MainConfig;
use function gimle\colorize;

/**
 * MySQL Utilities class.
 */
class Mysql extends \mysqli
{
	use \gimle\trick\Multiton;

	/**
	 * Information of the performed queries.
	 *
	 * @var array
	 */
	private $queryCache = [];
	/**
	 * Create a new Mysqli object.
	 *
	 * @param array $params
	 * @return object
	 */
	public function __construct ($key)
	{
		mysqli_report(MYSQLI_REPORT_STRICT);

		$params = Config::get('mysql.' . $key);
		if (($params === null) && (IS_SUBSITE)) {
			$params = MainConfig::get('mysql.' . $key);
		}
		if ($params === null) {
			$params = [];
		}
		parent::init();
		$params['pass'] = (isset($params['pass']) ? $params['pass'] : '');
		$params['user'] = (isset($params['user']) ? $params['user'] : 'root');
		$params['host'] = (isset($params['host']) ? $params['host'] : '127.0.0.1');
		$params['port'] = (isset($params['port']) ? $params['port'] : 3306);
		$params['timeout'] = (isset($params['timeout']) ? $params['timeout'] : 30);
		$params['charset'] = (isset($params['charset']) ? $params['charset'] : 'utf8');
		$params['database'] = (isset($params['database']) ? $params['database'] : false);
		parent::options(MYSQLI_OPT_CONNECT_TIMEOUT, $params['timeout']);
		parent::real_connect($params['host'], $params['user'], $params['pass'], $params['database'], $params['port']);
		if ($this->errno === 0) {
			$this->set_charset($params['charset']);
			if ((isset($params['cache'])) && ($params['cache'] === false)) {
				$this->cache(false);
			}
		}
	}
	/**
	 * Turn Mysql cache on or off.
	 *
	 * @param mixed $mode bool|null true = on, false = off, null (Default) = return current state.
	 * @return mixed bool|array
	 */
	public function cache ($mode = null)
	{
		if ($mode === true) {
			return parent::query("SET SESSION query_cache_type = ON;");
		}
		elseif ($mode === false) {
			return parent::query("SET SESSION query_cache_type = OFF;");
		}
		else {
			return parent::query("SHOW VARIABLES LIKE 'query_cache_type';")->fetch_assoc();
		}
	}
	/**
	 * Perform a mysql query.
	 *
	 * @see mysqli::query()
	 *
	 * @param string $query
	 * @param mixed $resultmode null|string
	 * @return mixed bool|object
	 */
	public function query ($query, $resultmode = null)
	{
		$t = microtime(true);
		$error = false;
		if (!$result = parent::query($query, $resultmode)) {
			$append = self::debug_backtrace('query');
			trigger_error('MySQL query error: (' . $this->errno . ') ' . $this->error . ' in "' . $query . '".' . $append);
			$error = ['errno' => $this->errno, 'error' => $this->error];
		}
		$mysqliresult = (is_bool($result) ? $result : new Mysqliresult($result));
		$t = microtime(true) - $t;
		$this->queryCache[] = ['query' => $query, 'time' => $t, 'rows' => $this->affected_rows, 'error' => $error];
		return $mysqliresult;
	}

	public function getQueryTotals ()
	{
		$return = ['count' => 0, 'time' => 0, 'doubles' => false];
		$doubles = [];
		foreach ($this->queryCache as $query) {
			$doubles[] = $query['query'];
			$return['time'] += $query['time'];
			$return['count']++;
		}
		if (count(array_unique($doubles)) < count($doubles)) {
			$return['doubles'] = true;
		}

		return $return;
	}

	/**
	 * Explain the performed queries.
	 *
	 * @return string
	 */
	public function explain ()
	{
		$textcolor = colorize('', 'black');
		$errstyle = 'style="' . colorize('', 'error') . '"';
		$textstyle = '';
		if ($textcolor !== '') {
			$textstyle = ' style="' . $textcolor . '"';
			$textcolor = ' ' . $textcolor;
		}
		$return = '';
		$sqlnum = 0;
		$sqltime = 0;
		$doubles = [];
		foreach ($this->queryCache as $query) {
			$doubles[] = $query['query'];
			$sqltime += $query['time'];
			$sqlnum++;
			$query['time'] = colorize($query['time'], 'range:{"type": "alert", "max":0.09, "value":' . str_replace(',', '.', $query['time']) . '}');
			if (ENV_MODE & ENV_WEB) {
				$return .= '<table border="1" style="font-size: 12px; width: 100%; border-collapse: collapse;">';
				$return .= '<tr><td colspan="12" style="font-family: monospace; font-size: 11px;' . $textcolor . '">' . $query['query'] . '</td></tr>';
				$return .= '<tr><td colspan="12"' . $textstyle . '>Affected rows: ' . $query['rows'] . ', Query Time: ' . $query['time'] . '</td></tr><tr>';
			}
			else {
				$return .= colorize($query['query'], 'black', $background) . "\n";
				$return .= colorize('Affected rows: ' . $query['rows'] . ', Query Time: ' . $query['time'], 'black', $background) . "\n";
			}
			$temp = '';
			if (($query['error'] === false) && (preg_match('/^SELECT/i', $query['query']) > 0)) {
				$charcount = [];
				$fieldsarray = [];
				$res = parent::query('EXPLAIN ' . $query['query']);
				$fields = $res->fetch_fields();
				foreach ($fields as $field) {
					if (ENV_MODE & ENV_WEB) {
						$return .= '<th' . $textstyle . '>' . $field->name . '</th>';
					}
					else {
						$fieldsarray[] = $field->name;
					}
				}
				if (ENV_MODE & ENV_WEB) {
					$return .= '</tr>';
				}
				$rowarray = [];
				while ($row = $res->fetch_assoc()) {
					$subrowarray = [];
					$i = 0;
					foreach ($row as $key => $value) {
						if (ENV_MODE & ENV_CLI) {
							$thiscount = (($value === null) ? 4 : strlen($value));
							if (isset($charcount[$key])) {
								$charcount[$key] = max($thiscount, $charcount[$key]);
							}
							else {
								$charcount[$key] = max($thiscount, strlen($fieldsarray[$i]));
							}
							$subrowarray[$key] = $value;
						}
						if ($value === null) {
							$row[$key] = 'NULL';
						}
						$i++;
					}
					$rowarray[] = $subrowarray;
					if (ENV_MODE & ENV_WEB) {
						$temp .= '<tr><td' . $textstyle . '>' . implode('</td><td' . $textstyle . '>', $row) . '</td></tr>';
					}
				}
				if ((ENV_MODE & ENV_WEB) && ($temp === '')) {
					if (preg_match('/^SELECT/i', $query['query']) > 0) {
						$return .= '<tr><td colspan="12"' . $errstyle . '>Erronymous query.' . '</td></tr>';
					}
					else {
						$return .= '<tr><td colspan="12"' . $errstyle . '>Unknown query.' . '</td></tr>';
					}
				}
				elseif (ENV_MODE & ENV_WEB) {
					$return .= $temp;
				}
				elseif (!empty($rowarray)) {
					$return .= '+';
					foreach ($charcount as $value) {
						$return .= str_repeat('-', $value + 2) . '+';
					}
					$return .= "\n|";
					foreach ($fieldsarray as $value) {
						$return .= ' ' . str_pad($value, $charcount[$value], ' ', STR_PAD_BOTH) . ' |';
					}
					foreach ($rowarray as $row) {
						$return .= "\n+";
						foreach ($charcount as $value) {
							$return .= str_repeat('-', $value + 2) . '+';
						}
						$return .= "\n|";
						foreach ($row as $key => $value) {
							$return .= ' ' . str_pad($value, $charcount[$key], ' ', STR_PAD_RIGHT) . ' |';
						}
					}
					$return .= "\n+";
					foreach ($charcount as $value) {
						$return .= str_repeat('-', $value + 2) . '+';
					}
					$return .= "\n";
				}
				else {
					if (preg_match('/^SELECT/i', $query['query']) > 0) {
						$return .= colorize('Erronymous query.', 'error', $background) . "\n";
					}
					else {
						$return .= colorize('Unknown query.', 'error', $background) . "\n";
					}
				}
			}
			elseif ($query['error'] !== false) {
				if (ENV_MODE & ENV_WEB) {
					$return .= '<tr><td colspan="12"' . $errstyle . '>Error (' . $query['error']['errno'] . '): ' . $query['error']['error'] . '</td></tr>';
				}
				else {
					$return .= colorize('Error (' . $query['error']['errno'] . '): ' . $query['error']['error'], 'error', $background) . "\n";
				}
			}
			elseif (ENV_MODE & ENV_WEB) {
				$return .= $temp;
			}
			if (ENV_MODE & ENV_WEB) {
				$return .= '</table><br>';
			}
			else {
				$return .= "\n";
			}
		}
		if (count(array_unique($doubles)) < count($doubles)) {
			$return .= colorize('You have duplicate queries!', 'error') . '<br>';
		}
		$return .= colorize('Total sql time: ' . colorize($query['time'], 'range:{"type": "alert", "max":0.3, "value":' . $sqltime . '}'), 'black') . (ENV_MODE & ENV_WEB ? '<br>' : "\n");
		$return .= colorize('Total sql queries: ' . $sqlnum, 'black') . (ENV_MODE & ENV_CLI ? "\n" : '');
		return $return;
	}

	/**
	 * Find callee.
	 *
	 * @param string $function
	 * @return string
	 */
	private function debug_backtrace ($function)
	{
		if (ini_get('html_errors') === '') {
			$template = ' in %s on line %s';
		}
		else {
			$template = ' in <b>%s</b> on line <b>%s</b>';
		}
		$backtrace = debug_backtrace();
		foreach ($backtrace as $key => $value) {
			if (isset($value['args'])) {
				foreach ($value['args'] as $key2 => $value2) {
					if ((is_array($value2)) && (isset($value2['GLOBALS']))) {
						$backtrace[$key]['args'][$key2] = 'Globals vars removed';
					}
				}
			}
		}
		$return = '';
		foreach ($backtrace as $value) {
			if ((isset($value['function'])) && ($value['function'] === $function)) {
				$return .= sprintf($template, $value['file'], $value['line']);
			}
		}
		return $return;
	}
}
/**
 * MySQL Result class.
 */
class Mysqliresult
{
	/**
	 * Result set
	 *
	 * @var object mysqli_result Object
	 */
	private $result;
	/**
	 * Create a new mysqli_result object.
	 *
	 * @param \mysqli_result $result mysqli_result Object
	 * @return object mysqli_result Object
	 */
	public function __construct (\mysqli_result $result)
	{
		$this->result = $result;
	}
	/**
	 * Fetch rows and return them all in a typesensitive array.
	 *
	 * @return array
	 */
	public function get_assoc ()
	{
		for ($i = 0; $i < $this->field_count; $i++) {
			$tmp = $this->fetch_field_direct($i);
			$finfo[$tmp->name] = $tmp->type;
			unset($tmp);
		}
		$result = $this->fetch_assoc();
		if ($result === null) {
			return false;
		}
		foreach ($result as $key => $value) {
			if ($result[$key] === null) {
			}
			elseif (in_array($finfo[$key], [1, 2, 3, 8, 9])) {
				$result[$key] = (int)$result[$key];
			}
			elseif (in_array($finfo[$key], [4, 5, 246])) {
				$result[$key] = (float)$result[$key];
			}
		}
		return $result;
	}
	/**
	 * Performs different operations depending on argument types.
	 *
	 * @param string $name Method name
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call ($name, $arguments)
	{
		return call_user_func_array([$this->result, $name], $arguments);
	}
	/**
	 * Set a value.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function __set ($name, $value)
	{
		$this->result->$name = $value;
	}
	/**
	 * Retrieve a value.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get ($name)
	{
		return $this->result->$name;
	}
}
