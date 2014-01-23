<?php

class Hps_SecureSubmit_Block_Info extends Mage_Payment_Block_Info
{
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $data = array();
        $info = $this->getInfo();

        $data[Mage::helper("payment")->__("Payment Type")] = "Secure Token";

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}

