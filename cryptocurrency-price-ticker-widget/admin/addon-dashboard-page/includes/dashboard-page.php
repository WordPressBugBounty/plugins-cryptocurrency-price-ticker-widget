<?php
/**
 * Dashboard Main Content - Plugin Cards Template (Crypto)
 *
 * Variables required:
 *
 * @var string $prefix              CSS prefix (e.g. 'ccew')
 * @var array  $activated_addons    Array of activated plugins
 * @var array  $available_addons    Array of available plugins
 * @var array  $pro_addons          Array of PRO plugins
 * @var object $dashboard_instance  Instance of dashboard class with render_plugin_card method
 *
 * Usage:
 * include 'dashboard-page.php';
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

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$activated_addons = isset( $activated_addons ) && is_array( $activated_addons ) ? $activated_addons : array();
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$available_addons = isset( $available_addons ) && is_array( $available_addons ) ? $available_addons : array();
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$pro_addons = isset( $pro_addons ) && is_array( $pro_addons ) ? $pro_addons : array();

$dashboard_instance = isset( $dashboard_instance ) ? $dashboard_instance : null;
?>
<div class="<?php echo esc_attr( $prefix ); ?>-content">

	<?php if ( ! empty( $activated_addons ) ) : ?>
	<!-- Currently Activated Addons -->
	<div class="<?php echo esc_attr( $prefix ); ?>-section-title">
		<span class="<?php echo esc_attr( $prefix ); ?>-indicator" style="background: var(--<?php echo esc_attr( $prefix ); ?>-success);"></span>
		<?php echo esc_html__( 'Currently Activated Crypto Addons', 'cryptocurrency-widgets-for-elementor' ); ?>
		<span class="<?php echo esc_attr( $prefix ); ?>-title-count"><?php echo esc_html( count( $activated_addons ) . ' ' . __( 'Active Addons', 'cryptocurrency-widgets-for-elementor' ) ); ?></span>
	</div>
	<div class="<?php echo esc_attr( $prefix ); ?>-cards-container">
		<?php
		foreach ( $activated_addons as $plugin ) {
			if ( $dashboard_instance && method_exists( $dashboard_instance, 'render_plugin_card' ) ) {
				$dashboard_instance->render_plugin_card( $prefix, $plugin, 'activated' );
			}
		}
		?>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $pro_addons ) ) : ?>
	<!-- Premium Addons -->
	<div class="<?php echo esc_attr( $prefix ); ?>-section-title">
		<span class="<?php echo esc_attr( $prefix ); ?>-indicator" style="background: #000;"></span>
		<?php echo esc_html__( 'Premium Crypto Plugins', 'cryptocurrency-widgets-for-elementor' ); ?>
	</div>
	<div class="<?php echo esc_attr( $prefix ); ?>-cards-container <?php echo esc_attr( $prefix ); ?>-premium-addons">
		<?php
		foreach ( $pro_addons as $plugin ) {
			if ( $dashboard_instance && method_exists( $dashboard_instance, 'render_plugin_card' ) ) {
				$dashboard_instance->render_plugin_card( $prefix, $plugin, 'pro' );
			}
		}
		?>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $available_addons ) ) : ?>
	<!-- Available Addons -->
	<div class="<?php echo esc_attr( $prefix ); ?>-section-title">
		<span class="<?php echo esc_attr( $prefix ); ?>-indicator" style="background: #94a3b8;"></span>
		<?php echo esc_html__( 'Available Crypto Addons', 'cryptocurrency-widgets-for-elementor' ); ?>
	</div>
	<div class="<?php echo esc_attr( $prefix ); ?>-cards-container">
		<?php
		foreach ( $available_addons as $plugin ) {
			if ( $dashboard_instance && method_exists( $dashboard_instance, 'render_plugin_card' ) ) {
				$dashboard_instance->render_plugin_card( $prefix, $plugin, 'available' );
			}
		}
		?>
	</div>
	<?php endif; ?>

</div>
