<?php
/**
 * Machine Learning Model for Email Spam Detection
 * Implements a simple neural network and training system
 */

class SpamDetectionModel {
    
    private $weights = [];
    private $bias = 0;
    private $feature_names = [];
    private $is_trained = false;
    
    public function __construct() {
        $this->initializeWeights();
    }
    
    /**
     * Initialize model weights randomly
     */
    private function initializeWeights() {
        $this->feature_names = [
            'subject_length', 'body_length', 'total_length',
            'spam_keyword_count', 'spam_keyword_ratio', 'caps_ratio',
            'exclamation_count', 'url_count', 'number_count',
            'sender_domain_suspicious', 'sender_has_numbers',
            'has_reply_to', 'has_return_path', 'header_count',
            'html_content', 'attachment_count', 'subject_spam_score',
            'body_spam_score', 'overall_text_quality'
        ];
        
        // Initialize weights randomly
        foreach ($this->feature_names as $feature) {
            $this->weights[$feature] = (rand(-100, 100) / 100);
        }
        
        $this->bias = (rand(-100, 100) / 100);
    }
    
    /**
     * Train the model with sample data
     */
    public function train($training_data, $learning_rate = 0.01, $epochs = 1000) {
        echo "Training spam detection model...\n";
        
        for ($epoch = 0; $epoch < $epochs; $epoch++) {
            $total_error = 0;
            
            foreach ($training_data as $sample) {
                $features = $sample['features'];
                $actual = $sample['is_spam'] ? 1 : 0;
                
                // Forward pass
                $prediction = $this->predict($features);
                $error = $actual - $prediction;
                $total_error += abs($error);
                
                // Backward pass - update weights
                foreach ($this->feature_names as $feature) {
                    if (isset($features[$feature])) {
                        $this->weights[$feature] += $learning_rate * $error * $features[$feature];
                    }
                }
                
                $this->bias += $learning_rate * $error;
            }
            
            // Print progress every 100 epochs
            if ($epoch % 100 == 0) {
                $avg_error = $total_error / count($training_data);
                echo "Epoch $epoch: Average Error = " . round($avg_error, 4) . "\n";
            }
        }
        
        $this->is_trained = true;
        echo "Training completed!\n";
    }
    
    /**
     * Predict spam probability for given features
     */
    public function predict($features) {
        $sum = $this->bias;
        
        foreach ($this->feature_names as $feature) {
            if (isset($features[$feature])) {
                $sum += $this->weights[$feature] * $features[$feature];
            }
        }
        
        // Sigmoid activation function
        $probability = 1 / (1 + exp(-$sum));
        
        return $probability;
    }
    
    /**
     * Get spam rate as percentage
     */
    public function getSpamRate($features) {
        $probability = $this->predict($features);
        return round($probability * 100, 2);
    }
    
    /**
     * Save trained model to file
     */
    public function saveModel($file_path) {
        $model_data = [
            'weights' => $this->weights,
            'bias' => $this->bias,
            'feature_names' => $this->feature_names,
            'is_trained' => $this->is_trained,
            'trained_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($file_path, json_encode($model_data, JSON_PRETTY_PRINT));
        echo "Model saved to: $file_path\n";
    }
    
    /**
     * Load trained model from file
     */
    public function loadModel($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception("Model file not found: $file_path");
        }
        
        $model_data = json_decode(file_get_contents($file_path), true);
        
        $this->weights = $model_data['weights'];
        $this->bias = $model_data['bias'];
        $this->feature_names = $model_data['feature_names'];
        $this->is_trained = $model_data['is_trained'];
        
        echo "Model loaded from: $file_path\n";
        echo "Model trained at: " . $model_data['trained_at'] . "\n";
    }
    
