<div id="boxnowCard" class="panel">
	  <div class="panel-heading">
		  {l s='BoxNow Voucher / Information' mod='boxnow'}
	  </div>

	<div class="card-body">
		<p class="mb-1">
              <strong>{l s='BoxNow information' mod='boxnow'}:</strong>
        </p>
		<p>{l s='Locker ID' mod='boxnow'}: <span id="boxnow-locker-id-display">{$boxnow_locker_id}</span>
			<span id="boxnow-change-locker-wrap" {if $boxnow_vouchers != 0}style="display:none;"{/if}><a href="#" class="boxnow-edit-locker" data-toggle="modal" data-target="#changeLocker" data-order-id="{$boxnow_id_order}"><i class="material-icons" aria-hidden="true">replay</i><span>{l s='Change' mod='boxnow'}</span></a></span>
		</p>
		<p><span id="boxnow-locker-name-display">{$boxnow_locker_name}</span></br>
		 {l s='Address' mod='boxnow'}: <span id="boxnow-locker-address-display">{$boxnow_locker_address}</span>,<span id="boxnow-locker-postcode-display">{$boxnow_locker_post_code}</span></p>

		{* Create a single voucher for the whole order *}
		<div id="boxnow-create-section" {if $boxnow_vouchers != 0}style="display:none;"{/if}>
			<p class="mb-1"> <strong>{l s='Create BoxNow Voucher' mod='boxnow'} </strong></p>
				    <div class="form-group">
	                  <label class="form-control-label" for="warehouses">
	                  {l s='Select Warehouse' mod='boxnow'}:
	                  </label>
					  <select id="warehouses" name="warehouses" class="custom-select" >
					  {foreach from=$boxnow_warehouses item=warehouse}
					  {assign var=warehouseid value="["|explode:$warehouse}
						<option value="{$warehouseid[0]|trim}">{$warehouse}</option>
					  {/foreach}
					  </select>
	               </div>
				    <div class="form-group">
	                  <label class="form-control-label" for="compartment_size">
	                  {l s='Locker size' mod='boxnow'}:
	                  </label>
					  <select id="compartment_size" name="compartment_size" class="custom-select" >
						<option value="1" selected>{l s='Small' mod='boxnow'}</option>
						<option value="2">{l s='Medium' mod='boxnow'}</option>
						<option value="3">{l s='Large' mod='boxnow'}</option>
					  </select>
	               </div>
				  <input type="hidden" id="boxnowid" value="{$boxnow_id_order}">
	               <button type="button" class="btn btn-primary" id="create_vouchers">
	               {l s='Create Voucher' mod='boxnow'}
	               </button>
		</div>

		{* Existing voucher for the order *}
		<div id="boxnow-voucher-section" {if $boxnow_vouchers == 0}style="display:none;"{/if}>
			<p class="mb-1"> <strong>{l s='Order Voucher(s)' mod='boxnow'}: </strong></p>
			<div id="boxnow-voucher-list">
			{foreach from=$boxnow_vouchers_numbers item=voucher_number}
			{if $voucher_number !=''}
			<div class="boxnow-voucher-row">&bull; <a href="{Context::getContext()->link->getBaseLink('index',true)}modules/boxnow/printVoucher.php?voucher={$voucher_number}" target="_new">{$voucher_number}</a>	<a href="#" class="boxnow-cancel-voucher" data-order-id="{$boxnow_id_order}" data-voucher="{$voucher_number}"><i class="material-icons" aria-hidden="true">cancel</i><span>{l s='Cancel Voucher' mod='boxnow'}</span></a></div>
			{/if}
			{/foreach}
			</div>
		</div>

		<p class="error" style="color:#cc0000;"></p>

	</div>
</div>



