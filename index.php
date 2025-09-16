<?php
// index.php - Main entry point for the email spam detection system
echo "<h2>🚀 Email Spam Detection System</h2>";
echo "<pre>";

require_once 'imap_daemon.php';

// Check if system is already running
$pid_file = 'logs/system.pid';
if (file_exists($pid_file)) {
    $pid = file_get_contents($pid_file);
    if (posix_kill($pid, 0)) {
        echo "✅ System is already running (PID: {$pid})\n";
        echo "📊 <a href='view_logs.php'>View Logs</a>\n";
        echo "🛑 <a href='stop.php'>Stop System</a>\n";
    } else {
        unlink($pid_file);
        echo "⚠️  System was not running, starting now...\n";
        startSystem();
    }
} else {
    echo "🚀 Starting Email Spam Detection System...\n";
    startSystem();
}

function startSystem() {
    // Fork process to run in background
    $pid = pcntl_fork();
    
    if ($pid == -1) {
        echo "❌ Error: Could not fork process\n";
        exit(1);
    } elseif ($pid) {
        // Parent process
        echo "✅ System started successfully (PID: {$pid})\n";
        echo "📧 Monitoring emails for spam...\n";
        echo "📊 <a href='view_logs.php'>View Logs</a>\n";
        echo "🛑 <a href='stop.php'>Stop System</a>\n";
        
        // Save PID
        file_put_contents('logs/system.pid', $pid);
        
    } else {
        // Child process - start the daemon
        $daemon = new IMAPDaemon();
        $daemon->start();
    }
}

echo "</pre>";
?>
