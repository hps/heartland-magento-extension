<?php
require_once Mage::getBaseDir('lib') . DS . 'SecureSubmit' . DS . 'Hps.php';

class Hps_Securesubmit_GiftcardController extends Mage_Core_Controller_Front_Action
{
    public function getBalanceAction() {
        try {
            $giftCardNumber = $this->getRequest()->getParam('giftcard_number');
            $giftCardPin = $this->getRequest()->getParam('giftcard_pin');

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
                $card->number = $giftCardNumber;
                $card->pin = $giftCardPin;

                $response = $giftService->balance($card);

                $cart = Mage::getModel('checkout/session')->getQuote();
                $total = $cart->getGrandTotal();

                $result = array(
                    'error' => FALSE,
                    'balance' => $response->balanceAmount,
                    'less_than_total' => $response->balanceAmount < $total,
                );
            } catch (HpsException $e) {
                $result = array('error' => TRUE, 'message' => $e->getMessage());
            }
        } catch (Mage_Core_Exception $e) {
            $result = array('error' => TRUE, 'message' => $e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
            $result = array('error' => TRUE, 'message' => $this->__('An unexpected error occurred retrieving your stored card. We apologize for the inconvenience, please contact us for further support.'));
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json', TRUE);
        $this->getResponse()->setBody(json_encode($result));
    }
}
