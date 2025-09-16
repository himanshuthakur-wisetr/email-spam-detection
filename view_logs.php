<?php
// view_logs.php - View email action logs
echo "<h2>📊 Email Action Logs</h2>";
echo "<pre>";

$log_file = 'logs/email_actions.log';

if (file_exists($log_file)) {
    $logs = file($log_file);
    $recent_logs = array_slice($logs, -20); // Show last 20 entries
    
    echo "Recent Email Actions (Last 20):\n";
    echo "===============================\n";
    
    foreach ($recent_logs as $log) {
        echo $log;
    }
    
    echo "\nTotal log entries: " . count($logs) . "\n";
} else {
    echo "No logs found. Start the system to see email actions.\n";
}

echo "</pre>";
?>
