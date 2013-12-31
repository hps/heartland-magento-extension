<?php

class Hps_Securesubmit_Model_Resource_Storedcard extends Mage_Core_Model_Resource_Db_Abstract{
    protected function _construct()
    {
        $this->_init('hps_securesubmit/storedcard', 'storedcard_id');
    }
}