scheduler.form_blocks["selector"] = {
	render:function(config) {	
		return '<div class="lessonSet" id="' + config.name + 'Select"></div>';
	},
	set_value:function(node, value, ev, config) {
		var container = $("#" + config.name + "Select");		
		if(value && typeof(value) == 'string')
		{
			value = value.split(',');
			var res = new Array();
			if(config.name == 'teacher')
			{
				for(i=0;i<value.length; i++)
				{
					var v = value[i].split('_');
					res.push(v[0]);
				}
				value = res;
			}			
		}else{
			value = '';
		}		
		var html = scheduler.selector(config, value);
		container.html(html);
	},
	get_value:function(node, ev, config) {	
		alert(scheduler.selected.get(config.name));
		return scheduler.selected.get(config.name);
	},
	focus:function(node) {
		
	}
};

scheduler.selector = function(config, value)
{
	var select = scheduler.selected;
	var option = scheduler.optioner;
	option.init(config);
	option.format(value);
	select.init(config);
	select.format(value);
	var html = '<div name="selector" class="lesson_section">' +
		'<div class="select"><ul>' + select.html +'</ul></div>' + 
		'<div class="option"><ul>' + option.html + '</ul></div>' + 
		'</div></div>';
	return html;
}

scheduler.selected = {
	html : '',
	name : '',	
	options : {},
	config : {},
	init : function(config){
		this.config = config;
		this.name = config.name;
		this.options = config.options;
		this.html = '';		
		return this;
	},
	item : function(i, name, obj)
	{		
		return '<li uid="'  + i + '" onclick="scheduler.selected.remove(' + i + ',\'' + name + '\',\'' + obj +'\')" >' + name + 
			'<input type="hidden" value="' + i + '"/>' +
			'<cite>删除</cite></li>';
	},
	remove : function(i, name, obj){
		var container = $("#" + obj + "Select");
		var option = $(container).find('.option ul');
		var select = $(container).find('.select ul');
		var item = scheduler.optioner.item(i, name, obj);
		container.find(".select li[uid='" + i + "']").remove();		
		$(option).append(item);
	},
	get : function(obj){
		var container = $("#" + obj + "Select");
		var res = new Array();
		$(container).find(".select input[type='hidden']").each(function(i, o){
			var val = $(o).val();
			if(obj == 'teacher') val += "_7";
			res.push(val);
		});
		return res;
	},
	format : function(value)
	{
		this.html = '';		
		if (!value || value.length < 1) return ;
		var container = $("#" + this.name + "Select");
		for (i=0; i< value.length;i++)
		{
			var str = value[i];
			if(str.indexOf("_"))
			{
				var strs = str.split("_");
				str = strs[0];
			}			
			if(typeof this.options[str] == 'string')
			{
				this.html+= this.item(str, this.options[str], this.name);				
			}
		}		
	}
};

scheduler.optioner = {
	html : '',
	name : '',
	values : {},
	options : {},
	config : {},
	init : function(config){
		this.config = config;
		this.name = config.name;
		this.options = config.options;
		this.html = '';
		return this;
	},
	item : function(i, name, obj)
	{		
		return '<li uid="'  + i + '" onclick="scheduler.optioner.remove(' + i + ',\'' + name + '\',\'' + obj +'\')" >' + name + '</li>';
	},
	remove : function(i, name, obj)
	{
		var container = $("#" + obj + "Select");
		var option = $(container).find('.option ul');
		var select = $(container).find('.select ul');		
		var item = scheduler.selected.item(i, name, obj);
		$(option).find("li[uid='" + i + "']").remove();
		$(select).append(item);
	},
	format : function(value)
	{
		this.html = '';
		var op = this.options;
		//alert(typeof value + ' ' + value);
		for (i in op)
		{	
			if(!this.selected(value, i))
			this.html+= this.item(i, op[i], this.name);
		}		
	},
	selected : function(value, i)
	{
		if(!value) return false;
		for (var j=0; j<value.length;j++)
		{
			if(i == value[j]) return true;
		}
		return false;
	}
	//this.get = function(){};
};

Array.prototype.in_array = function(e) 
{ 
    for(var i=0;i<this.length;i++)
    {
        if(this[i] == e) return true;
    }
    return false;
}