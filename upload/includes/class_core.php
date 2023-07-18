<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

define('FILE_VERSION', '3.8.11'); // this should match install.php
define('SIMPLE_VERSION', '3811'); // see vB_Datastore::check_options()
define('YUI_VERSION', '2.9.0'); // define the YUI version we bundle, used for external YUI

/**#@+
* The maximum sizes for the "small" profile avatars
*/
define('FIXED_SIZE_AVATAR_WIDTH', 60);
define('FIXED_SIZE_AVATAR_HEIGHT', 80);
/**#@-*/

/**#@+
* These make up the bit field to disable specific types of BB codes.
*/
define('ALLOW_BBCODE_BASIC',  1);
define('ALLOW_BBCODE_COLOR',  2);
define('ALLOW_BBCODE_SIZE',   4);
define('ALLOW_BBCODE_FONT',   8);
define('ALLOW_BBCODE_ALIGN',  16);
define('ALLOW_BBCODE_LIST',   32);
define('ALLOW_BBCODE_URL',    64);
define('ALLOW_BBCODE_CODE',   128);
define('ALLOW_BBCODE_PHP',    256);
define('ALLOW_BBCODE_HTML',   512);
define('ALLOW_BBCODE_IMG',    1024);
define('ALLOW_BBCODE_QUOTE',  2048);
define('ALLOW_BBCODE_CUSTOM', 4096);
/**#@-*/

/**#@+
* These make up the bit field to control what "special" BB codes are found in the text.
*/
define('BBCODE_HAS_IMG', 1);
define('BBCODE_HAS_ATTACH', 2);
define('BBCODE_HAS_SIGPIC', 4);
/**#@-*/

/**#@+
* Bitfield values for the inline moderation javascript selector which should be self-explanitory
*/
define('POST_FLAG_INVISIBLE', 1);
define('POST_FLAG_DELETED',   2);
define('POST_FLAG_ATTACH',    4);
define('POST_FLAG_GUEST',     8);

// #############################################################################
// MySQL Database Class

/**#@+
* The type of result set to return from the database for a specific row.
*/
define('DBARRAY_BOTH',  0);
define('DBARRAY_ASSOC', 1);
define('DBARRAY_NUM',   2);
/**#@-*/

/**
* Class to interface with a database
*
* This class also handles data replication between a master and slave(s) servers
*
* @package	vBulletin
*/
class vB_Database
{
	/**
	* Array of function names, mapping a simple name to the RDBMS specific function name
	*
	* @var	array
	*/
	var $functions = array(
		'connect'            => 'mysql_connect',
		'pconnect'           => 'mysql_pconnect',
		'select_db'          => 'mysql_select_db',
		'query'              => 'mysql_query',
		'query_unbuffered'   => 'mysql_unbuffered_query',
		'fetch_row'          => 'mysql_fetch_row',
		'fetch_array'        => 'mysql_fetch_array',
		'fetch_field'        => 'mysql_fetch_field',
		'free_result'        => 'mysql_free_result',
		'data_seek'          => 'mysql_data_seek',
		'error'              => 'mysql_error',
		'errno'              => 'mysql_errno',
		'affected_rows'      => 'mysql_affected_rows',
		'num_rows'           => 'mysql_num_rows',
		'num_fields'         => 'mysql_num_fields',
		'field_name'         => 'mysql_field_name',
		'insert_id'          => 'mysql_insert_id',
		'escape_string'      => 'mysql_escape_string',
		'real_escape_string' => 'mysql_real_escape_string',
		'close'              => 'mysql_close',
		'client_encoding'    => 'mysql_client_encoding',
		'get_server_info'	 => 'mysql_get_server_info',
	);

	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Array of constants for use in fetch_array
	*
	* @var	array
	*/
	var $fetchtypes = array(
		DBARRAY_NUM   => MYSQL_NUM,
		DBARRAY_ASSOC => MYSQL_ASSOC,
		DBARRAY_BOTH  => MYSQL_BOTH
	);

	/**
	* Full name of the system
	*
	* @var	string
	*/
	var $appname = 'vBulletin';

	/**
	* Short name of the system
	*
	* @var	string
	*/
	var $appshortname = 'vBulletin';

	/**
	* Database name
	*
	* @var	string
	*/
	var $database = null;

	/**
	* Link variable. The connection to the master/write server.
	*
	* @var	string
	*/
	var $connection_master = null;

	/**
	* Link variable. The connection to the slave/read server(s).
	*
	* @var	string
	*/
	var $connection_slave = null;

	/**
	* Link variable. The connection last used.
	*
	* @var	string
	*/
	var $connection_recent = null;

	/**
	* Whether or not we will be using different connections for read and write queries
	*
	* @var	boolean
	*/
	var $multiserver = false;

	/**
	* Array of queries to be executed when the script shuts down
	*
	* @var	array
	*/
	var $shutdownqueries = array();

	/**
	* The contents of the most recent SQL query string.
	*
	* @var	string
	*/
	var $sql = '';

	/**
	* Whether or not to show and halt on database errors
	*
	* @var	boolean
	*/
	var $reporterror = true;

	/**
	* The text of the most recent database error message
	*
	* @var	string
	*/
	var $error = '';

	/**
	* The error number of the most recent database error message
	*
	* @var	integer
	*/
	var $errno = '';

	/**
	* SQL Query String
	*
	* @var	integer	The maximum size of query string permitted by the master server
	*/
	var $maxpacket = 0;

	/**
	* Track lock status of tables. True if a table lock has been issued
	*
	* @var	bool
	*/
	var $locked = false;

	/**
	* Number of queries executed
	*
	* @var	integer	The number of SQL queries run by the system
	*/
	var $querycount = 0;

	/**
	* Number of queries executed
	*
	* @var	integer	The number of SQL queries run by the system
	*/
	var $realescape = false;

	/**
	* Constructor. If x_real_escape_string() is available, switches to use that
	* function over x_escape_string().
	*
	* @param	vB_Registry	Registry object
	*/
	function __construct(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Database::Registry object is not an object", E_USER_ERROR);
		}

