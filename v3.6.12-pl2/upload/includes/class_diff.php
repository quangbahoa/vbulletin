<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Finds the difference between two strings using a line as the atom.
*
* @package 		vBulletin
* @version		$Revision: 13326 $
* @date 		$Date: 2005-09-25 19:12:35 -0500 (Sun, 25 Sep 2005) $
*
*/
class vB_Text_Diff
{
	/**
	* Array of atoms for the old string.
	*
	* @var	array
	*/
	var $data_old = array();

	/**
	* Size of the atoms of the old string (elements in the array)
	*
	* @var	integer
	*/
	var $data_old_len = 0;

	/**
	*  Array of atoms for the new string.
	*
	* @var	array
	*/
	var $data_new = array();

	/**
	* Size of the atoms of the new string (elements in the array)
	*
	* @var	integer
	*/
	var $data_new_len = 0;

	/*
	* The old_len x new_len table that is used to find the LCS. Indexes
	* correspond to characters in the old data. Each value will contain
	* a pipe delimited string, that when decompressed, corresponds to
	* characters from the new data.
	*
	* @var	array
	*/
	var $table = array();

	/**
	* Constructor. Sets up the data.
	*
	* @param	string	Old data in string format.
	* @param	string	New data in string format.
	*/
	function vB_Text_Diff($data_old, $data_new)
	{
		$this->data_old = preg_split('#(\r\n|\n|\r)#', $data_old);
		$this->data_old_len = sizeof($this->data_old);

		$this->data_new = preg_split('#(\r\n|\n|\r)#', $data_new);
		$this->data_new_len = sizeof($this->data_new);
	}

	/**
	* Builds the table used to find the LCS of the old and new strings.
	* This is then used to build the diff.
	*/
	function populate_table()
	{
		$this->table = array();

		$prev_row = array();
		for ($i = -1; $i < $this->data_new_len; $i++)
		{
			$prev_row[$i] = 0;
		}

		for ($i = 0; $i < $this->data_old_len; $i++)
		{
			$this_row = array('-1' => 0);
			$data_old_value = $this->data_old[$i];

			for ($j = 0; $j < $this->data_new_len; $j++)
			{
				if ($data_old_value == $this->data_new[$j])
				{
					$this_row[$j] = $prev_row[$j - 1] + 1;
				}
				else if ($this_row[$j - 1] > $prev_row[$j])
				{
					$this_row[$j] = $this_row[$j - 1];
				}
				else
				{
					$this_row[$j] = $prev_row[$j];
				}
			}

			$this->table[$i - 1] = $this->compress_row($prev_row);
			$prev_row = $this_row;
		}
		unset($prev_row);
		$this->table[$this->data_old_len - 1] = $this->compress_row($this_row);
	}

	/**
	* Compresses a row of the LCS table in order to reduce memory usage of the table.
	*
	* @param	array	Array of entries in a row of the LCS table
	*
	* @return	string	A pipe delimited version of the row
	*/
	function compress_row($row)
	{
		return implode('|', $row);

		//return serialize($row);
	}

	/**
	* Decompresses a row of an LCS table into an array of records. Note that the
	* indexing of the row begins at index -1 (for the sentinel values).
	*
	* @param	string	Compressed version of the row
	*
	* @return	array	Uncompressed version of the row; indexing begins at -1
	*/
	function decompress_row($row)
	{
		$return = array();
		$i = -1;
		foreach (explode('|', $row) AS $value)
		{
			$return[$i] = $value;
			++$i;
		}
		return $return;

		//return unserialize($row);
	}

	/**
	* Fetches the table created by populate_table().
	* Populates it first if necessary.
	*
	* @return	array	The table
	*/
	function &fetch_table()
	{
		if (sizeof($this->table) == 0)
		{
			$this->populate_table();
		}
		return $this->table;
	}

	/**
	* Fetches an array of objects that holds both strings split up into sections
	* such that a diff can be generated with it.
	*
	* @return	array	Array of vB_Text_Diff_Entry objects
	*/
	function &fetch_diff()
	{
		$table =& $this->fetch_table();
		$output = array();

		$match = array();
		$nonmatch1 = array();
		$nonmatch2 = array();

		$data_old_key = $this->data_old_len - 1;
		$data_new_key = $this->data_new_len - 1;

		$this_row = $this->decompress_row($table[$data_old_key]);
		$above_row = $this->decompress_row($table[$data_old_key - 1]);

		while ($data_old_key >= 0 AND $data_new_key >= 0)
		{
			if ($this_row[$data_new_key] != $above_row[$data_new_key - 1] AND $this->data_old[$data_old_key] == $this->data_new[$data_new_key])
			{
				// this is a non changed entry
				$this->process_nonmatches($output, $nonmatch1, $nonmatch2);
				array_unshift($match, $this->data_old[$data_old_key]);

				$data_old_key--;
				$data_new_key--;

				$this_row = $above_row;
				$above_row = $this->decompress_row($table[$data_old_key - 1]);
			}
			else if ($above_row[$data_new_key] > $this_row[$data_new_key - 1])
			{
				$this->process_matches($output, $match);
				array_unshift($nonmatch1, $this->data_old[$data_old_key]);

				$data_old_key--;

				$this_row = $above_row;
				$above_row = $this->decompress_row($table[$data_old_key - 1]);
			}
			else
			{
				$this->process_matches($output, $match);
				array_unshift($nonmatch2, $this->data_new[$data_new_key]);

				$data_new_key--;
			}
		}

		$this->process_matches($output, $match);
		if ($data_old_key > -1 OR $data_new_key > -1)
		{
			for (; $data_old_key > -1; $data_old_key--)
			{
				array_unshift($nonmatch1, $this->data_old[$data_old_key]);
			}
			for (; $data_new_key > -1; $data_new_key--)
			{
				array_unshift($nonmatch2, $this->data_new[$data_new_key]);
			}
			$this->process_nonmatches($output, $nonmatch1, $nonmatch2);
		}

		return $output;
	}

