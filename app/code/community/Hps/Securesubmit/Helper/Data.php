<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_Securesubmit_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_SECRET_API_KEY  = 'payment/hps_securesubmit/secretapikey';
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_PUBLIC_API_KEY  = 'payment/hps_securesubmit/publicapikey';
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
     * @param string        $token
     * @param HpsCreditCard $cardData
     * @param string        $cardType
     * @param integer|null  $customerId
     * @return Hps_Securesubmit_Model_Storedcard
     */
    public function saveMultiToken($token,$cardData,$cardType, $customerId = null)
    {
        $_session = Mage::getSingleton('customer/session');
        $_loggedIn = $_session->isLoggedIn();

        if($_loggedIn || $customerId != null){
            if($customerId == null){
                $_customerId = $_session->getCustomer()->getId();
            }else{
                $_customerId = $customerId;
            }
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

    /**
     * Check whether the selected store has API credentials.
     * Fallback to the default store is not used.
     *
     * @param mixed $storeId
     * @return bool
     */
    public function hasApiCredentials($storeId = NULL)
    {
        if ($storeId instanceof Mage_Core_Model_Store) {
            $storeId = (int) $storeId->getId();
        } elseif ($storeId === NULL) {
            $storeId = Mage_Core_Model_App::ADMIN_STORE_ID;
        } else {
            $storeId = (int) $storeId;
        }

        $defaultPublicApiKey = (string) Mage::getStoreConfig(self::XML_PATH_PAYMENT_HPS_SECURESUBMIT_PUBLIC_API_KEY, Mage_Core_Model_App::ADMIN_STORE_ID);
        $defaultSecretApiKey = (string) Mage::getStoreConfig(self::XML_PATH_PAYMENT_HPS_SECURESUBMIT_SECRET_API_KEY, Mage_Core_Model_App::ADMIN_STORE_ID);
        if ($storeId === Mage_Core_Model_App::ADMIN_STORE_ID) {
            return ( ! empty($defaultPublicApiKey) && ! empty($defaultSecretApiKey));
        }

        $storePublicApiKey = (string) Mage::getStoreConfig(self::XML_PATH_PAYMENT_HPS_SECURESUBMIT_PUBLIC_API_KEY, $storeId);
        $storeSecretApiKey = (string) Mage::getStoreConfig(self::XML_PATH_PAYMENT_HPS_SECURESUBMIT_SECRET_API_KEY, $storeId);
        $hasPublicKey = ( ! empty($storePublicApiKey) && $storePublicApiKey !== $defaultPublicApiKey);
        $hasSecretKey = ( ! empty($storeSecretApiKey) && $storeSecretApiKey !== $defaultSecretApiKey);

        return ($hasPublicKey && $hasSecretKey);
    }
}
