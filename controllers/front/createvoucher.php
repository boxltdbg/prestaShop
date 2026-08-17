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

class BoxnowCreateVoucherModuleFrontController extends ModuleFrontController
{
    /**
     * Endpoint hit by the "Create Vouchers" admin button (POST).
     * Outputs an empty string on success; an error message on failure
     * (the front-end JS treats '' as success).
     */
    public function initContent()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->respond('Method Not Allowed');
        }

        if (!Tools::getIsset('order_id')
            || !Tools::getIsset('voucher_number')
            || !Tools::getIsset('Compartment_Size')
            || !Tools::getIsset('warehouses')) {
            http_response_code(400);
            $this->respond('Missing required parameters.');
        }

        $order_id = (int) Tools::getValue('order_id');
        $comp_size = (int) Tools::getValue('Compartment_Size');
        $warehouse = Tools::getValue('warehouses');

        // API configuration
        $api_url = Configuration::get('BOXNOW_API_URL');
        $api_id = Configuration::get('BOXNOW_OAUTH_CLIENT_ID');
        $api_secret = Configuration::get('BOXNOW_OAUTH_CLIENT_SECRET');
        $api_email = Configuration::get('BOXNOW_CONTACT_EMAIL');
        $api_name = Configuration::get('BOXNOW_CONTACT_NAME');
        $api_contact = Configuration::get('BOXNOW_CONTACT_NUMBER');

        // Authenticate
        $auth_payload = json_encode(array(
            'grant_type' => 'client_credentials',
            'client_id' => $api_id,
            'client_secret' => $api_secret,
        ));
        $auth = curl_init('https://' . $api_url . '/api/v1/auth-sessions');
        curl_setopt($auth, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($auth, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($auth, CURLOPT_POST, 1);
        curl_setopt($auth, CURLOPT_POSTFIELDS, $auth_payload);
        curl_setopt($auth, CURLOPT_FOLLOWLOCATION, 1);
        $auth_response = curl_exec($auth);
        $auth_http_status = curl_getinfo($auth, CURLINFO_HTTP_CODE);
        $auth_json = json_decode($auth_response, true);
        curl_close($auth);

        if ($auth_http_status != 200 || empty($auth_json['access_token'])) {
            $this->respond('Authentication failed: ' . (isset($auth_json['message']) ? $auth_json['message'] : 'Unknown error'));
        }

        // Order info
        $objOrder = new Order($order_id);
        if (!Validate::isLoadedObject($objOrder)) {
            $this->respond('Order not found.');
        }
        $customer = new Customer($objOrder->id_customer);
        $address = new Address($objOrder->id_address_delivery);
        $products = $objOrder->getProducts();

        if ($objOrder->module == 'ps_cashondelivery') {
            $paymentMode = 'cod';
            $payment_type = 2;
            $amountcollect = number_format(floor($objOrder->total_paid * 100) / 100, 2, '.', '');
        } else {
            $paymentMode = 'prepaid';
            $payment_type = 1;
            $amountcollect = '0.00';
        }

        // Boxnow locker entry for this order
        $boxnoworder = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'boxnow_entries` WHERE id_order = ' . (int) $order_id
        );
        if (!$boxnoworder) {
            $this->respond('Boxnow order entry not found for this order.');
        }

        // Build the delivery request (json_encode handles escaping safely)
        $orderid = $order_id . uniqid();
        $addressphone = Boxnow::formatPhone($address->phone);
        $invoiceValue = number_format(floor($objOrder->total_paid * 100) / 100, 2, '.', '');

        $items = array();
        foreach ($products as $v) {
            $value = number_format(floor(($v['unit_price_tax_incl'] * $v['product_quantity']) * 100) / 100, 2, '.', '');
            $items[] = array(
                'id' => (string) $v['id_product'],
                'name' => $v['product_name'],
                'value' => $value,
                'weight' => (float) $v['product_weight'] * (int) $v['product_quantity'],
                'compartmentSize' => $comp_size,
            );
        }

        $postData = json_encode(array(
            'orderNumber' => $orderid,
            'invoiceValue' => $invoiceValue,
            'paymentMode' => $paymentMode,
            'amountToBeCollected' => (string) $amountcollect,
            'allowReturn' => true,
            'origin' => array(
                'contactNumber' => $api_contact,
                'contactEmail' => $api_email,
                'contactName' => $api_name,
                'locationId' => $warehouse,
            ),
            'destination' => array(
                'contactNumber' => $addressphone,
                'contactEmail' => $customer->email,
                'contactName' => $address->firstname . ' ' . $address->lastname,
                'name' => $boxnoworder['locker_name'],
                'addressLine1' => $address->address1,
                'locationId' => $boxnoworder['locker_id'],
            ),
            'items' => $items,
        ));

        $authorization = 'Authorization: Bearer ' . $auth_json['access_token'];
        $delivery_request = curl_init('https://' . $api_url . '/api/v1/delivery-requests');
        curl_setopt($delivery_request, CURLOPT_HTTPHEADER, array($authorization, 'Content-Type: application/json'));
        curl_setopt($delivery_request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($delivery_request, CURLOPT_POST, 1);
        curl_setopt($delivery_request, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($delivery_request, CURLOPT_FOLLOWLOCATION, 1);
        $delivery_response = curl_exec($delivery_request);
        curl_close($delivery_request);
        $delivery = json_decode($delivery_response, true);

        if (!empty($delivery['parcels'][0]['id'])) {
            $old_parcel_ids = isset($boxnoworder['parcel_ids']) ? $boxnoworder['parcel_ids'] : '';
            $trackid = $old_parcel_ids . $delivery['parcels'][0]['id'] . ',';
            Db::getInstance()->update(
                'boxnow_entries',
                array(
                    'parcel_ids' => pSQL($trackid),
                    'warehouse_id' => pSQL($warehouse),
                    'vouchers' => 1,
                    'payment_type' => (int) $payment_type,
                ),
                'id_order = ' . (int) $order_id
            );

            // Empty body => success (front-end JS checks for '')
            $this->respond('');
        }

        $errors_arr = array(
            'P400' => 'Invalid request data. Make sure you are sending the request according to the documentation.',
            'P401' => 'Invalid request origin location reference. Make sure you are referencing a valid location ID from Origins endpoint or valid address.',
            'P402' => 'Invalid request destination location reference. Make sure you are referencing a valid location ID from Destinations endpoint or valid address.',
            'P403' => 'You are not allowed to use AnyAPM SameAPM delivery. Contact support if you believe this is a mistake.',
            'P404' => 'Invalid import CSV. See error contents for additional info.',
            'P405' => 'Invalid phone number. Make sure you are sending the phone number in full international format, e.g. +30 xx x xxx xxxx',
            'P406' => 'Invalid compartment/parcel size. Make sure you are sending one of required sizes 1, 2 or 3 (Medium or Large). Size is required when sending from AnyAPM directly.',
            'P407' => 'Invalid country code. Make sure you are sending country code in ISO 3166-1 alpha-2 format, e.g. GR.',
            'P410' => 'Order number conflict. You are trying to create a delivery request for an order ID that has already been created. Choose another order ID.',
            'P411' => 'You are not eligible to use Cash on delivery payment type. Use another payment type or contact our support.',
            'P420' => 'Parcel not ready for cancel. You can cancel only new, undelivered, or parcels that are not returned or lost. Make sure parcel is in transit and try again.',
            'P430' => 'Parcel not ready for AnyAPM confirmation. Parcel is probably already confirmed or being delivered. Contact support if you believe this is a mistake.',
        );
        $code = isset($delivery['code']) ? $delivery['code'] : '';
        $message = isset($errors_arr[$code]) ? $errors_arr[$code] : 'Unknown error';
        $this->respond('Error: ' . $code . ' - ' . $message);
    }

    /**
     * Output a plain response and stop. Avoids rendering the full front-office page.
     */
    private function respond($body)
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }
}
