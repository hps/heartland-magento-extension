<?php

class Hps_Securesubmit_Model_Report extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('hps_securesubmit/report');
    }

    public function loadByOrderId($orderId)
    {
        $collection = $this->getCollection()
                    ->addFieldToFilter('order_id', $orderId);
        return $collection->getFirstItem();
    }
}
