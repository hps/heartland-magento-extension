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

    /**
     * Check whether the customer has any saved credit card
     *
     * @return bool
     */
    public function hasAnySavedCard()
    {
        return (bool) Mage::helper('hps_securesubmit')->getStoredCards($this->getCustomerId())->getSize();
    }

    /**
     * Retrieve the list of the customer's stored cards that can be applied to the current shipping address
     *
     * @return Hps_Securesubmit_Model_Resource_Storedcard_Collection|Hps_Securesubmit_Model_Storedcard[]
     */
    public function getAllowedStoredCards()
    {
        return Mage::helper('hps_securesubmit')->getStoredCards($this->getCustomerId(), $this->getCustomerAddressId());
    }

    public function getCca()
    {
        if (!$this->getConfig('enable_threedsecure')) {
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

    /**
     * Retrieve credit card info
     *
     * @param Hps_Securesubmit_Model_Storedcard $card
     * @return string
     */
    public function getCCInfo(Hps_Securesubmit_Model_Storedcard $card)
    {
        $info = $card->getCcType().' '.str_repeat('*', 12).$card->getCcLast4().' ('.$card->getCcExpMonth().'/'.$card->getCcExpYear().')';
        if ($card->isExpired()) {
            $info.= ' '.$this->__('*expired');
        }
        return $info;
    }

    /**
     * Check whether the customer has added a new shipping address
     *
     * @return bool
     */
    public function isNewAddress()
    {
        return ! $this->getCustomerAddressId();
    }

    /**
     * Retrieve the customer address id for the shipping address
     *
     * @return int
     */
    public function getCustomerAddressId()
    {
        return (int) Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCustomerAddressId();
    }

    /**
     * Retrieve customer id from session
     *
     * @return int
     */
    public function getCustomerId()
    {
        return (int) Mage::getSingleton('customer/session')->getCustomerId();
    }
}
