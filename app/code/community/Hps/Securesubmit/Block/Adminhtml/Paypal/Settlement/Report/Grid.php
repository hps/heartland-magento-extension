<?php

class Hps_Securesubmit_Block_Adminhtml_Paypal_Settlement_Report_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Retain filter parameters in session
     *
     * @var bool
     */
    protected $_saveParametersInSession = true;

    public function __construct()
    {
        parent::__construct();
        $this->setId('HpsSecuresubmitReportSettlementGrid');
        // This is the primary key of the database
        $this->setDefaultSort('row_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('hps_securesubmit/report')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('row_id', array(
            'header'    => Mage::helper('hps_securesubmit')->__('ID'),
            'align'     =>'right',
            'width'     => '50px',
            'index'     => 'row_id',
        ));

        $this->addColumn('order_id', array(
            'header'    => Mage::helper('hps_securesubmit')->__('Order ID'),
            'align'     => 'left',
            'width'     => '80px',
            'index'     => 'order_id',
        ));

        $this->addColumn('payer_email', array(
            'header'    => Mage::helper('hps_securesubmit')->__('Payer Email'),
            'align'     => 'left',
            'index'     => 'payer_email',
        ));

        $this->addColumn('transaction_id', array(
            'header'    => Mage::helper('hps_securesubmit')->__('Transaction ID'),
            'align'     => 'left',
            'width'     => '80px',
            'index'     => 'transaction_id',
        ));


        $this->addColumn('last_known_status', array(
            'header'    => Mage::helper('hps_securesubmit')->__('Last Known Status'),
            'align'     => 'left',
            'index'     => 'last_known_status',
        ));

        $this->addColumn('created_time', array(
            'header'    => Mage::helper('hps_securesubmit')->__('Creation Time'),
            'align'     => 'left',
            'width'     => '120px',
            'type'      => 'date',
            'default'   => '--',
            'index'     => 'created_time',
        ));

        $this->addColumn('update_time', array(
            'header'    => Mage::helper('hps_securesubmit')->__('Update Time'),
            'align'     => 'left',
            'width'     => '120px',
            'type'      => 'date',
            'default'   => '--',
            'index'     => 'update_time',
        ));

        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/view', array(
            'store' => $this->getRequest()->getParam('store'),
            'id'    => $row->getId()
        ));
    }
}
