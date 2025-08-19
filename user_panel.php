<?php
require_once 'config.php';
requireLogin();

$error = '';
$success = '';

// Handle booking cancellation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $booking_id = $_POST['booking_id'];
    
    // Verify booking belongs to user and is cancellable
    $stmt = $pdo->prepare("
        SELECT b.*, m.name as mechanic_name, rs.service_name 
        FROM bookings b 
        JOIN mechanics m ON b.mechanic_id = m.id 
        JOIN repair_services rs ON b.service_id = rs.id 
        WHERE b.id = ? AND b.user_id = ? AND b.status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    
    if ($booking) {
        // Check if booking is at least 24 hours away
        $booking_datetime = strtotime($booking['booking_date'] . ' ' . $booking['booking_time']);
        $hours_until_booking = ($booking_datetime - time()) / 3600;
        
        if ($hours_until_booking < 24) {
            $error = 'Bookings can only be cancelled at least 24 hours in advance.';
        } else {
            // Cancel the booking
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$booking_id])) {
                // Send cancellation email
                $subject = "Booking Cancelled - Workshop Service";
                $message = "
                    <h2>Booking Cancellation</h2>
                    <p>Dear {$_SESSION['username']},</p>
                    <p>Your booking #{$booking_id} has been successfully cancelled.</p>
                    <h3>Cancelled Booking Details:</h3>
                    <ul>
                        <li><strong>Service:</strong> {$booking['service_name']}</li>
                        <li><strong>Mechanic:</strong> {$booking['mechanic_name']}</li>
                        <li><strong>Date:</strong> " . date('M j, Y', strtotime($booking['booking_date'])) . "</li>
                        <li><strong>Time:</strong> " . date('g:i A', strtotime($booking['booking_time'])) . "</li>
                    </ul>
                    <p>If you need to reschedule, please book a new appointment.</p>
                    <p>Thank you for using our workshop!</p>
                ";
                
                sendEmail($_SESSION['email'], $subject, $message);
                $success = 'Booking #' . $booking_id . ' has been successfully cancelled. A confirmation email has been sent.';
            } else {
                $error = 'Failed to cancel booking. Please try again.';
            }
        }
    } else {
        $error = 'Booking not found or cannot be cancelled.';
    }
}

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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #667eea 50%, #764ba2 75%, #8b5a3c 100%);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, rgba(30,30,30,0.95) 0%, rgba(70,50,40,0.95) 50%, rgba(20,20,20,0.95) 100%);
            backdrop-filter: blur(10px);
            color: white;
            padding: 1rem 0;
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
        }
        
        .nav-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
            color: rgba(255,255,255,0.9);
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
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid #667eea;
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .notification-bar {
            background: linear-gradient(90deg, #48dbfb, #0abde3);
            color: white;
            padding: 1rem;
            text-align: center;
            margin-bottom: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .notification-bar.success {
            background: linear-gradient(90deg, #00d2d3, #54a0ff);
        }
        
        .notification-bar.error {
            background: linear-gradient(90deg, #ff6b6b, #ee5a52);
        }
        
        .welcome {
            background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0.1));
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            margin-bottom: 2rem;
            text-align: center;
            color: white;
        }
        
        .welcome h1 {
            margin-bottom: 1rem;
            font-size: 2.5rem;
            background: linear-gradient(45deg, #feca57, #ff9ff3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0.1));
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            color: white;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
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
            box-shadow: 0 8px 20px rgba(102,126,234,0.4);
        }
        
        .card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #feca57;
        }
        
        .bookings-section {
            background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0.1));
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            color: white;
        }
        
        .bookings-section h2 {
            margin-bottom: 2rem;
            text-align: center;
            font-size: 2.5rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .booking-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .booking-card::before {
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
        
        .booking-card:hover::before {
            opacity: 1;
        }
        
        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 2;
        }
        
        .booking-id {
            font-weight: bold;
            color: #feca57;
            font-size: 1.2rem;
        }
        
        .status {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status.pending {
            background: linear-gradient(45deg, #feca57, #ff9f43);
            color: white;
        }
        
        .status.confirmed {
            background: linear-gradient(45deg, #00d2d3, #54a0ff);
            color: white;
        }
        
        .status.completed {
            background: linear-gradient(45deg, #5f27cd, #00d2d3);
            color: white;
        }
        
        .status.cancelled {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
        }
        
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            position: relative;
            z-index: 2;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-weight: bold;
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            color: white;
            font-size: 1rem;
        }
        
        .booking-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .cancel-form {
            display: inline-block;
        }
        
        .btn-book {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            margin-top: 1rem;
        }
        
        .no-bookings {
            text-align: center;
            color: rgba(255,255,255,0.8);
            font-style: italic;
            padding: 3rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            margin: 15% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        
        .modal h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .modal p {
            color: #666;
            margin-bottom: 1.5rem;
        }
        
        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #333;
        }
        
        @media (max-width: 768px) {
            .booking-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .booking-actions {
                flex-direction: column;
            }
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
        <?php if ($error): ?>
            <div class="notification-bar error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="notification-bar success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

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
                    <?php
                        // Check if booking can be cancelled
                        $booking_datetime = strtotime($booking['booking_date'] . ' ' . $booking['booking_time']);
                        $hours_until_booking = ($booking_datetime - time()) / 3600;
                        $can_cancel = ($booking['status'] === 'pending' || $booking['status'] === 'confirmed') && $hours_until_booking >= 24;
                    ?>
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
                        
                        <?php if ($can_cancel): ?>
                            <div class="booking-actions">
                                <button onclick="openCancelModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['service_name']); ?>', '<?php echo date('M j, Y g:i A', strtotime($booking['booking_date'] . ' ' . $booking['booking_time'])); ?>')" class="btn btn-danger">Cancel Booking</button>
                                <?php if ($hours_until_booking < 48): ?>
                                    <small style="color: rgba(255,255,255,0.7); margin-top: 0.5rem;">
                                        ⚠️ <?php echo round($hours_until_booking); ?> hours until appointment
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php elseif (($booking['status'] === 'pending' || $booking['status'] === 'confirmed') && $hours_until_booking < 24): ?>
                            <div class="booking-actions">
                                <small style="color: rgba(255,255,255,0.7);">
                                    ⚠️ Cannot cancel - less than 24 hours until appointment
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancel Booking Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCancelModal()">&times;</span>
            <h3>Cancel Booking</h3>
            <p id="cancelMessage"></p>
            <form id="cancelForm" method="POST">
                <input type="hidden" name="action" value="cancel_booking">
                <input type="hidden" name="booking_id" id="cancelBookingId">
                <div class="modal-buttons">
                    <button type="button" onclick="closeCancelModal()" class="btn btn-secondary">Keep Booking</button>
                    <button type="submit" class="btn btn-danger">Yes, Cancel Booking</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCancelModal(bookingId, serviceName, dateTime) {
            document.getElementById('cancelBookingId').value = bookingId;
            document.getElementById('cancelMessage').textContent = 
                `Are you sure you want to cancel your ${serviceName} appointment scheduled for ${dateTime}? This action cannot be undone.`;
            document.getElementById('cancelModal').style.display = 'block';
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target == modal) {
                closeCancelModal();
            }
        }

        // Auto-hide notifications after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notifications = document.querySelectorAll('.notification-bar');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        notification.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>