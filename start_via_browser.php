<?php
// start_via_browser.php - Start the system via browser
echo "<h2>🚀 Starting Email Spam Detection System</h2>";
echo "<pre>";

require_once 'imap_daemon.php';

// Check if system is already running
$pid_file = 'logs/system.pid';
if (file_exists($pid_file)) {
    $pid = file_get_contents($pid_file);
    if (posix_kill($pid, 0)) {
        echo "⚠️  System is already running (PID: {$pid})\n";
        echo "To stop: kill {$pid}\n";
    } else {
        unlink($pid_file);
        echo "✅ Cleaned up stale PID file\n";
    }
}

// Start the system
echo "Starting email spam detection system...\n";

// Fork process to run in background
$pid = pcntl_fork();

if ($pid == -1) {
    echo "❌ Error: Could not fork process\n";
    exit(1);
} elseif ($pid) {
    // Parent process
    echo "✅ System started successfully (PID: {$pid})\n";
    echo "The system is now monitoring your emails!\n";
    
    // Save PID
    file_put_contents($pid_file, $pid);
    
} else {
    // Child process - start the daemon
    $daemon = new IMAPDaemon();
    $daemon->start();
}

echo "</pre>";
?>
