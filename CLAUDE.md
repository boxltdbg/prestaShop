# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PrestaShop 1.7 carrier module (`CarrierModule`) that integrates BoxNow parcel locker delivery for Bulgaria. The module installs a carrier, renders a locker-picker widget at checkout, and provides an admin panel for creating/cancelling/printing shipping labels via the BoxNow REST API.

**No build system, no test suite, no package manager.** PHP files are deployed directly into the PS modules directory. The module must be installed/uninstalled from the PS back office.

## Architecture

### Entry Points

| File | Role |
|------|------|
| `boxnow.php` | Main module class (`Boxnow extends CarrierModule`). All PS hooks, carrier registration, config form. |
| `classes/BoxnowEntry.php` | `ObjectModel` for the `ps_boxnow_entries` table — links cart/order to selected locker. |
| `controllers/front/selection.php` | PS front controller (`ModuleFrontController`) — AJAX POST from checkout widget to save locker selection. |
| `createVoucher.php` | Standalone AJAX script (included from admin panel JS). Creates shipping label(s) via BoxNow API. |
| `cancelVoucher.php` | Standalone AJAX — cancels a voucher via BoxNow API. |
| `changeLocker.php` | Standalone AJAX — updates chosen locker and emails the customer. |
| `printVoucher.php` | Standalone AJAX — streams PDF label from BoxNow API. |

### Standalone vs. PS-Framework Scripts

`createVoucher.php`, `cancelVoucher.php`, `changeLocker.php`, and `printVoucher.php` bootstrap PrestaShop themselves by including `../../config/config.inc.php` and `../../init.php`. They use **PDO directly** for their own queries (not the PS `Db` class) and fetch API config from `ps_configuration` via PDO. They are called from `views/templates/hooks/boxnow_voucher.tpl` in the admin order panel.

### Data Flow

1. **Checkout**: `hookDisplayCarrierExtraContent` → `boxnow.tpl` renders map widget (popup or iframe) → customer picks locker → jQuery AJAX POST to `selection.php` → saves `BoxnowEntry` (cart-linked).
2. **Order placed**: `hookActionValidateOrder` → updates `BoxnowEntry.id_order` from `id_cart`.
3. **Admin panel**: `hookDisplayAdminOrderSide` renders `boxnow_voucher.tpl` → merchant triggers `createVoucher.php` (AJAX POST) → BoxNow API creates parcel → voucher ID stored in `vouchers_numbers` column.

### Database Table: `ps_boxnow_entries`

Columns: `id_boxnow_entry`, `id_cart`, `id_order`, `locker_id`, `locker_name`, `locker_address`, `locker_post_code`, `warehouse_id`, `payment_type`, `vouchers` (count), `vouchers_numbers` (CSV of parcel IDs), `submitted`.

### Configuration Keys (`ps_configuration`)

| Key | Purpose |
|-----|---------|
| `BOXNOW_CARRIER_ID` | PS carrier ID — updated automatically on carrier edit via `hookUpdateCarrier` |
| `BOXNOW_API_URL` | Base URL **without** `https://` prefix |
| `BOXNOW_PARTNER_ID` | Used in the map widget JS config |
| `BOXNOW_WAREHOUSE_NUMBER` | Comma-separated: `"1234 [Name],5678 [Name2]"` |
| `BOXNOW_OAUTH_CLIENT_ID` / `BOXNOW_OAUTH_CLIENT_SECRET` | API OAuth credentials |
| `BOXNOW_MAP_MODE` | `popup` or `iframe` |
| `BOXNOW_BUTTON_COLOR` / `BOXNOW_BUTTON_TEXT` | Popup button customisation |

## Important Patterns and Gotchas

### Smarty Templates
- **Never** access Smarty variables that may be undefined inside JS blocks — Smarty processes them even inside `<script>` comments. Use `{if $var}{$var}{else}default{/if}` guards, especially for numeric values (e.g. `partnerId`), to avoid JS syntax errors from empty interpolation like `partnerId: ,`.
- The `{literal}...{/literal}` block is required around any JS that contains `{` or `}`.

### Widget Reinitialization (boxnow.tpl)
PS 1.7 checkout rerenders the carrier area via AJAX without a full page reload. The widget script is loaded using a remove-old-then-add-new pattern keyed on `id="boxnow-widget-script"` to force re-execution. The "Continue" button validation uses a delegated jQuery handler (`$(document).off('click.boxnow').on('click.boxnow', ...)`) because the `load` event has already fired by the time the widget is injected.

### Carrier ID Check in hookDisplayCarrierExtraContent
`$params['carrier']` can be either an array or an object depending on the PS version. The hook explicitly handles both shapes and returns `''` early if the carrier is not the BoxNow carrier — without this, the widget renders for every carrier at checkout.

### Phone Formatting
`Boxnow::formatPhone($phone)` (static) normalises Bulgarian numbers to `+359` international format. Used in `createVoucher.php` for the delivery request.

### Payment Mode Detection
In `createVoucher.php`: if `$objOrder->module == 'ps_cashondelivery'` → `paymentMode = 'cod'`; otherwise `'prepaid'`. The amount to collect is set only for COD orders.
