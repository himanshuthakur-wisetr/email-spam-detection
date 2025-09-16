<?php
// control.php - Control panel for email daemon
echo "<h2>🎛️ Email Daemon Control Panel</h2>";
echo "<pre>";

require_once 'email_daemon.php';

$daemon = new EmailDaemon();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'start':
            echo "Starting email daemon...\n";
            $daemon->start();
            break;
            
        case 'stop':
            echo "Stopping email daemon...\n";
            $daemon->stop();
            break;
            
        case 'status':
            if ($daemon->isRunning()) {
                echo "✅ Daemon is running\n";
            } else {
                echo "❌ Daemon is not running\n";
            }
            break;
    }
} else {
    // Show control panel
    echo "Email Daemon Control Panel\n";
    echo "==========================\n\n";
    
    if ($daemon->isRunning()) {
        echo "Status: ✅ RUNNING\n";
        echo "Monitoring: Gmail every 30 seconds\n";
        echo "Mode: 24/7 Automatic\n\n";
        
        echo "Actions:\n";
        echo "• <a href='logs.php'>View Logs</a>\n";
        echo "• <form method='post' style='display:inline;'><input type='hidden' name='action' value='stop'><input type='submit' value='Stop Daemon'></form>\n";
        echo "• <form method='post' style='display:inline;'><input type='hidden' name='action' value='status'><input type='submit' value='Check Status'></form>\n";
    } else {
        echo "Status: ❌ STOPPED\n";
        echo "Monitoring: Not active\n";
        echo "Mode: Manual only\n\n";
        
        echo "Actions:\n";
        echo "• <form method='post' style='display:inline;'><input type='hidden' name='action' value='start'><input type='submit' value='Start Daemon'></form>\n";
        echo "• <a href='logs.php'>View Logs</a>\n";
        echo "• <form method='post' style='display:inline;'><input type='hidden' name='action' value='status'><input type='submit' value='Check Status'></form>\n";
    }
    
    echo "\nSystem Info:\n";
    echo "• Gmail: avirsingh52@gmail.com\n";
    echo "• Check Interval: 30 seconds\n";
    echo "• AI Training: Every 10 emails\n";
    echo "• Logs: logs/email_analysis.log\n";
    echo "• PID File: logs/daemon.pid\n\n";
    
    echo "How it works:\n";
    echo "1. Daemon connects to Gmail IMAP\n";
    echo "2. Checks for new emails every 30 seconds\n";
    echo "3. AI analyzes each email for spam\n";
    echo "4. Logs spam percentage and analysis\n";
    echo "5. Trains AI model with each email\n";
    echo "6. Runs 24/7 in background\n";
}

echo "</pre>";
?>
