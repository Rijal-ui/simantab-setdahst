<?php
require_once 'config.php';

header('Content-Type: application/json');

$building_id = $_GET['building_id'] ?? null;

$query = "
    SELECT 
        b.id,
        b.event_name as title,
        CONCAT(b.booking_date, 'T', b.start_time) as start,
        CONCAT(b.booking_date, 'T', b.end_time) as end,
        g.name as building_name
    FROM bookings b
    JOIN buildings g ON b.building_id = g.id
    WHERE b.status = 'approved'
";

$params = [];

if ($building_id) {
    $query .= " AND b.building_id = ?";
    $params[] = $building_id;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add color/extended props if needed
    foreach ($events as &$event) {
        $event['backgroundColor'] = '#f43f5e'; // Rose color
        $event['borderColor'] = '#e11d48';
        $event['textColor'] = '#ffffff';
        // Add building name to title if showing all buildings
        if (!$building_id) {
            $event['title'] = $event['title'] . ' (' . $event['building_name'] . ')';
        }
    }

    // Add recurring event for Zumba Isteri Bupati on Thursdays for "Gedung Balai Rakyat (Siang Hari)"
    // Use a more robust check for the building name
    $stmtB = $pdo->prepare("SELECT id, name FROM buildings WHERE name LIKE ?");
    $stmtB->execute(['%Balai Rakyat%Siang Hari%']);
    $zumbaBuilding = $stmtB->fetch(PDO::FETCH_ASSOC);

    if ($zumbaBuilding && (!$building_id || $building_id == $zumbaBuilding['id'])) {
        // 1. Background shading for the cell
        $events[] = [
            'id' => 'zumba_bg',
            'daysOfWeek' => [4],
            'display' => 'background',
            'backgroundColor' => '#fff7ed', // Very light orange
        ];

        // 2. The event label
        $events[] = [
            'id' => 'zumba_recurring',
            'title' => '🔒 RUTIN: Senam Gratis Bersama Ketua TP PKK Kab. HST',
            'daysOfWeek' => [4], // 4 = Thursday
            'startTime' => '07:00:00',
            'endTime' => '17:00:00',
            'backgroundColor' => '#f97316', // Orange color
            'borderColor' => '#ea580c',
            'textColor' => '#ffffff',
            'allDay' => false,
            'extendedProps' => [
                'building_name' => $zumbaBuilding['name'],
                'description' => 'Gedung tidak dapat dibooking setiap hari Kamis karena digunakan untuk acara rutin.'
            ]
        ];
    }
    
    echo json_encode($events);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
