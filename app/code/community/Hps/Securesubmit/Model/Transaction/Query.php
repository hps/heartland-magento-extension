<?php

class Hps_Securesubmit_Model_Transaction_Query extends Mage_Core_Model_Abstract
{
    const PROFILER_PREFIX = 'HPS SecureSubmit Transactions : ';
    const CACHE_PREFIX = 'HPS_SECURESUBMIT_TRANSACTIONS';
    const CACHE_TYPE = 'securesubmit_transactions';
    const CACHE_TAG = 'securesubmit_transactions';

    /**
     * Refresh cache flag
     *
     * @var bool
     */
    protected $_refreshCache = FALSE;

    /**
     * List of filters
     *
     * @var array
     */
    protected $_filters = array();

    /**
     * Amount of found transactions
     *
     * @var int
     */
    protected $_amount = 0;

    /**
     * List of error messages
     *
     * @var array
     */
    protected $_errorMessages = array();

    /**
     * Find transactions
     *
     * @param int $storeId
     * @return array
     */
    public function findTransactions($storeId = NULL)
    {
        try {
            $transactions = $this->_findTransactions($storeId);
            $this->_amount = count($transactions);
        } catch (Exception $e) {
            $this->addErrorMessage($e->getMessage());
            $transactions = array();
            $this->_amount = 0;
        }

        return $transactions;
    }

    /**
     * Check whether need to refresh transactions grid cache
     *
     * @param bool $refresh
     * @return bool
     */
    public function refreshCache($refresh = NULL)
    {
        $result = $this->_refreshCache;
        if ( ! is_null($refresh)) {
            $this->_refreshCache = $refresh;
        }
        return $result;
    }

    /**
     * Add filter
     *
     * @param string $field
     * @param string $condition
     * @return Hps_Securesubmit_Model_Transaction_Query
     */
    public function addFilter($field, $condition)
    {
        $this->_filters[$field] = $condition;
    }

    /**
     * Add error message
     *
     * @param string $message
     * @return Hps_Securesubmit_Model_Transaction_Query
     */
    public function addErrorMessage($message)
    {
        $this->_errorMessages[] = $message;
        return $this;
    }

    /**
     * Retrieve error messages
     *
     * @return array
     */
    public function getErrorMessages()
    {
        return $this->_errorMessages;
    }

    /**
     * Amount of found emails according to filters
     *
     * @return int
     */
    public function getAmount()
    {
        if ($this->_amount === NULL) {
            $this->_amount = count($this->findTransactions());
        }
        return (int) $this->_amount;
    }

    /**
     * Find transactions
     *
     * @param int $storeId
     * @return array
     */
    protected function _findTransactions($storeId = NULL)
    {
        // Get transactions from cache
        $searchCacheKey = $this->_getCacheKey('transactions_list', $storeId) . md5(serialize($this->_filters));
        if ( ! $this->refreshCache(FALSE) && $this->_useCache() && $cache = Mage::app()->loadCache($searchCacheKey)) {
            $transactions = unserialize($cache);

        } // Perform search
        else {
            $paymentModel = Mage::getSingleton('hps_securesubmit/payment'); /* @var $paymentModel Hps_Securesubmit_Model_Payment */
            $response = $paymentModel->setStore($storeId)->findTransactions($this->_filters);
            $exceptionMapper = new HpsExceptionMapper();
            $transactions = array();
            foreach ($response as $object) { /** @var $object HpsFindTransactionsTransactionDetails */
                $transaction = array();

                $transaction['first_name'] = $transaction['last_name'] = NULL;
                if ($object->cardHolderData instanceof HpsCardHolder) {
                    $transaction['first_name'] = (string) $object->cardHolderData->firstName;
                    $transaction['last_name'] = (string) $object->cardHolderData->lastName;
                }

                $transaction['invoice_number'] = $transaction['customer_id'] = NULL;
                if ($object->additionalTxnFields instanceof HpsAdditionalTxnFieldsData) {
                    $transaction['description'] = (string) $object->additionalTxnFields->description;
                    $transaction['invoice_number'] = (string) $object->additionalTxnFields->invoiceNumber;
                    $transaction['customer_id'] = (string) $object->additionalTxnFields->customerId;
                }

                $transaction['transaction_date'] = (string) $object->responseDatetime;
                $transaction['auth_code'] = (string) $object->authorizationCode;
                $transaction['cc_number'] = (string) $object->maskedCardNumber;
                $transaction['transaction_type'] = (string) $object->serviceName;
                $transaction['card_type'] = (string) strtoupper($object->cardType);
                $transaction['gateway_status'] = (string) $object->responseText;
                $transaction['transaction_id'] = (int) $object->transactionId;
                $transaction['amount'] = (float) $object->amount;

                $issuerResult = '00 Approval';
                if ($object->issuerResponseCode != '00') {
                    $exception = $exceptionMapper->map_issuer_exception($object->transactionId, $object->issuerResponseCode, $object->issuerResponseText);
                    $issuerResult = $object->issuerResponseCode.' '.$exception->getMessage();
                }
                $transaction['issuer_result'] = $issuerResult;
                $transactions[] = $transaction;
            }

            // Save search results in cache
            if ($this->_useCache()) {
                Mage::app()->saveCache(serialize($transactions), $searchCacheKey, array('COLLECTION_DATA', self::CACHE_TAG), 60 * 5);
            }
        }

        return $transactions;
    }

    /**
     * Cache key wrapper
     *
     * @param string $key
     * @param null|int $storeId
     * @return string
     */
    protected function _getCacheKey($key, $storeId = NULL)
    {
        return self::CACHE_PREFIX . '_' . md5($key) . '_STORE_' . $storeId;
    }

    /**
     * Check whether email archive cache is enabled
     *
     * @return bool
     */
    protected function _useCache()
    {
        return (bool) Mage::app()->useCache(self::CACHE_TYPE);
    }
}
