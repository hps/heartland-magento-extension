<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_Securesubmit_Block_Masterpass_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $mark = Mage::getConfig()->getBlockClassName('core/template');
        $mark = new $mark;
        $mark->setTemplate('securesubmit/masterpass/mark.phtml')
            ->setMarkHref('http://www.mastercard.com/mc_us/wallet/learnmore/en')
            ->setMarkSrc('https://www.mastercard.com/mc_us/wallet/img/en/US/mp_mc_acc_030px_gif.gif')
        ; // known issue: code above will render only static mark image
        $this->setTemplate('securesubmit/masterpass/form.phtml')
            ->setMethodTitle('')
            ->setMethodLabelAfterHtml($mark->toHtml())
        ;
    }

    public function getCards()
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        if (!$customerId) {
            return false;
        }

        $cards = $this->getSession()->getMasterPassCards();
        if ($cards) {
            return $cards;
        }

        $customer = Mage::getModel('customer/customer')->load($customerId);
        $result = Mage::helper('hps_securesubmit/masterpass')
            ->preApproval($customer->getMasterpassLongAccessToken());

        if (!$result) {
            return false;
        }

        $cards = $result->preCheckoutData->Cards->Card;
        $cards = $this->responseCardsToObject($cards);
        $customer->unsMasterpassLongAccessToken()
            ->setMasterpassLongAccessToken((string)$result->longAccessToken)
            ->save();

        $this->getSession()->setMasterPassCards($cards);
        $this->getSession()->setMasterPassWalletName((string)$result->preCheckoutData->WalletName);
        $this->getSession()->setMasterPassWalletId((string)$result->preCheckoutData->ConsumerWalletId);
        $this->getSession()->setMasterPassPreCheckoutTransactionId((string)$result->preCheckoutTransactionId);
        return $cards;
    }

    protected function responseCardsToObject($resp)
    {
        $cards = array();
        foreach ($resp as $card) {
            $cards[] = (object)array(
                'CardHolderName'    => (string)$card->CardHolderName,
                'CardId'            => (string)$card->CardId,
                'LastFour'          => (string)$card->LastFour,
                'CardAlias'         => (string)$card->CardAlias,
                'SelectedAsDefault' => (string)$card->SelectedAsDefault,
                'BrandName'         => (string)$card->BrandName,
                'ExpiryMonth'       => (string)$card->ExpiryMonth,
                'ExpiryYear'        => (string)$card->ExpiryYear,
            );
        }
        return $cards;
    }

    protected function getSession()
    {
        return Mage::getSingleton('hps_securesubmit/session');
    }
