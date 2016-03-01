<?php

class Hps_Securesubmit_Block_Masterpass_Client_Js extends Mage_Core_Block_Template
{
    public function _construct()
    {
        $this->setTemplate('securesubmit/masterpass/js.phtml');
    }

    public function isSandbox()
    {
        return '1' === Mage::getStoreConfig('payment/hps_masterpass/use_sandbox');
    }
}