		if (function_exists($this->functions['real_escape_string']))
		{
			$this->realescape = true;
		}
	}

	/**
	* Connects to the specified database server(s)
	*
	* @param	string	Name of the database that we will be using for select_db()
	* @param	string	Name of the master (write) server - should be either 'localhost' or an IP address
	* @param	integer	Port for the master server
	* @param	string	Username to connect to the master server
	* @param	string	Password associated with the username for the master server
	* @param	boolean	Whether or not to use persistent connections to the master server
	* @param	string	(Optional) Name of the slave (read) server - should be either left blank or set to 'localhost' or an IP address, but NOT the same as the servername for the master server
	* @param	integer	(Optional) Port of the slave server
	* @param	string	(Optional) Username to connect to the slave server
	* @param	string	(Optional) Password associated with the username for the slave server
	* @param	boolean	(Optional) Whether or not to use persistent connections to the slave server
	* @param	string	(Optional) Parse given MySQL config file to set options
	* @param	string	(Optional) Connection Charset MySQLi / PHP 5.1.0+ or 5.0.5+ / MySQL 4.1.13+ or MySQL 5.1.10+ Only
	*
	* @return	none
	*/
	function connect($database, $w_servername, $w_port, $w_username, $w_password, $w_usepconnect = false, $r_servername = '', $r_port = 3306, $r_username = '', $r_password = '', $r_usepconnect = false, $configfile = '', $charset = '')
	{
		$this->database = $database;

		$w_port = $w_port ? $w_port : 3306;
		$r_port = $r_port ? $r_port : 3306;

		$this->connection_master = $this->db_connect($w_servername, $w_port, $w_username, $w_password, $w_usepconnect, $configfile, $charset);
		$this->multiserver = false;
		$this->connection_slave =& $this->connection_master;

		if ($this->connection_master)
		{
			$this->select_db($this->database);
		}
	}

	/**
	* Initialize database connection(s)
	*
	* Connects to the specified master database server, and also to the slave server if it is specified
	*
	* @param	string	Name of the database server - should be either 'localhost' or an IP address
	* @param	integer	Port of the database server (usually 3306)
	* @param	string	Username to connect to the database server
	* @param	string	Password associated with the username for the database server
	* @param	boolean	Whether or not to use persistent connections to the database server
	* @param	string  Not applicable; config file for MySQLi only
	* @param	string  Force connection character set (to prevent collation errors)
	*
	* @return	boolean
	*/
	function db_connect($servername, $port, $username, $password, $usepconnect, $configfile = '', $charset = '')
	{
		if (function_exists('catch_db_error'))
		{
			set_error_handler('catch_db_error');
		}

		// catch_db_error will handle exiting, no infinite loop here
		do
		{
			$link = $this->functions[$usepconnect ? 'pconnect' : 'connect']("$servername:$port", $username, $password);
		}
		while ($link == false AND $this->reporterror);

		restore_error_handler();

		if (!empty($charset))
		{
			if (function_exists('mysql_set_charset'))
			{
				mysql_set_charset($charset);
			}
			else
			{
				$this->sql = "SET NAMES $charset";
				$this->execute_query(true, $link);
			}
		}

		return $link;
	}

	/**
	* Selects a database to use
	*
	* @param	string	The name of the database located on the database server(s)
	*
	* @return	boolean
	*/
	function select_db($database = '')
	{
		if ($database != '')
		{
			$this->database = $database;
		}

		if ($check_write = @$this->select_db_wrapper($this->database, $this->connection_master))
		{
			$this->connection_recent =& $this->connection_master;
			return true;
		}
		else
		{
			$this->connection_recent =& $this->connection_master;
			$this->halt('Cannot use database ' . $this->database);
			return false;
		}
	}

	/**
	* Simple wrapper for select_db(), to allow argument order changes
	*
	* @param	string	Database name
	* @param	integer	Link identifier
	*
	* @return	boolean
	*/
	function select_db_wrapper($database = '', $link = null)
	{
		return $this->functions['select_db']($database, $link);
	}

	/**
	* Forces the sql mode varaible to a specific mode. Certain modes may be
	* incompatible with vBulletin. We dont generally use this function.
	*
	* @param	string	The mode we want to set.
	*/
	function force_sql_mode($mode)
	{
		$reset_errors = $this->reporterror;

		if ($reset_errors)
		{
			$this->hide_errors();
		}

		if (is_string($mode))
		{
			$this->query_write("SET @@sql_mode = '" . $this->escape_string($mode) . "'");
		}

		if ($reset_errors)
		{
			$this->show_errors();
		}
	}

	/**
	* Executes an SQL query through the specified connection
	*
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	* @param	string	The connection ID to the database server
	*
	* @return	string
	*/
	function execute_query($buffered = true, &$link)
	{
		$this->connection_recent =& $link;
		$this->querycount++;

		if ($queryresult = $this->functions[$buffered ? 'query' : 'query_unbuffered']($this->sql, $link))
		{
			// unset $sql to lower memory .. this isn't an error, so it's not needed
			$this->sql = '';

			return $queryresult;
		}
		else
		{
			$this->halt();

			// unset $sql to lower memory .. error will have already been thrown
			$this->sql = '';
		}
	}

	/**
	* Executes a data-writing SQL query through the 'master' database connection
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	*
	* @return	string
	*/
	function query_write($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes a data-reading SQL query through the 'master' database connection
	* we don't know if the 'read' database is up to date so be on the safe side
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	*
	* @return	string
	*/
	function query_read($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes a data-reading SQL query through the 'slave' database connection
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	*
	* @return	string
	*/
	function query_read_slave($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes an SQL query, using either the write connection
	*
	* @deprecated	Deprecated as of 3.6. Use query_(read/write)
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	string
	*/
	function query($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes a data-reading SQL query, then returns an array of the data from the first row from the result set
	*
	* @param	string	The text of the SQL query to be executed
	* @param	string	One of (NUM, ASSOC, BOTH)
	*
	* @return	array
	*/
	function &query_first($sql, $type = DBARRAY_ASSOC)
	{
		$this->sql =& $sql;
		$queryresult = $this->execute_query(true, $this->connection_master);
		$returnarray = $this->fetch_array($queryresult, $type);
		$this->free_result($queryresult);
		return $returnarray;
	}

	/**
	* Executes a FOUND_ROWS query to get the results of SQL_CALC_FOUND_ROWS
	*
	* @return	integer
	*/
	function found_rows()
	{
		$this->sql = "SELECT FOUND_ROWS()";
		$queryresult = $this->execute_query(true, $this->connection_recent);
		$returnarray = $this->fetch_array($queryresult, DBARRAY_NUM);
		$this->free_result($queryresult);

		return intval($returnarray[0]);
	}

	/**
	* Executes a data-reading SQL query against the slave server, then returns an array of the data from the first row from the result set
	*
	* @param	string	The text of the SQL query to be executed
	* @param	string	One of (NUM, ASSOC, BOTH)
	*
	* @return	array
	*/
	function &query_first_slave($sql, $type = DBARRAY_ASSOC)
	{
		$returnarray = $this->query_first($sql, $type);
		return $returnarray;
	}

	/**
	* Executes an INSERT INTO query, using extended inserts if possible
	*
	* @param	string	Name of the table into which data should be inserted
	* @param	string	Comma-separated list of the fields to affect
	* @param	array	Array of SQL values
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	mixed
	*/
	function &query_insert($table, $fields, &$values, $buffered = true)
	{
		return $this->insert_multiple("INSERT INTO $table $fields VALUES", $values, $buffered);
	}

	/**
	* Executes a REPLACE INTO query, using extended inserts if possible
	*
	* @param	string	Name of the table into which data should be inserted
	* @param	string	Comma-separated list of the fields to affect
	* @param	array	Array of SQL values
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	mixed
	*/
	function &query_replace($table, $fields, &$values, $buffered = true)
	{
		return $this->insert_multiple("REPLACE INTO $table $fields VALUES", $values, $buffered);
	}

	/**
	* Executes an INSERT or REPLACE query with multiple values, splitting large queries into manageable chunks based on $this->maxpacket
	*
	* @param	string	The text of the first part of the SQL query to be executed - example "INSERT INTO table (field1, field2) VALUES"
	* @param	mixed	The values to be inserted. Example: (0 => "('value1', 'value2')", 1 => "('value3', 'value4')")
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	mixed
	*/
	function insert_multiple($sql, &$values, $buffered)
	{
		if ($this->maxpacket == 0)
		{
			// must do a READ query on the WRITE link here!
			$vars = $this->query_write("SHOW VARIABLES LIKE 'max_allowed_packet'");
			$var = $this->fetch_row($vars);
			$this->maxpacket = $var[1];
			$this->free_result($vars);
		}

		$i = 0;
		$num_values = sizeof($values);
		$this->sql = $sql;

		while ($i < $num_values)
		{
			$sql_length = strlen($this->sql);
			$value_length = strlen("\r\n" . $values["$i"] . ",");

			if (($sql_length + $value_length) < $this->maxpacket)
			{
				$this->sql .= "\r\n" . $values["$i"] . ",";
				unset($values["$i"]);
				$i++;
			}
			else
			{
				$this->sql = (substr($this->sql, -1) == ',') ? substr($this->sql, 0, -1) : $this->sql;
				$this->execute_query($buffered, $this->connection_master);
				$this->sql = $sql;
			}
		}
		if ($this->sql != $sql)
		{
			$this->sql = (substr($this->sql, -1) == ',') ? substr($this->sql, 0, -1) : $this->sql;
			$this->execute_query($buffered, $this->connection_master);
		}

		if (sizeof($values) == 1)
		{
			return $this->insert_id();
		}
		else
		{
			return true;
		}
	}

	/**
	* Registers an SQL query to be executed at shutdown time. If shutdown functions are disabled, the query is run immediately.
	*
	* @param	string	The text of the SQL query to be executed
	* @param	mixed	(Optional) Allows particular shutdown queries to be labelled
	*
	* @return	boolean
	*/
	function shutdown_query($sql, $arraykey = -1)
	{
		if ($arraykey === -1)
		{
			$this->shutdownqueries[] = $sql;
			return true;
		}
		else
		{
			$this->shutdownqueries["$arraykey"] = $sql;
			return true;
		}
	}

	/**
	* Returns the number of rows contained within a query result set
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	integer
	*/
	function num_rows($queryresult)
	{
		return @$this->functions['num_rows']($queryresult);
	}

	/**
	* Returns the number of fields contained within a query result set
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	integer
	*/
	function num_fields($queryresult)
	{
		return @$this->functions['num_fields']($queryresult);
	}

	/**
	* Returns the name of a field from within a query result set
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	The index position of the field
	*
	* @return	string
	*/
	function field_name($queryresult, $index)
	{
		return @$this->functions['field_name']($queryresult, $index);
	}

	/**
	* Returns the ID of the item just inserted into an auto-increment field
	*
	* @return	integer
	*/
	function insert_id()
	{
		return @$this->functions['insert_id']($this->connection_master);
	}

	/**
	* Returns the name of the character set
	*
	* @return	string
	*/
	function client_encoding()
	{
		return @$this->functions['client_encoding']($this->connection_master);
	}

	/**
	* Closes the connection to the database server
	*
	* @return	integer
	*/
	function close()
	{
		return @$this->functions['close']($this->connection_master);
	}

	/**
	* Escapes a string to make it safe to be inserted into an SQL query
	*
	* @param	string	The string to be escaped
	*
	* @return	string
	*/
	function escape_string($string)
	{
		if ($this->realescape)
		{
			return $this->functions['real_escape_string']($string, $this->connection_master);
		}

		return $this->functions['escape_string']($string);
	}

	/**
	* Escapes a string using the appropriate escape character for the RDBMS for use in LIKE conditions
	*
	* @param	string	The string to be escaped
	*
	* @return	string
	*/
	function escape_string_like($string)
	{
		return str_replace(array('%', '_') , array('\%' , '\_') , $this->escape_string($string));
	}

	/**
	* Takes a piece of data and prepares it to be put into an SQL query by adding quotes etc.
	*
	* @param	mixed	The data to be used
	*
	* @return	mixed	The prepared data
	*/
	function sql_prepare($value)
	{
		if (is_string($value))
		{
			return "'" . $this->escape_string($value) . "'";
		}
		else if (is_numeric($value) AND floatval($value) == $value)
		{
			return $value;
		}
		else if (is_bool($value))
		{
			return $value ? 1 : 0;
		}
		else
		{
			return "'" . $this->escape_string($value) . "'";
		}
	}

	/**
	* Fetches a row from a query result and returns the values from that row as an array
	*
	* The value of $type defines whether the array will have numeric or associative keys, or both
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	One of DBARRAY_ASSOC / DBARRAY_NUM / DBARRAY_BOTH
	*
	* @return	array
	*/
	function fetch_array($queryresult, $type = DBARRAY_ASSOC)
	{
		return @$this->functions['fetch_array']($queryresult, $this->fetchtypes["$type"]);
	}

	/**
	* Fetches a row from a query result and returns the values from that row as an array with numeric keys
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	array
	*/
	function fetch_row($queryresult)
	{
		return @$this->functions['fetch_row']($queryresult);
	}

	/**
	* Fetches a row information from a query result and returns the values from that row as an array
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	array
	*/
	function fetch_field($queryresult)
	{
		return @$this->functions['fetch_field']($queryresult);
	}

	/**
	* Moves the internal result pointer within a query result set
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	The position to which to move the pointer (first position is 0)
	*
	* @return	boolean
	*/
	function data_seek($queryresult, $index)
	{
		return @$this->functions['data_seek']($queryresult, $index);
	}

	/**
	* Frees all memory associated with the specified query result
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	boolean
	*/
	function free_result($queryresult)
	{
		$this->sql = '';
		return @$this->functions['free_result']($queryresult);
	}

	/**
	* Retuns the number of rows affected by the most recent insert/replace/update query
	*
	* @return	integer
	*/
	function affected_rows()
	{
		$this->rows = $this->functions['affected_rows']($this->connection_recent);
		return $this->rows;
	}

	/**
	* Lock tables
	*
	* @param	mixed	List of tables to lock
	* @param	string	Type of lock to perform
	*
	*/
	function lock_tables($tablelist)
	{
		if (!empty($tablelist) AND is_array($tablelist))
		{
			$sql = '';
			foreach($tablelist AS $name => $type)
			{
				$sql .= (!empty($sql) ? ', ' : '') . TABLE_PREFIX . $name . " " . $type;
			}

			$this->query_write("LOCK TABLES $sql");
			$this->locked = true;
		}
	}

	/**
	* Unlock tables
	*
	*/
	function unlock_tables()
	{
		// Must be called from exec_shutdown as tables can get stuck locked if pconnects are enabled
		// Note: the above case never actually happens as we skip the lock if pconnects are enabled (to be safe)
		if ($this->locked)
		{
			$this->query_write("UNLOCK TABLES");
		}
	}

	/**
	* Returns the text of the error message from previous database operation
	*
	* @return	string
	*/
	function error()
	{
		if ($this->connection_recent === null)
		{
			$this->error = '';
		}
		else
		{
			$this->error = $this->functions['error']($this->connection_recent);
		}
		return $this->error;
	}

	/**
	* Returns the numerical value of the error message from previous database operation
	*
	* @return	integer
	*/
	function errno()
	{
		if ($this->connection_recent === null)
		{
			$this->errno = 0;
		}
		else
		{
			$this->errno = $this->functions['errno']($this->connection_recent);
		}
		return $this->errno;
	}

	/**
	* Switches database error display ON
	*/
	function show_errors()
	{
		$this->reporterror = true;
	}

	/**
	* Switches database error display OFF
	*/
	function hide_errors()
	{
		$this->reporterror = false;
	}

	/**
	* Halts execution of the entire system and displays an error message
	*
	* @param	string	Text of the error message. Leave blank to use $this->sql as error text.
	*
	* @return	integer
	*/
	function halt($errortext = '')
	{
		global $vbulletin;

		if ($this->connection_recent)
		{
			$this->error = $this->error($this->connection_recent);
			$this->errno = $this->errno($this->connection_recent);
		}

		if (VB_AREA == 'Upgrade' AND $this->errno == 1049)
		{
			// No database found, need to install
			require_once(DIR . '/includes/functions.php');
			exec_header_redirect('install.php');
		}

		if ($this->reporterror)
		{
			if ($errortext == '')
			{
				$this->sql = "Invalid SQL:\r\n" . chop($this->sql) . ';';
				$errortext =& $this->sql;
			}

			if (!headers_sent())
			{
				if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
				{
					header('Status: 503 Service Unavailable');
				}
				else
				{
					header($_SERVER['SERVER_PROTOCOL'] . ' 503 Service Unavailable');
				}
			}

			// Try and stop e-mail flooding.
			if (!$vbulletin->options['disableerroremail'])
			{
				$tempdir = @sys_get_temp_dir();
				$unique = 'vb'.md5(COOKIE_SALT).'.err';
				$tempfile = realpath($tempdir).DIRECTORY_SEPARATOR.$unique;

				/* If its less than a minute since the last e-mail
				and the error code is the same as last time, disable e-mail */
				if ($data = @file_get_contents($tempfile)) 
				{
					$errc = intval(substr($data, 10));
					$time = intval(substr($data, 0, 10));
					if ($time AND (TIMENOW - $time) < 60 
						AND intval($this->errno) == $errc)
					{
						$vbulletin->options['disableerroremail'] = true;
					}
					else
					{
						$data = TIMENOW.intval($this->errno);
						@file_put_contents($tempfile, $data);
					}
				}
				else
				{
					$data = TIMENOW.intval($this->errno);
					@file_put_contents($tempfile, $data);
				}
			}

			$vboptions      =& $vbulletin->options;
			$technicalemail =& $vbulletin->config['Database']['technicalemail'];
			$bbuserinfo     =& $vbulletin->userinfo;
			$requestdate    = date('l, F jS Y @ h:i:s A', TIMENOW);
			$date           = date('l, F jS Y @ h:i:s A');
			$scriptpath     = str_replace('&amp;', '&', $vbulletin->scriptpath);
			$referer        = REFERRER;
			$ipaddress      = IPADDRESS;
			$classname      = get_class($this);

			if ($this->connection_recent)
			{
				$this->hide_errors();
				list($mysqlversion) = $this->query_first("SELECT VERSION() AS version", DBARRAY_NUM);
				$this->show_errors();
			}

			$display_db_error = (VB_AREA == 'Upgrade' OR VB_AREA == 'Install' OR $vbulletin->userinfo['usergroupid'] == 6 OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions));

			// Hide the MySQL Version if its going in the source
			if (!$display_db_error)
			{
				$mysqlversion = '';
			}

			eval('$message = "' . str_replace('"', '\"', file_get_contents(DIR . '/includes/database_error_message.html')) . '";');

			// add a backtrace to the message
			$trace_output = "\n";
			if ($vbulletin->debug OR ($technicalemail != '' AND !$vbulletin->options['disableerroremail']))
			{
				$trace = debug_backtrace();

				foreach ($trace AS $index => $trace_item)
				{
					$param = (in_array($trace_item['function'], array('require', 'require_once', 'include', 'include_once')) ? $trace_item['args'][0] : '');

					// remove path
					$param = str_replace(DIR, '[path]', $param);
					$trace_item['file'] = str_replace(DIR, '[path]', $trace_item['file']);

					$trace_output .= "#$index $trace_item[class]$trace_item[type]$trace_item[function]($param) called in $trace_item[file] on line $trace_item[line]\n";
				}
			}

			if ($vbulletin->debug)
			{
				$message .= "\n\nStack Trace:\n$trace_output\n";
			}

			require_once(DIR . '/includes/functions_log_error.php');
			if (function_exists('log_vbulletin_error'))
			{
				log_vbulletin_error($message, 'database');
			}

			if ($technicalemail != '' AND !$vbulletin->options['disableerroremail'] AND verify_email_vbulletin_error($this->errno, 'database'))
			{
				$bodytext = ($vbulletin->debug ? $message : "$message\n\nStack Trace:\n$trace_output\n");

				// If vBulletinHook is defined then we know that options are loaded, so we can then use vbmail
				if (class_exists('vBulletinHook'))
				{
					@vbmail($technicalemail, $this->appshortname . ' Database Error!', $bodytext, true, $technicalemail);
				}
				else
				{
					@mail($technicalemail, $this->appshortname . ' Database Error!', preg_replace("#(\r\n|\r|\n)#s", (@ini_get('sendmail_path') === '') ? "\r\n" : "\n", $bodytext), "From: $technicalemail");
				}
			}

			if ($display_db_error)
			{
				// display error message on screen
				$message = '<form><textarea rows="15" cols="70" wrap="off" id="message">' . htmlspecialchars_uni($message) . '</textarea></form>';
			}
			else
			{
				// display hidden error message
				$message = "\r\n<!--\r\n" . htmlspecialchars_uni($message) . "\r\n-->\r\n";
			}

			if ($vbulletin->options['bburl'])
			{
				$imagepath = $vbulletin->options['bburl'];
			}
			else
			{
				// this might not work with too many slashes in the archive
				$imagepath = (VB_AREA == 'Forum' ? '.' : '..');
			}

			eval('$message = "' . str_replace('"', '\"', file_get_contents(DIR . '/includes/database_error_page.html')) . '";');
			// This is needed so IE doesn't show the pretty error messages
			$message .= str_repeat(' ', 512);
			die($message);
		}
		else if (!empty($errortext))
		{
			$this->error = $errortext;
		}
	}

	public function get_server_info()
	{
		return @$this->functions['get_server_info']($this->connection_master);
	}
}

// #############################################################################
// MySQLi Database Class

/**
* Class to interface with a MySQL 4.1 database
*
* This class also handles data replication between a master and slave(s) servers
*
* @package	vBulletin
*/
class vB_Database_MySQLi extends vB_Database
{
	/**
	* Array of function names, mapping a simple name to the RDBMS specific function name
	*
	* @var	array
	*/
	var $functions = array(
		'connect'            => 'mysqli_real_connect',
		'pconnect'           => 'mysqli_real_connect', // mysqli doesn't support persistent connections THANK YOU!
		'select_db'          => 'mysqli_select_db',
		'query'              => 'mysqli_query',
		'query_unbuffered'   => 'mysqli_unbuffered_query',
		'fetch_row'          => 'mysqli_fetch_row',
		'fetch_array'        => 'mysqli_fetch_array',
		'fetch_field'        => 'mysqli_fetch_field',
		'free_result'        => 'mysqli_free_result',
		'data_seek'          => 'mysqli_data_seek',
		'error'              => 'mysqli_error',
		'errno'              => 'mysqli_errno',
		'affected_rows'      => 'mysqli_affected_rows',
		'num_rows'           => 'mysqli_num_rows',
		'num_fields'         => 'mysqli_num_fields',
		'field_name'         => 'mysqli_field_tell',
		'insert_id'          => 'mysqli_insert_id',
		'escape_string'      => 'mysqli_real_escape_string',
		'real_escape_string' => 'mysqli_real_escape_string',
		'close'              => 'mysqli_close',
		'client_encoding'    => 'mysqli_client_encoding',
		'get_server_info'	 => 'mysqli_get_server_info',
	);

	/**
	* Array of constants for use in fetch_array
	*
	* @var	array
	*/
	var $fetchtypes = array(
		DBARRAY_NUM   => MYSQLI_NUM,
		DBARRAY_ASSOC => MYSQLI_ASSOC,
		DBARRAY_BOTH  => MYSQLI_BOTH
	);

	/**
	* Initialize database connection(s)
	*
	* Connects to the specified master database server, and also to the slave server if it is specified
	*
	* @param	string  Name of the database server - should be either 'localhost' or an IP address
	* @param	integer	Port of the database server - usually 3306
	* @param	string  Username to connect to the database server
	* @param	string  Password associated with the username for the database server
	* @param	string  Persistent Connections - Not supported with MySQLi
	* @param	string  Configuration file from config.php.ini (my.ini / my.cnf)
	* @param	string  Mysqli Connection Charset PHP 5.1.0+ or 5.0.5+ / MySQL 4.1.13+ or MySQL 5.1.10+ Only
	*
	* @return	object  Mysqli Resource
	*/
	function db_connect($servername, $port, $username, $password, $usepconnect, $configfile = '', $charset = '')
	{
		if (function_exists('catch_db_error'))
		{
			set_error_handler('catch_db_error');
		}

		$link = mysqli_init();
		# Set Options Connection Options
		if (!empty($configfile))
		{
			mysqli_options($link, MYSQLI_READ_DEFAULT_FILE, $configfile);
		}

		// this will execute at most 5 times, see catch_db_error()
		do
		{
			$connect = $this->functions['connect']($link, $servername, $username, $password, '', $port);
		}
		while ($connect == false AND $this->reporterror);

		restore_error_handler();

		if (!empty($charset))
		{
			if (function_exists('mysqli_set_charset'))
			{
				mysqli_set_charset($link, $charset);
			}
			else
			{
				$this->sql = "SET NAMES $charset";
				$this->execute_query(true, $link);
			}
		}

		return (!$connect) ? false : $link;
	}

	/**
	* Executes an SQL query through the specified connection
	*
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	* @param	string	The connection ID to the database server
	*
	* @return	string
	*/
	function execute_query($buffered = true, &$link)
	{
		$this->connection_recent =& $link;
		$this->querycount++;

		if ($queryresult = mysqli_query($link, $this->sql, ($buffered ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT)))
		{
			// unset $sql to lower memory .. this isn't an error, so it's not needed
			$this->sql = '';

			return $queryresult;
		}
		else
		{
			$this->halt();

			// unset $sql to lower memory .. error will have already been thrown
			$this->sql = '';
		}
	}

	/**
	* Simple wrapper for select_db(), to allow argument order changes
	*
	* @param	string	Database name
	* @param	integer	Link identifier
	*
	* @return	boolean
	*/
	function select_db_wrapper($database = '', $link = null)
	{
		return $this->functions['select_db']($link, $database);
	}

	/**
	* Escapes a string to make it safe to be inserted into an SQL query
	*
	* @param	string	The string to be escaped
	*
	* @return	string
	*/
	function escape_string($string)
	{
		return $this->functions['real_escape_string']($this->connection_master, $string);
	}

	/**
	* Returns the name of a field from within a query result set
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	The index position of the field
	*
	* @return	string
	*/
	function field_name($queryresult, $index)
	{
		$field = @$this->functions['fetch_field']($queryresult);
		return $field->name;
	}
}

// #############################################################################
// datastore class

/**
* Class for fetching and initializing the vBulletin datastore from the database
*
* @package	vBulletin
*/
class vB_Datastore
{
	/**
	* Default items that are always loaded by fetch();
	*
	* @var	array
	*/
	var $defaultitems = array(
		'options',
		'bitfields',
		'attachmentcache',
		'forumcache',
		'usergroupcache',
		'stylecache',
		'languagecache',
		'products',
		'pluginlist',
		'cron',
		'profilefield',
		'loadcache',
		'noticecache'
	);

	/**
	* This variable contains a list of all items to be returned from the datastore
	*
	* @var    array
	*/
	var $itemarray = array();

	/**
	* This variable should be set to be a reference to the registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* This variable should be set to be a reference to the database object
	*
	* @var	vB_Database
	*/
	var $dbobject = null;

	/**
	* Unique prefix for item's title, required for multiple forums on the same server using the same classes that read/write to memory
	*
	* @var	string
	*/
	var $prefix = '';

	/**
	* Constructor - establishes the database object to use for datastore queries
	*
	* @param	vB_Registry	The registry object
	* @param	vB_Database	The database object
	*/
	function __construct(&$registry, &$dbobject)
	{
		$this->registry =& $registry;
		$this->dbobject =& $dbobject;

		$this->prefix = isset($this->registry->config['Datastore']['prefix']) ? $this->registry->config['Datastore']['prefix'] : '';

		if (defined('SKIP_DEFAULTDATASTORE'))
		{
			$this->defaultitems = array('options', 'bitfields', 'pluginlist');
		}

		if (!is_object($registry))
		{
			trigger_error('<strong>vB_Datastore</strong>: $this->registry is not an object', E_USER_ERROR);
		}
		if (!is_object($dbobject))
		{
			trigger_error('<strong>vB_Datastore</strong>: $this->dbobject is not an object!', E_USER_ERROR);
		}
	}

	/**
	* Sorts the data returned from the cache and places it into appropriate places
	*
	* @param	string	The name of the data item to be processed
	* @param	mixed	The data associated with the title
	* @param	integer	If the data needs to be unserialized, 0 = no, 1 = yes, 2 = auto detect
	*
	* @return	boolean
	*/
	function register($title, $data, $unserialize_detect = 2)
	{
		// specifies whether or not $data should be an array
		$try_unserialize = (($unserialize_detect == 2) 
		AND is_string($data) AND (substr($data,0,2) == 'a:'));

		if ($try_unserialize OR $unserialize_detect == 1)
		{
			// unserialize returned an error so return false
			if (($data = vb_unserialize($data)) === false)
			{
				return false;
			}
		}

		if ($title == 'bitfields')
		{
			$registry =& $this->registry;

			foreach (array_keys($data) AS $group)
			{
				$registry->{'bf_' . $group} =& $data["$group"];

				$group_prefix = 'bf_' . $group . '_';
				$group_info =& $data["$group"];

				foreach (array_keys($group_info) AS $subgroup)
				{
					$registry->{$group_prefix . $subgroup} =& $group_info["$subgroup"];
				}
			}
		}
		else if (!empty($title))
		{
			$this->registry->$title = $data;
		}

		return true;
	}

	/**
	* Fetches the contents of the datastore from the database
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($itemarray)
	{
		$db =& $this->dbobject;

		$itemlist = "''";

		foreach ($this->defaultitems AS $item)
		{
			$itemlist .= ",'" . $db->escape_string($item) . "'";
		}

		if (is_array($itemarray))
		{
			foreach ($itemarray AS $item)
			{
				$itemlist .= ",'" . $db->escape_string($item) . "'";
			}
		}

		$this->do_db_fetch($itemlist);

		$this->check_options();

		// set the version number variable
		$this->registry->versionnumber =& $this->registry->options['templateversion'];
	}

	/**
	* Performs the actual fetching of the datastore items for the database, child classes may use this
	*
	* @param	string	title of the datastore item
	*
	* @return	void
	*/
	function do_db_fetch($itemlist)
	{
		$db =& $this->dbobject;

		$dataitems = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "datastore
			WHERE title IN ($itemlist)
		");
		while ($dataitem = $db->fetch_array($dataitems))
		{
			$this->register($dataitem['title'], $dataitem['data'], (isset($dataitem['unserialize']) ? $dataitem['unserialize'] : 2));
		}
		$db->free_result($dataitems);
	}

	/**
	* Checks that the options item has come out of the datastore correctly
	* and sets the 'versionnumber' variable
	*/
	function check_options()
	{
		if (!isset($this->registry->options['templateversion']))
		{
			// fatal error - options not loaded correctly
			require_once(DIR . '/includes/adminfunctions.php');
			require_once(DIR . '/includes/functions.php');
			$this->register('options', build_options(), 0);
		}

		// set the short version number
		$this->registry->options['simpleversion'] = SIMPLE_VERSION . (isset($this->registry->config['Misc']['jsver']) ? $this->registry->config['Misc']['jsver'] : '');

		// set the version number variable
		$this->registry->versionnumber =& $this->registry->options['templateversion'];
	}
}

// #############################################################################
// input handler class

/**#@+
* Ways of cleaning input. Should be mostly self-explanatory.
*/
define('TYPE_NOCLEAN',      0); // no change

define('TYPE_BOOL',     1); // force boolean
define('TYPE_INT',      2); // force integer
define('TYPE_UINT',     3); // force unsigned integer
define('TYPE_NUM',      4); // force number
define('TYPE_UNUM',     5); // force unsigned number
define('TYPE_UNIXTIME', 6); // force unix datestamp (unsigned integer)
define('TYPE_STR',      7); // force trimmed string
define('TYPE_NOTRIM',   8); // force string - no trim
define('TYPE_NOHTML',   9); // force trimmed string with HTML made safe
define('TYPE_ARRAY',   10); // force array
define('TYPE_FILE',    11); // force file
define('TYPE_BINARY',  12); // force binary string
define('TYPE_NOHTMLCOND', 13); // force trimmed string with HTML made safe if determined to be unsafe

define('TYPE_ARRAY_BOOL',     101);
define('TYPE_ARRAY_INT',      102);
define('TYPE_ARRAY_UINT',     103);
define('TYPE_ARRAY_NUM',      104);
define('TYPE_ARRAY_UNUM',     105);
define('TYPE_ARRAY_UNIXTIME', 106);
define('TYPE_ARRAY_STR',      107);
define('TYPE_ARRAY_NOTRIM',   108);
define('TYPE_ARRAY_NOHTML',   109);
define('TYPE_ARRAY_ARRAY',    110);
define('TYPE_ARRAY_FILE',     11);  // An array of "Files" behaves differently than other <input> arrays. TYPE_FILE handles both types.
define('TYPE_ARRAY_BINARY',   112);
define('TYPE_ARRAY_NOHTMLCOND',113);

define('TYPE_ARRAY_KEYS_INT', 202);
define('TYPE_ARRAY_KEYS_STR', 207);

define('TYPE_CONVERT_SINGLE', 100); // value to subtract from array types to convert to single types
define('TYPE_CONVERT_KEYS',   200); // value to subtract from array => keys types to convert to single types
/**#@-*/

// temporary
define('INT',        TYPE_INT);
define('STR',        TYPE_STR);
define('STR_NOHTML', TYPE_NOHTML);
define('FILE',       TYPE_FILE);

/**
* Class to handle and sanitize variables from GET, POST and COOKIE etc
*
* @package	vBulletin
*/
class vB_Input_Cleaner
{
	/**
	* Translation table for short name to long name
	*
	* @var    array
	*/
	var $shortvars = array(
		'f'     => 'forumid',
		't'     => 'threadid',
		'p'     => 'postid',
		'u'     => 'userid',
		'a'     => 'announcementid',
		'c'     => 'calendarid',
		'e'     => 'eventid',
		'q'		=> 'query',
		'pp'    => 'perpage',
		'page'  => 'pagenumber',
		'sort'  => 'sortfield',
		'order' => 'sortorder',
	);

	/**
	* Translation table for short superglobal name to long superglobal name
	*
	* @var     array
	*/
	var $superglobal_lookup = array(
		'g' => '_GET',
		'p' => '_POST',
		'r' => '_REQUEST',
		'c' => '_COOKIE',
		's' => '_SERVER',
		'e' => '_ENV',
		'f' => '_FILES'
	);

	/**
	* System state. The complete URL of the current page, without sessionhash
	*
	* @var	string
	*/
	var $scriptpath = '';

	/**
	* Reload URL. Complete URL of the current page including sessionhash
	*
	* @var	string
	*/
	var $reloadurl = '';

	/**
	* System state. The complete URL of the page for Who's Online purposes
	*
	* @var	string
	*/
	var $wolpath = '';

	/**
	* System state. The complete URL of the referring page
	*
	* @var	string
	*/
	var $url = '';

	/**
	* System state. The IP address of the current visitor
	*
	* @var	string
	*/
	var $ipaddress = '';

	/**
	* System state. An attempt to find a second IP for the current visitor (proxy etc)
	*
	* @var	string
	*/
	var $alt_ip = '';

	/**
	* A reference to the main registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Keep track of variables that have already been cleaned
	*
	* @var	array
	*/
	var $cleaned_vars = array();

	/**
	* Constructor
	*
	* First, reverses the effects of magic quotes on GPC
	* Second, translates short variable names to long (u --> userid)
	* Third, deals with $_COOKIE[userid] conflicts
	*
	* @param	vB_Registry	The instance of the vB_Registry object
	*/
	function __construct(&$registry)
	{
		$this->registry =& $registry;

		if (!is_array($GLOBALS))
		{
			trigger_error('Problem detected with $GLOBALS - not an array.', E_USER_WARNING);
		}

		// overwrite GET[x] and REQUEST[x] with POST[x] if it exists (overrides server's GPC order preference)
		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			foreach (array_keys($_POST) AS $key)
			{
				if (isset($_GET["$key"]))
				{
					$_GET["$key"] = $_REQUEST["$key"] = $_POST["$key"];
				}
			}
		}

		// deal with session bypass situation
		if (!defined('SESSION_BYPASS'))
		{
			define('SESSION_BYPASS', !empty($_REQUEST['bypass']));
		}

		foreach (array('_GET', '_POST') AS $arrayname)
		{
			if (isset($GLOBALS["$arrayname"]['do']))
			{
				$GLOBALS["$arrayname"]['do'] = trim($GLOBALS["$arrayname"]['do']);
			}

			$this->convert_shortvars($GLOBALS["$arrayname"]);
		}

		// set the AJAX flag if we have got an AJAX submission
		if ($_SERVER['REQUEST_METHOD'] == 'POST' AND !empty($_SERVER['HTTP_X_REQUESTED_WITH']) AND $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')
		{
			$_POST['ajax'] = $_REQUEST['ajax'] = 1;
		}

		// reverse the effects of register_globals if necessary
		if (@ini_get('register_globals') OR !@ini_get('gpc_order'))
		{
			foreach ($this->superglobal_lookup AS $arrayname)
			{
				$registry->superglobal_size["$arrayname"] = sizeof($GLOBALS["$arrayname"]);

				foreach (array_keys($GLOBALS["$arrayname"]) AS $varname)
				{
					// make sure we dont unset any global arrays like _SERVER
					if (!in_array($varname, $this->superglobal_lookup))
					{
						unset($GLOBALS["$varname"]);
					}
				}
			}
		}
		else
		{
			foreach ($this->superglobal_lookup AS $arrayname)
			{
				$registry->superglobal_size["$arrayname"] = sizeof($GLOBALS["$arrayname"]);
			}
		}

		// deal with cookies that may conflict with _GET and _POST data, and create our own _REQUEST with no _COOKIE input
		foreach (array_keys($_COOKIE) AS $varname)
		{
			unset($_REQUEST["$varname"]);
			if (isset($_POST["$varname"]))
			{
				$_REQUEST["$varname"] =& $_POST["$varname"];
			}
			else if (isset($_GET["$varname"]))
			{
				$_REQUEST["$varname"] =& $_GET["$varname"];
			}
		}

		// fetch client IP address
		$registry->ipaddress = compress_ip($this->fetch_ip());
		$registry->alt_ip = compress_ip($this->fetch_alt_ip());

		// resolve the request scheme
		if (isset($config['Misc']['vb_url_scheme']) 
			AND $config['Misc']['vb_url_scheme'])
		{
			$scheme = $config['Misc']['vb_url_scheme'];
		}
		else
		{
			$scheme = ((isset($_SERVER['HTTPS']) AND ($_SERVER['HTTPS'] == 'on' OR $_SERVER['HTTPS'] == '1')) ? 'https' : 'http');
		}

		// defines if the current page was visited via SSL or not
		define('REQ_PROTOCOL', $scheme);

		// fetch complete url of current page
		$registry->scriptpath = $this->fetch_scriptpath();
		if (!defined('SCRIPTPATH'))
		{
			define('SCRIPTPATH', $registry->scriptpath);
		}
		// fetch url of current page without the variable string
		$quest_pos = strpos($registry->scriptpath, '?');
		if ($quest_pos !== false)
		{
			$registry->script = substr($registry->scriptpath, 0, $quest_pos);
		}
		else
		{
			$registry->script = $registry->scriptpath;
		}
		define('SCRIPT', $registry->script);

		// fetch url of current page for Who's Online
		$registry->wolpath = $this->fetch_wolpath();
		define('WOLPATH', $registry->wolpath);

		// define some useful contants related to environment
		define('USER_AGENT', isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
		define('REFERRER', isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
	}

	/**
	* Makes data in an array safe to use
	*
	* @param	array	The source array containing the data to be cleaned
	* @param	array	Array of variable names and types we want to extract from the source array
	*
	* @return	array
	*/
	function &clean_array(&$source, $variables)
	{
		$return = array();

		foreach ($variables AS $varname => $vartype)
		{
			$return["$varname"] =& $this->clean($source["$varname"], $vartype, isset($source["$varname"]));
		}

		return $return;
	}

	/**
	* Makes GPC variables safe to use
	*
	* @param	string	Either, g, p, c, r or f (corresponding to get, post, cookie, request and files)
	* @param	array	Array of variable names and types we want to extract from the source array
	*
	* @return	array
	*/
	function clean_array_gpc($source, $variables)
	{
		$sg =& $GLOBALS[$this->superglobal_lookup["$source"]];

		foreach ($variables AS $varname => $vartype)
		{
			// clean a variable only once unless its a different type
			if (!isset($this->cleaned_vars["$varname"]) OR $this->cleaned_vars["$varname"] != $vartype)
			{
				$this->registry->GPC_exists["$varname"] = isset($sg["$varname"]);
				$this->registry->GPC["$varname"] =& $this->clean(
					$sg["$varname"],
					$vartype,
					isset($sg["$varname"])
				);
				$this->cleaned_vars["$varname"] = $vartype;
			}
		}
	}

	/**
	* Makes a single GPC variable safe to use and returns it
	*
	* @param	array	The source array containing the data to be cleaned
	* @param	string	The name of the variable in which we are interested
	* @param	integer	The type of the variable in which we are interested
	*
	* @return	mixed
	*/
	function &clean_gpc($source, $varname, $vartype = TYPE_NOCLEAN)
	{
		// clean a variable only once unless its a different type
		if (!isset($this->cleaned_vars["$varname"]) OR $this->cleaned_vars["$varname"] != $vartype)
		{
			$sg =& $GLOBALS[$this->superglobal_lookup["$source"]];

			$this->registry->GPC_exists["$varname"] = isset($sg["$varname"]);
			$this->registry->GPC["$varname"] =& $this->clean(
				$sg["$varname"],
				$vartype,
				isset($sg["$varname"])
			);
			$this->cleaned_vars["$varname"] = $vartype;
		}

		return $this->registry->GPC["$varname"];
	}

	/**
	* Makes a single variable safe to use and returns it
	*
	* @param	mixed	The variable to be cleaned
	* @param	integer	The type of the variable in which we are interested
	* @param	boolean	Whether or not the variable to be cleaned actually is set
	*
	* @return	mixed	The cleaned value
	*/
	function &clean(&$var, $vartype = TYPE_NOCLEAN, $exists = true)
	{
		if ($exists)
		{
			if ($vartype < TYPE_CONVERT_SINGLE)
			{
				$this->do_clean($var, $vartype);
			}
			else if (is_array($var))
			{
				if ($vartype >= TYPE_CONVERT_KEYS)
				{
					$var = array_keys($var);
					$vartype -=  TYPE_CONVERT_KEYS;
				}
				else
				{
					$vartype -= TYPE_CONVERT_SINGLE;
				}

				foreach (array_keys($var) AS $key)
				{
					$this->do_clean($var["$key"], $vartype);
				}
			}
			else
			{
				$var = array();
			}
			return $var;
		}
		else
		{
			if ($vartype < TYPE_CONVERT_SINGLE)
			{
				switch ($vartype)
				{
					case TYPE_INT:
					case TYPE_UINT:
					case TYPE_NUM:
					case TYPE_UNUM:
					case TYPE_UNIXTIME:
					{
						$var = 0;
						break;
					}
					case TYPE_STR:
					case TYPE_NOHTML:
					case TYPE_NOTRIM:
					case TYPE_NOHTMLCOND:
					{
						$var = '';
						break;
					}
					case TYPE_BOOL:
					{
						$var = 0;
						break;
					}
					case TYPE_ARRAY:
					case TYPE_FILE:
					{
						$var = array();
						break;
					}
					case TYPE_NOCLEAN:
					{
						$var = null;
						break;
					}
					default:
					{
						$var = null;
					}
				}
			}
			else
			{
				$var = array();
			}

			return $var;
		}
	}

	/**
	* Does the actual work to make a variable safe
	*
	* @param	mixed	The data we want to make safe
	* @param	integer	The type of the data
	*
	* @return	mixed
	*/
	function &do_clean(&$data, $type)
	{
		static $booltypes = array('1', 'yes', 'y', 'true');

		switch ($type)
		{
			case TYPE_INT:    $data = intval($data);                                   break;
			case TYPE_UINT:   $data = ($data = intval($data)) < 0 ? 0 : $data;         break;
			case TYPE_NUM:    $data = strval($data) + 0;                               break;
			case TYPE_UNUM:   $data = strval($data) + 0;
							  $data = ($data < 0) ? 0 : $data;                         break;
			case TYPE_BINARY: $data = strval($data);                                   break;
			case TYPE_STR:    $data = trim(strval($data));                             break;
			case TYPE_NOTRIM: $data = strval($data);                                   break;
			case TYPE_NOHTML: $data = htmlspecialchars_uni(trim(strval($data)));       break;
			case TYPE_BOOL:   $data = in_array(strtolower($data), $booltypes) ? 1 : 0; break;
			case TYPE_ARRAY:  $data = (is_array($data)) ? $data : array();             break;
			case TYPE_NOHTMLCOND:
			{
				$data = trim(strval($data));
				if (strcspn($data, '<>"') < strlen($data) OR (strpos($data, '&') !== false AND !preg_match('/&(#[0-9]+|amp|lt|gt|quot);/si', $data)))
				{
					// data is not htmlspecialchars because it still has characters or entities it shouldn't
					$data = htmlspecialchars_uni($data);
				}
				break;
			}
			case TYPE_FILE:
			{
				// perhaps redundant :p
				if (is_array($data))
				{
					if (is_array($data['name']))
					{
						$files = count($data['name']);
						for ($index = 0; $index < $files; $index++)
						{
							$data['name']["$index"] = trim(strval($data['name']["$index"]));
							$data['type']["$index"] = trim(strval($data['type']["$index"]));
							$data['tmp_name']["$index"] = trim(strval($data['tmp_name']["$index"]));
							$data['error']["$index"] = intval($data['error']["$index"]);
							$data['size']["$index"] = intval($data['size']["$index"]);
						}
					}
					else
					{
						$data['name'] = trim(strval($data['name']));
						$data['type'] = trim(strval($data['type']));
						$data['tmp_name'] = trim(strval($data['tmp_name']));
						$data['error'] = intval($data['error']);
						$data['size'] = intval($data['size']);
					}
				}
				else
				{
					$data = array(
						'name'     => '',
						'type'     => '',
						'tmp_name' => '',
						'error'    => 0,
						'size'     => 4, // UPLOAD_ERR_NO_FILE
					);
				}
				break;
			}
			case TYPE_UNIXTIME:
			{
				if (is_array($data))
				{
					$data = $this->clean($data, TYPE_ARRAY_UINT);
					if ($data['month'] AND $data['day'] AND $data['year'])
					{
						require_once(DIR . '/includes/functions_misc.php');
						$data = vbmktime($data['hour'], $data['minute'], $data['second'], $data['month'], $data['day'], $data['year']);
					}
					else
					{
						$data = 0;
					}
				}
				else
				{
					$data = ($data = intval($data)) < 0 ? 0 : $data;
				}
				break;
			}
			// null actions should be deifned here so we can still catch typos below
			case TYPE_NOCLEAN:
			{
				break;
			}

			default:
			{
				if ($this->registry->debug)
				{
					trigger_error('vB_Input_Cleaner::do_clean() Invalid data type specified', E_USER_WARNING);
				}
			}
		}

		// strip out characters that really have no business being in non-binary data
		switch ($type)
		{
			case TYPE_STR:
			case TYPE_NOTRIM:
			case TYPE_NOHTML:
			case TYPE_NOHTMLCOND:
				$data = str_replace(chr(0), '', $data);
		}

		return $data;
	}

	/**
	* Removes HTML characters and potentially unsafe scripting words from a string
	*
	* @param	string	The variable we want to make safe
	*
	* @return	string
	*/
	function xss_clean($var)
	{
		static
			$preg_find    = array('#^javascript#i', '#^vbscript#i'),
			$preg_replace = array('java script',   'vb script');

		return preg_replace($preg_find, $preg_replace, htmlspecialchars_uni(trim($var)));
	}

	/**
	* Works on an entire array of variables
	*
	* @param	array	The array on which we want to work
	*/
	function stripslashes_deep(&$value, $depth = 0)
	{
		if (is_array($value))
		{
		    foreach ($value AS $key => $val)
		    {
		        if (is_string($val))
		        {
		            $value["$key"] = stripslashes($val);
		        }
		        else if (is_array($val) AND $depth < 10)
		        {
		            $this->stripslashes_deep($value["$key"], $depth + 1);
		        }
		    }
		}
	}

	/**
	* Turns $_POST['t'] into $_POST['threadid'] etc.
	*
	* @param	array	The name of the array
	*/
	function convert_shortvars(&$array)
	{
		// extract long variable names from short variable names
		foreach ($this->shortvars AS $shortname => $longname)
		{
			if (isset($array["$shortname"]) AND !isset($array["$longname"]))
			{
				$array["$longname"] =& $array["$shortname"];
				$GLOBALS['_REQUEST']["$longname"] =& $array["$shortname"];
			}
		}
	}

	/**
	* Strips out the s=gobbledygook& rubbish from URLs
	*
	* @param	string	The URL string from which to remove the session stuff
	*
	* @return	string
	*/
	function strip_sessionhash($string)
	{
		$string = preg_replace('/(s|sessionhash)=[a-z0-9]{32}?&?/', '', $string);
		return $string;
	}

	/**
	* Fetches the 'scriptpath' variable - ie: the URI of the current page
	*
	* @return	string
	*/
	function fetch_scriptpath()
	{
		if ($this->registry->scriptpath != '')
		{
			return $this->registry->scriptpath;
		}
		else
		{
			if ($_SERVER['REQUEST_URI'] OR $_ENV['REQUEST_URI'])
			{
				$scriptpath = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : $_ENV['REQUEST_URI'];
			}
			else
			{
				if ($_SERVER['PATH_INFO'] OR $_ENV['PATH_INFO'])
				{
					$scriptpath = $_SERVER['PATH_INFO'] ? $_SERVER['PATH_INFO'] : $_ENV['PATH_INFO'];
				}
				else if ($_SERVER['REDIRECT_URL'] OR $_ENV['REDIRECT_URL'])
				{
					$scriptpath = $_SERVER['REDIRECT_URL'] ? $_SERVER['REDIRECT_URL'] : $_ENV['REDIRECT_URL'];
				}
				else
				{
					$scriptpath = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_ENV['PHP_SELF'];
				}

				if ($_SERVER['QUERY_STRING'] OR $_ENV['QUERY_STRING'])
				{
					$scriptpath .= '?' . ($_SERVER['QUERY_STRING'] ? $_SERVER['QUERY_STRING'] : $_ENV['QUERY_STRING']);
				}
			}

			// in the future we should set $registry->script here too
			$quest_pos = strpos($scriptpath, '?');
			if ($quest_pos !== false)
			{
				$script = urldecode(substr($scriptpath, 0, $quest_pos));
				$scriptpath = $script . substr($scriptpath, $quest_pos);
			}
			else
			{
				$scriptpath = urldecode($scriptpath);
			}

			// store a version that includes the sessionhash
			$this->registry->reloadurl = $this->xss_clean($scriptpath);

			$scriptpath = $this->strip_sessionhash($scriptpath);
			$scriptpath = $this->xss_clean($scriptpath);
			$this->registry->scriptpath = $scriptpath;

			return $scriptpath;
		}
	}

	/**
	* Fetches the 'wolpath' variable - ie: the same as 'scriptpath' but with a handler for the POST request method
	*
	* @return	string
	*/
	function fetch_wolpath()
	{
		$wolpath = $this->fetch_scriptpath();

		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			// Tag the variables back on to the filename if we are coming from POST so that WOL can access them.
			$tackon = '';

			if (is_array($_POST))
			{
				foreach ($_POST AS $varname => $value)
				{
					switch ($varname)
					{
						case 'forumid':
						case 'threadid':
						case 'postid':
						case 'userid':
						case 'eventid':
						case 'calendarid':
						case 'do':
						case 'method': // postings.php
						case 'dowhat': // private.php
						{
							$tackon .= ($tackon == '' ? '' : '&amp;') . $varname . '=' . $value;
							break;
						}
					}
				}
			}
			if ($tackon != '')
			{
				$wolpath .= (strpos($wolpath, '?') !== false ? '&amp;' : '?') . "$tackon";
			}
		}

		return $wolpath;
	}

	/**
	* Fetches the 'url' variable - usually the URL of the previous page in the history
	*
	* @return	string
	*/
	function fetch_url()
	{
		$temp_url = isset($_REQUEST['url']) ? $_REQUEST['url'] : '';

		$scriptpath = $this->fetch_scriptpath();

		if (empty($temp_url))
		{
			$url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		}
		else
		{
			if ($temp_url == $_SERVER['HTTP_REFERER'])
			{
				$url = 'index.php';
			}
			else
			{
				$url = $temp_url;
			}
		}

		if ($url == $scriptpath OR empty($url))
		{
			$url = 'index.php';
		}

		// if $url is set to forum home page, check it against options
		if ($url == 'index.php' AND $this->registry->options['forumhome'] != 'index')
		{
			$url = $this->registry->options['forumhome'] . '.php';
		}

		$url = $this->xss_clean($url);

		return $url;
	}

	/**
	* Fetches the IP address of the current visitor
	*
	* @return	string
	*/
	function fetch_ip()
	{
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	* Fetches an alternate IP address of the current visitor, attempting to detect proxies etc.
	*
	* @return	string
	*/
	function fetch_alt_ip()
	{
		$alt_ip = $_SERVER['REMOTE_ADDR'];

		if (isset($_SERVER['HTTP_CLIENT_IP']))
		{
			$altip = $_SERVER['HTTP_CLIENT_IP'];
		}
		else if (isset($_SERVER['HTTP_CF_CONNECTING_IP']))
		{	// CloudFlare 
			$altip = $_SERVER['HTTP_CF_CONNECTING_IP'];
		}
		else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			$split = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$altip = $split[0];
		}
		else
		{
			$altip = false;
		}

		if ($altip AND $this->filter_ip($altip))
		{
			$alt_ip = $altip;
		}

		return $alt_ip;
	}

	/**
	* Validate the IP address (both ipv4 & ipv6)
	* By default, this function does not allow private IP ranges.
	*
	* @return	string
	*/
	function filter_ip($ip, $allow_private = false)
	{
		return (get_iptype($ip, $allow_private) ? true : false);
	}
}

// #############################################################################
// data registry class

/**
* Class to store commonly-used variables
*
* @package	vBulletin
*/
class vB_Registry
{
	// general objects
	/**
	* Datastore object.
	*
	* @var	vB_Datastore
	*/
	var $datastore;

	/**
	* Input cleaner object.
	*
	* @var	vB_Input_Cleaner
	*/
	var $input;

	/**
	* Database object.
	*
	* @var	vB_Database
	*/
	var $db;

	// user/session related
	/**
	* Array of info about the current browsing user. In the case of a registered
	* user, this will be results of fetch_userinfo(). A guest will have slightly
	* different entries.
	*
	* @var	array
	*/
	var $userinfo;

	/**
	* Session object.
	*
	* @var vB_Session
	*/
	var $session;

	/**
	* Array of do actions that are exempt from checks
	*
	* @var array
	*/
	var $csrf_skip_list = array();

	// configuration
	/**
	* Array of data from config.php.
	*
	* @var	array
	*/
	var $config;

	// GPC input
	/**
	* Array of data that has been cleaned by the input cleaner.
	*
	* @var	array
	*/
	var $GPC = array();

	/**
	* Array of booleans. When cleaning a variable, you often lose the ability
	* to determine if it was specified in the user's input. Entries in this
	* array are true if the variable existed before cleaning.
	*
	* @var	array
	*/
	var $GPC_exists = array();

	/**
	* The size of the super global arrays.
	*
	* @var	array
	*/
	var $superglobal_size = array();

	// single variables
	/**
	* IP Address of the current browsing user.
	*
	* @var	string
	*/
	var $ipaddress;

	/**
	* Alternate IP for the browsing user. This attempts to use various HTTP headers
	* to find the real IP of a user that may be behind a proxy.
	*
	* @var	string
	*/
	var $alt_ip;

	/**
	* The URL of the currently browsed page.
	*
	* @var	string
	*/
	var $scriptpath;

	/**
	* Similar to the URL of the current page, but expands some items and includes
	* data submitted via POST. Used for Who's Online purposes.
	*
	* @var	string
	*/
	var $wolpath;

	/**
	* The URL of the current page, without anything after the '?'.
	*
	* @var	string
	*/
	var $script;

	/**
	* Generally the URL of the referring page if there is one, though it is often
	* set in various places of the code. Used to determine the page to redirect
	* to, if necessary.
	*
	* @var	string
	*/
	var $url;

	// usergroup permission bitfields
	/**#@+
	* Bitfield arrays for usergroup permissions.
	*
	* @var	array
	*/
	var $bf_ugp;
	// $bf_ugp_x is a reference to $bf_ugp['x']
	var $bf_ugp_adminpermissions;
	var $bf_ugp_calendarpermissions;
	var $bf_ugp_forumpermissions;
	var $bf_ugp_genericoptions;
	var $bf_ugp_genericpermissions;
	var $bf_ugp_pmpermissions;
	var $bf_ugp_wolpermissions;
	var $bf_ugp_visitormessagepermissions;
	/**#@-*/

	// misc bitfield arrays
	/**#@+
	* Bitfield arrays for miscellaneous permissions and options.
	*
	* @var	array
	*/
	var $bf_misc;
	// $bf_misc_x is a reference to $bf_misc['x']
	var $bf_misc_calmoderatorpermissions;
	var $bf_misc_forumoptions;
	var $bf_misc_intperms;
	var $bf_misc_languageoptions;
	var $bf_misc_moderatorpermissions;
	var $bf_misc_useroptions;
	var $bf_misc_hvcheck;
	/**#@-*/

	/**#@+
	* Results for specific entries in the datastore.
	*
	* @var	mixed	Mixed, though mostly arrays.
	*/
	var $options = null;
	var $attachmentcache = null;
	var $avatarcache = null;
	var $birthdaycache = null;
	var $eventcache = null;
	var $forumcache = null;
	var $iconcache = null;
	var $markupcache = null;
	var $stylecache = null;
	var $languagecache = null;
	var $smiliecache = null;
	var $usergroupcache = null;
	var $bbcodecache = null;
	var $socialsitecache = null;
	var $cron = null;
	var $mailqueue = null;
	var $banemail = null;
	var $maxloggedin = null;
	var $pluginlist = null;
	var $products = null;
	var $ranks = null;
	var $statement = null;
	var $userstats = null;
	var $wol_spiders = null;
	var $loadcache = null;
	var $noticecache = null;
	var $prefixcache = null;
	/**#@-*/

	/**#@+
	* Miscellaneous variables
	*
	* @var	mixed
	*/
	var $bbcode_style = array('code' => -1, 'html' => -1, 'php' => -1, 'quote' => -1);
	var $templatecache = array();
	var $iforumcache = array();
	var $versionnumber;
	var $nozip;
	var $debug;
	var $noheader;
	var $shutdown;
	/**#@-*/

	/**
	* Constructor - initializes the nozip system,
	* and calls and instance of the vB_Input_Cleaner class
	*/
	function __construct()
	{
		// variable to allow bypassing of gzip compression
		$this->nozip = defined('NOZIP') ? true : (@ini_get('zlib.output_compression') ? true : false);
		// variable that controls HTTP header output
		$this->noheader = defined('NOHEADER') ? true : false;

		// load the config information
		$this->fetch_config();

		// initialize the input handler
		$this->input = new vB_Input_Cleaner($this);

		// initialize the shutdown handler
		$this->shutdown = vB_Shutdown::init();

		$this->csrf_skip_list = (defined('CSRF_SKIP_LIST') ? explode(',', CSRF_SKIP_LIST) : array());
	}

	/**
	* Fetches database/system configuration
	*/
	function fetch_config()
	{
		// parse the config file
		$config = array();
		include(CWD . '/includes/config.php');

		if (sizeof($config) == 0)
		{
			if (file_exists(CWD. '/includes/config.php'))
			{
				// config.php exists, but does not define $config
				die('<br /><br /><strong>Configuration</strong>: includes/config.php exists, but is not in the 3.6+ format. Please convert your config file via the new config.php.new.');
			}
			else
			{
				die('<br /><br /><strong>Configuration</strong>: includes/config.php does not exist. Please fill out the data in config.php.new and rename it to config.php');
			}
		}

		$this->config =& $config;
		// if a configuration exists for this exact HTTP host, use it
		if (isset($this->config["$_SERVER[HTTP_HOST]"]))
		{
			$this->config['MasterServer'] = $this->config["$_SERVER[HTTP_HOST]"];
		}

		// define table and cookie prefix constants
		define('TABLE_PREFIX', trim($this->config['Database']['tableprefix']));
		define('COOKIE_PREFIX', (empty($this->config['Misc']['cookieprefix']) ? 'bb' : $this->config['Misc']['cookieprefix']));

		// set debug mode
		$this->debug = !empty($this->config['Misc']['debug']);
		define('DEBUG', $this->debug);
	}

	/**
	* Set the various IP Variables
	*/
	function fetch_ip_data()
	{
		if (defined('USE_VB_ALT_IP'))
		{
			$proxy = true;
		}
		else
		{
			$proxy = false;
			if (isset($this->config['Misc']['proxyiplist']))
			{
				// Get the defined proxy list 
				$proxylist = array_map('trim', explode(',', $this->config['Misc']['proxyiplist']));
			
				// Check all the entries 
				foreach ($proxylist AS $proxyip)
				{
					if (strtolower($proxyip) == 'all' // Any IP allowed, nothing more to check.
					OR $this->ipaddress == $proxyip // We matched our current IP, thats good enough.
					OR (
						substr($proxyip, -1) == '*' // A wildcard was defined, so lets check it.
						AND substr($this->ipaddress, 0, strlen($proxyip)-1) == substr($proxyip, 0, -1) 
						)
					)
					{
						$proxy = true;

						if (isset($this->config['Misc']['proxyipheader'])
						AND isset($_SERVER[$this->config['Misc']['proxyipheader']]))
						{
							$altip = $_SERVER[$this->config['Misc']['proxyipheader']];
							if ($this->input->filter_ip($altip))
							{
								$this->alt_ip = $altip;
							}
						}

						break; // Our work is done, its break time.
					}
				}
			}
		}

		if ($proxy)
		{
			define('ALT_IP', $this->ipaddress);
			define('IPADDRESS', $this->alt_ip);
		}
		else
		{
			define('IPADDRESS', $this->ipaddress);
			define('ALT_IP', $this->alt_ip);
		}

		define('SESSION_HOST', IPADDRESS);
	}

	/**
	* Takes the contents of an array and recursively uses each title/data
	* pair to create a new defined constant.
	*/
	function array_define($array)
	{
		foreach ($array AS $title => $data)
		{
			if (is_array($data))
			{
				vB_Registry::array_define($data);
			}
			else
			{
				define(strtoupper($title), $data);
			}
		}
	}
}

// #############################################################################
// session management class

/**
* Class to handle sessions
*
* Creates, updates, and validates sessions; retrieves user info of browsing user
*
* @package	vBulletin
*/
class vB_Session
{
	/**
	* The individual session variables. Equivalent to $session from the past.
	*
	* @var	array
	*/
	var $vars = array();

	/**
	* A list of variables in the $vars member that are in the database. Includes their types.
	*
	* @var	array
	*/
	var $db_fields = array(
		'sessionhash'   => TYPE_STR,
		'userid'        => TYPE_INT,
		'host'          => TYPE_STR,
		'idhash'        => TYPE_STR,
		'lastactivity'  => TYPE_INT,
		'location'      => TYPE_STR,
		'styleid'       => TYPE_INT,
		'languageid'    => TYPE_INT,
		'loggedin'      => TYPE_INT,
		'inforum'       => TYPE_INT,
		'inthread'      => TYPE_INT,
		'incalendar'    => TYPE_INT,
		'badlocation'   => TYPE_INT,
		'useragent'     => TYPE_STR,
		'bypass'        => TYPE_INT,
		'profileupdate' => TYPE_INT,
	);

	/**
	* An array of changes. Used to prevent superfluous updates from being made.
	*
	* @var	array
	*/
	var $changes = array();

	/**
	* Whether the session was created or existed previously
	*
	* @var	bool
	*/
	var $created = false;

	/**
	* Reference to a vB_Registry object that keeps various data we need.
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Information about the user that this session belongs to.
	*
	* @var	array
	*/
	var $userinfo = null;

	/**
	* Is the sessionhash to be passed through URLs?
	*
	* @var	boolean
	*/
	var $visible = true;

	/**
	* Constructor. Attempts to grab a session that matches parameters, but will create one if it can't.
	*
	* @param	vB_Registry	Reference to a registry object
	* @param	string		Previously specified sessionhash
	* @param	integer		User ID (passed in through a cookie)
	* @param	string		Password, must arrive in cookie format: md5(md5(md5(password) . salt) . 'abcd1234')
	* @param	integer		Style ID for this session
	* @param	integer		Language ID for this session
	*/
	function __construct(&$registry, $sessionhash = '', $userid = 0, $password = '', $styleid = 0, $languageid = 0)
	{
		$userid = intval($userid);
		$styleid = intval($styleid);
		$languageid = intval($languageid);

		$this->registry =& $registry;
		$db =& $this->registry->db;
		$gotsession = false;

		if (!defined('SESSION_IDHASH'))
		{
			define('SESSION_IDHASH', md5($_SERVER['HTTP_USER_AGENT'] . $this->fetch_substr_ip($registry->alt_ip))); // this should *never* change during a session
		}

		// sessionhash specified, so see if it already exists
		if ($sessionhash AND !defined('SKIP_SESSIONCREATE'))
		{
			if ($session = $db->query_first_slave("
				SELECT *
				FROM " . TABLE_PREFIX . "session
				WHERE sessionhash = '" . $db->escape_string($sessionhash) . "'
					AND lastactivity > " . (TIMENOW - $registry->options['cookietimeout']) . "
					AND idhash = '" . $this->registry->db->escape_string(SESSION_IDHASH) . "'
			") AND $this->fetch_substr_ip($session['host']) == $this->fetch_substr_ip(SESSION_HOST))
			{
				$gotsession = true;
				$this->vars =& $session;
				$this->created = false;

				// found a session - get the userinfo
				if ($session['userid'] != 0)
				{
					$useroptions = (defined('IN_CONTROL_PANEL') ? 16 : 0) + (defined('AVATAR_ON_NAVBAR') ? 2 : 0);
					$userinfo = fetch_userinfo($session['userid'], $useroptions, (!empty($languageid) ? $languageid : $session['languageid']));
					$this->userinfo =& $userinfo;
				}
			}
		}

		// or maybe we can use a cookie..
		if (($gotsession == false OR empty($session['userid'])) AND $userid AND $password AND !defined('SKIP_SESSIONCREATE'))
		{
			$useroptions = (defined('IN_CONTROL_PANEL') ? FETCH_USERINFO_ADMIN : 0) + (defined('AVATAR_ON_NAVBAR') ? FETCH_USERINFO_AVATAR : 0);
			$userinfo = fetch_userinfo($userid, $useroptions, $languageid);

			if (md5($userinfo['password'] . COOKIE_SALT) == $password)
			{
				$gotsession = true;

				// combination is valid
				if (!empty($session['sessionhash']))
				{
					// old session still exists; kill it
					$db->shutdown_query("
						DELETE FROM " . TABLE_PREFIX . "session
						WHERE sessionhash = '" . $this->registry->db->escape_string($session['sessionhash']). "'
					");
				}

				$this->vars = $this->fetch_session($userinfo['userid']);
				$this->created = true;

				$this->userinfo =& $userinfo;
			}
		}

		// at this point, we're a guest, so lets try to *find* a session
		// you can prevent this check from being run by passing in a userid with no password
		if ($gotsession == false AND $userid == 0 AND !defined('SKIP_SESSIONCREATE'))
		{
			if ($session = $db->query_first_slave("
				SELECT *
				FROM " . TABLE_PREFIX . "session
				WHERE userid = 0
					AND host = '" . $this->registry->db->escape_string(SESSION_HOST) . "'
					AND idhash = '" . $this->registry->db->escape_string(SESSION_IDHASH) . "'
				LIMIT 1
			"))
			{
				$gotsession = true;

				$this->vars =& $session;
				$this->created = false;
			}
		}

		// well, nothing worked, time to create a new session
		if ($gotsession == false)
		{
			$gotsession = true;

			$this->vars = $this->fetch_session(0);
			$this->created = true;
		}

		$this->vars['dbsessionhash'] = $this->vars['sessionhash'];

		$this->set('styleid', $styleid);
		$this->set('languageid', $languageid);
		if ($this->created == false)
		{
			$this->set('useragent', USER_AGENT);
			$this->set('lastactivity', TIMENOW);
			if (!defined('LOCATION_BYPASS'))
			{
				$this->set('location', WOLPATH);
			}
			$this->set('bypass', SESSION_BYPASS);
		}
	}

	/**
	* Saves the session into the database by inserting it or updating an existing one.
	*/
	function save()
	{
		if (defined('SKIP_SESSIONCREATE'))
		{
			return;
		}

		$cleaned = $this->build_query_array();

		// since the sessionhash can be blanked out, lets make sure we pull from "dbsessionhash"
		$cleaned['sessionhash'] = "'" . $this->registry->db->escape_string($this->vars['dbsessionhash']) . "'";

		if ($this->created == true)
		{
			/*insert query*/
			$this->registry->db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "session
					(" . implode(', ', array_keys($cleaned)) . ")
				VALUES
					(" . implode(', ', $cleaned) . ")
			");
		}
		else
		{
			// update query

			unset($this->changes['sessionhash']); // the sessionhash is not updateable
			$update = array();
			foreach ($cleaned AS $key => $value)
			{
				if (!empty($this->changes["$key"]))
				{
					$update[] = "$key = $value";
				}
			}

			if (sizeof($update) > 0)
			{
				// note that $cleaned['sessionhash'] has been escaped as necessary above!
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "session
					SET " . implode(', ', $update) . "
					WHERE sessionhash = $cleaned[sessionhash]
				");
			}
		}

		$this->changes = array();
	}

	/**
	* Builds an array that can be used to build a query to insert/update the session
	*
	* @return	array	Array of column name => prepared value
	*/
	function build_query_array()
	{
		$return = array();
		foreach ($this->db_fields AS $fieldname => $cleantype)
		{
			switch ($cleantype)
			{
				case TYPE_INT:
					$cleaned = intval($this->vars["$fieldname"]);
					break;
				case TYPE_STR:
				default:
					$cleaned = "'" . $this->registry->db->escape_string($this->vars["$fieldname"]) . "'";
			}
			$return["$fieldname"] = $cleaned;
		}

		return $return;
	}

	/**
	* Sets a session variable and updates the change list.
	*
	* @param	string	Name of session variable to update
	* @param	string	Value to update it with
	*/
	function set($key, $value)
	{
		if (!isset($this->vars["$key"]) OR $this->vars["$key"] != $value)
		{
			$this->vars["$key"] = $value;
			$this->changes["$key"] = true;
		}
	}

	/**
	* Sets the session visibility (whether session info shows up in a URL). Updates are put in the $vars member.
	*
	* @param	bool	Whether the session elements should be visible.
	*/
	function set_session_visibility($invisible)
	{
		$this->visible = !$invisible;

		if ($invisible)
		{
			$this->vars['sessionhash'] = '';
			$this->vars['sessionurl'] = '';
			$this->vars['sessionurl_q'] = '';
			$this->vars['sessionurl_js'] = '';
		}
		else
		{
			$this->vars['sessionurl'] = 's=' . $this->vars['dbsessionhash'] . '&amp;';
			$this->vars['sessionurl_q'] = '?s=' . $this->vars['dbsessionhash'];
			$this->vars['sessionurl_js'] = 's=' . $this->vars['dbsessionhash'] . '&';
		}
	}

	/**
	* Fetches a valid sessionhash value, not necessarily the one tied to this session.
	*
	* @return	string	32-character sessionhash
	*/
	function fetch_sessionhash()
	{
		return md5(uniqid(microtime(), true));
	}

	/**
	* Returns the IP address with the specified number of octets removed
	*
	* @param	string	IP address
	*
	* @return	string	truncated IP address
	*/
	function fetch_substr_ip($ip, $length = null)
	{
		if ($length === null OR $length > 3)
		{
			$length = $this->registry->options['ipcheck'];
		}
		return implode('.', array_slice(explode('.', $ip), 0, 4 - $length));
	}

	/**
	* Fetches a default session. Used when creating a new session.
	*
	* @param	integer	User ID the session should be for
	*
	* @return	array	Array of session variables
	*/
	function fetch_session($userid = 0)
	{
		$sessionhash = $this->fetch_sessionhash();
		if (!defined('SKIP_SESSIONCREATE'))
		{
			vbsetcookie('sessionhash', $sessionhash, false, false, true);
		}

		return array(
			'sessionhash'   => $sessionhash,
			'dbsessionhash' => $sessionhash,
			'userid'        => intval($userid),
			'host'          => SESSION_HOST,
			'idhash'        => SESSION_IDHASH,
			'lastactivity'  => TIMENOW,
			'location'      => defined('LOCATION_BYPASS') ? '' : WOLPATH,
			'styleid'       => 0,
			'languageid'    => 0,
			'loggedin'      => intval($userid) ? 1 : 0,
			'inforum'       => 0,
			'inthread'      => 0,
			'incalendar'    => 0,
			'badlocation'   => 0,
			'profileupdate' => 0,
			'useragent'     => USER_AGENT,
			'bypass'        => SESSION_BYPASS
		);

	}

	/**
	* Returns appropriate user info for the owner of this session.
	*
	* @return	array	Array of user information.
	*/
	function &fetch_userinfo()
	{
		if ($this->userinfo)
		{
			// we already calculated this
			return $this->userinfo;
		}
		else if ($this->vars['userid'] AND !defined('SKIP_USERINFO'))
		{
			// user is logged in
			$useroptions = (defined('IN_CONTROL_PANEL') ? FETCH_USERINFO_ADMIN : 0) + (defined('AVATAR_ON_NAVBAR') ? FETCH_USERINFO_AVATAR : 0);
			$this->userinfo = fetch_userinfo($this->vars['userid'], $useroptions, $this->vars['languageid']);
			return $this->userinfo;
		}
		else
		{
			// guest setup
			$this->userinfo = array(
				'userid'         => 0,
				'usergroupid'    => 1,
				'username'       => (!empty($_REQUEST['username']) ? htmlspecialchars_uni($_REQUEST['username']) : ''),
				'password'       => '',
				'email'          => '',
				'styleid'        => $this->vars['styleid'],
				'languageid'     => $this->vars['languageid'],
				'lastactivity'   => $this->vars['lastactivity'],
				'daysprune'      => 0,
				'timezoneoffset' => $this->registry->options['timeoffset'],
				'dstonoff'       => $this->registry->options['dstonoff'],
				'showsignatures' => 1,
				'showavatars'    => 1,
				'showimages'     => 1,
				'showusercss'    => 1,
				'dstauto'        => 0,
				'maxposts'       => -1,
				'startofweek'    => 1,
				'threadedmode'   => $this->registry->options['threadedmode'],
				'securitytoken'  => 'guest',
				'securitytoken_raw'  => 'guest'
			);

			$this->userinfo['options'] =
										$this->registry->bf_misc_useroptions['showsignatures'] | $this->registry->bf_misc_useroptions['showavatars'] |
										$this->registry->bf_misc_useroptions['showimages'] | $this->registry->bf_misc_useroptions['dstauto'] |
										$this->registry->bf_misc_useroptions['showusercss'];

			if (!defined('SKIP_USERINFO'))
			{
				// get default language
				$phraseinfo = $this->registry->db->query_first_slave("
					SELECT languageid" . fetch_language_fields_sql(0) . "
					FROM " . TABLE_PREFIX . "language
					WHERE languageid = " . (!empty($this->vars['languageid']) ? $this->vars['languageid'] : intval($this->registry->options['languageid'])) . "
				");
				if (empty($phraseinfo))
				{ // can't phrase this since we can't find the language
					trigger_error('The requested language does not exist, reset via tools.php.', E_USER_ERROR);
				}
				foreach($phraseinfo AS $_arrykey => $_arryval)
				{
					$this->userinfo["$_arrykey"] = $_arryval;
				}
				unset($phraseinfo);
			}

			return $this->userinfo;
		}
	}

	/**
	* Updates the last visit and last activity times for guests and registered users (differently).
	* Last visit is set to the last activity time (before it's updated) only when a certain
	* time has lapsed. Last activity is always set to the specified time.
	*
	* @param	integer	Time stamp for last visit time (guest only)
	* @param	integer	Time stamp for last activity time (guest only)
	*/
	function do_lastvisit_update($lastvisit = 0, $lastactivity = 0)
	{
		// update last visit/activity stuff
		if ($this->vars['userid'] == 0)
		{
			// guest -- emulate last visit/activity for registered users by cookies
			if ($lastvisit)
			{
				// we've been here before
				$this->userinfo['lastvisit'] = intval($lastvisit);
				$this->userinfo['lastactivity'] = ($lastvisit ? intval($lastvisit) : TIMENOW);

				// here's the emulation
				if (TIMENOW - $this->userinfo['lastactivity'] > $this->registry->options['cookietimeout'])
				{
					$this->userinfo['lastvisit'] = $this->userinfo['lastactivity'];

					vbsetcookie('lastvisit', $this->userinfo['lastactivity']);
				}
			}
			else
			{
				// first visit!
				$this->userinfo['lastactivity'] = TIMENOW;
				$this->userinfo['lastvisit'] = TIMENOW;

				vbsetcookie('lastvisit', TIMENOW);
			}
			vbsetcookie('lastactivity', $lastactivity);
		}
		else
		{
			// registered user
			if (!SESSION_BYPASS)
			{
				if (TIMENOW - $this->userinfo['lastactivity'] > $this->registry->options['cookietimeout'])
				{
					// see if session has 'expired' and if new post indicators need resetting
					$this->registry->db->shutdown_query("
						UPDATE " . TABLE_PREFIX . "user
						SET
							lastvisit = lastactivity,
							lastactivity = " . TIMENOW . "
						WHERE userid = " . $this->userinfo['userid'] . "
					", 'lastvisit');

					$this->userinfo['lastvisit'] = $this->userinfo['lastactivity'];
				}
				else
				{
					// if this line is removed (say to be replaced by a cron job, you will need to change all of the 'online'
					// status indicators as they use $userinfo['lastactivity'] to determine if a user is online which relies
					// on this to be updated in real time.
					$this->registry->db->shutdown_query("
						UPDATE " . TABLE_PREFIX . "user
						SET lastactivity = " . TIMENOW . "
						WHERE userid = " . $this->userinfo['userid'] . "
					", 'lastvisit');
				}
			}
		}
	}
}

/**
* Class to handle shutdown
*
* @package	vBulletin
*/
class vB_Shutdown
{
	var $shutdown = array();

	protected static $instance;

	/**
	* Constructor. Empty.
	*/
	protected function __construct()
	{
	}

	/**
	* Singleton emulation - use this function to instantiate the class
	*
	* @return	vB_Shutdown
	*/
	public static function &init()
	{
		if (!self::$instance)
		{
			self::$instance = new vB_Shutdown();
		}

		return self::$instance;
	}

	/**
	* Add function to be executed at shutdown
	*
	* @param	string	Name of function to be executed on shutdown
	*/
	public static function add($function)
	{
		$obj = vB_Shutdown::init();
		if ((is_array($function) AND is_object($function[0]) AND method_exists($function[0], $function[1])) OR
				(function_exists($function) AND !in_array($function, $obj->shutdown)))
		{
			$obj->shutdown[] = $function;
		}
	}

	// only called when an object is destroyed, so $this is appropriate
	function __destruct()
	{
		if (!empty($this->shutdown))
		{
			foreach ($this->shutdown AS $key => $funcname)
			{
				call_user_func($funcname);
				unset($this->shutdown[$key]);
			}
		}
	}

	// called if unserialized
	function __wakeup()
	{
		$this->shutdown = array();
	}
}

/**
* Unicode-safe VB3 version of htmlspecialchars()
*
* ## This MUST be in class_core.php ##
* Its called before functions.php is loaded
*
* @param	string	Text to be made html-safe
*
* @return	string
*/
function htmlspecialchars_uni($text, $entities = true)
{
	return str_replace(
		// replace special html characters
		array('<', '>', '"'),
		array('&lt;', '&gt;', '&quot;'),
		preg_replace(
			// translates all non-unicode entities
			'/&(?!' . ($entities ? '#[0-9]+|shy' : '(#[0-9]+|[a-z]+)') . ';)/si',
			'&amp;',
			$text
		)
	);
}

/**
* vBulletin Emulation of Unserialize, to stop abuse of this function
*
* @param	string	null or valid serialized string
* @param	bool/array	overide custom function, and use php instead.
*
* @return	mixed	The unserialized data
*/
function vb_unserialize($string, $usephp = false)
{
	if ($usephp === true)
	{
		return unserialize($string);
	}
	else if (is_array($usephp))
	{
		if (version_compare(PHP_VERSION, '7.0.0', '>='))
		{
			return unserialize($string, $usephp);
		}
	}

	$index = 0;
	$string = trim($string);
	$strlen = strlen($string);
	$value = us_get_value($index, $strlen, $string);

	if ($value === false)
	{
		return false;
	}

	if ($index != $strlen)
	{
		us_trigger_error('vb_unserialize: 01 - Finished, but not at end of string', E_USER_WARNING);
		return false;
	}

	return $value;
}

function us_get_value(&$index, $strlen, $string)
{
	if ($strlen == 0)
	{
		return false;
	}
	else
	{
		$type = strtolower($string[$index]);
	}

	switch($type)
	{
		case 'a':
			return us_get_array($index, $strlen, $string);
			break;
		case 's':
			$length = (int) us_get_prefix($index, $strlen, $string, ':');
			return us_get_string($index, $strlen, $string, $length);
			break;
		case 'i':
			return (int) us_get_prefix($index, $strlen, $string, ';');
			break;
		case 'd':
			$value = us_get_prefix($index, $strlen, $string, ';');
			return (double) $value;
			break;
		case 'b':
			return (boolean) us_get_prefix($index, $strlen, $string, ';');
			break;
		case 'n':
			return us_get_null($index, $strlen, $string);
			break;
		default:
			us_trigger_error('vb_unserialize: 02 - Invalid unserialize type: ' . $string[$index] . ' at position ' . $index, E_USER_WARNING);
	}
	
	return false;
}

function us_get_array(&$index, $strlen, $string)
{
	$length = (int) us_get_prefix($index, $strlen, $string, ':');

	if ($index+1 >= $strlen)
	{
		us_trigger_error('vb_unserialize: 03 - Unexpected end of string', E_USER_WARNING);
		return false;
	}

	if ($string[$index] != '{')
	{
		us_trigger_error('vb_unserialize: 04 - Expected { at position ' .  $index . ' found ' . $string[$index], E_USER_WARNING);
		return false;
	}

	$index++;
	$array = array();

	for($i = 0; $i < $length; $i++)
	{
		$keystart = $index;
		$key = us_get_value($index, $strlen, $string);
		if(!(is_int($key) OR is_string($key)))
		{
			us_trigger_error('vb_unserialize: 05 - Invalid key type ' . gettype($key) . ' at index ' . $keystart, E_USER_WARNING);
			return false;
		}

		$value = us_get_value($index, $strlen, $string);
		$array[$key] = $value;
	}

	if ($string[$index] != '}')
	{
		us_trigger_error('vb_unserialize: 06 - Expected } at position ' .  $index . ' found ' . $string[$index], E_USER_WARNING);
		return false;
	}

	$index++;
	return $array;
}

function us_get_null(&$index, $strlen, $string)
{
	if ($index+1 >= $strlen)
	{
		us_trigger_error('vb_unserialize: 07 - Unexpected end of string', E_USER_WARNING);
		return false;
	}

	$index++;

	if ($string[$index] != ';')
	{
		us_trigger_error('vb_unserialize: 08 - Expected ; at position ' .  $index . ' found ' . $string[$index], E_USER_WARNING);
		return false;
	}

	$index++;
	return null;
}

function us_get_prefix(&$index, $strlen, $string, $lastchar)
{
	$start = $index+2;

	if ($start >= $strlen)
	{
		us_trigger_error('vb_unserialize: 09 - Unexpected end of string', E_USER_WARNING);
		return false;
	}

	if ($string[$index+1] != ':')
	{
		us_trigger_error('vb_unserialize: 10 - Expected : at position ' . $index + 1 . ' found ' . $string[$index], E_USER_WARNING);
		return false;
	}

	$end = strpos($string, $lastchar, $start);

	if($end === false)
	{
		us_trigger_error('vb_unserialize: 11 - Expected ' . $lastchar . ', reached end of string', E_USER_WARNING);
		return false;
	}

	$value = substr($string, $start, $end - ($start));

	$index += (2+strlen($value));

	if ($string[$index] != $lastchar)
	{
		us_trigger_error('vb_unserialize: 12 - Expected ' . $lastchar . ' at position ' .  $index . ' found ' . $string[$index], E_USER_WARNING);
		return false;
	}

	$index++;
	return $value;
}

function us_get_string(&$index, $strlen, $string, $length)
{
	if ($index+2+$length >= $strlen)
	{
		us_trigger_error('vb_unserialize: 13 - Unexpected end of string', E_USER_WARNING);
		return false;
	}

	if ($string[$index] != '"')
	{
		us_trigger_error('vb_unserialize: 14 - Expected " at position ' . $index . ' found ' . $string[$index], E_USER_WARNING);
		return false;
	}

	$value = substr($string, $index+1, $length);

	$index += $length+1;

	if ($string[$index] != '"')
	{
		us_trigger_error('vb_unserialize: 15 - Expected " at position ' .  $index . ' found ' . $string[$index], E_USER_WARNING);
		return false;
	}

	$index++;

	if ($string[$index] != ';')
	{
		us_trigger_error('vb_unserialize: 16 - Expected " at position ' .  $index . ' found ' . $string[$index], E_USER_WARNING);
		return false;
	}

	$index++;
	return $value;
}

function us_trigger_error($string, $type, $warn = false, $stop = false)
{
	// Default error trigger type
	if (!defined('VBUS_ERRORS_TYPE'))
	{
		define('VBUS_ERRORS_TYPE', 'default');
	}

	// Define error triggering
	if (VBUS_ERRORS_TYPE == 'stop' OR $stop)
	{
		vbstop($string);
	}
	else if (VBUS_ERRORS_TYPE == 'warn' OR $warn)
	{
		trigger_error($string, $type);
	}
}

// #############################################################################
// Misc core error handling functions
// #############################################################################

/**
* Feeds database connection errors into the halt() method of the vB_Database class.
*
* @param	integer	Error number
* @param	string	PHP error text string
* @param	strig	File that contained the error
* @param	integer	Line in the file that contained the error
*/
function catch_db_error($errno, $errstr, $errfile, $errline)
{
	global $db;
	static $failures;

	if (strstr($errstr, 'Lost connection') AND $failures < 5)
	{
		$failures++;
		return;
	}

	if (is_object($db))
	{
		$db->halt("$errstr\r\n$errfile on line $errline");
	}
	else
	{
		vbulletin_error_handler($errno, $errstr, $errfile, $errline);
	}
}

// #############################################################################
/**
* Removes the full path from being disclosed on any errors
*
* @param	integer	Error number
* @param	string	PHP error text string
* @param	strig	File that contained the error
* @param	integer	Line in the file that contained the error
*/
function vbulletin_error_handler($errno, $errstr, $errfile, $errline)
{
	global $vbulletin;

	if (defined('SKIP_ERRORS'))
	{
		return true; // Do nothing
	}
	else
	{
		/* Figure out if php log and display errors is enabled
		We may get '1' or 'On' returned (this changed in php 5.3) */
		$php_log_errors = in_array(strtolower(ini_get('log_errors')), array('on', '1')) ? true : false;
		$php_display_errors = in_array(strtolower(ini_get('display_errors')), array('on', '1')) ? true : false;
	}

	if ($php_log_errors AND defined('LOG_VB_PHP_ERRORS'))
	{
		// If set, try to log to system log regardless of vB settings
		error_log("VB PHP Error: $errstr in $errfile on line $errline");
	}

	/* Error reporting is not turned off */
	if ((error_reporting() & $errno) AND $php_display_errors)
	{
		$type = '';
		$return = true;
		$log = $exit = $stop = false;

		if (!defined('DIR'))
		{
			$xerrstr = $errstr;
			$xerrfile = $errfile;
		}
		else
		{
			// Strip path for displayed messages
			$xerrstr = str_replace(DIR, '....', $errstr);
			$xerrfile = str_replace(DIR, '....', $errfile);
		}

		switch ($errno)
		{
			case E_NOTICE:
			// Just ignore these completely //
			break;

			case E_WARNING:
				/* Don't log warnings here due to the false bug reports about
				valid warnings that we suppress, but still appear in the log */
				//$log = true;
				$type = 'PHP Warning';
			break;

			case E_USER_WARNING:
				$type = 'PHP User Warning';
			break;

			case E_USER_ERROR:
				$log = true;
				$exit = true;
				$type = 'Fatal User Error';
			break;

			case E_STRICT:
			case E_DEPRECATED:
				// This setting overrides the config option.
				if (!empty($vbulletin->options['hookerrors']))
				{
					break;
				}

				if (defined('SHOW_DS_ERRORS'))
				{
					$log = true;
					$type = 'PHP Warning';
				}
			break;

			default: // Not an error we currently deal with, log it and return.
				$log = true;
				$return = false;
				break;
		}

		if ($exit)
		{
			if (defined('DIR') AND !empty($vbulletin->userinfo['permissions']) 
			AND ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				$stop = true;
				require_once(DIR . '/includes/functions.php');
				echo str_repeat(' ', 512); // So IE doesn't show the pretty error messages
			}

			if (!headers_sent())
			{
				if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
				{
					header('Status: 500 Internal Server Error');
				}
				else
				{
					header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
				}
			}
		}

		if ($type)
		{
			echo "<br /><b>$type</b>: $xerrstr in <strong>$xerrfile</strong> on line <strong>$errline</strong><br />";
		}

		if ($log AND defined('DIR'))
		{
			$message = $type ? $type : 'Unhandled Error';
			require_once(DIR . '/includes/functions_log_error.php');
			log_vbulletin_error("$message: $errstr in $errfile on line $errline", 'php');
		}

		if ($exit)
		{
			if ($stop)
			{
				vbstop(); // Display trace and exit.
			}
			else
			{
				exit;
			}
		}
		else
		{
			return $return;
		}
	}
	
	return false;
}

/**
 * Handles PHP 7+ Error exceptions in a similar way to vbulletin_error_handler. Error handling will not be skipped.
 * @param Throwable Error or Exception object
 */
function vbulletin_exception_handler($throwable)
{
	global $vbulletin;

	$errstr = $throwable->getMessage();
	$errno = $throwable->getCode();;
	$errfile = $throwable->getFile();
	$errline = $throwable->getLine();

	/* Figure out if php log and display errors is enabled
	   We may get '1' or 'On' returned (this changed in php 5.3) */
	$php_log_errors = in_array(strtolower(ini_get('log_errors')), array('on', '1')) ? true : false;

	if ($php_log_errors AND defined('LOG_VB_PHP_ERRORS'))
	{
		// If set, try to log to system log regardless of vB settings
		error_log("VB PHP Exception: $errstr in $errfile on line $errline");
	}

	$type = get_class($throwable);
	$stop = false;

	if (!defined('DIR'))
	{
		$xerrstr = $errstr;
		$xerrfile = $errfile;
	}
	else
	{
		// Strip path for displayed messages
		$xerrstr = str_replace(DIR, '....', $errstr);
		$xerrfile = str_replace(DIR, '....', $errfile);
	}

	if (defined('DIR') AND !empty($vbulletin->userinfo['permissions'])
		AND ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		$stop = true;
		require_once(DIR . '/includes/functions.php');
		echo str_repeat(' ', 512); // So IE doesn't show the pretty error messages
	}

	if (!headers_sent())
	{
		if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
		{
			header('Status: 500 Internal Server Error');
		}
		else
		{
			header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
		}
	}

	echo "<br /><b>$type</b>: $xerrstr in <strong>$xerrfile</strong> on line <strong>$errline</strong><br />";

	if (defined('DIR'))
	{
		$message = $type ? $type : 'Unhandled Error';
		require_once(DIR . '/includes/functions_log_error.php');
		log_vbulletin_error("$message: $errstr in $errfile on line $errline", 'php');
	}

	if ($stop)
	{
		vbstop(); // Display trace and exit.
	}
	else
	{
		exit;
	}
}

/*
Stop execution at any point and prints a trace.
If passed a variable, it will also output the contents of
that variable. If exit is set to 0, it will return after
printing the trace (this allows multiple traces to be displayed).
This is used by the error handlers, so lives in the same file as them.
*/
function vbstop($variable = null, $exit = 1, $showtrace = -1, $showvar = 1)
{
	$count = 0;
	if ($showtrace)
	{
		echo'Trace Data:<br /><pre>';
		$trace = debug_backtrace();
		foreach ($trace AS $index => $trace_item)
		{
			$index++;
			$count++;
			if ($showtrace > 0 AND $count > $showtrace)
			{
				break;
			}
			$param = (in_array($trace_item['function'], array('require', 'require_once', 'include', 'include_once')) ? $trace_item['args'][0] : '');
			$param = str_replace(DIR, '....', $param);
			$trace_item['file'] = str_replace(DIR, '....', $trace_item['file']);
			echo "#$index : $trace_item[class]$trace_item[type]$trace_item[function]($param) called in $trace_item[file] on line $trace_item[line]<br />";
		}
		echo'<br /></pre>';
	}

	if ($showvar)
	{
		$type = gettype($variable);
		echo"Var Type:<pre>[ $type ]</pre><br />";
		
		echo'Var Data:<br /><pre>';
		if ($variable === true)
		{
			echo 'true';
		}
		else if ($variable === false)
		{
			echo 'false';
		}
		else if ($variable === null)
		{
			echo 'null';
		}
		else
		{
			print_r($variable);
		}
		echo'</pre>';
	}

	if ($exit)
	{
		exit;
	}

	return;
}

/**
* Validate the ip is v4
*
* @param	string	IP Address
* @param	bool	Allow Private and Reserved IP Addresses
*
* @return 	boolean	Returns true if the address is valid
*/
function validate_ip4($ip, $allow_private = true)
{
	if ($allow_private)
	{
		$flag = FILTER_FLAG_IPV4;
	}
	else
	{
		$flag =  FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
	}

	return filter_var($ip, FILTER_VALIDATE_IP, $flag);
}

/**
* Validate the ip is v6
*
* @param	string	IP Address
* @param	bool	Allow Private and Reserved IP Addresses
*
* @return 	boolean	Returns true if the address is valid
*/
function validate_ip6($ip, $allow_private = true)
{
	if ($allow_private)
	{
		$flag = FILTER_FLAG_IPV6;
	}
	else
	{
		$flag =  FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
	}

	return filter_var($ip, FILTER_VALIDATE_IP, $flag);
}

/**
* Get the ip type (4 or 6)
*
* @param	string	IP Address
* @param	bool	Allow Private and Reserved IP Addresses
*
* @return 	int	Returns 4 or 6 [or 0 if the ip address is invalid].
*/
function get_iptype($ip, $allow_private = true)
{
	if (validate_ip4($ip, $allow_private))
	{
		return 4;
	}
	else if (validate_ip6($ip, $allow_private))
	{
		return 6;
	}
	else
	{
		return 0;
	}
}

/**
* This will compress a valid IPv6 address to its shortest form. 
* otherwise a blank string, or the original (validated) IPv4 is returned.
*
* @param	string	Input ip address
* @param	bool	Validate the ip address
*
* @return 	string	Returned ip address.
*/
function compress_ip($ip, $validate = true)
{
	if ($validate)
	{
		$type = get_iptype($ip);

		if ($type == 4)
		{
			return $ip; // Valid IPv4
		}
		else if ($type != 6)
		{
			return ''; // Not Valid IPv6 (or IPv4)
		}
	}

	$ipx = @inet_pton($ip);

	if ($ipx === false)
	{
		return ''; // Bad ip
	}
	else
	{
		return strtoupper(inet_ntop($ipx));
	}
}

/**
* This will expand a valid IPv6 address to its shortest form. 
* otherwise a blank string, or the original (validated) IPv4 is returned.
*
* @param	string	Input ip address
* @param	bool	Validate the ip address
*
* @return 	string	Returned ip address.
*/
function expand_ip($ip, $validate = true)
{
	if ($validate)
	{
		$type = get_iptype($ip);

		if ($type == 4)
		{
			return $ip;
		}
		else if ($type != 6)
		{
			return '';
		}
	}

	$ipbits = explode('::',$ip);
	$size = sizeof($ipbits);

	if ($size > 2)
	{
		return ''; // Not valid IPv6
	}

	if ($size == 1)
	{
		$allbits = explode(':',$ip);
	}
	else // $size = 2
	{
		$lbits = explode(':',$ipbits[0]);
		$rbits = explode(':',$ipbits[1]);

		if (!strlen($lbits[0]))
		{
			unset($lbits[0]);
		}
		else if (!strlen($rbits[0]))
		{
			unset($rbits[0]);
		}

		$size = sizeof($lbits) + sizeof($rbits);

		if ($size > 8)
		{
			return ''; // Not valid IPv6
		}
		else
		{
			$fill = array();
			$expand = 8 - $size;
		}

		if ($size < 8)
		{
			for ($i = 1; $i <= $expand; $i++)
			{
				$fill[] = '0';
			}
		}

		$allbits = array_merge($lbits, $fill, $rbits);
	}

	if (sizeof($allbits) != 8)
	{
		return ''; // Not valid IPv6
	}

	$bits = array();
	foreach ($allbits AS $bit)
	{
		$bits[] = strtoupper(trim(str_pad($bit, 4, '0', STR_PAD_LEFT)));
	}

	$returnip = implode(':', $bits);
	$ipcheck = @inet_pton($returnip); // Is it a valid ip ?

	if ($ipcheck === false)
	{
		return ''; // Bad ip
	}
	else
	{
		return $return; // Valid ip
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 93992 $
|| # $Date: 2017-05-10 05:18:19 -0700 (Wed, 10 May 2017) $
|| ####################################################################
\*======================================================================*/
?>
