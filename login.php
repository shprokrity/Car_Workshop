<?php
require_once 'config.php';

$error = '';

if ($_POST) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'admin') {
                header('Location: admin_panel.php');
            } else {
                header('Location: user_panel.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Multi Brand Workshop</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1.5rem;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: bold;
        }
        
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        input[type="text"]:focus, input[type="password"]:focus, input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #fcc;
        }
        
        .links {
            text-align: center;
            margin-top: 1rem;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">🔧 Workshop Login</div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username or Email:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="links">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="index.php">Back to Home</a></p>
        </div>
    </div>
</body>
</html>