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
     * Get design categories - properly formatted
     */
    public function get_categories() {
        // Try the main categories endpoint
        $result = $this->make_request('fetch-design-categories', array());
        
        if ($result['success']) {
            $categories = array();
            
            // Handle different response formats from PitchPrint API
            if (isset($result['data']['data'])) {
                $data = $result['data']['data'];
                
                // If it's an object with categories
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        if (is_array($value) && isset($value['id'], $value['title'])) {
                            // Format: [{id: 'xxx', title: 'Name'}, ...]
                            $categories[] = array(
                                'id' => $value['id'],
                                'title' => $value['title']
                            );
                        } elseif (is_string($value)) {
                            // Format: {id: 'title', id2: 'title2'}
                            $categories[] = array(
                                'id' => $key,
                                'title' => $value
                            );
                        }
                    }
                }
            } elseif (isset($result['data']) && is_array($result['data'])) {
                // Direct array format
                foreach ($result['data'] as $key => $value) {
                    if (is_array($value) && isset($value['id'], $value['title'])) {
                        $categories[] = $value;
                    } elseif (is_string($value)) {
                        $categories[] = array(
                            'id' => $key,
                            'title' => $value
                        );
                    }
                }
            }
            
            // If we couldn't parse categories, try a different approach
            if (empty($categories)) {
                // Sometimes the API returns categories in a different structure
                pitchprint_log('Categories response structure: ' . print_r($result['data'], true));
                
                // Try to extract from any available data
                if (isset($result['data']['values'])) {
                    foreach ($result['data']['values'] as $cat) {
                        if (isset($cat['id'], $cat['title'])) {
                            $categories[] = array(
                                'id' => $cat['id'],
                                'title' => $cat['title']
                            );
                        }
                    }
                }
            }
            
            return array(
                'success' => true,
                'data' => $categories
            );
        }
        
        return $result;
    }
    
    /**
     * Get designs by category
     */
    public function get_designs($category_id) {
        $result = $this->make_request('fetch-designs', array('categoryId' => $category_id));
        
        if ($result['success']) {
            // Log the response structure for debugging
            pitchprint_log('Designs response for category ' . $category_id . ': ' . print_r($result['data'], true));
            
            // Ensure we have the proper structure
            if (!isset($result['data']['data'])) {
                $result['data']['data'] = array('items' => array());
            }
            
            if (!isset($result['data']['data']['items'])) {
                $result['data']['data']['items'] = array();
            }
            
            // Process items to ensure they have titles
            $items = $result['data']['data']['items'];
            foreach ($items as &$item) {
                if (!isset($item['title']) || empty($item['title'])) {
                    // Use design name or ID as fallback
                    if (isset($item['name'])) {
                        $item['title'] = $item['name'];
                    } elseif (isset($item['designName'])) {
                        $item['title'] = $item['designName'];
                    } else {
                        $item['title'] = $item['designId'];
                    }
                }
            }
            $result['data']['data']['items'] = $items;
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
