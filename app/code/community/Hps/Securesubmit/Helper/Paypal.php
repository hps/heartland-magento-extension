<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */
require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';

class Hps_Securesubmit_Helper_Paypal extends Mage_Core_Helper_Abstract
{

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
        $quote->collectTotals();

        if (!$quote->getGrandTotal() && !$quote->hasNominalItems()) {
            Mage::throwException(Mage::helper('hps_securesubmit')->__('PayPal does not support processing orders with zero amount. To complete your purchase, proceed to the standard checkout process.'));
        }

        $quote->reserveOrderId()->save();

        // call API and redirect with token
        $response = $this->startCheckout(
            $quote,
            $returnUrl,
            $cancelUrl,
            $credit
        );
        $token = $response->sessionId;
        $this->_redirectUrl = $response->redirectUrl;

        // Set flag that we came from Express Checkout button
        if (!empty($button)) {
            $quote->getPayment()->setAdditionalInformation('button', 1);
        } elseif ($quote->getPayment()->hasAdditionalInformation('button')) {
            $quote->getPayment()->unsAdditionalInformation('button');
        }

        // Set flag that we came from Credit Checkout button
        Mage::log('setting code without credit');
        $quote->getPayment()->setAdditionalInformation('code', 'hps_paypal');
        if (!empty($credit)) {
            $quote->getPayment()->setAdditionalInformation('credit', 1);
            Mage::log('setting code with credit');
            $quote->getPayment()->setAdditionalInformation('code', 'hps_paypal_credit');
        } elseif ($quote->getPayment()->hasAdditionalInformation('credit')) {
            $quote->getPayment()->unsAdditionalInformation('credit');
            Mage::log('setting code without credit');
            $quote->getPayment()->setAdditionalInformation('code', 'hps_paypal');
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
        Mage::log('method: ' . $payment->getMethod());
        $payment->setMethod($payment->getAdditionalInformation('code'));
        Mage::log('method: ' . $payment->getMethod());
        $payment->setAdditionalInformation('payer_id', $payerId)
            ->setAdditionalInformation('checkout_token', $token)
        ;
        $quote->collectTotals()->save();
    }

