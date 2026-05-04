<?php
/**
 * Fetches services and preacher information from Elvanto API
 *
 * @package KCGElvantoPreachingTable
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_Fetcher {
    
    /**
     * Fetch services and preachers from Elvanto API in a single call
     *
     * @return array Array with 'services' and 'preachers' keys
     */
    public static function fetch_data() {
        // Do not fetch directly if the API provider is unavailable.
        if (!class_exists('KCG_Elvanto_API_Registry') || !class_exists('KCG_Elvanto_API_Client')) {
            error_log('KCG_Elvanto_API_Registry or API client not available');
            self::set_last_refresh_status('failed');
            return array('services' => array(), 'preachers' => array());
        }

        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+1 year'));
        $debug_info = array();

        $raw_services = KCG_Elvanto_API_Client::fetch_services(
            $start_date,
            $end_date,
            array('series_name', 'volunteers'),
            $debug_info
        );

        if (is_wp_error($raw_services)) {
            $debug_info['error'] = $raw_services->get_error_message();
            self::set_last_refresh_status('failed');
            return array(
                'services' => array(),
                'preachers' => array(),
                'raw_response' => wp_json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }

        if (!is_array($raw_services)) {
            $raw_services = array();
        }

        if (empty($raw_services) && isset($debug_info['error'])) {
            self::set_last_refresh_status('failed');
            return array(
                'services' => array(),
                'preachers' => array(),
                'raw_response' => wp_json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }
        
        // Process the raw services to extract both service data and preachers
        $services = array();
        $preachers = array();
        
        foreach ($raw_services as $service) {
            if (!is_array($service) || !isset($service['id'])) {
                continue;
            }
            
            // Extract service data
            $event = array(
                'id' => $service['id'],
                'source' => 'service',
                'title' => $service['name'] ?? '',
                'date' => substr($service['date'] ?? '', 0, 10), // Normalize to YYYY-MM-DD
            );
            
            if (!empty($service['series_name'])) {
                $event['subtitle'] = $service['series_name'];
            }
            
            if (!empty($service['picture'])) {
                $event['picture'] = $service['picture'];
            }
            
            if (!empty($service['location']['name'])) {
                $event['location'] = $service['location']['name'];
            }
            
            $services[] = $event;
            
            // Extract preacher data
            if (!empty($service['date'])) {
                $service_date = substr($service['date'], 0, 10); // Extract just the date part (YYYY-MM-DD)
                $preacher_name = self::extract_preacher_from_service($service);
                
                if ($preacher_name) {
                    $preachers[$service_date] = $preacher_name;
                }
            }
        }
        
        // Persist the latest service and preacher cache.
        update_option('kcg_elvanto_services', $services);
        update_option('kcg_elvanto_preachers', $preachers);
        set_transient('kcg_elvanto_services', $services, 12 * HOUR_IN_SECONDS);
        set_transient('kcg_elvanto_preachers', $preachers, 12 * HOUR_IN_SECONDS);
        self::set_last_refresh_status('success');
        
        error_log('Stored ' . count($services) . ' services and ' . count($preachers) . ' preachers');
        
        return array(
            'services' => $services,
            'preachers' => $preachers,
            'raw_response' => wp_json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
    
    /**
     * Update the last refresh timestamp and status
     *
     * @param string $status The refresh status, e.g. 'success' or 'failed'
     */
    private static function set_last_refresh_status($status) {
        update_option('kcg_preaching_table_last_refresh', current_time('mysql'));
        update_option('kcg_preaching_table_last_refresh_status', $status);
    }
    
    /**
     * Extract preacher name from service data
     *
     * @param array $service Service data from Elvanto API
     * @return string|null The preacher name or null if not found
     */
    private static function extract_preacher_from_service($service) {
        // Look for volunteers in the service data
        if (!isset($service['volunteers']['plan'])) {
            return null;
        }
        
        $plans = is_array($service['volunteers']['plan']) ? 
            $service['volunteers']['plan'] : 
            array($service['volunteers']['plan']);
        
        foreach ($plans as $plan) {
            if (!is_array($plan) || !isset($plan['positions']['position'])) {
                continue;
            }
            
            $positions = is_array($plan['positions']['position']) ? 
                $plan['positions']['position'] : 
                array($plan['positions']['position']);
            
            foreach ($positions as $position) {
                if (!is_array($position)) {
                    continue;
                }
                
                // Check if this is a preaching position
                $position_name = $position['position_name'] ?? '';
                if (stripos($position_name, 'preaching') !== false || 
                    stripos($position_name, 'leading') !== false ||
                    stripos($position_name, 'preach') !== false) {
                    
                    // Get volunteers for this position
                    if (isset($position['volunteers']['volunteer'])) {
                        $volunteers = is_array($position['volunteers']['volunteer']) ? 
                            $position['volunteers']['volunteer'] : 
                            array($position['volunteers']['volunteer']);
                        
                        foreach ($volunteers as $volunteer) {
                            if (!is_array($volunteer)) {
                                continue;
                            }
                            
                            $person = $volunteer['person'] ?? array();
                            if (is_array($person)) {
                                $firstname = $person['firstname'] ?? '';
                                $lastname = $person['lastname'] ?? '';
                                
                                if (!empty($firstname) || !empty($lastname)) {
                                    return trim($firstname . ' ' . $lastname);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get cached services
     *
     * @return array Services data
     */
    public static function get_services() {
        return get_transient('kcg_elvanto_services') ?: get_option('kcg_elvanto_services', array());
    }
    
    /**
     * Get cached preachers
     *
     * @return array Preachers keyed by date
     */
    public static function get_preachers() {
        return get_transient('kcg_elvanto_preachers') ?: get_option('kcg_elvanto_preachers', array());
    }
}
