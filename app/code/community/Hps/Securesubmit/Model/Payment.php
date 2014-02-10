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
    protected $_canVoid                     = true;
    protected $_canAuthorize                = true;

    protected $_supportedCurrencyCodes = array('USD');
    protected $_minOrderTotal = 0.5;

    protected $_formBlockType = 'hps_securesubmit/form';
    protected $_infoBlockType = 'hps_securesubmit/info';

    /**
     * Fields that should be replaced in debug with '***'
     *
     * @var array
     */
    protected $_debugReplacePrivateDataKeys = array('SecretAPIKey');

    public function validate()
    {
        $info = $this->getInfoInstance();
        $additionalData = new Varien_Object($info->getAdditionalData() ? unserialize($info->getAdditionalData()) : null);

        // Only validate when not using token
        if ($additionalData->getUseCreditCard()) {
            parent::validate();
        }

        return $this;
    }

    /**
     * Capture payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return  $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $this->_authorize($payment, $amount, true);
    }

    /**
     * Authorize payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return  $this
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $this->_authorize($payment, $amount, false);
    }

    /**
     * Authorize or Capture payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @param bool $capture
     * @return  $this
     */
    private function _authorize(Varien_Object $payment, $amount, $capture)
    {
        $order = $payment->getOrder(); /* @var $order Mage_Sales_Model_Order */
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
            preg_replace('/[^0-9]/', '', $billing->getPostcode()),
            $billing->getCountry());

        $cardHolder = new HpsCardHolderInfo(
            $billing->getData('firstname'),
            $billing->getData('lastname'),
            preg_replace('/[^0-9]/', '', $billing->getTelephone()),
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
        catch (CardException $e) {
            $this->_debugChargeService($chargeService, $e);
            $payment->setStatus(self::STATUS_DECLINED);
            $this->throwUserError($e->getMessage(), $e->ResultText, TRUE);
        }
        catch (Exception $e)
        {
            $this->_debugChargeService($chargeService, $e);
            Mage::logException($e);
            $payment->setStatus(self::STATUS_ERROR);
            $this->throwUserError($e->getMessage());
        }

        // No exception thrown so action was a success
        $this->_debugChargeService($chargeService);
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
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if ($this->canVoid($payment)) {
            // First try to void the payment and if the batch is already closed - try to refund the payment.
            try {
                $this->void($payment);
            } catch (Mage_Core_Exception $e) {
                $this->_refund($payment, $amount);
            }
        } else {
            $this->_refund($payment, $amount);
        }
        return $this;
    }

    /**
     * Void payment abstract method
     *
     * @param Varien_Object $payment
     *
     * @return Hps_Securesubmit_Model_Payment
     */
    public function void(Varien_Object $payment)
    {
        $transactionId = $payment->getCcTransId();

        $config = new HpsServicesConfig();
        $config->secretAPIKey = $this->getConfigData('secretapikey');
        $config->versionNbr = '1509';
        $config->developerId = '002914';

        $chargeService = new HpsChargeService($config);
        try {
            $voidResponse = $chargeService->Void($transactionId);
        }
        catch (HpsException $e)
        {
            $this->_debugChargeService($chargeService, $e);
            Mage::throwException($e->getMessage());
        }
        catch (Exception $e) {
            $this->_debugChargeService($chargeService, $e);
            Mage::logException($e);
            Mage::throwException(Mage::helper('hps_securesubmit')->__('An unexpected error occurred. Please try again or contact a system administrator.'));
        }
        $this->_debugChargeService($chargeService);

        $payment
            ->setTransactionId($voidResponse->TransactionId)
            ->setParentTransactionId($transactionId)
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);

        return $this;
    }

    protected function _refund(Varien_Object $payment, $amount)
    {
        $transactionId = $payment->getCcTransId();
        $order = $payment->getOrder();

        $config = new HpsServicesConfig();
        $config->secretAPIKey = $this->getConfigData('secretapikey');
        $config->versionNbr = '1573';
        $config->developerId = '002914';

        $chargeService = new HpsChargeService($config);
        try {
            $refundResponse = $chargeService->RefundWithTransactionId(
                $amount,
                strtolower($order->getBaseCurrencyCode()),
                $transactionId);

        }
        catch (HpsException $e)
        {
            $this->_debugChargeService($chargeService, $e);
            $this->throwUserError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_debugChargeService($chargeService, $e);
            Mage::logException($e);
            $this->throwUserError($e->getMessage());
        }
        $this->_debugChargeService($chargeService);

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

    /**
     * @param string $error
     * @param string $detailedError
     * @param bool $goToPaymentSection
     * @throws Mage_Core_Exception
     */
    public function throwUserError($error, $detailedError = NULL, $goToPaymentSection = FALSE)
    {
        // Register detailed error for error reporting elsewhere
        $detailedError = $detailedError ?  $error.' ['.$detailedError.']' : $error;
        Mage::unregister('payment_detailed_error');
        Mage::register('payment_detailed_error', $detailedError);

        // Replace gateway error with custom error message for customers
        $error = Mage::helper('hps_securesubmit')->__($error);
        if ($customMessage = $this->getConfigData('custom_message')) {
            $error = sprintf($customMessage, $error);
        }

        // Send checkout session back to payment section to avoid double-attempt to charge single-use token
        if ($goToPaymentSection && Mage::app()->getRequest()->getOriginalPathInfo() == '/checkout/onepage/saveOrder') {
            Mage::getSingleton('checkout/session')->setGotoSection('payment');
        }
        throw new Mage_Core_Exception($error);
    }

    /**
     * @param HpsChargeService $chargeService
     * @param Exception|null $exception
     */
    public function _debugChargeService(HpsChargeService $chargeService, $exception = NULL)
    {
        if ($this->getDebugFlag()) {
            $this->_debug(array(
                'store' => Mage::app()->getStore($this->getStore())->getFrontendName(),
                'exception_message' => $exception ? get_class($exception).': '.$exception->getMessage() : '',
                'last_request' => $chargeService->lastRequest,
                'last_response' => $chargeService->lastResponse,
            ));
        }
    }

}
