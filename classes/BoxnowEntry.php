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
class BoxnowEntry extends ObjectModel
{
    public $id_boxnow_entry;
    public $id_cart;
    public $id_order;
    public $locker_id;
    public $locker_name;
    public $locker_address;
    public $locker_post_code;
   // public $submitted;

    public static $definition = array(
        'table' => 'boxnow_entries',
        'primary' => 'id_boxnow_entry',
        'multilang' => false,
        'fields' => array(
            'id_cart' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'locker_id' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName'],
            'locker_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName'],
            'locker_address' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName'],
            'locker_post_code' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName'],
            //'submitted' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
        ),
    );

    static public function fromCartId($id_cart)
    {
        $sql = '
            SELECT `id_boxnow_entry`
            FROM `' . _DB_PREFIX_ . 'boxnow_entries`
            WHERE `id_cart` = ' . $id_cart;

        $result = Db::getInstance()->getRow($sql);

        if ($result && isset($result['id_boxnow_entry'])) {
            return new BoxnowEntry($result['id_boxnow_entry']);
        } else {
            return new BoxnowEntry();
        }
    }
}
