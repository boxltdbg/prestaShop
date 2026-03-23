<div id="boxnowCard" class="boxnow card">
	  <div class="card-header">
		<h3 class="card-header-title">
		  {l s='Товарителници / Информация' mod='boxnow'}
		</h3>
	  </div>

	<div class="card-body">
		<p class="mb-1">
              <strong>{l s='BOX NOW информация' mod='boxnow'}:</strong>
        </p>
		<p>{l s='ID на автомат' mod='boxnow'}: {$boxnow_locker_id}  {if $boxnow_vouchers == 0}<a href="#" class="boxnow-edit-locker" data-toggle="modal" data-target="#changeLocker" data-order-id="{$boxnow_id_order}"><i class="material-icons" aria-hidden="true">replay</i><span>{l s='Промяна' mod='boxnow'}</span></a>{/if}</p>
		<p>{$boxnow_locker_name}</br>
		 {l s='Адрес' mod='boxnow'}: {$boxnow_locker_address}</p>

		{if $boxnow_vouchers == 0}
		<p class="mb-1"> <strong>{l s='Създайте BOX NOW Товарителница' mod='boxnow'} </strong></p>
			    <div class="form-group">
                  <label class="form-control-label" for="warehouses">
                  {l s='Изберете склад' mod='boxnow'}:
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
                  {l s='Размер на пратката' mod='boxnow'}:
                  </label>
                  <select id="compartment_size" name="compartment_size" class="custom-select">
                      <option value="1" selected="selected">{l s='Малък' mod='boxnow'}</option>
                      <option value="2">{l s='Среден' mod='boxnow'}</option>
                      <option value="3">{l s='Голям' mod='boxnow'}</option>
                  </select>
               </div>

               <div class="form-group">
				  <input type="hidden" id="boxnowid" value="{$boxnow_id_order}">
               <button type="submit" class="btn btn-primary" id="create_vouchers">
               {l s='Създай товарителница' mod='boxnow'}
               </button>
               </div>
		{else}
		<p class="mb-1"> <strong>{l s='Товарителници за тази поръчка' mod='boxnow'}: </strong></p>
		{foreach from=$boxnow_vouchers_numbers item=voucher_number}
		{if $voucher_number !=''}
		<a href="{Context::getContext()->link->getBaseLink('index',true)}modules/boxnow/printVoucher.php?voucher={$voucher_number}" target="_new" alt="{l s='Принтирай товарителница' mod='boxnow'}" title="{l s='Принтирай товарителница' mod='boxnow'}"><i class="material-icons" aria-hidden="true">print</i><span> {$voucher_number}</a>	<a href="#" class="boxnow-cancel-voucher" data-order-id="{$boxnow_id_order}" data-voucher="{$voucher_number}" onclick="return confirm('{l s='Сигурни ли сте, че искате да откажете товарителницата?' mod='boxnow'}')"><i class="material-icons" aria-hidden="true">cancel</i><span>{l s='Откажи товарителница' mod='boxnow'}</span></a></br>
		{/if}
		{/foreach}
		{/if}
		<p class="error" style="color:#cc0000;"></p>

	</div>
</div>


<div class="modal fade" id="changeLocker" tabindex="-1" role="dialog">
   <div class="modal-dialog modal-lg" role="document">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title">{l s='Промяна на BOX NOW автомат' mod='boxnow'}</h5>
               <button type="button" class="close" data-dismiss="modal" aria-label="Затвори">
               <span aria-hidden="true">×</span>
               </button>
            </div>
            <div class="modal-body">
               <div id="boxnow-admin-map" style="height:500px;width:100%;"></div>
               <div id="boxnow-admin-selected" class="alert alert-info mt-2" style="display:none;"></div>
               <div class="form-group mt-2">
                  <label class="form-control-label" for="locker_id">
                   {l s='ID на автомат' mod='boxnow'}:
                  </label>
                  <input type="text" id="locker_id" name="locker_id" class="form-control locker-field" />
               </div>
            </div>
            <div class="modal-footer">
			<input type="hidden" id="boxnow_id" value="{$boxnow_id_order}">
               <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
               {l s='Отказ' mod='boxnow'}
               </button>
               <button type="submit" class="btn btn-primary" id="boxnow-change-locker" onclick="return confirm('{l s='Сигурни ли сте, че желаете да смените номера на автомата?' mod='boxnow'}')">
               {l s='Актуализирай' mod='boxnow'}
               </button>
            </div>
         </div>
   </div>
</div>
<script>
var _bn_map_widget_config = {
	partnerId: {if $boxnow_partner_id}{$boxnow_partner_id}{else}0{/if},
	parentElement: '#boxnow-admin-map',
	autoshow: true,
	autoclose: false,
	gps: true,
	afterSelect: function(selected) {
		document.getElementById('locker_id').value = selected.boxnowLockerId || '';
		var el = document.getElementById('boxnow-admin-selected');
		el.textContent = (selected.boxnowLockerName || '') + ' \u2014 ' + (selected.boxnowLockerAddressLine1 || '');
		el.style.display = 'block';
	}
};
</script>
<script>
$( document ).ready(function() {
	$('.js-update-shipping-btn').hide();

	// Load the BoxNow map widget the first time the change-locker modal opens
	$('#changeLocker').off('shown.bs.modal').one('shown.bs.modal', function() {
		{literal}
		if (!document.getElementById('boxnow-admin-widget-script')) {
			var s = document.createElement('script');
			s.id = 'boxnow-admin-widget-script';
			s.src = 'https://widget-cdn.boxnow.bg/map-widget/client/v5.js';
			document.head.appendChild(s);
		}
		{/literal}
	});

	//cancel voucher
	$(document).off('click.boxnow-cancel').on('click.boxnow-cancel', '.boxnow-cancel-voucher', function() {
		var orderid = $(this).data("order-id");
		var voucher = $(this).data("voucher");
		var linkcancel = '{Context::getContext()->link->getBaseLink('index',true)}modules/boxnow/cancelVoucher.php';
		if(orderid > 0){
			$.post( linkcancel, { order_id: orderid, voucher_number: voucher })
			  .done(function( data ) {
					location.reload();
			  });
		}
	});

	//create vouchers
	$('#create_vouchers').off('click').on('click', function() {
		var $btn = $(this);
		var warehouse = $('#warehouses').val();
		var box_order_id = $('#boxnowid').val();
		var linkcreate = '{Context::getContext()->link->getBaseLink('index',true)}modules/boxnow/createVoucher.php';
		if(box_order_id > 0){
			$btn.prop('disabled', true);
			var compartment_size = $('#compartment_size').val();
			$.post( linkcreate, { order_id: box_order_id, warehouses: warehouse, compartment_size: compartment_size})
			  .done(function(data) {
				if(data == ''){
					location.reload();
				} else {
					$('.error').text(data).show();
					$btn.prop('disabled', false);
				}
			  })
			  .fail(function() {
				$btn.prop('disabled', false);
			  });
		}
	});

	//change locker
	$('#boxnow-change-locker').off('click').on('click', function() {
		var lockerid = $('#locker_id').val();
		var box_order_id = $('#boxnow_id').val();
		var linklocker = '{Context::getContext()->link->getBaseLink('index',true)}modules/boxnow/changeLocker.php';
		if(lockerid.length > 0){
			$.post( linklocker, { order_id: box_order_id, locker_id: lockerid})
			  .done(function( data ) {
				if(data == 'error'){
					alert( "Грешка! Моля проверете номера на автомата.");
				} else {
					location.reload();
				}
			  });
		}
	});
});
</script>
