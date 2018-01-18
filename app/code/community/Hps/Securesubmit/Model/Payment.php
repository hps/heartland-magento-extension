<?php
require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';

/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */
class Hps_Securesubmit_Model_Payment extends Mage_Payment_Model_Method_Cc
{
    const FRAUD_TEXT_DEFAULT              = '%s';
    const FRAUD_VELOCITY_ATTEMPTS_DEFAULT = 3;
    const FRAUD_VELOCITY_TIMEOUT_DEFAULT  = 10;

    protected $_code                        = 'hps_securesubmit';
    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canAuthorize                = true;
    protected $_supportedCurrencyCodes      = array('USD');
    protected $_minOrderTotal               = 0.5;
    protected $_formBlockType               = 'hps_securesubmit/form';
    protected $_formBlockTypeAdmin          = 'hps_securesubmit/adminhtml_form';
    protected $_infoBlockType               = 'hps_securesubmit/info';
    protected $_enable_anti_fraud           = null;
    protected $_allow_fraud                 = null;
    protected $_email_fraud                 = null;
    protected $_fraud_address               = null;
    protected $_fraud_text                  = null;
    protected $_use_iframes                 = null;
    protected $_fraud_velocity_attempts     = null;
    protected $_fraud_velocity_timeout      = null;

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
        $secureToken = $additionalData->getSecuresubmitToken() ? $additionalData->getSecuresubmitToken() : null;
        // Gracefully handle javascript errors.
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        $link_path = explode('/', rtrim($currentUrl, '/'));
        $path = end($link_path);

        if ((!$secureToken) && ($path != 'savePaymentMethod')) {
            Mage::log('Payment information submitted without token.', Zend_Log::ERR);
            $this->throwUserError(Mage::helper('hps_securesubmit')->__('An unexpected error occurred. Please try resubmitting your payment information.'), null, true);
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
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @param bool $capture
     * @return  $this
     */
    private function _authorize(Varien_Object $payment, $amount, $capture)
    {
        $this->getFraudSettings();

        $order = $payment->getOrder(); /* @var $order Mage_Sales_Model_Order */
        $multiToken = false;
        $cardData = null;
        $additionalData = new Varien_Object($payment->getAdditionalData() ? unserialize($payment->getAdditionalData()) : null);
        $secureToken = $additionalData->getSecuresubmitToken() ? $additionalData->getSecuresubmitToken() : null;
        $saveCreditCard = !! (bool)$additionalData->getCcSaveFuture();
        $customerId = $additionalData->getCustomerId();
        $giftService = $this->_getGiftService();
        $giftCardNumber = $additionalData->getGiftcardNumber();
        $giftCardPin = filter_var($additionalData->getGiftcardPin(),FILTER_VALIDATE_INT, ARRAY('default' => FILTER_NULL_ON_FAILURE));
        $ccaData = $additionalData->getCcaData();

        if ($giftCardNumber) {
            // 1. check balance
            $giftcard = new HpsGiftCard();
            $giftcard->number = $giftCardNumber;
            $giftcard->pin = $giftCardPin;
            $giftResponse = $giftService->balance($giftcard);

            // 2. is balance > amount?
            if ($giftResponse->balanceAmount > $amount) {
                //  2.yes. process full to gift
                try {
                    $this->checkVelocity();

                    if (strpos($this->getConfigData('secretapikey'), '_cert_') !== false) {
                        $giftresp = $giftService->sale($giftcard, 10.00);
                    } else {
                        $giftresp = $giftService->sale($giftcard, $amount);
                    }

                    $order->addStatusHistoryComment('Used Heartland Gift Card ' . $giftCardNumber . ' for amount $' . $amount . '. [full payment]');
                    $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                        array(
                            'gift_card_number' => $giftCardNumber,
                            'gift_card_transaction' => $giftresp->transactionId,
                            'gift_card_amount_charged' => $amount));

                    // just adds a trackable type for the DB
                    $giftresp->cardType = 'Gift';
                    // \Hps_Securesubmit_Model_Payment::closeTransaction
                    $this->closeTransaction($payment,$amount,$giftresp);
                    return $this;
                } catch (Exception $e) {
                    $this->updateVelocity($e);

                    Mage::logException($e);
                    $payment->setStatus(self::STATUS_ERROR);
                    $this->throwUserError($e->getMessage(), null, true);
                }
            } else {
                //  2.no. process full gift card amt and card process remainder
                try {
                    $this->checkVelocity();

                    $giftresp = $giftService->sale($giftcard, $giftResponse->balanceAmount);
                    $order->addStatusHistoryComment('Used Heartland Gift Card ' . $giftCardNumber . ' for amount $' . $giftResponse->balanceAmount . '. [partial payment]')->save();
                    $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                        array(
                            'gift_card_number' => $giftCardNumber,
                            'gift_card_transaction' => $giftresp->transactionId,
                            'gift_card_amount_charged' => $giftResponse->balanceAmount));
                    $payment->setAmount($giftResponse->balanceAmount)->save();
                    $amount = $amount - $giftResponse->balanceAmount; // remainder
                } catch (Exception $e) {
                    $this->updateVelocity($e);

                    Mage::logException($e);
                    $payment->setStatus(self::STATUS_ERROR);
                    $this->throwUserError($e->getMessage(), null, true);
                }
                // 3. TODO: if the card payment fails later, refund the gift transaction
            }
        }

