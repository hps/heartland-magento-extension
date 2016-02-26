<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

/**
 *
 * Paypal transaction session namespace
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Hps_Securesubmit_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('hps_securesubmit');
    }
}

