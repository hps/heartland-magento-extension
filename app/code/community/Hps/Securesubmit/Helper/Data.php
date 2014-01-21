<?php
class Hps_Securesubmit_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_USE_HTTP_PROXY  = 'payment/hps_securesubmit/use_http_proxy';
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_HTTP_PROXY_HOST = 'payment/hps_securesubmit/http_proxy_host';
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_HTTP_PROXY_PORT = 'payment/hps_securesubmit/http_proxy_port';

    public function getStoredCards($customerId){
        $cardCollection = Mage::getModel('hps_securesubmit/storedcard')
            ->getCollection()
            ->addFieldToFilter('customer_id',$customerId);
        return $cardCollection->getData();
    }

    public function saveMultiToken($token,$cardData,$cardType){
        $_session = Mage::getSingleton('customer/session');
        $_loggedIn = $_session->isLoggedIn();

        if($_loggedIn){
            $_customerId = $_session->getCustomer()->getId();

            $currentTimestamp = Mage::getModel('core/date')->timestamp(time());
            $currentDate = date('Y-m-d H:i:s', $currentTimestamp);

            $storedCard = Mage::getModel('hps_securesubmit/storedcard');
            $storedCard->setDt($currentDate)
                ->setCustomerId($_customerId)
                ->setTokenValue($token)
                ->setCcType($cardType)
                ->setCcLast4($cardData->CardNbr)
                ->setCcExpMonth(str_pad($cardData->ExpMonth, 2, '0', STR_PAD_LEFT))
                ->setCcExpYear($cardData->ExpYear);
            try{
                $storedCard->save();
            }catch (Exception $e){
                if($e->getCode() == '23000'){
                    Mage::throwException($this->__('Customer Not Found  : Card could not be saved.'));
                }
                Mage::throwException($e->getMessage());
            }
        }
        return $storedCard;
    }
}