        $cardType = $payment->getCcType();
        if ($saveCreditCard) {
            $multiToken = true;
            $cardData = new HpsCreditCard();
            $cardData->number = $payment->getCcLast4();
            $cardData->expYear = $payment->getCcExpYear();
            $cardData->expMonth = $payment->getCcExpMonth();
        }

        $chargeService = $this->_getChargeService();
        $cardHolder = $this->_getCardHolderData($order);
        $details = $this->_getTxnDetailsData($order);
        $cardOrToken = new HpsTokenData();
        $cardOrToken->tokenValue = $secureToken;
        $secureEcommerce = $this->getSecureEcommerce($ccaData, $cardType);

        try {
            $this->checkVelocity();

            $captureBuilder = false;
            $builder = null;
            if ($capture) {
                if ($payment->getCcTransId()) {
                    $builder = $chargeService->capture()
                        ->withTransactionId($payment->getCcTransId())
                        ->withAmount();
                    $captureBuilder = true;
                } else {
                    $builder = $chargeService->charge()
                        ->withAmount($amount)
                        ->withCurrency(strtolower($order->getBaseCurrencyCode()))
                        ->withToken($cardOrToken)
                        ->withCardHolder($cardHolder)
                        ->withRequestMultiUseToken($multiToken)
                        ->withDetails($details);
                }
            } else {
                $builder = $chargeService->authorize()
                    ->withAmount($amount)
                    ->withCurrency(strtolower($order->getBaseCurrencyCode()))
                    ->withToken($cardOrToken)
                    ->withCardHolder($cardHolder)
                    ->withRequestMultiUseToken($multiToken)
                    ->withDetails($details);
            }

            if (false === $captureBuilder && null !== $secureEcommerce) {
                $builder = $builder->withSecureEcommerce($secureEcommerce);
            }

            $response = $builder->execute();

            $this->_debugChargeService($chargeService);
            // \Hps_Securesubmit_Model_Payment::closeTransaction
            $this->closeTransaction($payment, $amount, $response);

            if ($giftCardNumber) {
                $order->addStatusHistoryComment('Remaining amount to be charged to credit card  ' .$this->_formatAmount((string)$amount) . '. [partial payment]')->save();
            }

            if ($multiToken) {
                $this->saveMultiUseToken($response, $cardData, $customerId, $cardType);
            }
        } catch (HpsCreditException $e) {
            $this->updateVelocity($e);

            Mage::logException($e);
            $this->_debugChargeService($chargeService, $e);

            // refund gift (if used)
            if ($giftCardNumber) {
                $order->addStatusHistoryComment('Reversed Heartland Gift Card ' . $giftCardNumber . ' for amount $' . $giftResponse->balanceAmount . '. [full reversal]')->save();
                $giftResponse = $giftService->reverse($giftcard, $giftResponse->balanceAmount);
            }

            if ($this->_allow_fraud && $e->getCode() == HpsExceptionCodes::POSSIBLE_FRAUD_DETECTED) {
                // we can skip the card saving if it fails for possible fraud there will be no token.
                if ($this->_email_fraud && $this->_fraud_address != '') {
                    // EMAIL THE PEOPLE
                    $this->sendEmail(
                        $this->_fraud_address,
                        $this->_fraud_address,
                        'Suspicious order (' . $order->getIncrementId() . ') allowed',
                        'Hello,<br><br>Heartland has determined that you should review order ' . $order->getRealOrderId() . ' for the amount of ' . $amount . '.'
                    );
                }

                $this->closeTransaction($payment,$amount,$e);
            } else {
                $payment->setStatus(self::STATUS_ERROR);

                if ($e->getCode() == HpsExceptionCodes::POSSIBLE_FRAUD_DETECTED) {
                    $this->throwUserError($this->_fraud_text, null, true);
                } else {
                    $this->throwUserError($e->getMessage(), null, true);
                }
            }
        } catch (HpsException $e) {
            $this->_debugChargeService($chargeService, $e);
            $payment->setStatus(self::STATUS_ERROR);
            $this->throwUserError($e->getMessage(), null, true);
        } catch (Exception $e) {
            $this->_debugChargeService($chargeService, $e);
            Mage::logException($e);
            $payment->setStatus(self::STATUS_ERROR);
            $this->throwUserError($e->getMessage());
        }

