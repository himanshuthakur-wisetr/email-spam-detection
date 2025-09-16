<?php
/**
 * IMAP Email Monitoring Daemon
 * Continuously monitors email accounts for new emails and processes them in real-time
 */

require_once 'realtime_processor.php';

class IMAPDaemon {
    
    private $processor;
    private $config;
    private $running = false;
    private $log_file;
    
    public function __construct() {
        $this->processor = new RealtimeEmailProcessor();
        $this->config = $this->loadConfig();
        $this->log_file = __DIR__ . '/logs/email_actions.log';
        
        // Create logs directory
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        
        // Handle signals for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
    }
    
    /**
     * Start the IMAP daemon
     */
    public function start() {
        $this->log("🚀 Email Spam Detection System Started");
        $this->running = true;
        
        while ($this->running) {
            try {
                $this->monitorAccounts();
                sleep($this->config['check_interval'] ?? 30); // Check every 30 seconds
                
                // Handle signals
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
                
            } catch (Exception $e) {
                $this->log("❌ Error: " . $e->getMessage());
                sleep(60); // Wait 1 minute before retrying
            }
        }
        
        $this->log("🛑 Email Spam Detection System Stopped");
    }
    
    /**
     * Monitor all configured email accounts
     */
    private function monitorAccounts() {
        $accounts = $this->getEmailAccounts();
        
        foreach ($accounts as $account) {
            if (!$account['enabled']) {
                continue;
            }
            
            $this->monitorAccount($account);
        }
    }
    
    /**
     * Monitor individual email account
     */
    private function monitorAccount($account) {
        $connection = null;
        
        try {
            // Connect to email server
            $connection = $this->connectToEmailServer($account);
            
            if (!$connection) {
                $this->log("❌ Failed to connect to: " . $account['email']);
                return;
            }
            
            // Get new emails
            $new_emails = $this->getNewEmails($connection, $account);
            
            foreach ($new_emails as $email_data) {
                $this->processNewEmail($email_data, $connection, $account);
            }
            
        } catch (Exception $e) {
            $this->log("❌ Error monitoring " . $account['email'] . ": " . $e->getMessage());
        } finally {
            if ($connection) {
                $this->closeConnection($connection, $account);
            }
        }
    }
    
    /**
     * Connect to email server
     */
    private function connectToEmailServer($account) {
        $host = $account['imap_host'];
        $port = $account['imap_port'];
        $username = $account['email'];
        $password = $account['password'];
        $encryption = $account['encryption'] ?? 'ssl';
        
        $connection_string = "{{$host}:{$port}/imap/{$encryption}}INBOX";
        
        $connection = imap_open($connection_string, $username, $password);
        
        if (!$connection) {
            $this->log("❌ IMAP connection failed for: " . $username);
            return false;
        }
        
        return $connection;
    }
    
    /**
     * Get new emails from server
     */
    private function getNewEmails($connection, $account) {
        $emails = [];
        
        // Get message count
        $message_count = imap_num_msg($connection);
        
        if ($message_count == 0) {
            return $emails;
        }
        
        // Get unread messages
        $unread = imap_search($connection, 'UNSEEN');
        
        if (!$unread) {
            return $emails;
        }
        
        // Process each unread email
        foreach ($unread as $message_number) {
            $email_data = $this->parseEmail($connection, $message_number);
            if ($email_data) {
                $emails[] = $email_data;
            }
        }
        
        return $emails;
    }
    
