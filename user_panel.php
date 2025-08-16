<?php
require_once 'config.php';
requireLogin();

// Get user's bookings
$stmt = $pdo->prepare("
    SELECT b.*, m.name as mechanic_name, rs.service_name, rs.price 
    FROM bookings b 
    JOIN mechanics m ON b.mechanic_id = m.id 
    JOIN repair_services rs ON b.service_id = rs.id 
    WHERE b.user_id = ? 
    ORDER BY b.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Panel - Multi Brand Workshop</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
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
            align-items: center;
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .welcome {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .welcome h1 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .card-icon {
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
        
        .card h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .card p {
            color: #666;
        }
        
        .bookings-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .bookings-section h2 {
            color: #333;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .booking-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: #f9f9f9;
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .booking-id {
            font-weight: bold;
            color: #667eea;
        }
        
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status.confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status.completed {
            background: #cce7ff;
            color: #004085;
        }
        
        .status.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-weight: bold;
            color: #333;
            font-size: 0.9rem;
        }
        
        .detail-value {
            color: #666;
        }
        
        .no-bookings {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 2rem;
        }
        
        .btn-book {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <div class="logo">🔧 Multi Brand Workshop</div>
            <div class="nav-buttons">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <a href="index.php" class="btn btn-secondary">Home</a>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="welcome">
            <h1>Your Dashboard</h1>
            <p>Manage your bookings and schedule new services</p>
            <a href="booking.php" class="btn btn-book">Book New Service</a>
        </div>

        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon">📅</div>
                <h3><?php echo count(array_filter($bookings, function($b) { return $b['status'] === 'pending'; })); ?></h3>
                <p>Pending Bookings</p>
            </div>
            <div class="card">
                <div class="card-icon">✅</div>
                <h3><?php echo count(array_filter($bookings, function($b) { return $b['status'] === 'confirmed'; })); ?></h3>
                <p>Confirmed Bookings</p>
            </div>
            <div class="card">
                <div class="card-icon">🔧</div>
                <h3><?php echo count(array_filter($bookings, function($b) { return $b['status'] === 'completed'; })); ?></h3>
                <p>Completed Services</p>
            </div>
            <div class="card">
                <div class="card-icon">💰</div>
                <h3>$<?php echo number_format(array_sum(array_map(function($b) { return $b['status'] === 'completed' ? $b['total_price'] : 0; }, $bookings)), 2); ?></h3>
                <p>Total Spent</p>
            </div>
        </div>

        <div class="bookings-section">
            <h2>Your Bookings</h2>
            
            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <p>You haven't made any bookings yet.</p>
                    <a href="booking.php" class="btn btn-primary" style="margin-top: 1rem;">Book Your First Service</a>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="booking-id">Booking #<?php echo $booking['id']; ?></div>
                            <div class="status <?php echo $booking['status']; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </div>
                        </div>
                        
                        <div class="booking-details">
                            <div class="detail-item">
                                <span class="detail-label">Service</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['service_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Mechanic</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['mechanic_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Date & Time</span>
                                <span class="detail-value"><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?> at <?php echo date('g:i A', strtotime($booking['booking_time'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Price</span>
                                <span class="detail-value">$<?php echo number_format($booking['total_price'], 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Booked On</span>
                                <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Services & Notes</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['notes']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>