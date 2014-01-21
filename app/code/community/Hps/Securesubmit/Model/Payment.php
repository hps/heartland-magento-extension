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

    /**
     * Fields that should be replaced in debug with '***'
     *
     * @var array
     */
    protected $_debugReplacePrivateDataKeys = array('SecretAPIKey');

    public function __construct()
    {
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


        if (isset($_POST['payment']['securesubmit_token']) && $_POST['payment']['securesubmit_token'])
        {
            $secureToken = $_POST['payment']['securesubmit_token'];
        }

        if (isset($_POST['payment']['cc_save_future']) && $_POST['payment']['cc_save_future']){
            $multiToken = true;
            $cardData = new HpsCardInfo(
                $_POST['payment']['cc_last_four'],
                $_POST['payment']['cc_exp_year'],
                $_POST['payment']['cc_exp_month']);
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
        $config->versionNbr = '1509';
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

        $token = new HpsToken(
            $secureToken,
            null,
            null);

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
                        $token,
                        $cardHolder,
                        $multiToken);
                }
            }
            else
            {
                $response = $chargeService->Authorize(
                    $amount,
                    strtolower($order->getBaseCurrencyCode()),
                    $token,
                    $cardHolder,
                    $multiToken);
            }
        }
        catch (Exception $e)
        {
            if($e instanceof CardException || $e instanceof AuthenticationException || $e instanceof HpsException){
                Mage::throwException(Mage::helper('paygate')->__($e->getMessage()));
            }else{
                Mage::throwException(Mage::helper('paygate')->__($e->getMessage()));
            }
            return;
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
                Mage::helper('hps_securesubmit')->saveMultiToken($response->TokenData->TokenValue,$cardData,$response->TransactionDetails->CardType);
            }
        }
        else
        {
            if (!$payment->getCcTransId())
            {
                $this->setStore($payment->getOrder()->getStoreId());
                $payment->setStatus(self::STATUS_ERROR);
                Mage::throwException(Mage::helper('paygate')->__($response->TransactionDetails->ResponseMessage));
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
        $config->versionNbr = '1509';
        $config->developerId = '002914';

        try {

            $chargeService = new HpsChargeService($config);

            $refundResponse = $chargeService->RefundWithTransactionId(
                $amount,
                strtolower($order->getBaseCurrencyCode()),
                $transactionId);

        } catch (Exception $e) {
            if($e instanceof ApiConnectionException || $e instanceof InvalidRequestException || $e instanceof HpsException){
                Mage::throwException(Mage::helper('paygate')->__($e->Message()));
            }else{
                Mage::throwException(Mage::helper('paygate')->__($e->getMessage()));
            }
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

}
