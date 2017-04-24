<?php

class Hps_Securesubmit_Adminhtml_Securesubmit_TransactionController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Transactions grid action
     */
    public function indexAction()
    {
        if ( ! Mage::helper('hps_securesubmit')->hasApiCredentials($this->_getStore())) {
            $this->_getSession()->addNotice(Mage::helper('hps_securesubmit')->__('SecureSubmit is not configured for the selected store scope.'));
        }
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Transactions grid action
     */
    public function gridAction()
    {
        $this->loadLayout(FALSE);
        $this->renderLayout();
    }

    /**
     * Export transactions grid to CSV format
     */
    public function exportCsvAction()
    {
        $fileName   = 'hps_transactions.csv';
        $grid       = $this->getLayout()->createBlock('hps_securesubmit/adminhtml_transaction_grid');
        $this->_prepareDownloadResponse($fileName, $grid->getCsvFile());
    }

    /**
     *  Export transactions grid to Excel XML format
     */
    public function exportExcelAction()
    {
        $fileName   = 'hps_transactions.xml';
        $grid       = $this->getLayout()->createBlock('hps_securesubmit/adminhtml_transaction_grid');
        $this->_prepareDownloadResponse($fileName, $grid->getExcelFile($fileName));
    }

    /**
     * @return Mage_Core_Model_Store
     */
    protected function _getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID);
        return Mage::app()->getStore($storeId);
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/transactions/heartland_transaction_search');
    }
}
