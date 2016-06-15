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
        $additionalData = unserialize($info->getAdditionalData());
        $skipCC = isset($additionalData['giftcard_skip_cc']) && $additionalData['giftcard_skip_cc'];
        $gift = '';

        if (isset($additionalData['giftcard_number'])) {
            $gift = "Gift Card" . (!$skipCC ? ' & ' : '');;
    	}

        $type = $gift;
        if (!$skipCC) {
            $type .= $info->getCcLast4() . " (" . $info->getCcExpMonth() . "/" . $info->getCcExpYear() . ")";
        }

        $data[Mage::helper("payment")->__("Payment Type")] = $type;

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}
