<?php

class Hps_Securesubmit_Adminhtml_StoredcardController extends Mage_Adminhtml_Controller_Action{

    /*
       * Get token data during checkout
       */
    public function getTokenDataAction()
    {
        try {
            $storedCard = Mage::getModel('hps_securesubmit/storedcard');
            $storedCard->load($this->getRequest()->getParam('storedcard_id'));
            $customerId = $this->getRequest()->getParam('customer_id');
            if ( ! $storedCard->getId() || $storedCard->getCustomerId() != $customerId) {
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
}