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
    print_r("PS Version exit");
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
        $this->version = '1.0.6';
        $this->author = 'BOX NOW';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0',
            'max' => '1.7.9.9',
        ];

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('BOX NOW');
        $this->description = $this->l('Позволява на Вашите клиенти да вземат своите пратки от автомати на BOX NOW');

        $this->confirmUninstall = $this->l('Сигурни ли сте, че искате да деинсталирате модула BOX NOW Доставка?');
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

        if (!Configuration::hasKey('BOXNOW_MAP_MODE')) {
            Configuration::updateValue('BOXNOW_MAP_MODE', 'popup');
        }

        if (!Configuration::hasKey('BOXNOW_BUTTON_COLOR')) {
            Configuration::updateValue('BOXNOW_BUTTON_COLOR', '#84C33F');
        }

        if (!Configuration::hasKey('BOXNOW_BUTTON_TEXT')) {
            Configuration::updateValue('BOXNOW_BUTTON_TEXT', 'Изберете BOX NOW автомат');
        }

        $carrier = $this->addCarrier();
        $this->addZones($carrier);
        $this->addGroups($carrier);
        $this->addRanges($carrier);

        include(dirname(__FILE__) . '/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('updateCarrier') &&
            $this->registerHook('displayCarrierExtraContent') &&
            $this->registerHook('actionValidateOrder')&& 
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
        $this->runMigrations();

        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitBoxnowModule')) == true) {
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
                    'title' => $this->l('BOX NOW конфигурация'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Вашият API URL'),
                        'name' => 'BOXNOW_API_URL',
                        'class' => 'lg',
                        'required' => true,
                        'desc' => $this->l('Въведете базовия URL без https:// префикс')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Вашият Client ID'),
                        'name' => 'BOXNOW_OAUTH_CLIENT_ID',
                        'class' => 'lg',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Вашият Client Secret'),
                        'name' => 'BOXNOW_OAUTH_CLIENT_SECRET',
                        'class' => 'lg',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Вашият номер/а на склад'),
                        'name' => 'BOXNOW_WAREHOUSE_NUMBER',
                        'class' => 'lg',
                        'required' => true,
                        'desc' => $this->l('Въведете номера на Вашия склад/складове (Пример: 1234 [Основен склад], 1235 [Склад 2])')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Вашият Partner ID'),
                        'name' => 'BOXNOW_PARTNER_ID',
                        'class' => 'lg',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Имейл за връзка'),
                        'name' => 'BOXNOW_CONTACT_EMAIL',
                        'class' => 'lg',
                        'required' => true,
                        'desc' => $this->l('Вашият имейл адрес за връзка')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Контактно лице'),
                        'name' => 'BOXNOW_CONTACT_NAME',
                        'class' => 'lg',
                        'required' => false,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Телефон за връзка'),
                        'name' => 'BOXNOW_CONTACT_NUMBER',
                        'class' => 'lg',
                        'required' => false,
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('BOX NOW режим на карта с автомати'),
                        'name' => 'BOXNOW_MAP_MODE',
                        'values' => array(
                            array(
                                'id' => 'popup',
                                'value' => 'popup',
                                'label' => $this->l('Поп-ъп карта')
                            ),
                            array(
                                'id' => 'iframe',
                                'value' => 'iframe',
                                'label' => $this->l('Вградена карта (iframe)')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Текст на бутона за BOX NOW'),
                        'name' => 'BOXNOW_BUTTON_TEXT',
                        'class' => 'lg',
                        'required' => false,
                        'desc' => $this->l('Редактирайте текста на бутона, когато изборът на автомат е чрез "popup" карта.')
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Цвят на бутона за BOX NOW'),
                        'name' => 'BOXNOW_BUTTON_COLOR',
                        'class' => 'lg',
                        'required' => false,
                        'desc' => $this->l('Редактирайте цвета на бутона, когато изборът на автомат е чрез "popup" карта.')
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Запази'),
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
            'BOXNOW_MAP_MODE' => Configuration::get('BOXNOW_MAP_MODE', 'popup'),  //popup | iframe
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
        $carrier->max_weight = 20;
        $carrier->max_width = 45;
        $carrier->max_height = 36;
        $carrier->max_depth = 60;

        foreach (Language::getLanguages() as $lang)
            $carrier->delay[$lang['id_lang']] = $this->l('Вземете своята поръчка от BOX NOW автомат');

        if ($carrier->add() == true) {
            @copy(dirname(__FILE__) . '/views/img/carrier_image.png', _PS_SHIP_IMG_DIR_ . '/' . (int)$carrier->id . '.jpg');
            Configuration::updateValue('BOXNOW_CARRIER_ID', (int)$carrier->id);

            $saved_payments = Configuration::get('BOXNOW_SAVED_PAYMENTS');
            if ($saved_payments) {
                foreach (explode(',', $saved_payments) as $id_module) {
                    Db::getInstance()->execute(
                        'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'carrier_payment` (`id_carrier`, `id_module`) VALUES ('
                        . (int)$carrier->id . ', ' . (int)$id_module . ')'
                    );
                }
                Configuration::deleteByName('BOXNOW_SAVED_PAYMENTS');
            }

            return $carrier;
        }

        return false;
    }

    protected function removeCarrier()
    {
        $old_carrier_id = (int)Configuration::get('BOXNOW_CARRIER_ID');
        if ($old_carrier_id) {
            $payments = Db::getInstance()->executeS(
                'SELECT `id_module` FROM `' . _DB_PREFIX_ . 'carrier_payment` WHERE `id_carrier` = ' . $old_carrier_id
            );
            if (!empty($payments)) {
                Configuration::updateValue('BOXNOW_SAVED_PAYMENTS', implode(',', array_column($payments, 'id_module')));
            }
        }

        // Find by external_module_name — reliable even if BOXNOW_CARRIER_ID config is missing/stale
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'carrier` SET `deleted` = 1, `active` = 0'
            . ' WHERE `external_module_name` = \'boxnow\' AND `deleted` = 0'
        );
        Configuration::deleteByName('BOXNOW_CARRIER_ID');

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

    public function hookDisplayCarrierExtraContent(array $params)
    {
        // Only render for the BoxNow carrier — critical to prevent widget rendering for other carriers.
        // PS passes $params['carrier'] as either an array or an object depending on version.
        $carrier_obj = isset($params['carrier']) ? $params['carrier'] : null;
        if (is_array($carrier_obj)) {
            $carrier_id = isset($carrier_obj['id']) ? (int)$carrier_obj['id']
                        : (isset($carrier_obj['id_carrier']) ? (int)$carrier_obj['id_carrier'] : 0);
        } elseif (is_object($carrier_obj)) {
            $carrier_id = (int)$carrier_obj->id;
        } else {
            $carrier_id = 0;
        }
        if ($carrier_id !== (int)Configuration::get('BOXNOW_CARRIER_ID')) {
            return '';
        }

        $id_cart = $params['cart']->id;

        $entry = BoxnowEntry::fromCartId($id_cart);

        $this->context->smarty->assign(
            array(
                'boxnow_partner_id' => Configuration::get('BOXNOW_PARTNER_ID'),
                'boxnow_map_mode' => Configuration::get('BOXNOW_MAP_MODE'),
                'boxnow_button_color' => Configuration::get('BOXNOW_BUTTON_COLOR'),
                'boxnow_button_text' => Configuration::get('BOXNOW_BUTTON_TEXT'),
                'boxnow_select_endpoint' => $this->context->link->getModuleLink('boxnow', 'selection'),
                'boxnow_selected_entry' => $entry,
                'boxnow_id_cart' => $params['cart']->id,
                'boxnow_cart' => $params['cart']->id_address_invoice
            )
        );

        return $this->context->smarty->fetch($this->local_path . 'views/templates/hooks/boxnow.tpl');
    }
	


	
	public function hookDisplayAdminOrderSide($param)
	{
		$this->runMigrations();
		$result = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'boxnow_entries` WHERE id_order = "' .
		(int)($param['id_order']) . '"');
			$boxnow_button = '';
		if (!empty($result['id_order']))
		{
			$voucher_numbers = array();
			$warehouses = array();
			//get vouchers
			if(!empty($result['vouchers_numbers'])){
				$voucher_numbers = explode(',',$result['vouchers_numbers']);
			}
			//get warehouses
			if(!empty(Configuration::get('BOXNOW_WAREHOUSE_NUMBER'))){
				$warehouses = explode(',',Configuration::get('BOXNOW_WAREHOUSE_NUMBER'));
			}
			$order = new Order($result['id_order']); 
			$products = $order->getProducts(); 
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
					'total_order_products' => $products,
					'boxnow_partner_id' => Configuration::get('BOXNOW_PARTNER_ID'),
				)
			);
			return $this->context->smarty->fetch($this->local_path . 'views/templates/hooks/boxnow_voucher.tpl');
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
        $this->runMigrations();

        $id = $params['order']->id;

        $order = new Order((int)$id);

        //Check if the order is using the BoxNow Carrier
        if ($order->id_carrier != Configuration::get('BOXNOW_CARRIER_ID')) {
            return;
        }


		$sql = 'UPDATE '._DB_PREFIX_.'boxnow_entries SET id_order='.$id.' WHERE id_cart='.$order->id_cart;
		Db::getInstance()->execute($sql);
		
        $address = new Address((int)$order->id_address_delivery);
        $customer = new Customer((int)$order->id_customer);
        $boxnow_entry = BoxnowEntry::fromCartId($order->id_cart);

        $boxnow_entry->id_order = $id;
        $boxnow_entry->save();
    }
    public function hookDisplayOrderConfirmation($params)
    {	

        $id = (int)Tools::getValue('id_order', isset($params['order']) ? $params['order']->id : 0);

		$boxdata = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'boxnow_entries WHERE id_order='.(int)$id);
		if(is_array($boxdata) && !empty($boxdata['id_order'])){
		$lockerdata = $boxdata['locker_name'].', '.$this->l('Адрес').': '.$boxdata['locker_address'].', '.$boxdata['locker_post_code'];
		} else {$lockerdata = '';}
			$this->context->smarty->assign(array(
			  'lockermsg' => $lockerdata
			));
			return $this->fetch(
			   'module:boxnow/views/templates/hooks/locker_msg.tpl'
			);
    }
    public function hookDisplayOrderDetail($params)
    {	

        $id = $params['order']->id;

		$boxdata = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'boxnow_entries WHERE id_order='.(int)$id);
		if(is_array($boxdata) && !empty($boxdata['id_order'])){
		$lockerdata = $boxdata['locker_name'].', '.$this->l('Адрес').': '.$boxdata['locker_address'].', '.$boxdata['locker_post_code'];
		} else {$lockerdata = '';}
			$this->context->smarty->assign(array(
			  'lockermsg' => $lockerdata
			));
			return $this->fetch(
			   'module:boxnow/views/templates/hooks/locker_msg.tpl'
			);
    }

    private function runMigrations()
    {
        if (Configuration::get('BOXNOW_DB_MIGRATED_V3')) {
            return;
        }
        $table = _DB_PREFIX_ . 'boxnow_entries';
        $cols = array(
            'id_order'         => "int(11) DEFAULT 0",
            'locker_id'        => "varchar(50) DEFAULT NULL",
            'locker_name'      => "varchar(50) DEFAULT NULL",
            'locker_address'   => "varchar(50) DEFAULT NULL",
            'locker_post_code' => "varchar(50) DEFAULT NULL",
            'warehouse_id'     => "varchar(50) DEFAULT NULL",
            'payment_type'     => "varchar(255) DEFAULT '1'",
            'vouchers'         => "int(2) DEFAULT 0",
            'vouchers_numbers' => "varchar(250) DEFAULT NULL",
            'submitted'        => "varchar(255) DEFAULT '0'",
        );
        foreach ($cols as $col => $definition) {
            $exists = (int)Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS'
                . ' WHERE TABLE_SCHEMA = DATABASE()'
                . ' AND TABLE_NAME = \'' . $table . '\''
                . ' AND COLUMN_NAME = \'' . $col . '\''
            );
            if (!$exists) {
                Db::getInstance()->execute(
                    'ALTER TABLE `' . $table . '` ADD COLUMN `' . $col . '` ' . $definition
                );
            }
        }
        Configuration::updateValue('BOXNOW_DB_MIGRATED_V3', 1);
    }

    public static function formatPhone($numOld)
    {
        return preg_replace('/^(?:\+?359|0)?/', '+359', $numOld);
    }
}
