<?php
$groups = array(
	'public' => array(
		'upload' => '上传', 
			@type { avatar 用户头像；student学生头像；notify 通知；feed 微博；comment 点评 space 呼啦圈}
			@student 学生头像需要
			@upfile 文件域
		'area' => '地区',
			getList
	),
	'apply' => '申请'
			getList	申请列表
				@type:1、学生+老师	2老师+学生,	3机构+老师,	4老师+机构,	 5好友申请,	 6学生+机构,	7机构+学生 注：type不传即收到的所有的申请
			deal	申请处理
				@id (apply_id)
				@status 1 通过 2拒绝
			exists	是否已经申请
				@from 学生|老师|用户 
				@to 学生|老师|用户 
				@student 学生ID 
				@school 学校机构ID				
			add		申请
				@type:1、学生+老师	2老师+学生,	3机构+老师,	4老师+机构, 5好友申请,	6学生+机构,	7机构+学生
				@to申请( 除+学生外：其他为呼啦ID或手机号)
				@student 学生为申请对象时｛+学生输入为家长呼啦ID或手机号，选择他的学生to=家长ID，student=学生ID｝
				@code 邀请/验证码 
				@message 申请消息
				@school 机构ID
				例：学生+老师 {type:1, to:hulaid,student:学生ID}
				例：老师+学生{type:2, to:家长ID,student:学生ID}
	),

	'blog'	=> '呼啦圈'
		mySpace, 我的呼啦圈
			@page
		userSpace, 用户的呼啦圈
			@uid
			@page
		post 发表动态
			@content
			@attach_ids
		repost	转发动态
			@blog_id	
			@content
			@attach_ids
			@curid
		comment	评论
			@content
			@blog_id
			@to_comment_id
		delComment	删除评论
			@comment_id
		getBlogCommentList	评论列表
			@blog_id
			@page
		getBlog	动态详情
			@blog_id
		remove	删除
			@blog_id
			
		digg 赞
				@type（add|del）
				@blog_id			
		)


	'friend' =>	好友
		getList
	
	'student' => 学生
		info 学生详情
			@character student家长 teacher老师 school机构
			@student 学生ID
			@school 获取机构的学生列表(非必须)
		update	修改
			@id	student.id
			@name
			@nickname
			@birthday
			@tag
			@relation
		getList	学生列表
			@character parent家长, teacher老师, school 机构,grade班级,event课程
			@school 获取机构的学生列表(非必须)
			@grade 获取班级的学生列表(非必须)
			@event 课程学生列表(非必须)
			@uid 获取某个老师或某个家长的学生（character）
		add	添加学生
			@name
			@nickname
			@birthday
			@tag
			@relation

	'feed' => 微博
		getFollowingList	关注列表
			@uid 用户id 
			@page 页码
		deleteComment	删除微博评论
			@comment_id
		remove	删除微博
			@feed_id
		post	发表微博
			@content
			@attach_ids
		defollow	取消微博关
			@fid
		delCollect	取消微博收藏
			@feed_id
		follow	微博关注
			@fid  关注的用户id
		collect	微博收藏
			@feed_id 微博id		
		userSpace	微博用户首页
			@uid
		getFeedCommentList	微博评论列表
			@feed_id
		getFeed	微博详情
			@feed_id
		mySpace 我的微博
			@page

		getFollowerList
			@uid
			@page
		comment
			@content
			@feed_id
			
	'course' => 授课内容
		add	添加
			@type	course_type
			@title	标题
			@fee	费用
			@class_time	
			@experience	教学经验
		update
			@id
			@type	course_type
			@title	标题
			@fee	费用
			@class_time	
			@experience	教学经验
		delete
			@id
		getList	内容列表		
		types	分类列表
	
	'comment'	点评
		add		添加
			@character 角色( teacher、student、school)
			@teacher 家长对某个老师的点评（非必须）
			@student 1、老师对某个或某几个学生的点评；2、家长对老师评价3；多个用逗号分割
			@event 对某个课程的点评（非必须）
			@school
			@content
			@attach
		getList	点评列表
			@character 角色( teacher、student、school) ；【type=1，4，6 必须】
			@teacher 【type=3,4 必须】
			@student 【type=2,5,6】 必须]br/> @event【type=1必须】
			@school 【非】
			@type 【0老师 -> 点评； 1、课程点评，2、学生 -> 点评 3、老师详情页(用户)；4、老师详情（点评记录）；5、学生详情（用户）；6、学生详情（点评记录）身份】 @reply [0,1] 是否返回回复
			@reply 是否带点评
			@attach
		reply	回复
			@character 角色( teacher、student、school)
			@id (comment id)
			@content (string)
			@attach
	'grade'	班级
		add		添加班级
			@name
		delete	删除班级
			@id
		update	修改班级
			@id
			@name
		remove_student	移除学生
			@grade
			@student
		student_exists	学生是否存在
			@grade
			@student
		add_student	添加学生
			@grade
			@student
		getList	班级列表
	'user'	用户	
		register	注册
			@account
			@password
			@verify
			@token
			@nickname
			@gender
			@upfile
		login	登录
			@account
			@password
		pwd		修改密码
			@old
			@new
		exists
			value
			key
	'teacher'	老师
		info	老师详细
			@character	parent|school
			@school
			@student
		add		创建档案
			@province
			@city
			@area
			@target
			@background
			@mind
		update	修改档案
			@province
			@city
			@area
			@target
			@background
			@mind
		delete	删除档案
	'attendance'
		update	更新
			@character
			@student
			@event
			@absence
	'event'	课程
		add	发布
			@course
			@start_date
			@end_date
			@rec_type
			@end
			@is_loop
			@color
			@class_time
			@student
			@text
			@grade
		update
			@id	// pid#length	
			@course
			@start_date
			@end_date
			@rec_type
			@end
			@is_loop
			@color
			@class_time
			@student
			@text
			@grade
			@whole
		info 详情
			@id //pid#length
);