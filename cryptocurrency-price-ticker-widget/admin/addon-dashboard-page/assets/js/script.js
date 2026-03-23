jQuery(document).ready(function ($) {

	var $allPluginBtns = function () {
		return $('.ccew-install-plugin, .cool-plugins-addon.plugin-downloader, .cool-plugins-addon.plugin-activator');
	};

	function disableAllBtns() {
		$allPluginBtns().not('[disabled]').prop('disabled', true).addClass('ccew-btn-processing');
	}

	function enableAllBtns() {
		$allPluginBtns().prop('disabled', false).removeClass('ccew-btn-processing');
	}

	// Single action: install or activate (WordPress core installer; backend handles both).
	$(document).on('click', '.ccew-install-plugin, .cool-plugins-addon.plugin-downloader, .cool-plugins-addon.plugin-activator', function () {
		var $btn = $(this);
		if ($btn.prop('disabled')) {
			return;
		}
		// Use attr() so slug always matches the DOM (jQuery .data() can cache / coerce).
		var slug = $btn.attr('data-slug') || $btn.attr('data-plugin-slug') || $btn.data('slug');
		var nonce = (typeof cp_events !== 'undefined' && cp_events.install_nonce) ? cp_events.install_nonce : $btn.attr('data-nonce') || $btn.data('nonce') || $btn.attr('data-action-nonce');
		var action = (typeof cp_events !== 'undefined' && cp_events.install_action) ? cp_events.install_action : 'ccew_dashboard_install_plugin';

		if (!slug || !nonce) {
			return;
		}

		// Server-rendered: no AJAX, no "Activating…" / "Installed!" when WooCommerce is inactive (reliable vs cp_events/class checks).
		if ($btn.attr('data-ccew-block-wc-activate') === '1') {
			return;
		}

		// Same pattern as Timeline + Divi: allow "Install Now"; block "Activate Now" when WooCommerce is inactive.
		var isActivateBtn = $btn.hasClass('ccew-btn-activate') || $btn.is('[class*="-btn-activate"]');
		if (isActivateBtn && typeof cp_events !== 'undefined' && cp_events.woocommerce_active !== true && Array.isArray(cp_events.woocommerce_slugs) && cp_events.woocommerce_slugs.indexOf(slug) !== -1) {
			return;
		}

		var ajaxUrl = (typeof cp_events !== 'undefined' && cp_events.ajax_url) ? cp_events.ajax_url : '';
		if (!ajaxUrl) {
			return;
		}

		// Disable all plugin buttons while the request is in flight.
		disableAllBtns();
		$btn.text($btn.hasClass('ccew-btn-activate') ? 'Activating...' : 'Installing...');

		// Use 'text' and parse JSON manually so leading output (BOM/whitespace/notices) doesn't break the first response.
		$.ajax({
			type: 'POST',
			url: ajaxUrl,
			dataType: 'text',
			data: {
				action: action,
				wp_nonce: nonce,
				slug: slug,
				pagenow: typeof window.pagenow !== 'undefined' ? window.pagenow : ''
			}
		}).done(function (raw) {
			var str = typeof raw === 'string' ? raw : '';
			// Some plugins redirect on activation (e.g. to a welcome page). The XHR then gets HTML instead of JSON.
			// If we got a large HTML response, activation likely succeeded — reload to show updated state.
			if (str.length > 2000) {
				var trim = str.trim();
				if (trim.indexOf('<!') === 0 || trim.indexOf('<html') !== -1 || trim.indexOf('<!DOCTYPE') !== -1) {
					$btn.prop('disabled', false).removeClass('ccew-btn-processing');
					$btn.text('Activated Successfully!');
					requestAnimationFrame(function () {
						setTimeout(function () { window.location.reload(); }, 1200);
					});
					return;
				}
			}
			var response = null;
			var lastParsed = null;
			var idx = 0;
			// When other code outputs JSON before ours, parse from each '{' until we find our object (has success: true).
			while ((idx = str.indexOf('{', idx)) !== -1) {
				try {
					response = JSON.parse(str.substring(idx));
					lastParsed = response;
					if (response && response.success === true) {
						break;
					}
					response = null;
				} catch (e) {}
				idx += 1;
			}
			if (response && response.success) {
				var d = response.data || {};
				// Installed but not auto-activated (e.g. WooCommerce-dependent add-on without Woo active).
				if (d.activated === false) {
					$btn.prop('disabled', false).removeClass('ccew-btn-processing');
					var installedLabel = (typeof cp_events !== 'undefined' && cp_events.installed_pending_wc_label) ? cp_events.installed_pending_wc_label : 'Installed!';
					$btn.text(installedLabel);
					requestAnimationFrame(function () {
						setTimeout(function () { window.location.reload(); }, 1200);
					});
					return;
				}
				$btn.prop('disabled', false).removeClass('ccew-btn-processing');
				$btn.text('Activated Successfully!');
				requestAnimationFrame(function () {
					setTimeout(function () { window.location.reload(); }, 1200);
				});
				return;
			}
			var msg = '';
			var errCode = '';
			var forMsg = response || lastParsed;
			if (forMsg && forMsg.data) {
				msg = forMsg.data.errorMessage || forMsg.data.message || '';
				errCode = forMsg.data.errorCode || '';
			}
			// Re-enable all buttons on failure.
			enableAllBtns();
			$btn.text($btn.hasClass('ccew-btn-activate') ? 'Activate Now' : 'Install Now');
			if (errCode === 'woocommerce_required') {
				return;
			}
			if (msg) {
				alert(msg);
			}
		}).fail(function (xhr) {
			enableAllBtns();
			$btn.text($btn.hasClass('ccew-btn-activate') ? 'Activate Now' : 'Install Now');
			var msg = '';
			var failErrCode = '';
			if (xhr && xhr.responseText) {
				try {
					var str = xhr.responseText;
					var start = str.indexOf('{');
					if (start !== -1) {
						var data = JSON.parse(str.substring(start));
						if (data && data.data) {
							msg = data.data.errorMessage || data.data.message || '';
							failErrCode = data.data.errorCode || '';
						}
					}
				} catch (e) {}
			}
			if (failErrCode === 'woocommerce_required') {
				return;
			}
			if (msg) {
				alert(msg);
			}
		});
	});

	// Legacy: separate activate action (if old markup still sends it).
	$(document).on('click', '.plugin-activator[data-plugin-id][data-action-nonce]', function () {
		var $btn = $(this);
		if ($btn.hasClass('ccew-install-plugin')) {
			return; // already handled above
		}
		var nonce = $btn.attr('data-action-nonce');
		var pluginSlug = $btn.attr('data-plugin-slug');
		var ajaxUrl = (typeof cp_events !== 'undefined' && cp_events.ajax_url) ? cp_events.ajax_url : '';
		if (!pluginSlug || !nonce || !ajaxUrl) {
			return;
		}
		if (typeof cp_events !== 'undefined' && !cp_events.woocommerce_active && cp_events.woocommerce_slugs && cp_events.woocommerce_slugs.indexOf(pluginSlug) !== -1) {
			return;
		}
		disableAllBtns();
		$btn.text('Activating...');
		$.ajax({
			type: 'POST',
			url: ajaxUrl,
			data: {
				action: 'ccew_dashboard_install_plugin',
				wp_nonce: (typeof cp_events !== 'undefined' && cp_events.install_nonce) ? cp_events.install_nonce : nonce,
				slug: pluginSlug
			}
		}).done(function (response) {
			if (response && response.success) {
				$btn.prop('disabled', false).removeClass('ccew-btn-processing');
				$btn.text('Activated Successfully!');
				requestAnimationFrame(function () {
					setTimeout(function () { window.location.reload(); }, 1200);
				});
			} else {
				enableAllBtns();
				$btn.text('Activate');
			}
		}).fail(function () {
			enableAllBtns();
			$btn.text('Activate');
		});
	});

	$('.plugins-list').each(function () {
		var $this = $(this);
		var message = $this.attr('data-empty-message');
		if ($this.children('.plugin-block').length === 0 && $this.children('.ccew-card').length === 0 && message) {
			$this.append('<div class="empty-message">' + message + '</div>');
		}
	});
});