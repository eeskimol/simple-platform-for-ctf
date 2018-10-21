<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>register</title>
    <link rel="stylesheet" href="{{url("css/user/register.css")}}">
    <link rel="stylesheet" href="{{url("css/admin/page.css")}}">
    <script type="text/javascript" src="{{url("js/jquery.min.js")}}"></script>
    <script type="text/javascript" src="{{url("org/layer/layer.js")}}"></script>
    <script type="text/javascript" src="{{url("js/user/tips.js")}}"></script>
</head>
<body background="{{url("images/bg.jpg")}}">
<a href="{{url("/")}}" style="text-decoration: none;color: white"><h1>DCTF</h1></a>
{{--<h1>DCTF</h1>--}}
<h2>用户注册</h2>
@if(count($errors)>0)
    @if(is_object($errors))
            <div class="errorBox" style="margin-left: 39%">{{$errors->first()}}</div><br>
    @else
        <div class="errorBox" style="margin-left: 39%">{{$errors}}</div><br>
    @endif
@endif
<div class="register">
    <form action="" method="post" enctype="multipart/form-data">
        {{csrf_field()}}
        <input type="text" name="user_id" placeholder="学号" id="id" onclick="remind('请输入10位学号','#id')">
        <input type="text" name="user_name" placeholder="您的真实姓名" id="username" onclick="remind('务必输入真实姓名','#username')">
        <input type="text" name="user_nickname" placeholder="昵称" id="nickname" onclick="remind('随心所欲的昵称','#nickname')"><br>
        <input type="text" name="user_email" placeholder="邮箱" id="email" onclick="remind('请输入正确的邮箱地址用于密码找回','#email')"><br>
        <label for="head">选择上传的头像：</label>
        <input type="file" name="user_head" id="file_upload" ><br>
        <input type="password" name="user_password" placeholder="密码" id="password" onclick="remind('请输入6-20位复杂的密码','#password')">
        <input type="password" name="user_password_confirmation" placeholder="确认密码"id="password_check" onclick="remind('保持密码一致','#password_check')">
        <br><input type="text" name="code" placeholder="验证码" style="width: 20%">
        <img onclick="this.src='{{url("admin/code")}}?'+Math.random()" src={{url("admin/code")}} ><br>
	<input type="submit" value="注册">
    </form>
</div>
@extends("layouts.logo")
</body>
</html>
