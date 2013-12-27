<?php
/**
 * Created by PhpStorm.
 * User: berton
 * Date: 12/27/13
 * Time: 12:40 PM
 */
class Hps_Securesubmit_Model_Resource_Storedcard_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract {
    protected function _construct()
    {
        $this->_init('hps_securesubmit/storedcard');
    }
}