<?php
require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_Securesubmit_StoredcardController extends Mage_core_Controller_Front_Action
{

    public function preDispatch()
    {
        parent::preDispatch();

        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
    }

    /*
     * Customer Account > Manage Cards
     *
     * Shows customer list of their stored cards
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /*
     * Customer can delete their stored cards
     */
    public function deleteAction()
    {
        try{
            $storedCard = Mage::getModel('hps_securesubmit/storedcard');
            $storedCard->load($this->getRequest()->getParam('storedcard_id'));
            if ( ! $storedCard->getId()) {
                throw new Mage_Core_Exception($this->__('Stored card no longer exists.'));
            }
            $storedCard->delete();
            Mage::getSingleton('customer/session')->addSuccess($this->__('Stored card has been deleted.'));
        }
        catch (Mage_Core_Exception $e) {
            Mage::getSingleton('customer/session')->addError($e->getMessage());
        }
        catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('customer/session')->addError($this->__('An unexpected error occurred deleting your stored card. We apologize for the inconvenience, please contact us for further support.'));
        }
        $this->_redirect('*/*');
    }

    /*
     * Get token data during checkout
     */
    public function getTokenDataAction()
    {
        try {
            $storedCard = Mage::getModel('hps_securesubmit/storedcard');
            $storedCard->load($this->getRequest()->getParam('storedcard_id'));
            if (!$storedCard->getId() || $storedCard->getCustomerId() != Mage::getSingleton('customer/session')->getCustomerId()) {
                throw new Mage_Core_Exception($this->__('Stored card no longer exists.'));
            }
            $result = array(
                'error' => FALSE,
                'token' => array(
                    'token_value'  => $storedCard->getTokenValue(),
                    'cc_last4'     => $storedCard->getCcLast4(),
                    'cc_exp_month' => $storedCard->getCcExpMonth(),
                    'cc_exp_year'  => $storedCard->getCcExpYear(),
                    'cc_type'      => $storedCard->getCcType(),
                )
            );
        }
        catch (Mage_Core_Exception $e) {
            $result = array('error' => TRUE, 'message' => $e->getMessage());
        }
        catch (Exception $e) {
            Mage::logException($e);
            $result = array('error' => TRUE, 'message' => $this->__('An unexpected error occurred retrieving your stored card. We apologize for the inconvenience, please contact us for further support.'));
        }
        $this->getResponse()->setHeader('Content-Type', 'application/json', TRUE);
        $this->getResponse()->setBody(json_encode($result));
    }

    public function getBalanceAction()
    {
        try {
            $giftCardNumber = $this->getRequest()->getParam('giftcard_number');

            if (!$giftCardNumber) {
                throw new Mage_Core_Exception($this->__('No number received.'));
            }

            $config = new HpsServicesConfig();

            $config->secretApiKey = Mage::getModel('hps_securesubmit/payment')->getConfigData('secretapikey');
            $config->versionNumber = '1573';
            $config->developerId = '002914';

            $giftService = new HpsGiftCardService($config);

            try {
                $card = new HpsGiftCard();
                $card->number = $_GET["giftcard_number"];

                $response = $giftService->balance($card);

                $result = array(
                    'error' => FALSE,
                    'balance' => $response->balanceAmount
                );
            } catch (HpsException $e) {
                $result = array('error' => TRUE, 'message' => $e->getMessage());
            }
        }
        catch (Mage_Core_Exception $e) {
            $result = array('error' => TRUE, 'message' => $e->getMessage());
        }
        catch (Exception $e) {
            Mage::logException($e);
            $result = array('error' => TRUE, 'message' => $this->__('An unexpected error occurred retrieving your stored card. We apologize for the inconvenience, please contact us for further support.'));
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json', TRUE);
        $this->getResponse()->setBody(json_encode($result));
    }

}
