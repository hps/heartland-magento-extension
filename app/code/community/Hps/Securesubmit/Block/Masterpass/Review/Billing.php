<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_Securesubmit_Block_Masterpass_Review_Billing extends Mage_Checkout_Block_Onepage_Billing
{
    /**
     * Return Sales Quote Address model
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getAddress()
    {
        if (is_null($this->_address)) {
            if ($this->isCustomerLoggedIn() || $this->getQuote()->getBillingAddress()) {
                $this->_address = $this->getQuote()->getBillingAddress();
                if (!$this->_address->getFirstname()) {
                    $this->_address->setFirstname($this->getQuote()->getCustomer()->getFirstname());
                }
                if (!$this->_address->getLastname()) {
                    $this->_address->setLastname($this->getQuote()->getCustomer()->getLastname());
                }
            } else {
                $this->_address = Mage::getModel('sales/quote_address');
            }
        }

        return $this->_address;
    }
}
