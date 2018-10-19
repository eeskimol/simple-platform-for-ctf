<?php

namespace App\Http\Controllers\User;

use App\Model\AttackerFinishType;
use Illuminate\Support\Carbon;
use App\Model\QuestionFinishType;
use App\Tools\Code;
use App\Http\Controllers\Controller;
use App\Model\Announcement;
use App\Model\Hint;
use App\Model\Links;
use App\Model\News;
use App\Model\NewsPic;
use App\Model\Question;
use App\Model\QuestionFinished;
use App\Model\QuestionType;
use App\Model\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
	//展示4张新闻图片与6条资讯
	public function index()
	{
		$news_pic = NewsPic::limit(4)->offset(0)->orderBy("pic_id")->get();
		$news = News::limit(6)->offset(0)->orderBy("news_id")->get();
		return view("user/dctf")->with("news_pic",$news_pic)->with("news",$news);
    }
	//跳转至网页说明
	public function know()
	{
		return view("know");
	}
	//退出登录并跳转至登录页面
	
	public function logout()
	{
		session(['user'=>null]);
		return redirect("login");
	}
	//修改密码
	public function change_password()
	{
		if ($input = Input::all())
		{
			//设定新密码规则
			$rules=[
				"new_pass"=>"required | between:6,20 | confirmed",
			];
			//设定每条规则对应的错误提示信息
			$message=[
				"new_pass.required"=>"新密码不能为空！",
				"new_pass.between"=>"新密码必须在6-20位之间！",
				"new_pass.confirmed"=>"新密码与确认密码不匹配！",
			];
			//依据输入，判定规则与提示信息生成validator
			$validator = Validator::make($input,$rules,$message);

			//判断用户输入的原密码是否与数据库内容一致，如一致则更新密码否则报错
			if($validator->passes())
			{
				$user = User::find(session("user.user_id"));
				if (!$user)
				{
					
					return back()->with("errors","暂时查询不到该用户，请稍后重试");
				}
				else
				{
					$_password = Crypt::decrypt($user->user_password);
					if($input['old_pass'] === $_password)
					{
						$user->user_password = Crypt::encrypt($input["new_pass"]);
						$user->update();
						return back()->with("errors","修改密码成功");
					}else{
						return back()->with("errors","原密码错误!");
					}
				}
			}
			else
			{
				return back()->withErrors($validator);
			}

		}
		else
		{
			return view("user/change_password");
		}

	}

	public function information()
	{
		$user = User::find(session("user")->user_id);
		return view("user/information");
	}

	public function rank()
	{
		$users = User::orderBy("user_count","desc")->get();
		return view("user/rank")->with("users",$users);
	}

	public function announcement()
	{
		$announcement = Announcement::orderBy("created","desc")->get();
		return view("user/announcement")->with("announcement",$announcement);
	}

	public function vs()
	{
		$question_finished = QuestionFinished::where("user_id",'=',session("user")->user_id)->get();
		$vs = Question::where("question_own","=","vs")->get();
		$question_type = QuestionType::all();
		$announcement = Announcement::orderBy("created","desc")->take(3)->offset(0)->get();
		return view("user/vs")->with("vs",$vs)->with("question_type",$question_type)->with("announcement",$announcement)->with("question_finished",$question_finished);
	}
	//查看提示函数
	public function hint($question_id)
	{
		$hint = Hint::where("question_id", $question_id)->orderBy("created")->get();
		return view("user/hint")->with("hint",$hint);
	}

	//验证flag
	public function confirm_flag()
	{
		//获取输入
		if($input = Input::all())
		{
			//根据题目编号查找对应的答案并与输入进行比对
			$question = Question::find($input['question_id']);
			if($question)
			{
				
				$confirm_flag = Crypt::decrypt($question->question_flag);
				if($input['flag']===$confirm_flag){

					$question_confirm_finished = QuestionFinished::where(['question_id'=>$input['question_id'],"user_id"=>session('user')->user_id])->get();
					//重复答题验证
					if($question_confirm_finished->first())
					{
						$data=[
							"sta"=>0,
							"msg"=>"您已经提交过该题目的FLAG"
						];
					}
					else{
						$question = Question::find($input['question_id']);
						//根据完成题目的类型更新用户的答题记录
						$user = QuestionFinishType::find(session('user')->user_id);
						$question_count = $question->question_count;
						if($question_count == "easy")
						{
							$user->easy = $user->easy+1;
							$user->save();
						}
						else if($question_count == "middle")
						{
							$user->middle = $user->middle+1;
							$user->save();
						}
						else if($question_count == "hard")
						{
							$user->hard = $user->hard+1;
							$user->save();
						}
						else
						{
							$data=[
								"sta"=>0,
								"msg"=>"错误"
							];
							return $data;
						}
						$question_finished = new QuestionFinished;
						$question_finished->question_id = $input['question_id'];
						$question_finished->user_id = session('user')->user_id;
						$question_finished->save();
						$question->question_finished = $question->question_finished+1;
						$question->update();
						$data=[
							"sta"=>1,
							"msg"=>"恭喜，FLAG正确"
						];
					}



				}
				else{
					$data=[
						"sta"=>0,
						"msg"=>"很遗憾，FLAG错误"
					];
				}

			}
			else{
				$data=[
					"sta"=>0,
					"msg"=>"没有该题目"
				];
			}

		}else{
			$data=[
				"sta"=>0,
				'msg'=>"没有内容"
			];
		}
		return $data;

	}

	//注册函数
	public function register()
	{
		//比对验证码
		if ($input = Input::all())
		{	$code = new Code();
			$_code=$code->get();
			if(strtoupper($input['code'])!==$_code)
			{
				return back()->with("errors","验证码错误");
			}
			/*
			学号必填，且为整形
			电子邮箱必填，且通过邮件地址判定
			密码必填，长度位于6-20,需要二次确认
			昵称必填
			头像必选，且上传必须为图片
			*/
			$rules = [
				"user_id"=>"required | integer:true",
				"user_email"=>"required | email:true",
				"user_password"=>"required | confirmed | between:6,20",
				"user_nickname"=>"required",
				"user_name"=>"required",
				"user_head"=>"required | image:true",
			];
			//规则判定对应的错误提示信息
			$message = [
				"user_id.required"=>"学号必填",
				"user_id.integer"=>"学号格式错误",
				"user_email.email"=>"邮箱格式错误",
				"user_email.required"=>"邮箱必填",
				"user_password.required"=>"密码必填",
				"user_password.confirmed"=>"两次密码不一致",
				"user_password.between"=>"密码在6-20位之间",
				"user_nickname.required"=>"昵称必填",
				"user_name.required"=>"真实姓名必填",
				"user_head.required"=>"请上传头像",
				"user_head.image"=>"上传的头像必须为图片",
			];
			
			$validator = Validator::make($input,$rules,$message);
			if($validator->passes())
			{
				if(User::find($input['user_id']))
				{
					return back()->with("errors","用户已存在");
				}
				
				$user_email = User::where("user_email",$input['user_email'])->get();
				if ($user_email->first())
				{
					return back()->with("errors","邮箱已注册");
				}
				
				$file = Input::file("user_head");
				$size = $file->getSize();
				
				if($size > 2*1024*1024){
					return back()->with('errors','上传文件不能超过2M');
				}
				//检查mimetype
				$mimeType = $file->getMimeType();
				
				if (!(($mimeType === "image/gif")
					|| ($mimeType === "image/jpeg")
					|| ($mimeType === "image/jpg")
					|| ($mimeType === "image/jpeg")
					|| ($mimeType === "image/x-png")
					|| ($mimeType === "image/png")))
				{
					return back()->with('errors','只允许上传jgp,png格式的图片');
				}
				//检查扩展文件名
				$allowedExts = array("jpeg", "jpg", "png");
				$ext = $file->getClientOriginalExtension();
				if(!in_array($ext,$allowedExts)){
					return back()->with('errors','只允许上传jgp,png格式的图片');
				}
				//判断文件是否是通过HTTP POST上传的
				$realPath = $file->getRealPath();

				if(!$realPath){
					return back()->with('errors','非法操作');
				}

				/*$dir = env("STORE_PATH","E:\wamp64\www\laravel\dctf\public\storage\headers");*/
				$path = Storage::putfile("public/headers",$file);
				if(!$path){
					return back()->with("errors","文件存储失败，请稍后重试");
				}
				//根据输入初始化用户
				$user = new User;
				$user->user_id = $input['user_id'];
				$user->user_name = $input['user_name'];
				$user->user_nickname = $input['user_nickname'];
				$user->user_email = $input['user_email'];
				$user->user_head = substr($path,7);
				$user->user_password = Crypt::encrypt($input['user_password']);
				$user->created=Carbon::now("Asia/Shanghai");
				$user->save();
			    //初始化做题信息 
				$attacker_finish_type = new AttackerFinishType();
				$attacker_finish_type->user_id = $input['user_id'];
				$attacker_finish_type->count=0;
				$attacker_finish_type->save();
						
				$question_finish_type = new QuestionFinishType();
				$question_finish_type->user_id = $input['user_id'];
				$question_finish_type->easy = 0;
				$question_finish_type->middle = 0;
				$question_finish_type->hard = 0;
				if($question_finish_type->save())
				{
					return redirect("login")->with("register","注册成功，请先查看网站说明之后登陆！！！");
				}
				else{
					return back()->with("errors","数据库错误，请稍后重试");
				}
			}
			else{
				return back()->withErrors($validator);
			}
		}
		else
		{
			return view("register");
		}
    }
}
