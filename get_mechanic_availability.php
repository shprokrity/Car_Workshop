<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_POST && isset($_POST['date'])) {
    $selected_date = $_POST['date'];
    
    // Check if current user already has a booking on this date
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as user_bookings_on_date
        FROM bookings 
        WHERE user_id = ? 
        AND booking_date = ? 
        AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$_SESSION['user_id'], $selected_date]);
    $user_booking_check = $stmt->fetch();
    
    if ($user_booking_check['user_bookings_on_date'] > 0) {
        // User already has a booking on this date
        echo json_encode([
            'error' => 'user_has_booking',
            'message' => 'You already have a booking on this date. You can only book one appointment per day.'
        ]);
        exit;
    }
    
    // Get all mechanics with their bookings for the specific date
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.name,
            m.specialty,
            m.email,
            m.phone,
            m.status,
            COALESCE(COUNT(b.id), 0) as bookings_on_date
        FROM mechanics m 
        LEFT JOIN bookings b ON m.id = b.mechanic_id 
                            AND b.booking_date = ?
                            AND b.status IN ('pending', 'confirmed')
        WHERE m.status = 'available'
        GROUP BY m.id
        ORDER BY bookings_on_date ASC, m.name ASC
    ");
    
    $stmt->execute([$selected_date]);
    $mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($mechanics);
} else {
    echo json_encode(['error' => 'Date not provided']);
}
?>