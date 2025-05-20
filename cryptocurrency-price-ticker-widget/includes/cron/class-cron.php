<?php
if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('CCPW_cronjob')) {
    class CCPW_cronjob
    {
        use CCPW_Helper_Functions;

        public function __construct() {
           
            add_action('init', array($this, 'ccpw_cron_coins_autoupdater'));
          // Register cron jobs
            add_filter('cron_schedules', array($this, 'ccpw_cron_schedules'));
            add_action('ccpw_extra_data_update', array($this, 'ccpw_cron_extra_data_autoupdater'));
            add_action('ccpw_coins_autosave', array($this, 'ccpw_cron_coins_autoupdater'));
        }
        
        function ccpw_cron_extra_data_autoupdater() {
       
            $settings       = get_option('openexchange-api-settings', []);
            $ccpw_response  = isset($settings['ccpw_extra_info']) ? $settings['ccpw_extra_info'] : '';

            if (!empty($ccpw_response) || $ccpw_response === 'on'){
          
                if (class_exists('CCPW_cronjob')) {
                    CCPW_cronjob::ccpw_send_data();
                }
            }
        }
           
       static public function ccpw_send_data() {
                   
            $feedback_url = 'https://feedback.coolplugins.net/wp-json/coolplugins-feedback/v1/site';
            require_once CCPWF_DIR . 'admin/feedback/class-admin-feedback-form.php';

            if (!defined('CCPWF_DIR')  || !class_exists('\CCPW\feedback\cp_feedback') ) {
                return;
            }
            
            $extra_data         = new \CCPW\feedback\cp_feedback();
            $extra_data_details = $extra_data->cpfm_get_user_info();


            $server_info    = $extra_data_details['server_info'];
            $extra_details  = $extra_data_details['extra_details'];
            $site_url       = get_site_url();
            $install_date   = get_option('ccpw-install-date');
            $uni_id         = '2';
            $site_id        = $site_url . '-' . $install_date . '-' . $uni_id;
            $initial_version = get_option('crypto_widgets_initial_save_version');
            $initial_version = is_string($initial_version) ? sanitize_text_field($initial_version) : 'N/A';
            $plugin_version = defined('CCPWF_VERSION') ? CCPWF_VERSION : 'N/A';
            $admin_email    = sanitize_email(get_option('admin_email') ?: 'N/A');
            
            $post_data = array(

                'site_id'           => md5($site_id),
                'plugin_version'    => $plugin_version,
                'plugin_name'       => 'Cryptocurrency Widgets',
                'plugin_initial'    => $initial_version,
                'email'             => $admin_email,
                'site_url'          => esc_url_raw($site_url),
                'server_info'       => $server_info,
                'extra_details'     => $extra_details,
            );
            
            $response = wp_remote_post($feedback_url, array(

                'method'    => 'POST',
                'timeout'   => 30,
                'headers'   => array(
                    'Content-Type' => 'application/json',
                ),
                'body'      => wp_json_encode($post_data),
            ));
            
            if (is_wp_error($response)) {

                error_log('CCPW Feedback Send Failed: ' . $response->get_error_message());
                return;
            }
            
            $response_body  = wp_remote_retrieve_body($response);
            $decoded        = json_decode($response_body, true);
            
            if (!wp_next_scheduled('ccpw_extra_data_update')) {

                wp_schedule_event(time(), 'every_30_days', 'ccpw_extra_data_update');
            }
        }
          
        /**
         * Cron status schedule(s).
         */
        public function ccpw_cron_schedules($schedules)
        {
            // 5 minute schedule for grabbing all coins
            if (!isset($schedules['5min'])) {

                $schedules['5min'] = array(
                    'interval' => 5 * 60,
                    'display' => __('Once every 5 minutes'),
                );
            }

            // 30days schedule for update information

            if (!isset($schedules['every_30_days'])) {

                $schedules['every_30_days'] = array(
                    'interval' => 30 * 24 * 60 * 60, // 2,592,000 seconds
                    'display'  => __('Once every 30 days'),
                );
            }

            return $schedules;
        }

        /*
        |-----------------------------------------------------------
        |   This will update the database after a specific interval
        |-----------------------------------------------------------
        |   Always use this function to update the database
        |-----------------------------------------------------------
         */
        public function ccpw_cron_coins_autoupdater()
        {
            // Do not proceed further if
            if (!$this->ccpw_check_user()) {
                return;
            }

            $current_select = $this->ccpw_current_select_api();

            // Determine the selected API
            $api_obj = new CCPW_api_data();

            // Fetch coin data based on the selected API
            if($current_select == 'coin_paprika'){
                $data = $api_obj->ccpw_get_coin_paprika_data();
            }elseif($current_select == 'coin_marketcap'){
                $data = $api_obj->ccpw_get_coin_marketcap_data();
            }elseif($current_select == 'coin_capapi'){
                $data = $api_obj->ccpw_get_coin_cap_data();
            }else{
                $data = $api_obj->ccpw_get_coin_gecko_data();
            }

            // Check if 24 hours have passed since the last check
            $last_check_time = get_transient('ccpw_last_check_time');
            if (!$last_check_time) {
                // Call the function to update the coin list
                $obj = new ccpw_database();
                $obj->ccpw_check_coin_list();
                // Update the last check time in transient
                set_transient('ccpw_last_check_time', time(), 24 * 60 * 60);
            }

            // Reset option data once on the first day of the month
            $this->reset_option_data_once_on_first_of_month();
        }

        /**
         * Reset option data once on the first day of the month
         */
        public function reset_option_data_once_on_first_of_month()
        {
            // Check if it's the 1st day of the month
            $current_date = date('j');

            if ($current_date === '1') {
                // Check if a flag or option indicating the reset has already been performed
                $reset_flag = get_option('ccpw_reset_flag');

                // If the reset has not been performed (reset_flag is not set), perform the reset
                if (empty($reset_flag)) {
                    // Reset your option data
                    update_option('cmc_coingecko_api_hits', 0);

                    // Set a flag to indicate that the reset has been performed
                    update_option('ccpw_reset_flag', '1');
                }
            } else {
                // If it's not the first day of the month, delete the reset flag if it exists
                if (get_option('ccpw_reset_flag')) {
                    delete_option('ccpw_reset_flag');
                }
            }
        }
    }

    $cron_init = new CCPW_cronjob();
}
