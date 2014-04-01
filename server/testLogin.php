<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Bootstrap, from Twitter</title>
    <meta name="description" content="">
    <meta name="author" content="">

    <!-- Le styles -->
    <link href="lib/bootstrap/css/bootstrap.css" rel="stylesheet">
    <style type="text/css">
      /* Override some defaults */
      html, body {
        background-color: #eee;
      }
      body {
        padding-top: 40px; 
      }
      .container {
        width: 300px;
      }

      /* The white background content wrapper */
      .container > .content {
        background-color: #fff;
        padding: 20px;
        margin: 0 -20px; 
        -webkit-border-radius: 10px 10px 10px 10px;
           -moz-border-radius: 10px 10px 10px 10px;
                border-radius: 10px 10px 10px 10px;
        -webkit-box-shadow: 0 1px 2px rgba(0,0,0,.15);
           -moz-box-shadow: 0 1px 2px rgba(0,0,0,.15);
                box-shadow: 0 1px 2px rgba(0,0,0,.15);
      }

      .login-form {
        margin-left: 65px;
      }

      legend {
        margin-right: -50px;
        font-weight: bold;
        color: #404040;
      }
    </style>

</head>
<body>
  <div class="container">
    <div class="content">
      <div class="row">
        <div class="login-form">
          <h2>Login</h2>
          <form method="post" action="">
            <fieldset>
              <div class="clearfix">
                <input type="text" placeholder="Username" name="username">
              </div>
              <div class="clearfix">
                <input type="password" placeholder="Password" name="password">
              </div>
              <button class="btn btn-primary" name="login" type="submit">Sign in</button>
            </fieldset>
          </form>
        </div>
      </div>
    </div>
  </div> <!-- /container -->

<?php
include "standard.php";
include "./lib/password_compat/lib/password.php";

//if user did not click the login button show the login form
if(isset( $_POST['login'] )) {
    echo "Logged in!";
}var_dump($_POST);

for ($i = 0; $i < 3; $i++) {
    $password = "TestPassword";
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $verified = password_verify($password, $hash);
    
    echo "<br>".password_hash("TestDefaultPassword", PASSWORD_DEFAULT)." Verified: ".$verified;
}

?>

</body>
</html>