{*
 * PrestaShop Module Template - BoxNow Integration
 * Improvements by ChatGPT (2025)
 *}

<div id="boxnowCard" class="boxnow card">
	<div class="card-header">
		<h3 class="card-header-title">{l s='BoxNow Voucher / Information' mod='boxnow'}</h3>
	</div>

	<div class="card-body">
		<p class="mb-1"><strong>{l s='BoxNow information' mod='boxnow'}:</strong></p>
		<p>
			{l s='Locker ID' mod='boxnow'}: {$boxnow_locker_id}
			{if $boxnow_vouchers == 0}
				<a href="#" class="boxnow-edit-locker" data-toggle="modal" data-target="#changeLocker" data-order-id="{$boxnow_id_order}">
					<i class="material-icons" aria-hidden="true">replay</i>
					<span>{l s='Change' mod='boxnow'}</span>
				</a>
			{/if}
		</p>
		<p>{$boxnow_locker_name}<br />
			{l s='Address' mod='boxnow'}: {$boxnow_locker_address}
		</p>

		{if $boxnow_vouchers == 0}
			<p class="mb-1"><strong>{l s='Create BoxNow Voucher(s)' mod='boxnow'}</strong></p>

			<div class="form-group">
				<label class="form-control-label" for="warehouses">{l s='Select Warehouse' mod='boxnow'}:</label>
				<select id="warehouses" name="warehouses" class="custom-select">
					{foreach from=$boxnow_warehouses item=warehouse}
						{assign var=warehouseid value="["|explode:$warehouse}
						<option value="{$warehouseid[0]|trim}">{$warehouse}</option>
					{/foreach}
				</select>
			</div>

			<input type="hidden" id="boxnowid" value="{$boxnow_id_order}" />
			<br><br>
			<div class="form-group">
	<label class="form-control-label float-left" for="Compartment_Size">{l s='Compartment Size' mod='boxnow'}:</label>
	<select id="Compartment_Size" name="Compartment_Size" class="custom-select">
		<option value="1">{l s='Small' mod='boxnow'}</option>
		<option value="2">{l s='Medium' mod='boxnow'}</option>
		<option value="3">{l s='Large' mod='boxnow'}</option>
	</select>
	<button type="submit" class="btn btn-primary float-left" style="margin-top:1rem;" id="create_vouchers">
		{l s='Create Vouchers' mod='boxnow'}
	</button>
	<div class="clearfix"></div>
</div>

		{else}
			<p class="mb-1"><strong>{l s='Order Voucher(s)' mod='boxnow'}:</strong></p>
			{foreach from=$boxnow_parcel_ids item=voucher_number}
				{if $voucher_number != ''}
					<a href="{Context::getContext()->link->getBaseLink('index', true)}modules/boxnow/printVoucher.php?voucher={$voucher_number}"
					   target="_blank" title="{l s='Print Voucher' mod='boxnow'}">
						<i class="material-icons" aria-hidden="true">print</i> {$voucher_number}
					</a>
					<a href="#" class="boxnow-cancel-voucher" data-order-id="{$boxnow_id_order}" data-voucher="{$voucher_number}"
					   onclick="return confirm('{l s='Are you sure you want to cancel voucher?' mod='boxnow'}')">
						<i class="material-icons" aria-hidden="true">cancel</i>
						<span>{l s='Cancel Voucher' mod='boxnow'}</span>
					</a><br />
				{/if}
			{/foreach}
		{/if}
		<p class="error text-danger mt-2"></p>
	</div>
</div>

<!-- Change Locker Modal -->
<div class="modal fade" id="changeLocker" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">{l s='Change BoxNow Locker' mod='boxnow'}</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="{l s='Close' mod='boxnow'}">
					<span aria-hidden="true">×</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="form-group">
					<label for="locker_id" class="form-control-label">{l s='New Locker ID' mod='boxnow'}:</label>
					<input type="text" id="locker_id" name="locker_id" class="form-control locker-field" />
					<p>{l s='Enter the new locker id. A new email will be sent to the customer with updated information.' mod='boxnow'}</p>
				</div>
			</div>
			<div class="modal-footer">
				<input type="hidden" id="boxnow_id" value="{$boxnow_id_order}" />
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
$(function () {
	// Hide update shipping button
	$('.js-update-shipping-btn').hide();

	// Cancel Voucher
	$('.boxnow-cancel-voucher').on('click', function () {
		const orderId = $(this).data("order-id");
		const voucher = $(this).data("voucher");
		const linkCancel = '{Context::getContext()->link->getBaseLink('index', true)}modules/boxnow/cancelVoucher.php';
		if (orderId > 0) {
			$.post(linkCancel, { order_id: orderId, voucher_number: voucher }, function () {
								alert('Voucher Cancelled Successfully');
				location.reload();
			});
		}
	});

	// Create Vouchers
	$('#create_vouchers').on('click', function () {
		const button = $(this);
		const warehouse = $('#warehouses').val();
		const compartmentSize = parseInt($('#Compartment_Size').val());
		const orderId = $('#boxnowid').val();
		const linkCreate = '{Context::getContext()->link->getBaseLink('index', true)}modules/boxnow/createVoucher.php';

		if (!orderId || !warehouse || !compartmentSize) {
			$('.error').html('{l s="Please fill in all fields correctly." mod="boxnow"}');
			return;
		}

		button.prop('disabled', true); // Disable to prevent double submission

		$.post(linkCreate, {
			order_id: orderId,
			warehouses: warehouse,
			Compartment_Size: compartmentSize
		}, function (data) {
			if (data === '') {
				alert('Voucher Created Succesfully');
				location.reload();
			} else {
				$('.error').html(data);
				button.prop('disabled', false); // Re-enable on error
			}
		});
	});

	// Change Locker
	$('#boxnow-change-locker').on('click', function () {
		const lockerId = $('#locker_id').val().trim();
		const orderId = $('#boxnow_id').val();
		const linkLocker = '{Context::getContext()->link->getBaseLink('index', true)}modules/boxnow/changeLocker.php';

		if (!lockerId) {
			alert('{l s="Please enter a valid Locker ID." mod="boxnow"}');
			return;
		}

		$.post(linkLocker, { order_id: orderId, locker_id: lockerId }, function (data) {
			if (data === 'error') {
				alert('{l s="Error! Check the locker ID!" mod="boxnow"}');
			} else {
				alert(data);
				location.reload();
			}
		});
	});
});
</script>
