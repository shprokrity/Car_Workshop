<?php
require_once 'config.php';

// Get repair services for homepage display
$stmt = $pdo->query("SELECT * FROM repair_services WHERE status = 'available' ORDER BY service_name");
$services = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi Brand Workshop</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        
        .nav {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .nav-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: #fff;
            color: #667eea;
        }
        
        .btn-secondary {
            background-color: transparent;
            color: white;
            border: 1px solid white;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .hero {
            background: url('https://images.unsplash.com/photo-1486754735734-325b5831c3ad?ixlib=rb-4.0.3') center/cover;
            height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            margin-top: 60px;
            position: relative;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        
        .services {
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .services h2 {
            text-align: center;
            margin-bottom: 3rem;
            font-size: 2.5rem;
            color: #333;
        }
        
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .service-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
        }
        
        .service-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .service-card h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .service-card p {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .cta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
        }
        
        .cta h2 {
            margin-bottom: 1rem;
        }
        
        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            margin-top: 1rem;
        }
        
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 2rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <div class="logo">🔧 Multi Brand Workshop</div>
            <div class="nav-buttons">
                <?php if (isLoggedIn()): ?>
                    <div class="user-info">
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                        <?php if (isAdmin()): ?>
                            <a href="admin_panel.php" class="btn btn-secondary">Admin Panel</a>
                        <?php else: ?>
                            <a href="user_panel.php" class="btn btn-secondary">My Bookings</a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-primary">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">Login</a>
                    <a href="register.php" class="btn btn-secondary">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>Professional Auto Repair Services</h1>
            <p>Expert mechanics, quality service, and competitive prices</p>
            <?php if (!isLoggedIn()): ?>
                <a href="register.php" class="btn btn-primary btn-large">Get Started Today</a>
            <?php else: ?>
                <a href="booking.php" class="btn btn-primary btn-large">Book a Service</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="services">
        <h2>Our Repair Services</h2>
        <div class="service-grid">
            <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <div class="service-icon">🔧</div>
                    <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                    <p><?php echo htmlspecialchars($service['description']); ?></p>
                    <div class="price">$<?php echo number_format($service['price'], 2); ?></div>
                    <p><small><?php echo $service['duration_hours']; ?> hour(s)</small></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cta">
        <h2>Ready to Book Your Service?</h2>
        <p>Choose from our expert mechanics and schedule your repair today</p>
        <?php if (isLoggedIn()): ?>
            <a href="booking.php" class="btn btn-primary btn-large">Book Now</a>
        <?php else: ?>
            <a href="register.php" class="btn btn-primary btn-large">Register to Book</a>
        <?php endif; ?>
    </section>

    <footer class="footer">
        <p>&copy; 2024 Multi Brand Workshop. All rights reserved.</p>
    </footer>
</body>
</html>