	/**
	* Processes an array of matching lines. Resets the $match array afterwards.
	*
	* @param	array	Array of vB_Text_Diff_Entry objects which will be returned by fetch_diff()
	* @param	array	Array of text that matches between the two strings
	*/
	function process_matches(&$output, &$match)
	{
		if (sizeof($match) > 0)
		{
			$data = implode("\n", $match);
			array_unshift($output, new vB_Text_Diff_Entry($data, $data));
		}

		$match = array();
	}

	/**
	* Processes an array of nonmatching lines. Resets text arrays afterwards.
	*
	* @param	array	Array of vB_Text_Diff_Entry objects which will be returned by fetch_diff()
	* @param	array	Array of text from the old string which doesn't match the new string
	* @param	array	Array of text from the new string which doesn't match the old string
	*/
	function process_nonmatches(&$output, &$text_old, &$text_new)
	{
		$s1 = sizeof($text_old);
		$s2 = sizeof($text_new);

		if ($s1 > 0 AND $s2 == 0)
		{
			// lines deleted
			array_unshift($output, new vB_Text_Diff_Entry(implode("\n", $text_old), ''));
		}
		else if ($s2 > 0 AND $s1 == 0)
		{
			// lines added
			array_unshift($output, new vB_Text_Diff_Entry('', implode("\n", $text_new)));
		}
		else if ($s1 > 0 AND $s2 > 0)
		{
			// substitution
			array_unshift($output, new vB_Text_Diff_Entry(implode("\n", $text_old), implode("\n", $text_new)));
		}

		$text_old = array();
		$text_new = array();
	}
}

/**
* Represents a single entry in a diff.
* Can be a group of unchanged, added, deleted, or changed lines.
*
* @package 		vBulletin
* @version		$Revision: 13326 $
* @date 		$Date: 2005-09-25 19:12:35 -0500 (Sun, 25 Sep 2005) $
*
*/
class vB_Text_Diff_Entry
{
	/**
	* Text from the old string.
	*
	* @var	string
	*/
	var $data_old = '';

	/**
	* Text from the new string
	*
	* @var	string
	*/
	var $data_new = '';

	/**
	* Constructor. Sets up data.
	*
	* @param	string	Text from the old string
	* @param	string	Text from the new string
	*/
	function vB_Text_Diff_Entry($data_old, $data_new)
	{
		$this->data_old = $data_old;
		$this->data_new = $data_new;
	}

	/**
	* Fetches data from the old string
	*
	* @return	string
	*/
	function fetch_data_old()
	{
		return $this->data_old;
	}

	/**
	* Fetches data from the new string
	*
	* @return	string
	*/
	function fetch_data_new()
	{
		return $this->data_new;
	}

	/**
	* Fetches the name of the CSS class that should be used for the data from the old string
	*
	* @return	string
	*/
	function fetch_data_old_class()
	{
		if ($this->data_old == $this->data_new)
		{
			return 'unchanged';
		}
		else if ($this->data_old AND empty($this->data_new))
		{
			return 'deleted';
		}
		else if (trim($this->data_old) === '')
		{
			return 'notext';
		}
		else
		{
			return 'changed';
		}
	}

	/**
	* Fetches the name of the CSS class that should be used for the data from the new string
	*
	* @return	string
	*/
	function fetch_data_new_class()
	{
		if ($this->data_old == $this->data_new)
		{
			return 'unchanged';
		}
		else if ($this->data_new AND empty($this->data_old))
		{
			return 'added';
		}
		else if (trim($this->data_new) === '')
		{
			return 'notext';
		}
		else
		{
			return 'changed';
		}
	}

	/**
	* Prepares a section of text to be displayed in a diff
	* by making it display closer to normal in a browser
	*
	* @param	string	Text to prepare
	* @param	boolean	Whether to allow the text to wrap on its own (false uses code tags, true uses pre tags)
	*
	* @return	string	Prepared text
	*/
	function prep_diff_text($string, $wrap = true)
	{
		if (trim($string) === '')
		{
			return '&nbsp;';
		}
		else
		{
			if ($wrap)
			{
				$string = nl2br(htmlspecialchars_uni($string));
				$string = preg_replace('#( ){2}#', '&nbsp; ', $string);
				$string = str_replace("\t", '&nbsp; &nbsp; ', $string);
				return "<code>$string</code>";
			}
			else
			{
				return '<pre style="display:inline">' . "\n" . htmlspecialchars_uni($string) . '</pre>';
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13326 $
|| ####################################################################
\*======================================================================*/
?>