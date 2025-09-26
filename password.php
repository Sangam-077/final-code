<?php
include 'db_connect.php';
$staff = [
    ['email' => 'staff@example.com', 'user_id' => 'staff_001'],
    ['email' => 'sangam@ravenhill.com', 'user_id' => 'user_66f1a8e0c7f9d'],
    ['email' => 'vishal@ravenhill.com', 'user_id' => 'user_66f1a8e0c7fa0'],
    ['email' => 'aakriti@ravenhill.com', 'user_id' => 'user_66f1a8e0c7fa1'],
    ['email' => 'bishal@ravenhill.com', 'user_id' => 'user_66f1a8e0c7fa2'],
    ['email' => 'binay@ravenhill.com', 'user_id' => 'user_66f1a8e0c7fa3']
];

foreach ($staff as $s) {
    $hashed_password = password_hash('Staff123!', PASSWORD_DEFAULT); // New hash each time
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND user_id = ?");
    if ($stmt === false) {
        error_log("Prepare failed for " . $s['email'] . ": " . $conn->error);
    } else {
        $stmt->bind_param("sss", $hashed_password, $s['email'], $s['user_id']);
        if (!$stmt->execute()) {
            error_log("Update failed for " . $s['email'] . ": " . $stmt->error);
        }
        $stmt->close();
    }
}
echo "Passwords updated with new hashes. Check error log for issues.";
?>