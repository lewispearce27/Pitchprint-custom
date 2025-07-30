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
     * Since PitchPrint doesn't have a direct categories endpoint,
     * we'll fetch from the admin panel webpage
     */
    public function get_categories() {
        $auth = $this->generate_signature();
        
        // First try the API endpoint
        $api_url = str_replace('/runtime/', '/admin/', $this->api_base_url) . 'fetch-categories';
        
        $response = $this->make_request('fetch-categories', array());
        
        if ($response['success'] && isset($response['data']['data'])) {
            return $response;
        }
        
        // Alternative: Try to get categories from designs endpoint
        // This is a workaround - you may need to manually create categories list
        $cached_categories = get_transient('pitchprint_categories_' . $this->api_key);
        
        if ($cached_categories !== false) {
            return array(
                'success' => true,
                'data' => $cached_categories
            );
        }
        
        // If no cached categories, return empty with instructions
        return array(
            'success' => true,
            'data' => array(
                'items' => array(),
                'message' => 'Categories need to be fetched manually from PitchPrint admin'
            )
        );
    }
    
    /**
     * Get designs by category
     */
    public function get_designs($category_id) {
        $result = $this->make_request('fetch-designs', array('categoryId' => $category_id));
        
        // Cache the category if we get successful results
        if ($result['success'] && isset($result['data']['data']['items']) && !empty($result['data']['data']['items'])) {
            // Extract category info from the response if available
            $categories = get_transient('pitchprint_categories_' . $this->api_key);
            if (!is_array($categories)) {
                $categories = array('items' => array());
            }
            
            // Add this category to our known categories
            $category_exists = false;
            foreach ($categories['items'] as $cat) {
                if ($cat['id'] === $category_id) {
                    $category_exists = true;
                    break;
                }
            }
            
            if (!$category_exists && isset($result['data']['data']['categoryTitle'])) {
                $categories['items'][] = array(
                    'id' => $category_id,
                    'title' => $result['data']['data']['categoryTitle']
                );
                set_transient('pitchprint_categories_' . $this->api_key, $categories, DAY_IN_SECONDS);
            }
        }
        
        return $result;
    }
    
    /**
     * Get project details
     */
    public function get_project($project_id) {
        return $this->make_request('fetch-project', array('projectId' => $project_id));
    }
    
    /**
     * Render PDF
     */
    public function render_pdf($project_id) {
        return $this->make_request('render-pdf', array('projectId' => $project_id));
    }
    
    /**
     * Fetch raster images
     */
    public function fetch_raster($project_id) {
        $auth = $this->generate_signature();
        $data = array_merge(array('projectId' => $project_id), $auth);
        
        $args = array(
            'method' => 'POST',
            'timeout' => 60,
            'body' => $data
        );
        
        $response = wp_remote_post('https://pitchprint.net/api/runtime/fetch-raster', $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        // This endpoint returns a zip file
        $headers = wp_remote_retrieve_headers($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        if ($content_type === 'application/zip') {
            return array(
                'success' => true,
                'data' => wp_remote_retrieve_body($response),
                'is_binary' => true
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to fetch raster images', 'pitchprint-integration')
        );
    }
    
    /**
     * Clone project
     */
    public function clone_project($project_id) {
        return $this->make_request('clone-project', array('projectId' => $project_id));
    }
    
    /**
     * Create blank project
     */
    public function create_blank_project($width, $height, $unit = 'in') {
        $data = array(
            'width' => $width,
            'height' => $height,
            'unit' => $unit
        );
        
        return $this->make_request('create-project', $data);
    }
}
