<?php

require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';

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
        $saveCreditCard = !! (bool)$additionalData->getCcSaveFuture();
        $useCreditCard = !! (bool)$additionalData->getUseCreditCard();

        if ($saveCreditCard && ! $useCreditCard) {
            $multiToken = true;
            $cardData = new HpsCreditCard();
            $cardData->number = $payment->getCcLast4();
            $cardData->expYear = $payment->getCcExpYear();
            $cardData->expMonth = $payment->getCcExpMonth();
        }

        $config = new HpsConfiguration();
        // Use HTTP proxy
        if (Mage::getStoreConfig('payment/hps_securesubmit/use_http_proxy')) {
            $config->useProxy = true;
            $config->proxyOptions = array(
                'proxy_host' => Mage::getStoreConfig('payment/hps_securesubmit/http_proxy_host'),
                'proxy_port' => Mage::getStoreConfig('payment/hps_securesubmit/http_proxy_port'),
            );
        }
        
        $config->secretApiKey = $this->getConfigData('secretapikey');
        $config->versionNumber = '1573';
        $config->developerId = '002914';

        $chargeService = new HpsChargeService($config);

        $address = new HpsAddress();
        $address->address = $billing->getStreet(1);
        $address->city = $billing->getCity();
        $address->state = $billing->getRegion();
        $address->zip = preg_replace('/[^0-9]/', '', $billing->getPostcode());
        $address->country = $billing->getCountry();

        $cardHolder = new HpsCardHolder();
        $cardHolder->firstName = $billing->getData('firstname');
        $cardHolder->lastName = $billing->getData('lastname');
        $cardHolder->phone = preg_replace('/[^0-9]/', '', $billing->getTelephone());
        $cardHolder->emailAddress = $billing->getData('email');
        $cardHolder->address = $address;

        if ($useCreditCard) {
            $cardOrToken = new HpsCreditCard();
            $cardOrToken->number = $payment->getCcNumber();
            $cardOrToken->expYear = $payment->getCcExpYear();
            $cardOrToken->expMonth = $payment->getCcExpMonth();
            $cardOrToken->cvv = $payment->getCcCid();
        } else {
            $cardOrToken = new HpsTokenData();
            $cardOrToken->tokenValue = $secureToken;
        }
        
        $details = new HpsTransactionDetails();
        $details->invoiceNumber = $order->getIncrementId();

        try
        {
            if ($capture)
            {
                if ($payment->getCcTransId())
                {
                    $response = $chargeService->capture(
                        $payment->getCcTransId(), 
                        $amount);
                }
                else
                {
                    $response = $chargeService->charge(
                        $amount,
                        strtolower($order->getBaseCurrencyCode()),
                        $cardOrToken,
                        $cardHolder,
                        $multiToken,
                        $details);

                    $payment->setCcTransId($response->transactionId);
                }
            }
            else
            {
                $response = $chargeService->authorize(
                    $amount,
                    strtolower($order->getBaseCurrencyCode()),
                    $cardOrToken,
                    $cardHolder,
                    $multiToken,
                    $details);

                $payment->setCcTransId($response->transactionId);
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
        $payment->setLastTransId($response->transactionId);
        $payment->setTransactionId($response->transactionId);

        $payment->setIsTransactionClosed(0);
        if($multiToken){
            if ($response->tokenData->responseCode == '0') {
                Mage::helper('hps_securesubmit')->saveMultiToken($response->tokenData->tokenValue,$cardData,$response->cardType);
            } else {
                Mage::log(Mage::helper('hps_securesubmit')->__('Requested multi token has not been generated for the transaction # %s.', $response->transactionId), Zend_Log::WARN);
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

        $config = new HpsConfiguration();
        $config->secretApiKey = $this->getConfigData('secretapikey');
        $config->versionNumber = '1509';
        $config->developerId = '002914';

        $chargeService = new HpsChargeService($config);
        try {
            $voidResponse = $chargeService->void($transactionId);
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

        $config = new HpsConfiguration();
        $config->secretApiKey = $this->getConfigData('secretapikey');
        $config->versionNumber = '1573';
        $config->developerId = '002914';

        $chargeService = new HpsChargeService($config);
        try {
            $refundResponse = $chargeService->refundTransaction(
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
            ->setTransactionId($refundResponse->transactionId)
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
                // 'last_request' => $chargeService->lastRequest,
                // 'last_response' => $chargeService->lastResponse,
            ));
        }
    }

}
