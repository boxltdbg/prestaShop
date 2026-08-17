{*
 * 2007-2024 PrestaShop
 * License: Academic Free License (AFL 3.0)
 * Author: PrestaShop SA
 *}
{assign var='select_boxnow_locker_error' value={l s='Please select BoxNow Locker' mod='boxnow'}}
{assign var='locker_label' value={l s='Locker' mod='boxnow'}}

<div class="delivery-option" id="boxnow-map-container">
	{if $boxnow_map_mode == 'popup'}
		<div id="boxnowmap-popup"></div>
		<div id="boxnow-popup-content-wrapper">
			<div class="boxnow-selected-locker">
				{assign var="address_full" value=""}
				{if $boxnow_selected_entry->locker_address}
					{assign var="address_full" value=$boxnow_selected_entry->locker_address}
				{/if}
				{if $boxnow_selected_entry->locker_post_code}
					{if $address_full != ""}
						{assign var="address_full" value=$address_full|cat:", "}
					{/if}
					{assign var="address_full" value=$address_full|cat:$boxnow_selected_entry->locker_post_code}
				{/if}

				{if isset($boxnow_selected_entry->locker_id) && $boxnow_selected_entry->locker_id != ''}
					<div class="boxnow-selected-locker">
						<div class="selected-boxnow">
							<strong>{$locker_label}:</strong><br>
							{$boxnow_selected_entry->locker_name}<br>
							{$address_full}
						</div>
					</div>
				{/if}
			</div>

			<button class="btn btn-primary float-xs-right boxnow-map-widget-button"
				style="background-color: {if empty($boxnow_button_color)}#84C33F{else}{$boxnow_button_color}{/if};"
				id="boxnow-map-button">
				{if empty($boxnow_button_text)}Pick a locker{else}{$boxnow_button_text}{/if}
			</button>
		</div>
	{else}
		<div class="boxnow-pickup-map" style="width:100%">
			<div class="boxnow-selected-locker"></div>
			<div class="gmap_canvas" style="overflow:hidden;background:none !important;height:700px;width:100%;">
				<div id="boxnowmap-inline" style="height:100%; width:100%;"></div>
			</div>
		</div>
	{/if}

	<input type="hidden" class="boxnow-id" value="{$boxnow_selected_entry->locker_id|escape:'htmlall':'UTF-8'}">
	<input type="hidden" class="boxnow-name" value="{$boxnow_selected_entry->locker_name|escape:'htmlall':'UTF-8'}">
	<input type="hidden" class="boxnow-address"
		value="{$boxnow_selected_entry->locker_address|escape:'htmlall':'UTF-8'}">
	<input type="hidden" class="boxnow-zip" value="{$boxnow_selected_entry->locker_post_code|escape:'htmlall':'UTF-8'}">

	<script type="text/javascript">
		window.addEventListener("load", function() {
			jQuery('#js-delivery .continue').off('click.boxnow').on('click.boxnow', function() {
				if ($('.boxnow-zip').val() === '' && $('#boxnow-map-container').is(":visible")) {
					$('.boxnow-selected-locker').html('<div class="select-boxnow-locker error">{$select_boxnow_locker_error}</div>');
					const offset = 100;
					const target = $('.boxnow-selected-locker');
					$('html, body').stop().animate({
						'scrollTop': $(target).offset().top - offset
					}, 700, 'swing');
					return false;
				}
			});

			if (typeof _bn_map_widget_config === 'undefined') return;

			(function(d) {
				const e = d.createElement("script");
				e.src = "https://widget-cdn.boxnow.hr/map-widget/client/v5.js";
				e.async = true;
				e.defer = true;
				d.getElementsByTagName("head")[0].appendChild(e);
			})(document);
		});

		var _bn_map_widget_config = {
			type: "{$boxnow_map_mode|escape:'htmlall':'UTF-8'}",
			gps: true,
			partnerId: {$boxnow_partner_id},
			parentElement: "#{if $boxnow_map_mode == 'popup'}boxnowmap-popup{else}boxnowmap-inline{/if}",
			autoclose: {if $boxnow_map_mode == 'popup'}true{else}false{/if},
			autoshow: {if $boxnow_map_mode != 'popup'}true{else}false{/if},
			autoselect: {if $boxnow_map_mode != 'popup'}true{else}false{/if},
			afterSelect: function(selected) {
				const endpoint = '{$boxnow_select_endpoint nofilter}';
				const cartId = '{$boxnow_id_cart}';
				const text = selected.boxnowLockerName + '<br/>' + selected.boxnowLockerAddressLine1 + ', ' + selected
					.boxnowLockerPostalCode;

				$('.boxnow-id').val(selected.boxnowLockerId);
				$('.boxnow-text').val(text);
				$('.boxnow-name').val(selected.boxnowLockerName);
				$('.boxnow-address').val(selected.boxnowLockerAddressLine1);
				$('.boxnow-zip').val(selected.boxnowLockerPostalCode);

				if (selected.boxnowLockerId) {
					$.ajax({
						url: endpoint,
						type: 'POST',
						data: {
							'boxnow_selected': "true",
							'boxnow_id': selected.boxnowLockerId,
							'boxnow_cart_id': cartId,
							'boxnow_locker_id': selected.boxnowLockerId,
							'boxnow_locker_name': selected.boxnowLockerName,
							'boxnow_locker_address': selected.boxnowLockerAddressLine1,
							'boxnow_locker_post_code': selected.boxnowLockerPostalCode,
						},
						dataType: 'json',
						success: function(response) {
							if (response.status === "success") {
								$('.boxnow-selected-locker').html('<div class="selected-boxnow"><strong>{$locker_label}:<br/></strong>' + text + '</div>');
							}
						}
					});
				}
			}
		};
	</script>

	{literal}
		<style>
			.selected-boxnow,
			.select-boxnow-locker.error {
				padding: 15px;
				background: #fff;
				margin: 15px;
			}

			.select-boxnow-locker.error {
				color: #cc0000;
				font-weight: 700;
			}

			#boxnow_close_all {
				z-index: 99999 !important;
			}

			.boxnow-map-widget-button {
				height: auto !important;
				max-height: 38px;
			}

			.boxnow-selected-locker {
				width: 80%!important;
			}

			#boxnowmap-popup iframe,
			#boxnowmap-inline iframe {
				z-index: 99999;
			}
		</style>
	{/literal}
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var carrierId = parseInt('{$boxnow_dynamic_id}', 10);
			var container = document.getElementById('boxnow-map-container');
			if (!container || !carrierId) {
				return;
			}

			var extraRow = container.closest('.carrier-extra-content');

			function getCarrierId(radio) {
				if (!radio) return null;
				var dataCarrier = radio.dataset.idCarrier || radio.dataset.carrierId;
				if (dataCarrier) return parseInt(dataCarrier, 10);
				var val = (radio.value || '').toString();
				if (val.indexOf(',') !== -1) {
					val = val.split(',')[0];
				}
				var parsed = parseInt(val, 10);
				return isNaN(parsed) ? null : parsed;
			}

			function showRow() {
				if (extraRow) {
					extraRow.style.removeProperty('display');
					extraRow.style.display = 'block';
					extraRow.style.maxHeight = 'none';
					extraRow.style.visibility = 'visible';
					extraRow.style.opacity = '1';
					extraRow.style.overflow = 'visible';
					var wrapper = extraRow.closest('.carrier__extra-content-wrapper');
					if (wrapper) {
						wrapper.classList.add('carrier__extra-content-wrapper--active');
						wrapper.style.maxHeight = 'none';
						wrapper.style.height = 'auto';
						wrapper.style.display = 'block';
						wrapper.style.overflow = 'visible';
						wrapper.style.opacity = '1';
					}
				}
				if (container.parentNode && container.parentNode.style) {
					container.parentNode.style.display = 'block';
				}
			}

			function hideRow() {
				if (extraRow) {
					extraRow.style.display = 'none';
				}
			}

			function sync() {
				var selected = document.querySelector('.delivery-options input[type="radio"][name^="delivery_option"]:checked');
				var selectedId = getCarrierId(selected);
				if (selectedId === carrierId) {
					showRow();
				} else {
					hideRow();
				}
			}

			function autoSelectIfLocker() {
				var hasLocker = (document.querySelector('.boxnow-zip') || {}).value || document.querySelector('.selected-boxnow');
				if (!hasLocker) return;
				var selected = document.querySelector('.delivery-options input[type="radio"][name^="delivery_option"]:checked');
				var selectedId = getCarrierId(selected);
				// Respect any other carrier the customer picked after load
				if (selectedId && selectedId !== carrierId) {
					return;
				}
				var radios = document.querySelectorAll('.delivery-options input[type="radio"][name^="delivery_option"]');
				var target = Array.prototype.find.call(radios, function (r) {
					return getCarrierId(r) === carrierId;
				});
				if (target && !target.checked) {
					target.checked = true;
					target.dispatchEvent(new Event('change', { bubbles: true }));
				}
			}

			var radios = document.querySelectorAll('.delivery-options input[type="radio"][name^="delivery_option"]');
			radios.forEach(function (radio) {
				radio.removeEventListener('change', sync);
				radio.addEventListener('change', sync);
			});

			autoSelectIfLocker();
			sync();

			var deliveryRoot = document.getElementById('js-delivery');
			if (deliveryRoot && window.MutationObserver) {
				var observer = new MutationObserver(function () {
					var refreshedRadios = document.querySelectorAll('.delivery-options input[type="radio"][name^="delivery_option"]');
					refreshedRadios.forEach(function (radio) {
						radio.removeEventListener('change', sync);
						radio.addEventListener('change', sync);
					});
					autoSelectIfLocker();
					sync();
				});
				observer.observe(deliveryRoot, { childList: true, subtree: true });
			}
		});
	</script>
</div>