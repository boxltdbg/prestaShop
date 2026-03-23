<?php

include_once(_PS_MODULE_DIR_ . 'boxnow/classes/BoxnowEntry.php');

class BoxnowSelectionModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        if (Tools::getValue('boxnow_selected')) {

            $boxnow_entry = BoxnowEntry::fromCartId((int)Tools::getValue('boxnow_cart_id'));
            $boxnow_entry->id_cart = (int)Tools::getValue('boxnow_cart_id');
            $boxnow_entry->locker_id = Tools::getValue('boxnow_locker_id');
            $boxnow_entry->locker_name = Tools::getValue('boxnow_locker_name');
            $boxnow_entry->locker_address = Tools::getValue('boxnow_locker_address');
            $boxnow_entry->locker_post_code = Tools::getValue('boxnow_locker_post_code');
            $boxnow_entry->submitted = false;

            $boxnow_entry->save();

            die(Tools::jsonEncode(array('status' => 'success')));
        }

        die(Tools::jsonEncode(array('status' => 'fail')));
    }
}