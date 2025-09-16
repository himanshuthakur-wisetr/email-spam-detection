<?php
/**
 * Email Spam Detection AI Agent
 * Processes emails and extracts features for spam detection
 */

class EmailProcessor {
    
    private $spam_keywords = [
        'free', 'urgent', 'limited time', 'act now', 'click here', 'winner',
        'congratulations', 'cash', 'money', 'guarantee', 'no risk', 'viagra',
        'casino', 'lottery', 'inheritance', 'nigerian', 'prince', 'million',
        'dollars', 'euros', 'pounds', 'bitcoin', 'cryptocurrency', 'investment',
        'loan', 'credit', 'debt', 'refinance', 'mortgage', 'insurance',
        'weight loss', 'diet', 'pills', 'supplements', 'pharmacy', 'prescription',
        'discount', 'sale', 'offer', 'deal', 'bargain', 'cheap', 'affordable',
        'win', 'prize', 'award', 'bonus', 'reward', 'gift', 'present',
        'limited', 'exclusive', 'special', 'vip', 'premium', 'luxury',
        'instant', 'immediate', 'fast', 'quick', 'easy', 'simple',
        'secret', 'hidden', 'confidential', 'private', 'exclusive',
        'amazing', 'incredible', 'fantastic', 'wonderful', 'awesome',
        'shocking', 'surprising', 'unbelievable', 'act fast', 'hurry', 'rush',
        'don\'t wait', 'last chance', 'expires', 'deadline', 'asap', 'immediately',
        'click', 'here', 'now', 'today', 'tonight', 'this week',
        'guaranteed', 'promise', 'assure', 'certain', 'sure',
        'no obligation', 'no commitment', 'risk free', 'trial',
        'subscribe', 'unsubscribe', 'opt in', 'opt out',
        'spam', 'junk', 'trash', 'delete', 'remove',
        'work from home', 'make money', 'earn cash', 'get rich',
        'lose weight', 'burn fat', 'muscle building', 'fitness',
        'dating', 'singles', 'marriage', 'love', 'romance',
        'adult', 'xxx', 'porn', 'sex', 'nude', 'naked',
        'phishing', 'scam', 'fraud', 'fake', 'bogus',
        'malware', 'virus', 'hack', 'crack', 'pirate',
        'refund', 'rebate', 'compensation', 'settlement',
        'lawsuit', 'legal action', 'court', 'attorney',
        'inheritance', 'unclaimed', 'beneficiary', 'estate',
        'lottery', 'sweepstakes', 'contest', 'drawing',
        'crypto', 'forex', 'trading', 'stocks', 'shares',
        'mlm', 'pyramid', 'scheme', 'scam', 'fraud',
        'rolex', 'replica', 'fake', 'counterfeit',
        'viagra', 'cialis', 'levitra', 'pharmacy',
        'weight loss', 'diet pills', 'fat burner',
        'male enhancement', 'penis', 'breast', 'enlargement',
        'get paid', 'work at home', 'online job',
        'credit repair', 'debt consolidation', 'bad credit',
        'mortgage', 'refinance', 'home loan',
        'insurance', 'life insurance', 'health insurance',
        'car insurance', 'auto insurance', 'home insurance',
        'travel', 'vacation', 'cruise', 'hotel', 'flight',
        'dating', 'singles', 'marriage', 'divorce',
        'education', 'degree', 'diploma', 'certificate',
        'job', 'employment', 'career', 'resume',
        'business', 'opportunity', 'franchise', 'startup',
        'real estate', 'property', 'house', 'apartment',
        'car', 'auto', 'vehicle', 'truck', 'motorcycle',
        'electronics', 'gadgets', 'phones', 'computers',
        'fashion', 'clothing', 'shoes', 'jewelry',
        'beauty', 'cosmetics', 'skincare', 'makeup',
        'health', 'medical', 'doctor', 'hospital',
        'fitness', 'gym', 'exercise', 'workout',
        'food', 'restaurant', 'cooking', 'recipe',
        'entertainment', 'movies', 'music', 'games',
        'sports', 'football', 'basketball', 'soccer',
        'news', 'politics', 'government', 'election',
        'technology', 'software', 'apps', 'programming',
        'finance', 'banking', 'accounting', 'taxes',
        'legal', 'lawyer', 'attorney', 'court',
        'travel', 'tourism', 'vacation', 'holiday',
        'shopping', 'retail', 'store', 'mall',
        'services', 'consulting', 'repair', 'maintenance',
        'education', 'school', 'university', 'college',
        'healthcare', 'medical', 'dental', 'vision',
        'automotive', 'repair', 'parts', 'accessories',
        'home', 'garden', 'furniture', 'decor',
        'pets', 'animals', 'dogs', 'cats',
        'family', 'children', 'babies', 'toys',
        'hobbies', 'crafts', 'art', 'music',
        'books', 'magazines', 'newspapers', 'media',
        'gifts', 'cards', 'flowers', 'jewelry',
        'wedding', 'party', 'event', 'celebration',
        'funeral', 'memorial', 'obituary', 'death',
        'birthday', 'anniversary', 'holiday', 'christmas',
        'valentine', 'mother\'s day', 'father\'s day',
        'thanksgiving', 'easter', 'halloween', 'new year'
    ];
    