<div class="modal fade" id="changeLocker" tabindex="-1" role="dialog">
   <div class="modal-dialog" role="document">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title">{l s='Change BoxNow Locker' mod='boxnow'}</h5>
               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
               <span aria-hidden="true">&times;</span>
               </button>
            </div>
            <div class="modal-body">
               <button type="button" class="btn btn-primary" id="boxnow-open-locker-map" style="margin-bottom:15px;">
               {l s='Pick a locker on the map' mod='boxnow'}
               </button>
               <div class="form-group">
                  <label class="form-control-label" for="locker_id">
                   {l s='New Locker ID' mod='boxnow'}:
                  </label>
                  <input type="text" id="locker_id" name="locker_id" class="form-control locker-field" />
				  <p>{l s='Pick a locker on the map, or enter the new locker id manually. A new email will be sent to the customer with the new information.' mod='boxnow'}</p>
               </div>
            </div>
            <div class="modal-footer">
			<input type="hidden" id="boxnow_id" value="{$boxnow_id_order}">
			<input type="hidden" id="boxnow_map_locker_name" value="">
			<input type="hidden" id="boxnow_map_locker_address" value="">
			<input type="hidden" id="boxnow_map_locker_postcode" value="">
               <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
               {l s='Cancel' mod='boxnow'}
               </button>
               <button type="button" class="btn btn-primary" id="boxnow-change-locker">
               {l s='Update' mod='boxnow'}
               </button>
            </div>
         </div>
   </div>
</div>
<script>
var BOXNOW_BASE_LINK = '{Context::getContext()->link->getBaseLink('index',true)}';
var BOXNOW_WIDGET_BASE_URL = '{$boxnow_widget_url|escape:"javascript"}';
var BOXNOW_WIDGET_LANG = '{$boxnow_widget_language|escape:"javascript"}';
var BOXNOW_WIDGET_PARTNER_ID = '{$boxnow_partner_id|escape:"javascript"}';

function boxnowBuildWidgetUrl() {
	var base = (BOXNOW_WIDGET_BASE_URL || '').replace(/\/+$/, '') + '/';
	return base + '?partnerId=' + encodeURIComponent(BOXNOW_WIDGET_PARTNER_ID) +
		'&language=' + encodeURIComponent(BOXNOW_WIDGET_LANG || 'en') +
		'&peer=prestashop';
}

function boxnowVoucherRow(orderId, voucher) {
	return '<div class="boxnow-voucher-row">&bull; <a href="' + BOXNOW_BASE_LINK +
		'modules/boxnow/printVoucher.php?voucher=' + encodeURIComponent(voucher) + '" target="_new">' + voucher + '</a> ' +
		'<a href="#" class="boxnow-cancel-voucher" data-order-id="' + orderId + '" data-voucher="' + voucher + '">' +
		'<i class="material-icons" aria-hidden="true">cancel</i><span>{l s='Cancel Voucher' mod='boxnow'}</span></a></div>';
}

function boxnowEnsureWidgetOverlay() {
	if (document.getElementById('boxnow-admin-widget-overlay')) return;

	var overlay = document.createElement('div');
	overlay.id = 'boxnow-admin-widget-overlay';
	overlay.style.cssText = 'display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:100000;';

	var modal = document.createElement('div');
	modal.style.cssText = 'position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:95%;max-width:1100px;height:85%;background:#fff;border-radius:8px;overflow:hidden;';

	var closeBtn = document.createElement('button');
	closeBtn.type = 'button';
	closeBtn.innerHTML = '&times;';
	closeBtn.style.cssText = 'position:absolute;right:10px;top:6px;z-index:2;background:transparent;border:0;font-size:28px;line-height:28px;cursor:pointer;';

	var iframe = document.createElement('iframe');
	iframe.id = 'boxnow-admin-widget-iframe';
	iframe.src = boxnowBuildWidgetUrl();
	iframe.style.cssText = 'width:100%;height:100%;border:0;';

	closeBtn.addEventListener('click', function() { overlay.style.display = 'none'; });
	overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.style.display = 'none'; });

	modal.appendChild(closeBtn);
	modal.appendChild(iframe);
	overlay.appendChild(modal);
	document.body.appendChild(overlay);
}

