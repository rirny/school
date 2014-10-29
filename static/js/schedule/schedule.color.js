var colors = {
	"009999"	:	"默认",
	"003399"	:	"蓝色",
	"006633"	:	"深绿",
	"339933"	:	"新绿",
	"cc9900"	:	"棕黄",
	"cc3300"	:	"红色",
	"663399"	:	"紫色",
	"666666"	:	"灰色",
	"663300"	:	"棕色",	
	"000000"	:	"黑色"
};	

scheduler.form_blocks["color"] = {
	render:function(config) {
		var html = '<div id="color_selector">';
		var color = '';
		for (i in config.options)
		{
			if(color == '') color = i;
			html += '<div class="color_option" name="' + i +'" style="background:#'+ i +'" title="'+  config.options[i] +'" ><div class="to-select ' +(i==color ? 'bcp-selected' : '')+ '"></div></div>';
		}			
		return html +='<input type="hidden" name="color" value="#'+color+'" /></div>';
	},
	set_value:function(node, value, ev, config) {		
		if(!value || typeof value == 'undefinde') value = '#339933';
		value = value.substring(1);		
		$("#color_selector .color_option[name='" + value + "'] div").addClass('bcp-selected').parent().siblings().find(".to-select").removeClass('bcp-selected');
		$("input[name='color']").val("#" + value);		
	},
	get_value:function(node, ev, config) {				
		//document.write($("input[name='color']").html());
		return $("input[name='color']").val();
	},
	focus:function(node) {
		selectA();
	}
};

function color_select(color)
{		
	if (!color) return false;
	$("#color_selector .color_option[name='" + color + "'] div").addClass('bcp-selected').parent().siblings().find(".to-select").removeClass('bcp-selected');
	$("input[name='color']").val("#" + color);
}
function selectA()
{
	$("#color_selector .color_option").unbind('click').bind('click', function(){
		var color = $(this).attr('name');
		if($(this).find('.bcp-selected').length > 0) return false;
		$(this).find('div').addClass('bcp-selected').parent().siblings().find("div").removeClass('bcp-selected');
		$("input[name='color']").val("#" + color);
	}).hover(function(){$(this).css('border-color','#000');},function(){$(this).css('border-color','#FFF');});
}