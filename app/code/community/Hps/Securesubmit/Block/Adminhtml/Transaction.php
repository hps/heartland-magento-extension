<?php

class Hps_Securesubmit_Block_Adminhtml_Transaction extends Mage_Adminhtml_Block_Widget_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('securesubmit/transaction.phtml');
        $this->_controller = 'adminhtml_transaction';
        $this->_headerText = Mage::helper('hps_securesubmit')->__('Heartland Transaction Search');
    }

    /**
     * Prepare grid
     *
     * @return Mage_Adminhtml_Block_Widget_Container
     */
    protected function _prepareLayout()
    {
        $this->setChild('grid', $this->getLayout()->createBlock('hps_securesubmit/adminhtml_transaction_grid', 'securesubmit.transactions.grid'));
        return parent::_prepareLayout();
    }

    /**
     * Check whether it is single store mode
     *
     * @return bool
     */
    public function isSingleStoreMode()
    {
        return !! Mage::app()->isSingleStoreMode();
    }

    /**
     * Retrieve grid HTML
     *
     * @return string
     */
    public function getGridHtml()
    {
        if ( ! Mage::helper('hps_securesubmit')->hasApiCredentials($this->_getStore())) {
            return '';
        }
        return $this->getChildHtml('grid');
    }

    /**
     * @return Mage_Core_Model_Store
     */
    protected function _getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID);
        return Mage::app()->getStore($storeId);
    }
}
