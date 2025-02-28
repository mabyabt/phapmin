<?php
session_start();
$users = ['admin' => 'password']; // Change password for security

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    if (isset($users[$username]) && $users[$username] == $password) {
        $_SESSION["loggedin"] = true;
        header("Location: admin.php");
        exit;
    } else {
        echo "Invalid login.";
    }
}
?>

<form method="post">
    <label>Username: <input type="text" name="username"></label><br>
    <label>Password: <input type="password" name="password"></label><br>
    <button type="submit">Login</button>
</form>
