<?php
class Hps_SecureSubmit_Block_Info extends Mage_Payment_Block_Info
{
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $data = array();
        $info = $this->getInfo();
        $additionalData = $info->getAdditionalData();

        if (strpos($additionalData, 'giftcard_number') !== false) {
        	$gift = "Gift Card & ";
    	}

        $data[Mage::helper("payment")->__("Payment Type")] = $gift . "Credit Card ending in " . $info->getCcLast4();

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}