    /**
     * SetExpressCheckout call
     * @link https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_SetExpressCheckout
     * TODO: put together style and giropay settings
     */
    public function startCheckout(
        $quote,
        $returnUrl,
        $cancelUrl,
        $credit = null
    ) {
        $amount = $quote->getBaseGrandTotal();
        $currency = $quote->getBaseCurrencyCode();
        $address = null;
        // supress or export shipping address
        if ($quote->getIsVirtual()) {
            $this->setRequireBillingAddress(1);
            $this->setSuppressShipping(true);
        } else {
            $address = $quote->getShippingAddress();
            $isOverriden = 0;
            if (true === $address->validate()) {
                $isOverriden = 1;
            }
            $quote->getPayment()->setAdditionalInformation(
                'shipping_overriden',
                $isOverriden
            );
            $quote->getPayment()->save();
        }

        // add line items
        $paypalCart = Mage::getModel('hps_securesubmit/paypal_cart', array($quote));
        $totals = $paypalCart->getTotals();

        $buyer = new HpsBuyerData();
        $buyer->returnUrl = $returnUrl;
        $buyer->cancelUrl = $cancelUrl;
        $buyer->credit = $credit;

        $payment = new HpsPaymentData();
        $payment->subtotal = sprintf("%0.2f", round($totals[Mage_Paypal_Model_Cart::TOTAL_SUBTOTAL], 3));
        $payment->shippingAmount = sprintf("%0.2f", round($totals[Mage_Paypal_Model_Cart::TOTAL_SHIPPING], 3));
        $payment->taxAmount = sprintf("%0.2f", round($totals[Mage_Paypal_Model_Cart::TOTAL_TAX], 3));
        $payment->paymentType = (Mage::getStoreConfig('payment/hps_paypal/payment_action') == 'authorize_capture'
            ? 'Sale' : 'Authorization');

        $discount = 0;
        if (isset($totals[Mage_Paypal_Model_Cart::TOTAL_DISCOUNT])) {
            $discount = sprintf("-%0.2f", round($totals[Mage_Paypal_Model_Cart::TOTAL_DISCOUNT], 3));
            $payment->subtotal += $discount;
        }

        // import/suppress shipping address, if any
        $shippingInfo = null;
        if ($address !== null && $address->getRegionId() !== null) {
            Mage::log(sprintf('address region id: %i', $address->getRegionId()));
            Mage::log(sprintf('address city: %s', $address->getCity()));
            $regionModel = Mage::getModel('directory/region')->load($address->getRegionId());
            $shippingInfo = new HpsShippingInfo();
            $shippingInfo->name = $address->getFirstname() . ' ' . $address->getMiddlename() . ' ' . $address->getLastname();
            $shippingInfo->address = new HpsAddress();
            $shippingInfo->address->address = $address->getData('street');
            $shippingInfo->address->city = $address->getCity();
            $shippingInfo->address->state = $regionModel->getCode();
            $shippingInfo->address->zip = $address->getPostcode();
            $shippingInfo->address->country = $address->getCountryId();

            if ($address->getEmail()) {
                $buyer->emailAddress = $address->getEmail();
            }
        }

        $lineItems = $this->exportLineItems($paypalCart);

        if ($discount != 0) {
            $discountItem = new HpsLineItem();
            $discountItem->name = 'Discount';
            $discountItem->number = 'discount';
            $discountItem->amount = $discount;
            $lineItems[] = $discountItem;
            unset($discountItem);
        }

        return $this->getService()->createSession(
            $amount,
            $currency,
            $buyer,
            $payment,
            $shippingInfo,
            $lineItems
        );
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
            Mage::throwException(Mage::helper('paypal')->__('Payer is not identified.'));
        }
        $quote->setMayEditShippingAddress(false);
        $quote->setMayEditShippingMethod(
            '' == $quote->getPayment()->getAdditionalInformation('shipping_method')
        );
        $this->ignoreAddressValidation($quote);
        $quote->collectTotals()->save();
    }

    /**
     * Place the order and recurring payment profiles when customer returned from paypal
     * Until this moment all quote data must be valid
     *
     * @param string $token
     * @param string $shippingMethodCode
     */
    public function place($quote, $token, $shippingMethodCode = null)
    {
        if ($shippingMethodCode) {
            $this->updateShippingMethod($shippingMethodCode);
        }

        $isNewCustomer = false;
        switch ($this->getCheckoutMethod($quote)) {
            case Mage_Checkout_Model_Type_Onepage::METHOD_GUEST:
                $quote = $this->_prepareGuestQuote($quote);
                break;
            case Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER:
                $quote = $this->_prepareNewCustomerQuote($quote);
                $isNewCustomer = true;
                break;
            default:
                $quote = $this->_prepareCustomerQuote($quote);
                break;
        }

        $this->ignoreAddressValidation($quote);
        $quote->collectTotals();
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        $quote->save();

        if ($isNewCustomer) {
            try {
                $this->_involveNewCustomer();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        /** @var $order Mage_Sales_Model_Order */
        $order = $service->getOrder();
        if (!$order) {
            return false;
        }

        switch ($order->getState()) {
            // even after placement paypal can disallow to authorize/capture, but will wait until bank transfers money
            case Mage_Sales_Model_Order::STATE_PENDING_PAYMENT:
                // TODO
                break;
            // regular placement, when everything is ok
            case Mage_Sales_Model_Order::STATE_PROCESSING:
            case Mage_Sales_Model_Order::STATE_COMPLETE:
            case Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW:
                $order->sendNewOrderEmail();
                break;
        }
        return $order;
    }

    /**
     * Make sure addresses will be saved without validation errors
     */
    public function ignoreAddressValidation($quote)
    {
        $quote->getBillingAddress()->setShouldIgnoreValidation(true);
        if (!$quote->getIsVirtual()) {
            $quote->getShippingAddress()->setShouldIgnoreValidation(true);
        }
    }

    /**
     * Get checkout method
     *
     * @return string
     */
    protected function getCheckoutMethod($quote)
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            return Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER;
        }
        if (!$quote->getCheckoutMethod()) {
            if (Mage::helper('checkout')->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
            }
        }
        return $quote->getCheckoutMethod();
    }

    /**
     * GetExpressCheckoutDetails call
     * @link https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_GetExpressCheckoutDetails
     */
    protected function getCheckoutDetails($token)
    {
        return $this->getService()->sessionInfo($token);
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @return Hps_Securesubmit_Model_Paypal_Checkout
     */
    protected function _prepareGuestQuote($quote)
    {
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        return $quote;
    }

    /**
     * Checks if customer with email coming from Express checkout exists
     *
     * @return int
     */
    protected function _lookupCustomerId($quote)
    {
        return Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getWebsite()->getId())
            ->loadByEmail($quote->getCustomerEmail())
            ->getId();
    }

    /**
     * Prepare quote for customer registration and customer order submit
     * and restore magento customer data from quote
     *
     * @return Hps_Securesubmit_Model_Paypal_Checkout
     */
    protected function _prepareNewCustomerQuote($quote)
    {
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customerId = $this->_lookupCustomerId($quote);
        if ($customerId) {
            Mage::getSingleton('customer/session')->loginById($customerId);
            return $this->_prepareCustomerQuote($quote);
        }

        $customer = $quote->getCustomer();
        /** @var $customer Mage_Customer_Model_Customer */
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);
        } elseif ($shipping) {
            $customerBilling->setIsDefaultShipping(true);
        }

        if ($quote->getCustomerDob() && !$billing->getCustomerDob()) {
            $billing->setCustomerDob($quote->getCustomerDob());
        }

        if ($quote->getCustomerTaxvat() && !$billing->getCustomerTaxvat()) {
            $billing->setCustomerTaxvat($quote->getCustomerTaxvat());
        }

        if ($quote->getCustomerGender() && !$billing->getCustomerGender()) {
            $billing->setCustomerGender($quote->getCustomerGender());
        }

        Mage::helper('core')->copyFieldset('checkout_onepage_billing', 'to_customer', $billing, $customer);
        $customer->setEmail($quote->getCustomerEmail());
        $customer->setPrefix($quote->getCustomerPrefix());
        $customer->setFirstname($quote->getCustomerFirstname());
        $customer->setMiddlename($quote->getCustomerMiddlename());
        $customer->setLastname($quote->getCustomerLastname());
        $customer->setSuffix($quote->getCustomerSuffix());
        $customer->setPassword($customer->decryptPassword($quote->getPasswordHash()));
        $customer->setPasswordHash($customer->hashPassword($customer->getPassword()));
        $customer->save();
        $quote->setCustomer($customer);

        return $quote;
    }

    /**
     * Prepare quote for customer order submit
     *
     * @return Hps_Securesubmit_Model_Paypal_Checkout
     */
    protected function _prepareCustomerQuote($quote)
    {
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customer = Mage::getSingleton('customer/session')->getCustomer();
        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }
        if ($shipping && ((!$shipping->getCustomerId() && !$shipping->getSameAsBilling())
            || (!$shipping->getSameAsBilling() && $shipping->getSaveInAddressBook()))) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling(true);
        }
        if ($shipping && isset($customerBilling) && !$customer->getDefaultShipping() && $shipping->getSameAsBilling()) {
            $customerBilling->setIsDefaultShipping(true);
        } elseif ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping(true);
        }
        $quote->setCustomer($customer);

        return $quote;
    }

    protected function exportLineItems($cart)
    {
        if (!$cart) {
            return;
        }

        // add cart line items
        $items = $cart->getItems();
        if (empty($items)) {
            return;
        }

        $result = array();
        foreach ($items as $item) {
            $lineItem = new HpsLineItem();
            $lineItem->number = $item->getDataUsingMethod('id');
            $lineItem->quantity = $item->getDataUsingMethod('qty');
            $lineItem->name = $item->getDataUsingMethod('name');
            $lineItem->amount = $item->getDataUsingMethod('amount');
            $result[] = $lineItem;
        }
        return $result;
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
