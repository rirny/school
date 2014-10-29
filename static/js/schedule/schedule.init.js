scheduler.customer = function(){
	scheduler.config.first_hour = 7;
	scheduler.config.time_step = 5;
	scheduler.config.event_duration = 30;
	scheduler.config.limit_time_select = true;
	scheduler.config.readonly = false;
	scheduler.config.drag_move = false;
	scheduler.config.drag_create = false;
	scheduler.config.dblclick_create = false;
	
	scheduler.config.edit_on_create = false;
	scheduler.config.details_on_create = false;
	scheduler.config.drag_resize = false;
	
	scheduler.config.xml_date="%Y-%m-%d %H:%i";
	scheduler.date.date_to_str("%Y-%m-%d %H:%i");
	scheduler.templates.calendar_time = scheduler.date.date_to_str("%Y-%m-%d");
	scheduler.config.repeat_date = "%Y-%m-%d";
	
	if(scheduler.hlp.module){
		if(scheduler.module == "school"){
			scheduler.config.icons_edit = ["icon_save", "icon_cancel"];
			scheduler.config.icons_select = ["icon_details", "icon_comment", "icon_attendance"];
			scheduler.config.buttons_left = ["dhx_save_btn", 'dhx_release_btn', "dhx_cancel_btn"];
		}else if(scheduler.module == "teacher"){
			scheduler.config.icons_edit = ["icon_save", "icon_cancel"];
			scheduler.config.icons_select = ["icon_info"];
			scheduler.config.buttons_left = ["dhx_save_btn"];
		}else if(scheduler.module == "student"){
			scheduler.config.icons_edit = ["icon_save", "icon_cancel"];
			scheduler.config.icons_select = ["icon_info"];
			scheduler.config.buttons_left = ["dhx_save_btn"];
		}
	}
	//scheduler.config.readonly_form = false;
	
	// 课程样式
	//scheduler.templates.event_class = function(start, end, event){
		//if(typeof event.is_comment == 'string' && event.is_comment==1) return "comment"; //已经考评
		//if(typeof event.readonly == 'string' && event.readonly==1) return "past_event"; // 已经过期
		//if(start.valueOf() < scheduler._currentDate().valueOf()) return "past";
	//};
	if(!scheduler.hlp.start || !scheduler.hlp.end){
		var start = scheduler.date.week_start(scheduler._currentDate());
		var start_date = start.Format("yyyy-MM-dd");
		start.setDate(start.getDate()+6);
		var end_date = start.Format("yyyy-MM-dd");
		scheduler.init('scheduler_here', new Date(),"week");
	}else{
		start_date = scheduler.hlp.param.start;
		end_date = scheduler.hlp.param.end;
		scheduler.init('scheduler_here', new Date(Date.parse(start_date.replace(/-/g, "/"))),"week");
	}
	scheduler.hlp.param.start = start_date;
	scheduler.hlp.param.end = end_date;
	scheduler.load(scheduler.hlp.build());

	scheduler.attachEvent("onDblClick",function(event_id, native_event_object){
		return false;
	});
	
	scheduler.attachEvent("onViewChange",function(type, native_event_object, url){
		if(type =="week"){
			var start = scheduler.date.week_start(native_event_object);
			var start_date = start.Format("yyyy-MM-dd");
			start.setDate(start.getDate()+6);
			var end_date = start.Format("yyyy-MM-dd");
		}else if(type =="month"){
			var start = scheduler.date.month_start(native_event_object);
			var start_date = start.Format("yyyy-MM-dd");
			start.setMonth(start.getMonth()+1);
			start.setDate(start.getDate()-1);
			var end_date = start.Format("yyyy-MM-dd");
		}else if(type =="year"){
			var start = scheduler.date.year_start(native_event_object);
			var start_date = start.Format("yyyy-MM-dd");
			start.setFullYear(start.getFullYear()+1);
			start.setDate(start.getDate()-1);
			var end_date = start.Format("yyyy-MM-dd");
		}else if(type =="day"){
			var start_date = native_event_object.Format("yyyy-MM-dd");
			var end_date = start_date;
		}

		scheduler.hlp.param.start = start_date;
		scheduler.hlp.param.end = end_date;
		url = url || scheduler.hlp.build();
		scheduler.load(url);
	});
};


Date.prototype.Format = function (fmt) { //author: meizz 
    var o = {
        "M+": this.getMonth() + 1, //月份 
        "d+": this.getDate(), //日 
        "h+": this.getHours(), //小时 
        "m+": this.getMinutes(), //分 
        "s+": this.getSeconds(), //秒 
        "q+": Math.floor((this.getMonth() + 3) / 3), //季度 
        "S": this.getMilliseconds() //毫秒 
    };
    if (/(y+)/.test(fmt)) fmt = fmt.replace(RegExp.$1, (this.getFullYear() + "").substr(4 - RegExp.$1.length));
    for (var k in o)
    if (new RegExp("(" + k + ")").test(fmt)) fmt = fmt.replace(RegExp.$1, (RegExp.$1.length == 1) ? (o[k]) : (("00" + o[k]).substr(("" + o[k]).length)));
    return fmt;
}