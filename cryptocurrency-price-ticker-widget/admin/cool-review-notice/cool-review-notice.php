<?php

//phpcs:disable WordPress.Security.NonceVerification.Recommended

if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('Cool_Review_Notice')) {
	//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	class Cool_Review_Notice
	{

	/** @var string */
	private $prefix;
	/** @var string */
	private $plugin_name;
	/** @var string */
	private $review_link;
	/** @var string */
	private $spare_me_key;
	/** @var string */
	private $activation_time;
	/** @var string */
	private $ajax_action;
	/** @var array */
	private $allowed_pages;
	/** @var string */
	private $menu_slug;

	// Static properties to store assets URL/version from the first plugin that loads the class
	/** @var string */
	private static $assets_url = '';
	/** @var string */
	private static $assets_version = '';

	public function __construct(string $prefix, string $plugin_name, string $review_link, string $plugin_url = '', string $plugin_version = '', array $allowed_pages = [], string $menu_slug = '', string $activation_time = '', string $spare_me_key = '')
	{
		$this->prefix        = sanitize_key($prefix);
		$this->plugin_name   = sanitize_text_field($plugin_name);
		$this->review_link   = esc_url($review_link);
		$this->spare_me_key  = !empty($spare_me_key) ? sanitize_text_field($spare_me_key) : "{$this->prefix}_spare_me";
		$this->activation_time  = !empty($activation_time) ? sanitize_text_field($activation_time) : "{$this->prefix}_activation_time";
		$this->ajax_action   = "{$this->prefix}_dismiss_notice";
		$this->allowed_pages = $allowed_pages;
		$this->menu_slug     = !empty($menu_slug) ? sanitize_text_field($menu_slug) : 'cool-crypto-plugins';
		
		// Set assets URL and version only once (from the first plugin that loads the class)
		if (empty(self::$assets_url) && !empty($plugin_url)) {
			self::$assets_url = trailingslashit($plugin_url);
			self::$assets_version = sanitize_text_field($plugin_version);
		}

		if (is_admin()) {
			add_action('ccew_display_admin_notices', [$this, 'enqueue_notice']);
			add_action('wp_ajax_' .  $this->ajax_action, array($this, 'dismiss_review_notice'));
		}
	}

	public function enqueue_notice()
	{
		if (!current_user_can('update_plugins')) {
			return;
		}

		if (get_option($this->spare_me_key, 'no') === 'yes') {
			return;
		}

		// Check if we should only show on specific pages
		if (!empty($this->allowed_pages) && !$this->is_allowed_page()) {
			return;
		}

		
		// Allowed HTML tags
		$allowed_tags = [
			'h3'     => [],
			'p'      => [],
			'a'      => [
				'class'  => [],
				'target' => ['_blank'],
				'href'   => [],
			],
			'strong' => [],
		];

		$title = esc_html($this->plugin_name);

		$description = wp_kses(
			__('Thanks for using our plugin. Please give us a quick rating, it works as a boost for us to keep working on more <a href="https://coolplugins.net" target="_blank"><strong>Cool Plugins</strong></a>.', 'cryptocurrency-price-ticker-widget'),
			$allowed_tags
		);

		$pointer_content  = "<h3>{$title}</h3>";
		$pointer_content .= "<p>{$description}</p>";
		$pointer_content .= sprintf(
			'<p><a class="button button-primary" href="%s" target="_blank">%s</a></p>',
			$this->review_link,
			esc_html__('Rate Now! ★★★★★', 'cryptocurrency-price-ticker-widget')
		);

		$installation_date = get_option($this->activation_time);
		
		if (!$installation_date) {
                return;
            }

			if (is_numeric($installation_date)) {
                $installation_date = gmdate('Y-m-d h:i:s', (int) $installation_date);
            }

			// Calculate difference in days
			$install_date = new DateTime($installation_date);
			$current_date = new DateTime();
			$diff_days = $install_date->diff($current_date)->days;

			if ($diff_days >= 3) {
				$this->enqueue_dependencies($pointer_content, $allowed_tags);
			}
		}


	public function should_display_notice(): bool
	{
		return (get_option($this->spare_me_key, 'no') !== 'yes');
	}

	private function is_allowed_page(): bool
	{
		$current_screen = get_current_screen();
		
		if (!$current_screen) {
			return false;
		}

		// Check for page parameter in URL
		$current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		
		// Check for post_type parameter in URL or screen
		$current_post_type = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : $current_screen->post_type;

		foreach ($this->allowed_pages as $allowed_page) {
			// If it's a page parameter match
			if (!empty($current_page) && $current_page === $allowed_page) {
				return true;
			}
			
			// If it's a post_type parameter match
			if (!empty($current_post_type) && $current_post_type === $allowed_page) {
				return true;
			}
		}

		return false;
	}

	public function dismiss_review_notice()
		{
			if (!current_user_can('manage_options')) {
				wp_send_json_error('You don\'t have permission to dismiss admin notices.');
				wp_die();
			}
			check_ajax_referer("{$this->prefix}_review_nonce", 'nonce');

			update_option($this->spare_me_key, 'yes');

			wp_send_json_success('dismissed');
		}

	private function enqueue_dependencies($pointer_content, $allowed_tags)
	{
		wp_enqueue_script(
			"{$this->prefix}-black-friday-js",
			self::$assets_url . 'admin/cool-review-notice/js/cool-review-notice.js',
			['jquery', 'wp-pointer'],
			self::$assets_version,
			true
		);

		wp_enqueue_style(
			"{$this->prefix}-black-friday-css",
			self::$assets_url . 'admin/cool-review-notice/css/cool-review-notice.css',
			['wp-pointer'],
			self::$assets_version
		);

			$nonce = wp_create_nonce("{$this->prefix}_review_nonce");

			wp_localize_script(
				"{$this->prefix}-black-friday-js",
				"{$this->prefix}BFNotice",
				[
					'content'    => wp_kses($pointer_content, $allowed_tags),
					'dismissed'  => (sanitize_text_field(get_option($this->spare_me_key, 'no')) === 'yes')
				]
			);

		wp_localize_script(
			"{$this->prefix}-black-friday-js",
			"{$this->prefix}ReviewObj",
			[
				'ajax_url'      => admin_url('admin-ajax.php'),
				'nonce'         => $nonce,
				'action'        => $this->ajax_action,
				'menu_slug'     => $this->menu_slug,
			]
		);
		}
	}
}
