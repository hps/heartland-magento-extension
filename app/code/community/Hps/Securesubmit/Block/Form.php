<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_SecureSubmit_Block_Form extends Mage_Payment_Block_Form_Ccsave
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('securesubmit/form.phtml');
    }
}
