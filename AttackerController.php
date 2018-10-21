<?php

namespace App\Http\Controllers\user;

use App\Model\Announcement;
use App\Model\Attacker;
use App\Model\AttackerFinished;
use App\Model\AttackerFinishType;
use App\Model\AttackerType;
use App\Model\AttackerWriteUp;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
//靶场与靶机控制器控制器
//方法：
//		1.public function show_wp($attacker_id)：根据序号显示该靶机下的writeup
//		2.public function index() ：默认页面，显示所有的题目并以漏洞靶机的破解人数降序排序
//		3.public function show($attacker_type)：显示某一分类下的靶机(web,pwn,漏洞环境等)
//		4.public function show_attacker($attacker_id)：显示该靶机信息
//		5.public function upload_attacker()：上传与创建题目环境
//		6.public function upload_attacker():进入靶机的发布界面
//		7.public function edit($attacker_id):进入靶机/题目环境的编辑界面
//		8.public function update($attacker_id)：修改靶机/题目的flag
//		9.public function my_attacker()：查看自己的题目/靶机
//		10.public function destroy($attacker_id)：删除题目环境/靶机
//		11.public function upload()：发布靶机
//		12.public function confirm_flag():判断flag是否正确
//		13.public function upload_wp($attacker_id)：跳转至writeup上传视图	
//		14.public function upload_writeup($attacker_id)：先完成题目再上传WriteUp且每个用户只能为一道题上传一个writeup文件
	
class AttackerController extends Controller
{
	public function show_wp($attacker_id)
	{
		$writeup = AttackerWriteUp::where("attacker_id",$attacker_id)->get();
		return view("user/attacker_wp")->with("writeup",$writeup);
	}

	public function index()
	{
		$announcement = Announcement::orderBy("created","desc")->take(3)->offset(0)->get();
		$attacker_type = AttackerType::all();
		$all_attacker_type = AttackerType::all();
		$attacker = Attacker::orderBy("finish","desc")->get();
		$attacker_finished = AttackerFinished::where("user_id",session("user.user_id"))->orderBy("attacker_id")->get();
		return view("user/attacker",compact("announcement","attacker_type","attacker","all_attacker_type","attacker_finished"));
    }

	public function show($attacker_type)
	{
		$announcement = Announcement::orderBy("created","desc")->take(3)->offset(0)->get();
		$all_attacker_type = AttackerType::all();
		$attacker = Attacker::where("attacker_type",$attacker_type)->orderBy("finish","desc")->get();
		$attacker_finished = AttackerFinished::where(["user_id"=>session("user.user_id")])->orderBy("attacker_id")->get();
		return view("user/attacker",compact("announcement","all_attacker_type","attacker_type","attacker","attacker_finished"));
    }

	public function show_attacker($attacker_id)
	{
		$attacker = Attacker::find($attacker_id);
		$user = Attacker::find($attacker_id)->hasOneUser;
		return view("user/show_attacker",compact("attacker","user"));
    }

	public function upload_attacker()
	{
		$attacker_type = AttackerType::all();
		return view("user/attacker_upload_index")->with("attacker_type",$attacker_type);
    }

	public function edit($attacker_id)
	{
		$attacker = Attacker::where(['attacker_id'=>$attacker_id,"user_id"=>session("user.user_id")])->get()->first();
		$attacker->attacker_flag=Crypt::decrypt($attacker->attacker_flag);
		$attacker_type = AttackerType::all();
		return view("user/attacker_edit")->with("attacker",$attacker)->with("attacker_type",$attacker_type);
    }

	public function update($attacker_id)
	{
		$input = Input::except("_token","_method");
		$input["attacker_flag"]=Crypt::encrypt($input["attacker_flag"]);
		$result = Attacker::where(["attacker_id"=>$attacker_id,"user_id"=>session("user.user_id")])->update($input);
		if($result){
			return back()->with("errors","修改成功");
		}
		else{
			return back()->with("errors","无更新内容或更新失败，请稍后重试");
		}
	}

	public function my_attacker()
	{
		$user_attacker = Attacker::where("user_id",session("user.user_id"))->get();
		return view("user/MyAttacker")->with("user_attacker",$user_attacker);
	}

	public function destroy($attacker_id)
	{
		$result = Attacker::where(["attacker_id"=>$attacker_id,"user_id"=>session("user.user_id")])->get()->first();
		if($result)
		{
			if($result->finish>0)
			{
				$data=[
					"sta"=>0,
					"msg"=>"已经有人完成题目，删除失败，请联系管理员"
				];
			}
			else{
				$r = $result->delete();
				if($r)
				{
					$data=[
						"sta"=>1,
						"msg"=>"删除成功"
					];
				}
				else{
					$data=[
						"sta"=>0,
						"msg"=>"删除失败"
					];
				}
			}
		}
		return $data;
	}
	
	public function upload()
	{
		if ($input = Input::all())
		{
				$rules = [
					"attacker_name"=>"required | between:0,14",
					"attacker_type"=>"required",
					"attacker_describe"=>"required",
					"attacker_flag"=>"required",
					"attacker_ip"=>"required | url:true",
				];

				$message = [
					"attacker_name.required"=>"请填写靶机名称",
					"attacker_name.between"=>"靶机名称在14个字以内",
					"attacker_type.required"=>"请选择靶机类型",
					"attacker_describe.required"=>"请填写靶机描述",
					"attacker_flag.required"=>"请填写靶机flag",
					"attacker_ip.required"=>"请填写靶机IP",
					"attacker_ip.url"=>"IP格式错误",
				];

			$validator = Validator::make($input,$rules,$message);

			if ($validator->passes())
			{

				$all_attacker = Attacker::where(["attacker_name"=>$input["attacker_name"],"attacker_type"=>$input['attacker_type']])->get();
				if ($all_attacker->first())
				{
					return back()->with("errors","该靶机名称已经被使用了哦，请换个名字上传");
				}

				$attacker = new Attacker();
				$attacker->attacker_name = $input['attacker_name'];
				$attacker->attacker_describe = $input['attacker_describe'];
				$attacker->attacker_type = $input['attacker_type'];
				$attacker->attacker_flag = Crypt::encrypt($input['attacker_flag']);;
				$attacker->attacker_ip = $input['attacker_ip'];
				$attacker->created = Carbon::now("Asia/Shanghai");
				$attacker->user_id = session("user.user_id");
				$attacker->save();


				return back()->with("errors","靶机发布成功");
			}
			else{
				return back()->withErrors($validator);
			}


		}else{
			return back();
		}
    }

