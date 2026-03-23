<?php
/**
 * Universal Header Template for All Crypto Addon Pages
 *
 * Can be used for: Dashboard or any other crypto addons page.
 *
 * Variables available:
 *
 * @var string $prefix              CSS prefix (default: 'ccew')
 * @var bool   $show_wrapper        Show wrapper div (default: false for dashboard, true for others)
 *
 * Usage:
 *
 * For Dashboard (show_wrapper false; we output #cool-plugins-container):
 * include 'dashboard-header.php';
 *
 * For other pages (with wrapper):
 * $show_wrapper = true;
 * include 'dashboard-header.php';
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $prefix ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$prefix = 'ccew';
}
if ( ! isset( $show_wrapper ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$show_wrapper = false;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$prefix = sanitize_key( $prefix );

$dashboard_instance = isset( $dashboard_instance ) ? $dashboard_instance : null;
$docs_url           = 'https://cryptocurrencyplugins.com/docs/?utm_source=ccew_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard';
$demos_url          = 'https://cryptocurrencyplugins.com/demo/?utm_source=ccew_plugin&utm_medium=inside&utm_campaign=demo&utm_content=dashboard';
$heading            = ( $dashboard_instance && isset( $dashboard_instance->dashboar_page_heading ) ) ? $dashboard_instance->dashboar_page_heading : __( 'Cryptocurrency Plugins', 'cryptocurrency-widgets-for-elementor' );
// Use local CryptocurrencyPlugins.com logo in the header.
$header_icon_url    = CCPWF_URL . 'assets/image/CryptocurrencyPlugins-logo.svg';
?>
<?php if ( $show_wrapper ) : ?>
<div class="<?php echo esc_attr( $prefix ); ?>-dashboard-wrapper">
<?php endif; ?>

<header class="<?php echo esc_attr( $prefix ); ?>-top-header">
	<div class="<?php echo esc_attr( $prefix ); ?>-header-left">
		<div class="<?php echo esc_attr( $prefix ); ?>-header-img-box">
			<img src="<?php echo esc_url( $header_icon_url ); ?>" alt="<?php esc_attr_e( 'Cryptocurrency Plugins', 'cryptocurrency-widgets-for-elementor' ); ?>">
		</div>
		<!-- <h1><?php echo esc_html( $heading ); ?></h1> -->
	</div>
	<div class="<?php echo esc_attr( $prefix ); ?>-header-right">
		<a href="<?php echo esc_url( $demos_url ); ?>" target="_blank" rel="noopener" class="<?php echo esc_attr( $prefix ); ?>-btn <?php echo esc_attr( $prefix ); ?>-btn-outline">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true"><g fill="currentColor"><path d="M10.5 8a2.5 2.5 0 1 1-5 0a2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7a3.5 3.5 0 0 0 0 7"/></g></svg>
			<?php echo esc_html__( 'View Demos', 'cryptocurrency-widgets-for-elementor' ); ?>
		</a>
		<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener" class="<?php echo esc_attr( $prefix ); ?>-btn <?php echo esc_attr( $prefix ); ?>-btn-primary">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 56 56" aria-hidden="true"><path fill="currentColor" d="M15.555 53.125h24.89c4.852 0 7.266-2.461 7.266-7.336V24.508H30.742c-3 0-4.406-1.43-4.406-4.43V2.875H15.555c-4.828 0-7.266 2.484-7.266 7.36v35.554c0 4.898 2.438 7.336 7.266 7.336m15.258-31.828h16.64c-.164-.961-.844-1.899-1.945-3.047L32.57 5.102c-1.078-1.125-2.062-1.805-3.047-1.97v16.9c0 .843.446 1.265 1.29 1.265m-11.836 13.36c-.961 0-1.641-.68-1.641-1.594c0-.915.68-1.594 1.64-1.594h18.07c.938 0 1.665.68 1.665 1.593c0 .915-.727 1.594-1.664 1.594Zm0 8.929c-.961 0-1.641-.68-1.641-1.594s.68-1.594 1.64-1.594h18.07c.938 0 1.665.68 1.665 1.594s-.727 1.594-1.664 1.594Z"/></svg>
			<?php echo esc_html__( 'Check Docs', 'cryptocurrency-widgets-for-elementor' ); ?>
		</a>
	</div>
</header>

<?php if ( $show_wrapper ) : ?>
<div class="<?php echo esc_attr( $prefix ); ?>-main-content-wrapper">
<?php endif; ?>