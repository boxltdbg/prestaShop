<div class="delivery-option" id="boxnow-map-container">

    {if $boxnow_map_mode == 'popup'}
		<div id="boxnowmap"></div>
        <div id="boxnow-popup-content-wrapper">
            <div class="boxnow-selected-locker">
                {assign var="address_full" value="{$boxnow_selected_entry->locker_address}, {$boxnow_selected_entry->locker_post_code}"|ltrim: ',' }
                <div id="boxnow-locker-field-title">{$boxnow_selected_entry->locker_name}</div>
                <div id="boxnow-locker-field-address">{$address_full}</div>
            </div>
            <button class="btn btn-primary float-xs-right boxnow-map-widget-button"
                    style="background-color: {if empty($boxnow_button_color) } '#84C33F' {else} {$boxnow_button_color} {/if}"
                    id="boxnow-map-button">{if empty($boxnow_button_text) } Изберете BOX NOW автомат {else} {$boxnow_button_text} {/if}</button>
        </div>
		<input type="hidden" class="boxnow-zip" value="">
		<script type="text/javascript">
			var _boxnow_err_select = '<div class="select-boxnow-locker error">{l s='Моля изберете BOX NOW автомат!' mod='boxnow'}</div>';
			var _boxnow_cart_id = '{$boxnow_id_cart}';

			var _bn_map_widget_config = {
				type: "popup",
				gps: true,
				partnerId: {if $boxnow_partner_id}{$boxnow_partner_id}{else}0{/if},
				parentElement: "#boxnowmap",
				autoclose: true,
				afterSelect: function(selected) {
					const endpoint = '{$boxnow_select_endpoint}';
					const cartId   = '{$boxnow_id_cart}';

					var lockerName     = selected.boxnowLockerName;
					var lockerAddress  = selected.boxnowLockerAddressLine1;
					var lockerPostCode = selected.boxnowLockerPostalCode;
					var text = lockerName + '<br/>' + lockerAddress + ', ' + lockerPostCode;

					// Update display immediately — do not wait for AJAX response
					$('.boxnow-zip').val(lockerPostCode);
					$('.boxnow-selected-locker').html('<strong>{l s='Автомат' mod='boxnow'}:</strong> <br/>' + text).fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast');

					if (selected.boxnowLockerId) {
						// Persist selection in localStorage so it survives carrier-switch re-renders
						try {
							localStorage.setItem('boxnow_locker_' + cartId, JSON.stringify({
								name: lockerName,
								address: lockerAddress,
								postCode: lockerPostCode
							}));
						} catch(e) {}

						$.ajax({
							url: endpoint,
							type: 'POST',
							data: {
								'boxnow_selected': "true",
								'boxnow_cart_id': cartId,
								'boxnow_locker_id': selected.boxnowLockerId,
								'boxnow_locker_name': lockerName,
								'boxnow_locker_address': lockerAddress,
								'boxnow_locker_post_code': lockerPostCode
							},
							dataType: 'json'
						});
					}
				}
			};
		</script>
		{literal}
		<script type="text/javascript">
			// Remove any stale widget instance then inject a fresh one to force reinitialization
			(function(d) {
				var old = d.getElementById('boxnow-widget-script');
				if (old) { old.parentNode.removeChild(old); }
				var e = d.createElement('script');
				e.id = 'boxnow-widget-script';
				e.src = 'https://widget-cdn.boxnow.bg/map-widget/client/v4.js';
				e.async = true;
				d.head.appendChild(e);
			})(document);

			// jQuery loads at bottom of PS 1.7 page — poll until available then register handler
			(function waitForjQuery() {
				if (typeof jQuery !== 'undefined') {
					// Restore selected locker from localStorage (persists across carrier switches)
					(function() {
						try {
							var stored = localStorage.getItem('boxnow_locker_' + _boxnow_cart_id);
							if (stored) {
								var data = JSON.parse(stored);
								if (data && data.postCode) {
									// Always restore zip for checkout validation
									jQuery('.boxnow-zip').val(data.postCode);
									// Only update display if server-side content is empty
									if (!jQuery('#boxnow-locker-field-title').text().trim()) {
										jQuery('.boxnow-selected-locker').html('<strong>Автомат:</strong> <br/>' + data.name + '<br/>' + data.address + ', ' + data.postCode);
									}
								}
							}
						} catch(e) {}
					})();

					jQuery(document).off('click.boxnow').on('click.boxnow', '#js-delivery .continue', function() {
						if (jQuery('.boxnow-zip').val() === '' && jQuery('#boxnow-map-container').is(':visible')) {
							jQuery('.boxnow-selected-locker').html(_boxnow_err_select).fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast');
							var offset = 100;
							jQuery('html, body').stop().animate({
								'scrollTop': jQuery('.boxnow-selected-locker').offset().top - offset
							}, 700, 'swing', function() {});
							return false;
						}
					});
				} else {
					setTimeout(waitForjQuery, 50);
				}
			})();
		</script>
		{/literal}
    {else}
	<div class="boxnow-pickup-map" style="width:100%">

		<div class="boxnow-selected-locker"></div>

		<div class="gmap_canvas" style="overflow:hidden;background:none !important;height:700px; width:100%;">
			<div id="boxnowmap" style="height:100%; width:100%;"></div>
		</div>
		<input type="hidden" class="boxnow-zip" value="">
	</div>
	<script type="text/javascript">
		var _boxnow_err_select = '<div class="select-boxnow-locker error">{l s='Моля изберете BOX NOW автомат!' mod='boxnow'}</div>';
		var _boxnow_cart_id = '{$boxnow_id_cart}';

		var _bn_map_widget_config = {
			partnerId: {if $boxnow_partner_id}{$boxnow_partner_id}{else}0{/if},
			parentElement: "#boxnowmap",
			autoshow: true,
			autoclose: false,
			autoselect: true,
			gps: true,
			afterSelect: function(selected) {
				const endpoint = '{$boxnow_select_endpoint}';
				const cartId   = '{$boxnow_id_cart}';

				var lockerName     = selected.boxnowLockerName;
				var lockerAddress  = selected.boxnowLockerAddressLine1;
				var lockerPostCode = selected.boxnowLockerPostalCode;
				var text = lockerName + '<br/>' + lockerAddress + ', ' + lockerPostCode;

				$('.boxnow-zip').val(lockerPostCode);
				$('.boxnow-selected-locker').html('<div class="selected-boxnow"><strong>{l s='Автомат' mod='boxnow'}: <br/></strong>' + text + '</div>').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast');

				if (selected.boxnowLockerId) {
					// Persist selection in localStorage so it survives carrier-switch re-renders
					try {
						localStorage.setItem('boxnow_locker_' + cartId, JSON.stringify({
							name: lockerName,
							address: lockerAddress,
							postCode: lockerPostCode
						}));
					} catch(e) {}

					$.ajax({
						url: endpoint,
						type: 'POST',
						data: {
							'boxnow_selected': "true",
							'boxnow_cart_id': cartId,
							'boxnow_locker_id': selected.boxnowLockerId,
							'boxnow_locker_name': lockerName,
							'boxnow_locker_address': lockerAddress,
							'boxnow_locker_post_code': lockerPostCode
						},
						dataType: 'json'
					});
				}
			}
		};
	</script>
	{literal}
	<script type="text/javascript">
		// Remove any stale widget instance then inject a fresh one to force reinitialization
		(function(d) {
			var old = d.getElementById('boxnow-widget-script');
			if (old) { old.parentNode.removeChild(old); }
			var e = d.createElement('script');
			e.id = 'boxnow-widget-script';
			e.src = 'https://widget-cdn.boxnow.bg/map-widget/client/v5.js';
			e.async = true;
			d.head.appendChild(e);
		})(document);

		// jQuery loads at bottom of PS 1.7 page — poll until available then register handler
		(function waitForjQuery() {
			if (typeof jQuery !== 'undefined') {
				// Restore selected locker from localStorage (persists across carrier switches)
				(function() {
					try {
						var stored = localStorage.getItem('boxnow_locker_' + _boxnow_cart_id);
						if (stored) {
							var data = JSON.parse(stored);
							if (data && data.postCode) {
								jQuery('.boxnow-zip').val(data.postCode);
								if (!jQuery('.boxnow-selected-locker').text().trim()) {
									jQuery('.boxnow-selected-locker').html('<div class="selected-boxnow"><strong>Автомат: <br/></strong>' + data.name + '<br/>' + data.address + ', ' + data.postCode + '</div>');
								}
							}
						}
					} catch(e) {}
				})();

				jQuery(document).off('click.boxnow').on('click.boxnow', '#js-delivery .continue', function() {
					if (jQuery('.boxnow-zip').val() === '' && jQuery('#boxnow-map-container').is(':visible')) {
						jQuery('.boxnow-selected-locker').html(_boxnow_err_select).fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast');
						var offset = 100;
						jQuery('html, body').stop().animate({
							'scrollTop': jQuery('.boxnow-selected-locker').offset().top - offset
						}, 700, 'swing', function() {});
						return false;
					}
				});
			} else {
				setTimeout(waitForjQuery, 50);
			}
		})();
	</script>
	{/literal}

    {/if}
	{literal}
	<style>
	.selected-boxnow,.select-boxnow-locker.error{padding:15px;background:#fff;margin:15px;}
	.select-boxnow-locker.error{color:#cc0000;font-weight:700;}
	#boxnow_close_all{z-index:99999 !important;}
	.boxnow-map-widget-button {height:auto !important;max-height:38px;}
	.boxnow-selected-locker{width:100%}
	#boxnowmap iframe{z-index:99999}
	</style>
	{/literal}
</div>
