<?php
class Hps_Securesubmit_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_USE_HTTP_PROXY  = 'payment/hps_securesubmit/use_http_proxy';
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_HTTP_PROXY_HOST = 'payment/hps_securesubmit/http_proxy_host';
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_HTTP_PROXY_PORT = 'payment/hps_securesubmit/http_proxy_port';

    /**
     * @param $customerId
     * @return Hps_Securesubmit_Model_Storedcard[]|Hps_Securesubmit_Model_Resource_Storedcard_Collection
     */
    public function getStoredCards($customerId)
    {
        $cardCollection = Mage::getResourceModel('hps_securesubmit/storedcard_collection')
            ->addFieldToFilter('customer_id', $customerId);
        return $cardCollection;
    }

    /**
     * @param string $token
     * @param HpsCreditCard $cardData
     * @param string $cardType
     * @return Hps_Securesubmit_Model_Storedcard
     */
    public function saveMultiToken($token,$cardData,$cardType)
    {
        $_session = Mage::getSingleton('customer/session');
        $_loggedIn = $_session->isLoggedIn();

        if($_loggedIn){
            $_customerId = $_session->getCustomer()->getId();

            $storedCard = Mage::getModel('hps_securesubmit/storedcard');
            $storedCard->setDt(Varien_Date::now())
                ->setCustomerId($_customerId)
                ->setTokenValue($token)
                ->setCcType($cardType)
                ->setCcLast4($cardData->number)
                ->setCcExpMonth(str_pad($cardData->expMonth, 2, '0', STR_PAD_LEFT))
                ->setCcExpYear($cardData->expYear);
            try{
                $storedCard->removeDuplicates();
                $storedCard->save();
                return $storedCard;
            }catch (Exception $e){
                if($e->getCode() == '23000'){
                    Mage::throwException($this->__('Customer Not Found  : Card could not be saved.'));
                }
                Mage::throwException($e->getMessage());
            }
        }
    }
}
