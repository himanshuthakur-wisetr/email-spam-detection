<?php
// stop.php - Stop the email spam detection system
echo "<h2>🛑 Stopping Email Spam Detection System</h2>";
echo "<pre>";

$pid_file = 'logs/system.pid';

if (file_exists($pid_file)) {
    $pid = file_get_contents($pid_file);
    
    if (posix_kill($pid, 0)) {
        // System is running, stop it
        posix_kill($pid, SIGTERM);
        unlink($pid_file);
        echo "✅ System stopped successfully (PID: {$pid})\n";
    } else {
        // System was not running
        unlink($pid_file);
        echo "⚠️  System was not running\n";
    }
} else {
    echo "⚠️  No PID file found - system was not running\n";
}

echo "\n🔄 <a href='index.php'>Start System Again</a>\n";
echo "📊 <a href='view_logs.php'>View Logs</a>\n";

echo "</pre>";
?>
