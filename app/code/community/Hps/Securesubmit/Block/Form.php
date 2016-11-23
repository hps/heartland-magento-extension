<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_SecureSubmit_Block_Form extends Mage_Payment_Block_Form_Ccsave
{
    protected $cca;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('securesubmit/form.phtml');
    }

    public function getCca()
    {
        if (false) {
            return false;
        }

        if (null !== $this->cca) {
            return $this->cca;
        }

        $helper = Mage::helper('hps_securesubmit/jwt');
        $orderNumber = str_shuffle('abcdefghijklmnopqrstuvwxyz');
        $data = array(
            'jti' => str_shuffle('abcdefghijklmnopqrstuvwxyz'),
            'iat' => time(),
            'iss' => $this->getConfig('threedsecure_api_identifier'),
            'OrgUnitId' => $this->getConfig('threedsecure_org_unit_id'),
            'Payload' => array(
                'OrderDetails' => array(
                    'OrderNumber' => $orderNumber,
                    // Centinel requires amounts in pennies
                    'Amount' => 100 * Mage::getSingleton('checkout/cart')
                        ->getQuote()
                        ->getGrandTotal(),
                    'CurrencyCode' => '840',
                ),
            ),
        );
        error_log(print_r($data, true));
        $jwt = $helper::encode(
            $this->getConfig('threedsecure_api_key'),
            $data
        );
        $this->cca = array(
            'jwt' => $jwt,
            'orderNumber' => $orderNumber,
        );

        return $this->cca;
    }

    protected function getConfig($key)
    {
        return Mage::getStoreConfig(sprintf('payment/hps_securesubmit/%s', $key));
    }
}
