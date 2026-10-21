/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

var multi_input = new Array();
	
/**
*/
function vB_Multi_Input(varname, count, cpstylefolder)
{
	/**
	*/
	this.varname = varname;
	this.count = count;
	this.cpstylefolder = cpstylefolder;
	
	/**
	*/
	this.add = function()
	{
		var div = document.createElement('div');
			div.id = 'multi_input_container_' + this.varname + '_' + this.count;		
			div.appendChild(document.createTextNode((this.count + 1) + ' '));
			div.appendChild(this.create_input(this.count + 1));
		fetch_object('multi_input_fieldset_' + this.varname).appendChild(div);
		this.append_buttons(this.count++);
		return false;
	};
	
	/**
	*/
	this.create_input = function()
	{
		var input = document.createElement('input');
			input.type = 'text';
			input.size = 40;
			input.className = 'bginput';
			input.name = 'setting[' + this.varname + '][' + this.count + ']';
			input.id = 'multi_input_' + this.varname + '_' + this.count;
			input.tabIndex = 1;	
		return input;
	};
	
	/**
	*/
	this.create_button = function(index, img, moveby)
	{
		var a = document.createElement('a');
			a.varname = this.varname;
			a.index = index;
			a.moveby = moveby;
			a.href = '#';
			a.onclick = function() { return multi_input[this.varname].move(this.index, this.moveby); };
			var i = document.createElement('img');
				i.src = '../cpstyles/' + this.cpstylefolder + '/move_' + img + '.gif';
				i.alt = '';
				i.border = 0;
			a.appendChild(i);
		return a;
	};
	
	/**
	*/
	this.append_buttons = function(i)
	{
		var div = fetch_object('multi_input_container_' + this.varname + '_' + i);
			div.varname = this.varname;
			div.index = i;
		div.appendChild(document.createTextNode(' '));
		div.appendChild(this.create_button(i, 'down', 1));
		div.appendChild(document.createTextNode(' '));
		div.appendChild(this.create_button(i, 'up', -1));
	};
	
	/**
	*/
	this.fetch_input = function(i)
	{
		return fetch_object('multi_input_' + this.varname + '_' + i);
	};
	
	/**
	*/
	this.move = function(index, moveby)
	{		
		var values = new Array();
		
		for (var i = 0; i < this.count; i++)
		{
			values[i] = this.fetch_input(i).value;
		}
		
		if (index == 0 && moveby < 0)
		{
			for (var i = 0; i < this.count; i++)
			{
				this.fetch_input(i).value = (i == (this.count - 1) ? values[0] : values[i + 1]);
			}
		}
		else if (index == (this.count - 1) && moveby > 0)
		{
			for (var i = 0; i < this.count; i++)
			{
				this.fetch_input(i).value = (i == 0 ? values[this.count - 1] : values[i - 1]);
			}
		}
		else
		{		
			this.fetch_input(index).value = values[index + moveby];
			this.fetch_input(index + moveby).value = values[index];
		}
		
		values = null;
		
		return false;
	};
	
	// add move buttons to each input
	for (var i = 0; i < this.count; i++)
	{
		this.append_buttons(i);
	}
};

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15747 $
|| ####################################################################
\*======================================================================*/