    private $suspicious_patterns = [
        '/\b[A-Z]{2,}\b/',  // Excessive caps
        '/[!]{2,}/',        // Multiple exclamation marks
        '/\$\d+/',          // Money amounts
        '/\b\d{10,}\b/',    // Long numbers (phone/account)
        '/https?:\/\/[^\s]+/', // URLs
        '/[^\x00-\x7F]/',   // Non-ASCII characters
    ];
    
    /**
     * Process email and extract features for spam detection
     */
    public function processEmail($email_data) {
        $features = [];
        
        // Extract email components
        $subject = $this->extractSubject($email_data);
        $body = $this->extractBody($email_data);
        $headers = $this->extractHeaders($email_data);
        $sender = $this->extractSender($email_data);
        
        // Basic features
        $features['subject_length'] = strlen($subject);
        $features['body_length'] = strlen($body);
        $features['total_length'] = $features['subject_length'] + $features['body_length'];
        
        // Text analysis features
        $features['spam_keyword_count'] = $this->countSpamKeywords($subject . ' ' . $body);
        $features['spam_keyword_ratio'] = $features['spam_keyword_count'] / max($features['total_length'], 1) * 100;
        
        // Pattern analysis
        $features['caps_ratio'] = $this->calculateCapsRatio($subject . ' ' . $body);
        $features['exclamation_count'] = substr_count($subject . ' ' . $body, '!');
        $features['url_count'] = preg_match_all('/https?:\/\/[^\s]+/', $subject . ' ' . $body);
        $features['number_count'] = preg_match_all('/\b\d+\b/', $subject . ' ' . $body);
        
        // Sender analysis
        $features['sender_domain_suspicious'] = $this->isSuspiciousDomain($sender);
        $features['sender_has_numbers'] = preg_match('/\d/', $sender) ? 1 : 0;
        
        // Header analysis
        $features['has_reply_to'] = isset($headers['Reply-To']) ? 1 : 0;
        $features['has_return_path'] = isset($headers['Return-Path']) ? 1 : 0;
        $features['header_count'] = count($headers);
        
        // Content analysis
        $features['html_content'] = $this->hasHtmlContent($body) ? 1 : 0;
        $features['attachment_count'] = $this->countAttachments($email_data);
        
        // Advanced features
        $features['subject_spam_score'] = $this->calculateSubjectSpamScore($subject);
        $features['body_spam_score'] = $this->calculateBodySpamScore($body);
        $features['overall_text_quality'] = $this->calculateTextQuality($subject . ' ' . $body);
        
        return [
            'features' => $features,
            'raw_data' => [
                'subject' => $subject,
                'body' => substr($body, 0, 1000), // Truncate for storage
                'sender' => $sender,
                'headers' => $headers
            ]
        ];
    }
    
