<?php
require_once 'config.php';
requireLogin();
requireAdmin();

$success = '';
$error = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_booking':
                $booking_id = $_POST['booking_id'];
                $new_mechanic_id = $_POST['new_mechanic_id'];
                $new_date = $_POST['new_date'];
                $new_time = $_POST['new_time'];
                $new_status = $_POST['new_status'];
                
                // Get original booking details
                $stmt = $pdo->prepare("
                    SELECT b.*, u.email, u.username, m.name as mechanic_name, rs.service_name
                    FROM bookings b 
                    JOIN users u ON b.user_id = u.id 
                    JOIN mechanics m ON b.mechanic_id = m.id 
                    JOIN repair_services rs ON b.service_id = rs.id 
                    WHERE b.id = ?
                ");
                $stmt->execute([$booking_id]);
                $original_booking = $stmt->fetch();
                
                if ($original_booking) {
                    // Check if mechanic is available if changing mechanic
                    if ($new_mechanic_id != $original_booking['mechanic_id']) {
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as current_orders 
                            FROM bookings 
                            WHERE mechanic_id = ? AND status IN ('pending', 'confirmed')
                        ");
                        $stmt->execute([$new_mechanic_id]);
                        $mechanic_load = $stmt->fetch();
                        
                        if ($mechanic_load['current_orders'] >= 4) {
                            $error = 'Selected mechanic has reached maximum capacity (4 jobs)';
                            break;
                        }
                    }
                    
                    // Update booking
                    $stmt = $pdo->prepare("
                        UPDATE bookings 
                        SET mechanic_id = ?, booking_date = ?, booking_time = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([$new_mechanic_id, $new_date, $new_time, $new_status, $booking_id])) {
                        // Get new mechanic name
                        $stmt = $pdo->prepare("SELECT name FROM mechanics WHERE id = ?");
                        $stmt->execute([$new_mechanic_id]);
                        $new_mechanic = $stmt->fetch();
                        
                        // Send email notification to user
                        $subject = "Booking Update - Workshop Service";
                        $message = "
                            <h2>Booking Update</h2>
                            <p>Dear {$original_booking['username']},</p>
                            <p>Your booking #{$booking_id} has been updated:</p>
                            <h3>New Details:</h3>
                            <ul>
                                <li><strong>Service:</strong> {$original_booking['service_name']}</li>
                                <li><strong>Mechanic:</strong> {$new_mechanic['name']}</li>
                                <li><strong>Date:</strong> " . date('M j, Y', strtotime($new_date)) . "</li>
                                <li><strong>Time:</strong> " . date('g:i A', strtotime($new_time)) . "</li>
                                <li><strong>Status:</strong> " . ucfirst($new_status) . "</li>
                            </ul>
                            <p>If you have any questions, please contact us.</p>
                        ";
                        
                        sendEmail($original_booking['email'], $subject, $message);
                        $success = 'Booking updated successfully and user notified via email.';
                    } else {
                        $error = 'Failed to update booking.';
                    }
                }
                break;
                
            case 'toggle_service':
                $service_id = $_POST['service_id'];
                $new_status = $_POST['new_status'];
                
                $stmt = $pdo->prepare("UPDATE repair_services SET status = ? WHERE id = ?");
                if ($stmt->execute([$new_status, $service_id])) {
                    $success = 'Service status updated successfully.';
                } else {
                    $error = 'Failed to update service status.';
                }
                break;
                
            case 'toggle_mechanic':
                $mechanic_id = $_POST['mechanic_id'];
                $new_status = $_POST['new_status'];
                
                $stmt = $pdo->prepare("UPDATE mechanics SET status = ? WHERE id = ?");
                if ($stmt->execute([$new_status, $mechanic_id])) {
                    $success = 'Mechanic status updated successfully.';
                } else {
                    $error = 'Failed to update mechanic status.';
                }
                break;
        }
    }
}