    /**
     * Generate sample training data
     */
    public function generateTrainingData() {
        $training_data = [];
        
        // Spam samples
        $spam_samples = [
            [
                'subject' => 'URGENT! WIN $1000 CASH NOW!',
                'body' => 'Congratulations! You have won $1000! Click here to claim your prize! Limited time offer! Act now!',
                'from' => 'winner@spam.com',
                'is_spam' => true
            ],
            [
                'subject' => 'Free Viagra - No Prescription',
                'body' => 'Get cheap viagra online! No prescription needed! Guaranteed delivery! Click here now!',
                'from' => 'pharmacy@spam-site.com',
                'is_spam' => true
            ],
            [
                'subject' => 'Investment Opportunity - Bitcoin',
                'body' => 'Make money fast with bitcoin investment! Guaranteed returns! No risk! Click here to start earning!',
                'from' => 'invest@crypto-spam.net',
                'is_spam' => true
            ],
            [
                'subject' => 'Nigerian Prince Inheritance',
                'body' => 'I am a Nigerian prince with millions of dollars. I need your help to transfer money. You will get 20% commission.',
                'from' => 'prince@nigeria.com',
                'is_spam' => true
            ],
            [
                'subject' => 'You Won! Claim Your Prize!',
                'body' => 'Congratulations! You are the winner of our lottery! Claim your $5000 prize now! Limited time!',
                'from' => 'lottery@winner.com',
                'is_spam' => true
            ]
        ];
        
        // Ham (legitimate) samples
        $ham_samples = [
            [
                'subject' => 'Meeting Tomorrow at 2 PM',
                'body' => 'Hi John, just wanted to confirm our meeting tomorrow at 2 PM in the conference room. Please bring the quarterly reports.',
                'from' => 'sarah@company.com',
                'is_spam' => false
            ],
            [
                'subject' => 'Invoice #12345 - Payment Due',
                'body' => 'Dear Customer, Your invoice #12345 for $150.00 is due on 2024-01-15. Please remit payment to avoid late fees.',
                'from' => 'billing@legitimate-business.com',
                'is_spam' => false
            ],
            [
                'subject' => 'Newsletter - January 2024',
                'body' => 'Welcome to our monthly newsletter! This month we have exciting updates about our products and services.',
                'from' => 'newsletter@trusted-company.com',
                'is_spam' => false
            ],
            [
                'subject' => 'Password Reset Request',
                'body' => 'You requested a password reset for your account. Click the link below to reset your password. If you did not request this, please ignore.',
                'from' => 'noreply@secure-site.com',
                'is_spam' => false
            ],
            [
                'subject' => 'Thank You for Your Order',
                'body' => 'Thank you for your recent order #ORD-12345. Your items have been shipped and will arrive in 3-5 business days.',
                'from' => 'orders@ecommerce-site.com',
                'is_spam' => false
            ]
        ];
        
        // Process samples
        $processor = new EmailProcessor();
        
        foreach ($spam_samples as $sample) {
            $processed = $processor->processEmail($sample);
            $processed['is_spam'] = true;
            $training_data[] = $processed;
        }
        
        foreach ($ham_samples as $sample) {
            $processed = $processor->processEmail($sample);
            $processed['is_spam'] = false;
            $training_data[] = $processed;
        }
        
        return $training_data;
    }
    
    /**
     * Evaluate model performance
     */
    public function evaluate($test_data) {
        $correct = 0;
        $total = count($test_data);
        $true_positives = 0;
        $false_positives = 0;
        $true_negatives = 0;
        $false_negatives = 0;
        
        foreach ($test_data as $sample) {
            $prediction = $this->predict($sample['features']);
            $predicted_spam = $prediction > 0.5;
            $actual_spam = $sample['is_spam'];
            
            if ($predicted_spam == $actual_spam) {
                $correct++;
            }
            
            if ($predicted_spam && $actual_spam) {
                $true_positives++;
            } elseif ($predicted_spam && !$actual_spam) {
                $false_positives++;
            } elseif (!$predicted_spam && !$actual_spam) {
                $true_negatives++;
            } elseif (!$predicted_spam && $actual_spam) {
                $false_negatives++;
            }
        }
        
        $accuracy = $correct / $total;
        $precision = $true_positives / ($true_positives + $false_positives);
        $recall = $true_positives / ($true_positives + $false_negatives);
        $f1_score = 2 * ($precision * $recall) / ($precision + $recall);
        
        return [
            'accuracy' => round($accuracy * 100, 2),
            'precision' => round($precision * 100, 2),
            'recall' => round($recall * 100, 2),
            'f1_score' => round($f1_score * 100, 2),
            'true_positives' => $true_positives,
            'false_positives' => $false_positives,
            'true_negatives' => $true_negatives,
            'false_negatives' => $false_negatives
        ];
    }
}
