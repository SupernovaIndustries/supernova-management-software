<!DOCTYPE html>
<html>
<head>
    <title>Test Login</title>
</head>
<body>
    <h1>Test Login</h1>
    <form method="POST" action="/admin/login">
        <input type="hidden" name="_token" value="<?php echo $_GET['token'] ?? ''; ?>">
        <input type="email" name="email" placeholder="Email" value="alessandro.cursoli@supernovaindustries.it"><br>
        <input type="password" name="password" placeholder="Password" value="password"><br>
        <button type="submit">Login</button>
    </form>
    
    <h2>Get Token</h2>
    <a href="/test" target="_blank">Get CSRF Token from /test</a>
    
    <h2>Direct Links</h2>
    <a href="/admin">Admin</a><br>
    <a href="/admin/login">Admin Login</a><br>
</body>
</html>