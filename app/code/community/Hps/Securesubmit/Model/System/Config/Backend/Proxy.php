<?php
class Hps_Securesubmit_Model_System_Config_Backend_Proxy extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        if ($this->getFieldsetDataValue('use_http_proxy')) {
            $httpProxyHost = $this->getFieldsetDataValue('http_proxy_host');
            if (empty($httpProxyHost)) {
                Mage::throwException(Mage::helper('hps_securesubmit')->__('HTTP Proxy Host is required for using proxy.'));
            }
            $httpProxyPort = $this->getFieldsetDataValue('http_proxy_port');
            if (empty($httpProxyPort)) {
                Mage::throwException(Mage::helper('hps_securesubmit')->__('HTTP Proxy Port is required for using proxy.'));
            }
        }
    }
}
