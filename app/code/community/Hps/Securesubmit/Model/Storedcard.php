<?php
/**
 * @method Hps_Securesubmit_Model_Resource_Storedcard getResource()
 * @method int getStoredcardId()
 * @method Hps_Securesubmit_Model_Storedcard setStoredcardId(int $value)
 * @method string getDt()
 * @method Hps_Securesubmit_Model_Storedcard setDt(string $value)
 * @method int getCustomerId()
 * @method Hps_Securesubmit_Model_Storedcard setCustomerId(int $value)
 * @method string getTokenValue()
 * @method Hps_Securesubmit_Model_Storedcard setTokenValue(string $value)
 * @method string getCcType()
 * @method Hps_Securesubmit_Model_Storedcard setCcType(string $value)
 * @method string getLast4()
 * @method Hps_Securesubmit_Model_Storedcard setLast4(string $value)
 * @method string getCcExpMonth()
 * @method Hps_Securesubmit_Model_Storedcard setCcExpMonth(string $value)
 * @method string getCcExpYear()
 * @method Hps_Securesubmit_Model_Storedcard setCcExpYear(string $value)
 */
class Hps_Securesubmit_Model_Storedcard  extends Mage_Core_Model_Abstract{
    protected function _construct()
    {
        $this->_init('hps_securesubmit/storedcard');
    }

    public function removeDuplicates()
    {
        $this->getResource()->removeDuplicates($this);
        return $this;
    }
}