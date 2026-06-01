<?php
/**
 * Dashboard Sidebar Template (Crypto)
 *
 * Variables available:
 *
 * @var string $prefix              CSS prefix (e.g. 'ccew')
 * @var object $dashboard_instance  Main class instance (optional; provides addon_file for asset URLs)
 *
 * Usage:
 * $prefix = 'ccew';
 * include 'dashboard-sidebar.php';
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $prefix ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$prefix = 'ccew';
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$prefix = sanitize_key( $prefix );

$dashboard_instance = isset( $dashboard_instance ) ? $dashboard_instance : null;
$addon_file         = ( $dashboard_instance && isset( $dashboard_instance->addon_file ) ) ? $dashboard_instance->addon_file : __FILE__;
$support_url        = 'https://cryptocurrencyplugins.com/support/?utm_source=ccew_plugin&utm_medium=inside&utm_campaign=support&utm_content=dashboard';
$pro_url            = 'https://cryptocurrencyplugins.com/?utm_source=ccew_plugin&utm_medium=inside&utm_campaign=pro&utm_content=dashboard';

// Decide which review URL to use based on whether a Pro crypto plugin is active.
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$pro_slugs   = array(
	'cryptocurrency-price-ticker-widget-pro',
	'cryptocurrency-donation-box-pro',
	'cryptocurrency-exchanges-list-pro',
	'crypto-ico-list-widget-pro',
	'pay-with-metamask-for-woocommerce-pro',
	'blockchain-explorer-pro',
);
$pro_active  = false;
if ( function_exists( 'get_plugins' ) ) {
	$all_plugins = get_plugins();
	foreach ( $all_plugins as $path => $data ) {
		$dir = dirname( $path );
		if ( in_array( $dir, $pro_slugs, true ) && is_plugin_active( $path ) ) {
			$pro_active = true;
			break;
		}
	}
}

if ( $pro_active ) {
	$reviews_url        = 'https://www.trustpilot.com/review/coolplugins.net';
	$reviews_heading    = __( 'LOVING OUR PLUGINS?', 'cryptocurrency-price-ticker-widget' );
	$reviews_text       = __( 'Review us on Trustpilot and share your feedback with the community.', 'cryptocurrency-price-ticker-widget' );
	$reviews_link_label = __( 'Rate us on trustpilot.com', 'cryptocurrency-price-ticker-widget' );
	$reviews_image      = CCPWF_URL . 'admin/addon-dashboard-page/assets/images/crypto-trustpilot.svg';
} else {
	$reviews_url        = 'https://wordpress.org/support/plugin/cryptocurrency-price-ticker-widget/reviews/#new-post';
	$reviews_heading    = __( 'LOVING OUR PLUGIN?', 'cryptocurrency-price-ticker-widget' );
	$reviews_text       = __( 'Review us on WordPress.org and share your feedback with the community.', 'cryptocurrency-price-ticker-widget' );
	$reviews_link_label = __( 'Rate us on WordPress.org', 'cryptocurrency-price-ticker-widget' );
	$reviews_image      = CCPWF_URL . 'admin/addon-dashboard-page/assets/images/crypto-trustpilot.svg';
}
?>
<aside class="<?php echo esc_attr( $prefix ); ?>-sidebar">

	<!-- Premium Support -->
	<div class="<?php echo esc_attr( $prefix ); ?>-sidebar-card <?php echo esc_attr( $prefix ); ?>-premium-support">
		<div class="<?php echo esc_attr( $prefix ); ?>-sidebar-header">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2C6.486 2 2 6.486 2 12v4.143C2 17.167 2.897 18 4 18h1a1 1 0 0 0 1-1v-5.143a1 1 0 0 0-1-1h-.908C4.648 6.987 7.978 4 12 4s7.352 2.987 7.908 6.857H19a1 1 0 0 0-1 1V18c0 1.103-.897 2-2 2h-2v-1h-4v3h6c2.206 0 4-1.794 4-4c1.103 0 2-.833 2-1.857V12c0-5.514-4.486-10-10-10"/></svg>
			<h3><?php echo esc_html__( 'PREMIUM SUPPORT', 'cryptocurrency-price-ticker-widget' ); ?></h3>
		</div>
		<ul class="<?php echo esc_attr( $prefix ); ?>-feature-list">
			<li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true"><defs><mask id="<?php echo esc_attr( $prefix ); ?>-support-check1"><g fill="none" stroke-linejoin="round" stroke-width="4"><path fill="#fff" stroke="#fff" d="M24 44a19.94 19.94 0 0 0 14.142-5.858A19.94 19.94 0 0 0 44 24a19.94 19.94 0 0 0-5.858-14.142A19.94 19.94 0 0 0 24 4A19.94 19.94 0 0 0 9.858 9.858A19.94 19.94 0 0 0 4 24a19.94 19.94 0 0 0 5.858 14.142A19.94 19.94 0 0 0 24 44Z"/><path stroke="#000" stroke-linecap="round" d="m16 24l6 6l12-12"/></g></mask></defs><path fill="currentColor" d="M0 0h48v48H0z" mask="url(#<?php echo esc_attr( $prefix ); ?>-support-check1)"/></svg> <?php echo esc_html__( 'Priority fast support.', 'cryptocurrency-price-ticker-widget' ); ?></li>
			<li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true"><defs><mask id="<?php echo esc_attr( $prefix ); ?>-support-check2"><g fill="none" stroke-linejoin="round" stroke-width="4"><path fill="#fff" stroke="#fff" d="M24 44a19.94 19.94 0 0 0 14.142-5.858A19.94 19.94 0 0 0 44 24a19.94 19.94 0 0 0-5.858-14.142A19.94 19.94 0 0 0 24 4A19.94 19.94 0 0 0 9.858 9.858A19.94 19.94 0 0 0 4 24a19.94 19.94 0 0 0 5.858 14.142A19.94 19.94 0 0 0 24 44Z"/><path stroke="#000" stroke-linecap="round" d="m16 24l6 6l12-12"/></g></mask></defs><path fill="currentColor" d="M0 0h48v48H0z" mask="url(#<?php echo esc_attr( $prefix ); ?>-support-check2)"/></svg> <?php echo esc_html__( 'Mon–Fri, 9:30 AM–6:30 PM IST.', 'cryptocurrency-price-ticker-widget' ); ?></li>
			<li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true"><defs><mask id="<?php echo esc_attr( $prefix ); ?>-support-check3"><g fill="none" stroke-linejoin="round" stroke-width="4"><path fill="#fff" stroke="#fff" d="M24 44a19.94 19.94 0 0 0 14.142-5.858A19.94 19.94 0 0 0 44 24a19.94 19.94 0 0 0-5.858-14.142A19.94 19.94 0 0 0 24 4A19.94 19.94 0 0 0 9.858 9.858A19.94 19.94 0 0 0 4 24a19.94 19.94 0 0 0 5.858 14.142A19.94 19.94 0 0 0 24 44Z"/><path stroke="#000" stroke-linecap="round" d="m16 24l6 6l12-12"/></g></mask></defs><path fill="currentColor" d="M0 0h48v48H0z" mask="url(#<?php echo esc_attr( $prefix ); ?>-support-check3)"/></svg> <?php echo esc_html__( 'Aim to resolve issues in 24 hrs.', 'cryptocurrency-price-ticker-widget' ); ?></li>
		</ul>
		<a href="<?php echo esc_url( $support_url ); ?>" target="_blank" rel="noopener" class="button <?php echo esc_attr( $prefix ); ?>-button-primary <?php echo esc_attr( $prefix ); ?>-btn-full">
			<?php echo esc_html__( 'Contact Support', 'cryptocurrency-price-ticker-widget' ); ?>
		</a>
	</div>

	<!-- Rate us / Reviews -->
	<div class="<?php echo esc_attr( $prefix ); ?>-sidebar-card <?php echo esc_attr( $prefix ); ?>-trustpilot-rating">
		<div class="<?php echo esc_attr( $prefix ); ?>-sidebar-header">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m12 21.35l-1.45-1.32C5.4 15.36 2 12.27 2 8.5C2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.08C13.09 3.81 14.76 3 16.5 3C19.58 3 22 5.41 22 8.5c0 3.77-3.4 6.86-8.55 11.53z"/></svg>
			<h3><?php echo esc_html( $reviews_heading ); ?></h3>
		</div>
		<div class="<?php echo esc_attr( $prefix ); ?>-trustpilot">
			<div class="<?php echo esc_attr( $prefix ); ?>-stars">
				<a href="<?php echo esc_url( $reviews_url ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $reviews_image ); ?>" alt="<?php esc_attr_e( 'Rating', 'cryptocurrency-price-ticker-widget' ); ?>"></a>
			</div>
			<p class="<?php echo esc_attr( $prefix ); ?>-sidebar-text"><?php echo esc_html( $reviews_text ); ?></p>
			<a href="<?php echo esc_url( $reviews_url ); ?>" target="_blank" rel="noopener" class="<?php echo esc_attr( $prefix ); ?>-trustpilot-link">
				<?php echo esc_html( $reviews_link_label ); ?> <span class="dashicons dashicons-external"></span>
			</a>
		</div>
	</div>

</aside>