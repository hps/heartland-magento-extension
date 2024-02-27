<?php

class Hps_Securesubmit_Block_Masterpass_Connect extends Mage_Core_Block_Template
{
    public function _construct()
    {
        parent::_construct();
    }

    public function getLongAccessToken()
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        $customer = Mage::getModel('customer/customer')->load($customerId);
        return $customer->getMasterpassLongAccessToken();
    }
}
