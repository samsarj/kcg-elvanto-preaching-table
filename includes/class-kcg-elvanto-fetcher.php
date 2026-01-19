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
        // Get API key from the provider
        if (!class_exists('KCG_Elvanto_API_Registry')) {
            error_log('KCG_Elvanto_API_Registry not available');
            return array('services' => array(), 'preachers' => array());
        }
        
        $api_key = KCG_Elvanto_API_Registry::get_api_key();
        if (!$api_key) {
            error_log('No Elvanto API key configured');
            return array('services' => array(), 'preachers' => array());
        }
        
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+1 year'));
        
        $url = 'https://api.elvanto.com/v1/services/getAll.json';
        
        // Single API call with both volunteers and series_name fields
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($api_key . ':x')
            ),
            'body' => json_encode(array(
                'start' => $start_date,
                'end' => $end_date,
                'fields' => array('series_name', 'volunteers')
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Services fetch error: ' . $response->get_error_message());
            return array('services' => array(), 'preachers' => array());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            error_log('Services API error: ' . json_encode($data['error']));
            return array('services' => array(), 'preachers' => array());
        }
        
        // Extract services from the pagination wrapper
        $raw_services = array();
        if (isset($data['services']['service']) && is_array($data['services']['service'])) {
            $raw_services = $data['services']['service'];
        } elseif (isset($data['services']) && is_array($data['services']) && !isset($data['services']['service'])) {
            $raw_services = $data['services'];
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
        
        // Store both in options
        update_option('kcg_elvanto_services', $services);
        update_option('kcg_elvanto_preachers', $preachers);
        
        error_log('Stored ' . count($services) . ' services and ' . count($preachers) . ' preachers');
        
        return array(
            'services' => $services,
            'preachers' => $preachers,
            'raw_response' => wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
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
        return get_option('kcg_elvanto_services', array());
    }
    
    /**
     * Get cached preachers
     *
     * @return array Preachers keyed by date
     */
    public static function get_preachers() {
        return get_option('kcg_elvanto_preachers', array());
    }
}
