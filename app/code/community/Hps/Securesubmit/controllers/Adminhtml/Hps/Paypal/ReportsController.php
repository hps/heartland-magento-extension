<?php

require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';

class Hps_Securesubmit_Adminhtml_Hps_Paypal_ReportsController extends Mage_Adminhtml_Controller_Action
{
    protected function _initAction()
    {
        $this->_title($this->__('Reports'))->_title($this->__('Sales'))->_title($this->__('PayPal Settlement Reports'));
        $this->loadLayout()
            ->_setActiveMenu('report/sales')
            ->_addBreadcrumb(Mage::helper('hps_securesubmit')->__('Reports'), Mage::helper('hps_securesubmit')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('hps_securesubmit')->__('Sales'), Mage::helper('hps_securesubmit')->__('Sales'))
            ->_addBreadcrumb(Mage::helper('hps_securesubmit')->__('HPS PayPal Settlement Report'), Mage::helper('hps_securesubmit')->__('HPS PayPal Settlement Report'));
        return $this;
    }

    public function indexAction()
    {
        $this->_initAction();
        $this->_addContent($this->getLayout()->createBlock('hps_securesubmit/adminhtml_paypal_settlement_report'));
        $this->renderLayout();
    }

    public function viewAction()
    {
        $rowId = $this->getRequest()->getParam('id');
        $row = Mage::getModel('hps_securesubmit/report')->load($rowId);
        if (!$row->getId()) {
            $this->_redirect('*/*/');
            return;
        }
        Mage::register('current_transaction', $row);
        $this->_initAction()
            ->_title($this->__('View Transaction'))
            ->_addContent($this->getLayout()->createBlock('hps_securesubmit/adminhtml_paypal_settlement_report_form', 'settlementView'))
            ->renderLayout();
    }

    public function fetchAction()
    {
        $rows = Mage::getModel('hps_securesubmit/report')
            ->getCollection()
            ->addFieldToSelect(array(
                'row_id',
                'transaction_id',
                'last_known_status',
            ), 'inner')
            ->addFieldToFilter('last_known_status', array(
                'neq' => 'Completed (None)',
            ));

        $id = $this->getRequest()->getParam('id');
        if ($id) {
            $rows = $rows->addFieldToFilter('row_id', array(
                'eq' => $id,
            ));
        }

        Mage::getSingleton('core/resource_iterator')->walk($rows->getSelect(), array(
            array($this, 'updateRow'),
        ));

        if ($id) {
            $this->_redirect('*/*/view', array(
                'id' => $id,
            ));
        } else {
            $this->_redirect('*/*/index');
        }
    }

    public function updateRow($args)
    {
        try {
            $report = Mage::getModel('hps_securesubmit/report');
            $report->setData($args['row']);

            $service = $this->getService();
            $status = $service->status($report->getData('transaction_id'));

            $report
                ->setLastKnownStatus($status->altPayment->Status . ' (' . $status->altPayment->StatusMessage . ')')
                ->setUpdateTime(date('Y-m-d H:i:s'))
                ->save();
        } catch (HpsException $e) {
            // Mage::log(print_r($e, true));
        }
    }

    protected function getService()
    {
        $config = new HpsServicesConfig();
        if (Mage::getStoreConfig('payment/hps_paypal/use_sandbox')) {
            $config->username  = Mage::getStoreConfig('payment/hps_paypal/username');
            $config->password  = Mage::getStoreConfig('payment/hps_paypal/password');
            $config->deviceId  = Mage::getStoreConfig('payment/hps_paypal/device_id');
            $config->licenseId = Mage::getStoreConfig('payment/hps_paypal/license_id');
            $config->siteId    = Mage::getStoreConfig('payment/hps_paypal/site_id');
            $config->soapServiceUri  = "https://api-uat.heartlandportico.com/paymentserver.v1/PosGatewayService.asmx";
        } else {
            $config->secretApiKey = Mage::getStoreConfig('payment/hps_paypal/secretapikey');
        }

        return new HpsPayPalService($config);
    }
}
