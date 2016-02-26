<?php

class Hps_Securesubmit_Block_Paypal_Incontext_Js extends Mage_Core_Block_Template
{
    public function _construct()
    {
        $this->setTemplate('securesubmit/paypal/incontext/js.phtml');
    }

    public function getConfig()
    {
        $env = 'sandbox';
        $privateKey = Mage::getStoreConfig('payment/hps_paypal/secretapikey');
        if (strpos($privateKey, '_prod_') !== false) {
            $env = 'production';
        }

        return json_encode(array(
            'env'    => $env,
            'bmlUrl' => $this->getUrl('securesubmit/paypal/incontextCredit', array(
                'button' => 1,
            )),
            'stdUrl' => $this->getUrl('securesubmit/paypal/incontext', array(
                'button' => 1,
            )),
        ));
    }
}
