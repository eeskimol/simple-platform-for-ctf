<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>login</title>
    <link rel="stylesheet" href="{{url("css/user/register.css")}}">
    <link rel="stylesheet" href="{{url("css/user/login.css")}}">
    <script type="text/javascript" src="{{url("js/jquery.min.js")}}"></script>
    <script type="text/javascript" src="{{url("org/layer/layer.js")}}"></script>

</head>
<body background="{{url("images/bg.jpg")}}">
<a href="{{url("/")}}" style="text-decoration: none;color: white"><h1>DCTF</h1></a>
{{--<h1>DCTF</h1>--}}
<h2>用户登录</h2>
@if(session("msg"))
    <p style="text-align: center;margin: 0;font-size: 20px;font-weight: bold; color: red;letter-spacing: 2px">{{session("msg")}}</p>
@endif
@if(session("register"))
    <script>
        alert("注册成功，立即登录！！！！");
    </script>
@endif

<div class="login">
    <form class="login_form" action="" method="post">
		<!--CSRF防护-->
        {{csrf_field()}}	
        <input type="text" name="user_id" placeholder="学号"><br>
        <input type="password" name="user_pass" placeholder="密码"><br>
        <input type="text" name="code" placeholder="验证码" style="width: 20%">
        <img onclick="this.src='{{url("admin/code")}}?'+Math.random()" src={{url("admin/code")}} >
</div>
<input type="submit" value="Login">
</form>
<a href="{{url("password/forget")}}" style="color: white">Forget You Password ?</a>
@extends("layouts.logo")
</body>
</html>
