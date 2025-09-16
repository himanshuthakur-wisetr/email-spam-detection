<?php
// logs.php - View system logs
echo "<h2>📊 System Logs</h2>";
echo "<pre>";

$log_file = 'logs/email_analysis.log';

if (file_exists($log_file)) {
    $logs = file($log_file);
    $recent_logs = array_slice($logs, -50); // Show last 50 entries
    
    echo "Recent Email Analysis (Last 50):\n";
    echo "================================\n";
    
    foreach ($recent_logs as $log) {
        echo $log;
    }
    
    echo "\nTotal log entries: " . count($logs) . "\n";
} else {
    echo "No logs found yet.\n";
    echo "System will create logs when emails are processed.\n";
}

echo "</pre>";
?>
