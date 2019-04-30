<?php

class Hps_Securesubmit_Block_Adminhtml_Transaction_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /** @var string */
    protected $_refreshTransactionsCacheFlag = 'refresh_transactions_cache';

    public function __construct()
    {
        parent::__construct();
        $this->setId('securesubmit_transaction_grid');
        $this->setUseAjax(TRUE);
        $this->setDefaultDir('DESC');
        $this->setDefaultSort('transaction_date');
        $this->setSaveParametersInSession(TRUE);
    }

    /**
     * Prepare additional JavaScript
     *
     * @return string
     */
    public function getAdditionalJavaScript()
    {
        $js = "
            {$this->getJsObjectName()}.doFilterCallback = function() {
                this.addVarToUrl('{$this->_refreshTransactionsCacheFlag}', 1);
                return true;
            };
            {$this->getJsObjectName()}.initCallback = function() {
                this.addVarToUrl('{$this->_refreshTransactionsCacheFlag}', '');
                return true;
            };
        ";
        return $js;
    }

    /**
     * @return Mage_Core_Model_Store
     */
    protected function _getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID);
        return Mage::app()->getStore($storeId);
    }

    protected function _prepareCollection()
    {
        $store = $this->_getStore();
        $collection = Mage::getResourceModel('hps_securesubmit/transaction_collection');
        $collection->setStoreId($store->getId());
        if ($this->getRequest()->getParam($this->_refreshTransactionsCacheFlag, FALSE)) {
            $collection->getConnector()->refreshCache(TRUE);
        }
        $this->setCollection($collection);
        try {
            parent::_prepareCollection();
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
        return $this;
    }

    protected function _prepareColumns()
    {
        $this->addColumn('transaction_date', array(
            'header'    => $this->__('Transaction Date'),
            'index'     => 'transaction_date',
            'type'      => 'datetime',
            'width'     => '100px',
        ));

        $this->addColumn('auth_code', array(
            'header'    => $this->__('Authorization Code'),
            'type'      => 'text',
            'index'     => 'auth_code',
            'width'     => '100px',
        ));

        $this->addColumn('first_name', array(
            'header'    => $this->__('First Name'),
            'type'      => 'text',
            'index'     => 'first_name',
            'width'     => '150px',
        ));

        $this->addColumn('last_name', array(
            'header'    => $this->__('Last Name'),
            'type'      => 'text',
            'index'     => 'last_name',
            'width'     => '150px',
        ));

        $this->addColumn('cc_number', array(
            'header'    => $this->__('CC Number'),
            'type'      => 'text',
            'index'     => 'cc_number',
            'width'     => '100px',
        ));

        $this->addColumn('invoice_number', array(
            'header'    => $this->__('Invoice Number'),
            'type'      => 'text',
            'index'     => 'invoice_number',
            'width'     => '100px',
        ));

        $this->addColumn('customer_id', array(
            'header'    => $this->__('Customer ID'),
            'type'      => 'text',
            'index'     => 'customer_id',
            'width'     => '100px',
        ));

        $this->addColumn('transaction_type', array(
            'header'    => $this->__('Transaction Type'),
            'index'     => 'transaction_type',
            'type'      => 'options',
            'width'     => '100px',
            'options'   => Mage::getSingleton('hps_securesubmit/transaction_config')->getServiceNameTypeOptions(),
        ));

        $this->addColumn('card_type', array(
            'header'    => $this->__('Card Type'),
            'index'     => 'card_type',
            'type'      => 'options',
            'width'     => '100px',
            'options'   => Mage::getSingleton('hps_securesubmit/transaction_config')->getCardTypeOptions(),
        ));

        $this->addColumn('issuer_result', array(
            'header'    => $this->__('Issuer Result'),
            'type'      => 'text',
            'index'     => 'issuer_result',
            'width'     => '100px',
        ));

        $this->addColumn('gateway_status', array(
            'header'    => $this->__('Status'),
            'type'      => 'text',
            'index'     => 'gateway_status',
            'width'     => '100px',
            'sortable'  => FALSE,
            'filter'    => FALSE,
        ));

        $this->addColumn('transaction_id', array(
            'header'    => $this->__('Transaction ID'),
            'type'      => 'text',
            'index'     => 'transaction_id',
            'width'     => '100px',
        ));

        $store = $this->_getStore();
        $this->addColumn('amount', array(
            'header'    => $this->__('Amount'),
            'type'      => 'currency',
            'currency_code' => $store->getBaseCurrency()->getCode(),
            'index'     => 'amount',
            'filter'    => FALSE,
        ));

        $this->addColumn('description', array(
            'header'    => $this->__('Description'),
            'type'      => 'text',
            'index'     => 'description',
            'sortable'  => FALSE,
            'filter'    => FALSE,
        ));

        $this->addExportType('*/*/exportCsv', $this->__('CSV'));
        $this->addExportType('*/*/exportExcel', $this->__('Excel XML'));

        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return '';
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => TRUE));
    }
}
