<?php
/**
 * Real-Time Email Spam Detection Processor
 * Processes emails instantly via webhooks and IMAP monitoring
 */

require_once 'email_processor.php';
require_once 'ml_model.php';

class RealtimeEmailProcessor {
    
    private $processor;
    private $model;
    private $config;
    private $log_file;
    
    public function __construct() {
        $this->processor = new EmailProcessor();
        $this->model = new SpamDetectionModel();
        $this->config = $this->loadConfig();
        $this->log_file = __DIR__ . '/logs/email_actions.log';
        
        // Create logs directory
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        
        // Load trained model
        $this->loadModel();
    }
    
    /**
     * Process incoming email via webhook
     */
    public function processWebhookEmail($email_data, $source = 'webhook') {
        $this->log("Processing email from {$source}: " . ($email_data['subject'] ?? 'No subject'));
        
        try {
            // Process email for spam detection
            $processed = $this->processor->processEmail($email_data);
            $spam_rate = $this->model->getSpamRate($processed['features']);
            $is_spam = $spam_rate > $this->config['spam_threshold'];
            
            // Log detection
            $this->logDetection($email_data, $spam_rate, $is_spam, $source);
            
            // Take action based on spam detection
            $action = $this->determineAction($spam_rate);
            $result = $this->executeAction($action, $email_data, $spam_rate);
            
            return [
                'success' => true,
                'spam_rate' => $spam_rate,
                'is_spam' => $is_spam,
                'action' => $action,
                'result' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->log("Error processing email: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Process email via IMAP monitoring
     */
    public function processIMAPEmail($email_data, $connection, $message_number) {
        $this->log("Processing IMAP email #{$message_number}: " . ($email_data['subject'] ?? 'No subject'));
        
        try {
            // Process email for spam detection
            $processed = $this->processor->processEmail($email_data);
            $spam_rate = $this->model->getSpamRate($processed['features']);
            $is_spam = $spam_rate > $this->config['spam_threshold'];
            
            // Log detection
            $this->logDetection($email_data, $spam_rate, $is_spam, 'imap');
            
            // Take action based on spam detection
            $action = $this->determineAction($spam_rate);
            $result = $this->executeIMAPAction($action, $connection, $message_number, $email_data, $spam_rate);
            
            return [
                'success' => true,
                'spam_rate' => $spam_rate,
                'is_spam' => $is_spam,
                'action' => $action,
                'result' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->log("Error processing IMAP email: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Determine action based on spam rate
     */
    private function determineAction($spam_rate) {
        if ($spam_rate >= 80) return 'move_to_spam';
        if ($spam_rate >= 60) return 'flag_as_spam';
        if ($spam_rate >= 40) return 'mark_suspicious';
        return 'allow';
    }
    
    /**
     * Execute action for webhook emails
     */
    private function executeAction($action, $email_data, $spam_rate) {
        switch ($action) {
            case 'move_to_spam':
                return $this->moveToSpam($email_data, $spam_rate);
            case 'flag_as_spam':
                return $this->flagAsSpam($email_data, $spam_rate);
            case 'mark_suspicious':
                return $this->markSuspicious($email_data, $spam_rate);
            case 'allow':
            default:
                return $this->allowEmail($email_data);
        }
    }
    
    /**
     * Execute action for IMAP emails
     */
    private function executeIMAPAction($action, $connection, $message_number, $email_data, $spam_rate) {
        switch ($action) {
            case 'move_to_spam':
                return $this->moveIMAPToSpam($connection, $message_number, $email_data, $spam_rate);
            case 'flag_as_spam':
                return $this->flagIMAPAsSpam($connection, $message_number, $email_data, $spam_rate);
            case 'mark_suspicious':
                return $this->markIMAPSuspicious($connection, $message_number, $email_data, $spam_rate);
            case 'allow':
            default:
                return $this->allowIMAPEmail($connection, $message_number, $email_data);
        }
    }
    
    /**
     * Move email to spam folder (webhook)
     */
    private function moveToSpam($email_data, $spam_rate) {
        $this->log("MOVING TO SPAM: " . $email_data['subject'] . " (Spam rate: {$spam_rate}%)");
        
        // Send notification
        $this->sendNotification('moved_to_spam', $email_data, $spam_rate);
        
        // Log action
        $this->logAction('moved_to_spam', $email_data, $spam_rate);
        
        return ['action' => 'moved_to_spam', 'status' => 'success'];
    }
    
    /**
     * Flag email as spam (webhook)
     */
    private function flagAsSpam($email_data, $spam_rate) {
        $this->log("FLAGGING AS SPAM: " . $email_data['subject'] . " (Spam rate: {$spam_rate}%)");
        
        // Send notification
        $this->sendNotification('flagged_as_spam', $email_data, $spam_rate);
        
        // Log action
        $this->logAction('flagged_as_spam', $email_data, $spam_rate);
        
        return ['action' => 'flagged_as_spam', 'status' => 'success'];
    }
    
    /**
     * Mark email as suspicious (webhook)
     */
    private function markSuspicious($email_data, $spam_rate) {
        $this->log("MARKING SUSPICIOUS: " . $email_data['subject'] . " (Spam rate: {$spam_rate}%)");
        
        // Send notification
        $this->sendNotification('marked_suspicious', $email_data, $spam_rate);
        
        // Log action
        $this->logAction('marked_suspicious', $email_data, $spam_rate);
        
        return ['action' => 'marked_suspicious', 'status' => 'success'];
    }
    
    /**
     * Allow email (webhook)
     */
    private function allowEmail($email_data) {
        $this->log("ALLOWING EMAIL: " . $email_data['subject']);
        
        // Log action
        $this->logAction('allowed', $email_data, 0);
        
        return ['action' => 'allowed', 'status' => 'success'];
    }
    
    /**
     * Move IMAP email to spam folder
     */
    private function moveIMAPToSpam($connection, $message_number, $email_data, $spam_rate) {
        $this->log("MOVING IMAP TO SPAM: " . $email_data['subject'] . " (Spam rate: {$spam_rate}%)");
        
        try {
            // Create spam folder if it doesn't exist
            $spam_folder = 'INBOX.Spam';
            $this->createFolderIfNotExists($connection, $spam_folder);
            
            // Move email to spam folder
            $result = imap_mail_move($connection, $message_number, $spam_folder);
            
            if ($result) {
                $this->log("Successfully moved email to spam folder");
                $this->sendNotification('moved_to_spam', $email_data, $spam_rate);
                $this->logAction('moved_to_spam', $email_data, $spam_rate);
                return ['action' => 'moved_to_spam', 'status' => 'success'];
            } else {
                $this->log("Failed to move email to spam folder");
                return ['action' => 'moved_to_spam', 'status' => 'failed'];
            }
            
        } catch (Exception $e) {
            $this->log("Error moving email to spam: " . $e->getMessage());
            return ['action' => 'moved_to_spam', 'status' => 'error', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Flag IMAP email as spam
     */
    private function flagIMAPAsSpam($connection, $message_number, $email_data, $spam_rate) {
        $this->log("FLAGGING IMAP AS SPAM: " . $email_data['subject'] . " (Spam rate: {$spam_rate}%)");
        
        try {
            // Add spam flag to email
            $result = imap_setflag_full($connection, $message_number, '\\Spam', ST_UID);
            
            if ($result) {
                $this->log("Successfully flagged email as spam");
                $this->sendNotification('flagged_as_spam', $email_data, $spam_rate);
                $this->logAction('flagged_as_spam', $email_data, $spam_rate);
                return ['action' => 'flagged_as_spam', 'status' => 'success'];
            } else {
                $this->log("Failed to flag email as spam");
                return ['action' => 'flagged_as_spam', 'status' => 'failed'];
            }
            
        } catch (Exception $e) {
            $this->log("Error flagging email as spam: " . $e->getMessage());
            return ['action' => 'flagged_as_spam', 'status' => 'error', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Mark IMAP email as suspicious
     */
    private function markIMAPSuspicious($connection, $message_number, $email_data, $spam_rate) {
        $this->log("MARKING IMAP SUSPICIOUS: " . $email_data['subject'] . " (Spam rate: {$spam_rate}%)");
        
        try {
            // Add suspicious flag to email
            $result = imap_setflag_full($connection, $message_number, '\\Flagged', ST_UID);
            
            if ($result) {
                $this->log("Successfully marked email as suspicious");
                $this->sendNotification('marked_suspicious', $email_data, $spam_rate);
                $this->logAction('marked_suspicious', $email_data, $spam_rate);
                return ['action' => 'marked_suspicious', 'status' => 'success'];
            } else {
                $this->log("Failed to mark email as suspicious");
                return ['action' => 'marked_suspicious', 'status' => 'failed'];
            }
            
        } catch (Exception $e) {
            $this->log("Error marking email as suspicious: " . $e->getMessage());
            return ['action' => 'marked_suspicious', 'status' => 'error', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Allow IMAP email
     */
    private function allowIMAPEmail($connection, $message_number, $email_data) {
        $this->log("ALLOWING IMAP EMAIL: " . $email_data['subject']);
        
        // Log action
        $this->logAction('allowed', $email_data, 0);
        
        return ['action' => 'allowed', 'status' => 'success'];
    }
    
    /**
     * Create folder if it doesn't exist
     */
    private function createFolderIfNotExists($connection, $folder_name) {
        $folders = imap_list($connection, '{' . $this->getServerName($connection) . '}', '*');
        
        if (!in_array('{' . $this->getServerName($connection) . '}' . $folder_name, $folders)) {
            imap_createmailbox($connection, '{' . $this->getServerName($connection) . '}' . $folder_name);
            $this->log("Created folder: {$folder_name}");
        }
    }
    
    /**
     * Get server name from connection
     */
    private function getServerName($connection) {
        $server = imap_server($connection);
        return $server;
    }
    
    /**
     * Send notification about email action
     */
    private function sendNotification($action, $email_data, $spam_rate = null) {
        if (!$this->config['notifications']['enabled']) {
            return;
        }
        
        $message = "Email {$action}: " . $email_data['subject'] . " from " . $email_data['from'];
        if ($spam_rate !== null) {
            $message .= " (Spam rate: " . $spam_rate . "%)";
        }
        
        // Send email notification
        if ($this->config['notifications']['email']) {
            $this->sendEmailNotification($action, $email_data, $spam_rate);
        }
        
        // Send webhook notification
        if ($this->config['notifications']['webhook']) {
            $this->sendWebhookNotification($action, $email_data, $spam_rate);
        }
    }
    
    /**
     * Send email notification
     */
    private function sendEmailNotification($action, $email_data, $spam_rate = null) {
        $to = $this->config['notifications']['email_address'];
        $subject = "Email {$action}: " . $email_data['subject'];
        
        $message = "An email has been {$action}:\n\n";
        $message .= "Subject: " . $email_data['subject'] . "\n";
        $message .= "From: " . $email_data['from'] . "\n";
        $message .= "Date: " . ($email_data['date'] ?? date('Y-m-d H:i:s')) . "\n";
        if ($spam_rate !== null) {
            $message .= "Spam Rate: " . $spam_rate . "%\n";
        }
        $message .= "\nAction taken: " . strtoupper($action) . "\n";
        
        mail($to, $subject, $message);
    }
    
    /**
     * Send webhook notification
     */
    private function sendWebhookNotification($action, $email_data, $spam_rate = null) {
        $webhook_url = $this->config['notifications']['webhook_url'];
        
        if (!$webhook_url) {
            return;
        }
        
        $data = [
            'action' => $action,
            'email' => $email_data,
            'spam_rate' => $spam_rate,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        curl_exec($ch);
        curl_close($ch);
    }
    
    /**
     * Log detection results
     */
    private function logDetection($email_data, $spam_rate, $is_spam, $source) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'source' => $source,
            'subject' => $email_data['subject'] ?? '',
            'from' => $email_data['from'] ?? '',
            'spam_rate' => $spam_rate,
            'is_spam' => $is_spam,
            'action' => $this->determineAction($spam_rate)
        ];
        
        $log_file = __DIR__ . '/logs/detection_log.json';
        $logs = [];
        
        if (file_exists($log_file)) {
            $logs = json_decode(file_get_contents($log_file), true) ?: [];
        }
        
        $logs[] = $log_entry;
        
        // Keep only last 1000 entries
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
    }
    
    /**
     * Log action taken
     */
    private function logAction($action, $email_data, $spam_rate) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'subject' => $email_data['subject'] ?? '',
            'from' => $email_data['from'] ?? '',
            'spam_rate' => $spam_rate
        ];
        
        $log_file = __DIR__ . '/logs/actions_log.json';
        $logs = [];
        
        if (file_exists($log_file)) {
            $logs = json_decode(file_get_contents($log_file), true) ?: [];
        }
        
        $logs[] = $log_entry;
        
        // Keep only last 1000 entries
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
    }
    
    /**
     * Load configuration
     */
    private function loadConfig() {
        $config_file = __DIR__ . '/realtime_config.json';
        
        if (!file_exists($config_file)) {
            $this->createDefaultConfig($config_file);
        }
        
        return json_decode(file_get_contents($config_file), true);
    }
    
    /**
     * Create default configuration
     */
    private function createDefaultConfig($config_file) {
        $config = [
            'spam_threshold' => 50,
            'notifications' => [
                'enabled' => true,
                'email' => true,
                'webhook' => false,
                'email_address' => 'admin@example.com',
                'webhook_url' => null
            ],
            'actions' => [
                'move_to_spam_threshold' => 80,
                'flag_as_spam_threshold' => 60,
                'mark_suspicious_threshold' => 40
            ]
        ];
        
        file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
        $this->log("Created default configuration file: {$config_file}");
    }
    
    /**
     * Load trained model
     */
    private function loadModel() {
        $model_path = __DIR__ . '/trained_model.json';
        
        if (file_exists($model_path)) {
            try {
                $this->model->loadModel($model_path);
                $this->log("Model loaded successfully");
            } catch (Exception $e) {
                $this->log("Error loading model: " . $e->getMessage());
                $this->trainModel();
            }
        } else {
            $this->log("Model not found, training new model...");
            $this->trainModel();
        }
    }
    
    /**
     * Train model
     */
    private function trainModel() {
        $this->log("Training new spam detection model...");
        
        $training_data = $this->model->generateTrainingData();
        $this->model->train($training_data);
        
        $model_path = __DIR__ . '/trained_model.json';
        $this->model->saveModel($model_path);
        
        $this->log("Model training completed");
    }
    
    /**
     * Log message
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}\n";
        
        file_put_contents($this->log_file, $log_message, FILE_APPEND | LOCK_EX);
        
        // Also output to console if running from command line
        if (php_sapi_name() === 'cli') {
            echo $log_message;
        }
    }
}
