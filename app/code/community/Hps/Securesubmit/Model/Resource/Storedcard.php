<?php

class Hps_Securesubmit_Model_Resource_Storedcard extends Mage_Core_Model_Resource_Db_Abstract{
    protected function _construct()
    {
        $this->_init('hps_securesubmit/storedcard', 'storedcard_id');
    }

    public function removeDuplicates(Hps_Securesubmit_Model_Storedcard $storedcard)
    {
        $this->_getWriteAdapter()->delete($this->getMainTable(), array(
            'customer_id = ?' => $storedcard->getCustomerId(),
            'token_value = ?' => $storedcard->getTokenValue()
        ));
    }
}