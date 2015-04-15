<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_SecureSubmit_Block_Info extends Mage_Payment_Block_Info
{
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $data = array();
        $info = $this->getInfo();
        $additionalData = $info->getAdditionalData();
        $gift = '';

        if (strpos($additionalData, 'giftcard_number') !== false) {
        	$gift = "Gift Card & ";
    	}

        $data[Mage::helper("payment")->__("Payment Type")] = $gift . "Credit Card ending in " . $info->getCcLast4() . " (" . $info->getCcExpMonth() . "/" . $info->getCcExpYear() . ")";

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}
