<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

/**
 * @method Hps_Securesubmit_Model_Resource_Storedcard getResource()
 * @method string getDt()
 * @method Hps_Securesubmit_Model_Storedcard setDt(string $value)
 * @method int getCustomerId()
 * @method Hps_Securesubmit_Model_Storedcard setCustomerId(int $value)
 * @method string getTokenValue()
 * @method Hps_Securesubmit_Model_Storedcard setTokenValue(string $value)
 * @method string getCcType()
 * @method Hps_Securesubmit_Model_Storedcard setCcType(string $value)
 * @method string getCcLast4()
 * @method Hps_Securesubmit_Model_Storedcard setCcLast4(string $value)
 * @method string getCcExpMonth()
 * @method Hps_Securesubmit_Model_Storedcard setCcExpMonth(string $value)
 * @method string getCcExpYear()
 * @method Hps_Securesubmit_Model_Storedcard setCcExpYear(string $value)
 */
class Hps_Securesubmit_Model_Storedcard  extends Mage_Core_Model_Abstract
{

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
