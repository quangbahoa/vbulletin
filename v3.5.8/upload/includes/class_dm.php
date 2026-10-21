<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Abstract class to do data save/delete operations for a particular data type (such as user, thread, post etc.)
*
* @package	vBulletin
* @version	$Revision: 14027 $
* @date		$Date: 2006-01-05 11:23:42 -0600 (Thu, 05 Jan 2006) $
*/
class vB_DataManager
{
	/**
	* Array of field names that are valid for this data object
	*
	* Each array element has the field name as its key, and then a three element array as the value.
	* These three elements are used as follows:
	* FIELD 0 (VF_TYPE) - This specifies the expected data type of the field, and draws on the
	* 	data types defined for the vB_Input_Cleaner class
	* FIELD 1 (VF_REQ) - This specified whether or not the field is REQUIRED for a valid INSERT query.
	* 	Options include REQ_NO, REQ_YES and REQ_AUTO, which is a special option, indicating that the value of the field is automatically created
	* FIELD 2 (VF_CODE) - This contains code to be executed as a lamda function called as 'function($data, $dm)'.
	* 	Alternatively, the value can be VF_METHOD, in which case, $this->verify_{$fieldname} will be called.
	*
	* @var	array
	*/
	var $validfields = array();

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	* For example: var $bitfields = array('options' => 'bf_misc_useroptions', 'permissions' => 'bf_misc_moderatorpermissions')
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* Array to store the names of fields that have been sucessfully set
	*
	* @var	array
	*/
	var $setfields = array();

	/**
	* Array to store the names for fields that will be taking raw SQL
	*
	* @var	array
	*/
	var $rawfields = array();

	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* The vBulletin database object
	*
	* @var	vB_Database
	*/
	var $dbobject = null;

	/**
	* Will contain the temporary verification function for each field
	*
	* @var	function
	*/
	var $lamda = null;

	/**
	* Array to store any errors encountered while building data
	*
	* @var	array
	*/
	var $errors = array();

	/**
	* The error handler for this object
	*
	* @var	string
	*/
	var $error_handler = ERRTYPE_STANDARD;

	/**
	* Array to store existing data
	*
	* @var	array
	*/
	var $existing = array();

	/**
	* Array to store information
	*
	* @var	array
	*/
	var $info = array();

	/**
	* Condition to be used in SQL query - if empty, an INSERT query will be performed
	*
	* @var	string
	*/
	var $condition = null;

	/**
	* Default table to be used in queries
	*
	* @var	string
	*/
	var $table = 'default_table';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('dataid = %1$d', 'dataid');

	/**
	* Callback to execute just before an error is logged.
	*
	* @var	callback
	*/
	var $failure_callback = null;

	/**
	* This variable prevents the pre_save() method from being called more than once.
	* In some classes, it is helpful to explicitly call pre_save() before
	* calling save as additional checks are done. This variable is used to prevent
	* pre_save() from being executed when save() is called. If null, pre_save()
	* has yet to be called; else, it is the return value of pre_save().
	*
	* @var	null|bool
	*/
	var $presave_called = null;

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		if (!is_subclass_of($this, 'vB_DataManager'))
		{
			trigger_error("Direct Instantiation of vB_DataManager class prohibited.", E_USER_ERROR);
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;

			if (is_object($registry->db))
			{
				$this->dbobject =& $registry->db;
			}
			else
			{
				trigger_error('Database object is not an object', E_USER_ERROR);
			}
		}
		else
		{
			trigger_error('Registry object is not an object', E_USER_ERROR);
		}

		$this->set_error_handler($errtype);

