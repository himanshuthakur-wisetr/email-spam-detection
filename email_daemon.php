<?php
/**
 * Email Daemon - Fully Automatic Gmail Monitoring
 * Monitors Gmail via IMAP every 30 seconds
 * Runs as background daemon 24/7
 */

require_once 'email_processor.php';
require_once 'ml_model.php';

class EmailDaemon {
    
    private $processor;
    private $model;
    private $log_file;
    private $training_file;
    private $pid_file;
    private $running = true;
    
    // Gmail configuration
    private $gmail_config = [
        'email' => 'avirsingh52@gmail.com',
        'password' => 'otty rprh lcct cajx',
        'imap_host' => 'imap.gmail.com',
        'imap_port' => 993,
        'encryption' => 'ssl'
    ];
    
    public function __construct() {
        $this->processor = new EmailProcessor();
        $this->model = new SpamDetectionModel();
        $this->log_file = __DIR__ . '/logs/email_analysis.log';
        $this->training_file = __DIR__ . '/logs/training_data.json';
        $this->pid_file = __DIR__ . '/logs/daemon.pid';
        
        // Create logs directory
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        
        // Load or create model
        $this->loadModel();
    }
    
    /**
     * Start the daemon
     */
    public function start() {
        // Fork process to run in background
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            die("Error: Could not fork process\n");
        } elseif ($pid) {
            // Parent process - save PID and exit
            file_put_contents($this->pid_file, $pid);
            echo "✅ Email daemon started (PID: {$pid})\n";
            echo "📧 Monitoring Gmail every 30 seconds...\n";
            echo "🔄 System running 24/7 automatically\n";
            exit(0);
        } else {
            // Child process - start monitoring
            $this->runDaemon();
        }
    }
    
    /**
     * Run the daemon loop
     */
    private function runDaemon() {
        echo "🚀 Email daemon started - monitoring Gmail...\n";
        
        while ($this->running) {
            try {
                $this->checkForNewEmails();
                sleep(30); // Check every 30 seconds
            } catch (Exception $e) {
                $this->logError("Daemon error: " . $e->getMessage());
                sleep(60); // Wait longer on error
            }
        }
    }
    
    /**
     * Check for new emails in Gmail
     */
    private function checkForNewEmails() {
        try {
            // Connect to Gmail IMAP
            $connection_string = "{{$this->gmail_config['imap_host']}:{$this->gmail_config['imap_port']}/imap/ssl}INBOX";
            $imap = imap_open($connection_string, $this->gmail_config['email'], $this->gmail_config['password']);
            
            if (!$imap) {
                throw new Exception("Could not connect to Gmail IMAP");
            }
            
            // Get unread emails
            $emails = imap_search($imap, 'UNSEEN');
            
            if ($emails) {
                echo "📧 Found " . count($emails) . " new emails\n";
                
                foreach ($emails as $email_id) {
                    $this->processEmail($imap, $email_id);
                }
            }
            
            imap_close($imap);
            
        } catch (Exception $e) {
            $this->logError("IMAP error: " . $e->getMessage());
        }
    }
    
    /**
     * Process individual email
     */
    private function processEmail($imap, $email_id) {
        try {
            // Get email headers
            $headers = imap_headerinfo($imap, $email_id);
            $subject = $headers->subject ?? '';
            $from = $headers->from[0]->mailbox . '@' . $headers->from[0]->host;
            $date = date('Y-m-d H:i:s', $headers->udate);
            
            // Get email body
            $body = imap_fetchbody($imap, $email_id, 1);
            if (empty($body)) {
                $body = imap_fetchbody($imap, $email_id, 1.1);
            }
            
            // Create email data array
            $email_data = [
                'subject' => $subject,
                'body' => $body,
                'from' => $from,
                'to' => $this->gmail_config['email'],
                'date' => $date
            ];
            
            // Process email for spam detection
            $processed = $this->processor->processEmail($email_data);
            $features = $processed['features'];
            
            // Get spam rate from AI model
            $spam_rate = $this->model->getSpamRate($features);
            
            // Determine spam level
            $spam_level = $this->getSpamLevel($spam_rate);
            
            // Log the analysis
            $this->logAnalysis($subject, $from, $spam_rate, $spam_level, $features);
            
            // Train the model with this email
            $this->trainModelWithEmail($email_data, $spam_rate);
            
            // Mark email as read (optional)
            imap_setflag_full($imap, $email_id, "\\Seen");
            
        } catch (Exception $e) {
            $this->logError("Error processing email {$email_id}: " . $e->getMessage());
        }
    }
    
    /**
     * Get spam level based on percentage
     */
    private function getSpamLevel($spam_rate) {
        if ($spam_rate >= 80) return 'HIGH_SPAM';
        if ($spam_rate >= 60) return 'MEDIUM_SPAM';
        if ($spam_rate >= 40) return 'LOW_SPAM';
        if ($spam_rate >= 20) return 'SUSPICIOUS';
        return 'LEGITIMATE';
    }
    
    /**
     * Log email analysis
     */
    private function logAnalysis($subject, $from, $spam_rate, $spam_level, $features) {
        $timestamp = date('Y-m-d H:i:s');
        
        // Create log entry
        $log_entry = [
            'timestamp' => $timestamp,
            'subject' => $subject,
            'from' => $from,
            'spam_rate' => round($spam_rate, 2),
            'spam_level' => $spam_level,
            'features' => [
                'spam_keywords' => $features['spam_keyword_count'],
                'caps_ratio' => round($features['caps_ratio'], 2),
                'exclamation_count' => $features['exclamation_count'],
                'url_count' => $features['url_count'],
                'subject_length' => $features['subject_length'],
                'body_length' => $features['body_length']
            ]
        ];
        
        // Save to log file
        $this->saveToLog($log_entry);
        
        // Output to console
        echo "[{$timestamp}] 📧 {$spam_level} | {$spam_rate}% | {$subject} | From: {$from}\n";
    }
    
    /**
     * Save to text log file
     */
    private function saveToLog($log_entry) {
        $log_line = "[{$log_entry['timestamp']}] {$log_entry['spam_level']} | {$log_entry['spam_rate']}% | {$log_entry['subject']} | From: {$log_entry['from']}\n";
        file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Train model with new email
     */
    private function trainModelWithEmail($email_data, $spam_rate) {
        try {
            // Determine if this is spam based on rate
            $is_spam = $spam_rate > 50;
            
            // Create training sample
            $training_sample = [
                'email_data' => $email_data,
                'features' => $this->processor->processEmail($email_data)['features'],
                'is_spam' => $is_spam,
                'spam_rate' => $spam_rate,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Save training data
            $this->saveTrainingData($training_sample);
            
            // Retrain model periodically (every 10 new emails)
            $this->checkAndRetrain();
            
        } catch (Exception $e) {
            $this->logError("Error training model: " . $e->getMessage());
        }
    }
    
    /**
     * Save training data
     */
    private function saveTrainingData($training_sample) {
        $training_data = [];
        
        if (file_exists($this->training_file)) {
            $training_data = json_decode(file_get_contents($this->training_file), true) ?: [];
        }
        
        $training_data[] = $training_sample;
        
        // Keep only last 1000 training samples
        if (count($training_data) > 1000) {
            $training_data = array_slice($training_data, -1000);
        }
        
        file_put_contents($this->training_file, json_encode($training_data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Check if model needs retraining
     */
    private function checkAndRetrain() {
        if (!file_exists($this->training_file)) {
            return;
        }
        
        $training_data = json_decode(file_get_contents($this->training_file), true);
        $count = count($training_data);
        
        // Retrain every 10 new emails
        if ($count % 10 == 0) {
            $this->retrainModel($training_data);
        }
    }
    
    /**
     * Retrain the model
     */
    private function retrainModel($training_data) {
        try {
            echo "🔄 Retraining AI model with " . count($training_data) . " samples...\n";
            
            // Convert to model format
            $model_training_data = [];
            foreach ($training_data as $sample) {
                $model_training_data[] = [
                    'features' => $sample['features'],
                    'is_spam' => $sample['is_spam']
                ];
            }
            
            // Train the model
            $this->model->train($model_training_data, 0.01, 100);
            
            // Save updated model
            $this->model->saveModel(__DIR__ . '/trained_model.json');
            
            echo "✅ Model retrained successfully!\n";
            
        } catch (Exception $e) {
            $this->logError("Error retraining model: " . $e->getMessage());
        }
    }
    
    /**
     * Load or create model
     */
    private function loadModel() {
        $model_path = __DIR__ . '/trained_model.json';
        
        if (file_exists($model_path)) {
            try {
                $this->model->loadModel($model_path);
                echo "✅ AI model loaded successfully\n";
            } catch (Exception $e) {
                echo "⚠️  Error loading model, creating new one: " . $e->getMessage() . "\n";
                $this->createNewModel();
            }
        } else {
            echo "🆕 Creating new AI model...\n";
            $this->createNewModel();
        }
    }
    
    /**
     * Create new model
     */
    private function createNewModel() {
        // Generate initial training data
        $training_data = $this->model->generateTrainingData();
        $this->model->train($training_data);
        $this->model->saveModel(__DIR__ . '/trained_model.json');
        echo "✅ New AI model created and trained\n";
    }
    
    /**
     * Log error
     */
    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $error_log = "[{$timestamp}] ERROR: {$message}\n";
        file_put_contents(__DIR__ . '/logs/errors.log', $error_log, FILE_APPEND | LOCK_EX);
        echo "❌ {$message}\n";
    }
    
    /**
     * Stop the daemon
     */
    public function stop() {
        if (file_exists($this->pid_file)) {
            $pid = file_get_contents($this->pid_file);
            if (posix_kill($pid, SIGTERM)) {
                unlink($this->pid_file);
                echo "✅ Email daemon stopped\n";
            } else {
                echo "❌ Could not stop daemon (PID: {$pid})\n";
            }
        } else {
            echo "❌ No daemon running\n";
        }
    }
    
    /**
     * Check if daemon is running
     */
    public function isRunning() {
        if (file_exists($this->pid_file)) {
            $pid = file_get_contents($this->pid_file);
            return posix_kill($pid, 0);
        }
        return false;
    }
}

// Handle command line arguments
if (php_sapi_name() === 'cli') {
    $daemon = new EmailDaemon();
    
    if (isset($argv[1])) {
        switch ($argv[1]) {
            case 'start':
                $daemon->start();
                break;
            case 'stop':
                $daemon->stop();
                break;
            case 'status':
                if ($daemon->isRunning()) {
                    echo "✅ Daemon is running\n";
                } else {
                    echo "❌ Daemon is not running\n";
                }
                break;
            default:
                echo "Usage: php email_daemon.php [start|stop|status]\n";
        }
    } else {
        echo "Usage: php email_daemon.php [start|stop|status]\n";
    }
}
?>
