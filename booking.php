<?php
require_once 'config.php';
requireLogin();

$error = '';
$success = '';

// Get all mechanics
$stmt = $pdo->query("SELECT * FROM mechanics WHERE status = 'available' ORDER BY name ASC");
$all_mechanics = $stmt->fetchAll();

// Get available repair services
$stmt = $pdo->query("SELECT * FROM repair_services WHERE status = 'available' ORDER BY service_name");
$services = $stmt->fetchAll();

if ($_POST) {
    $mechanic_id = $_POST['mechanic_id'];
    $selected_services = isset($_POST['services']) ? $_POST['services'] : [];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $notes = trim($_POST['notes']);
    
    if (empty($mechanic_id) || empty($selected_services) || empty($booking_date) || empty($booking_time)) {
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
                
                // Create main booking record
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (user_id, mechanic_id, service_id, booking_date, booking_time, total_price, notes, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                
                // Use first service ID as primary service (we'll store multiple services in notes)
                $primary_service_id = $selected_services[0];
                $detailed_notes = "Services: " . implode(', ', $service_names);
                if (!empty($notes)) {
                    $detailed_notes .= " | Customer Notes: " . $notes;
                }
                
                if ($stmt->execute([$_SESSION['user_id'], $mechanic_id, $primary_service_id, $booking_date, $booking_time, $total_price, $detailed_notes])) {
                    $booking_id = $pdo->lastInsertId();
                    
                    // Create additional records for each service (for tracking purposes)
                    $stmt = $pdo->prepare("
                        INSERT INTO booking_services (booking_id, service_id) 
                        VALUES (?, ?)
                    ");
                    
                    // We need to create this table first, let's add it to the notes for now
                    // In a production system, you'd create a separate booking_services table
                    
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
                            <li><strong>Total Price:</strong> $" . number_format($total_price, 2) . "</li>
                        </ul>
                        <p>We will contact you if any changes are needed.</p>
                        <p>Thank you for choosing our workshop!</p>
                    ";
                    
                    sendEmail($user_email, $subject, $message);
                    
                    $success = 'Booking created successfully! Total: $' . number_format($total_price, 2) . ' for ' . count($selected_services) . ' service(s). A confirmation email has been sent.';
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
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .booking-form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: bold;
        }
        
        select, input[type="date"], input[type="time"], textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            height: 100px;
            resize: vertical;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .service-checkbox {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .service-checkbox:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .service-checkbox.selected {
            border-color: #667eea;
            background: #e7f3ff;
        }
        
        .service-checkbox input[type="checkbox"] {
            margin-right: 0.5rem;
            transform: scale(1.2);
        }
        
        .service-info {
            margin-left: 1.5rem;
        }
        
        .service-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .service-desc {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .service-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .service-price {
            font-weight: bold;
            color: #667eea;
            font-size: 1.1rem;
        }
        
        .service-duration {
            color: #888;
            font-size: 0.9rem;
        }
        
        .mechanic-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 0.5rem;
            display: none;
        }
        
        .availability-info {
            background: #e7f3ff;
            border: 1px solid #667eea;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
            display: none;
        }
        
        .availability {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .available {
            background: #d4edda;
            color: #155724;
        }
        
        .busy {
            background: #fff3cd;
            color: #856404;
        }
        
        .full {
            background: #f8d7da;
            color: #721c24;
        }
        
        .cost-summary {
            background: #f8f9fa;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            display: none;
        }
        
        .cost-summary h3 {
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .cost-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .cost-total {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 1.2rem;
            color: #333;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #667eea;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            width: 100%;
            margin-top: 1rem;
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
            <h1 class="form-title">Book Services</h1>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" id="bookingForm">
                <div class="form-group">
                    <label>Select Services:</label>
                    <div class="services-grid">
                        <?php foreach ($services as $service): ?>
                            <div class="service-checkbox" onclick="toggleService(this, <?php echo $service['id']; ?>)">
                                <input type="checkbox" name="services[]" value="<?php echo $service['id']; ?>" 
                                       data-price="<?php echo $service['price']; ?>" 
                                       data-duration="<?php echo $service['duration_hours']; ?>"
                                       data-name="<?php echo htmlspecialchars($service['service_name']); ?>">
                                <div class="service-info">
                                    <div class="service-name"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                    <div class="service-desc"><?php echo htmlspecialchars($service['description']); ?></div>
                                    <div class="service-details">
                                        <span class="service-price">$<?php echo number_format($service['price'], 2); ?></span>
                                        <span class="service-duration"><?php echo $service['duration_hours']; ?> hour(s)</span>
                                    </div>
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
                        <span id="total-cost">$0.00</span>
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
                    <small>Workshop hours: 8:00 AM - 5:00 PM</small>
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
                    <span>$${price.toFixed(2)}</span>
                `;
                selectedServicesDiv.appendChild(serviceItem);
            });
            
            document.getElementById('total-cost').textContent = `$${totalCost.toFixed(2)}`;
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
                submitBtn.textContent = `Book ${selectedServices.length} Service(s) - $${totalCost.toFixed(2)}`;
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
                submitBtn.style.background = '#ccc';
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
                        // User already has a booking on this date
                        mechanicSelect.innerHTML = '<option value="">You already have a booking on this date</option>';
                        availabilityInfo.style.display = 'block';
                        availabilityInfo.style.background = '#f8d7da';
                        availabilityInfo.style.borderColor = '#dc3545';
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
                    availabilityInfo.style.background = '#e7f3ff';
                    availabilityInfo.style.borderColor = '#667eea';
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
    </script>
</body>
</html>