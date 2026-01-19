<?php
/**
 * Helper Functions for KCG Elvanto Preaching Table
 * 
 * Essential helper functions for programmatic access to service data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get services by type programmatically
 * 
 * @param string $service_type Service type/title to filter by
 * @param int $limit Number of services to return
 * @return array Array of service data
 * 
 * Usage:
 *   $services = kcg_get_services_by_type('Sunday', 5);
 *   foreach ($services as $service) {
 *       echo $service['title'] . ' - ' . $service['formatted_date'];
 *   }
 */
if (!function_exists('kcg_get_services_by_type')) {
    function kcg_get_services_by_type($service_type, $limit = 10) {
        if (!class_exists('KCG_Elvanto_Fetcher')) {
            return array();
        }
        
        $events = KCG_Elvanto_Fetcher::get_services();
        $filtered_services = array();
        
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
                $date = $event['date'] ?? $event['start_date'] ?? null;
                if ($date && !isset($event['formatted_date'])) {
                    $timestamp = strtotime($date);
                    if ($timestamp) {
                        $event['formatted_date'] = date('F j, Y', $timestamp);
                    }
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
        
        return $limit > 0 ? array_slice($filtered_services, 0, $limit) : $filtered_services;
    }
}

/**
 * Get the next upcoming service matching a type
 * 
 * @param string $service_type Service type/title to filter by
 * @return array|false Service data or false if no services found
 * 
 * Usage:
 *   $next_service = kcg_get_next_service_by_type('Sunday');
 *   if ($next_service) {
 *       echo 'Next Service: ' . $next_service['title'];
 *   }
 */
if (!function_exists('kcg_get_next_service_by_type')) {
    function kcg_get_next_service_by_type($service_type) {
        $services = kcg_get_services_by_type($service_type, 1);
        return !empty($services) ? $services[0] : false;
    }
}

