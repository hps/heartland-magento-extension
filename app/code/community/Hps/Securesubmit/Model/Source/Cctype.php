<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_Securesubmit_Model_Source_Cctype extends Mage_Payment_Model_Source_Cctype
{
    protected $_allowedTypes = array('AE','VI','MC','DI','JCB','OT');

}
