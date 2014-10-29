function flashChecker(){
	var hasFlash=0;         //是否安装了flash
	var flashVersion=0; //flash版本
	var isIE=/*@cc_on!@*/0;      //是否IE浏览器

	if(isIE){
		var swf = new ActiveXObject('ShockwaveFlash.ShockwaveFlash');
			if(swf) {
				hasFlash=1;
				VSwf=swf.GetVariable("$version");
				flashVersion=parseInt(VSwf.split(" ")[1].split(",")[0]);
			}
	}else{
		if (navigator.plugins && navigator.plugins.length > 0){
		var swf=navigator.plugins["Shockwave Flash"];
		    if (swf){
				hasFlash=1;
		        var words = swf.description.split(" ");
		        for (var i = 0; i < words.length; ++i){
		            if (isNaN(parseInt(words[i]))) continue;
		            flashVersion = parseInt(words[i]);
				}
		    }
		}
	}
	return {f:hasFlash,v:flashVersion};
}

function confirmurl(url,message)
{
	if(confirm(message)) redirect(url);
}
function redirect(url) {
	//if(url.indexOf('://') == -1 && url.substr(0, 1) != '/' && url.substr(0, 1) != '?') url = $('base').attr('href')+url;
	location.href = url;
}
//滚动条
$(function(){
	//window.onresize = function(){

	//}
	//window.onresize();
	//inputStyle
	$(":text").addClass('input-text');
})

/**
 * 全选checkbox,注意：标识checkbox id固定为为check_box
 * @param string name 列表check名称,如 uid[]
 */
function selectall(name) {
	if ($("#check_box").attr("checked")==false) {
		$("input[name='"+name+"']").each(function() {
			this.checked=false;
		});
	} else {
		$("input[name='"+name+"']").each(function() {
			this.checked=true;
		});
	}
}

function openwinx(url,name,title,w,h) {
	if(!w) w=window.screen.width-4;
	if(!h) h=window.screen.height-95;
	var top = (window.screen.availHeight-30-h)/2; //获得窗口的垂直位置;
	var left = (window.screen.availWidth-10-w)/2; //获得窗口的水平位置;
	var obj=window.open(url,name,"top=" + top + ",left=" + left + ",width=" + w + ",height=" + h + ",toolbar=no,menubar=no,scrollbars=yes,resizable=yes,location=no,status=no");
	setTimeout(function(){
	    obj.document.title = title;
	}, 1000) ;
}

var calSelector = {
	weekNumbers: 1,
	dateFormat: "%Y-%m-%d",
	inputField : '',
	dateInit : true,
	trigger: '',
	showTime: false,
	minuteStep: 1,
	min: 20130101,
	//max: 20150212,
	onSelect : function(){}
};

function callCalendar(options)
{	
	if(typeof options != 'object') options = {};	
	var inputField = options.inputField;	
	var init = {
		inputField : inputField,
		trigger    : inputField,
		onSelect   : function() {
			/*
			var date = this.selection.get(this.dateFormat);
			alert(this.getTime());
			alert(this.dateInit);
			if(this.dateInit)
			{
				date = Calendar.intToDate(date);
			}
			date = Calendar.printDate(date, this.dateFormat);
			
			$("#" + inputField).focus();
			$("#" + inputField).val(date);
			*/
			this.hide();
			
		}
	}
	var op = $.extend({},calSelector,options, init);
	return new Calendar(op);
}

var uploadOptions = {
	'fileSizeLimit' : "5MB",	
	'width'	: 200,
	'height': 40,
	'buttonText' : '上传',
	'uploader' : 'public/upload',
	'fileObjName' : 'upfile',
	'onSelectError' : function (file, errorCode, errorMsg) {
	   if (errorCode == SWFUpload.QUEUE_ERROR.FILE_EXCEEDS_SIZE_LIMIT){
			alert('超过文件上传大小限制（5M）！');
			return;
	   }
	   alert(errorObj.type + ', Error: ' + errorObj.info);
	}
};

String.prototype.url_build = function(param)
{
	var params = new Array();
	var queryStr = '';
	for (key in param)
	{
		if(param[key]) params.push(key + '=' + param[key]);
	}
	if(params.lenght < 1) return this;
	if(params) queryStr = params.join("&");
	if(this.indexOf('&') > 0) return this + '&' + queryStr;
	if(this.indexOf('?') > 0) return this + queryStr;
	return this + '?' + queryStr;
}

function wordChange(total, obj)
{
	var totalWordsCount = total;                 
	var dynamicContent = $(obj).val();  
	var dynamicContentLength = dynamicContent.length;
	var wordsCount = 0;   
	for (var i=0; i<dynamicContentLength; i++) {   
		var charCode = dynamicContent.charCodeAt(i);   
		if ((charCode >= 0x0001 && charCode <= 0x007e) || (0xff60<=charCode &charCode<=0xff9f)) {   
			//wordsCount+=0.5;   
			wordsCount++;
		}else {      
			wordsCount++;
		}   
	}  
	var leftWordsCount = totalWordsCount - Math.ceil(wordsCount); 
	$("#wordCount").find("b").text(leftWordsCount);
	//$("#formSubmit").attr("disabled",false);
	return true;
	/*
	if(leftWordsCount >= 0) {  
		$("#wordCount").find("b").text(leftWordsCount);  
		//$("#formSubmit").attr("disabled",false).css({color : '#FFF'});
	} else {  
		//leftWordsCount = Math.ceil(wordsCount) - totalWordsCount;  
		$("#wordCount").find("b").text(leftWordsCount);  
		//$("#formSubmit").attr("disabled","disabled").css({color : '#BBB'}); ;  
	}
	*/
}

// 文本字符
String.prototype.textLength = function()
{
	var result = 0;
	for (var i=0; i< this.length ; i++) {   
		var charCode = this.charCodeAt(i);   
		if ((charCode >= 0x0001 && charCode <= 0x007e) || (0xff60<=charCode &charCode<=0xff9f)) { // 中文字算一个字
			result+=0.5;
		}else{
			result++;
		}
	}
	return result;
}