<?php
require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'hpsChargeService.php';

class Hps_Securesubmit_Model_Payment extends Mage_Payment_Model_Method_Cc
{
    protected $_code                        = 'hps_securesubmit';
    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canAuthorize                = true;

    protected $_supportedCurrencyCodes = array('USD');
    protected $_minOrderTotal = 0.5;

    protected $_formBlockType = 'hps_securesubmit/form';
    protected $_infoBlockType = 'hps_securesubmit/info';

    protected $_customMessage = '';

    /**
     * Fields that should be replaced in debug with '***'
     *
     * @var array
     */
    protected $_debugReplacePrivateDataKeys = array('SecretAPIKey');

    public function __construct()
    {
        $this->_customMessage = $this->getConfigData('custom_message');
    }

    public function validate()
    {
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $this->_authorize($payment, $amount, true);
    }

    public function authorize(Varien_Object $payment, $amount)
    {
        $this->_authorize($payment, $amount, false);
    }

    private function _authorize(Varien_Object $payment, $amount, $capture)
    {
        $order = $payment->getOrder();
        $billing = $order->getBillingAddress();
        $multiToken = false;
        $cardData = null;
        $cardType = null;
        $additionalData = new Varien_Object($payment->getAdditionalData() ? unserialize($payment->getAdditionalData()) : null);
        $secureToken = $additionalData->getSecuresubmitToken() ? $additionalData->getSecuresubmitToken() : null;
        $saveCreditCard = !! $additionalData->getCcSaveFuture();
        $useCreditCard = !! $additionalData->getUseCreditCard();

        if ($saveCreditCard && ! $useCreditCard) {
            $multiToken = true;
            $cardData = new HpsCardInfo(
                $payment->getCcLast4(),
                $payment->getCcExpYear(),
                $payment->getCcExpMonth()
            );
        }

        $config = new HpsServicesConfig();
        // Use HTTP proxy
        if (Mage::getStoreConfig('payment/hps_securesubmit/use_http_proxy')) {
            $config->useproxy = true;
            $config->proxyOptions = array(
                'proxy_host' => Mage::getStoreConfig('payment/hps_securesubmit/http_proxy_host'),
                'proxy_port' => Mage::getStoreConfig('payment/hps_securesubmit/http_proxy_port'),
            );
        }
        $config->secretAPIKey = $this->getConfigData('secretapikey');
        $config->versionNbr = '1573';
        $config->developerId = '002914';

        $chargeService = new HpsChargeService($config);

        $address = new HpsAddressInfo(
            $billing->getStreet(1),
            $billing->getCity(),
            $billing->getRegion(),
            str_replace('-', '', $billing->getPostcode()),
            $billing->getCountry());

        $cardHolder = new HpsCardHolderInfo(
            $billing->getData('firstname'),
            $billing->getData('lastname'),
            $billing->getTelephone(),
            $billing->getData('email'),
            $address);

        if ($useCreditCard) {
            $cardOrToken = new HpsCardInfo(
                $payment->getCcNumber(),
                $payment->getCcExpYear(),
                $payment->getCcExpMonth(),
                $payment->getCcCid());
        } else {
            $cardOrToken = new HpsToken(
                $secureToken,
                null,
                null);
        }

        try
        {
            if ($capture)
            {
                if ($payment->getCcTransId())
                {
                    $response = $chargeService->Capture(
                        $payment->getCcTransId());
                }
                else
                {
                    $response = $chargeService->Charge(
                        $amount,
                        strtolower($order->getBaseCurrencyCode()),
                        $cardOrToken,
                        $cardHolder,
                        $multiToken);
                }
            }
            else
            {
                $response = $chargeService->Authorize(
                    $amount,
                    strtolower($order->getBaseCurrencyCode()),
                    $cardOrToken,
                    $cardHolder,
                    $multiToken);
            }
        }
        catch (Exception $e)
        {
            $error = Mage::helper('hps_securesubmit')->__($e->getMessage());
            Mage::throwException(sprintf($this->_customMessage, $error));
        }
        if ($response->TransactionDetails->RspCode == '00' || $response->TransactionDetails->RspCode == '0')
        {
            $this->setStore($payment->getOrder()->getStoreId());
            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setAmount($amount);
            $payment->setLastTransId($response->TransactionId);
            $payment->setCcTransId($response->TransactionId);
            $payment->setTransactionId($response->TransactionId);
            $payment->setIsTransactionClosed(0);
            if($multiToken){
                if ($response->TokenData->TokenRspCode == '0') {
                    Mage::helper('hps_securesubmit')->saveMultiToken($response->TokenData->TokenValue,$cardData,$response->TransactionDetails->CardType);
                } else {
                    Mage::log(Mage::helper('hps_securesubmit')->__('Requested multi token has not been generated for the transaction # %s.', $response->TransactionId), Zend_Log::WARN);
                }
            }
        }
        else
        {
            if (!$payment->getCcTransId())
            {
                $this->setStore($payment->getOrder()->getStoreId());
                $payment->setStatus(self::STATUS_ERROR);
                $error = Mage::helper('hps_securesubmit')->__($response->TransactionDetails->ResponseMessage);
                Mage::throwException(sprintf($this->_customMessage, $error));
            }
        }

        return $this;
    }
    
    public function refund(Varien_Object $payment, $amount)
    {
        $transactionId = $payment->getCcTransId();
        $order = $payment->getOrder();

        $config = new HpsServicesConfig();
        $config->secretAPIKey = $this->getConfigData('secretapikey');
        $config->versionNbr = '1573';
        $config->developerId = '002914';

        try {

            $chargeService = new HpsChargeService($config);

            $refundResponse = $chargeService->RefundWithTransactionId(
                $amount,
                strtolower($order->getBaseCurrencyCode()),
                $transactionId);

        } catch (Exception $e) {
            $error = Mage::helper('hps_securesubmit')->__($e->getMessage());
            Mage::throwException(sprintf($this->_customMessage, $error));
        }

        $payment
            ->setTransactionId($refundResponse->TransactionId)
            ->setParentTransactionId($transactionId)
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);

        return $this;
    }    
    
    public function isAvailable($quote = null)
    {
        if($quote && $quote->getBaseGrandTotal()<$this->_minOrderTotal) {
            return false;
        }

        return $this->getConfigData('secretapikey', ($quote ? $quote->getStoreId() : null))
            && parent::isAvailable($quote);
    }
    
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }

        return true;
    }

    public function assignData($data)
    {
        parent::assignData($data);

        if ( ! ($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();

        if ( ! $info->getCcLast4() && $data->getCcLastFour()) {
            $info->setCcLast4($data->getCcLastFour());
        }

        $details = array();
        if ($data->getData('cc_save_future')) {
            $details['cc_save_future'] = 1;
        }
        if ($data->getData('securesubmit_token')) {
            $details['securesubmit_token'] = $data->getData('securesubmit_token');
        }
        if ($data->getData('use_credit_card')) {
            $details['use_credit_card'] = 1;
        }
        if ( ! empty($details)) {
            $this->getInfoInstance()->setAdditionalData(serialize($details));
        }

        return $this;
    }

}
