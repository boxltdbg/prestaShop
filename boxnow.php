<?php

/**
 * 2007-2024 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2024 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    print_r("PS Version exit");
    exit;
}
include_once _PS_MODULE_DIR_ . 'boxnow/classes/BoxnowEntry.php';

class boxnow extends CarrierModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'boxnow';
        $this->tab = 'shipping_logistics';
        $this->version = '2.0';
        $this->author = 'BOX NOW';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.5.0',
            'max' => '9.0.1',
        ];

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('BOX NOW');
        $this->description = $this->l('The Future of Parcel Delivery! 24/7, Faster, Greener');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the BOX NOW Delivery module?');
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        // Register the module itself FIRST. If this fails there is nothing to roll back.
        if (!parent::install()) {
            return false;
        }

        // Default configuration values (idempotent).
        if (!Configuration::hasKey('BOXNOW_MAP_MODE')) {
            Configuration::updateValue('BOXNOW_MAP_MODE', 'popup');
        }
        if (!Configuration::hasKey('BOXNOW_BUTTON_COLOR')) {
            Configuration::updateValue('BOXNOW_BUTTON_COLOR', '#84C33F');
        }
        if (!Configuration::hasKey('BOXNOW_BUTTON_TEXT')) {
            Configuration::updateValue('BOXNOW_BUTTON_TEXT', 'Pick a locker');
        }

        // Create the carrier only once. Guard against a leftover/valid carrier so a
        // second install attempt never creates a duplicate BOX NOW carrier.
        $existingCarrierId = (int) Configuration::get('BOXNOW_CARRIER_ID');
        $existingCarrier = $existingCarrierId ? new Carrier($existingCarrierId) : null;
        if (!$existingCarrier || !Validate::isLoadedObject($existingCarrier) || $existingCarrier->deleted) {
            $carrier = $this->addCarrier();
            if (!$carrier || !Validate::isLoadedObject($carrier)) {
                $this->_errors[] = $this->l('Could not create the BOX NOW carrier during installation.');
                return false;
            }
            Configuration::updateValue('BOXNOW_CARRIER_ID', (int) $carrier->id);
            $this->addGroups($carrier);
        }

        // Execute the SQL installation script.
        include dirname(__FILE__) . '/sql/install.php';

        $boxnowCarrierId = (int) Configuration::get('BOXNOW_CARRIER_ID');
        $carrierDimensions = Db::getInstance()->execute(
            'UPDATE ' . _DB_PREFIX_ . 'carrier
             SET max_width = 100, max_height = 50, max_depth = 60, max_weight = 20
             WHERE id_carrier = ' . $boxnowCarrierId
        );
        if ($carrierDimensions) {
            PrestaShopLogger::addLog('BOXNOW: Dimensions added to Carrier', 1);
        } else {
            PrestaShopLogger::addLog('BOXNOW: Encountered error trying to add dimensions to Carrier', 3);
        }

        // Register hooks. Only hooks that have a matching hookXxx() method are listed:
        // PrestaShop 8.2 throws when registering a hook whose method is not defined,
        // which is what aborted the install on 8.2.2. Collect failures instead of
        // short-circuiting so a single failure cannot leave the module half-installed.
        $hooks = [
            'header',
            'updateCarrier',
            'displayCarrierExtraContent',
            'actionValidateOrder',
            'displayAdminOrderSide',
            'displayOrderConfirmation',
            'displayOrderDetail',
        ];
        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                PrestaShopLogger::addLog('BOXNOW: Failed to register hook ' . $hook, 3);
            }
        }

        return true;
    }

    /**
     * Handle the old carrier if the module was previously uninstalled.
     */
    protected function handleOldCarrier()
{
    $boxnowCarrierId = (int) Configuration::get('BOXNOW_CARRIER_ID');
    if (!$boxnowCarrierId) {
        PrestaShopLogger::addLog("BOXNOW: No Boxnow Carrier ID found in configuration.", 3);
        return;
    }

    // Step 1: Find carts using Boxnow carrier and without corresponding orders
    $cartIds = Db::getInstance()->executeS(
        'SELECT c.id_cart 
         FROM ' . _DB_PREFIX_ . 'cart AS c 
         WHERE c.id_carrier = ' . $boxnowCarrierId . ' 
         AND NOT EXISTS (
             SELECT 1 FROM ' . _DB_PREFIX_ . 'orders o 
             WHERE o.id_cart = c.id_cart
         )'
    );

    // Step 2: Delete carts that have no corresponding orders
    if (!empty($cartIds)) {
        foreach ($cartIds as $row) {
            $cartId = (int) $row['id_cart'];
            $cart = new Cart($cartId);
            if (Validate::isLoadedObject($cart)) {
                $cart->delete();
                PrestaShopLogger::addLog("BOXNOW: Deleted cart ID $cartId during uninstall.", 1);
            } else {
                PrestaShopLogger::addLog("BOXNOW: Failed to load cart ID $cartId during uninstall.", 3);
            }
        }
    }

    // Step 3: Remove Boxnow entries from the `boxnow_entries` table
    $result = Db::getInstance()->execute(
        'DELETE FROM ' . _DB_PREFIX_ . 'boxnow_entries 
         WHERE id_cart IN (
             SELECT c.id_cart 
             FROM ' . _DB_PREFIX_ . 'cart c 
             WHERE c.id_carrier = ' . $boxnowCarrierId . ' 
             AND NOT EXISTS (
                 SELECT 1 FROM ' . _DB_PREFIX_ . 'orders o 
                 WHERE o.id_cart = c.id_cart
             )
         )'
    );

    if ($result === false) {
        PrestaShopLogger::addLog("BOXNOW: Failed to delete entries from boxnow_entries for carrier ID $boxnowCarrierId.", 3);
    } else {
        PrestaShopLogger::addLog("BOXNOW: Deleted Boxnow entries for carrier ID $boxnowCarrierId.", 1);
    }
}


    public function uninstall()
    {
        $this->handleOldCarrier();
        Configuration::deleteByName('BOXNOW_API_URL');
        Configuration::deleteByName('BOXNOW_OAUTH_CLIENT_ID');
        Configuration::deleteByName('BOXNOW_OAUTH_CLIENT_SECRET');
        Configuration::deleteByName('BOXNOW_WAREHOUSE_NUMBER');
        Configuration::deleteByName('BOXNOW_PARTNER_ID');
        Configuration::deleteByName('BOXNOW_CONTACT_EMAIL');
        Configuration::deleteByName('BOXNOW_CONTACT_NAME');
        Configuration::deleteByName('BOXNOW_CONTACT_NUMBER');
        Configuration::deleteByName('BOXNOW_MAP_MODE');
        Configuration::deleteByName('BOXNOW_BUTTON_COLOR');
        Configuration::deleteByName('BOXNOW_BUTTON_TEXT');
        $this->removeCarrier();
        /* Under Review
        Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'boxnow_entries`');
        return true;*/
        //delete the table
        //include(dirname(__FILE__) . '/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submitBoxnowModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBoxnowModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm($this->getConfigForm());

    }

    /**
     * Create the structure of your form.
     */
 protected function getConfigForm()
{
    return [
        [
            'form' => [
                'legend' => [
                    'title' => $this->l('API Details'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Select API URL'),
                        'name' => 'BOXNOW_API_URL',
                        'required' => true,
                        'options' => [
                            'query' => [
                                ['id' => 'api-production.boxnow.gr', 'name' => 'GR Production Environment (api-production.boxnow.gr)'],
                                ['id' => 'api-stage.boxnow.gr', 'name' => 'GR Staging Environment (api-stage.boxnow.gr)'],
                                ['id' => 'api-production.boxnow.cy', 'name' => 'CY Production Environment (api-production.boxnow.cy)'],
                                ['id' => 'api-stage.boxnow.cy', 'name' => 'CY Staging Environment (api-stage.boxnow.cy)'],
                                ['id' => 'api-production.boxnow.bg', 'name' => 'BG Production Environment (api-production.boxnow.bg)'],
                                ['id' => 'api-stage.boxnow.bg', 'name' => 'BG Staging Environment (api-stage.boxnow.bg)'],
                                ['id' => 'api-production.boxnow.hr', 'name' => 'HR Production Environment (api-production.boxnow.hr)'],
                                ['id' => 'api-stage.boxnow.hr', 'name' => 'HR Staging Environment (api-stage.boxnow.hr)'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Select the API environment you want to use'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Client ID'),
                        'name' => 'BOXNOW_OAUTH_CLIENT_ID',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Client Secret'),
                        'name' => 'BOXNOW_OAUTH_CLIENT_SECRET',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Warehouse IDs (Multiple IDs separated by commas ",")'),
                        'name' => 'BOXNOW_WAREHOUSE_NUMBER',
                        'required' => true,
                        'desc' => $this->l('Enter your warehouse number(s). If you have more than one warehouse separate numbers by commas (example: 1234 [Main Warehouse], 1235 [Supplier])'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Partner ID'),
                        'name' => 'BOXNOW_PARTNER_ID',
                        'required' => true,
                    ],
                ],
            ],
        ],
        [
            'form' => [
                'legend' => [
                    'title' => $this->l('Contact Details'),
                    'icon' => 'icon-user',
                    'description' => $this->l('Contact details are also used for Parcel Returns'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Orders Contact Name'),
                        'name' => 'BOXNOW_CONTACT_NAME',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Orders Contact Email'),
                        'name' => 'BOXNOW_CONTACT_EMAIL',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Orders Contact Mobile Phone'),
                        'name' => 'BOXNOW_CONTACT_NUMBER',
                        'required' => true,
                    ],
                ],
            ],
        ],
        [
            'form' => [
                'legend' => [
                    'title' => $this->l('Widget Options'),
                    'icon' => 'icon-map-marker',
                ],
                'input' => [
                    [
                        'type' => 'radio',
                        'label' => $this->l('Widget Display Mode'),
                        'name' => 'BOXNOW_MAP_MODE',
                        'values' => [
                            [
                                'id' => 'popup',
                                'value' => 'popup',
                                'label' => $this->l('Popup Window'),
                            ],
                            [
                                'id' => 'iframe',
                                'value' => 'iframe',
                                'label' => $this->l('Embedded iFrame'),
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'form' => [
                'legend' => [
                    'title' => $this->l('Button & Customization'),
                    'icon' => 'icon-cog',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Change Button Text'),
                        'name' => 'BOXNOW_BUTTON_TEXT',
                        'required' => false,
                        'desc' => $this->l('Edit the text of the BOX NOW pick location selection button in popup mode'),
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Change Button Background Color'),
                        'name' => 'BOXNOW_BUTTON_COLOR',
                        'required' => false,
                        'desc' => $this->l('Edit the color of the BOX NOW pick location selection button in popup mode'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ],
    ];
}



    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'BOXNOW_API_URL' => Configuration::get('BOXNOW_API_URL', null),
            'BOXNOW_OAUTH_CLIENT_ID' => Configuration::get('BOXNOW_OAUTH_CLIENT_ID', null),
            'BOXNOW_OAUTH_CLIENT_SECRET' => Configuration::get('BOXNOW_OAUTH_CLIENT_SECRET', null),
            'BOXNOW_WAREHOUSE_NUMBER' => Configuration::get('BOXNOW_WAREHOUSE_NUMBER', null),
            'BOXNOW_PARTNER_ID' => Configuration::get('BOXNOW_PARTNER_ID', null),
            'BOXNOW_CONTACT_EMAIL' => Configuration::get('BOXNOW_CONTACT_EMAIL', null),
            'BOXNOW_CONTACT_NAME' => Configuration::get('BOXNOW_CONTACT_NAME', null),
            'BOXNOW_CONTACT_NUMBER' => Configuration::get('BOXNOW_CONTACT_NUMBER', null),
            'BOXNOW_MAP_MODE' => Configuration::get('BOXNOW_MAP_MODE', 'popup'), //popup | iframe
            'BOXNOW_BUTTON_COLOR' => Configuration::get('BOXNOW_BUTTON_COLOR', '#84C33F'),
            'BOXNOW_BUTTON_TEXT' => Configuration::get('BOXNOW_BUTTON_TEXT', 'Pick a locker'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        return $shipping_cost;
    }

    public function getOrderShippingCostExternal($params)
    {
        return false;
    }

    protected function addCarrier()
    {
        $carrier = new Carrier();

        $carrier->name = $this->l('BOX NOW');
        $carrier->is_module = true;
        $carrier->active = 1;
        $carrier->need_range = 1;
        $carrier->shipping_external = true;
        $carrier->range_behavior = 0;
        $carrier->url = 'https://track.boxnow.bg/?track=@';
        $carrier->external_module_name = $this->name;
        $carrier->shipping_method = 2;

        foreach (Language::getLanguages() as $lang) {
            $carrier->delay[$lang['id_lang']] = $this->l('Pick up your order in BOX NOW lockers');
        }

        if ($carrier->add() == true) {
            @copy(dirname(__FILE__) . '/views/img/carrier_image.png', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
            Configuration::updateValue('BOXNOW_CARRIER_ID', (int) $carrier->id);
            return $carrier;
        }

        return false;
    }

    protected function removeCarrier()
    {
        $carrierId = (int) Configuration::get('BOXNOW_CARRIER_ID');

        if (!$carrierId) {
            PrestaShopLogger::addLog('BOXNOW: Invalid carrier ID provided or fetched from configuration.', 3);
            return false;
        }

        $carrier = new Carrier($carrierId);
        if (Validate::isLoadedObject($carrier)) {
            $carrier->deleted = 1; // Mark as deleted
            $carrier->save();
        }

        return Configuration::deleteByName('BOXNOW_CARRIER_ID');
    }




    protected function addGroups($carrier)
    {
        $groups_ids = array();
        $groups = Group::getGroups(Context::getContext()->language->id);
        foreach ($groups as $group) {
            $groups_ids[] = $group['id_group'];
        }

        $carrier->setGroups($groups_ids);
    }

    protected function addRanges($carrier)
    {
        $range_price = new RangePrice();
        $range_price->id_carrier = $carrier->id;
        $range_price->delimiter1 = '0';
        $range_price->delimiter2 = '10000';
        $range_price->add();

        $range_weight = new RangeWeight();
        $range_weight->id_carrier = $carrier->id;
        $range_weight->delimiter1 = '0';
        $range_weight->delimiter2 = '20';
        $range_weight->add();
    }

    protected function addZones($carrier)
    {
        $zones = Zone::getZones();

        foreach ($zones as $zone) {
            $carrier->addZone($zone['id_zone']);
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    public function hookUpdateCarrier($params)
    {
        $id_carrier_old = (int) $params['id_carrier'];
        $id_carrier_new = (int) $params['carrier']->id;
        if ($id_carrier_old === (int) Configuration::get('BOXNOW_CARRIER_ID')) {
            Configuration::updateValue('BOXNOW_CARRIER_ID', $id_carrier_new);
        }
    }

    public function hookDisplayCarrierExtraContent(array $params)
    {
        $id_cart = $params['cart']->id;
        $entry = BoxnowEntry::fromCartId($id_cart);

        // The widget environment (and therefore which country's lockers are shown)
        // is driven by the API environment selected in the module configuration,
        // NOT by the customer's delivery address. This ensures a BG shop always
        // loads the Bulgarian widget/CDN, a HR shop the Croatian one, etc.
        $api_url = (string) Configuration::get('BOXNOW_API_URL');
        $selected_country = 'GR';
        if (strpos($api_url, '.bg') !== false) {
            $selected_country = 'BG';
        } elseif (strpos($api_url, '.cy') !== false) {
            $selected_country = 'CY';
        } elseif (strpos($api_url, '.hr') !== false) {
            $selected_country = 'HR';
        } elseif (strpos($api_url, '.gr') !== false) {
            $selected_country = 'GR';
        }
        PrestaShopLogger::addLog('BOXNOW: widget environment resolved to ' . $selected_country . ' from API URL "' . $api_url . '"', 1);

        $this->context->smarty->assign(array(
            'boxnow_partner_id' => Configuration::get('BOXNOW_PARTNER_ID'),
            'boxnow_map_mode' => Configuration::get('BOXNOW_MAP_MODE'),
            'boxnow_button_color' => Configuration::get('BOXNOW_BUTTON_COLOR'),
            'boxnow_button_text' => Configuration::get('BOXNOW_BUTTON_TEXT'),
            'boxnow_select_endpoint' => $this->context->link->getModuleLink('boxnow', 'selection'),
            'boxnow_selected_entry' => $entry,
            'boxnow_dynamic_id' => Configuration::get('BOXNOW_CARRIER_ID'),
            'boxnow_id_cart' => $params['cart']->id,
            'boxnow_cart' => $params['cart']->id_address_invoice,
            'selected_country' => $selected_country,
        ));

        switch ($selected_country) {
            case 'CY':
                $template_file = 'views/templates/hooks/boxnowcy.tpl';
                break;
            case 'BG':
                $template_file = 'views/templates/hooks/boxnowbg.tpl';
                break;
            case 'HR':
                $template_file = 'views/templates/hooks/boxnowhr.tpl';
                break;
            default:
                $template_file = 'views/templates/hooks/boxnow.tpl';
                break;
        }

        //PrestaShopLogger::addLog('Boxnow loading template: ' . $template_path . $template_file, 1);

        return $this->context->smarty->fetch($this->local_path . $template_file);
    }




    public function hookDisplayAdminOrderSide($param)
    {
        $result = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'boxnow_entries` WHERE id_order = "' .
            (int) ($param['id_order']) . '"');
        $boxnow_button = '';
        if (!empty($result['id_order'])) {
            // get vouchers
            $voucher_numbers = [];
            if (!empty($result['parcel_ids'])) {
                $voucher_numbers = explode(',', $result['parcel_ids']);
            }
            // get warehouses
            $warehouses = [];
            if (!empty(Configuration::get('BOXNOW_WAREHOUSE_NUMBER'))) {
                $warehouses = explode(',', Configuration::get('BOXNOW_WAREHOUSE_NUMBER'));
            }
            $this->context->smarty->assign(
                array(
                    'boxnow_id_order' => $result['id_order'],
                    'boxnow_vouchers' => $result['vouchers'],
                    'boxnow_locker_id' => $result['locker_id'],
                    'boxnow_locker_name' => $result['locker_name'],
                    'boxnow_locker_address' => $result['locker_address'],
                    'boxnow_locker_post_code' => $result['locker_post_code'],
                    'boxnow_parcel_ids' => $voucher_numbers,
                    'boxnow_warehouses' => $warehouses,
                )
            );
            return $this->context->smarty->fetch($this->local_path . 'views/templates/hooks/boxnow_voucher.tpl');
        } else {
            return '';
        }
    }

    public function hookActionValidateOrder($params)
    {
        $id = $params['order']->id;
        $order = new Order((int) $id);

        // Check if the order is using the BoxNow Carrier
        if ($order->id_carrier != Configuration::get('BOXNOW_CARRIER_ID')) {
            return;
        }

        // Check if cart ID is valid
        $id_cart = (int) $order->id_cart;
        if ($id_cart <= 0) {
            // Invalid cart ID, skip the update operation
            return;
        }

        // Update boxnow_entries table with order ID
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'boxnow_entries SET id_order=' . $id . ' WHERE id_cart=' . $id_cart;
        if (!Db::getInstance()->execute($sql)) {
            // Error handling
            // Consider logging the error or throwing an exception
            die('Error updating boxnow_entries table');
        }
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $id = $_REQUEST['id_order'];

        $boxdata = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'boxnow_entries WHERE id_order=' . $id);
        if ($boxdata['id_order']) {
            $lockerdata = $boxdata['locker_name'] . ', ' . $this->l('Address') . ': ' . $boxdata['locker_address'] . ', ' . $boxdata['locker_post_code'];
        } else {
            $lockerdata = '';
        }
        $this->context->smarty->assign(array(
            'lockermsg' => $lockerdata,
        ));
        return $this->fetch(
            'module:boxnow/views/templates/hooks/locker_msg.tpl'
        );
    }
    public function hookDisplayOrderDetail($params)
    {
        $id = $params['order']->id;

        $boxdata = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'boxnow_entries WHERE id_order=' . $id);
        if ($boxdata['id_order']) {
            $lockerdata = $boxdata['locker_name'] . ', ' . $this->l('Address') . ': ' . $boxdata['locker_address'] . ', ' . $boxdata['locker_post_code'];
        } else {
            $lockerdata = '';
        }
        $this->context->smarty->assign(array(
            'lockermsg' => $lockerdata,
        ));
        return $this->fetch(
            'module:boxnow/views/templates/hooks/locker_msg.tpl'
        );
    }

    public static function formatPhone($numOld, $apiUrl = null)
    {
        // Remove all non-digit characters for processing
        $digitsOnly = preg_replace('/[^\d]/', '', $numOld);
        
        // If API URL is provided and contains .bg, format as Bulgarian number
        if ($apiUrl && strpos($apiUrl, '.bg') !== false) {
            // If already starts with +359, return as is
            if (substr($numOld, 0, 4) === '+359') {
                return $numOld;
            }
            // If starts with 00359, convert to +359
            if (substr($numOld, 0, 5) === '00359' || substr($digitsOnly, 0, 4) === '359') {
                $remaining = preg_replace('/[^\d]/', '', substr($numOld, 5));
                return '+359' . $remaining;
            }
            // If starts with 0, remove it and add +359
            if (substr($numOld, 0, 1) === '0') {
                return '+359' . substr($digitsOnly, 1);
            }
            // If doesn't start with +, add +359
            if (substr($numOld, 0, 1) !== '+') {
                return '+359' . $digitsOnly;
            }
        }
        
        // Original logic for other countries
        // If the phone number doesn't start with "+", perform further checks
        if (substr($numOld, 0, 1) != '+') {
            // If the phone starts with "00", replace "00" with "+"
            if (substr($numOld, 0, 2) === '00') {
                $numOld = '+' . substr($numOld, 2);
            }
            // If the phone starts with the specified codes and has less than 9 digits, put "+357" in the beginning
            elseif (in_array(substr($numOld, 0, 2), ['22', '23', '24', '25', '26', '96', '97', '98', '99']) && strlen($digitsOnly) < 9) {
                $numOld = '+357' . $digitsOnly;
            }
            // If none of the above conditions match, put "+30" in the beginning
            else {
                $numOld = '+30' . $digitsOnly;
            }
        }
        return $numOld;
    }
    public function hookValidateCarrier($params)
    {
        $cart = $this->context->cart;

        if ((int)$cart->id_carrier !== (int)Configuration::get('BOXNOW_CARRIER_ID')) {
            return;
        }

        // Check for selected locker in custom table or cookie/session
        $lockerData = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'boxnow_entries WHERE id_cart = ' . (int)$cart->id);

        if (!$lockerData || empty($lockerData['locker_id'])) {
            // Locker not selected – throw error or redirect
            $this->context->controller->errors[] = $this->l('You must select a BOX NOW Locker before continuing.');
            // Optional: Redirect to the carrier step
            Tools::redirect('index.php?controller=order&step=2');
        }
    }
}