		if (is_array($this->bitfields))
		{
			foreach ($this->bitfields AS $key => $val)
			{
				if (isset($this->registry->$val))
				{
					$this->bitfields["$key"] =& $this->registry->$val;
				}
				else
				{
					trigger_error("Please check the <em>\$bitfields</em> array in the <strong>" . get_class($this) . "</strong> class definition - <em>\$vbulletin->$val</em> is not a valid bitfield.<br />", E_USER_ERROR);
				}
			}
		}
	}

	/**
	* Sets the existing data
	*
	* @param	array	Optional array of data describing the existing data we will be updating
	*
	* @return	boolean	Returns true if successful
	*/
	function set_existing(&$existing)
	{
		if (is_array($existing))
		{
			if (sizeof($this->existing) == 0)
			{
				$this->existing =& $existing;
			}
			else
			{
				foreach (array_keys($existing) AS $fieldname)
				{
					$this->existing["$fieldname"] =& $existing["$fieldname"];
				}
			}

			$this->set_condition();
		}
		else
		{
			$line = '';
			if (function_exists('debug_backtrace'))
			{
				$trace = debug_backtrace();
				$line = "<br />\n";
				foreach ($trace AS $num => $arr)
				{
					$line .= "Called $arr[function] in $arr[file] on line $arr[line]<br />\n";
				}
			}
			trigger_error('Existing data passed is not an array' . $line, E_USER_ERROR);
		}
	}

	/**
	* Sets the condition to be used in WHERE clauses, based upon the $this->existing data and the $this->condition_constuct condition template.
	*/
	function set_condition($clause = '')
	{
		if ($clause != '')
		{
			$this->condition = $clause;
			return true;
		}
		else
		{
			$condition_args = array($this->condition_construct[0]);
			$condition_length = sizeof($this->condition_construct);

			for ($i = 1; $i < $condition_length; $i++)
			{
				$condition_args[] =& $this->existing[$this->condition_construct["$i"]];
			}

			if ($this->condition = @call_user_func_array('sprintf', $condition_args))
			{
				return true;
			}
			else
			{
				trigger_error('Failed to set condition from existing data', E_USER_ERROR);
			}
		}
	}

	/**
	* Fetches info about the current data object - if a new value is set, it returns this, otherwise it will return the existing data
	*
	* @param	string	Fieldname
	*
	* @return	mixed	The requested data
	*/
	function &fetch_field($fieldname, $table = null)
	{
		if ($table === null)
		{
			$table =& $this->table;
		}

		if (isset($this->{$table}["$fieldname"]))
		{
			return $this->{$table}["$fieldname"];
		}
		else if (isset($this->existing["$fieldname"]))
		{
			return $this->existing["$fieldname"];
		}
		else
		{
			return null;
		}
	}

	/**
	* Sets the supplied data to be part of the data to be saved. Use setr() if a reference to $value is to be passed
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	* @param	boolean	Clean data, or insert it RAW (used for non-arbitrary updates, like posts = posts + 1)
	* @param	boolean	Whether to verify the data with the appropriate function. Still cleans data if previous arg is true.
	*
	* @return	boolean	Returns false if the data is rejected for whatever reason
	*/
	function set($fieldname, $value, $clean = true, $doverify = true)
	{
		if ($clean)
		{
			$verify = $this->verify($fieldname, $value, $doverify);

			if ($verify === true)
			{
				$errsize = sizeof($this->errors);
				$this->do_set($fieldname, $value);
				return true;
			}
			else
			{
				if ($this->validfields["$fieldname"][VF_REQ] AND $errsize == sizeof($this->errors))
				{
					$this->error('required_field_x_missing_or_invalid', $fieldname);
				}
				return $verify;
			}
		}
		else if (isset($this->validfields["$fieldname"]))
		{
			$this->rawfields["$fieldname"] = true;
			$this->do_set($fieldname, $value);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Sets the supplied data to be part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data (reference) itself
	* @param	boolean	Clean data, or insert it RAW (used for non-arbitrary updates, like posts = posts + 1)
	* @param	boolean	Whether to verify the data with the appropriate function. Still cleans data if previous arg is true.
	*
	* @return	boolean	Returns false if the data is rejected for whatever reason
	*/
	function setr($fieldname, &$value, $clean = true, $doverify = true)
	{
		if ($clean)
		{
			$verify = $this->verify($fieldname, $value, $doverify);

			if ($verify === true)
			{
				$errsize = sizeof($this->errors);
				$this->do_set($fieldname, $value);
				return true;
			}
			else
			{
				if ($this->validfields["$fieldname"][VF_REQ] AND $errsize == sizeof($this->errors))
				{
					$this->error('required_field_x_missing_or_invalid', $fieldname);
				}
				return $verify;
			}
		}
		else if (isset($this->validfields["$fieldname"]))
		{
			$this->rawfields["$fieldname"] = true;
			$this->do_set($fieldname, $value);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Sets a bit in a bitfield
	*
	* @param	string	Name of the database bitfield (options, permissions etc.)
	* @param	string	Name of the bit within the bitfield (canview, canpost etc.)
	* @param	boolean	Whether the bit should be set or not
	*
	* @return	boolean
	*/
	function set_bitfield($fieldname, $bitname, $onoff)
	{
		if ($bitvalue = $this->bitfields["$fieldname"]["$bitname"])
		{
			$this->{$this->table}["$fieldname"]["$bitvalue"] = ($onoff ? 1 : 0);
			//echo "<div>Setting <strong>$fieldname</strong>[<em>$bitname</em>] = <em>" . ($onoff ? 'Yes' : 'No') . "</em></div>";
			$this->setfields["$fieldname"] = true;
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Rather like set(), but sets data into the $this->info array instead. Use setr_info if $value if a reference to value is to be passed
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function set_info($fieldname, $value)
	{
		if (isset($this->validfields["$fieldname"]))
		{
			$this->verify($fieldname, $value);
		}
		$this->info["$fieldname"] = $value;
	}

	/**
	* Rather like set(), but sets reference to data into the $this->info array instead
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data (reference) itself
	*/
	function setr_info($fieldname, &$value)
	{
		if (isset($this->validfields["$fieldname"]))
		{
			$this->verify($fieldname, $value);
		}
		$this->info["$fieldname"] =& $value;
	}

	/**
	* Verifies that the supplied data is one of the fields used by this object
	*
	* Also ensures that the data is of the correct type,
	* and attempts to correct errors in the supplied data.
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	* @param	boolean	Whether to verify the data with the appropriate function. Data is still cleaned though.
	*
	* @return	boolean	Returns true if the data is one of the fields used by this object, and is the correct type (or has been successfully corrected to be so)
	*/
	function verify($fieldname, &$value, $doverify = true)
	{
		if (isset($this->validfields["$fieldname"]))
		{
			$field =& $this->validfields["$fieldname"];

			// clean the value according to its type
			$value = $this->registry->input->clean($value, $field[VF_TYPE]);

			if ($doverify AND isset($field[VF_CODE]))
			{
				if ($field[VF_CODE] === VF_METHOD)
				{
					if (isset($field[VF_METHODNAME]))
					{
						return $this->{$field[VF_METHODNAME]}($value);
					}
					else
					{
						return $this->{'verify_' . $fieldname}($value);
					}
				}
				else
				{
					$lamdafunction = create_function('&$data, &$dm', $field[VF_CODE]);
					return $lamdafunction($value, $this);
				}
			}
			else
			{
				return true;
			}
		}
		else
		{
			trigger_error("Field <em>$fieldname</em> is not defined in <em>\$validfields</em> in class <strong>" . get_class($this) . "</strong>", E_USER_ERROR);
			return false;
		}
	}

	/**
	* Unsets a values that has already been set
	*
	* @param	string	The name of the field that is to be unset
	*/
	function do_unset($fieldname)
	{
		if (isset($this->{$this->table}["$fieldname"]))
		{
			unset($this->{$this->table}["$fieldname"], $this->setfields["$fieldname"]);
		}
	}

	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function do_set($fieldname, &$value)
	{
		$this->setfields["$fieldname"] = true;
		$this->{$this->table}["$fieldname"] =& $value;
	}

	/**
	* Checks through the required fields for this object and ensures that all required fields have a value
	*
	* @return	boolean	Returns true if all required fields have a valid value set
	*/
	function check_required()
	{
		foreach ($this->validfields AS $fieldname => $validfield)
		{
			if ($validfield[VF_REQ] == REQ_YES AND !$this->setfields["$fieldname"])
			{
				$this->error('required_field_x_missing_or_invalid', $fieldname);
				return false;
			}
		}

		return true;
	}

	/**
	* Builds an UPDATE query
	*
	* @param	string	The system's table prefix
	* @param	string	The name of the database table to be affected (do not include TABLE_PREFIX in your argument)
	* @param	string	Specify the WHERE condition here. For example, 'userid > 10 AND posts < 50'
	*
	* @return	string	SQL Update query
	*/
	function fetch_update_sql($tableprefix, $table, $condition)
	{
		if (sizeof($this->$table) == 0)
		{
			return '';
		}

		$sql = "UPDATE {$tableprefix}{$table} SET";

		foreach ($this->$table AS $fieldname => $value)
		{
			if (isset($this->bitfields["$fieldname"]) AND is_array($value) AND sizeof($value) > 0)
			{
				$sql .= "\r\n\t### Bitfield: {$tableprefix}{$table}.$fieldname ###";
				foreach ($value AS $bitvalue => $bool)
				{
					$sql .= "\r\n\t\t$fieldname = IF($fieldname & $bitvalue, " . ($bool ? "$fieldname, $fieldname + $bitvalue" : "$fieldname - $bitvalue, $fieldname") . "),";
				}
			}
			else
			{
				$sql .= "\r\n\t$fieldname = " . (isset($this->rawfields["$fieldname"]) ? $value : $this->dbobject->sql_prepare($value)) . ",";
			}
		}

		$sql = substr($sql, 0, -1);
		$sql .= "\r\nWHERE $condition";

		return $sql;
	}

	/**
	* Creates and runs an UPDATE query to save the data from the object into the database
	*
	* @param	string	The system's table prefix
	* @param	string	The name of the database table to be affected (do not include TABLE_PREFIX in your argument)
	* @param	string	Specify the WHERE condition here. For example, 'userid > 10 AND posts < 50'
	* @param	boolean	Whether or not to actually run the query
	* @param	mixed	If this evaluates to true, the query will be delayed. If it is a string, that will be the name of the shutdown query.
	*
	* @return	boolean	Returns true on success
	*/
	function db_update($tableprefix, $table, $condition = '', $doquery = true, $delayed = false)
	{
		if ($this->has_errors())
		{
			return false;
		}
		else if (is_array($this->$table) AND !empty($this->$table))
		{
			$sql = $this->fetch_update_sql($tableprefix, $table, $condition);

			if ($doquery)
			{
				if ($sql)
				{
					//echo "<pre>$sql<hr /></pre>";
					if ($delayed)
					{
						if (is_string($delayed))
						{
							$this->dbobject->shutdown_query($sql, $delayed);
						}
						else
						{
							$this->dbobject->shutdown_query($sql);
						}
					}
					else
					{
						$this->dbobject->query_write($sql);
					}
				}
			}
			else
			{
				echo "<pre>$sql<hr /></pre>";
			}

			return true;
		}
		else
		{
			return true;
		}
	}

	/**
	* Builds an INSERT query
	*
	* @param	string	The system's table prefix
	* @param	string	The name of the database table to be affected (do not include TABLE_PREFIX in your argument)
	*
	* @return	string	SQL Insert query
	*/
	function fetch_insert_sql($tableprefix, $table)
	{
		$sql = "INSERT INTO {$tableprefix}{$table}\r\n\t(" . implode(', ', array_keys($this->$table)) . ")\r\nVALUES\r\n\t(";

		foreach ($this->$table AS $fieldname => $value)
		{
			if (isset($this->bitfields["$fieldname"]))
			{
				$bits = 0;
				foreach ($value AS $bitvalue => $bool)
				{
					$bits += $bool ? $bitvalue : 0;
				}
				$value = $bits;
			}

			$sql .= (isset($this->rawfields["$fieldname"]) ? $value : $this->dbobject->sql_prepare($value)) . ', ';
		}

		$sql = substr($sql, 0, -2);
		$sql .= ')';

		return $sql;
	}

	/**
	* Creates and runs an INSERT query to save the data from the object into the database
	*
	* @param	string	The system's table prefix
	* @param	string	The name of the database table to be affected (do not include TABLE_PREFIX in your argument)
	* @param	boolean	Whether or not to actually run the query
	*
	* @return	integer	Returns the ID of the inserted record
	*/
	function db_insert($tableprefix, $table, $doquery = true)
	{
		static $requiredfields = null;

		if ($requiredfields === null)
		{
			$requiredfields = $this->check_required();
		}

		if ($this->has_errors())
		{
			return false;
		}
		else if (is_array($this->$table) AND !empty($this->$table) AND $requiredfields)
		{
			$sql = $this->fetch_insert_sql($tableprefix, $table);

			if ($doquery)
			{
				/*insert query*/
				$this->dbobject->query_write($sql);
				// Not all of our tables have AUTO_INCREMENT fields, i.e. customavatar
				if ($insertid = $this->dbobject->insert_id())
				{
					return $insertid;
				}
				else
				{
					return -1;
				}
			}
			else
			{
				echo "<pre>$sql<hr /></pre>";
				return 123456789;
			}
		}
		else
		{
			return 0;
		}
	}

	/**
	* Generates the SQL to delete a record from a database table, then executes it
	*
	* @param	string	The system's table prefix
	* @param	string	The name of the database table to be affected (do not include TABLE_PREFIX in your argument)
	* @param	string	Specify the WHERE condition for the DELETE here. For example, 'userid > 10 AND posts < 50'
	* @param	boolean	Whether or not to actually run the query
	*
	* @return	integer	The number of records deleted
	*/
	function db_delete($tableprefix, $table, $condition = '', $doquery = true)
	{
		$sql = "DELETE FROM {$tableprefix}{$table} WHERE $condition";

		if ($doquery)
		{
			$this->dbobject->query_write($sql);
			return $this->dbobject->affected_rows();
		}
		else
		{
			echo "<pre>$sql<hr /></pre>";
			return 12345;
		}
	}

	/**
	* Check if the DM currently has errors. Will kill execution if it does and $die is true.
	*
	* @param	bool	Whether or not to end execution if errors are found; ignored if the error type is ERRTYPE_SILENT
	*
	* @return	bool	True if there *are* errors, false otherwise
	*/
	function has_errors($die = true)
	{
		if (!empty($this->errors))
		{
			if ($this->error_handler == ERRTYPE_SILENT OR $die == false)
			{
				return true;
			}
			else
			{
				trigger_error("Unable to proceed with save while \$errors array is not empty in class <strong>" . get_class($this) . "</strong>", E_USER_ERROR);
				return true;
			}
		}
		else
		{
			return false;
		}
	}

	/**
	* Saves the data from the object into the specified database tables
	*
	* @param	boolean	Do the query?
	* @param	mixed	Whether to run the query now; see db_update() for more info
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/
	function save($doquery = true, $delayed = false)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return false;
		}


		if ($this->condition === null)
		{
			$return = $this->db_insert(TABLE_PREFIX, $this->table, $doquery);
			if ($return)
			{
				$autoid = '';
				foreach ($this->validfields AS $fieldid => $fieldinfo)
				{
					if ($fieldinfo[VF_REQ] == REQ_INCR)
					{
						$autoid = $fieldid;
						break;
					}
				}

				if ($autoid)
				{
					$this->{$this->table}["$autoid"] = $return;
				}
			}
		}
		else
		{
			$return = $this->db_update(TABLE_PREFIX, $this->table, $this->condition, $doquery, $delayed);
		}

		if ($return)
		{
			$this->post_save_each($doquery);
			$this->post_save_once($doquery);
		}

		return $return;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$this->presave_called = true;
		return true;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		return true;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed once after all records are updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		return true;
	}

	/**
	* Deletes the specified data item from the database
	*
	* @return	integer	The number of rows deleted
	*/
	function delete($doquery = true)
	{
		if (empty($this->condition))
		{
			if ($this->error_handler == ERRTYPE_SILENT)
			{
				return false;
			}
			else
			{
				trigger_error('Delete SQL condition not specified!', E_USER_ERROR);
			}
		}
		else
		{
			if (!$this->pre_delete($doquery))
			{
				return false;
			}

			$return = $this->db_delete(TABLE_PREFIX, $this->table, $this->condition, $doquery);
			$this->post_delete($doquery);
			return $return;
		}
	}

	/**
	* Additional data to update before a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function pre_delete($doquery = true)
	{
		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		return true;
	}

	/**
	* Sets the error handler for the object
	*
	* @param	string	Error type
	*
	* @return	boolean
	*/
	function set_error_handler($errtype = ERRTYPE_STANDARD)
	{
		switch ($errtype)
		{
			case ERRTYPE_ARRAY:
			case ERRTYPE_STANDARD:
			case ERRTYPE_CP:
			case ERRTYPE_SILENT:
				$this->error_handler = $errtype;
				break;
			default:
				$this->error_handler = ERRTYPE_STANDARD;
				break;
		}
	}

	/**
	* Shows an error message and halts execution - use this in the same way as print_stop_message();
	*
	* @param	string	Phrase name for error message
	*/
	function error($errorphrase)
	{
		$args = func_get_args();
		$error = call_user_func_array('fetch_error', $args);

		$this->errors[] = $error;

		if ($this->failure_callback AND is_callable($this->failure_callback))
		{
			call_user_func_array($this->failure_callback, array(&$this, $errorphrase));
		}

		switch ($this->error_handler)
		{
			case ERRTYPE_ARRAY:
			case ERRTYPE_SILENT:
			{
				// do nothing
			}
			break;

			case ERRTYPE_STANDARD:
			{
				eval(standard_error($error));
			}
			break;

			case ERRTYPE_CP:
			{
				print_cp_message($error);
			}
			break;
		}
	}

	/**
	* Sets the function to call on an error.
	*
	* @param	callback	A valid callback (either a function name, or specially formed array)
	*/
	function set_failure_callback($callback)
	{
		$this->failure_callback = $callback;
	}

	// #############################################################################
	// additional functions for use in data verification

	/**
	* Verifies that an integer is greater than zero
	*
	* @param	integer	Value to check
	*
	* @return	boolean
	*/
	function verify_nonzero(&$int)
	{
		return ($int > 0 ? true : false);
	}

	/**
	* Verifies that a string is not empty
	*
	* @param	string	Text to check
	*
	* @return	boolean
	*/
	function verify_nonempty(&$string)
	{
		$string = strval($string);

		return ($string !== '');
	}

	/**
	* Verifies that a variable is a comma-separated list of integers
	*
	* @param	mixed	List (can be string or array)
	*
	* @return	boolean
	*/
	function verify_commalist(&$list)
	{
		return $this->verify_list($list, ',', true);
	}

	/**
	* Verifies that a variable is a space-separated list of integers
	*
	* @param	mixed	List (can be string or array)
	*
	* @return	boolean
	*/
	function verify_spacelist(&$list)
	{
		return $this->verify_list($list, ' ', true);
	}

	/**
	* Creates a valid string of comma-separated integers
	*
	* @param	mixed	Either specify a string of integers separated by parameter 2, or an array of integers
	* @param	string	The 'glue' for the string. Usually a comma or a space.
	* @param	boolean	Whether or not to exclude zero from the list
	*
	* @return	boolean
	*/
	function verify_list(&$list, $glue = ',', $dropzero = false)
	{
		if ($list !== '')
		{
			// turn strings into arrays
			if (!is_array($list))
			{
				if (preg_match_all('#(-?\d+)#s', $list, $matches))
				{
					$list = $matches[1];
				}
				else
				{
					$list = '';
					return true;
				}
			}

			// clean array values and remove duplicates, then sort into order
			$list = array_unique($this->registry->input->clean($list, TYPE_ARRAY_INT));
			sort($list);

			// remove zero values
			if ($dropzero)
			{
				$key = array_search(0, $list);
				if ($key !== false)
				{
					unset($list["$key"]);
				}
			}

			// implode back into a string
			$list = implode($glue, $list);
		}

		return true;
	}

	/**
	* Verifies that input is a serialized array (or force an array to serialize)
	*
	* @param	mixed	Either specify a serialized array, or an array to serialize, or an empty string
	*
	* @return	boolean
	*/
	function verify_serialized(&$data)
	{
		if ($data === '')
		{
			$data = serialize(array());
			return true;
		}
		else
		{
			if (!is_array($data))
			{
				$data = unserialize($data);
				if ($data === false)
				{
					return false;
				}
			}

			$data = serialize($data);
		}

		return true;
	}

	/**
	* Verifies an IP address - currently only works with IPv4
	*
	* @param	string	IP address
	*
	* @return 	boolean
	*/
	function verify_ipaddress(&$ipaddress)
	{
		if ($ipaddress == '')
		{
			return true;
		}
		else if (preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$#', $ipaddress, $octets))
		{
			for ($i = 1; $i <= 4; $i++)
			{
				if ($octets["$i"] > 255)
				{
					return false;
				}
			}
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Verifies that a string is an MD5 string
	*
	* @param	string	The MD5 string
	*
	* @return	boolean
	*/
	function verify_md5(&$md5)
	{
		return (preg_match('#^[a-f0-9]{32}$#', $md5) ? true : false);
	}

	/**
	* Verifies that an email address is valid
	*
	* @param	string	Email address
	*
	* @return	boolean
	*/
	function verify_email(&$email)
	{
		return is_valid_email($email);
	}

	/**
	* Verifies that a hyperlink is valid
	*
	* @param	string	Hyperlink URL
	*
	* @return	boolean
	*/
	function verify_link(&$link)
	{
		if (preg_match('#^www\.#si', $link))
		{
			$link = 'http://' . $link;
			return true;
		}
		else if (!preg_match('#^[a-z0-9]+://#si', $link))
		{
			// link doesn't match the http://-style format in the beginning -- possible attempted exploit
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Verifies a date array as a valid unix timestamp
	*
	* @param	array	Date array containing day/month/year and optionally: hour/minute/second
	*
	* @return	boolean
	*/
	function verify_date_array(&$date)
	{
		$date['year']   = intval($date['year']);
		$date['month']  = intval($date['month']);
		$date['day']    = intval($date['day']);
		$date['hour']   = intval($date['hour']);
		$date['minute'] = intval($date['minute']);
		$date['second'] = intval($date['second']);

		if (checkdate($date['month'], $date['day'], $date['year']))
		{
			$date = vbmktime($date['hour'],  $date['minute'], $date['second'], $date['month'], $date['day'], $date['year']);

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Basic options to perform on all pagetext type fields
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	*/
	function verify_pagetext(&$pagetext)
	{
		require_once(DIR . '/includes/functions_newpost.php');

		// remove empty bbcodes
		$pagetext = $this->strip_empty_bbcode($pagetext);

		// add # to color tags using hex if it's not there
		$pagetext = preg_replace('#\[color=(&quot;|"|\'|)([a-f0-9]{6})\\1]#i', '[color=\1#\2\1]', $pagetext);

		// strip alignment codes that are closed and then immediately reopened
		$pagetext = preg_replace('#\[/(left|center|right)]((\r\n|\r|\n)*)\[\\1]#si', '\\2', $pagetext);

		// remove [/list=x remnants
		if (stristr($pagetext, '[/list=') != false)
		{
			$pagetext = preg_replace('#\[/list=[a-z0-9]+\]#siU', '[/list]', $pagetext);
		}

		// remove extra whitespace between [list] and first element
		// -- unnecessary now, bbcode parser handles leading spaces after a list tag
		//$pagetext = preg_replace('#(\[list(=(&quot;|"|\'|)([^\]]*)\\3)?\])\s+#i', "\\1\n", $pagetext);

		// censor main message text
		$pagetext = fetch_censored_text($pagetext);

		// parse URLs in message text
		if ($this->info['parseurl'])
		{
			$pagetext = convert_url_to_bbcode($pagetext);
		}

		// remove sessionhash from urls:
		require_once(DIR . '/includes/functions_login.php');
		$pagetext = fetch_removed_sessionhash($pagetext);

		$pagetext = fetch_no_shouting_text($pagetext);

		return true;
	}

	/**
	* Strips empty BB code from the entire message except inside PHP/HTML/Noparase tags.
	*
	* @param	string	Text to strip tags from
	*
	* @return	string	Text with tags stripped
	*/
	function strip_empty_bbcode($text)
	{
		return preg_replace('#(^.*|.*)(\[(php|html|noparse)\].*\[/\\3])(.*|.*$)#siUe', "\$this->strip_empty_bbcode_callback('\\1', '\\2', '\\4')", $text);
	}

	/**
	* Callback function for strip_empty_bbcode.
	*
	* @param	string	Text before PHP/HTML tag
	* @param	string	Text inside PHP/HTML tag
	* @param	string	Text after  PHP/HTML tag
	*
	* @return	string	Compiled text with empty tags stripped where appropriate
	*/
	function strip_empty_bbcode_callback($before, $inner, $after)
	{
		$before = str_replace('\"', '"', $before);
		$inner = str_replace('\"', '"', $inner); // this is text in php/html tags, so empty tags aren't stripped
		$after = str_replace('\"', '"', $after);

		$before = preg_replace('#(\[([^=\]]+)(=[^\]]+)?]\s*\[/\\2])#siU', '', $before);
		$after = preg_replace('#(\[([^=\]]+)(=[^\]]+)?]\s*\[/\\2])#siU', '', $after);

		return $before . $inner . $after;
	}

}

// #############################################################################
// multiple data object update class

/**
* Abstract class to do data update operations for a particular data type (such as user, thread, post etc.).
* Works on multiple records simultaneously. Updates will occur on all records matching set_condition().
*
* @package	vBulletin
* @version	$Revision: 14027 $
* @date		$Date: 2006-01-05 11:23:42 -0600 (Thu, 05 Jan 2006) $
*/
class vB_DataManager_Multiple
{
	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* The vBulletin database object
	*
	* @var	vB_Database
	*/
	var $dbobject = null;

	/**
	* The error handler for the child objects. Should be one of the ERRTYPE_* constants.
	*
	* @var	integer
	*/
	var $error_handler = ERRTYPE_STANDARD;

	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager';

	/**
	* The base object of type $class_name. This is created in the constructor
	* for optimization purposes. Do not change this object.
	*
	* @var	vB_DataManager
	*/
	var $base_object = null;

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'dataid';

	/**
	* Holds an array of vB_DataManager objects that matched the condition specified
	* in a call to set_condition(). The first object in this array becomes the "master".
	* Changes are done to it first; changes are not pushed to the rest of the matches
	* until copy_changes() is called.
	*
	* @var	array	Key: Primary ID of record; value: vB_DataManager object
	*/
	var $children = array();

	/**
	* Array of the primary ID fields (as specified by $primary_id) of any records
	* that matched in a call to set_condition(). This is used to build the condition
	* when saving.
	*
	* @var	array
	*/
	var $primary_ids = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Multiple(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		if (!is_subclass_of($this, 'vB_DataManager_Multiple'))
		{
			trigger_error("Direct Instantiation of vB_DataManager_Multiple class prohibited.", E_USER_ERROR);
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;

			if (is_object($registry->db))
			{
				$this->dbobject =& $registry->db;
			}
			else
			{
				trigger_error("Database object is not an object", E_USER_ERROR);
			}
		}
		else
		{
			trigger_error("Registry object is not an object", E_USER_ERROR);
		}

		$this->error_handler = $errtype;

		$class = $this->class_name;
		$this->base_object = new $class($this->registry, $this->error_handler);
	}

	/**
	* Queries for matching records based on the condition specified,
	* and sets up the manager to make modifications to those records.
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	integer	The number of matching records
	*/
	function set_condition($condition = '', $limit = 0, $offset = 0)
	{
		// Init arrays to ensure memory is reclaimed
		$this->reset();

		$results = $this->dbobject->query($this->fetch_query($condition, $limit, $offset));
		while ($result = $this->dbobject->fetch_array($results))
		{
			$new = (phpversion() < '5' ? $this->base_object : clone($this->base_object));
			$new->set_existing($result);
			$this->children[$result[$this->primary_id]] =& $new;

			$this->primary_ids[] = $result[$this->primary_id];
		}

		return sizeof($this->primary_ids);
	}

	/**
	* This function adds an existing record to the data manager. This is helpful
	* if you have already executed the query to grab the data, for example.
	*
	* @param	array	Array of existing data. MUST HAVE THE PRIMARY ID!
	*/
	function add_existing(&$existing)
	{
		$primary_id = $existing[$this->primary_id];
		if (!$primary_id)
		{
			trigger_error('You must pass a primary ID value to vB_DataManager_Multiple::add_existing.', E_USER_ERROR);
		}

		$new = (phpversion() < '5' ? $this->base_object : clone($this->base_object));
		$new->set_existing($existing);
		$this->children["$primary_id"] =& $new;
		$this->primary_ids[] = $primary_id;
	}

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition = '', $limit = 0, $offset = 0)
	{
		trigger_error('vB_DataManager_Multiple::fetch_query must be overridden.', E_USER_ERROR);
	}

	/**
	* Allows you to fetch the value of a field whose value was changed.
	* This does not look at existing data as it may vary from record to record!
	*
	* @param	string	Field name
	* @param	string	Table name to force. Leave as null to use the default table
	*
	* @return	mixed	The requested data
	*/
	function &fetch_field($fieldname, $table = null)
	{
		if (!$this->children)
		{
			return null;
		}

		$firstid = reset($this->primary_ids);
		$master =& $this->children["$firstid"];

		if ($table === null)
		{
			$table =& $master->table;
		}

		if (isset($master->{$table}["$fieldname"]))
		{
			return $master->{$table}["$fieldname"];
		}
		else
		{
			return null;
		}
	}

	/**
	* Sets the supplied data to be part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	* @param	boolean	Clean data, or insert it RAW (used for non-arbitrary updates, like posts = posts + 1)
	* @param	boolean	Whether to verify the data with the appropriate function. Still cleans data if previous arg is true.
	*
	* @return	boolean	Returns false if the data is rejected for whatever reason
	*/
	function set($fieldname, $value, $clean = true, $doverify = true)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			return $this->children["$firstid"]->setr($fieldname, $value, $clean, $doverify);
		}

		return false;
	}

	/**
	* Sets a bit in a bitfield
	*
	* @param	string	Name of the database bitfield (options, permissions etc.)
	* @param	string	Name of the bit within the bitfield (canview, canpost etc.)
	* @param	boolean	Whether the bit should be set or not
	*
	* @return	boolean
	*/
	function set_bitfield($fieldname, $bitname, $onoff)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			return $this->children["$firstid"]->set_bitfield($fieldname, $bitname, $onoff);
		}

		return false;
	}

	/**
	* Rather like set(), but sets data into the $this->info array instead
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function set_info($fieldname, $value)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			$this->children["$firstid"]->setr_info($fieldname, $value);
		}
	}

	/**
	* Pushes the changes made to the "master" child to the rest.
	*/
	function copy_changes()
	{
		if (sizeof($this->children) > 1)
		{
			$firstid = reset($this->primary_ids);
			$master =& $this->children["$firstid"];
			$table = $master->table;

			while ($id = next($this->primary_ids))
			{
				$child =& $this->children["$id"];
				$child->{$table} = $master->{$table};
				$child->info = $master->info;
			}
		}
	}

	/**
	* Saves the data from the object into the specified database tables
	*
	* @param	boolean	Do the query?
	* @param	boolean	Whether to call post_save_once() at the end
	*
	* @return	boolean	True on success; false on failure
	*/
	function save($doquery = true, $call_save_once = true)
	{
		if (!$this->children)
		{
			return false;
		}

		$master =& $this->children[reset($this->primary_ids)];

		// push changes to all children
		$this->copy_changes();

		// pre-save validation
		foreach ($this->primary_ids AS $id)
		{
			if (!$this->children["$id"]->pre_save($doquery))
			{
				return false;
			}
		}

		// update all children
		$this->execute_query($doquery);

		// post-save updates
		foreach ($this->primary_ids AS $id)
		{
			$this->children["$id"]->post_save_each($doquery);
		}

		if ($call_save_once)
		{
			$master->post_save_once($doquery);
		}

		return true;
	}

	/**
	* Executes the necessary query/queries to update the records
	*
	* @param	boolean	Actually perform the query?
	*/
	function execute_query($doquery = true)
	{
		$condition = $this->primary_id . ' IN (' . implode(',', $this->primary_ids) . ')';
		$master =& $this->children[reset($this->primary_ids)];

		$sql = $master->fetch_update_sql(TABLE_PREFIX, $master->table, $condition);
		if ($doquery)
		{
			if ($sql)
			{
				$this->dbobject->query_write($sql);
			}
		}
		else
		{
			echo "<pre>$sql<hr /></pre>";
		}
	}

	/**
	* Removes the records stored in the manager, resetting it (essentially) to
	* its start state.
	*/
	function reset()
	{
		$this->primary_ids = array();
		$this->children = array();
	}

	/**
	* This function iterate over a large result set, only processing records in
	* batches to keep the memory usage reasonable. After a batch has been collected,
	* a reference to this object is passed to a callback function. That function
	* will update any columns necessary. This function will the save the changes
	* and start on a new batch.
	*
	* Callback function first argument must be a reference to a vB_DataManager_Multiple object.
	* Additional arguments should be passed to this function in an array.
	*
	* @param	string|resource	A query result resource or a string containing the query to execute
	* @param	callback		The function to call to make changes to the records
	* @param	integer			Number of records to process in a batch
	* @param	array			Any additional arguments to pass to the callback function
	*/
	function batch_iterate($records, $callback, $batch_size = 500, $args = array())
	{
		if (is_string($records))
		{
			$records = $this->dbobject->query_read($records);
		}

		$intargs = array(&$this);
		foreach (array_keys($args) AS $argkey)
		{
			$intargs[] =& $args["$argkey"];
		}

		$counter = 0;
		while ($record = $this->dbobject->fetch_array($records))
		{
			// this if is seperate because otherwise, if we had
			// count($records) % $batch_size == 0, $this->children would be empty
			// so we couldn't call post_save_once().
			if (($counter % $batch_size) == 0)
			{
				$this->reset();
			}

			$this->add_existing($record);
			$counter++;

			if (($counter % $batch_size) == 0)
			{
				call_user_func_array($callback, $intargs);
				$this->save(true, false);
			}
		}

		if ($this->children AND ($counter % $batch_size) != 0)
		{
			call_user_func_array($callback, $intargs);
			$this->save(true, false);
		}

		$master =& $this->children[reset($this->primary_ids)];
		if ($master)
		{
			$master->post_save_once();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14027 $
|| ####################################################################
\*======================================================================*/
?>