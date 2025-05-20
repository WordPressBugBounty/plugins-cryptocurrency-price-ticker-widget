<?php
// Do not use namespace to keep this in the global space and maintain singleton initialization
if (!class_exists('Openexchange_api_settings')) {

    /**
     * Main class for creating dashboard addon page and all submenu items
     * Do not call or initialize this class directly; instead, use the function mentioned at the bottom of this file
     */
    class Openexchange_api_settings
    {
        /**
         * Private static instance variable
         */
        private static $instance;

        /**
         * Initialize the class and create the dashboard page only once
         */
        public static function init()
        {
            if (empty(self::$instance)) {
                return self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Initialize the dashboard with specific plugins as per plugin tag
         */
        public function cool_init_hooks()
        {
            add_action('admin_notices', array($this, 'openexchange_api_key_notice'));
            add_action('admin_menu', array($this, 'openexchange_add_submenu'), 100);
            add_action('cmb2_admin_init', array($this, 'openexchange_settings_callback'));
            add_action('ccpw_get_extra_info', array($this, 'ccpw_get_extra_info'));
            add_action('admin_enqueue_scripts', array($this, 'openexchange_custom_javascript_for_cmb2'));
            add_action('cmb2_save_options-page_fields', array($this, 'ccpw_handle_unchecked_checkbox'), 10, 3);
        }

        function ccpw_handle_unchecked_checkbox($object_id, $updated, $cmb) {

            if ($object_id === 'openexchange-api-settings') {

                $choice = get_option('cpfm_opt_in_choice_crypto');
                $options = get_option($object_id, array());

                if (!empty($choice)) {
                    
                    if (!isset($_POST['ccpw_extra_info'])) {

                        $options['ccpw_extra_info'] = false;
                       
                        // Clear scheduled hook if it exists
                        wp_clear_scheduled_hook('ccpw_extra_data_update');
                        
                        // Only check for CMC if the class exists and the option isn't set
                        if ( method_exists('CMC_cronjob', 'cmc_send_data')  && !isset($_POST['cmc_extra_info'])) {
                            

                            $options['cmc_extra_info'] = false;
                            wp_clear_scheduled_hook('cmc_extra_data_update');
                        }
                        if ( method_exists('CCEW_cronjob', 'ccew_send_data') &&  !isset($_POST['ccew_extra_info'])) {

                            $options['ccew_extra_info'] = false;
                            wp_clear_scheduled_hook('ccew_extra_data_update');
                        }
                        
                        update_option($object_id, $options);

                    } else {

                        // Only schedule the cron job if it's not already scheduled
                        if (!wp_next_scheduled('ccpw_extra_data_update')) {

                            CCPW_cronjob::ccpw_send_data(); // Trigger immediate data send
                            wp_schedule_event(time(), 'every_30_days', 'ccpw_extra_data_update');
                        }

                        if ( method_exists('CMC_cronjob', 'cmc_send_data') && !isset($_POST['cmc_extra_info'])) {

                            CMC_cronjob::cmc_send_data(); // Trigger immediate data send
                            wp_schedule_event(time(), 'every_30_days', 'cmc_extra_data_update');
                            $options['cmc_extra_info'] = true;
                            
                        }

                        if (  method_exists('CCEW_cronjob', 'ccew_send_data') && !isset($_POST['ccew_extra_info'])) {

                            $options['ccew_extra_info'] = true;
                            CCEW_cronjob::ccew_send_data(); // Trigger immediate data send
                            wp_schedule_event(time(), 'every_30_days', 'ccew_extra_data_update');
                        }

                        // Optionally set the flag to true for clarity
                        $options['ccpw_extra_info'] = true;
                        update_option($object_id, $options);
                    }

                }
            }
        }
        /**
         * Enqueue custom JavaScript for CMB2
         */
        public function openexchange_custom_javascript_for_cmb2()
        {
            $custom_script_inline = "
            jQuery(document).ready(function ($) {
        
                // Initially hide the API key input fields
                $('.cmb2-id-coinmarketcap-api').hide();
                $('.cmb2-id-coingecko-api').hide();
                $('.cmb2-id-coincap-api').hide();
        
                var url = window.location.href;
                if (url.indexOf('?page=openexchange-api-settings') > 0) {
                    
                    // Handle changes in the API selection
                    $('.cmb2-id-ccpw-select-api #ccpw_select_api').change(function() {
                        $('.cmb2-id-coinmarketcap-api').hide();
                        $('.cmb2-id-coingecko-api').hide();
                        $('.cmb2-id-coincap-api').hide();
                        
                        // Retrieve the selected value
                        const selectedValue = $(this).val();
                        if(selectedValue == 'coin_gecko'){
                            $('.cmb2-id-coingecko-api').show();
                        }
                        else if (selectedValue == 'coin_marketcap') {
                            $('.cmb2-id-coinmarketcap-api').show();
                        }else if (selectedValue == 'coin_capapi') {
                            $('.cmb2-id-coincap-api').show();
                        }
                    }).change(); // Trigger change to apply correct visibility on page load
        
                    $('[href=\"admin.php?page=openexchange-api-settings\"]').parent('li').addClass('current');
                }
                var data = $('#adminmenu #toplevel_page_cool-crypto-plugins ul li a[href=\"admin.php?page=openexchange-api-settings\"]');
                data.each(function () {
                    if ($(this).is(':empty')) {
                        $(this).hide();
                    }
                });
        
            });
            ";
            wp_enqueue_script('jquery');
            wp_add_inline_script('jquery', $custom_script_inline);
        }
  

        /**
         * Add submenu page for API settings
         */
        public function openexchange_add_submenu()
        {
            add_submenu_page('cool-crypto-plugins', 'API Settings', 'API Settings', 'manage_options', 'admin.php?page=openexchange-api-settings', false, 100);
        }

        /**
         * Render and create the HTML display of dashboard page
         */
        public function openexchange_settings_callback()
        {
            $ccpw_nonce = wp_create_nonce('ccpw-nonce');
            $ajax_url = admin_url('admin-ajax.php');
            // Register options page menu item and form
            $cool_options = new_cmb2_box(
                array(
                    'id' => 'ccpw_settings_page',
                    'title' => esc_html__('API Settings', 'celp1'),
                    'object_types' => array('options-page'),
                    'option_key' => 'openexchange-api-settings', // Option key and admin menu page slug
                    'menu_title' => false, // Falls back to 'title' (above)
                    'parent_slug' => 'cool-crypto-plugins', // Make options page a submenu item of the themes menu
                    'capability' => 'manage_options', // Cap required to view options-page
                    'position' => 44, // Menu position
                )
            );

            // Add fields
            $cool_options->add_field(
                array(
                    'name' => __('Enter OpenExchangeRates.org API Key', 'ccpw1'),
                    'id' => 'ccpw_openexchangerate_api_title',
                    'type' => 'title',
                )
            );

            $cool_options->add_field(
                array(
                    'name' => __('Enter API Key', 'ccpw1'),
                    'desc' => __('Display cryptocurrency prices in over <b>30 fiat currencies</b>.<br/>
					>>  <a href="https://openexchangerates.org/signup/free" target="blank">Get OpenExchangeRates.org Free API Key</a>', 'ccpw1'),
                    'id' => 'openexchangerate_api',
                    'type' => 'text',
                )
            );

            $cool_options->add_field(array(
                'name' => __('Api Settings', 'ccpw1'),
                'id' => 'ccpw_coingecko_api_title',
                'type' => 'title',
            ));

            $cool_options->add_field(
                array(
                    'name' => 'Select API',
                    'id' => 'ccpw_select_api',
                    'desc' => 'Pick the source for fetching crypto price data using an API.',
                    'type' => 'select',
                    'default' => 'coin_gecko',
                    'options' => array(
                        'coin_gecko' => __('CoinGecko API', 'cmb2'),
                        'coin_paprika' => __('Coinpaprika API', 'cmb2'),
                        'coin_marketcap' => __('CoinMarketCap API', 'cmb2'),
                        'coin_capapi' => __('CoinCap API','cmb2')
                    ),
                )
            );

            $cool_options->add_field(array(
                'name' => __('Enter CoinGecko API Key', 'ccpw1'),
                'desc' => __('Check - <a href="https://support.coingecko.com/hc/en-us/articles/21880397454233-User-Guide-How-to-use-Demo-plan-API-key?utm_source=cryptocurrency-widgets&utm_medium=plugin&utm_campaign=coolplugins&utm_content=view_crypto_widget" target="blank">How to retrieve CoinGecko Free API Key ?</a>', 'ccpw1'),
                'id' => 'coingecko_api',
                'type' => 'text',

            ));
            $cool_options->add_field(array(
                'name' => __('Enter CoinMarketCap API Key', 'ccpw1'),
                'desc' => __('Check - <a href="https://coinmarketcap.com/api/" target="blank">How to retrieve CoinMarketCap Free API Key ?</a>', 'ccpw1'),
                'id' => 'coinmarketcap_api',
                'type' => 'text',

            ));
            $cool_options->add_field(array(
                'name' => __('Enter CoinCap API Key', 'ccpw1'),
                'desc' => __('Check - <a href="https://coincap.io/api-key" target="blank">How to retrieve CoinCap Free API Key ?</a>', 'ccpw1'),
                'id' => 'coincap_api',
                'type' => 'text',
            ));
            $cool_options->add_field(
                array(
                    'name' => 'Select API Cache Time',
                    'id' => 'select_cache_time',
                    'desc' => 'Trigger the API after that interval to load the most recent prices.',
                    'type' => 'select',
                    'default' => '10',
                    'options' => array(
                        '5' => __('5 Minutes', 'cmb2'),
                        '10' => __('10 Minutes', 'cmb2'),
                        '15' => __('15 Minutes', 'cmb2'),
                    ),
                    'desc' => 'Approximately 18,000 monthly API calls can be handled with a 5-minute API cache.<br>
                    Approximately 9,000 API calls per month can be managed with a 10-minute API cache.<br>
                    With a 15-minute API cache, you can support approximately 6,000 monthly API calls.',
                )
            );

            $selected_api = get_option("openexchange-api-settings");
            $api_type = (isset($selected_api['ccpw_select_api'])) ? $selected_api['ccpw_select_api'] : "coin_gecko";
            $active_api = ($api_type == "coin_gecko") ? "https://www.coingecko.com/en/developers/dashboard?utm_source=cryptocurrency-widgets&utm_medium=plugin&utm_campaign=coolplugins&utm_content=view_crypto_widget" : "https://pro.coinmarketcap.com/account";
            
            if ($api_type == "coin_gecko" || $api_type == "coin_marketcap") {
             
                $cool_options->add_field(array(
                    'name' => 'API Usage Report',
                    'id' => 'ccpw_api_hit_title',
                    'type' => 'title',
                    'desc' => '<div class="cmb-th"></div><div class="cmb-td"><table><tr><td><a href="' . $active_api . ' " target="blank">Click here to view API usage details</a></td><td></td></tr></table></div>',
                ));
            }

            $cpfm_opt_in    = get_option('cpfm_opt_in_choice_crypto');
            $notice_check   = isset($cpfm_opt_in) ? $cpfm_opt_in : '';

            if($notice_check)   {

                $cool_options->add_field(
                    array(
                        'name' => __('Make Cryptocurrency Widgets Even Better', 'ccpw1'),
                        'id' => 'ccpw_extra_info_title',
                        'type' => 'title',
                    
                    )
                );
                do_action('ccpw_get_extra_info', $cool_options);
            }
            
            $cool_options->add_field(
                array(
                    'name' => 'Purge Crypto Widget Free API Data Cache',
                    'id' => 'Delete Cache',
                    'type' => 'title',
                    'desc' => '<button class="button button-secondary" data-ccpw-nonce="' . esc_attr($ccpw_nonce) . '" data-ajax-url="' . esc_url($ajax_url) . '" id="ccpw_delete_cache">' . __('Purge Cache', 'ccpw') . '</button>',
                )
            );

            

        }

        public function ccpw_get_extra_info($cool_options_setting) {

            $choice     = get_option('cpfm_opt_in_choice_crypto');
        
            $api_option = get_option("openexchange-api-settings");
           
            if (!empty($api_option) && isset($api_option['ccpw_extra_info'])) {

                $choice = $api_option['ccpw_extra_info'];

            }

            $choice = (!empty($choice) && $choice === 'yes') ? 'on' : '';

            $terms_html = '
                Help us make this plugin more compatible with your site by sharing non-sensitive site data. 
                <a href="#" class="ccpw-see-terms">[See terms]</a>
                <div id="termsBox" style="display: none;padding-left: 20px; margin-top: 10px; font-size: 12px; color: #999;">
                 <p>' . esc_html__('Opt in to receive email updates about security improvements, new features, helpful tutorials, and occasional special offers. We\'ll collect:', 'ccpw') . '</p>
                    <ul style="list-style-type:auto;">
                        <li>'. esc_html__('Your website home URL and WordPress admin email.', 'ccpw') . '</li>
                        <li>' . esc_html__('To check plugin compatibility, we will collect the following: list of active plugins and themes, server type, MySQL version, WordPress version, memory limit, site language and database prefix.', 'ccpw') . '</li>
                    </ul>
                </div>';

            $cool_options_setting->add_field(array(
                'name'      => __('Usage Data Sharing ', 'ccpw1'),
                'id'        => 'ccpw_extra_info',
                'type'      => 'checkbox',
                'default'   => $choice,
                'desc'      => $terms_html,
              
            ));
       
        }
        

        /**
         * Admin notice for OpenExchangeRates.org API key
         */
        public function openexchange_api_key_notice()
        {
            // Check API options
            $api_option = get_option("openexchange-api-settings");
            $openexchange_api = (!empty($api_option['openexchangerate_api'])) ? $api_option['openexchangerate_api'] : "";
            $coin_gecko_api = (!empty($api_option['coingecko_api'])) ? $api_option['coingecko_api'] : "";
            $coin_marketcap_api = (!empty($api_option['coinmarketcap_api'])) ? $api_option['coinmarketcap_api'] : "";
            $coin_cap_api = (!empty($api_option['coincap_api'])) ? $api_option['coincap_api'] : "";
            $api_type = (isset($api_option['ccpw_select_api'])) ? $api_option['ccpw_select_api'] : "coin_gecko";
            
            // Check user capabilities
            if (!current_user_can('delete_posts')) {
                return;
            }

            // Get current user
            $current_user = wp_get_current_user();
            $user_name = $current_user->display_name;

            // Check if OpenExchange API key is missing
            if (empty($openexchange_api)) {
                ?>
				<div  class="license-warning notice notice-error is-dismissible">
					<p>Hi, <strong><?php echo ucwords($user_name); ?></strong>! Please <strong><a href="<?php echo esc_url(get_admin_url(null, 'admin.php?page=openexchange-api-settings')); ?>">enter</a></strong> Openexchangerates.org free API key for crypto to fiat price conversions.</p>
				</div>
				<?php
            }

            // Check if CoinGecko API key is missing
            if (($api_type == "coin_gecko") && empty($coin_gecko_api)) {
                if (empty($coin_gecko_api)) {
                    ?>
					<div  class="license-warning notice notice-error is-dismissible">
						<p>Hi, <strong><?php echo ucwords($user_name); ?></strong>! Please <strong><a href="<?php echo esc_url(get_admin_url(null, 'admin.php?page=openexchange-api-settings')); ?>">enter</a></strong> Coingecko free API key to work with this plugin.</p>
					</div>
					<?php
                }
            } elseif (($api_type == "coin_marketcap") && empty($coin_marketcap_api)) {
                ?>
                <div  class="license-warning notice notice-error is-dismissible">
                    <p>Hi, <strong><?php echo ucwords($user_name); ?></strong>! Please <strong><a href="<?php echo esc_url(get_admin_url(null, 'admin.php?page=openexchange-api-settings')); ?>">enter</a></strong> Coinmarketcap API key to work this plugin.</p>

                </div>
                <?php
            
            } elseif (($api_type == "coin_capapi") && empty($coin_cap_api)) {
                ?>
                <div  class="license-warning notice notice-error is-dismissible">
                    <p>Hi, <strong><?php echo ucwords($user_name); ?></strong>! Please <strong><a href="<?php echo esc_url(get_admin_url(null, 'admin.php?page=openexchange-api-settings')); ?>">enter</a></strong> CoinCap API key to work this plugin.</p>

                </div>
                <?php
            }
        }

    }

    // Initialize the main dashboard class with all required parameters
    $Openexchange = Openexchange_api_settings::init();
    $Openexchange->cool_init_hooks();
}
