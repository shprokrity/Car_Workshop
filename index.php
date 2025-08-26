<?php
require_once 'config.php';

// Get repair services for homepage display
$stmt = $pdo->query("SELECT * FROM repair_services WHERE status = 'available' ORDER BY service_name");
$services = $stmt->fetchAll();

// Service icons mapping
$service_icons = [
    'Oil Change' => 'oil change.jpg',
    'Brake Repair' => 'brake-discs.png',
    'Engine Diagnostic' => 'engine.png',
    'Transmission Service' => 'transmission service.webp',
    'Battery Replacement' => 'car-battery.png',
    'Tire Rotation' => 'tire.png'
];
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #667eea 50%, #764ba2 75%, #8b5a3c 100%);
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, rgba(30,30,30,0.95) 0%, rgba(70,50,40,0.95) 50%, rgba(20,20,20,0.95) 100%);
            backdrop-filter: blur(10px);
            color: white;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
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
            background: linear-gradient(45deg, #ff6b6b, #feca57, #48dbfb, #ff9ff3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .nav-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.7rem 1.5rem;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid #667eea;
            backdrop-filter: blur(10px);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .hero {
            background: 
                linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)),
                url('workshop.avif') center/cover;
            height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            margin-top: 60px;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255,107,107,0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(72,219,251,0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255,159,243,0.2) 0%, transparent 50%);
            animation: gradientShift 8s ease-in-out infinite alternate;
        }
        
        @keyframes gradientShift {
            0% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            animation: fadeInUp 1s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .hero h1 {
            font-size: 4rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #fff, #feca57, #ff9ff3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .hero p {
            font-size: 1.4rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .notification-bar {
            background: linear-gradient(90deg, #48dbfb, #0abde3);
            color: white;
            padding: 0.8rem;
            text-align: center;
            position: fixed;
            top: 80px;
            width: 100%;
            z-index: 999;
            transform: translateY(-100%);
            transition: transform 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .notification-bar.show {
            transform: translateY(0);
        }
        
        .notification-bar.success {
            background: linear-gradient(90deg, #00d2d3, #54a0ff);
        }
        
        .notification-bar.error {
            background: linear-gradient(90deg, #ff6b6b, #ee5a52);
        }
        
        .services {
            padding: 6rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            margin-top: 4rem;
            margin-bottom: 4rem;
        }
        
        .services h2 {
            text-align: center;
            margin-bottom: 4rem;
            font-size: 3rem;
            color: white;
            background: linear-gradient(45deg, #667eea, #764ba2, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2.5rem;
        }
        
        .service-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0.1));
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102,126,234,0.1), rgba(118,75,162,0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .service-card:hover::before {
            opacity: 1;
        }
        
        .service-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.4);
        }
        
        .service-image {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            box-shadow: 0 8px 20px rgba(102,126,234,0.4);
            transition: transform 0.3s ease;
        }
        
        .service-card:hover .service-image {
            transform: rotate(360deg);
        }
        
        .service-image img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        
        .service-card h3 {
            margin-bottom: 1rem;
            color: white;
            font-size: 1.4rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        .service-card p {
            color: rgba(255,255,255,0.9);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .price-duration {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .price {
            font-size: 1.4rem;
            font-weight: bold;
            color: #feca57;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        .duration {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
        }
        
        .cta {
            background: linear-gradient(135deg, rgba(30,30,30,0.95) 0%, rgba(70,50,40,0.95) 50%, rgba(20,20,20,0.95) 100%);
            color: white;
            padding: 6rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 30% 30%, rgba(255,107,107,0.2) 0%, transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(72,219,251,0.2) 0%, transparent 50%);
            animation: gradientShift 6s ease-in-out infinite alternate;
        }
        
        .cta h2 {
            margin-bottom: 1rem;
            font-size: 2.5rem;
            position: relative;
            z-index: 2;
        }
        
        .cta p {
            position: relative;
            z-index: 2;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .btn-large {
            padding: 1.2rem 2.5rem;
            font-size: 1.2rem;
            margin-top: 2rem;
            position: relative;
            z-index: 2;
        }
        
        .footer {
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
            color: white;
            text-align: center;
            padding: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: rgba(255,255,255,0.9);
        }
        
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .service-grid {
                grid-template-columns: 1fr;
            }
            
            .nav {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div id="notification" class="notification-bar">
        <span id="notification-text"></span>
    </div>

    <header class="header">
        <nav class="nav">
            <div class="logo">🔧 Nature's Workshop</div>
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
            <h1>Professional Auto Repair</h1>
            <p>Expert mechanics, premium service, competitive prices</p>
            <?php if (!isLoggedIn()): ?>
                <a href="register.php" class="btn btn-primary btn-large">Get Started Today</a>
            <?php else: ?>
                <a href="booking.php" class="btn btn-primary btn-large">Book a Service</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="services">
        <h2>Our Services</h2>
        <div class="service-grid">
            <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <div class="service-image">
                        <?php if (isset($service_icons[$service['service_name']])): ?>
                            <img src="<?php echo $service_icons[$service['service_name']]; ?>" alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                        <?php else: ?>
                            <img src="maintenance.png" alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                    <p><?php echo htmlspecialchars($service['description']); ?></p>
                    <div class="price-duration">
                        <div class="price">BDT<?php echo number_format($service['price'], 2); ?></div>
                        <div class="duration"><?php echo $service['duration_hours']; ?> hour(s)</div>
                    </div>
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
        <p>&copy; Made for CSE391 Assignment</p>
    </footer>

    <script>
        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            const text = document.getElementById('notification-text');
            
            notification.className = `notification-bar ${type}`;
            text.textContent = message;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 5000);
        }

        // Show welcome notification for logged in users
        <?php if (isLoggedIn()): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showNotification('Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Ready to book your next service?', 'success');
            });
        <?php endif; ?>

        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add parallax effect to hero
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });
    </script>
</body>
</html>