    /**
     * Calculate spam probability using ML model
     */
    public function calculateSpamProbability($features) {
        // Simple rule-based scoring (can be replaced with trained ML model)
        $score = 0;
        
        // Spam keyword scoring
        $score += $features['spam_keyword_count'] * 2;
        $score += $features['spam_keyword_ratio'] * 0.5;
        
        // Pattern scoring
        $score += $features['caps_ratio'] * 0.3;
        $score += $features['exclamation_count'] * 0.5;
        $score += $features['url_count'] * 1.5;
        
        // Sender scoring
        $score += $features['sender_domain_suspicious'] * 3;
        $score += $features['sender_has_numbers'] * 1;
        
        // Content scoring
        $score += $features['html_content'] * 0.5;
        $score += $features['attachment_count'] * 0.3;
        
        // Advanced scoring
        $score += $features['subject_spam_score'] * 2;
        $score += $features['body_spam_score'] * 1.5;
        $score -= $features['overall_text_quality'] * 0.5;
        
        // Normalize to 0-100
        $probability = min(100, max(0, $score));
        
        return round($probability, 2);
    }
    
    private function extractSubject($email_data) {
        if (isset($email_data['subject'])) {
            return $email_data['subject'];
        }
        
        if (isset($email_data['headers']['Subject'])) {
            return $email_data['headers']['Subject'];
        }
        
        return '';
    }
    
    private function extractBody($email_data) {
        if (isset($email_data['body'])) {
            return $email_data['body'];
        }
        
        if (isset($email_data['text'])) {
            return $email_data['text'];
        }
        
        if (isset($email_data['html'])) {
            return strip_tags($email_data['html']);
        }
        
        return '';
    }
    
    private function extractHeaders($email_data) {
        if (isset($email_data['headers'])) {
            return $email_data['headers'];
        }
        
        return [];
    }
    
    private function extractSender($email_data) {
        if (isset($email_data['from'])) {
            return $email_data['from'];
        }
        
        if (isset($email_data['headers']['From'])) {
            return $email_data['headers']['From'];
        }
        
        return '';
    }
    
    private function countSpamKeywords($text) {
        $text = strtolower($text);
        $count = 0;
        
        foreach ($this->spam_keywords as $keyword) {
            $count += substr_count($text, strtolower($keyword));
        }
        
        return $count;
    }
    
    private function calculateCapsRatio($text) {
        $total_chars = strlen($text);
        $caps_chars = strlen(preg_replace('/[^A-Z]/', '', $text));
        
        return $total_chars > 0 ? ($caps_chars / $total_chars) * 100 : 0;
    }
    
    private function isSuspiciousDomain($sender) {
        $suspicious_domains = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', // Common but can be spoofed
            'tempmail.com', '10minutemail.com', 'guerrillamail.com'
        ];
        
        $domain = $this->extractDomain($sender);
        return in_array(strtolower($domain), $suspicious_domains) ? 0 : 1;
    }
    
    private function extractDomain($email) {
        if (preg_match('/@([^>]+)/', $email, $matches)) {
            return trim($matches[1], '>');
        }
        return '';
    }
    
    private function hasHtmlContent($body) {
        return strpos($body, '<') !== false && strpos($body, '>') !== false;
    }
    
    private function countAttachments($email_data) {
        if (isset($email_data['attachments'])) {
            return count($email_data['attachments']);
        }
        return 0;
    }
    
    private function calculateSubjectSpamScore($subject) {
        $score = 0;
        
        // Check for excessive caps
        if (strlen($subject) > 0) {
            $caps_ratio = $this->calculateCapsRatio($subject);
            if ($caps_ratio > 50) $score += 2;
        }
        
        // Check for spam keywords in subject
        $spam_count = $this->countSpamKeywords($subject);
        $score += $spam_count * 3;
        
        // Check for excessive punctuation
        $punct_count = substr_count($subject, '!') + substr_count($subject, '?');
        if ($punct_count > 2) $score += 1;
        
        return $score;
    }
    
    private function calculateBodySpamScore($body) {
        $score = 0;
        
        // Check for repetitive content
        $words = explode(' ', $body);
        $word_count = array_count_values($words);
        $max_repetition = max($word_count);
        if ($max_repetition > 10) $score += 2;
        
        // Check for excessive links
        $link_count = preg_match_all('/https?:\/\/[^\s]+/', $body);
        if ($link_count > 5) $score += 3;
        
        return $score;
    }
    
    private function calculateTextQuality($text) {
        // Simple text quality metric
        $words = explode(' ', $text);
        $avg_word_length = array_sum(array_map('strlen', $words)) / count($words);
        
        // Higher average word length suggests better quality
        return min(10, $avg_word_length);
    }
}
