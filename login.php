<?php
// Start session securely
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Use only if HTTPS is available
session_start();

// Redirect if already logged in
if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: admin.php');
    exit;
}

// Define the users file
$users_file = 'users.json';

// Functions to manage encrypted users file
function getEncryptionKey() {
    // In production, use a secure method to generate/store this key
    // For simplicity, we're using a fixed key based on server info
    return hash('sha256', $_SERVER['HTTP_HOST'] . 'secure_cms_key');
}

function loadUsers() {
    global $users_file;
    if (file_exists($users_file)) {
        $encrypted_data = file_get_contents($users_file);
        $decrypted_data = openssl_decrypt(
            $encrypted_data, 
            'AES-256-CBC', 
            getEncryptionKey(), 
            0, 
            substr(hash('sha256', getEncryptionKey()), 0, 16)
        );
        return json_decode($decrypted_data, true) ?: [];
    }
    // Return default admin if no users file exists
    return [
        'admin' => [
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'admin'
        ]
    ];
}

// Initialize variables
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Process login data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate username
    if(empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err)) {
        // Load users
        $users = loadUsers();
        
        // Check if username exists and verify password
        if(isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
            // Password is correct, start a new session
            session_regenerate_id(true);
            
            // Store data in session variables
            $_SESSION["loggedin"] = true;
            $_SESSION["username"] = $username;
            $_SESSION["role"] = $users[$username]['role'];
            
            // Create CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Redirect to admin page
            header("location: admin.php");
            exit;
        } else {
            // Username doesn't exist or password is incorrect
            $login_err = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Secure Page Generator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            margin-top: 0;
            color: #333;
            text-align: center;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.5rem;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .error {
            color: #dc3545;
            margin-bottom: 1rem;
            text-align: center;
        }
        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        
        <?php if(!empty($login_err)): ?>
            <div class="error"><?php echo $login_err; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" class="<?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                <?php if(!empty($username_err)): ?>
                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <?php if(!empty($password_err)): ?>
                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <input type="submit" class="btn" value="Login">
            </div>
        </form>
    </div>
</body>
</html>