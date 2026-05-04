<?php
/**
 * Admin functionality for KCG Elvanto Preaching Table
 *
 * @package KCGElvantoPreachingTable
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_Preaching_Admin {
    
    /**
     * Initialize admin functionality
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'), 20);
        add_action('admin_init', array($this, 'handle_refresh'));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_page() {
        add_submenu_page(
            'kcg-elvanto-api',
            'Elvanto Preaching Table',
            'Preaching Table',
            'manage_options',
            'kcg-preaching-table-refresh',
            array($this, 'admin_page')
        );
    }

    /**
     * Handle manual refresh
     */
    public function handle_refresh() {
        if (!isset($_POST['kcg_preaching_table_refresh_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['kcg_preaching_table_refresh_nonce'], 'kcg_preaching_table_refresh')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['kcg_preaching_table_refresh'])) {
            $data = KCG_Elvanto_Fetcher::fetch_data();
            
            if (!empty($data['services'])) {
                update_option('kcg_elvanto_services', $data['services']);
                set_transient('kcg_elvanto_services', $data['services'], 12 * HOUR_IN_SECONDS);
            }
            
            if (!empty($data['preachers'])) {
                update_option('kcg_elvanto_preachers', $data['preachers']);
                set_transient('kcg_elvanto_preachers', $data['preachers'], 12 * HOUR_IN_SECONDS);
            }
            
            // Store raw API response for debugging
            if (!empty($data['raw_response'])) {
                update_option('kcg_preaching_table_last_response', $data['raw_response']);
            }
            
            wp_safe_remote_post(
                add_query_arg('kcg_preaching_refresh_success', '1', admin_url('admin.php?page=kcg-preaching-table-refresh'))
            );
        }
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        $last_refresh = get_option('kcg_preaching_table_last_refresh');
        $last_response = get_option('kcg_preaching_table_last_response');
        $refresh_status = get_option('kcg_preaching_table_last_refresh_status');
        $services = KCG_Elvanto_Fetcher::get_services();
        $preachers = KCG_Elvanto_Fetcher::get_preachers();
        $services_count = count($services);
        $preachers_count = count($preachers);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (isset($_GET['kcg_preaching_refresh_success'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Preaching table data refreshed successfully!', 'kcg-elvanto-preaching-table'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="postbox" style="max-width: 600px;">
                <h2 class="hndle"><span><?php esc_html_e('Refresh Data', 'kcg-elvanto-preaching-table'); ?></span></h2>
                <div class="inside">
                    <p><?php esc_html_e('Click the button below to manually refresh the preaching table data from Elvanto:', 'kcg-elvanto-preaching-table'); ?></p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('kcg_preaching_table_refresh', 'kcg_preaching_table_refresh_nonce'); ?>
                        <p>
                            <button type="submit" name="kcg_preaching_table_refresh" class="button button-primary">
                                <?php esc_html_e('Refresh Now', 'kcg-elvanto-preaching-table'); ?>
                            </button>
                        </p>
                    </form>
                    
                    <hr>
                    
                    <h3><?php esc_html_e('Connection Status', 'kcg-elvanto-preaching-table'); ?></h3>
                    
                    <?php if ($last_refresh): ?>
                        <div style="background-color: #f5f5f5; padding: 12px; border-left: 4px solid #28a745; margin-bottom: 12px;">
                            <p style="margin: 0;">
                                <strong><?php esc_html_e('Last Refresh:', 'kcg-elvanto-preaching-table'); ?></strong>
                                <code><?php echo esc_html($last_refresh); ?></code>
                            </p>
                            <p style="margin: 8px 0 0 0; color: #666;">
                                <strong><?php esc_html_e('Status:', 'kcg-elvanto-preaching-table'); ?></strong>
                                <span style="background-color: <?php echo $refresh_status === 'success' ? '#28a745' : '#dc3545'; ?>; color: white; padding: 2px 8px; border-radius: 3px; display: inline-block;">
                                    <?php echo esc_html($refresh_status ?: 'unknown'); ?>
                                </span>
                            </p>
                            <p style="margin: 8px 0 0 0; color: #666;">
                                <strong><?php esc_html_e('Data Loaded:', 'kcg-elvanto-preaching-table'); ?></strong>
                                <?php printf(
                                    esc_html__('%d services, %d preachers', 'kcg-elvanto-preaching-table'),
                                    $services_count,
                                    $preachers_count
                                ); ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <p style="color: #999;"><em><?php esc_html_e('No refresh yet. Click "Refresh Now" to fetch data.', 'kcg-elvanto-preaching-table'); ?></em></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Combined Services with Preachers -->
            <div class="postbox" style="max-width: 900px; margin-top: 20px;">
                <h2 class="hndle"><span><?php esc_html_e('Services & Preachers', 'kcg-elvanto-preaching-table'); ?> (<?php echo esc_html($services_count); ?>)</span></h2>
                <div class="inside">
                    <?php if (!empty($services)): ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">
                                    <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd;"><strong>Date</strong></th>
                                    <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd;"><strong>Title</strong></th>
                                    <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd;"><strong>Series</strong></th>
                                    <th style="padding: 8px; text-align: left;"><strong>Preacher</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): 
                                    $date = $service['date'] ?? '';
                                    $preacher = isset($preachers[$date]) ? $preachers[$date] : '—';
                                ?>
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 8px; border-right: 1px solid #eee;"><code><?php echo esc_html($date); ?></code></td>
                                        <td style="padding: 8px; border-right: 1px solid #eee;"><strong><?php echo esc_html($service['title'] ?? ''); ?></strong></td>
                                        <td style="padding: 8px; border-right: 1px solid #eee;"><?php echo esc_html($service['subtitle'] ?? 'N/A'); ?></td>
                                        <td style="padding: 8px; font-weight: 500; background-color: <?php echo $preacher !== '—' ? '#f0f8ff' : 'transparent'; ?>;">
                                            <?php echo esc_html($preacher); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #999;"><em><?php esc_html_e('No services loaded yet.', 'kcg-elvanto-preaching-table'); ?></em></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Raw API Response -->
            <?php if (!empty($last_response)): ?>
            <div class="postbox" style="max-width: 900px; margin-top: 20px;">
                <h2 class="hndle"><span><?php esc_html_e('Raw API Response', 'kcg-elvanto-preaching-table'); ?></span></h2>
                <div class="inside">
                    <pre style="background-color: #f5f5f5; padding: 12px; border-radius: 3px; overflow-x: auto; max-height: 600px; overflow-y: auto; border: 1px solid #ddd; font-size: 12px; font-family: 'Courier New', monospace; line-height: 1.4;">
                        <?php echo esc_html($last_response); ?>
                    </pre>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
