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
        echo json_encode([
            'error' => 'user_has_booking',
            'message' => 'You already have a booking on this date. You can only book one appointment per day.'
        ]);
        exit;
    }
    
    // Get mechanics with their date-specific availability
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.name,
            m.specialty,
            m.email,
            m.phone,
            m.status as mechanic_status,
            COALESCE(mda.current_bookings, 0) as current_bookings,
            COALESCE(mda.max_bookings, m.max_orders) as max_bookings,
            (COALESCE(mda.max_bookings, m.max_orders) - COALESCE(mda.current_bookings, 0)) as available_slots,
            COALESCE(mda.status, 'available') as daily_status,
            mda.availability_date
        FROM mechanics m 
        LEFT JOIN mechanic_daily_availability mda ON m.id = mda.mechanic_id 
            AND mda.availability_date = ?
        WHERE m.status = 'available' 
            AND (COALESCE(mda.status, 'available') != 'full')
            AND (COALESCE(mda.max_bookings, m.max_orders) - COALESCE(mda.current_bookings, 0)) > 0
        ORDER BY COALESCE(mda.current_bookings, 0) ASC, m.name ASC
    ");
    
    $stmt->execute([$selected_date]);
    $mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add additional information for frontend display
    foreach ($mechanics as &$mechanic) {
        $mechanic['bookings_info'] = $mechanic['current_bookings'] . '/' . $mechanic['max_bookings'] . ' slots booked';
        $mechanic['is_available'] = $mechanic['available_slots'] > 0;
        $mechanic['availability_percentage'] = round(($mechanic['current_bookings'] / $mechanic['max_bookings']) * 100, 2);
    }
    
    echo json_encode($mechanics);
} else {
    echo json_encode(['error' => 'Date not provided']);
}
?>