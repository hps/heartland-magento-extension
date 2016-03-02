<?php

require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_Securesubmit_Model_Masterpass extends Mage_Payment_Model_Method_Abstract
{
    protected $_code                        = 'hps_masterpass';
    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canAuthorize                = true;

    protected $_supportedCurrencyCodes = array('USD');
    protected $_minOrderTotal = 0.5;

    protected $_formBlockType = 'hps_securesubmit/masterpass_form';
    protected $_infoBlockType = 'hps_securesubmit/masterpass_info';

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
        $authenticate = Mage::getSingleton('hps_securesubmit/session')->getMasterPassAuthenticate();
        $currency = $order->getBaseCurrencyCode();

        // Billing
        $buyer = new HpsBuyerData();
        $buyer->name = $billing->getData('firstname') . ' ' . $billing->getData('lastname');

        $regionModel = Mage::getModel('directory/region')->load($billing->getRegionId());

        $address = new HpsAddress();
        $address->address = $billing->getStreet(1);
        $address->city = $billing->getCity();
        $address->state = $regionModel->getCode();
        $address->zip = preg_replace('/[^0-9]/', '', $billing->getPostcode());
        $address->country = $billing->getCountryId();
        $buyer->address = $address;

        // Shipping
        $shippingInfo = new HpsShippingInfo();
        $shippingInfo->name = $billing->getData('firstname') . ' ' . $billing->getData('lastname');

        $regionModel = Mage::getModel('directory/region')->load($shipping->getRegionId());

        $address = new HpsAddress();
        $address->address = $shipping->getStreet(1);
        $address->city = $shipping->getCity();
        $address->state = $regionModel->getCode();
        $address->zip = preg_replace('/[^0-9]/', '', $shipping->getPostcode());
        $address->country = $shipping->getCountryId();
        $shippingInfo->address = $address;

        //$details = new HpsTransactionDetails();
        //$details->invoiceNumber = $order->getIncrementId();

        try
        {
            if (!$authenticate && !$payment->getCcTransId()) {
                throw new Exception(
                    __('Error:', 'wc_securesubmit')
                    . ' Invalid MasterPass session'
                );
            }

            // Create an authorization
            $response = null;
            $orderId  = null;
            if ($capture) {
                if ($payment->getCcTransId()) {
                    $orderId = $this->getMasterPassOrderId($payment);
                    $orderData = new HpsOrderData();
                    $orderData->currencyCode = $currency;

                    $response = $this->getService()->capture(
                        $orderId,
                        $amount,
                        $orderData
                    );
                } else {
                    $response = $this->getService()->sale(
                        $authenticate->orderId,
                        $amount,
                        $currency,
                        $buyer,
                        new HpsPaymentData(),
                        $shippingInfo
                    );
                    $orderId = $authenticate->orderId;
                }
            } else {
                $response = $this->getService()->authorize(
                    $authenticate->orderId,
                    $amount,
                    $currency,
                    $buyer,
                    new HpsPaymentData(),
                    $shippingInfo
                );
                $orderId = $authenticate->orderId;
            }

            $transactionId = null;
            if (property_exists($response, 'capture')) {
                $transactionId = $response->capture->transactionId;
            } else {
                $transactionId = $response->transactionId;
            }

            // No exception thrown so action was a success
            $this->_debugChargeService($this->getService());
            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setAmount($amount);
            $payment->setLastTransId($orderId);
            $payment->setCcTransId($orderId);
            $payment->setTransactionId($transactionId);
            $payment->setIsTransactionClosed(0);
        } catch (HpsException $e) {
            $this->_debugChargeService($this->getService(), $e);
            $payment->setStatus(self::STATUS_DECLINED);
            $this->throwUserError($e->getMessage(), null, true);
        } catch (Exception $e) {
            $this->_debugChargeService($this->getService(), $e);
            Mage::logException($e);
            $payment->setStatus(self::STATUS_ERROR);
            $this->throwUserError($e->getMessage());
        }

        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();
        $orderId = $this->getMasterPassOrderId($payment);
        $currency = $order->getBaseCurrencyCode();
        $transactionId = $payment->getTransactionId();

        $orderData = new HpsOrderData();
        $orderData->currencyCode = $currency;

        try {
            $refundResponse = $this->getService()->refund(
                $orderId,
                $order->getGrandTotal() === $amount,
                $amount,
                $orderData
            );

            $this->_debugChargeService($this->getService());
            $payment
                ->setTransactionId($refundResponse->transactionId)
                ->setParentTransactionId($transactionId)
                ->setIsTransactionClosed(1)
                ->setShouldCloseParentTransaction(1);

        }
        catch (HpsException $e)
        {
            $this->_debugChargeService($this->getService(), $e);
            $this->throwUserError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_debugChargeService($this->getService(), $e);
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
        $orderId = $this->getMasterPassOrderId($payment);
        $transactionId = $payment->getTransactionId();

        try {
            $voidResponse = $this->getService()->void($orderId);

            $this->_debugChargeService($this->getService());
            $payment
                ->setTransactionId($voidResponse->transactionId)
                ->setParentTransactionId($transactionId)
                ->setIsTransactionClosed(1)
                ->setShouldCloseParentTransaction(1);
        } catch (HpsException $e) {
            $this->_debugChargeService($this->getService(), $e);
            Mage::throwException($e->getMessage());
        } catch (Exception $e) {
            $this->_debugChargeService($this->getService(), $e);
            Mage::logException($e);
            Mage::throwException(Mage::helper('hps_securesubmit')->__('An unexpected error occurred. Please try again or contact a system administrator.'));
        }

        return $this;
    }

    protected function getMasterPassOrderId($payment)
    {
        $orderId = $payment->getCcTransId();
        Mage::log($orderId);
        if ($orderId == null) {
            $orderId = $payment->getLastTransId();
        }
        Mage::log($orderId);
        return $orderId;
    }

    public function isAvailable($quote = null)
    {
        if ($quote && $quote->getBaseGrandTotal() < $this->_minOrderTotal) {
            return false;
        }

        $storeId = $quote ? $quote->getStoreId() : null;
        return $this->getConfigData('processor_id', $storeId)
            && $this->getConfigData('merchant_id', $storeId)
            && $this->getConfigData('transaction_pwd', $storeId)
            && $this->getConfigData('merchant_checkout_id', $storeId)
            && parent::isAvailable($quote);
    }

    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }

        return true;
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
     * @param HpsMasterPassService $service
     * @param Exception|null $exception
     */
    public function _debugChargeService(HpsMasterPassService $service, $exception = NULL)
    {
        if ($this->getDebugFlag()) {
            $this->_debug(array(
                'store' => Mage::app()->getStore($this->getStore())->getFrontendName(),
                'exception_message' => $exception ? get_class($exception).': '.$exception->getMessage() : '',
                // 'last_request' => $service->lastRequest,
                // 'last_response' => $service->lastResponse,
            ));
        }
    }

    protected function getService()
    {
        $config = new HpsCentinelConfig();
        if (!Mage::getStoreConfig('payment/hps_masterpass/use_sandbox')) {
            $config->serviceUri  = "https://api-uat.heartlandportico.com/paymentserver.v1/PosGatewayService.asmx";
        }
        $config->processorId    = Mage::getStoreConfig('payment/hps_masterpass/processor_id');
        $config->merchantId     = Mage::getStoreConfig('payment/hps_masterpass/merchant_id');
        $config->transactionPwd = Mage::getStoreConfig('payment/hps_masterpass/transaction_pwd');
        return new HpsMasterPassService($config);
    }
}