$( document ).ready(function() {
	$('.js-update-shipping-btn').hide();

	//cancel voucher (delegated, rows can be rendered dynamically)
	$('#boxnow-voucher-list').on('click', '.boxnow-cancel-voucher', function() {
		if (!confirm('{l s='Are you sure you want to cancel voucher?' mod='boxnow'}')) { return false; }
		var $row = $(this).closest('.boxnow-voucher-row');
		var orderid = $(this).data("order-id");
		var voucher = $(this).data("voucher");
		var linkcancel = BOXNOW_BASE_LINK + 'modules/boxnow/cancelVoucher.php';
		if(orderid > 0){
			$.post( linkcancel, { order_id: orderid, voucher_number: voucher }, null, 'json')
			  .done(function( data ) {
					if (data && data.success) {
						$row.remove();
						// no vouchers left -> bring back the create UI
						if ($('#boxnow-voucher-list .boxnow-voucher-row').length === 0) {
							$('#boxnow-voucher-section').hide();
							$('#boxnow-create-section').show();
							$('#boxnow-change-locker-wrap').show();
						}
					} else {
						$('.error').text(data && data.error ? data.error : 'Error').show();
					}
			  })
			  .fail(function() { $('.error').text('Request failed').show(); });
		}
		return false;
	});

	//create voucher (always a single voucher for the whole order)
	$('#create_vouchers').on('click',function() {
		var warehouse = $('#warehouses').val();
		var compartment_size = $('#compartment_size').val();
		var box_order_id = $('#boxnowid').val();
		var linkcreate = BOXNOW_BASE_LINK + 'modules/boxnow/createVoucher.php';
		$('.error').hide().text('');
		var $btn = $(this);
		if(box_order_id > 0){
			$btn.prop('disabled', true);
			$.post( linkcreate, { order_id: box_order_id, warehouses: warehouse, compartment_size: compartment_size }, null, 'json')
			  .done(function(data) {
				if(data && data.success){
					var list = $('#boxnow-voucher-list');
					$.each(data.vouchers, function(i, v){
						list.append(boxnowVoucherRow(box_order_id, v));
					});
					$('#boxnow-create-section').hide();
					$('#boxnow-change-locker-wrap').hide();
					$('#boxnow-voucher-section').show();
				} else {
					$('.error').text(data && data.error ? data.error : 'Error').show();
				}
			  })
			  .fail(function() { $('.error').text('Request failed').show(); })
			  .always(function() { $btn.prop('disabled', false); });
		}
	});

	//open the locker map widget from inside the change-locker modal
	$('#boxnow-open-locker-map').on('click', function() {
		boxnowEnsureWidgetOverlay();
		document.getElementById('boxnow-admin-widget-overlay').style.display = 'block';
	});

	//receive the chosen locker from the widget
	window.addEventListener("message", function(event) {
		if (!event || !event.data) return;
		var d = event.data;
		if (!d.boxnowLockerId || !d.boxnowLockerName) return;
		$('#locker_id').val(d.boxnowLockerId);
		$('#boxnow_map_locker_name').val(d.boxnowLockerName);
		$('#boxnow_map_locker_address').val(d.boxnowLockerAddressLine1 || '');
		$('#boxnow_map_locker_postcode').val(d.boxnowLockerPostalCode || '');
		var overlay = document.getElementById('boxnow-admin-widget-overlay');
		if (overlay) overlay.style.display = 'none';
	});

	//change locker (manual id or map selection)
	$('#boxnow-change-locker').on('click',function() {
		var lockerid = $('#locker_id').val();
		var box_order_id = $('#boxnow_id').val();
		var linklocker = BOXNOW_BASE_LINK + 'modules/boxnow/changeLocker.php';
		if(!lockerid){ alert('Please choose or enter a locker ID.'); return; }
		if(!confirm('{l s='Are you sure you want to change the locker?' mod='boxnow'}')){ return; }
		$.post( linklocker, {
			order_id: box_order_id,
			locker_id: lockerid,
			locker_name: $('#boxnow_map_locker_name').val(),
			locker_address: $('#boxnow_map_locker_address').val(),
			locker_post_code: $('#boxnow_map_locker_postcode').val()
		}, null, 'json')
		  .done(function( data ) {
			if(data && data.success){
				$('#boxnow-locker-id-display').text(data.locker_id);
				$('#boxnow-locker-name-display').text(data.locker_name);
				$('#boxnow-locker-address-display').text(data.locker_address);
				$('#boxnow-locker-postcode-display').text(data.locker_post_code);
				$('#changeLocker').modal('hide');
			} else {
				alert(data && data.error ? data.error : 'Error! check the locker ID!');
			}
		  })
		  .fail(function() { alert('Request failed'); });
	});
});
</script>

