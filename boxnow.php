<?php
/**
 * 2007-2021 PrestaShop
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
 * @copyright 2007-2021 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}
include_once(_PS_MODULE_DIR_ . 'boxnow/classes/BoxnowEntry.php');

class Boxnow extends CarrierModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'boxnow';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.5';
        $this->author = 'BoxNow';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.5.0',
            'max' => '1.7.99',
        ];

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('BoxNow');
        $this->description = $this->l('Allow customer to pick up orders themselves in BoxNow lockers');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the BoxNow Delivery module?');
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

        $carrier = $this->addCarrier();
        $this->addZones($carrier);
        $this->addGroups($carrier);
        $this->addRanges($carrier);

        include(dirname(__FILE__) . '/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('actionCarrierUpdate') &&
			$this->registerHook('extraCarrier') &&
			$this->registerHook('updateCarrier') &&
            $this->registerHook('displayCarrierExtraContent') &&
            $this->registerHook('actionValidateOrder')&& 
			$this->registerHook('displayAdminOrder') && 
			$this->registerHook('displayAdminOrderLeft') && 
			$this->registerHook('displayAdminOrderMain') && 
			$this->registerHook('displayAdminOrderTop') && 
			$this->registerHook('displayAdminOrderSide') && 
			$this->registerHook('displayOrderConfirmation') &&
			$this->registerHook('displayOrderDetail') &&
			$this->registerHook('actionAdminControllerSetMedia');
    }


    public function uninstall()
    {
        Configuration::deleteByName('BOXNOW_API_URL');
        Configuration::deleteByName('BOXNOW_OAUTH_CLIENT_ID');
        Configuration::deleteByName('BOXNOW_OAUTH_CLIENT_SECRET');
        Configuration::deleteByName('BOXNOW_WAREHOUSE_NUMBER');
        Configuration::deleteByName('BOXNOW_PARTNER_ID');
        Configuration::deleteByName('BOXNOW_CONTACT_EMAIL');
        Configuration::deleteByName('BOXNOW_CONTACT_NAME');
        Configuration::deleteByName('BOXNOW_CONTACT_NUMBER');

        $this->removeCarrier();
		/*Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'boxnow_entries`');
		return true;*/

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
        if (((bool)Tools::isSubmit('submitBoxnowModule')) == true) {
            $this->postProcess();
        }

        // Make sure the existing carrier carries the hardcoded BoxNow locker dimensions
        $this->applyCarrierDimensions();

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

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('BoxNow configuration'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Your API URL'),
                        'name' => 'BOXNOW_API_URL',
                        'class' => 'lg',
                        'required' => true,
                        'desc' => 'Enter the base URL only without https prefix'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Your client ID'),
                        'name' => 'BOXNOW_OAUTH_CLIENT_ID',
                        'class' => 'lg',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Your client secret'),
                        'name' => 'BOXNOW_OAUTH_CLIENT_SECRET',
                        'class' => 'lg',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Your warehouse number(s)'),
                        'name' => 'BOXNOW_WAREHOUSE_NUMBER',
                        'class' => 'lg',
                        'required' => true,
                        'desc' => $this->l('Enter your warehouse number(s). If you have more than one warehouse seperate numbers by comma (example: 1234 [Main Warehouse], 1235 [Supplier])')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Your partner ID'),
                        'name' => 'BOXNOW_PARTNER_ID',
                        'class' => 'lg',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Contact email'),
                        'name' => 'BOXNOW_CONTACT_EMAIL',
                        'class' => 'lg',
                        'required' => true,
                        'desc' => $this->l('To this email, we will send PDF labels to be printed out and attached to each parcel.')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Contact name'),
                        'name' => 'BOXNOW_CONTACT_NAME',
                        'class' => 'lg',
                        'required' => false,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Contact phone number'),
                        'name' => 'BOXNOW_CONTACT_NUMBER',
                        'class' => 'lg',
                        'required' => false,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
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

        $carrier->name = $this->l('BoxNow');
        $carrier->is_module = true;
        $carrier->active = 1;
        $carrier->need_range = 1;
        $carrier->shipping_external = true;
        $carrier->range_behavior = 0;
		$carrier->url = 'https://track.boxnow.bg/?track=@';
        $carrier->external_module_name = $this->name;
        $carrier->shipping_method = 2;
        // Don't add the global handling fee (PS_SHIPPING_HANDLING) on top of our price
        $carrier->shipping_handling = false;
        // BoxNow locker max parcel dimensions (cm) and weight (kg)
        $carrier->max_width = 45;
        $carrier->max_height = 36;
        $carrier->max_depth = 60;
        $carrier->max_weight = 20;

        foreach (Language::getLanguages() as $lang)
            $carrier->delay[$lang['id_lang']] = $this->l('Pick up your order in BoxNow lockers');

        if ($carrier->add() == true) {
            @copy(dirname(__FILE__) . '/views/img/carrier_image.png', _PS_SHIP_IMG_DIR_ . '/' . (int)$carrier->id . '.jpg');
            Configuration::updateValue('BOXNOW_CARRIER_ID', (int)$carrier->id);
            return $carrier;
        }

        return false;
    }

    protected function applyCarrierDimensions()
    {
        if (!Configuration::hasKey('BOXNOW_CARRIER_ID')) {
            return;
        }

        $carrier = new Carrier((int)Configuration::get('BOXNOW_CARRIER_ID'));
        if (Validate::isLoadedObject($carrier)) {
            $carrier->max_width = 45;
            $carrier->max_height = 36;
            $carrier->max_depth = 60;
            $carrier->max_weight = 20;
            $carrier->shipping_handling = false;
            $carrier->url = 'https://track.boxnow.bg/?track=@';
            $carrier->update();
        }
    }

    protected function removeCarrier()
    {
        if (Configuration::hasKey('BOXNOW_CARRIER_ID')) {
            $carrier = new Carrier((int)Configuration::get('BOXNOW_CARRIER_ID'));
            $carrier->delete();
        }

        return true;
    }

    protected function addGroups($carrier)
    {
        $groups_ids = array();
        $groups = Group::getGroups(Context::getContext()->language->id);
        foreach ($groups as $group)
            $groups_ids[] = $group['id_group'];

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

        foreach ($zones as $zone)
            $carrier->addZone($zone['id_zone']);
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
        $id_carrier_old = (int)$params['id_carrier'];
        $id_carrier_new = (int)$params['carrier']->id;
        if ($id_carrier_old === (int)Configuration::get('BOXNOW_CARRIER_ID')) {
            Configuration::updateValue('BOXNOW_CARRIER_ID', $id_carrier_new);
        }
    }

    public function hookExtraCarrier($params)
    {
        $id_cart = $params['cart']->id;

        if ($params['cart']->id_carrier != (int)Configuration::get('BOXNOW_CARRIER_ID')) {
            return $this->context->smarty->fetch($this->local_path . 'views/templates/hooks/reset.tpl');
        }

        $entry = BoxnowEntry::fromCartId((int)$id_cart);
		$address = new Address($params['cart']->id_address_invoice, intval($cookie->id_lang));
		$zipcode = $address->postcode;
        $language_iso = !empty($this->context->language->iso_code) ? $this->context->language->iso_code : 'en';
		
        $this->context->smarty->assign(
            array(
                'boxnow_partner_id' => Configuration::get('BOXNOW_PARTNER_ID'),
                'boxnow_widget_url' => 'https://widget-v5.boxnow.bg/',
                'boxnow_widget_language' => $language_iso,
                'boxnow_select_endpoint' => $this->context->link->getModuleLink('boxnow', 'selection'),
                'boxnow_selected_entry' => $entry,
                'boxnow_id_cart' => $params['cart']->id,
                'boxnow_cart' => $zipcode
            )
        );

        return $this->context->smarty->fetch($this->local_path . 'views/templates/hooks/boxnow.tpl');
    }
	


	
	public function hookDisplayAdminOrderSide($param)
	{
		// >= 1.7.7
		$result = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'boxnow_entries` WHERE id_order = "' .
		(int)($param['id_order']) . '"');
			$boxnow_button = '';
		if (!empty($result['id_order']))
		{
			//get vouchers
			if(!empty($result['vouchers_numbers'])){
				$voucher_numbers = explode(',',$result['vouchers_numbers']);
			}
			//get warehouses
			if(!empty(Configuration::get('BOXNOW_WAREHOUSE_NUMBER'))){
				$warehouses = explode(',',Configuration::get('BOXNOW_WAREHOUSE_NUMBER'));
			}
			$language_iso = !empty($this->context->language->iso_code) ? $this->context->language->iso_code : 'en';
			$this->context->smarty->assign(
				array(
					'boxnow_id_order' => $result['id_order'],
					'boxnow_vouchers' => $result['vouchers'],
					'boxnow_locker_id' => $result['locker_id'],
					'boxnow_locker_name' => $result['locker_name'],
					'boxnow_locker_address' => $result['locker_address'],
					'boxnow_locker_post_code' => $result['locker_post_code'],
					'boxnow_vouchers_numbers' => $voucher_numbers,
					'boxnow_warehouses' => $warehouses,
					'boxnow_partner_id' => Configuration::get('BOXNOW_PARTNER_ID'),
					'boxnow_widget_url' => 'https://widget-v5.boxnow.bg/',
					'boxnow_widget_language' => $language_iso
				)
			);
			//return $this->context->smarty->fetch($this->local_path . 'views/templates/hooks/boxnow_voucher.tpl');
			return $this->display(__FILE__, 'views/templates/hooks/boxnow_voucher.tpl');
		} else {
			return '';
		}
	}
	


	
	public function hookDisplayAdminOrderLeft($param)
	{
		$result = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'boxnow_entries` WHERE id_order = "' .
		(int)($param['id_order']) . '"');
			$boxnow_button = '';
		if (!empty($result['id_order']))
		{
			//get vouchers
			if(!empty($result['vouchers_numbers'])){
				$voucher_numbers = explode(',',$result['vouchers_numbers']);
			}
			//get warehouses
			if(!empty(Configuration::get('BOXNOW_WAREHOUSE_NUMBER'))){
				$warehouses = explode(',',Configuration::get('BOXNOW_WAREHOUSE_NUMBER'));
			}
			$language_iso = !empty($this->context->language->iso_code) ? $this->context->language->iso_code : 'en';
			$this->context->smarty->assign(
				array(
					'boxnow_id_order' => $result['id_order'],
					'boxnow_vouchers' => $result['vouchers'],
					'boxnow_locker_id' => $result['locker_id'],
					'boxnow_locker_name' => $result['locker_name'],
					'boxnow_locker_address' => $result['locker_address'],
					'boxnow_locker_post_code' => $result['locker_post_code'],
					'boxnow_vouchers_numbers' => $voucher_numbers,
					'boxnow_warehouses' => $warehouses,
					'boxnow_partner_id' => Configuration::get('BOXNOW_PARTNER_ID'),
					'boxnow_widget_url' => 'https://widget-v5.boxnow.bg/',
					'boxnow_widget_language' => $language_iso
				)
			);
			//return $this->context->smarty->fetch($this->local_path . 'views/templates/hooks/boxnow_voucher.tpl');
			return $this->display(__FILE__, 'views/templates/hooks/boxnow_voucher.tpl');
		} else {
			return '';
		}
	}

    public function hookActionAdminControllerSetMedia(array $params)
    {
        $this->context->controller->addCss($this->getPathUri() . '/views/css/back.css');
    }
	
    public function hookActionValidateOrder($params)
    {	

        $id = $params['order']->id;

        $order = new Order((int)$id);

        //Check if the order is using the BoxNow Carrier
        if ($order->id_carrier != Configuration::get('BOXNOW_CARRIER_ID')) {
            return;
        }


		$sql = 'UPDATE '._DB_PREFIX_.'boxnow_entries SET id_order='.$id.' WHERE id_cart='.$order->id_cart;
		if (!Db::getInstance()->execute($sql)) die('error!');
		
        $address = new Address((int)$order->id_address_delivery);
        $customer = new Customer((int)$order->id_customer);
        $boxnow_entry = BoxnowEntry::fromCartId($order->id_cart);

        $boxnow_entry->id_order = $id;
        $boxnow_entry->save();
    }
    public function hookDisplayOrderConfirmation($params)
    {	

        $id = $_REQUEST['id_order'];

		$boxdata = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'boxnow_entries WHERE id_order='.$id);
		if($boxdata['id_order']){
			$lockerdata = $boxdata['locker_name'].', '.$this->l('Address').': '.$boxdata['locker_address'].', '.$boxdata['locker_post_code'];
		} else {$lockerdata = '';}
			$this->context->smarty->assign(array(
			  'lockermsg' => $lockerdata
			));
			/*return $this->fetch(
			   'module:boxnow/views/templates/hooks/locker_msg.tpl'
			);*/
			return $this->display(__FILE__, 'views/templates/hooks/locker_msg.tpl');
    }
    public function hookDisplayOrderDetail($params)
    {	

        $id = $params['order']->id;

		$boxdata = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'boxnow_entries WHERE id_order='.$id);
		if($boxdata['id_order']){
		$lockerdata = $boxdata['locker_name'].', '.$this->l('Address').': '.$boxdata['locker_address'];
		} else {$lockerdata = '';}
			$this->context->smarty->assign(array(
			  'lockermsg' => $lockerdata
			));
			/*return $this->fetch(
			   'module:boxnow/views/templates/hooks/locker_msg.tpl'
			);*/
			return $this->display(__FILE__, 'views/templates/hooks/locker_msg.tpl');
    }

    private function formatPhone($numOld)
    {
        return preg_replace('/^(?:\+?30|0)?/', '+30', $numOld);
    }
}
