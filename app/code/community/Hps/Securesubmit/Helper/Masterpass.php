<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */
require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';

class Hps_Securesubmit_Helper_Masterpass extends Hps_Securesubmit_Helper_Altpayment_Abstract
{
    protected $_methodCode = 'hps_masterpass';

    public function returnFromMasterPass(
        $status,
        $orderId,
        $oauthToken,
        $oauthVerifier,
        $payload,
        $checkoutResourceUrl,
        $pairingToken,
        $pairingVerifier
    ) {
        $data = null;

        try {
            $orderData = new HpsOrderData();
            $orderData->transactionStatus = $status;
            if ($pairingToken !== '' && $pairingVerifier !== '') {
                $orderData->pairingToken = $pairingToken;
                $orderData->pairingVerifier = $pairingVerifier;
                $orderData->checkoutType = HpsCentinelCheckoutType::PAIRING_CHECKOUT;
            }

            // Authenticate the request with the information we've gathered
            $response = $this->getService()->authenticate(
                $orderId,
                $oauthToken,
                $oauthVerifier,
                $payload,
                $checkoutResourceUrl,
                $orderData
            );

            if ('0' !== $response->errorNumber) {
                throw new Exception();
            }

            $data = (object)array_merge((array)$response, array(
                'status' => $status,
            ));
        } catch (Exception $e) {
            $data = false;
        }
        return $data;
    }

    public function preApproval($longAccessToken = '')
    {
        if ($longAccessToken == '') {
            return false;
        }

        return $this->getService()->preApproval($longAccessToken);
    }

    protected function getService()
    {
        $config = new HpsCentinelConfig();
        if (!Mage::getStoreConfig('payment/hps_masterpass/use_sandbox')) {
            $config->serviceUri  = "https://api-uat.heartlandportico.com/paymentserver.v1/PosGatewayService.asmx";
        }
        $config->processorId    = Mage::getStoreConfig('payment/hps_masterpass/processor_id');
        $config->merchantId     = Mage::getStoreConfig('payment/hps_masterpass/merchant_id');
        $config->transactionPwd = Mage::getStoreConfig('payment/hps_masterpass/transaction_pwd');
        return new HpsMasterPassService($config);
    }
}
