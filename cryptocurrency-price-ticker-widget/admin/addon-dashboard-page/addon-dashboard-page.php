<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if current screen is the Crypto Addons dashboard page.
 *
 * Used to decide when to render the global header and enqueue JS.
 */
if ( ! function_exists( 'ccew_is_crypto_addon_page' ) ) {
	function ccew_is_crypto_addon_page() {
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page      = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : '';

		
		$crypto_pages = array(
			'cool-crypto-plugins',
			'cool-crypto-registration',
			'celp-ex-list',
			'celp_options',
			'openexchange-api-settings',
			'ccew-settings',
			'ccpw_get_started',
			'cmc-coins-list',
			'cmc-coin-details-settings',
			'cmc-coin-extra-settings',
			'cmc-coin-documentation-settings',
			'cmc-coin-update-settings',
			'cmc-coin-category-settings',
		);

		// Admin pages matched via ?page=...
		if ( 'admin.php' === $pagenow && in_array( $page, $crypto_pages, true ) ) {
			return true;
		}

		// Custom post type screens for Crypto Widgets, Exchanges List and CMC post types.
		if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) && in_array( $post_type, array( 'ccpw', 'celp', 'cmc', 'cmc-description' ), true ) ) {
			return true;
		}

		// Single post edit screen: post_type is not reliably in $_GET, so read from the current screen.
		if ( 'post.php' === $pagenow ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && in_array( $screen->post_type, array( 'ccpw', 'celp', 'cmc', 'cmc-description' ), true ) ) {
				return true;
			}
		}

		return false;
	}
}

