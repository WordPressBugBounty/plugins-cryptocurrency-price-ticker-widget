<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('CCPW_Review_Notice')) {
    class CCPW_Review_Notice
    {

        // Constants for various plugin attributes
        const PLUGIN = 'Cryptocurrency Widgets';
        const SLUG = 'ccpw';
        const SPARE_ME = 'ccpw_spare_me';
        const ACTIVATE_TIME = 'ccpw_activation_time';
        const REVIEW_LINK = 'https://wordpress.org/support/plugin/cryptocurrency-price-ticker-widget/reviews/#new-post';
        const AJAX_REQUEST = 'ccpw_dismiss_notice';

        // Constructor to initialize hooks
        public function __construct()
        {
            add_action('admin_init', array($this, 'setup'));
        }

        // Setup hooks
        public function setup()
        {
            if (is_admin()) {
                add_action('admin_notices', array($this, 'display_review_notice'));
                add_action('wp_ajax_' . self::AJAX_REQUEST, array($this, 'dismiss_review_notice'));
            }
        }
                     

        // Callback function to dismiss review notice
        public function dismiss_review_notice()
        {
            // Check for nonce and validate it
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ccpw-nonce' ) ) {
                wp_send_json_error('You don\'t have permission to hide notice.');
                return;
            }
            if (!current_user_can('manage_options')) {
                wp_send_json_error('You don\'t have permission to dismiss admin notices.');
                return;
            }
            update_option(self::SPARE_ME, 'yes');
            wp_send_json_success();
        }


        // Callback function to display review notice
        public function display_review_notice()
        {
            if (!current_user_can('update_plugins')) {
                return;
            }

            $spare_me_option = get_option(self::SPARE_ME);

            if ($spare_me_option === 'yes') {
                return;
            }

            $installation_date = get_option(self::ACTIVATE_TIME);

            if (!$installation_date) {
                return;
            }

            // Convert numeric timestamp to formatted string if needed
            if (is_numeric($installation_date)) {
                $installation_date = gmdate('Y-m-d h:i:s', (int) $installation_date);
            }

            $install_date = new DateTime($installation_date);
            $current_date = new DateTime();
            $diff_days = $install_date->diff($current_date)->days;

            if ($diff_days >= 3) {
                wp_enqueue_script('ccpwf-review-notices-script', CCPWF_URL . 'admin/review-notices/js/ccpwf-review-notices.js', array('jquery'), CCPWF_VERSION, true);
                wp_enqueue_style('ccpwf-review-notices-styles', CCPWF_URL . 'admin/review-notices/css/ccpwf-review-notices.css', array(), CCPWF_VERSION);   
                echo wp_kses_post($this->create_notice_content());
            }
        }

        // Function to create HTML content for review notice
        public function create_notice_content()
        {
            $ajax_url = esc_url(admin_url('admin-ajax.php'));
            $ajax_callback = esc_attr(self::AJAX_REQUEST);
            $nonce = wp_create_nonce('ccpw-nonce');
            $wrap_cls = esc_attr('notice notice-info is-dismissible cool-feedback-notice-wrapper ccpw-review-notice-wrapper');

            $p_name = esc_html(self::PLUGIN);
            $message = sprintf(
                'Thanks for using <b>%s</b> WordPress plugin. We hope you liked it !<br/>Please give us a quick rating, it works as a boost for us to keep working on more <a href="https://coolplugins.net" target="_blank"><strong>Cool Plugins</strong></a>!',
                $p_name
            );

            $rate_text = esc_html__('Rate Now! ★★★★★', 'cryptocurrency-price-ticker-widget');
            $already_rated_text = esc_html__('Already Reviewed', 'cryptocurrency-price-ticker-widget');
            $not_interested_text = esc_html__('Not Interested', 'cryptocurrency-price-ticker-widget');

            $template = '<div data-ajax-url="%1$s" data-ajax-callback="%2$s" data-nonce="%3$s" class="%4$s">
                <div class="message_container">%5$s
                    <div class="callto_action">
                        <ul>
                            <li class="love_it"><a href="%6$s" class="like_it_btn button button-primary" target="_new" title="%7$s">%7$s</a></li>
                            <li class="already_rated"><a href="#" class="already_rated_btn button ccew_dismiss_notice" title="%8$s">%8$s</a></li>
                            <li class="already_rated"><a href="#" class="already_rated_btn button ccew_dismiss_notice" title="%9$s">%9$s</a></li>
                        </ul>
                        <div class="clrfix"></div>
                    </div>
                </div>
            </div>';

            return sprintf(
                $template,
                $ajax_url,
                $ajax_callback,
                esc_attr($nonce),
                $wrap_cls,
                $message,
                esc_url(self::REVIEW_LINK),
                $rate_text,
                $already_rated_text,
                $not_interested_text
            );
        }
    }

    new CCPW_Review_Notice();
}
