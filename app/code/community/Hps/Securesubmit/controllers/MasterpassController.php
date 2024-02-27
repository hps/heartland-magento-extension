<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */
require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';

class Hps_Securesubmit_MasterpassController extends Mage_Core_Controller_Front_Action
{
    private $_quote = null;

    protected function _construct()
    {
        parent::_construct();
    }

    public function startAction()
    {
        $result = array();

        try {
            $helper = Mage::helper('hps_securesubmit/masterpass');

            if ($this->getQuote()->getIsMultiShipping()) {
                $this->getQuote()->setIsMultiShipping(false);
                $this->getQuote()->removeAllAddresses();
            }

            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $quoteCheckoutMethod = $this->getQuote()->getCheckoutMethod();
            if ($customer && $customer->getId()) {
                $this->getQuote()->assignCustomerWithAddressChange(
                    $customer,
                    $this->getQuote()->getBillingAddress(),
                    $this->getQuote()->getShippingAddress()
                );
            } elseif ((!$quoteCheckoutMethod
                || $quoteCheckoutMethod != Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER)
                && !Mage::helper('checkout')->isAllowedGuestCheckout(
                    $this->getQuote(),
                    $this->getQuote()->getStoreId()
                )
            ) {
                Mage::getSingleton('core/session')->addNotice(
                    Mage::helper('hps_securesubmit')->__('To proceed to Checkout, please log in using your email address.')
                );
                Mage::getSingleton('customer/session')
                    ->setBeforeAuthUrl(Mage::getUrl('*/*/*', array('_current' => true)));
                $result = array(
                    'result'   => 'error',
                    'redirect' => Mage::getUrl('customer/account/login'),
                );
                $this->getResponse()->setBody(json_encode($result));
                return;
            }

            $pair = $this->getRequest()->getParam('pair');
            $returnUrl = Mage::getUrl('*/*/return');
            if ($pair) {
                $returnUrl = Mage::getUrl('*/*/connect');
            }
            $cancelUrl = Mage::getUrl('*/*/cancel');
            $response = $helper->start(
                $this->getQuote(),
                $returnUrl,
                $cancelUrl,
                $pair
            );

            $this->getSession()->setMasterPassPayload($response->payload);
            $this->getSession()->setMasterPassOrderId($response->orderId);

            $result = array(
                'result' => 'success',
                'data' => array(
                    'processorTransactionId' => $response->processorTransactionId,
                    'returnUrl'              => $returnUrl,
                    'merchantCheckoutId'     => Mage::getStoreConfig('payment/hps_masterpass/merchant_checkout_id'),
                ),
            );

            if (null !== $response->processorTransactionIdPairing) {
                $result['data']['processorTransactionIdPairing'] = $response->processorTransactionIdPairing;
            }

            $walletName = $this->getSession()->getMasterPassWalletName();
            $walletId = $this->getSession()->getMasterPassWalletId();
            $preCheckoutTransactionId = $this->getSession()->getMasterPassPreCheckoutTransactionId();
            if (false !== $preCheckoutTransactionId) {
                $result['data']['preCheckoutTransactionId'] = $preCheckoutTransactionId;
            }
            if (false !== $walletName) {
                $result['data']['walletName'] = $walletName;
            }
            if (false !== $walletId) {
                $result['data']['walletId'] = $walletId;
            }
        } catch (Mage_Core_Exception $e) {
            $this->getCheckoutSession()->addError($e->getMessage());
            Mage::log(Mage::helper('hps_securesubmit')->__("Error creating MasterPass session: %s", $e->getMessage()), Zend_Log::WARN);
            $result = array(
                'result'   => 'error',
                'message'  => $e->getMessage(),
                'redirect' => Mage::getUrl('checkout/cart'),
            );
        } catch (Exception $e) {
            $this->getCheckoutSession()->addError($this->__('Unable to start MasterPass lightbox.'));
            Mage::logException($e);
            Mage::log(Mage::helper('hps_securesubmit')->__("Error creating MasterPass session: %s", $e->getMessage()), Zend_Log::WARN);
            $result = array(
                'result'   => 'error',
                'message'  => $e->getMessage(),
                'redirect' => Mage::getUrl('checkout/cart'),
            );
        }
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json; charset=utf-8')
            ->setBody(json_encode($result));
        return;
    }