// Do not use namespace to keep this in global space so the singleton initialization keeps working.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
if ( ! class_exists( 'cool_plugins_crypto_addons' ) ) {

	/**
	 * Main class for creating dashboard addon page and all submenu items.
	 * Do not call or initialize this class directly; use the function at the bottom of this file.
	 */
	class cool_plugins_crypto_addons {

		/** @var cool_plugins_crypto_addons|null */
		private static $instance;

		/** @var array */
		private $pro_plugins = array();

		/** @var array */
		private $pages = array();

		/** @var string|null */
		private $main_menu_slug = null;

		/** @var string|null */
		private $plugin_tag = null;

		/** @var string|null */
		private $dashboar_page_heading = null;

		/** @var array */
		private $disable_plugins = array();

		/** @var string */
		private $addon_dir = '';

		/** @var string */
		private $addon_file = '';

		/** @var string */
		private $menu_title = 'Addon Dashboard';

		/** @var string|false */
		private $menu_icon = false;

		/** @var bool True when header was output at admin_notices (so dashboard body skips it). */
		private static $global_header_rendered = false;

		/** @var array Discontinued Pro plugin slugs that should never appear on the dashboard. */
		private static $discontinued_pro_slugs = array();

		/** Allowed plugin slugs for install/activate from this dashboard (whitelist). */
		private static $allowed_slugs = array(
			// Free plugins (install from WordPress.org).
			'cryptocurrency-price-ticker-widget',
			'cryptocurrency-donation-box',
			'cryptocurrency-payments-using-metamask-for-woocommerce',
			'cryptocurrency-widgets-for-elementor',
			// Pro plugins (no download; activate only if already installed).
			'cryptocurrency-price-ticker-widget-pro',
			'cryptocurrency-donation-box-pro',
			'cryptocurrency-exchanges-list-pro',
			'crypto-ico-list-widget-pro',
			'pay-with-metamask-for-woocommerce-pro',
			'blockchain-explorer-pro',
			'coin-market-cap',
			'cryptocurrency-search-addon',
		);

		/** Pro plugin slugs (no download from WP.org; activate if already installed). */
		private static $pro_plugin_slugs = array(
			'cryptocurrency-price-ticker-widget-pro',
			'cryptocurrency-donation-box-pro',
			'cryptocurrency-exchanges-list-pro',
			'crypto-ico-list-widget-pro',
			'pay-with-metamask-for-woocommerce-pro',
			'blockchain-explorer-pro',
			'coin-market-cap',
			'cryptocurrency-search-addon',
		);

		/** Slugs that require WooCommerce to be active before install/activate from this dashboard. */
		private static $woocommerce_dependent_slugs = array(
			'cryptocurrency-payments-using-metamask-for-woocommerce',
			'pay-with-metamask-for-woocommerce-pro',
		);

		/** Map old slugs to current JSON slug (for cached dashboard data and backward compatibility). */
		private static $pro_slug_aliases = array();

		public function __construct() {
			$this->addon_dir  = __DIR__;
			$this->addon_file = __FILE__;
		}

		/**
		 * Initialize the class and create dashboard page only one time.
		 *
		 * @return cool_plugins_crypto_addons
		 */
		public static function init() {
			if ( empty( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Initialize the dashboard with specific plugins as per plugin tag.
		 *
		 * @param string $plugin_tag         Tag for plugin grouping.
		 * @param string $menu_slug          Main menu slug.
		 * @param string $dashboard_heading  Dashboard heading.
		 * @param string $main_menu_title    Menu title.
		 * @param string $icon               Menu icon URL or dashicon.
		 * @return bool
		 */
		public function show_plugins( $plugin_tag, $menu_slug, $dashboard_heading, $main_menu_title, $icon ) {
			if ( empty( $plugin_tag ) || empty( $menu_slug ) || empty( $dashboard_heading ) ) {
				return false;
			}
			$this->plugin_tag            = sanitize_text_field( $plugin_tag );
			$this->main_menu_slug        = sanitize_text_field( $menu_slug );
			$this->dashboar_page_heading = sanitize_text_field( $dashboard_heading );
			$this->menu_title            = sanitize_text_field( $main_menu_title );
			$this->menu_icon             = sanitize_text_field( $icon );

			add_action( 'admin_menu', array( $this, 'init_plugins_dasboard_page' ), 1 );
			add_action( 'wp_ajax_ccew_dashboard_install_plugin', array( $this, 'ccew_dashboard_install_plugin' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_required_scripts' ) );
			add_action( 'admin_notices', array( $this, 'maybe_render_global_header' ), 1 );
			add_action( 'admin_print_footer_scripts', array( $this, 'reorder_header_and_screen_meta' ) );

			return true;
		}

		/**
		 * Output the crypto header at the very top (admin_notices priority 1) on all crypto addon pages
		 * so that all notices (ours and third-party) display below the header.
		 */
		public function maybe_render_global_header() {
			if ( ! function_exists( 'ccew_is_crypto_addon_page' ) || ! ccew_is_crypto_addon_page() ) {
				return;
			}
			echo '<div class="ccew-global-crypto-header">';
			$prefix             = 'ccew';
			$show_wrapper       = false;
			$dashboard_instance = $this;
			include $this->addon_dir . '/includes/dashboard-header.php';
			do_action( 'ccew_after_crypto_header' );
			echo '</div>';
			self::$global_header_rendered = true;
		}

		/**
		 * Move the global crypto header before the Screen Options / Help panel in the DOM.
		 */
		public function reorder_header_and_screen_meta() {
			if ( ! function_exists( 'ccew_is_crypto_addon_page' ) || ! ccew_is_crypto_addon_page() ) {
				return;
			}
			?>
			<script>
			(function() {
				var body = document.body;
				if ( ! body ) {
					return;
				}
				var header = document.querySelector( '.ccew-global-crypto-header' );
				var screenMeta = document.getElementById( 'screen-meta' );

				if ( ! header || ! screenMeta || ! screenMeta.parentNode ) {
					return;
				}

				// Insert header just before the Screen Options / Help panel.
				if ( screenMeta.parentNode !== header.parentNode || header.previousElementSibling !== screenMeta ) {
					screenMeta.parentNode.insertBefore( header, screenMeta );
				}
			})();
			</script>
			<?php
		}

		/**
		 * Handle AJAX: install plugin via WordPress core or activate if already installed (including Pro).
		 */
		public function ccew_dashboard_install_plugin() {
			if ( ! current_user_can( 'install_plugins' ) ) {
				wp_send_json_error(
					array(
						'errorMessage' => __( 'Sorry, you are not allowed to install plugins on this site.', 'cryptocurrency-widgets-for-elementor' ),
					)
				);
			}

			check_ajax_referer( 'ccew-plugins-download', 'wp_nonce' );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above.
			$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
			if ( empty( $slug ) ) {
				wp_send_json_error(
					array(
						'slug'         => '',
						'errorCode'    => 'no_plugin_specified',
						'errorMessage' => __( 'No plugin specified.', 'cryptocurrency-widgets-for-elementor' ),
					)
				);
			}

			if ( ! in_array( $slug, self::$allowed_slugs, true ) ) {
				wp_send_json_error(
					array(
						'slug'         => $slug,
						'errorCode'    => 'plugin_not_allowed',
						'errorMessage' => __( 'This plugin cannot be installed from here.', 'cryptocurrency-widgets-for-elementor' ),
					)
				);
			}

			if ( in_array( $slug, self::$woocommerce_dependent_slugs, true ) ) {
				if ( ! function_exists( 'is_plugin_active' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
					wp_send_json_error(
						array(
							'slug'         => $slug,
							'errorCode'    => 'woocommerce_required',
							'errorMessage' => __( 'WooCommerce must be installed and active before you can install or activate this plugin.', 'cryptocurrency-widgets-for-elementor' ),
						)
					);
				}
			}

			$status = array(
				'install' => 'plugin',
				'slug'    => $slug,
			);

			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			// Pro plugins: only activate if already installed (no download from WP.org).
			if ( in_array( $slug, self::$pro_plugin_slugs, true ) ) {
				$slug_for_data = isset( self::$pro_slug_aliases[ $slug ] ) ? self::$pro_slug_aliases[ $slug ] : $slug;
				$pro_plugins   = $this->request_pro_plugins_data( $this->plugin_tag );
				$main_file     = ( ! empty( $pro_plugins[ $slug_for_data ]['main_file'] ) ) ? $pro_plugins[ $slug_for_data ]['main_file'] : ( $slug_for_data . '.php' );
				if ( substr( $main_file, -4 ) !== '.php' ) {
					$main_file .= '.php';
				}
				$plugin_file = $slug . '/' . $main_file;
				$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
				if ( ! file_exists( $plugin_path ) ) {
					$plugin_file = $slug_for_data . '/' . $main_file;
					$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
				}
				if ( ! file_exists( $plugin_path ) ) {
					// Fallback: discover main file from plugin directory (handles cached data without main_file or different filename).
					$all_plugins = get_plugins();
					foreach ( $all_plugins as $path => $plugin_data ) {
						if ( dirname( $path ) === $slug || dirname( $path ) === $slug_for_data ) {
							$plugin_file = $path;
							$plugin_path = WP_PLUGIN_DIR . '/' . $path;
							break;
						}
					}
				}
				if ( ! file_exists( $plugin_path ) && ! empty( $pro_plugins[ $slug_for_data ]['incompatible'] ) ) {
					// Pro may be installed in free_version folder.
					$free_slug = $pro_plugins[ $slug_for_data ]['incompatible'];
					$free_dir  = WP_PLUGIN_DIR . '/' . $free_slug;
					if ( file_exists( $free_dir ) ) {
						$all_plugins = get_plugins();
						foreach ( $all_plugins as $path => $plugin_data ) {
							if ( dirname( $path ) === $free_slug ) {
								$plugin_file = $path;
								$plugin_path = WP_PLUGIN_DIR . '/' . $path;
								break;
							}
						}
					}
				}
				if ( ! file_exists( $plugin_path ) ) {
					wp_send_json_error(
						array(
							'errorMessage' => __( 'Pro plugin must be installed manually. Purchase and download from the product page.', 'cryptocurrency-widgets-for-elementor' ),
						)
					);
				}
				if ( ! current_user_can( 'activate_plugin', $plugin_file ) ) {
					wp_send_json_error(
						array(
							'message' => __( 'Permission denied', 'cryptocurrency-widgets-for-elementor' ),
						)
					);
				}
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above.
				$pagenow      = isset( $_POST['pagenow'] ) ? sanitize_key( wp_unslash( $_POST['pagenow'] ) ) : '';
				$network_wide = is_multisite() && 'import' !== $pagenow;
				$result       = activate_plugin( $plugin_file, '', $network_wide );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error(
						array(
							'message' => $result->get_error_message(),
						)
					);
				}
				wp_send_json_success(
					array(
						'message'     => __( 'Plugin activated successfully', 'cryptocurrency-widgets-for-elementor' ),
						'activated'   => true,
						'plugin_slug' => $slug,
					)
				);
			}

			// Free plugins: install via WordPress.org API, then activate.
			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false ),
				)
			);

			if ( is_wp_error( $api ) ) {
				$status['errorMessage'] = $api->get_error_message();
				wp_send_json_error( $status );
			}

			$status['pluginName'] = $api->name;

			$skin     = new \WP_Ajax_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $api->download_link );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$status['debug'] = $skin->get_upgrade_messages();
			}

			if ( is_wp_error( $result ) ) {
				$status['errorCode']    = $result->get_error_code();
				$status['errorMessage'] = $result->get_error_message();
				wp_send_json_error( $status );
			}

			if ( is_wp_error( $skin->result ) ) {
				$msg = $skin->result->get_error_message();
				if ( 'Destination folder already exists.' === $msg ) {
					$install_status = install_plugin_install_status( $api );
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above.
					$pagenow       = isset( $_POST['pagenow'] ) ? sanitize_key( wp_unslash( $_POST['pagenow'] ) ) : '';
					$network_wide  = is_multisite() && 'import' !== $pagenow;
					if ( current_user_can( 'activate_plugin', $install_status['file'] ) ) {
						$activation_result = activate_plugin( $install_status['file'], '', $network_wide );
						if ( is_wp_error( $activation_result ) ) {
							$status['errorCode']    = $activation_result->get_error_code();
							$status['errorMessage'] = $activation_result->get_error_message();
							wp_send_json_error( $status );
						}
						$status['activated'] = true;
					}
					wp_send_json_success( $status );
				}
				$status['errorCode']    = $skin->result->get_error_code();
				$status['errorMessage'] = $skin->result->get_error_message();
				wp_send_json_error( $status );
			}

			if ( $skin->get_errors()->has_errors() ) {
				$status['errorMessage'] = $skin->get_error_messages();
				wp_send_json_error( $status );
			}

			if ( is_null( $result ) ) {
				global $wp_filesystem;
				$status['errorCode']    = 'unable_to_connect_to_filesystem';
				$status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.', 'cryptocurrency-widgets-for-elementor' );
				if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
					$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
				}
				wp_send_json_error( $status );
			}

			$install_status = install_plugin_install_status( $api );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above.
			$pagenow      = isset( $_POST['pagenow'] ) ? sanitize_key( wp_unslash( $_POST['pagenow'] ) ) : '';
			$network_wide = is_multisite() && 'import' !== $pagenow;

			if ( current_user_can( 'activate_plugin', $install_status['file'] ) && is_plugin_inactive( $install_status['file'] ) ) {
				$activation_result = activate_plugin( $install_status['file'], '', $network_wide );
				if ( is_wp_error( $activation_result ) ) {
					$status['errorCode']    = $activation_result->get_error_code();
					$status['errorMessage'] = $activation_result->get_error_message();
					wp_send_json_error( $status );
				}
				$status['activated'] = true;
			}
			wp_send_json_success( $status );
		}

		/**
		 * Register the main dashboard menu and submenu.
		 */
		public function init_plugins_dasboard_page() {
			add_menu_page(
				$this->menu_title,
				$this->menu_title,
				'manage_options',
				$this->main_menu_slug,
				array( $this, 'displayPluginAdminDashboard' ),
				$this->menu_icon,
				9
			);
			add_submenu_page(
				$this->main_menu_slug,
				__( 'Dashboard', 'cryptocurrency-widgets-for-elementor' ),
				__( 'Dashboard', 'cryptocurrency-widgets-for-elementor' ),
				'manage_options',
				$this->main_menu_slug,
				array( $this, 'displayPluginAdminDashboard' ),
				1
			);
		}

		/**
		 * Render the dashboard: load data, build activated/available/pro lists and output via templates.
		 */
		public function displayPluginAdminDashboard() {
			$tag         = $this->plugin_tag;
			$plugins     = $this->request_wp_plugins_data( $tag );
			$pro_plugins = $this->request_pro_plugins_data( $tag );
			$this->disable_free_plugins();

			$pro_plugin_slugs    = array_keys( $pro_plugins );
			$free_to_pro_mapping = array();
			if ( ! empty( $pro_plugins ) ) {
				foreach ( $pro_plugins as $slug => $data ) {
					if ( ! empty( $data['incompatible'] ) && 'false' !== $data['incompatible'] ) {
						$free_to_pro_mapping[ $data['incompatible'] ] = $slug;
					}
				}
			}

			$prefix           = 'ccew';
			$activated_addons = array();
			$available_addons = array();
			$pro_addons       = array();

			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( ! empty( $plugins ) ) {
				foreach ( $plugins as $plugin ) {
					$plugin_slug = $plugin['slug'];
					if ( in_array( $plugin_slug, $pro_plugin_slugs, true ) ) {
						continue;
					}

					if ( isset( $free_to_pro_mapping[ $plugin_slug ] ) ) {
						$pro_slug = $free_to_pro_mapping[ $plugin_slug ];
						$pro_dir  = WP_PLUGIN_DIR . '/' . $pro_slug;
						if ( file_exists( $pro_dir ) ) {
							$pro_active = false;
							$files      = glob( $pro_dir . '/*.php' );
							if ( ! empty( $files ) ) {
								foreach ( $files as $pf ) {
									if ( is_plugin_active( plugin_basename( $pf ) ) ) {
										$pro_active = true;
										break;
									}
								}
							}
							if ( $pro_active ) {
								continue;
							}
						}
					}

					$plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
					if ( file_exists( $plugin_dir ) ) {
						$plugin_files = glob( $plugin_dir . '/*.php' );
						$is_active    = false;
						$main_file    = '';
						foreach ( $plugin_files as $pf ) {
							$basename = plugin_basename( $pf );
							if ( empty( $main_file ) ) {
								$headers = get_file_data( $pf, array( 'Plugin Name' => 'Plugin Name' ) );
								if ( ! empty( $headers['Plugin Name'] ) ) {
									$main_file = $basename;
								}
							}
							if ( is_plugin_active( $basename ) ) {
								$is_active = true;
								$main_file = $basename;
								break;
							}
						}
						if ( ! empty( $main_file ) ) {
							$plugin['plugin_basename'] = $main_file;
							$path                      = WP_PLUGIN_DIR . '/' . $main_file;
							if ( file_exists( $path ) ) {
								$data = get_plugin_data( $path, false, false );
								if ( ! empty( $data['Version'] ) ) {
									$plugin['installed_version'] = $data['Version'];
								}
							}
						}
						$plugin['has_update']       = $this->check_plugin_update( $plugin_slug );
						$plugin['needs_activation'] = ! $is_active;
						if ( $is_active ) {
							$activated_addons[] = $plugin;
						} else {
							$available_addons[] = $plugin;
						}
					} else {
						$available_addons[] = $plugin;
					}
				}
			}

			if ( ! empty( $pro_plugins ) ) {
				foreach ( $pro_plugins as $plugin ) {
					$plugin_slug = $plugin['slug'];
					$has_buy     = ! empty( $plugin['buyLink'] );
					$is_pro      = ( strpos( $plugin_slug, '-pro' ) !== false ) || in_array( $plugin_slug, self::$pro_plugin_slugs, true );
					if ( ! $has_buy && ! $is_pro ) {
						continue;
					}
					$plugin_dir     = WP_PLUGIN_DIR . '/' . $plugin_slug;
					$used_free_dir  = false;
					$pro_name       = isset( $plugin['name'] ) ? trim( $plugin['name'] ) : '';
					$main_file      = '';
					$is_active      = false;
					if ( ! file_exists( $plugin_dir ) && $pro_name ) {
						$all_plugins     = get_plugins();
						$pro_name_lower  = strtolower( $pro_name );
						foreach ( $all_plugins as $p_path => $p_data ) {
							$p_name = isset( $p_data['Name'] ) ? trim( $p_data['Name'] ) : '';
							if ( ! $p_name ) {
								continue;
							}
							$exact_match = ( $p_name === $pro_name || strtolower( $p_name ) === $pro_name_lower );
							if ( ! $exact_match ) {
								continue;
							}
							$plugin_dir    = WP_PLUGIN_DIR . '/' . dirname( $p_path );
							$used_free_dir = false;
							$main_file     = $p_path;
							$is_active     = is_plugin_active( $p_path );
							break;
						}
					}
					if ( ! file_exists( $plugin_dir ) && ! empty( $plugin['incompatible'] ) && 'false' !== $plugin['incompatible'] ) {
						$free_dir = WP_PLUGIN_DIR . '/' . $plugin['incompatible'];
						if ( file_exists( $free_dir ) ) {
							$plugin_dir    = $free_dir;
							$used_free_dir = true;
						}
					}
					if ( file_exists( $plugin_dir ) ) {
						$plugin_files = glob( $plugin_dir . '/*.php' );
						if ( empty( $main_file ) ) {
							$is_active = false;
						}
						if ( empty( $main_file ) && $used_free_dir && ! empty( $plugin_files ) && $pro_name ) {
							$pro_name_lower = strtolower( $pro_name );
							foreach ( $plugin_files as $pf ) {
								$fdata = get_plugin_data( $pf, false, false );
								$fname = isset( $fdata['Name'] ) ? trim( $fdata['Name'] ) : '';
								$fbase = plugin_basename( $pf );
								$name_matches   = $fname && ( $fname === $pro_name || strtolower( $fname ) === $pro_name_lower );
								$file_looks_pro = strpos( $fbase, '-pro' ) !== false && stripos( $fname, 'Pro' ) !== false;
								if ( $name_matches || $file_looks_pro ) {
									$main_file = $fbase;
									$is_active = is_plugin_active( $fbase );
									break;
								}
							}
						}
						if ( empty( $main_file ) ) {
							foreach ( $plugin_files as $pf ) {
								$basename = plugin_basename( $pf );
								if ( empty( $main_file ) ) {
									$headers = get_file_data( $pf, array( 'Plugin Name' => 'Plugin Name' ) );
									if ( ! empty( $headers['Plugin Name'] ) ) {
										$main_file = $basename;
									}
								}
								if ( is_plugin_active( $basename ) ) {
									$is_active = true;
									$main_file = $basename;
									break;
								}
							}
						}
						if ( ! empty( $main_file ) ) {
							$plugin['plugin_basename'] = $main_file;
							$path                      = WP_PLUGIN_DIR . '/' . $main_file;
							$data                      = array();
							if ( file_exists( $path ) ) {
								$data = get_plugin_data( $path, false, false );
								if ( ! empty( $data['Version'] ) ) {
									$plugin['installed_version'] = $data['Version'];
								}
							}
							$plugin['has_update'] = $this->check_plugin_update( $plugin_slug );
							$installed_is_pro     = false;
							if ( $used_free_dir ) {
								$installed_is_pro = ! empty( $data['Name'] ) && ( $data['Name'] === $pro_name || ( strpos( $main_file, '-pro' ) !== false && stripos( $data['Name'], 'Pro' ) !== false ) );
							}
							if ( $used_free_dir && ! $installed_is_pro ) {
								$pro_addons[] = $plugin;
							} elseif ( $is_active ) {
								$activated_addons[] = $plugin;
							} else {
								$plugin['needs_activation']  = true;
								$plugin['is_pro_installed'] = true;
								$available_addons[]         = $plugin;
							}
						} else {
							$pro_addons[] = $plugin;
						}
					} else {
						$pro_addons[] = $plugin;
					}
				}
			}

			if ( ! empty( $activated_addons ) || ! empty( $available_addons ) || ! empty( $pro_addons ) ) {
				$this->render_modern_dashboard( $prefix, $activated_addons, $available_addons, $pro_addons );
			} else {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'No plugins data available at the moment.', 'cryptocurrency-widgets-for-elementor' ) . '</p></div>';
			}
		}

		/**
		 * Check if a plugin has an update available.
		 *
		 * @param string $plugin_slug Plugin directory slug.
		 * @return string|false New version string or false.
		 */
		public function check_plugin_update( $plugin_slug ) {
			$updates = get_site_transient( 'update_plugins' );
			if ( ! empty( $updates->response ) && is_array( $updates->response ) ) {
				foreach ( $updates->response as $file => $data ) {
					if ( strpos( $file, $plugin_slug ) !== false && isset( $data->new_version ) ) {
						return $data->new_version;
					}
				}
			}
			return false;
		}

		/**
		 * Render Modern Dashboard UI (Using Modular Include Files).
		 *
		 * @param string $prefix            CSS prefix.
		 * @param array  $activated_addons  Activated plugins.
		 * @param array  $available_addons  Available (install or activate) plugins.
		 * @param array  $pro_addons        Pro plugins not installed.
		 */
		public function render_modern_dashboard( $prefix, $activated_addons, $available_addons, $pro_addons ) {
			$dashboard_instance = $this;
			$prefix             = sanitize_key( $prefix );
			?>
			<div class="<?php echo esc_attr( $prefix ); ?>-dashboard-wrapper">
				<?php
				if ( ! self::$global_header_rendered ) {
					include $this->addon_dir . '/includes/dashboard-header.php';
					do_action( 'ccew_after_crypto_header' );
				}
				?>
				<div class="<?php echo esc_attr( $prefix ); ?>-main-grid">
					<?php
					include $this->addon_dir . '/includes/dashboard-page.php';
					include $this->addon_dir . '/includes/dashboard-sidebar.php';
					?>
				</div>
			</div>
			<?php
		}

		/**
		 * Get demo and docs URLs for a plugin.
		 *
		 * @param string $plugin_slug   Slug.
		 * @param bool   $is_pro_plugin Whether it is a pro plugin.
		 * @return array{ demo: string, docs: string }
		 */
		public function get_plugin_demo_docs_urls( $plugin_slug, $is_pro_plugin = false ) {
			$demo_url = 'https://cryptocurrencyplugins.com/demo/?utm_source=ccew_plugin&utm_medium=inside&utm_campaign=demo&utm_content=dashboard';
			$docs_url = 'https://cryptocurrencyplugins.com/docs/?utm_source=ccew_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard';

			if ( $is_pro_plugin ) {
				$pro = $this->request_pro_plugins_data();
				if ( isset( $pro[ $plugin_slug ] ) ) {
					$p = $pro[ $plugin_slug ];
					if ( ! empty( $p['demo_url'] ) ) {
						$demo_url = $p['demo_url'];
					}
					if ( ! empty( $p['docs_url'] ) ) {
						$docs_url = $p['docs_url'];
					}
				}
			} else {
				$free = $this->request_wp_plugins_data();
				if ( isset( $free[ $plugin_slug ] ) ) {
					$f = $free[ $plugin_slug ];
					if ( ! empty( $f['demo_url'] ) ) {
						$demo_url = $f['demo_url'];
					}
					if ( ! empty( $f['docs_url'] ) ) {
						$docs_url = $f['docs_url'];
					}
				}
			}
			return array(
				'demo' => esc_url( $demo_url ),
				'docs' => esc_url( $docs_url ),
			);
		}

		/**
		 * Output demo + docs links markup for a plugin card.
		 *
		 * @param string $prefix        CSS prefix.
		 * @param string $plugin_slug   Slug.
		 * @param bool   $is_pro_plugin Whether pro.
		 */
		private function render_plugin_card_demo_docs_links( $prefix, $plugin_slug, $is_pro_plugin ) {
			$urls = $this->get_plugin_demo_docs_urls( $plugin_slug, $is_pro_plugin );
			$demo = empty( $urls['demo'] ) ? 'https://cryptocurrencyplugins.com/demo/?utm_source=ccew_plugin&utm_medium=inside&utm_campaign=demo&utm_content=dashboard' : $urls['demo'];
			$docs = empty( $urls['docs'] ) ? 'https://cryptocurrencyplugins.com/docs/?utm_source=ccew_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard' : $urls['docs'];
			?>
			<div class="<?php echo esc_attr( $prefix ); ?>-card-links">
				<a href="<?php echo esc_url( $demo ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'View Demo', 'cryptocurrency-widgets-for-elementor' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor"><path d="M10.5 8a2.5 2.5 0 1 1-5 0a2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7a3.5 3.5 0 0 0 0 7"/></g></svg>
					<?php esc_html_e( 'Demo', 'cryptocurrency-widgets-for-elementor' ); ?>
				</a>
				<a href="<?php echo esc_url( $docs ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Documentation', 'cryptocurrency-widgets-for-elementor' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 56 56"><path fill="currentColor" d="M15.555 53.125h24.89c4.852 0 7.266-2.461 7.266-7.336V24.508H30.742c-3 0-4.406-1.43-4.406-4.43V2.875H15.555c-4.828 0-7.266 2.484-7.266 7.36v35.554c0 4.898 2.438 7.336 7.266 7.336m15.258-31.828h16.64c-.164-.961-.844-1.899-1.945-3.047L32.57 5.102c-1.078-1.125-2.062-1.805-3.047-1.97v16.9c0 .843.446 1.265 1.29 1.265m-11.836 13.36c-.961 0-1.641-.68-1.641-1.594c0-.915.68-1.594 1.64-1.594h18.07c.938 0 1.665.68 1.665 1.593c0 .915-.727 1.594-1.664 1.594Zm0 8.929c-.961 0-1.641-.68-1.641-1.594s.68-1.594 1.64-1.594h18.07c.938 0 1.665.68 1.665 1.594s-.727 1.594-1.664 1.594Z"/></svg>
					<?php esc_html_e( 'Docs', 'cryptocurrency-widgets-for-elementor' ); ?>
				</a>
			</div>
			<?php
		}

		/**
		 * Render a single plugin card (activated, available, or pro).
		 *
		 * @param string $prefix CSS prefix.
		 * @param array  $plugin Plugin data.
		 * @param string $type   'activated'|'available'|'pro'.
		 */
		public function render_plugin_card( $prefix, $plugin, $type = 'activated' ) {
			$prefix = sanitize_key( $prefix );
			$type   = sanitize_key( $type );

			$plugin_name = isset( $plugin['name'] ) ? sanitize_text_field( $plugin['name'] ) : '';
			$plugin_desc = isset( $plugin['desc'] ) ? wp_kses_post( $plugin['desc'] ) : '';
			$plugin_slug = isset( $plugin['slug'] ) ? sanitize_key( $plugin['slug'] ) : '';
			$plugin_logo = ! empty( $plugin['logo'] ) ? $plugin['logo'] : '';

			$has_update = isset( $plugin['has_update'] ) ? $plugin['has_update'] : false;
			$avail_ver  = isset( $plugin['latest_version'] ) ? $plugin['latest_version'] : ( isset( $plugin['version'] ) ? $plugin['version'] : '' );
			$show_ver   = isset( $plugin['installed_version'] ) ? sanitize_text_field( $plugin['installed_version'] ) : sanitize_text_field( $avail_ver );

			if ( empty( $plugin_name ) || empty( $plugin_slug ) ) {
				return;
			}

			$is_pro = ( 'pro' === $type ) || ( ! empty( $plugin['is_pro_installed'] ) ) || ( 'activated' === $type && ( strpos( $plugin_slug, '-pro' ) !== false || in_array( $plugin_slug, self::$pro_plugin_slugs, true ) ) );
			?>
			<div class="<?php echo esc_attr( $prefix ); ?>-card">
				<?php if ( ! empty( $has_update ) ) : ?>
					<div title="<?php esc_attr_e( 'Update available', 'cryptocurrency-widgets-for-elementor' ); ?>" class="<?php echo esc_attr( $prefix ); ?>-pulse-wrapper"></div>
					<div title="<?php esc_attr_e( 'Update available', 'cryptocurrency-widgets-for-elementor' ); ?>" class="<?php echo esc_attr( $prefix ); ?>-notification-dot"></div>
				<?php endif; ?>
				<?php if ( $is_pro ) : ?>
					<span class="<?php echo esc_attr( $prefix ); ?>-badge <?php echo esc_attr( $prefix ); ?>-badge-premium"><?php esc_html_e( 'Pro', 'cryptocurrency-widgets-for-elementor' ); ?></span>
				<?php endif; ?>
				<div class="<?php echo esc_attr( $prefix ); ?>-icon-box">
					<img src="<?php echo esc_url( $plugin_logo ); ?>" alt="<?php echo esc_attr( $plugin_name ); ?>">
				</div>
				<div class="<?php echo esc_attr( $prefix ); ?>-info">
					<h3><?php echo esc_html( $plugin_name ); ?></h3>
					<p><?php echo esc_html( $plugin_desc ); ?></p>
					<?php if ( 'activated' === $type ) : ?>
						<div class="<?php echo esc_attr( $prefix ); ?>-badge-group">
							<div class="<?php echo esc_attr( $prefix ); ?>-active-update">
								<span class="<?php echo esc_attr( $prefix ); ?>-badge <?php echo esc_attr( $prefix ); ?>-badge-active"><?php esc_html_e( 'Active', 'cryptocurrency-widgets-for-elementor' ); ?></span>
								<?php if ( $show_ver ) : ?>
									<span class="<?php echo esc_attr( $prefix ); ?>-badge <?php echo esc_attr( $prefix ); ?>-badge-version">v <?php echo esc_html( $show_ver ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( 'pro' !== $type ) : ?>
								<?php $this->render_plugin_card_demo_docs_links( $prefix, $plugin_slug, $is_pro ); ?>
							<?php endif; ?>
						</div>
					<?php elseif ( 'available' === $type ) : ?>
						<div class="<?php echo esc_attr( $prefix ); ?>-card-footer">
							<?php
							$needs_activation = ! empty( $plugin['needs_activation'] ) && ! empty( $plugin['plugin_basename'] );
							$install_nonce    = wp_create_nonce( 'ccew-plugins-download' );
							?>
							<button type="button"
								class="button <?php echo esc_attr( $prefix ); ?>-button-primary <?php echo esc_attr( $prefix ); ?>-install-plugin <?php echo $needs_activation ? esc_attr( $prefix ) . '-btn-activate' : esc_attr( $prefix ) . '-btn-install'; ?>"
								data-slug="<?php echo esc_attr( $plugin_slug ); ?>"
								data-nonce="<?php echo esc_attr( $install_nonce ); ?>">
								<?php echo $needs_activation ? esc_html__( 'Activate Now', 'cryptocurrency-widgets-for-elementor' ) : esc_html__( 'Install Now', 'cryptocurrency-widgets-for-elementor' ); ?>
							</button>
							<?php $this->render_plugin_card_demo_docs_links( $prefix, $plugin_slug, $is_pro ); ?>
						</div>
					<?php elseif ( 'pro' === $type ) : ?>
						<div class="<?php echo esc_attr( $prefix ); ?>-card-footer">
							<a href="<?php echo esc_url( isset( $plugin['buyLink'] ) ? $plugin['buyLink'] : '#' ); ?>" target="_blank" rel="noopener" class="button <?php echo esc_attr( $prefix ); ?>-button-primary <?php echo esc_attr( $prefix ); ?>-btn-buy">
								<?php esc_html_e( 'Buy Pro', 'cryptocurrency-widgets-for-elementor' ); ?>
							</a>
							<?php $this->render_plugin_card_demo_docs_links( $prefix, $plugin_slug, true ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}

		/**
		 * Enqueue dashboard CSS/JS and localize script.
		 * CSS is enqueued on all admin pages so the Addons menu icon stays styled;
		 * JS only on crypto addon pages.
		 */
		public function enqueue_required_scripts() {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_style( 'cool-plugins-crypto-addon', plugin_dir_url( __FILE__ ) . 'assets/css/styles.css', null, null, 'all' );

			if ( ! function_exists( 'ccew_is_crypto_addon_page' ) || ! ccew_is_crypto_addon_page() ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			if ( $page === $this->main_menu_slug ) {
				// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				wp_enqueue_script( 'cool-plugins-crypto-addon', plugin_dir_url( __FILE__ ) . 'assets/js/script.js', array( 'jquery' ), null, true );

				if ( ! function_exists( 'is_plugin_active' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				wp_localize_script(
					'cool-plugins-crypto-addon',
					'cp_events',
					array(
						'ajax_url'        => admin_url( 'admin-ajax.php' ),
						'plugin_tag'      => $this->plugin_tag,
						'prefix'          => 'ccew',
						'install_action'  => 'ccew_dashboard_install_plugin',
						'install_nonce'   => wp_create_nonce( 'ccew-plugins-download' ),
						'activated_label' => __( 'Activated', 'cryptocurrency-widgets-for-elementor' ),
						'woocommerce_active'       => function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce/woocommerce.php' ),
						'woocommerce_slugs'        => array_values( self::$woocommerce_dependent_slugs ),
						'woocommerce_required_msg' => __( 'WooCommerce must be installed and active before you can install or activate this plugin.', 'cryptocurrency-widgets-for-elementor' ),
					)
				);
			}
		}

		/**
		 * Populate disable_plugins from pro list (free_version => pro slug).
		 */
		public function disable_free_plugins() {
			if ( ! empty( $this->pro_plugins ) && is_array( $this->pro_plugins ) ) {
				foreach ( $this->pro_plugins as $plugin ) {
					if ( ! empty( $plugin['incompatible'] ) && 'false' !== $plugin['incompatible'] ) {
						$this->disable_plugins[ $plugin['incompatible'] ] = array( 'pro' => $plugin['slug'] );
					}
				}
			}
		}

		/**
		 * Load plugins data from JSON fallback file (no external API).
		 *
		 * @param string $type 'free'|'pro'.
		 * @return array
		 */
		private function load_json_fallback( $type = 'free' ) {
			$json_file = $this->addon_dir . '/data/' . $type . '-plugins.json';
			if ( ! file_exists( $json_file ) ) {
				return array();
			}

			$json_content = file_get_contents( $json_file );

			$placeholders = array(
				'{{CCEW_VERSION}}' => 'CCEW_VERSION',
			);
			foreach ( $placeholders as $placeholder => $constant_name ) {
				if ( defined( $constant_name ) ) {
					$json_content = str_replace( $placeholder, constant( $constant_name ), $json_content );
				}
			}

			$plugin_info = json_decode( $json_content, true );
			if ( empty( $plugin_info ) || ! is_array( $plugin_info ) ) {
				return array();
			}

			$plugins_data = array();
			foreach ( $plugin_info as $plugin ) {
				if ( empty( $plugin['slug'] ) ) {
					continue;
				}
				$json_image_url = isset( $plugin['image_url'] ) ? $plugin['image_url'] : '';
				$image_url      = '';
				if ( ! empty( $json_image_url ) ) {
					if ( strpos( $json_image_url, 'http' ) === 0 ) {
						$image_url = $json_image_url;
					} else {
						$image_url = plugin_dir_url( $this->addon_file ) . 'assets/images/' . $json_image_url;
					}
				} else {
					$image_url = '';
				}
				$static_version = isset( $plugin['version'] ) ? $plugin['version'] : '';
				$latest_version = isset( $plugin['latest_version'] ) ? $plugin['latest_version'] : $static_version;
				$data           = array(
					'name'           => isset( $plugin['name'] ) ? $plugin['name'] : '',
					'logo'           => $image_url,
					'slug'           => $plugin['slug'],
					'desc'           => isset( $plugin['info'] ) ? $plugin['info'] : '',
					'version'        => $static_version,
					'latest_version' => $latest_version,
					'demo_url'       => isset( $plugin['demo_url'] ) ? $plugin['demo_url'] : '',
					'docs_url'       => isset( $plugin['docs_url'] ) ? $plugin['docs_url'] : '',
				);
				if ( 'pro' === $type ) {
					$data['buyLink']       = isset( $plugin['buy_url'] ) ? $plugin['buy_url'] : '';
					$data['download_link'] = null;
					$data['incompatible']  = isset( $plugin['free_version'] ) ? $plugin['free_version'] : null;
					$data['main_file']     = isset( $plugin['main_file'] ) ? $plugin['main_file'] : '';
					if ( ! empty( $plugin['free_version'] ) && 'false' !== $plugin['free_version'] ) {
						$this->disable_plugins[ $plugin['free_version'] ] = array( 'pro' => $plugin['slug'] );
					}
				} else {
					$data['tags']          = isset( $plugin['tag'] ) ? $plugin['tag'] : '';
					$data['download_link'] = isset( $plugin['download_url'] ) ? $plugin['download_url'] : '';
				}
				$plugins_data[ $plugin['slug'] ] = $data;
			}
			return $plugins_data;
		}

		/**
		 * Get pro plugins data (from JSON, cached in transient/option).
		 *
		 * @param string|null $tag Optional tag filter.
		 * @return array
		 */
		public function request_pro_plugins_data( $tag = null ) {
			$trans_name  = $this->main_menu_slug . '_pro_api_cache' . $this->plugin_tag;
			$option_name = $this->main_menu_slug . '-' . $this->plugin_tag . '-pro';
			$ver_option  = $this->main_menu_slug . '_' . $this->plugin_tag . '_pro_json_sig';

			$json_file  = $this->addon_dir . '/data/pro-plugins.json';
			$json_sig   = file_exists( $json_file ) ? (string) filemtime( $json_file ) : '';
			$stored_sig = (string) get_option( $ver_option, '' );
			if ( $json_sig !== '' && $stored_sig !== $json_sig ) {
				delete_transient( $trans_name );
				delete_option( $option_name );
			}

			$this->pro_plugins = $this->filter_discontinued_pro_addons( $this->load_json_fallback( 'pro' ) );
			if ( ! empty( $this->pro_plugins ) && is_array( $this->pro_plugins ) ) {
				set_transient( $trans_name, $this->pro_plugins, DAY_IN_SECONDS );
				update_option( $option_name, $this->pro_plugins );
				if ( $json_sig !== '' ) {
					update_option( $ver_option, $json_sig );
				}
				return $this->pro_plugins;
			}

			$cached = get_transient( $trans_name );
			if ( false !== $cached && ! empty( $cached ) && is_array( $cached ) ) {
				$this->pro_plugins = $this->filter_discontinued_pro_addons( $cached );
				return $this->pro_plugins;
			}
			if ( get_option( $option_name, false ) ) {
				$this->pro_plugins = $this->filter_discontinued_pro_addons( get_option( $option_name ) );
				return $this->pro_plugins;
			}
			return $this->pro_plugins;
		}

		/**
		 * Get free plugins data (from JSON, cached in transient/option).
		 *
		 * @param string|null $tag Optional tag filter.
		 * @return array
		 */
		public function request_wp_plugins_data( $tag = null ) {
			$trans_name  = $this->main_menu_slug . '_api_cache' . $this->plugin_tag;
			$option_name = $this->main_menu_slug . '-' . $this->plugin_tag;
			$ver_option  = $this->main_menu_slug . '_' . $this->plugin_tag . '_free_json_sig';

			$json_file  = $this->addon_dir . '/data/free-plugins.json';
			$json_sig   = file_exists( $json_file ) ? (string) filemtime( $json_file ) : '';
			$stored_sig = (string) get_option( $ver_option, '' );
			if ( $json_sig !== '' && $stored_sig !== $json_sig ) {
				delete_transient( $trans_name );
				delete_option( $option_name );
			}

			$all_plugins = $this->filter_discontinued_pro_addons( $this->load_json_fallback( 'free' ) );
			if ( ! empty( $all_plugins ) && is_array( $all_plugins ) ) {
				set_transient( $trans_name, $all_plugins, DAY_IN_SECONDS );
				update_option( $option_name, $all_plugins );
				if ( $json_sig !== '' ) {
					update_option( $ver_option, $json_sig );
				}
				return $all_plugins;
			}

			$cached = get_transient( $trans_name );
			if ( false !== $cached && ! empty( $cached ) && is_array( $cached ) ) {
				return $this->filter_discontinued_pro_addons( $cached );
			}
			if ( get_option( $option_name, false ) ) {
				return $this->filter_discontinued_pro_addons( get_option( $option_name ) );
			}
			return array();
		}

		/**
		 * Remove discontinued Pro addons from a plugins array, regardless of source (JSON, transient, or option).
		 *
		 * @param array $plugins Raw plugins array (expected to be keyed by slug).
		 * @return array Filtered plugins array.
		 */
		private function filter_discontinued_pro_addons( $plugins ) {
			if ( empty( $plugins ) || ! is_array( $plugins ) ) {
				return array();
			}
			$filtered = array();
			foreach ( $plugins as $slug => $plugin ) {
				$slug_key = is_string( $slug ) ? $slug : ( isset( $plugin['slug'] ) ? $plugin['slug'] : '' );
				if ( $slug_key && in_array( $slug_key, self::$discontinued_pro_slugs, true ) ) {
					continue;
				}
				$key              = $slug_key ? $slug_key : $slug;
				$filtered[ $key ] = $plugin;
			}
			return $filtered;
		}

	}

	/**
	 * Initialize the main dashboard class with all required parameters.
	 *
	 * @param string $tag                  Plugin tag.
	 * @param string $settings_page_slug   Menu slug.
	 * @param string $dashboard_heading    Heading.
	 * @param string $main_menu_title      Menu title.
	 * @param string $icon                 Icon URL or dashicon.
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	function cool_plugins_crypto_addon_settings_page( $tag, $settings_page_slug, $dashboard_heading, $main_menu_title, $icon ) {
		$page = cool_plugins_crypto_addons::init();
		$page->show_plugins( $tag, $settings_page_slug, $dashboard_heading, $main_menu_title, $icon );
	}
}

