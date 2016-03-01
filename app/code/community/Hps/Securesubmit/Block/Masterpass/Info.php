<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_SecureSubmit_Block_Masterpass_Info extends Mage_Payment_Block_Info
{
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $data = array();
        $info = $this->getInfo();

        $data[Mage::helper("payment")->__("Payment Type")] = "MasterPass";
        //$data[Mage::helper('payment')->__('Email Address')] = print_r($info, true);

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}
