<div class="delivery-option" id="boxnow-map-container">
	<div class="boxnow-pickup-map" style="width:100%">

		<div class="boxnow-selected-locker"></div>

		<div class="gmap_canvas" style="overflow:hidden;background:none !important;height:700px; width:100%;">
			<iframe
				id="boxnow-widget-iframe-inline"
				src="{$boxnow_widget_url}?partnerId={$boxnow_partner_id|escape:'url'}&language={$boxnow_widget_language|escape:'url'}&peer=prestashop"
				style="height:100%; width:100%; border:0;"
				loading="lazy"
			></iframe>
		</div>
		<input type="hidden" class="boxnow-zip" value="{if $boxnow_selected_entry->locker_id}{$boxnow_selected_entry->locker_post_code}{/if}">
	</div>
	<script>
		function boxnowHandleSelectedLockerInline(selected) {
			const endpoint = '{$boxnow_select_endpoint}';
			const cartId = '{$boxnow_id_cart}';

			text = selected.boxnowLockerName + '<br/>' + selected.boxnowLockerAddressLine1 + ', ' + selected.boxnowLockerPostalCode;

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
					success: function (response) {
						if (response.status === "success") {
							$('.boxnow-selected-locker').html('<div class="selected-boxnow"><strong>{l s='Locker' mod='boxnow'}: <br/></strong>' + text + '</div>').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast');
							{literal}$('html,body').animate({scrollTop: $('.boxnow-selected-locker').offset().top},'slow');{/literal}
						}
					}
				});
			}
		}

		window.addEventListener("message", function(event) {
			if (!event || !event.data) return;
			var d = event.data;
			if (!d.boxnowLockerId || !d.boxnowLockerName) return;
			boxnowHandleSelectedLockerInline(d);
		});

		// The carrier extra-content is injected via AJAX, so window 'load' has
		// already fired. Run the setup immediately instead of waiting for it.
		jQuery(function () {
			// Block finishing the order until a locker is picked from the iframe.
			var boxnowSubmitSelectors = '.button.standard-checkout, #js-delivery .continue, #confirmOrder, button[name="confirmDeliveryOption"], button[name="processCarrier"]';
			$(document).off('click.boxnowCheckout', boxnowSubmitSelectors).on('click.boxnowCheckout', boxnowSubmitSelectors, function(e) {
				if ($('.boxnow-zip').val() == '' && $('#boxnow-map-container').is(":visible")) {
					e.preventDefault();
					e.stopImmediatePropagation();
					$('.boxnow-selected-locker').html('<div class="select-boxnow-locker error">{l s='Please select BoxNow Locker' mod='boxnow'}</div>').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast').fadeOut('fast').fadeIn('fast');
					var offset = 100;
					var target = $('.boxnow-selected-locker');
					$('html, body').stop().animate({
						'scrollTop': $(target).offset().top - offset
					}, 700, 'swing', function() {});
					return false;
				}
			});
		});
	</script>
	{literal}
	<style>
	.selected-boxnow,.select-boxnow-locker.error{padding:15px;background:#fff;margin:15px;}
	.select-boxnow-locker.error{color:#cc0000;font-weight:700;}
	.boxnow-selected-locker{width:100%;margin-bottom:15px;font-size:1.2em;background:#fff;padding:15px;}
	</style>
	{/literal}
</div>