	public function confirm_flag()
	{
		if($input = Input::all())
		{
			$attacker = Attacker::find($input['attacker_id']);
			if($attacker)
			{
				$confirm_flag = Crypt::decrypt($attacker->attacker_flag);
				if($input['flag']===$confirm_flag){

					$attacker_confirm_finished = AttackerFinished::where(['attacker_id'=>$input['attacker_id'],"user_id"=>session('user')->user_id])->get();
					if($attacker_confirm_finished->first())
					{
						$data=[
							"sta"=>0,
							"msg"=>"您已经提交过该靶机的FLAG"
						];
					}
					else{
						$user = AttackerFinishType::find(session('user')->user_id);
						$user->count = $user->count + 1;
						$user->update();

						$attacker_finished = new AttackerFinished;
						$attacker_finished->attacker_id = $input['attacker_id'];
						$attacker_finished->user_id = session('user')->user_id;
						$attacker_finished->created = Carbon::now("Asia/Shanghai");
						$attacker_finished->save();

						$attacker->finish = $attacker->finish+1;
						$attacker->update();
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
					"msg"=>"没有该靶机"
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

	public function upload_wp($attacker_id)
	{
		return view('user/upload_attacker_wp')->with("attacker_id",$attacker_id);
	}
	public function upload_writeup($attacker_id)
	{
		$attacker_finish = AttackerFinished::where(["attacker_id"=>$attacker_id,"user_id"=>session("user.user_id")])->get();
		
		if (!$attacker_finish->first()){
			return back()->with("errors","先完成题目再上传WriteUp");
		}else{


			$attacker = attacker::find($attacker_id);
			if (!$attacker)
			{
				return back()->with("errors","没有该题目，无法上传");
			}

			$write_upload = AttackerWriteUp::where(["attacker_id"=>$attacker_id,"user_id"=>session("user.user_id")])->get();
			if ($write_upload->first())
			{
				return back()->with("errors","已上传过该题目的WriteUp，禁止重复上传");
			}
			$input = Input::all();
			$rules=[
				"writeup_name"=>"required",
				"writeup_upload"=>"required"
			];

			$message=[
				"writeup_name.required"=>"请填写WriteUp的名字",
				"writeup_upload.required"=>"请选择上传的文件",
			];

			$validator = Validator::make($input,$rules,$message);
			//将接收到的writeup记录插入数据库内
			if ($validator->passes())
			{


				$write_up = new AttackerWriteUp();
				$write_up->user_id = session("user.user_id");
				$write_up->attacker_id = $attacker_id;
				$write_up->wp_link = null;
				$write_up->wp_name = $input['writeup_name'];

				$file = Input::file("writeup_upload");
				if (!$file)
				{
					return back()->with("errors","请选择上传的文件");
				}
				$size = $file->getSize();
				
				if($size > 50*1024*1024){
					return back()->with('errors','上传文件不能超过50M');
				}

				$mimeType = $file->getMimeType();
				
				if (!(($mimeType === "application/octet-stream")
					|| ($mimeType === "application/x-rar-compressed ")
					|| ($mimeType === "application/x-zip-compressed")
					|| ($mimeType === "application/octet-stream")
					|| ($mimeType === "application/msword")
					|| ($mimeType === "text/plain")
					|| ($mimeType === "text/markdown")
					|| ($mimeType === "application/vnd.ms-works")
					|| ($mimeType === "application/zip")))
				{
					return back()->with('errors','只允许上传zip,7z格式的文件');
				}
				//扩展文件名白名单
				$allowedExts = array("rar", "zip", "zipped","7z","doc","docx","md","wps");
				$ext = $file->getClientOriginalExtension();
				if(!in_array($ext,$allowedExts)){
					return back()->with('errors','只允许上传zip,7z格式的文件');
				}
				//判断文件是否是通过HTTP POST上传的
				$realPath = $file->getRealPath();

				if(!$realPath){
					return back()->with('errors','非法操作');
				}

				if (!is_dir("storage/attacker_writeup/".session("user.user_id"))) {
					File::makeDirectory("storage/attacker_writeup/".session("user.user_id"));
				}
				$file_name = $file->getClientOriginalName();

				$path = Storage::putfileAs("public/attacker_writeup/".session("user.user_id"),$file,session("user.user_nickname")."_".$attacker->attacker_type."_".$file_name);
				if(!$path){
					return back()->with("errors","文件存储失败，请稍后重试");
				}
				$write_up->wp_link = url("storage/".substr($path,7));
				$write_up->created = Carbon::now("Asia/Shanghai");
				$write_up->save();

				$attacker_count = AttackerFinishType::find(session("user.user_id"));
				$attacker_count->count = $attacker_count->count+0.5;
				$attacker_count->update();

				return back()->with("errors","上传成功");


			}else{
				return back()->withErrors($validator);
			}

		}
	}
	
}
