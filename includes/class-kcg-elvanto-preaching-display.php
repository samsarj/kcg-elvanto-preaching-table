<?php
/**
 * Display functionality for Elvanto Preaching Table
 *
 * @package KCGElvantoPreachingTable
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_Preaching_Display {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register shortcode for displaying the preaching table
        add_shortcode('kcg_preaching_table', array($this, 'render_preaching_table'));
        
        // Register REST API endpoint for fetching table data
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route(
            'kcg-elvanto/v1',
            '/preaching-services',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_preaching_services'),
                'permission_callback' => '__return_true',
            )
        );
    }
    
    /**
     * Get preaching services from the API provider
     */
    public function get_preaching_services($request) {
        // This endpoint returns cached preachers
        $preachers = KCG_Elvanto_Fetcher::get_preachers();
        
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Preachers retrieved successfully.',
                'preachers' => $preachers,
                'count' => count($preachers)
            ),
            200
        );
    }
    
    /**
     * Filter events by service type/title and add preacher information
     */
    private function filter_by_service_type($events, $service_type) {
        if (!is_array($events)) {
            return array();
        }
        
        // Get preachers data
        $preachers = KCG_Elvanto_Fetcher::get_preachers();
        
        $filtered_services = array();
        $service_type_lower = strtolower(trim($service_type));
        
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            
            // Get the title from the event
            $title = $event['title'] ?? $event['name'] ?? '';
            
            if (empty($title)) {
                continue;
            }
            
            // Check if the title contains the service type (case-insensitive)
            if (stripos($title, $service_type) !== false) {
                // Add the formatted date if date exists
                $date = $event['date'] ?? $event['start_date'] ?? null;
                if ($date && !isset($event['formatted_date'])) {
                    $timestamp = strtotime($date);
                    if ($timestamp) {
                        $event['formatted_date'] = date('D jS M', $timestamp);
                    }
                }
                
                // Add preacher information if available
                if (!empty($date) && isset($preachers[$date])) {
                    $event['preacher'] = $preachers[$date];
                }
                
                $filtered_services[] = $event;
            }
        }
        
        // Sort by date
        usort($filtered_services, function($a, $b) {
            $date_a = strtotime($a['date'] ?? $a['start_date'] ?? '1970-01-01');
            $date_b = strtotime($b['date'] ?? $b['start_date'] ?? '1970-01-01');
            return $date_a - $date_b;
        });
        
        return $filtered_services;
    }
    
    /**
     * Render the preaching table shortcode
     */
    public function render_preaching_table($atts = array()) {
        // Parse attributes
        $atts = shortcode_atts(
            array(
                'service_type' => '',
                'limit' => 10,
                'show_time' => 'no',
                'show_location' => 'no',
                'show_description' => 'no',
                'class' => 'kcg-preaching-table'
            ),
            $atts,
            'kcg_preaching_table'
        );
        
        // Get cached services
        $events = KCG_Elvanto_Fetcher::get_services();
        
        if (empty($events)) {
            return '<div class="kcg-preaching-error">No services have been fetched. Please ensure the cron job has run or manually trigger a refresh.</div>';
        }
        
        // Filter by service type if specified
        if (!empty($atts['service_type'])) {
            $filtered_services = $this->filter_by_service_type($events, $atts['service_type']);
        } else {
            $filtered_services = $events;
        }
        
        // Limit results
        $limit = intval($atts['limit']);
        if ($limit > 0) {
            $filtered_services = array_slice($filtered_services, 0, $limit);
        }
        
        // Build the table HTML
        $html = $this->build_table_html($filtered_services, $atts);
        
        return $html;
    }
    
    /**
     * Build the HTML table
     */
    private function build_table_html($services, $atts) {
        if (empty($services)) {
            $filter_text = !empty($atts['service_type']) ? ' matching "' . esc_html($atts['service_type']) . '"' : '';
            return '<div class="kcg-preaching-no-services">No services found' . $filter_text . '.</div>';
        }
        
        $class = esc_attr($atts['class']);
        
        $html = '<div class="kcg-card ' . $class . '-wrapper">';
        $html .= '<table class="' . $class . '">';
        
        // Table header
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th><h5>Date</h5></th>';
        $html .= '<th><h5>Name/Passage</h5></th>';
        $html .= '<th><h5>Speaker</h5></th>';
        $html .= '</tr>';
        $html .= '</thead>';
        
        // Table body
        $html .= '<tbody>';
        
        foreach ($services as $service) {
            $html .= '<tr>';
            
            // Date column
            $date_display = isset($service['formatted_date']) ? $service['formatted_date'] : $service['date'];
            $html .= '<td class="date-cell">' . esc_html($date_display) . '</td>';
            
            // Series column
            $series = $service['subtitle'] ?? 'N/A';
            $html .= '<td class="series-cell">' . esc_html($series) . '</td>';
            
            // Preacher column
            $preacher = $service['preacher'] ?? 'TBD';
            $html .= '<td class="preacher-cell">' . esc_html($preacher) . '</td>';
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
}
