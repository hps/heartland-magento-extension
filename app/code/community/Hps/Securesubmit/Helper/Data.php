<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

class Hps_Securesubmit_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_USE_HTTP_PROXY  = 'payment/hps_securesubmit/use_http_proxy';
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_HTTP_PROXY_HOST = 'payment/hps_securesubmit/http_proxy_host';
    const XML_PATH_PAYMENT_HPS_SECURESUBMIT_HTTP_PROXY_PORT = 'payment/hps_securesubmit/http_proxy_port';

    /**
     * Retrieve list of the stored credit cards for the customer
     *
     * @param int|Mage_Customer_Model_Customer $customerId
     * @param int|Mage_Customer_Model_Address $addressId
     * @return Hps_Securesubmit_Model_Storedcard[]|Hps_Securesubmit_Model_Resource_Storedcard_Collection
     */
    public function getStoredCards($customerId, $addressId = NULL)
    {
        if ($customerId instanceof Mage_Customer_Model_Customer) {
            $customerId = $customerId->getId();
        }
        if ($addressId instanceof Mage_Customer_Model_Address) {
            $addressId = $addressId->getId();
        }
        /** @var $cardCollection Hps_Securesubmit_Model_Resource_Storedcard_Collection */
        $cardCollection = Mage::getResourceModel('hps_securesubmit/storedcard_collection')
            ->addFieldToFilter('customer_id', $customerId);
        if (NULL !== $addressId) {
            $cardCollection->join(
                array('address' => 'hps_securesubmit/storedcard_address'),
                'main_table.storedcard_id = address.storedcard_id',
                array()
            );
            $cardCollection->getSelect()->where('address.customer_address_id = ?', $addressId);
        }
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
     * Save relation between the stored card and the customer's shipping addresses
     *
     * @param int|Hps_Securesubmit_Model_Storedcard $cardId
     * @param int $customerId
     * @return void
     */
    public function saveCardToAddress($cardId, $customerId)
    {
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        if ($cardId instanceof Hps_Securesubmit_Model_Storedcard) {
            $cardId = $cardId->getId();
        }

        $select = $db->select()
            ->from($resource->getTableName('customer/address_entity'), array(
                'storedcard_id' => new Zend_Db_Expr(intval($cardId)),
                'customer_address_id' => 'entity_id'
            ))
            ->where('parent_id = ?', intval($customerId));
        $db->query($select->insertIgnoreFromSelect($resource->getTableName('hps_securesubmit/storedcard_address')));
    }

    /**
     * Remove stored credit cards for the specified address
     *
     * @param int $addressId
     * @return int The number of affected rows.
     */
    public function removeStoredCards($addressId)
    {
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        return (int)$db->delete(
            $resource->getTableName('hps_securesubmit/storedcard_address'),
            $db->quoteInto('customer_address_id = ?', intval($addressId))
        );
    }
}