    public function returnAction()
    {
        $status = $this->getRequest()->getParam('mpstatus');
        if ($status != 'success') {
            $this->_redirect('checkout/cart');
            return;
        }
        try {
            $checkoutResourceUrl = $this->getRequest()->getParam('checkout_resource_url');
            $oauthToken = $this->getRequest()->getParam('oauth_token');
            $oauthVerifier = $this->getRequest()->getParam('oauth_verifier');
            $pairingToken = $this->getRequest()->getParam('pairing_token');
            $pairingVerifier = $this->getRequest()->getParam('pairing_verifier');

            $helper = Mage::helper('hps_securesubmit/masterpass');

            $payload = $this->getSession()->getMasterPassPayload();
            $orderId = $this->getSession()->getMasterPassOrderId();

            $data = $helper->returnFromMasterPass(
                $status,
                $orderId,
                $oauthToken,
                $oauthVerifier,
                $payload,
                $checkoutResourceUrl,
                $pairingToken,
                $pairingVerifier
            );

            $this->getSession()->setMasterPassAuthenticate($data);
            $this->_redirect('*/*/review');
            return;
        } catch (Mage_Core_Exception $e) {
            $this->getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->getCheckoutSession()->addError($this->__('Unable to process PayPal Checkout approval.'));
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    public function reviewAction()
    {
        try {
            $helper = Mage::helper('hps_securesubmit/masterpass');
            $helper->prepareOrderReview($this->getQuote());
            $this->loadLayout();
            $reviewBlock = $this->getLayout()->getBlock('hps.securesubmit.masterpass.review');
            $reviewBlock->setQuote($this->getQuote());
            $reviewBlock->getChild('details')->setQuote($this->getQuote());
            if ($reviewBlock->getChild('shipping_method')) {
                $reviewBlock->getChild('shipping_method')->setQuote($this->getQuote());
            }
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->getCheckoutSession()->addError(
                $this->__('Unable to initialize MasterPass order review.')
            );
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    public function saveShippingMethodAction()
    {
        try {
            $isAjax = $this->getRequest()->getParam('isAjax');

            if (!$this->getQuote()->getIsVirtual() && $shippingAddress = $this->getQuote()->getShippingAddress()) {
                if ($this->getRequest()->getParam('shipping_method') != $shippingAddress->getShippingMethod()) {
                    Mage::helper('hps_securesubmit/masterpass')->ignoreAddressValidation($this->getQuote());
                    $shippingAddress->setShippingMethod($this->getRequest()->getParam('shipping_method'))->setCollectShippingRates(true);
                    $this->getQuote()->collectTotals()->save();
                }
            }

            if ($isAjax) {
                $this->loadLayout('hps_securesubmit_masterpass_review_details');
                $this->getResponse()->setBody($this->getLayout()->getBlock('root')
                    ->setQuote($this->getQuote())
                    ->toHtml());
                return;
            }
        } catch (Mage_Core_Exception $e) {
            $this->getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->getCheckoutSession()->addError($this->__('Unable to update shipping method.'));
            Mage::logException($e);
        }
        if ($isAjax) {
            $this->getResponse()->setBody('<script type="text/javascript">window.location.href = '
                . Mage::getUrl('*/*/review') . ';</script>');
        } else {
            $this->_redirect('*/*/review');
        }
    }

    public function placeOrderAction()
    {
        try {
            $helper = Mage::helper('hps_securesubmit/masterpass');
            $order = $helper->place(
                $this->getQuote(),
                $this->getSession()->getMasterPassAuthenticate()
            );

            // prepare session to success or cancellation page
            $session = $this->getCheckoutSession();
            $session->clearHelperData();

            // "last successful quote"
            $quoteId = $this->getQuote()->getId();
            $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

            // an order may be created
            if ($order) {
                $session->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId());
            }

            $this->_redirect('checkout/onepage/success');
            return;
        } catch (Mage_Core_Exception $e) {
            Mage::log($e->getMessage());
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getQuote(), $e->getMessage());
            $this->getCheckoutSession()->addError($e->getMessage());
            $this->_redirect('*/*/review');
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            Mage::helper('checkout')->sendPaymentFailedEmail(
                $this->getQuote(),
                $this->__('Unable to place the order.')
            );
            $this->getCheckoutSession()->addError($this->__('Unable to place the order.'));
            Mage::logException($e);
            $this->_redirect('*/*/review');
        }
    }

    public function connectAction()
    {
        $forget = $this->getRequest()->getParam('forget_masterpass');
        if ($forget && $forget == 'true') {
            $customer = Mage::getModel('customer/customer')
                ->load(Mage::getSingleton('customer/session')->getCustomerId());
            $customer->unsMasterpassLongAccessToken();
            $customer->setMasterpassLongAccessToken('');
            $customer->save();
            $this->_redirect('*/*/*');
            return;
        }

        $status = $this->getRequest()->getParam('mpstatus');
        if ($status && $status == 'success') {
            $pairingToken = $this->getRequest()->getParam('pairing_token');
            $pairingVerifier = $this->getRequest()->getParam('pairing_verifier');

            $payload = $this->getSession()->getMasterPassPayload();
            $orderId = $this->getSession()->getMasterPassOrderId();

            try {
                $orderData = new HpsOrderData();
                $orderData->transactionStatus = $status;
                $orderData->checkoutType = HpsCentinelCheckoutType::PAIRING;
                $orderData->pairingToken = $pairingToken;
                $orderData->pairingVerifier = $pairingVerifier;

                // Authenticate the request with the information we've gathered
                $response = Mage::helper('hps_securesubmit/masterpass')->authenticate(
                    $orderId,
                    null,
                    null,
                    $payload,
                    null,
                    $orderData
                );

                $customer = Mage::getModel('customer/customer')
                    ->load(Mage::getSingleton('customer/session')->getCustomerId());
                $customer->unsMasterpassLongAccessToken();
                $customer->setMasterpassLongAccessToken($response->longAccessToken);
                $customer->save();
            } catch (Exception $e) { Mage::logException($e->getMessage()); }

            $this->_redirect('*/*/*');
            return;
        }

        $this->loadLayout();
        $this->getLayout()
            ->getBlock('head')
            ->setTitle(Mage::helper('hps_securesubmit')->__('MasterPass'));
        $this->renderLayout();
        return;
    }

    protected function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    protected function getSession()
    {
        return Mage::getSingleton('hps_securesubmit/session');
    }

    protected function getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }
}
