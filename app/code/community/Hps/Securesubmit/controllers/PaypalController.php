<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

/**
 * Paypal Checkout Controller
 */
class Hps_Securesubmit_PaypalController extends Mage_Core_Controller_Front_Action
{
    protected $_checkoutType = 'hps_securesubmit/paypal_checkout';
    protected $_quote = null;

    protected function _construct()
    {
        parent::_construct();
    }

    /**
     * Action for Bill Me Later checkout button (product view and shopping cart pages)
     */
    public function creditAction()
    {
        $this->_forward('start', 'paypal', 'securesubmit', array(
            'credit' => 1,
            'button' => $this->getRequest()->getParam('button')
        ));
    }

    public function incontextAction()
    {
        $this->_forward('start', 'paypal', 'securesubmit', array(
            'incontext' => 1,
            'credit'    => $this->getRequest()->getParam('credit'),
            'button'    => $this->getRequest()->getParam('button'),
        ));
    }

    public function incontextCreditAction()
    {
        $this->_forward('incontext', 'paypal', 'securesubmit', array(
            'credit' => 1,
            'button' => $this->getRequest()->getParam('button'),
        ));
    }

    /**
     * Start PayPal Checkout by requesting initial token and dispatching customer to PayPal
     */
    public function startAction()
    {
        try {
            $helper = Mage::helper('hps_securesubmit/paypal');

            if ($this->_getQuote()->getIsMultiShipping()) {
                $this->_getQuote()->setIsMultiShipping(false);
                $this->_getQuote()->removeAllAddresses();
            }

            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $quoteCheckoutMethod = $this->_getQuote()->getCheckoutMethod();
            Mage::log('checkout method: ' . $quoteCheckoutMethod);
            if ($customer && $customer->getId()) {
                $this->_getQuote()->assignCustomerWithAddressChange(
                    $customer,
                    $this->_getQuote()->getBillingAddress(),
                    $this->_getQuote()->getShippingAddress()
                );
                Mage::log('customer: ' . $customer->getId());
            } elseif ((!$quoteCheckoutMethod
                || $quoteCheckoutMethod != Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER)
                && !Mage::helper('checkout')->isAllowedGuestCheckout(
                    $this->_getQuote(),
                    $this->_getQuote()->getStoreId()
                )
            ) {
                Mage::getSingleton('core/session')->addNotice(
                    Mage::helper('hps_securesubmit')->__('To proceed to Checkout, please log in using your email address.')
                );
                $this->redirectLogin();
                Mage::getSingleton('customer/session')
                    ->setBeforeAuthUrl(Mage::getUrl('*/*/*', array('_current' => true)));
                return;
            }

            $button = (bool)$this->getRequest()->getParam('button');
            $credit = (bool)$this->getRequest()->getParam('credit');
            $incontext = (bool)$this->getRequest()->getParam('incontext');
            $token = $helper->start(
                $this->_getQuote(),
                Mage::getUrl('*/*/return'),
                Mage::getUrl('*/*/cancel'),
                array(
                    'button' => $button,
                    'credit' => $credit,
                )
            );

            $this->_initRedirectUrl($url = $helper->getRedirectUrl());
            Mage::log('url: ' . $url);

            if ($token && $incontext && $url) {
                Mage::log('incontext');
                $this->_initToken($token);
                $this->getResponse()->setBody($url);
                return;
            }
            if ($token && $url) {
                Mage::log('standard');
                $this->_initToken($token);
                $this->getResponse()->setRedirect($url);
                return;
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckoutSession()->addError($e->getMessage());
            Mage::log(Mage::helper('hps_securesubmit')->__("Error creating PayPal session: %s", $e->getMessage()), Zend_Log::WARN);
        } catch (Exception $e) {
            $this->_getCheckoutSession()->addError($this->__('Unable to start PayPal Checkout.'));
            Mage::logException($e);
            Mage::log(Mage::helper('hps_securesubmit')->__("Error creating PayPal session: %s", $e->getMessage()), Zend_Log::WARN);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Cancel PayPal Checkout
     */
    public function cancelAction()
    {
        try {
            $this->_initToken(false);
            // TODO verify if this logic of order cancelation is deprecated
            // if there is an order - cancel it
            $orderId = $this->_getCheckoutSession()->getLastOrderId();
            $order = ($orderId) ? Mage::getModel('sales/order')->load($orderId) : false;
            if ($order && $order->getId() && $order->getQuoteId() == $this->_getCheckoutSession()->getQuoteId()) {
                $order->cancel()->save();
                $this->_getCheckoutSession()
                    ->unsLastQuoteId()
                    ->unsLastSuccessQuoteId()
                    ->unsLastOrderId()
                    ->unsLastRealOrderId()
                    ->addSuccess($this->__('PayPal Checkout and Order have been canceled.'))
                ;
            } else {
                $this->_getCheckoutSession()->addSuccess($this->__('PayPal Checkout has been canceled.'));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getCheckoutSession()->addError($this->__('Unable to cancel PayPal Checkout.'));
            Mage::logException($e);
        }

        $this->_redirect('checkout/cart');
    }

    /**
     * Return from PayPal and dispatch customer to order review page
     */
    public function returnAction()
    {
        if ($this->getRequest()->getParam('retry_authorization') == 'true'
            && is_array($this->_getCheckoutSession()->getPaypalTransactionData())
        ) {
            $this->_forward('placeOrder');
            return;
        }
        try {
            $token = $this->getRequest()->getParam('token');
            $payerId = $this->getRequest()->getParam('PayerID');
            $this->_getCheckoutSession()->unsPaypalTransactionData();
            $helper = Mage::helper('hps_securesubmit/paypal');
            $token = $this->_initToken();
            $helper->returnFromPaypal($this->_getQuote(), $token, $payerId);
            $this->_getSession()->setPayPalPayerId($payerId);

            $this->_redirect('*/*/review');
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getCheckoutSession()->addError($this->__('Unable to process PayPal Checkout approval.'));
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Review order after returning from PayPal
     */
    public function reviewAction()
    {
        try {
            $helper = Mage::helper('hps_securesubmit/paypal');
            $token = $this->_initToken();
            $helper->prepareOrderReview($this->_getQuote(), $token);
            $this->loadLayout();
            $this->_initLayoutMessages('paypal/session');
            $reviewBlock = $this->getLayout()->getBlock('hps.securesubmit.paypal.review');
            $reviewBlock->setQuote($this->_getQuote());
            $reviewBlock->getChild('details')->setQuote($this->_getQuote());
            if ($reviewBlock->getChild('shipping_method')) {
                $reviewBlock->getChild('shipping_method')->setQuote($this->_getQuote());
            }
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getCheckoutSession()->addError(
                $this->__('Unable to initialize PayPal Checkout review.')
            );
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Update shipping method (combined action for ajax and regular request)
     */
    public function saveShippingMethodAction()
    {
        try {
            $isAjax = $this->getRequest()->getParam('isAjax');

            if (!$this->_getQuote()->getIsVirtual() && $shippingAddress = $this->_getQuote()->getShippingAddress()) {
                if ($this->getRequest()->getParam('shipping_method') != $shippingAddress->getShippingMethod()) {
                    Mage::helper('hps_securesubmit/paypal')->ignoreAddressValidation($this->_getQuote());
                    $shippingAddress->setShippingMethod($this->getRequest()->getParam('shipping_method'))->setCollectShippingRates(true);
                    $this->_getQuote()->collectTotals()->save();
                }
            }

            if ($isAjax) {
                $this->loadLayout('hps_securesubmit_paypal_review_details');
                $this->getResponse()->setBody($this->getLayout()->getBlock('root')
                    ->setQuote($this->_getQuote())
                    ->toHtml());
                return;
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Unable to update shipping method.'));
            Mage::logException($e);
        }
        if ($isAjax) {
            $this->getResponse()->setBody('<script type="text/javascript">window.location.href = '
                . Mage::getUrl('*/*/review') . ';</script>');
        } else {
            $this->_redirect('*/*/review');
        }
    }

    /**
     * Submit the order
     */
    public function placeOrderAction()
    {
        try {
            $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
            if ($requiredAgreements) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                if (array_diff($requiredAgreements, $postedAgreements)) {
                    Mage::throwException(Mage::helper('paypal')->__('Please agree to all the terms and conditions before placing the order.'));
                }
            }
            $helper = Mage::helper('hps_securesubmit/paypal');
            $order = $helper->place($this->_getQuote(), $this->_initToken());

            // prepare session to success or cancellation page
            $session = $this->_getCheckoutSession();
            $session->clearHelperData();

            // "last successful quote"
            $quoteId = $this->_getQuote()->getId();
            $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

            // an order may be created
            if ($order) {
                $session->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId());
            }

            $this->_initToken(false); // no need in token anymore
            $this->_redirect('checkout/onepage/success');
            return;
        } catch (HpsProcessorException $e) {
            $this->_processPaypalApiError($e);
        } catch (Mage_Core_Exception $e) {
            Mage::helper('checkout')->sendPaymentFailedEmail($this->_getQuote(), $e->getMessage());
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/review');
        } catch (Exception $e) {
            Mage::helper('checkout')->sendPaymentFailedEmail(
                $this->_getQuote(),
                $this->__('Unable to place the order.')
            );
            $this->_getSession()->addError($this->__('Unable to place the order.'));
            Mage::logException($e);
            $this->_redirect('*/*/review');
        }
    }

    /**
     * Process PayPal API's processable errors
     *
     * @param HpsProcessorException $exception
     * @throws HpsProcessorException
     */
    protected function _processPaypalApiError($exception)
    {
        switch ($exception->getCode()) {
            case 10486:
            case 10422:
                $token = $this->_initToken();
                $this->getResponse()->setRedirect(
                    $this->_config->getPayPalCheckoutStartUrl($token)
                );
                break;
            case 10416:
            case 10411:
                $this->getResponse()->setRedirect(
                    $this->_getQuote()->getPayment()->getCheckoutRedirectUrl()
                );
                break;
            default:
                $cart = Mage::getSingleton('checkout/cart');
                $cart->getCheckoutSession()->addError($exception->getUserMessage());
                $this->_redirect('checkout/cart');
                break;
        }

    }

    /**
     * Search for proper checkout token in request or session or (un)set specified one
     * Combined getter/setter
     *
     * @param string $setToken
     * @return Hps_Securesubmit_PaypalController|string
     */
    protected function _initToken($setToken = null)
    {
        if (null !== $setToken) {
            if (false === $setToken) {
                // security measure for avoid unsetting token twice
                if (!$this->_getSession()->getPayPalCheckoutToken()) {
                    Mage::throwException($this->__('PayPal Checkout Token does not exist.'));
                }
                $this->_getSession()->unsPayPalCheckoutToken();
            } else {
                $this->_getSession()->setPayPalCheckoutToken($setToken);
            }
            return $this;
        }
        if ($setToken = $this->getRequest()->getParam('token')) {
            if ($setToken !== $this->_getSession()->getPayPalCheckoutToken()) {
                Mage::throwException($this->__('Wrong PayPal Checkout Token specified.'));
            }
        } else {
            $setToken = $this->_getSession()->getPayPalCheckoutToken();
        }
        return $setToken;
    }

    /**
     * Search for proper checkout token in request or session or (un)set specified one
     * Combined getter/setter
     *
     * @param string $setRedirectUrl
     * @return Hps_Securesubmit_PaypalController|string
     */
    protected function _initRedirectUrl($setRedirectUrl = null)
    {
        if (null !== $setRedirectUrl) {
            if (false === $setRedirectUrl) {
                // security measure for avoid unsetting token twice
                if (!$this->_getSession()->getPayPalCheckoutRedirectUrl()) {
                    Mage::throwException($this->__('PayPal Checkout RedirectUrl does not exist.'));
                }
                $this->_getSession()->unsPayPalCheckoutRedirectUrl();
            } else {
                $this->_getSession()->setPayPalCheckoutRedirectUrl($setRedirectUrl);
            }
            return $this;
        }
        if ($setRedirectUrl = $this->getRequest()->getParam('token')) {
            if ($setRedirectUrl !== $this->_getSession()->getPayPalCheckoutRedirectUrl()) {
                Mage::throwException($this->__('Wrong PayPal Checkout RedirectUrl specified.'));
            }
        } else {
            $setRedirectUrl = $this->_getSession()->getPayPalCheckoutRedirectUrl();
        }
        return $setRedirectUrl;
    }

    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    protected function _getSession()
    {
        return Mage::getSingleton('hps_securesubmit/session');
    }

    protected function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }
}
