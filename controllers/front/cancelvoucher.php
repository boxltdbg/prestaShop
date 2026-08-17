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

class BoxnowCancelVoucherModuleFrontController extends ModuleFrontController
{
    /**
     * Endpoint hit by the "Cancel Voucher" admin link (POST).
     * Outputs 'success' when the parcel is cancelled and the local voucher
     * state is reset; an error message otherwise (the admin JS checks for
     * the exact string 'success').
     */
    public function initContent()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->respond('Method Not Allowed');
        }

        if (!Tools::getIsset('order_id') || !Tools::getIsset('voucher_number')) {
            http_response_code(400);
            $this->respond('Missing required parameters.');
        }

        $order_id = (int) Tools::getValue('order_id');
        $voucher = Tools::getValue('voucher_number');

        if ($order_id <= 0 || $voucher === '' || $voucher === false) {
            http_response_code(400);
            $this->respond('Invalid parameters.');
        }

        // API configuration
        $api_url = Configuration::get('BOXNOW_API_URL');
        $api_id = Configuration::get('BOXNOW_OAUTH_CLIENT_ID');
        $api_secret = Configuration::get('BOXNOW_OAUTH_CLIENT_SECRET');

        // Authenticate
        $auth_payload = json_encode(array(
            'grant_type' => 'client_credentials',
            'client_id' => $api_id,
            'client_secret' => $api_secret,
        ));
        $auth = curl_init('https://' . $api_url . '/api/v1/auth-sessions');
        curl_setopt($auth, CURLOPT_USERAGENT, 'BoxNow-PrestaShop-Module/2.0');
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
            $this->respond('Authentication failed (HTTP ' . (int) $auth_http_status . '): '
                . (isset($auth_json['message']) ? $auth_json['message'] : 'Unknown error'));
        }

        // Cancel the parcel. BoxNow uses the custom method POST /parcels/{id}:cancel
        $authorization = 'Authorization: Bearer ' . $auth_json['access_token'];
        $cancel = curl_init('https://' . $api_url . '/api/v1/parcels/' . rawurlencode($voucher) . ':cancel');
        curl_setopt($cancel, CURLOPT_USERAGENT, 'BoxNow-PrestaShop-Module/2.0');
        curl_setopt($cancel, CURLOPT_HTTPHEADER, array($authorization, 'Content-Type: application/json'));
        curl_setopt($cancel, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cancel, CURLOPT_POST, 1);
        curl_setopt($cancel, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($cancel, CURLOPT_FOLLOWLOCATION, 1);
        $cancel_response = curl_exec($cancel);
        $cancel_http_status = curl_getinfo($cancel, CURLINFO_HTTP_CODE);
        $cancel_json = json_decode($cancel_response, true);
        curl_close($cancel);

        if ($cancel_http_status >= 200 && $cancel_http_status < 300) {
            $row = Db::getInstance()->getRow(
                'SELECT parcel_ids, vouchers FROM `' . _DB_PREFIX_ . 'boxnow_entries` WHERE id_order = ' . (int) $order_id
            );
            $parcelIds = isset($row['parcel_ids']) ? $row['parcel_ids'] : '';
            $newParcelIds = str_replace($voucher . ',', '', $parcelIds);
            $totalVouchers = max(0, (int) (isset($row['vouchers']) ? $row['vouchers'] : 0) - 1);

            Db::getInstance()->update(
                'boxnow_entries',
                array(
                    'parcel_ids' => pSQL($newParcelIds),
                    'vouchers' => $totalVouchers,
                ),
                'id_order = ' . (int) $order_id
            );

            $this->respond('success');
        }

        $code = isset($cancel_json['code']) ? $cancel_json['code'] : '';
        $message = isset($cancel_json['message']) ? $cancel_json['message'] : '';
        $detail = trim($code . ' ' . $message);
        $this->respond('Error (HTTP ' . (int) $cancel_http_status . '): ' . ($detail !== '' ? $detail : 'Unknown error'));
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
