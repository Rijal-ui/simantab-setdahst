<?php
require_once 'auth_check.php';
require_once '../config.php';

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Fetch bookings with search filter
    if ($search_query) {
        $stmt = $pdo->prepare("
            SELECT b.*, g.name as building_name, g.image_url, g.category as building_category
            FROM bookings b 
            JOIN buildings g ON b.building_id = g.id 
            WHERE g.name LIKE ? OR b.booker_name LIKE ? OR b.event_name LIKE ?
            ORDER BY b.created_at DESC
        ");
        $search_param = '%' . $search_query . '%';
        $stmt->execute([$search_param, $search_param, $search_param]);
    } else {
        $stmt = $pdo->query("
            SELECT b.*, g.name as building_name, g.image_url, g.category as building_category
            FROM bookings b 
            JOIN buildings g ON b.building_id = g.id 
            ORDER BY b.created_at DESC
        ");
    }
    $all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group bookings that are part of multi-day booking
    $grouped_bookings = [];
    $processed_ids = [];

    foreach ($all_bookings as $booking) {
        if (in_array($booking['id'], $processed_ids)) {
            continue;
        }
        
        $group = [
            'main_booking' => $booking,
            'dates' => [$booking['booking_date']],
            'booking_ids' => [$booking['id']]
        ];
        $processed_ids[] = $booking['id'];
        
        foreach ($all_bookings as $other_booking) {
            if ($other_booking['id'] == $booking['id']) continue;
            if (in_array($other_booking['id'], $processed_ids)) continue;
            
            if (
                $other_booking['event_name'] == $booking['event_name'] &&
                $other_booking['booker_name'] == $booking['booker_name'] &&
                $other_booking['building_id'] == $booking['building_id']
            ) {
                $group['dates'][] = $other_booking['booking_date'];
                $group['booking_ids'][] = $other_booking['id'];
                $processed_ids[] = $other_booking['id'];
            }
        }
        
        sort($group['dates']);
        $grouped_bookings[] = $group;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $grouped_bookings, 'search_query' => $search_query]);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