// Get all bookings with details
$stmt = $pdo->query("
    SELECT b.*, u.username, u.email, m.name as mechanic_name, rs.service_name, rs.price
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    JOIN mechanics m ON b.mechanic_id = m.id 
    JOIN repair_services rs ON b.service_id = rs.id 
    ORDER BY b.created_at DESC
");
$bookings = $stmt->fetchAll();

// Get all mechanics with their current workload
$stmt = $pdo->query("
    SELECT m.*, 
           COALESCE(COUNT(b.id), 0) as current_orders
    FROM mechanics m 
    LEFT JOIN bookings b ON m.id = b.mechanic_id 
                        AND b.status IN ('pending', 'confirmed')
    GROUP BY m.id
    ORDER BY m.name ASC
");
$mechanics = $stmt->fetchAll();

// Get all repair services
$stmt = $pdo->query("SELECT * FROM repair_services ORDER BY service_name");
$services = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Multi Brand Workshop</title>
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
            max-width: 1400px;
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
            font-size: 0.9rem;
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
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .admin-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .admin-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .admin-sections {
            display: grid;
            gap: 2rem;
        }
        
        .section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
            border-radius: 10px 10px 0 0;
        }
        
        .section-header h2 {
            color: #333;
            margin: 0;
        }
        
        .section-content {
            padding: 1.5rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th, .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 0.9rem;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
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
        
        .status.available {
            background: #d4edda;
            color: #155724;
        }
        
        .status.unavailable {
            background: #f8d7da;
            color: #721c24;
        }
        
        .form-inline {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .form-inline select, .form-inline input {
            padding: 0.25rem 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 0.8rem;
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #fcc;
        }
        
        .success {
            background: #efe;
            color: #3c3;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #cfc;
        }
        
        .workload {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .workload.low {
            background: #d4edda;
            color: #155724;
        }
        
        .workload.medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .workload.high {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <div class="logo">🔧 Admin Panel - Multi Brand Workshop</div>
            <div class="nav-buttons">
                <span>Welcome, Admin <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <a href="index.php" class="btn btn-secondary">Home</a>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="admin-header">
            <h1>Workshop Management Dashboard</h1>
            <p>Manage bookings, mechanics, and services</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($bookings, function($b) { return $b['status'] === 'pending'; })); ?></div>
                <div class="stat-label">Pending Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($bookings, function($b) { return $b['status'] === 'confirmed'; })); ?></div>
                <div class="stat-label">Confirmed Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($mechanics, function($m) { return $m['status'] === 'available'; })); ?></div>
                <div class="stat-label">Available Mechanics</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($services, function($s) { return $s['status'] === 'available'; })); ?></div>
                <div class="stat-label">Active Services</div>
            </div>
        </div>

        <div class="admin-sections">
            <!-- Bookings Management -->
            <div class="section">
                <div class="section-header">
                    <h2>📅 Booking Management</h2>
                </div>
                <div class="section-content">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Mechanic</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['username']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['mechanic_name']); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($booking['booking_date'] . ' ' . $booking['booking_time'])); ?></td>
                                    <td><span class="status <?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                    <td>$<?php echo number_format($booking['price'], 2); ?></td>
                                    <td>
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="action" value="update_booking">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            
                                            <select name="new_mechanic_id" required>
                                                <?php foreach ($mechanics as $mechanic): ?>
                                                    <option value="<?php echo $mechanic['id']; ?>" 
                                                            <?php echo ($mechanic['id'] == $booking['mechanic_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($mechanic['name']); ?>
                                                        (<?php echo $mechanic['current_orders']; ?>/4)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <input type="date" name="new_date" value="<?php echo $booking['booking_date']; ?>" required>
                                            <input type="time" name="new_time" value="<?php echo $booking['booking_time']; ?>" required>
                                            
                                            <select name="new_status" required>
                                                <option value="pending" <?php echo ($booking['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo ($booking['status'] === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="completed" <?php echo ($booking['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo ($booking['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            
                                            <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mechanics Management -->
            <div class="section">
                <div class="section-header">
                    <h2>👨‍🔧 Mechanics Management</h2>
                </div>
                <div class="section-content">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Specialty</th>
                                <th>Contact</th>
                                <th>Workload</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mechanics as $mechanic): ?>
                                <?php
                                    $workload_class = 'low';
                                    if ($mechanic['current_orders'] >= 3) $workload_class = 'high';
                                    elseif ($mechanic['current_orders'] >= 2) $workload_class = 'medium';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($mechanic['name']); ?></td>
                                    <td><?php echo htmlspecialchars($mechanic['specialty']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($mechanic['email']); ?><br>
                                        <small><?php echo htmlspecialchars($mechanic['phone']); ?></small>
                                    </td>
                                    <td>
                                        <span class="workload <?php echo $workload_class; ?>">
                                            <?php echo $mechanic['current_orders']; ?>/<?php echo $mechanic['max_orders']; ?> jobs
                                        </span>
                                    </td>
                                    <td><span class="status <?php echo $mechanic['status']; ?>"><?php echo ucfirst($mechanic['status']); ?></span></td>
                                    <td>
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="action" value="toggle_mechanic">
                                            <input type="hidden" name="mechanic_id" value="<?php echo $mechanic['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo ($mechanic['status'] === 'available') ? 'unavailable' : 'available'; ?>">
                                            <button type="submit" class="btn <?php echo ($mechanic['status'] === 'available') ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                <?php echo ($mechanic['status'] === 'available') ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Services Management -->
            <div class="section">
                <div class="section-header">
                    <h2>🔧 Services Management</h2>
                </div>
                <div class="section-content">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Service Name</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                    <td><?php echo htmlspecialchars($service['description']); ?></td>
                                    <td>$<?php echo number_format($service['price'], 2); ?></td>
                                    <td><?php echo $service['duration_hours']; ?> hour(s)</td>
                                    <td><span class="status <?php echo $service['status']; ?>"><?php echo ucfirst($service['status']); ?></span></td>
                                    <td>
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="action" value="toggle_service">
                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo ($service['status'] === 'available') ? 'unavailable' : 'available'; ?>">
                                            <button type="submit" class="btn <?php echo ($service['status'] === 'available') ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                <?php echo ($service['status'] === 'available') ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>