<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */
require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';

class Hps_Securesubmit_Helper_Altpayment_Abstract extends Mage_Core_Helper_Abstract
{
    protected $_methodCode = null;

    public function start($quote, $returnUrl = null, $cancelUrl = null, $credit = false)
    {
        $quote->collectTotals();

        if (!$credit && !$quote->getGrandTotal() && !$quote->hasNominalItems()) {
            Mage::throwException(Mage::helper('hps_securesubmit')->__($this->_methodCode . ' does not support processing orders with zero amount. To complete your purchase, proceed to the standard checkout process.'));
        }

        $quote->reserveOrderId()->save();

        return $this->startCheckout(
            $quote,
            $returnUrl,
            $cancelUrl,
            $credit
        );
    }

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
        $cart = Mage::getModel('hps_securesubmit/altpayment_cart', array($quote));
        $totals = $cart->getTotals();

        $buyer = new HpsBuyerData();
        $buyer->returnUrl = $returnUrl;
        $buyer->cancelUrl = $cancelUrl;
        $buyer->credit = $credit;
        if ($quote->getBillingAddress()) {
            $billingAddress = $quote->getBillingAddress();
            $regionModel = Mage::getModel('directory/region')->load($billingAddress->getRegionId());
            $buyer->address = new HpsAddress();
            $buyer->name = $billingAddress->getFirstname() . ' ' . $billingAddress->getMiddlename() . ' ' . $billingAddress->getLastname();
            $buyer->address = new HpsAddress();
            $buyer->address->address = $billingAddress->getData('street');
            $buyer->address->city = $billingAddress->getCity();
            $buyer->address->state = $regionModel->getCode();
            $buyer->address->zip = $billingAddress->getPostcode();
            $buyer->address->country = $billingAddress->getCountryId();
        }

        $payment = new HpsPaymentData();
        $payment->subtotal = sprintf("%0.2f", round($totals[Hps_Securesubmit_Model_Altpayment_Cart::TOTAL_SUBTOTAL], 3));
        $payment->shippingAmount = sprintf("%0.2f", round($totals[Hps_Securesubmit_Model_Altpayment_Cart::TOTAL_SHIPPING], 3));
        $payment->taxAmount = sprintf("%0.2f", round($totals[Hps_Securesubmit_Model_Altpayment_Cart::TOTAL_TAX], 3));
        $payment->paymentType = (Mage::getStoreConfig('payment/' . $this->_methodCode . '/payment_action') == 'authorize_capture'
            ? 'Sale' : 'Authorization');

        $discount = 0;
        if (isset($totals[Hps_Securesubmit_Model_Altpayment_Cart::TOTAL_DISCOUNT])) {
            $discount = sprintf("-%0.2f", round($totals[Hps_Securesubmit_Model_Altpayment_Cart::TOTAL_DISCOUNT], 3));
            $payment->subtotal += $discount;
        }

        // import/suppress shipping address, if any
        $shippingInfo = null;
        if ($address !== null && $address->getRegionId() !== null) {
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

        $lineItems = $this->exportLineItems($cart);

        if ($discount != 0) {
            $discountItem = new HpsLineItem();
            $discountItem->name = 'Discount';
            $discountItem->number = 'discount';
            $discountItem->amount = $discount;
            $lineItems[] = $discountItem;
            unset($discountItem);
        }

        $orderData = new HpsOrderData();
        $orderData->orderNumber = str_shuffle('abcdefghijklmnopqrstuvwxyz');
        $orderData->ipAddress = $_SERVER['REMOTE_ADDR'];
        $orderData->browserHeader = $_SERVER['HTTP_ACCEPT'];
        $orderData->userAgent = $_SERVER['HTTP_USER_AGENT'];
        $orderData->originUrl = $returnUrl;
        $orderData->termUrl = $cancelUrl;
        $orderData->checkoutType = HpsCentinelCheckoutType::LIGHTBOX;

        if ($credit) {
            $orderData->checkoutType = HpsCentinelCheckoutType::PAIRING;
        }

        return $this->getService()->createSession(
            $amount,
            $currency,
            $buyer,
            $payment,
            $shippingInfo,
            $lineItems,
            $orderData
        );
    }

    public function prepareOrderReview($quote)
    {
        $quote->setMayEditShippingAddress(false);
        $quote->setMayEditShippingMethod(
            '' == $quote->getPayment()->getAdditionalInformation('shipping_method')
        );
        $this->ignoreAddressValidation($quote);
        $quote->collectTotals()->save();
    }

    /**
     * Place the order when customer returned from altpayment service
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
            // even after placement altpayment service can disallow to authorize/capture,
            // but will wait until bank transfers money
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

    public function authenticate(
        $orderId,
        $oauthToken,
        $oauthVerifier,
        $payload,
        $resourceUrl,
        $orderData = null
    ) {
        return $this->getService()->authenticate(
            $orderId,
            $oauthToken,
            $oauthVerifier,
            $payload,
            $resourceUrl,
            $orderData
        );
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

    protected function getCheckoutDetails($token)
    {
        return $this->getService()->sessionInfo($token);
    }

    /**
     * Prepare quote for guest checkout order submit
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
        throw new Exception('AltPayment service not configured');
    }
}
