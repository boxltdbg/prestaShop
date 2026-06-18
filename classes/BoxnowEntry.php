<?php

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