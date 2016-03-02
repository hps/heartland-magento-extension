<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */
require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';

class Hps_Securesubmit_Helper_Paypal extends Hps_Securesubmit_Helper_Altpayment_Abstract
{
    protected $_methodCode = 'hps_paypal';

    /**
     * Reserve order ID for specified quote and start checkout on PayPal
     *
     * @param string $returnUrl
     * @param string $cancelUrl
     * @param array|null $params
     * @return mixed
     */
    public function start($quote, $returnUrl, $cancelUrl, $params = null)
    {
        $button = null;
        $credit = null;
        if (!empty($params)) {
            if (isset($params['button'])) {
                $button = $params['button'];
            }
            if (isset($params['credit'])) {
                $credit = $params['credit'];
            }
        }

        $response = parent::start($quote, $returnUrl, $cancelUrl, $credit);

        $token = $response->sessionId;
        $this->_redirectUrl = $response->redirectUrl;

        // Set flag that we came from Express Checkout button
        if (!empty($button)) {
            $quote->getPayment()->setAdditionalInformation('button', 1);
        } elseif ($quote->getPayment()->hasAdditionalInformation('button')) {
            $quote->getPayment()->unsAdditionalInformation('button');
        }

        // Set flag that we came from Credit Checkout button
        $quote->getPayment()->setAdditionalInformation('code', $this->_methodCode);
        if (!empty($credit)) {
            $quote->getPayment()->setAdditionalInformation('credit', 1);
            $quote->getPayment()->setAdditionalInformation('code', $this->_methodCode . '_credit');
        } elseif ($quote->getPayment()->hasAdditionalInformation('credit')) {
            $quote->getPayment()->unsAdditionalInformation('credit');
            $quote->getPayment()->setAdditionalInformation('code', $this->_methodCode);
        }

        $quote->getPayment()->save();
        return $token;
    }

    /**
     * Update quote when returned from PayPal
     * rewrite billing address by paypal
     * save old billing address for new customer
     * export shipping address in case address absence
     *
     * @param string $token
     */
    public function returnFromPaypal($quote, $token, $payerId)
    {
        $response = $this->getCheckoutDetails($token);

        $this->ignoreAddressValidation($quote);
        $shippingAddress = null;
        $billingAddress = null;

        // import shipping address
        $exportedShippingAddress = $response->shipping;
        if (!$quote->getIsVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            if ($shippingAddress) {
                if ($exportedShippingAddress
                    && $quote->getPayment()->getAdditionalInformation('button') == 1
                ) {
                    $regionModel = Mage::getModel('directory/region')->loadByCode($exportedShippingAddress->address->state, $exportedShippingAddress->address->country);
                    $shippingAddress->setData('street', $exportedShippingAddress->address->address);
                    $shippingAddress->setCity($exportedShippingAddress->address->city);
                    $shippingAddress->setRegionId($regionModel->getId());
                    $shippingAddress->setPostcode($exportedShippingAddress->address->zip);
                    $shippingAddress->setCountryId($exportedShippingAddress->address->country);
                    $shippingAddress->setPrefix(null);
                    $shippingAddress->setFirstname($response->buyer->firstName);
                    $shippingAddress->setMiddlename(null);
                    $shippingAddress->setLastname($response->buyer->lastName);
                    $shippingAddress->setEmail($response->buyer->emailAddress);
                    $shippingAddress->setSuffix(null);
                    $shippingAddress->setCollectShippingRates(true);
                    $shippingAddress->setSameAsBilling(0);
                }
            }
        }

        // import billing address
        $portBillingFromShipping = $quote->getPayment()->getAdditionalInformation('button') == 1
            && !$quote->isVirtual();
        if ($portBillingFromShipping) {
            $billingAddress = clone $shippingAddress;
            $billingAddress->unsAddressId()
                ->unsAddressType();
            $data = $billingAddress->getData();
            $data['save_in_address_book'] = 0;
            $quote->getBillingAddress()->addData($data);
            $quote->getShippingAddress()->setSameAsBilling(1);
        } else {
            $billingAddress = $quote->getBillingAddress();
        }
        $quote->setBillingAddress($billingAddress);
        $quote->save();

        // import payment info
        $payment = $quote->getPayment();
        $payment->setMethod($payment->getAdditionalInformation('code'));
        $payment->setAdditionalInformation('payer_id', $payerId)
            ->setAdditionalInformation('checkout_token', $token)
        ;
        $quote->collectTotals()->save();
    }

    public function getRedirectUrl()
    {
        return $this->_redirectUrl;
    }

    /**
     * Check whether order review has enough data to initialize
     *
     * @param $token
     * @throws Mage_Core_Exception
     */
    public function prepareOrderReview($quote, $token = null)
    {
        $payment = $quote->getPayment();
        if (!$payment || !$payment->getAdditionalInformation('payer_id')) {
            Mage::throwException(Mage::helper('hps_securesubmit')->__('Payer is not identified.'));
        }
        parent::prepareOrderReview($quote);
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
