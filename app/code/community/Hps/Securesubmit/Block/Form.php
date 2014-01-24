<?php
class Hps_Securesubmit_Block_Form extends Mage_Payment_Block_Form_Ccsave
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('securesubmit/form.phtml');
    }
}
