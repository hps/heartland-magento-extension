<?php

class Hps_Securesubmit_Model_Resource_Report extends Mage_Core_Model_Resource_Db_Abstract
{
    public function _construct()
    {
        $this->_init('hps_securesubmit/report', 'row_id');
    }
}
