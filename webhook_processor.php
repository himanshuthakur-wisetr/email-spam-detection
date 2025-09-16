<?php
/**
 * Webhook Email Processor
 * Processes emails automatically when they arrive via webhook
 * Logs spam percentage and trains AI model
 */

require_once 'email_processor.php';
require_once 'ml_model.php';

class WebhookEmailProcessor {
    
    private $processor;
    private $model;
    private $log_file;
    private $training_file;
    
    public function __construct() {
        $this->processor = new EmailProcessor();
        $this->model = new SpamDetectionModel();
        $this->log_file = __DIR__ . '/logs/email_analysis.log';
        $this->training_file = __DIR__ . '/logs/training_data.json';
        
        // Create logs directory
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        
        // Load or create model
        $this->loadModel();
    }
    
    /**
     * Process incoming email via webhook
     */
    public function processEmail($email_data) {
        try {
            // Extract email information
            $subject = $email_data['subject'] ?? '';
            $body = $email_data['body'] ?? '';
            $from = $email_data['from'] ?? '';
            $to = $email_data['to'] ?? '';
            $date = $email_data['date'] ?? date('Y-m-d H:i:s');
            
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
            
            // Return analysis result
            return [
                'success' => true,
                'subject' => $subject,
                'from' => $from,
                'spam_rate' => $spam_rate,
                'spam_level' => $spam_level,
                'features' => $features,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->logError("Error processing email: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
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
        
        // Also save to JSON for easy viewing
        $this->saveToJsonLog($log_entry);
        
        // Output to console
        echo "[{$timestamp}] 📧 EMAIL ANALYSIS\n";
        echo "Subject: {$subject}\n";
        echo "From: {$from}\n";
        echo "Spam Rate: {$spam_rate}% ({$spam_level})\n";
        echo "Keywords: {$features['spam_keyword_count']}, Caps: {$features['caps_ratio']}%, URLs: {$features['url_count']}\n";
        echo "----------------------------------------\n";
    }
    
    /**
     * Save to text log file
     */
    private function saveToLog($log_entry) {
        $log_line = "[{$log_entry['timestamp']}] {$log_entry['spam_level']} | {$log_entry['spam_rate']}% | {$log_entry['subject']} | From: {$log_entry['from']}\n";
        file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Save to JSON log file
     */
    private function saveToJsonLog($log_entry) {
        $json_file = __DIR__ . '/logs/email_analysis.json';
        $logs = [];
        
        if (file_exists($json_file)) {
            $logs = json_decode(file_get_contents($json_file), true) ?: [];
        }
        
        $logs[] = $log_entry;
        
        // Keep only last 1000 entries
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        file_put_contents($json_file, json_encode($logs, JSON_PRETTY_PRINT));
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
}

// Handle webhook requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input) {
        $processor = new WebhookEmailProcessor();
        $result = $processor->processEmail($input);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
    }
} else {
    // Show status page
    echo "<h2>🤖 AI Email Spam Detection System</h2>";
    echo "<p>System is ready to process emails via webhook.</p>";
    echo "<p>Send POST requests with email data to analyze spam.</p>";
    echo "<p><a href='view_analysis.php'>View Analysis Logs</a></p>";
}
?>
