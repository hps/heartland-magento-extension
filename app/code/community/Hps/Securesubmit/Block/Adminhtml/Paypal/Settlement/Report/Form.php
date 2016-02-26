<?php

class Hps_Securesubmit_Block_Adminhtml_Paypal_Settlement_Report_Form extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->_controller = '';
        $this->_headerText = Mage::helper('hps_securesubmit')->__('View Transaction');
        $message = Mage::helper('hps_securesubmit')->__('Fetching updates for this transaction. Do you want to continue?');
        $model = Mage::registry('current_transaction');
        $url = $this->getUrl('*/*/fetch', array(
            'id' => $model->getRowId(),
        ));
        $this->_removeButton('reset')
            ->_removeButton('delete')
            ->_removeButton('save')
            ->_addButton('fetch', array(
                'label'   => Mage::helper('hps_securesubmit')->__('Fetch Updates'),
                'onclick' => "confirmSetLocation('{$message}', '{$url}')",
                'class'   => 'task'
            ));
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->setChild('form', $this->getLayout()->createBlock('hps_securesubmit/adminhtml_paypal_settlement_report_form_form'));
        return $this;
    }
}
