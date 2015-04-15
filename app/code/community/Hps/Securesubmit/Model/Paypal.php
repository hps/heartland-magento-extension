<?php

require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_Securesubmit_Model_Paypal extends Mage_Payment_Model_Method_Cc
{
    protected $_code                        = 'hps_paypal';
    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canAuthorize                = true;

    protected $_supportedCurrencyCodes = array('USD');
    protected $_minOrderTotal = 0.5;

    protected $_formBlockType = 'hps_securesubmit/paypal_form';
    protected $_infoBlockType = 'hps_securesubmit/paypal_info';

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
        $shipping = $order->getShippingAddress();
        $multiToken = false;
        $cardData = null;
        $cardType = null;
        $additionalData = new Varien_Object($payment->getAdditionalData() ? unserialize($payment->getAdditionalData()) : null);
        $secureToken = $additionalData->getSecuresubmitToken() ? $additionalData->getSecuresubmitToken() : null;
        $saveCreditCard = !! (bool)$additionalData->getCcSaveFuture();
        $useCreditCard = !! (bool)$additionalData->getUseCreditCard();
        $token = Mage::getSingleton('hps_securesubmit/session')->getPayPalCheckoutToken();
        $payerId = Mage::getSingleton('hps_securesubmit/session')->getPayPalPayerId();

        if ($saveCreditCard && ! $useCreditCard) {
            $multiToken = true;
            $cardData = new HpsCreditCard();
            $cardData->number = $payment->getCcLast4();
            $cardData->expYear = $payment->getCcExpYear();
            $cardData->expMonth = $payment->getCcExpMonth();
        }

        $config = $this->_getServicesConfig();
        // Use HTTP proxy
        if (Mage::getStoreConfig('payment/hps_paypal/use_http_proxy')) {
            $config->useProxy = true;
            $config->proxyOptions = array(
                'proxy_port' => Mage::getStoreConfig('payment/hps_paypal/http_proxy_port'),
                'proxy_host' => Mage::getStoreConfig('payment/hps_paypal/http_proxy_host'),
            );
        }


        $payPalService = new HpsPayPalService($config);

        $regionModel = Mage::getModel('directory/region')->load($shipping->getRegionId());

        $address = new HpsAddress();
        $address->address = $shipping->getStreet(1);
        $address->city = $shipping->getCity();
        $address->state = $regionModel->getCode();
        $address->zip = preg_replace('/[^0-9]/', '', $shipping->getPostcode());
        $address->country = $shipping->getCountryId();

        $currency = $order->getBaseCurrencyCode();
        $taxAmount = $order->getTaxAmount();
        $shippingAmount = $order->getShippingAmount();
        $subtotal = $amount - $taxAmount - $shippingAmount;

        $buyer = new HpsBuyerData();
        $buyer->payerId = $payerId;
        $buyer->emailAddress = $shipping->getData('email');

        $paymentData = new HpsPaymentData();
        $paymentData->subtotal = sprintf("%0.2f", round($subtotal, 3));
        $paymentData->shippingAmount = sprintf("%0.2f", round($shippingAmount, 3));
        $paymentData->taxAmount = sprintf("%0.2f", round($taxAmount, 3));

        $shippingInfo = new HpsShippingInfo();
        $shippingInfo->name = $billing->getData('firstname') . ' ' . $billing->getData('lastname');
        $shippingInfo->address = $address;

        //$details = new HpsTransactionDetails();
        //$details->invoiceNumber = $order->getIncrementId();

        try
        {
            if ($capture)
            {
                if ($payment->getCcTransId())
                {
                    $response = $payPalService->capture(
                        $payment->getCcTransId(),
                        $amount
                    );
                }
                else
                {
                    $response = $payPalService->sale(
                        $token,
                        $amount,
                        $currency,
                        $buyer,
                        $paymentData,
                        $shippingInfo
                    );
                }
            }
            else
            {
                $response = $payPalService->authorize(
                    $token,
                    $amount,
                    $currency,
                    $buyer,
                    $paymentData,
                    $shippingInfo
                );
            }

            $report = Mage::getModel('hps_securesubmit/report')
                    ->loadByOrderId($order->getRealOrderId());
            if ($report->getData() === array()) {
                $report = Mage::getModel('hps_securesubmit/report');
                $report
                    ->setOrderId($order->getRealOrderId())
                    ->setPayerEmail($shipping->getData('email'))
                    ->setCreatedTime(date('Y-m-d H:i:s'));
            }
            $report
                ->setTransactionId($response->transactionId)
                ->setLastKnownStatus($response->status . ' (' . $response->statusMessage . ')')
                ->setUpdateTime(date('Y-m-d H:i:s'))
                ->save();

            // No exception thrown so action was a success
            $this->_debugChargeService($payPalService);
            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setAmount($amount);
            $payment->setLastTransId($response->transactionId);
            $payment->setCcTransId($response->transactionId);
            $payment->setTransactionId($response->transactionId);
            $payment->setIsTransactionClosed(0);


            // $info = new Varien_Object(
            //     $this->getInfoInstance()->getAdditionalData() ?
            //     unserialize($this->getInfoInstance()->getAdditionalData()) :
            //     null
            // );
            // $info->setBuyerEmailAddress($shipping->getData('email'));
            // $this->getInfoInstance()->setAdditionalData(serialize($info));
        }
        catch (HpsProcessorException $e) {
            $this->_debugChargeService($payPalService, $e);
            $payment->setStatus(self::STATUS_DECLINED);
            $this->throwUserError($e->getMessage(), null, TRUE);
        }
        catch (Exception $e)
        {
            $this->_debugChargeService($payPalService, $e);
            Mage::logException($e);
            $payment->setStatus(self::STATUS_ERROR);
            $this->throwUserError($e->getMessage());
        }

        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        $transactionId = $payment->getCcTransId();
        $order = $payment->getOrder();

        $config = $this->_getServicesConfig();
        $payPalService = new HpsPayPalService($config);

        try {
            $refundResponse = $payPalService->refund(
                $transactionId,
                $payment->getBaseTotalDue() == $amount,
                $amount
            );

            $this->_debugChargeService($payPalService);
            $payment
                ->setTransactionId($refundResponse->transactionId)
                ->setParentTransactionId($transactionId)
                ->setIsTransactionClosed(1)
                ->setShouldCloseParentTransaction(1);

        }
        catch (HpsException $e)
        {
            $this->_debugChargeService($payPalService, $e);
            $this->throwUserError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_debugChargeService($payPalService, $e);
            Mage::logException($e);
            $this->throwUserError($e->getMessage());
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
        $config = $this->_getServicesConfig();

        $payPalService = new HpsPayPalService($config);
        try {
            $voidResponse = $payPalService->void($transactionId);

            $this->_debugChargeService($payPalService);
            $payment
                ->setTransactionId($voidResponse->transactionId)
                ->setParentTransactionId($transactionId)
                ->setIsTransactionClosed(1)
                ->setShouldCloseParentTransaction(1);
        }
        catch (HpsException $e)
        {
            $this->_debugChargeService($payPalService, $e);
            Mage::throwException($e->getMessage());
        }
        catch (Exception $e) {
            $this->_debugChargeService($payPalService, $e);
            Mage::logException($e);
            Mage::throwException(Mage::helper('hps_securesubmit')->__('An unexpected error occurred. Please try again or contact a system administrator.'));
        }

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
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @see Mage_Checkout_OnepageController::savePaymentAction()
     * @see Mage_Sales_Model_Quote_Payment::getCheckoutRedirectUrl()
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return Mage::getUrl('securesubmit/paypal/start');
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
     * @param HpsPayPalService $payPalService
     * @param Exception|null $exception
     */
    public function _debugChargeService(HpsPayPalService $payPalService, $exception = NULL)
    {
        if ($this->getDebugFlag()) {
            $this->_debug(array(
                'store' => Mage::app()->getStore($this->getStore())->getFrontendName(),
                'exception_message' => $exception ? get_class($exception).': '.$exception->getMessage() : '',
                // 'last_request' => $payPalService->lastRequest,
                // 'last_response' => $payPalService->lastResponse,
            ));
        }
    }

    protected function _getServicesConfig()
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
        return $config;
    }
}