    /**
     * Parse email from IMAP
     */
    private function parseEmail($connection, $message_number) {
        try {
            $header = imap_headerinfo($connection, $message_number);
            $body = imap_body($connection, $message_number);
            
            if (!$header) {
                return null;
            }
            
            $email_data = [
                'subject' => $this->decodeHeader($header->subject ?? ''),
                'from' => $this->decodeHeader($header->from[0]->mailbox . '@' . $header->from[0]->host),
                'to' => $this->decodeHeader($header->to[0]->mailbox . '@' . $header->to[0]->host),
                'date' => $header->date ?? '',
                'body' => $this->decodeBody($body),
                'message_number' => $message_number,
                'headers' => [
                    'Message-ID' => $header->message_id ?? '',
                    'Subject' => $this->decodeHeader($header->subject ?? ''),
                    'From' => $this->decodeHeader($header->from[0]->mailbox . '@' . $header->from[0]->host),
                    'To' => $this->decodeHeader($header->to[0]->mailbox . '@' . $header->to[0]->host),
                    'Date' => $header->date ?? ''
                ]
            ];
            
            return $email_data;
            
        } catch (Exception $e) {
            $this->log("❌ Error parsing email #{$message_number}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Decode email header
     */
    private function decodeHeader($header) {
        if (empty($header)) {
            return '';
        }
        
        $decoded = imap_mime_header_decode($header);
        $result = '';
        
        foreach ($decoded as $part) {
            $result .= $part->text;
        }
        
        return $result;
    }
    
    /**
     * Decode email body
     */
    private function decodeBody($body) {
        // Simple body decoding
        $decoded = quoted_printable_decode($body);
        $decoded = imap_utf8($decoded);
        
        // Remove HTML tags if present
        $decoded = strip_tags($decoded);
        
        return $decoded;
    }
    
    /**
     * Process new email
     */
    private function processNewEmail($email_data, $connection, $account) {
        // Process email for spam detection
        $result = $this->processor->processIMAPEmail($email_data, $connection, $email_data['message_number']);
        
        if ($result['success']) {
            // Log only important actions
            if ($result['action'] !== 'allowed') {
                $this->log("📧 " . strtoupper($result['action']) . " | " . $email_data['subject'] . " | From: " . $email_data['from'] . " | Spam Rate: " . $result['spam_rate'] . "%");
            }
        } else {
            $this->log("❌ Error processing email: " . $result['error']);
        }
    }
    
    /**
     * Close email connection
     */
    private function closeConnection($connection, $account) {
        imap_close($connection);
    }
    
    /**
     * Get email accounts to monitor
     */
    private function getEmailAccounts() {
        $accounts_file = __DIR__ . '/email_accounts.json';
        
        if (!file_exists($accounts_file)) {
            $this->createSampleAccountsFile($accounts_file);
        }
        
        $accounts = json_decode(file_get_contents($accounts_file), true);
        
        if (!$accounts) {
            $this->log("❌ No email accounts configured");
            return [];
        }
        
        return $accounts;
    }
    
    /**
     * Create sample accounts file
     */
    private function createSampleAccountsFile($file_path) {
        $sample_accounts = [
            [
                'email' => 'your-email@gmail.com',
                'password' => 'your-app-password',
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'encryption' => 'ssl',
                'enabled' => false,
                'description' => 'Gmail account - Enable 2FA and use App Password'
            ]
        ];
        
        file_put_contents($file_path, json_encode($sample_accounts, JSON_PRETTY_PRINT));
        $this->log("📝 Created sample accounts file");
    }
    
    /**
     * Load configuration
     */
    private function loadConfig() {
        $config_file = __DIR__ . '/imap_daemon_config.json';
        
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
            'check_interval' => 30, // Check every 30 seconds
            'max_connection_attempts' => 3,
            'connection_timeout' => 30,
            'max_emails_per_check' => 100,
            'log_level' => 'INFO'
        ];
        
        file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
        $this->log("📝 Created default configuration file");
    }
    
    /**
     * Shutdown daemon gracefully
     */
    public function shutdown() {
        $this->log("🛑 Shutdown signal received, stopping daemon...");
        $this->running = false;
    }
    
    /**
     * Log message (simplified)
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}\n";
        
        file_put_contents($this->log_file, $log_message, FILE_APPEND | LOCK_EX);
        echo $log_message;
    }
}

// Run daemon if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $daemon = new IMAPDaemon();
    $daemon->start();
}