        return $this;
    }

    /**
     * @param Varien_Object|Mage_Sales_Model_Order_Payment  $payment
     * @param float                                         $amount
     * @param HpsReportTransactionDetails|HpsAuthorization  $response
     * @param Mage_Payment_Model_Method_Abstract::STATUS_UNKNOWN|STATUS_APPROVED|STATUS_ERROR|STATUS_DECLINED|STATUS_VOID|STATUS_SUCCESS                                   $status
     */
    protected function closeTransaction($payment, $amount, $response, $status = self::STATUS_APPROVED){
        $info = $this->getInfoInstance();
        $details = unserialize($info->getAdditionalData());

        $payment->setStatus($status);
        $payment->setAmount($amount);
        $payment->setLastTransId($response->transactionId);
        $payment->setCcTransId(($response instanceof HpsReportTransactionDetails) ? $payment->getCcTransId() : $response->transactionId);
        $payment->setTransactionId($response->transactionId);
        $payment->setIsTransactionClosed(0);

        $details['cc_type'] = $payment->getCcType();

        if (property_exists($response, 'authorizationCode')) {
            $payment->setCcApproval($response->authorizationCode);
            $details['auth_code'] = $response->authorizationCode;
        }

        if (property_exists($response, 'avsResultCode')) {
            $payment->setCcAvsStatus($response->avsResultCode);
            $details['avs_response_code'] = $response->avsResultCode;
            $details['avs_response_text'] = $response->avsResultText;
        }

        if (property_exists($response, 'cvvResultCode')) {
            $details['cvv_response_code'] = $response->cvvResultCode;
            $details['cvv_response_text'] = $response->cvvResultText;
        }

        $info->setAdditionalData(serialize($details));
    }

    protected function saveMultiUseToken($response, $cardData, $customerId, $cardType)
    {
        $tokenData = $response->tokenData; /* @var $tokenData HpsTokenData */

        if ($tokenData->responseCode == '0') {
            try {
                $this->_getChargeService()->updateTokenExpiration($tokenData->tokenValue, $cardData->expMonth, $cardData->expYear);
            } catch (Exception $e) {
                Mage::logException($e);
            }

            if ($customerId > 0) {
                Mage::helper('hps_securesubmit')->saveMultiToken($tokenData->tokenValue, $cardData, $cardType, $customerId);
            } else {
                Mage::helper('hps_securesubmit')->saveMultiToken($tokenData->tokenValue, $cardData, $cardType);
            }
        } else {
            Mage::log('Requested multi token has not been generated for the transaction # ' . $response->transactionId, Zend_Log::WARN);
        }
    }

    protected function getSecureEcommerce($ccaData, $cardType)
    {
        if ($this->getConfigData('enable_threedsecure')
            && !empty($ccaData) && !empty($ccaData['actionCode'])
            && in_array($ccaData['actionCode'], array('SUCCESS', 'NOACTION'))
        ) {
            $dataSource = '';
            switch ($cardType) {
            case 'visa':
                $dataSource = 'Visa 3DSecure';
                break;
            case 'mastercard':
                $dataSource = 'MasterCard 3DSecure';
                break;
            case 'discover':
                $dataSource = 'Discover 3DSecure';
                break;
            case 'amex':
                $dataSource = 'AMEX 3DSecure';
                break;
            }
            $cavv = isset($ccaData['cavv'])
                ? $ccaData['cavv']
                : '';
            $eciFlag = isset($ccaData['eci'])
                ? substr($ccaData['eci'], 1)
                : '';
            $xid = isset($ccaData['xid'])
                ? $ccaData['xid']
                : '';
            $secureEcommerce = new HpsSecureEcommerce();
            $secureEcommerce->type       = '3DSecure';
            $secureEcommerce->dataSource = $dataSource;
            $secureEcommerce->data       = $cavv;
            $secureEcommerce->eciFlag    = $eciFlag;
            $secureEcommerce->xid        = $xid;
            return $secureEcommerce;
        }

        return false;
    }
    protected function _formatAmount($amount)
    {
        return Mage::helper('core')->currency($amount, true, false);
    }

    protected function getFraudSettings()
    {
        if ($this->_enable_anti_fraud === null) {
            $this->_enable_anti_fraud       = Mage::getStoreConfig('payment/hps_securesubmit/enable_anti_fraud') == 1;
            $this->_allow_fraud             = Mage::getStoreConfig('payment/hps_securesubmit/allow_fraud') == 1;
            $this->_email_fraud             = Mage::getStoreConfig('payment/hps_securesubmit/email_fraud') == 1;
            $this->_fraud_address           = (string)Mage::getStoreConfig('payment/hps_securesubmit/fraud_address');
            $this->_fraud_text              = (string)Mage::getStoreConfig('payment/hps_securesubmit/fraud_text');
            $this->_fraud_velocity_attempts = (int)Mage::getStoreConfig('payment/hps_securesubmit/fraud_velocity_attempts');
            $this->_fraud_velocity_timeout  = (int)Mage::getStoreConfig('payment/hps_securesubmit/fraud_velocity_timeout');

            if ($this->_fraud_text === null) {
                $this->_fraud_text = self::FRAUD_TEXT_DEFAULT;
            }

            if ($this->_fraud_velocity_attempts === null
                || !is_numeric($this->_fraud_velocity_attempts)
            ) {
                $this->_fraud_velocity_attempts = self::FRAUD_VELOCITY_ATTEMPTS_DEFAULT;
            }

            if ($this->_fraud_velocity_timeout === null
                || !is_numeric($this->_fraud_velocity_timeout)
            ) {
                $this->_fraud_velocity_timeout = self::FRAUD_VELOCITY_TIMEOUT_DEFAULT;
            }
        }
    }

    protected function maybeResetVelocityTimeout()
    {
        $timeoutSeconds = $this->_fraud_velocity_timeout * 60;
        $timeoutExpiration = (int)$this->getVelocityVar('TimeoutExpiration');

        if (time() < $timeoutExpiration) {
            return;
        }

        $this->unsVelocityVar('Count');
        $this->unsVelocityVar('IssuerResponse');
        $this->unsVelocityVar('TimeoutExpiration');
    }

    protected function checkVelocity()
    {
        if ($this->_enable_anti_fraud !== true) {
            return;
        }

        $this->maybeResetVelocityTimeout();

        $count = (int)$this->getVelocityVar('Count');
        $issuerResponse = (string)$this->getVelocityVar('IssuerResponse');
        $timeoutExpiration = (int)$this->getVelocityVar('TimeoutExpiration');

        if ($count >= $this->_fraud_velocity_attempts
            && time() < $timeoutExpiration) {
            sleep(5);
            throw new HpsException(sprintf($this->_fraud_text, $issuerResponse));
        }
    }

    protected function updateVelocity($e)
    {
        if ($this->_enable_anti_fraud !== true) {
            return;
        }

        $this->maybeResetVelocityTimeout();

        $count = (int)$this->getVelocityVar('Count');
        $issuerResponse = (string)$this->getVelocityVar('IssuerResponse');
        if ($issuerResponse !== $e->getMessage()) {
            $issuerResponse = $e->getMessage();
        }
        //                   NOW    + (fraud velocity timeout in seconds)
        $timeoutExpiration = time() + ($this->_fraud_velocity_timeout * 60);

        $this->setVelocityVar('Count', $count + 1);
        $this->setVelocityVar('IssuerResponse', $issuerResponse);
        $this->setVelocityVar('TimeoutExpiration', $timeoutExpiration);
    }

    protected function getVelocityVar($var)
    {
        return Mage::getSingleton('checkout/session')
            ->getData($this->getVelocityVarPrefix() . $var);
    }

    protected function setVelocityVar($var, $data = null)
    {
        return Mage::getSingleton('checkout/session')
            ->setData($this->getVelocityVarPrefix() . $var, $data);
    }

    protected function unsVelocityVar($var)
    {
        return Mage::getSingleton('checkout/session')
            ->unsetData($this->getVelocityVarPrefix() . $var);
    }

    protected function getVelocityVarPrefix()
    {
        return sprintf('HeartlandHPS_Velocity%s', md5($this->getRemoteIP()));
    }

    protected function getRemoteIP()
    {
        static $remoteIP = '';
        if ($remoteIP !== '') {
            return $remoteIP;
        }
        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)
            && $_SERVER['HTTP_X_FORWARDED_FOR'] != ''
        ) {
            $remoteIPArray = array_values(
                array_filter(
                    explode(
                        ',',
                        $_SERVER['HTTP_X_FORWARDED_FOR']
                    )
                )
            );
            $remoteIP = end($remoteIPArray);
        } else {
            $remoteIP = $_SERVER['REMOTE_ADDR'];
        }
        return $remoteIP;
    }


    /**
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return Hps_Securesubmit_Model_Payment
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $transactionDetails = $this->getTransactionDetails($payment);
        if ($this->canVoid($payment) && $this->transactionActiveOnGateway($transactionDetails)) {
            if ($transactionDetails->settlementAmt > $amount) {
                $this->_reversal($payment, $transactionDetails, $amount);
            } else {
                $this->void($payment);
            }
        } else {
            $this->_refund($payment, $amount);
        }

        return $this;
    }


    public function getTransactionDetails(Varien_Object $payment)
    {
        $transactionId = null;

        if (false !== ($parentId = $this->getParentTransactionId($payment))) {
            $transactionId = $parentId;
        } else {
            $transactionId = $payment->getCcTransId();
        }

        $service = $this->_getChargeService();
        return $service->get($transactionId)->execute();
    }


    public function transactionActiveOnGateway($transactionDetail)
    {
        return $transactionDetail->transactionStatus == 'A';
    }

    public function getParentTransactionId(Varien_Object $payment)
    {
        $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->addAttributeToFilter('order_id', array('eq' => $payment->getOrder()->getEntityId()))
            ->addAttributeToFilter('txn_type', array('eq' => 'capture'))
            ->toArray();
        if ($transaction['totalRecords'] == 1) {
            return isset($transaction['items'][0]['parent_txn_id'])
                ? $transaction['items'][0]['parent_txn_id'] : false;
        } else {
            return false;
        }
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
        $transactionId = null;

        if (false !== ($parentId = $this->getParentTransactionId($payment))) {
            $transactionId = $parentId;
        } else {
            $transactionId = $payment->getCcTransId();
        }

        $chargeService = $this->_getChargeService();

        try {
            $voidResponse = $chargeService->void()
                ->withTransactionId($transactionId)
                ->execute();
            $payment
                ->setTransactionId($voidResponse->transactionId)
                ->setParentTransactionId($transactionId)
                ->setIsTransactionClosed(1)
                ->setShouldCloseParentTransaction(1);
        } catch (HpsException $e) {
            $this->_debugChargeService($chargeService, $e);
            $this->throwUserError($e->getMessage());
        } catch (Exception $e) {
            $this->_debugChargeService($chargeService, $e);
            Mage::logException($e);
            $this->throwUserError(Mage::helper('hps_securesubmit')->__('An unexpected error occurred. Please try again or contact a system administrator.'));
        }

        return $this;
    }

    /**
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param $amount
     * @return Hps_Securesubmit_Model_Payment
     */
    public function _refund(Varien_Object $payment, $amount)
    {
        $transactionId = $payment->getCcTransId();
        $order = $payment->getOrder(); /* @var $order Mage_Sales_Model_Order */
        $chargeService = $this->_getChargeService();
        $cardHolder = $this->_getCardHolderData($order);
        $details = $this->_getTxnDetailsData($order);

        try {
            $refundResponse = $chargeService->refund()
                ->withAmount($amount)
                ->withCurrency(strtolower($order->getBaseCurrencyCode()))
                ->withTransactionId($transactionId)
                ->withCardHolder($cardHolder)
                ->withDetails($details)
                ->execute();
            $payment
                ->setTransactionId($refundResponse->transactionId)
                ->setParentTransactionId($transactionId)
                ->setIsTransactionClosed(1)
                ->setShouldCloseParentTransaction(1);
        } catch (HpsException $e) {
            $this->_debugChargeService($chargeService, $e);
            $this->throwUserError($e->getMessage());
        } catch (Exception $e) {
            $this->_debugChargeService($chargeService, $e);
            Mage::logException($e);
            $this->throwUserError($e->getMessage());
        }

        return $this;
    }


    /**
     * @param Varien_Object|Mage_Sales_Model_Order_Payment  $payment
     * @param HpsReportTransactionDetails                   $transactionDetails
     * @param float                                         $newAuthAmount
     * @return Hps_Securesubmit_Model_Payment
     */
    public function _reversal(Varien_Object $payment, HpsReportTransactionDetails $transactionDetails, $newAuthAmount)
    {

        $transactionId = null;

        if (false !== ($parentId = $this->getParentTransactionId($payment))) {
            $transactionId = $parentId;
        } else {
            $transactionId = $payment->getCcTransId();
        }
        $newAuthAmount = $transactionDetails->settlementAmt-$newAuthAmount;
        $order = $payment->getOrder();
        /* @var $order Mage_Sales_Model_Order */
        $chargeService = $this->_getChargeService();
        $details = $this->_getTxnDetailsData($order);
        try {
            $reverseResponse = $chargeService->reverse()
                ->withTransactionId($transactionId)
                ->withAmount($transactionDetails->authorizedAmount)
                ->withCurrency(strtolower($order->getBaseCurrencyCode()))
                ->withDetails($details)
                ->withAuthAmount($newAuthAmount)
                ->execute();
            $payment
                ->setTransactionId($reverseResponse->transactionId)
                ->setParentTransactionId($transactionId)
                ->setIsTransactionClosed(1)
                ->setShouldCloseParentTransaction(1);
        } catch (HpsException $e) {

            $this->_debugChargeService($chargeService, $e);
            $this->throwUserError($e->getMessage());
        } catch (Exception $e) {
            $this->_debugChargeService($chargeService, $e);
            Mage::logException($e);
            $this->throwUserError($e->getMessage());
        }

        return $this;
    }
    /**
     * @param null|Mage_Sales_Model_Quote $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        if ($quote && $quote->getBaseGrandTotal() < $this->_minOrderTotal) {
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

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();

        if (!$info->getCcLast4() && $data->getCcLastFour()) {
            $info->setCcLast4($data->getCcLastFour());
        }

        $details = array();

        if ($data->getData('cc_save_future')) {
            $details['cc_save_future'] = 1;
        }

        if ($data->getData('securesubmit_token')) {
            $details['securesubmit_token'] = $data->getData('securesubmit_token');
        }

        if ($data->getData('giftcard_number')) {
            $details['giftcard_number'] = $data->getData('giftcard_number');
        }

        if ($data->getData('giftcard_pin')) {
            $details['giftcard_pin'] = $data->getData('giftcard_pin');
        }

        if ($data->getData('giftcard_skip_cc')) {
            $details['giftcard_skip_cc'] = $data->getData('giftcard_skip_cc') === 'true';
        }

        if ($data->getData('use_credit_card')) {
            $details['use_credit_card'] = 1;
        }

        if ($data->getData('customer_id')) {
            $details['customer_id'] = $data->getData('customer_id');
        }

        $ccaData = array();

        if ($data->getData('cca_data_action_code')) {
            $ccaData['actionCode'] = $data->getData('cca_data_action_code');
        }

        if ($data->getData('cca_data_cavv')) {
            $ccaData['cavv'] = $data->getData('cca_data_cavv');
        }

        if ($data->getData('cca_data_eci')) {
            $ccaData['eci'] = $data->getData('cca_data_eci');
        }

        if ($data->getData('cca_data_xid')) {
            $ccaData['xid'] = $data->getData('cca_data_xid');
        }

        if ($data->getData('cca_data_token')) {
            $ccaData['token'] = $data->getData('cca_data_token');
        }

        if (array() !== $ccaData) {
            $details['cca_data'] = $ccaData;
        }

        if (!empty($details)) {
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
    public function throwUserError($error, $detailedError = null, $goToPaymentSection = false)
    {
        // Register detailed error for error reporting elsewhere
        $detailedError = $detailedError != null ?  $error.' ['.$detailedError.']' : $error;
        Mage::unregister('payment_detailed_error');
        Mage::register('payment_detailed_error', $detailedError);

        // Replace gateway error with custom error message for customers
        $error = Mage::helper('hps_securesubmit')->__($error);
        if ($customMessage = $this->getConfigData('custom_message')) {
            $error = sprintf($customMessage, $error);
        }

        // Send checkout session back to payment section to avoid double-attempt to charge single-use token
        if ($goToPaymentSection === true) {
            Mage::log('throwing user error with Mage_Payment_Model_Info_Exception: ' . $error);
            throw new Mage_Payment_Model_Info_Exception($error);
        } else {
            Mage::log('throwing user error with Mage_Core_Exception: ' . $error);
            throw new Mage_Core_Exception($error);
        }
    }

    /**
     * @return HpsCreditService
     */
    protected function _getChargeService()
    {
        $config = new HpsServicesConfig();

        // Support HTTP proxy
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

        return new HpsFluentCreditService($config);
    }

    protected function _getGiftService()
    {
        $config = new HpsServicesConfig();

        // Support HTTP proxy
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

        return new HpsGiftCardService($config);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return HpsCardHolder
     */
    protected function _getCardHolderData($order)
    {
        $billing = $order->getBillingAddress();

        $address = new HpsAddress();
        $address->address = substr($billing->getStreet(1), 0, 40);        // Actual limit unknown..
        $address->city = substr($billing->getCity(), 0, 20);
        $address->state = substr($billing->getRegion(), 0, 20);
        $address->zip = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($billing->getPostcode())), 0, 9);
        $address->country = $billing->getCountry();

        $cardHolder = new HpsCardHolder();
        $cardHolder->firstName = substr($billing->getData('firstname'), 0, 26);
        $cardHolder->lastName = substr($billing->getData('lastname'), 0, 26);
        $cardHolder->phone = substr(preg_replace('/[^0-9]/', '', $billing->getTelephone()), 0, 10);
        $cardHolder->emailAddress = substr($billing->getData('email'), 0, 40);
        $cardHolder->address = $address;

        return $cardHolder;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return HpsTransactionDetails
     */
    protected function _getTxnDetailsData($order)
    {
        $memo = array();
        $ip = '';

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        if ($ip) {
            $memo[] = 'Customer IP Address: '.$ip;
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $memo[] = 'User Agent: '.$_SERVER['HTTP_USER_AGENT'];
        }

        $memo = implode(', ', $memo);
        $details = new HpsTransactionDetails();
        $details->memo = substr($memo, 0, 200);                           // Actual limit unknown..
        $details->invoiceNumber = $order->getIncrementId();
        $details->customerId = substr($order->getCustomerEmail(), 0, 25); // Actual limit unknown..

        return $details;
    }

    /**
     * @param HpsCreditService $chargeService
     * @param Exception|null $exception
     */
    protected function _debugChargeService(HpsFluentCreditService $chargeService, $exception = null)
    {
        if ($this->getDebugFlag()) {
            $debugData = array(
                'store' => Mage::app()->getStore($this->getStore())->getFrontendName(),
                'exception_message' => $exception ? get_class($exception).': '.$exception->getMessage() : '',
                // 'last_request' => $chargeService->lastRequest,
                // 'last_response' => $chargeService->lastResponse,
            );
            $this->_debug($debugData);
        }
    }

    public function sendEmail($to, $from, $subject, $body, $headers = array(), $isHtml = true)
    {
        $headers[] = sprintf('From: %s', $from);
        $headers[] = sprintf('Reply-To: %s', $from);
        $message = $body;

        if ($isHtml) {
            $message = sprintf('<html><body>%s</body></html>', $body);
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=ISO-8859-1';
        }

        $message = wordwrap($message, 70, "\r\n");
        mail($to, $subject, $message, implode("\r\n", $headers));
    }

    /**
     * Retrieve block type for method form generation
     *
     * @return string
     */
    public function getFormBlockType()
    {
        return Mage::app()->getStore()->isAdmin() ? $this->_formBlockTypeAdmin : $this->_formBlockType;
    }
}
