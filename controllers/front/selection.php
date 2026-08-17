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
include_once(_PS_MODULE_DIR_ . 'boxnow/classes/BoxnowEntry.php');

class BoxnowSelectionModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        if (Tools::getValue('boxnow_selected')) {
            $id_cart = Tools::getValue('boxnow_cart_id');
            $id = Tools::getValue('boxnow_id');
            $boxnow_entry = new BoxnowEntry();

        if ($id_cart > 0) {
        $boxnow_entry = BoxnowEntry::fromCartId($id_cart);
        } elseif ($id > 0) {
        $boxnow_entry = new BoxnowEntry($id);
        } else {
        $boxnow_entry = new BoxnowEntry();
        }
            $boxnow_entry->id_cart = Tools::getValue('boxnow_cart_id');
            $boxnow_entry->locker_id = Tools::getValue('boxnow_locker_id');
            $boxnow_entry->locker_name = Tools::getValue('boxnow_locker_name');
            $boxnow_entry->locker_address = Tools::getValue('boxnow_locker_address');
            $boxnow_entry->locker_post_code = Tools::getValue('boxnow_locker_post_code');
            $boxnow_entry->submitted = false;

            $boxnow_entry->save();

            die(json_encode(array('status' => 'success')));
        }

        die(json_encode(array('status' => 'fail')));
    }
}
