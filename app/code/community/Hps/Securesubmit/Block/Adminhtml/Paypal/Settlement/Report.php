<?php
class Hps_Securesubmit_Block_Adminhtml_Paypal_Settlement_Report extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Prepare grid container, add additional buttons
     */
    public function __construct()
    {
        $this->_blockGroup = 'hps_securesubmit';
        $this->_controller = 'adminhtml_paypal_settlement_report';
        $this->_headerText = Mage::helper('hps_securesubmit')->__('HPS PayPal Settlement Reports');
        parent::__construct();
        $this->_removeButton('add');
        $message = Mage::helper('hps_securesubmit')->__('Fetching updates for all non-Completed transactions. Do you want to continue?');
        $this->_addButton('fetch', array(
            'label'   => Mage::helper('hps_securesubmit')->__('Fetch Updates'),
            'onclick' => "confirmSetLocation('{$message}', '{$this->getUrl('*/*/fetch')}')",
            'class'   => 'task'
        ));
    }
}
