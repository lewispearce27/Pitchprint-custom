<?php
/**
 * PitchPrint API class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PitchPrint_API {
    
    private $api_key;
    private $secret_key;
    private $api_base_url = 'https://api.pitchprint.io/runtime/';
    private $admin_base_url = 'https://admin.pitchprint.io/';
    
    /**
     * Constructor
     */
    public function __construct($api_key, $secret_key) {
        $this->api_key = $api_key;
        $this->secret_key = $secret_key;
    }
    
    /**
     * Generate signature for API authentication
     */
    private function generate_signature() {
        $timestamp = time();
        $signature = md5($this->api_key . $this->secret_key . $timestamp);
        
        return array(
            'timestamp' => $timestamp,
            'apiKey' => $this->api_key,
            'signature' => $signature
        );
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $data = array(), $method = 'POST') {
        $auth = $this->generate_signature();
        $data = array_merge($data, $auth);
        
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        );
        
        $response = wp_remote_request($this->api_base_url . $endpoint, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => __('Invalid response from API', 'pitchprint-integration')
            );
        }
        
        // Check if response has error
        if (isset($data['error']) && $data['error']) {
            return array(
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : __('API Error', 'pitchprint-integration')
            );
        }
        
        return array(
            'success' => true,
            'data' => $data
        );
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        // Make a simple request to verify credentials
        // Using fetch-designs endpoint with a dummy category as a test
        $result = $this->make_request('fetch-designs', array('categoryId' => 'test'));
        
        if ($result['success'] || (isset($result['data']['error']) && strpos($result['data']['message'], 'category') !== false)) {
            // If we get a category-related error, it means auth worked
            return array(
                'success' => true,
                'message' => __('Connection successful', 'pitchprint-integration')
            );
        }
        
        return $result;
    }
    
    /**
     * Get design categories
     * Since PitchPrint doesn't have a categories endpoint, we'll discover them
     */
    public function get_categories() {
        // Check if we have cached categories
        $cached_categories = get_transient('pitchprint_categories_' . $this->api_key);
        
        if ($cached_categories !== false && !empty($cached_categories)) {
            return array(
                'success' => true,
                'data' => array('items' => $cached_categories)
            );
        }
        
        // Common category ID patterns to test
        $test_patterns = array(
            // Single letters
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 
            'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
            // Numbers
            '1', '2', '3', '4', '5', '6', '7', '8', '9', '10',
            // Common patterns
            'cat1', 'cat2', 'cat3', 'cat4', 'cat5',
            'category1', 'category2', 'category3',
            'default', 'main', 'general'
        );
        
        $found_categories = array();
        
        foreach ($test_patterns as $pattern) {
            $result = $this->get_designs($pattern);
            
            if ($result['success'] && isset($result['data']['data']['items']) && !empty($result['data']['data']['items'])) {
                // Extract category info from the response
                $category_name = $pattern; // Default to ID
                
                // Try to get category name from the response
                if (isset($result['data']['data']['categoryTitle'])) {
                    $category_name = $result['data']['data']['categoryTitle'];
                } elseif (isset($result['data']['data']['category'])) {
                    $category_name = $result['data']['data']['category'];
                } elseif (!empty($result['data']['data']['items'])) {
                    // Try to infer from first design
                    $first_design = $result['data']['data']['items'][0];
                    if (isset($first_design['category'])) {
                        $category_name = $first_design['category'];
                    }
