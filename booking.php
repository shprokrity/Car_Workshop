<?php
require_once 'config.php';
requireLogin();

$error = '';
$success = '';

// get mechanics
$stmt = $pdo->query("SELECT * FROM mechanics WHERE status = 'available' ORDER BY name ASC");
$all_mechanics = $stmt->fetchAll();

// available repair services
$stmt = $pdo->query("SELECT * FROM repair_services WHERE status = 'available' ORDER BY service_name");
$services = $stmt->fetchAll();

// service icons mapping
$service_icons = [
    'Oil Change' => 'oil change.jpg',
    'Brake Repair' => 'brake-discs.png',
    'Engine Diagnostic' => 'engine.png',
    'Transmission Service' => 'transmission service.webp',
    'Battery Replacement' => 'car-battery.png',
    'Tire Rotation' => 'tire.png'
];

if ($_POST) {
    $mechanic_id = $_POST['mechanic_id'];
    $selected_services = isset($_POST['services']) ? $_POST['services'] : [];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $notes = trim($_POST['notes']);
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $car_license = trim($_POST['car_license']);
    $car_engine = trim($_POST['car_engine']);

    if (empty($name) || empty($address) || empty($phone) || empty($car_license) || empty($car_engine) || empty($selected_services) || empty($booking_date) || empty($booking_time)) {
        $error = 'Please fill in all required fields and select at least one service';
    } elseif (strtotime($booking_date) < strtotime('today')) {
        $error = 'Booking date cannot be in the past';
    } else {
        // Check if user already has a booking on this date
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as user_bookings_on_date
            FROM bookings 
            WHERE user_id = ? 
            AND booking_date = ? 
            AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute([$_SESSION['user_id'], $booking_date]);
        $user_booking_check = $stmt->fetch();
        
        if ($user_booking_check['user_bookings_on_date'] > 0) {
            $error = 'You already have a booking on this date. You can only book one appointment per day.';
        } else {
            // Check if mechanic is available for the selected date
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as bookings_on_date
                FROM bookings 
                WHERE mechanic_id = ? 
                AND booking_date = ? 
                AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$mechanic_id, $booking_date]);
            $date_bookings = $stmt->fetch();
            
            if ($date_bookings['bookings_on_date'] >= 4) {
                $error = 'Selected mechanic is fully booked for this date. Please choose another date or mechanic.';
            } else {
                // Calculate total price and duration
                $service_ids = implode(',', array_map('intval', $selected_services));
                $stmt = $pdo->query("SELECT * FROM repair_services WHERE id IN ($service_ids) AND status = 'available'");
                $selected_service_details = $stmt->fetchAll();
                
                if (count($selected_service_details) !== count($selected_services)) {
                    $error = 'One or more selected services are not available';
                } else {
                    $total_price = 0;
                    $total_duration = 0;
                    $service_names = [];
                    
                    foreach ($selected_service_details as $service) {
                        $total_price += $service['price'];
                        $total_duration += $service['duration_hours'];
                        $service_names[] = $service['service_name'];
                    }
                    
                    // Get mechanic details
                    $stmt = $pdo->prepare("SELECT * FROM mechanics WHERE id = ?");
                    $stmt->execute([$mechanic_id]);
                    $mechanic = $stmt->fetch();
                    
                    // Create booking record
                    $stmt = $pdo->prepare("
                        INSERT INTO bookings (user_id, name, address, phone, car_license, car_engine, mechanic_id, service_id, booking_date, booking_time, total_price, notes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    // Use primary service (first selected service)
                    $primary_service_id = $selected_services[0];
                    $detailed_notes = "Services: " . implode(', ', $service_names);
                    if (!empty($notes)) {
                        $detailed_notes .= " | Customer Notes: " . $notes;
                    }

                    if ($stmt->execute([
                        $_SESSION['user_id'], $name, $address, $phone, $car_license, $car_engine,
                        $mechanic_id, $primary_service_id, $booking_date, $booking_time, $total_price, $detailed_notes
                    ])) {
                        $booking_id = $pdo->lastInsertId();
                        
                        // Send confirmation email
                        $user_email = $_SESSION['email'];
                        $subject = "Booking Confirmation - Workshop Services";
                        $message = "
                            <h2>Booking Confirmation</h2>
                            <p>Dear {$_SESSION['username']},</p>
                            <p>Your booking has been confirmed with the following details:</p>
                            <ul>
                                <li><strong>Booking ID:</strong> #{$booking_id}</li>
                                <li><strong>Services:</strong> " . implode(', ', $service_names) . "</li>
                                <li><strong>Mechanic:</strong> {$mechanic['name']}</li>
                                <li><strong>Date:</strong> " . date('M j, Y', strtotime($booking_date)) . "</li>
                                <li><strong>Time:</strong> " . date('g:i A', strtotime($booking_time)) . "</li>
                                <li><strong>Total Duration:</strong> {$total_duration} hour(s)</li>
                                <li><strong>Total Price:</strong> BDT" . number_format($total_price, 2) . "</li>
                            </ul>
                            <p>We will contact you if any changes are needed.</p>
                            <p>Thank you for choosing our workshop!</p>
                        ";
                        
                        sendEmail($user_email, $subject, $message);
                        
                        $success = 'Booking created successfully! Total: BDT' . number_format($total_price, 2) . ' for ' . count($selected_services) . ' service(s). A confirmation email has been sent.';
                    } else {
                        $error = 'Failed to create booking. Please try again.';
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Service - Multi Brand Workshop</title>
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
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
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
        
        .booking-form {
            background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0.1));
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            color: white;
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 3rem;
            font-size: 2.5rem;
            background: linear-gradient(45deg, #feca57, #ff9ff3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .form-group {
            margin-bottom: 2rem;
        }
        
        label {
            display: block;
            margin-bottom: 1rem;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        select, input[type="date"], input[type="time"], textarea, input[type="text"] {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            font-size: 1rem;
            background: rgba(255,255,255,0.1);
            color: white;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        select option {
            background: #2a2a2a;
            color: white;
        }
        
        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.4);
        }
        
        input::placeholder, textarea::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        textarea {
            height: 120px;
            resize: vertical;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .service-checkbox {
            background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.4s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .service-checkbox::before {
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
        
        .service-checkbox:hover::before {
            opacity: 1;
        }
        
        .service-checkbox:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        
        .service-checkbox.selected {
            border-color: #feca57;
            background: linear-gradient(135deg, rgba(254,202,87,0.2), rgba(255,159,243,0.1));
            box-shadow: 0 8px 25px rgba(254,202,87,0.4);
        }
        
        .service-checkbox.selected::before {
            opacity: 1;
        }
        
        .service-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .service-checkbox input[type="checkbox"] {
            margin-right: 1rem;
            transform: scale(1.5);
            accent-color: #feca57;
            width: auto;
        }
        
        .service-image {
            width: 50px;
            height: 50px;
            margin-right: 1rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
        }
        
        .service-image img {
            width: 30px;
            height: 30px;
            object-fit: contain;
        }
        
        .service-info {
            flex: 1;
            position: relative;
            z-index: 2;
        }
        
        .service-name {
            font-weight: bold;
            color: white;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .service-desc {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        
        .service-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .service-price {
            font-weight: bold;
            color: #feca57;
            font-size: 1.2rem;
        }
        
        .service-duration {
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }
        
        .mechanic-info {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 1rem;
            display: none;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .availability-info {
            background: linear-gradient(135deg, rgba(0,210,211,0.2), rgba(84,160,255,0.1));
            border: 1px solid rgba(0,210,211,0.3);
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 1rem;
            display: none;
        }
        
        .availability {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .available {
            background: linear-gradient(45deg, #00d2d3, #54a0ff);
            color: white;
        }
        
        .busy {
            background: linear-gradient(45deg, #feca57, #ff9f43);
            color: white;
        }
        
        .full {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
        }
        
        .cost-summary {
            background: linear-gradient(135deg, rgba(254,202,87,0.2), rgba(255,159,243,0.1));
            border: 2px solid rgba(254,202,87,0.4);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            display: none;
            backdrop-filter: blur(10px);
        }
        
        .cost-summary h3 {
            color: #feca57;
            margin-bottom: 1.5rem;
            font-size: 1.4rem;
        }
        
        .cost-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        
        .cost-total {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 1.3rem;
            color: #feca57;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid rgba(254,202,87,0.4);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.2rem 2.5rem;
            font-size: 1.2rem;
            width: 100%;
            margin-top: 2rem;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-submit:disabled {
            background: linear-gradient(135deg, #666, #999);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        @media (max-width: 768px) {
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .service-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .service-details {
                flex-direction: column;
                gap: 0.5rem;
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
                <a href="user_panel.php" class="btn btn-secondary">My Dashboard</a>
                <a href="index.php" class="btn btn-secondary">Home</a>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="booking-form">
            <h1 class="form-title">Book Your Service</h1>
            
            <?php if ($error): ?>
                <div class="notification-bar error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="notification-bar success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" id="bookingForm">
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="text" id="phone" name="phone" required>
                </div>

                <div class="form-group">
                    <label for="car_license">Car License Number:</label>
                    <input type="text" id="car_license" name="car_license" required>
                </div>

                <div class="form-group">
                    <label for="car_engine">Car Engine Number:</label>
                    <input type="text" id="car_engine" name="car_engine" required>
                </div>

                <div class="form-group">
                    <label>Select Services:</label>
                    <div class="services-grid">
                        <?php foreach ($services as $service): ?>
                            <div class="service-checkbox" onclick="toggleService(this, <?php echo $service['id']; ?>)">
                                <div class="service-header">
                                    <input type="checkbox" name="services[]" value="<?php echo $service['id']; ?>" 
                                           data-price="<?php echo $service['price']; ?>" 
                                           data-duration="<?php echo $service['duration_hours']; ?>"
                                           data-name="<?php echo htmlspecialchars($service['service_name']); ?>">
                                    <div class="service-image">
                                        <?php if (isset($service_icons[$service['service_name']])): ?>
                                            <img src="<?php echo $service_icons[$service['service_name']]; ?>" alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                                        <?php else: ?>
                                            <img src="maintenance.png" alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="service-info">
                                        <div class="service-name"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                    </div>
                                </div>
                                <div class="service-desc"><?php echo htmlspecialchars($service['description']); ?></div>
                                <div class="service-details">
                                    <span class="service-price">BDT<?php echo number_format($service['price'], 2); ?></span>
                                    <span class="service-duration"><?php echo $service['duration_hours']; ?> hour(s)</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div id="cost-summary" class="cost-summary">
                    <h3>Selected Services Summary</h3>
                    <div id="selected-services"></div>
                    <div class="cost-total">
                        <span>Total Cost:</span>
                        <span id="total-cost">BDT0.00</span>
                    </div>
                    <div class="cost-total">
                        <span>Total Duration:</span>
                        <span id="total-duration">0 hour(s)</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="booking_date">Preferred Date:</label>
                    <input type="date" name="booking_date" id="booking_date" 
                           min="<?php echo date('Y-m-d'); ?>" 
                           value="<?php echo isset($_POST['booking_date']) ? $_POST['booking_date'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="mechanic_id">Select Mechanic:</label>
                    <select name="mechanic_id" id="mechanic_id" required>
                        <option value="">First select a date to see available mechanics...</option>
                    </select>
                    <div id="mechanic-info" class="mechanic-info"></div>
                </div>
                
                <div id="availability-info" class="availability-info">
                    <h4>📅 Availability for Selected Date</h4>
                    <p id="availability-text"></p>
                </div>
                
                <div class="form-group">
                    <label for="booking_time">Preferred Time:</label>
                    <input type="time" name="booking_time" id="booking_time" 
                           min="08:00" max="17:00"
                           value="<?php echo isset($_POST['booking_time']) ? $_POST['booking_time'] : ''; ?>" required>
                    <small style="color: rgba(255,255,255,0.7); margin-top: 0.5rem; display: block;">Workshop hours: 8:00 AM - 5:00 PM</small>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes (Optional):</label>
                    <textarea name="notes" id="notes" placeholder="Any special instructions or details about your vehicle..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-submit" id="submit-btn" disabled>Select Services and Date First</button>
            </form>
        </div>
    </div>

    <script>
        const mechanics = <?php echo json_encode($all_mechanics); ?>;
        let selectedServices = [];
        let totalCost = 0;
        let totalDuration = 0;
        
        function toggleService(element, serviceId) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
            
            updateCostSummary();
            updateSubmitButton();
        }
        
        function updateCostSummary() {
            const checkboxes = document.querySelectorAll('input[name="services[]"]:checked');
            const costSummary = document.getElementById('cost-summary');
            const selectedServicesDiv = document.getElementById('selected-services');
            
            selectedServices = [];
            totalCost = 0;
            totalDuration = 0;
            
            selectedServicesDiv.innerHTML = '';
            
            checkboxes.forEach(checkbox => {
                const price = parseFloat(checkbox.dataset.price);
                const duration = parseInt(checkbox.dataset.duration);
                const name = checkbox.dataset.name;
                
                selectedServices.push({
                    id: checkbox.value,
                    name: name,
                    price: price,
                    duration: duration
                });
                
                totalCost += price;
                totalDuration += duration;
                
                const serviceItem = document.createElement('div');
                serviceItem.className = 'cost-item';
                serviceItem.innerHTML = `
                    <span>${name} (${duration}h)</span>
                    <span>BDT${price.toFixed(2)}</span>
                `;
                selectedServicesDiv.appendChild(serviceItem);
            });
            
            document.getElementById('total-cost').textContent = `BDT${totalCost.toFixed(2)}`;
            document.getElementById('total-duration').textContent = `${totalDuration} hour(s)`;
            
            if (selectedServices.length > 0) {
                costSummary.style.display = 'block';
            } else {
                costSummary.style.display = 'none';
            }
        }
        
        function updateSubmitButton() {
            const submitBtn = document.getElementById('submit-btn');
            const hasServices = selectedServices.length > 0;
            const hasDate = document.getElementById('booking_date').value;
            const hasMechanic = document.getElementById('mechanic_id').value;
            const hasTime = document.getElementById('booking_time').value;
            
            if (hasServices && hasDate && hasMechanic && hasTime) {
                submitBtn.disabled = false;
                submitBtn.textContent = `Book ${selectedServices.length} Service(s) - BDT${totalCost.toFixed(2)}`;
                submitBtn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            } else {
                submitBtn.disabled = true;
                if (!hasServices) {
                    submitBtn.textContent = 'Select Services First';
                } else if (!hasDate) {
                    submitBtn.textContent = 'Select Date First';
                } else if (!hasMechanic) {
                    submitBtn.textContent = 'Select Mechanic First';
                } else {
                    submitBtn.textContent = 'Select Time First';
                }
                submitBtn.style.background = 'linear-gradient(135deg, #666, #999)';
            }
        }
        
        // Load mechanics when date is selected
        document.getElementById('booking_date').addEventListener('change', function() {
            const selectedDate = this.value;
            const mechanicSelect = document.getElementById('mechanic_id');
            const availabilityInfo = document.getElementById('availability-info');
            
            if (selectedDate) {
                // Fetch mechanic availability for selected date
                fetch('get_mechanic_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `date=${selectedDate}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error === 'user_has_booking') {
                        mechanicSelect.innerHTML = '<option value="">You already have a booking on this date</option>';
                        availabilityInfo.style.display = 'block';
                        availabilityInfo.style.background = 'linear-gradient(135deg, rgba(255,107,107,0.2), rgba(238,90,82,0.1))';
                        availabilityInfo.style.borderColor = 'rgba(255,107,107,0.3)';
                        document.getElementById('availability-text').innerHTML = 
                            '<strong>⚠️ Booking Restriction:</strong> You already have a booking on this date. You can only book one appointment per day. Please choose a different date.';
                        return;
                    }
                    
                    mechanicSelect.innerHTML = '<option value="">Choose a mechanic...</option>';
                    
                    data.forEach(mechanic => {
                        const slotsLeft = 4 - mechanic.bookings_on_date;
                        const option = document.createElement('option');
                        option.value = mechanic.id;
                        option.dataset.specialty = mechanic.specialty;
                        option.dataset.slotsLeft = slotsLeft;
                        option.textContent = `${mechanic.name} - ${mechanic.specialty} (${slotsLeft}/4 slots available)`;
                        
                        if (slotsLeft > 0) {
                            mechanicSelect.appendChild(option);
                        }
                    });
                    
                    availabilityInfo.style.display = 'block';
                    availabilityInfo.style.background = 'linear-gradient(135deg, rgba(0,210,211,0.2), rgba(84,160,255,0.1))';
                    availabilityInfo.style.borderColor = 'rgba(0,210,211,0.3)';
                    const availableMechanics = data.filter(m => (4 - m.bookings_on_date) > 0).length;
                    document.getElementById('availability-text').textContent = 
                        `${availableMechanics} mechanic(s) available on ${new Date(selectedDate).toLocaleDateString()}`;
                })
                .catch(error => {
                    console.error('Error:', error);
                    mechanicSelect.innerHTML = '<option value="">Error loading mechanics</option>';
                });
            } else {
                mechanicSelect.innerHTML = '<option value="">First select a date...</option>';
                availabilityInfo.style.display = 'none';
            }
            
            updateSubmitButton();
        });
        
        // Show mechanic info when selected
        document.getElementById('mechanic_id').addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const infoDiv = document.getElementById('mechanic-info');
            
            if (option.value) {
                const specialty = option.dataset.specialty;
                const slotsLeft = option.dataset.slotsLeft;
                
                let availabilityClass = 'available';
                if (slotsLeft <= 1) availabilityClass = 'busy';
                
                infoDiv.innerHTML = `
                    <strong>Mechanic Details:</strong><br>
                    <strong>Specialty:</strong> ${specialty}<br>
                    <strong>Availability:</strong> <span class="availability ${availabilityClass}">${slotsLeft} slots left for this date</span>
                `;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
            
            updateSubmitButton();
        });
        
        // Update submit button when time changes
        document.getElementById('booking_time').addEventListener('change', updateSubmitButton);
        
        // Initialize
        updateSubmitButton();

        // Auto-hide notifications
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