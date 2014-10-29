scheduler.form_blocks["selector"] = {
	render:function(config) {
		var html = '<div class="lessonSet"><div name="selector" class="lesson_section">';
			html+= '<div class="select"><ul></ul></div>';
			html+= '<div class="option"><ul>';			
			html+= '</ul></div></div></div>';
		return html;
	},
	
	set_value:function(node, value, ev, config) {					
		var optoin = new option_handle(config, value.split(','));
	},
	get_value:function(node, ev, config) {				
		
	},
	focus:function(node) {
	}
};

teacerh_priv = '<div class="priv" style="display:none">' +
	'<label name="priv" class="privCheck"><B>权限:</B>' + 
	'<input name="priv" value="1" type="checkbox" /> 授课' +
	'<input name="priv" value="2" type="checkbox" /> 考勤' +
	'<input name="priv" value="4" type="checkbox" /> 点评' +
	'</label></div>';

function option_handle(config)
{	
	this.name = config.name;
	this.type = config.type;
	this.source = config.options;

	this.options = {};

	this.priv = config.priv;
	this.val = value;	
	
	this.privs = new Array();
	this.src = new Array();

	this.html = '';

	this.init();

	this.init = function(){};

	this.setVal = function(val)
	{
		if(val)
		{
			for(i=0; i< val.length; i++)
			{
				var str = val[i];
				this.src.push(src[0]);
				if(this.privs)
					this.src.push(str[1]);
			}
		}
	};

	this.option_item = '<li onclick="addSelect(\'%s\',\'%s\',\'%s\',\'%s\')" uid="%s" title="%s" priv="%s">%s</li>';
	// <li onclick="addSelect('teacher','4','龚靖培',true)" uid="4" title="龚靖培" priv="true">龚靖培</li>

	this.option = function()
	{
		var html = '';
		for (i in this.source)
		{
			var op = this.source[i];
			if(!this.src.in_array(i))
				html += this.option_add(i,op,priv);
				// html += this.format(this.option_item, this.name, i, op, this.priv, i, op, this.priv, op);
		}
		return html;
	};

	this.select_item = '<li uid="%s" title="%s" priv="%s">%s<input name="%s" type="hidden" value="%s"><cite onclick="delSelect(this)">删除</cite></li>';

	this.selected = function()
	{		
		if(this.src.length < 1) return ;
		var html = '';
		for (i=0;i<this.src.length; i++)
		{
			var index = this.src.length[i];
			var op = this.source[index];
			html += this.format(this.select_item, index, op, this.priv, index, index);
		}
		return html;
	};
	
	this.option_add = function()
	{
	
	};
	this.option_del = function()
	{
	
	};
	this.select.add = function()
	{
	};
	this.select.del = function()
	{
	};
	this.format = function()
	{
		var arg = arguments, str = arg[0] || '', i, n;
		for (i=1, n=arg.length; i<n; i++){str=str.replace(/%s/, arg[i]);}
		return str;
	}
}

Array.prototype.in_array = function(e)
{
	for(i=0;i return !(i==this.length);
}