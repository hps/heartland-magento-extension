<?php

class Hps_Securesubmit_Model_Resource_Transaction_Collection extends Varien_Data_Collection
{
    /** @var Hps_Securesubmit_Model_Payment */
    protected $_connector;

    /** @var array */
    protected $_data = NULL;

    /** @var string */
    protected $_dateFormat = 'Y-m-d\TH:i:s.00\Z';

    /** @var string */
    protected $_timezone = 'UTC';

    /** @var int */
    protected $_priorDays = 30;

    /** @var int */
    protected $_maxPriorDays = 60;

    /** @var int */
    protected $_storeId = NULL;

    protected $_filtersMapping = array(
        'auth_code'         => 'AuthCode',
        'first_name'        => 'CardHolderFirstName',
        'last_name'         => 'CardHolderLastName',
        'invoice_number'    => 'InvoiceNbr',
        'customer_id'       => 'CustomerID',
        'transaction_type'  => 'ServiceName',
        'card_type'         => 'CardType',
        'issuer_result'     => 'IssuerResult',
        'transaction_id'    => 'TxnId',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->_connector = Mage::getSingleton('hps_securesubmit/transaction_query');
    }

    /**
     * @return Hps_Securesubmit_Model_Transaction_Query
     */
    public function getConnector()
    {
        return $this->_connector;
    }

    /**
     * Set store id
     *
     * @param int $storeId
     * @return Hps_Securesubmit_Model_Resource_Transaction_Collection
     */
    public function setStoreId($storeId)
    {
        $this->_storeId = (int) $storeId;
        return $this;
    }

    /**
     * Add search criteria.
     *
     * @param mixed $field
     * @param mixed $condition
     * @return Hps_Securesubmit_Model_Resource_Transaction_Collection
     */
    public function addFieldToFilter($field, $condition = NULL)
    {
        $this->_addQueryFilter($field, $condition);
        return $this;
    }

    /**
     * Load data
     *
     * @param bool $printQuery
     * @param bool $logQuery
     * @return  Hps_Securesubmit_Model_Resource_Transaction_Collection
     */
    public function load($printQuery = false, $logQuery = false)
    {
        if ($this->isLoaded()) {
            return $this;
        }

        $data = $this->getData();
        $this->resetData();

        if (is_array($data)) {
            foreach ($data as $row) {
                $item = $this->getNewEmptyItem();
                $item->addData($row);
                $this->addItem($item);
            }
        }

        $this->_setIsLoaded();
        return $this;
    }

    /**
     * Get all data array for collection
     *
     * @return array
     */
    public function getData()
    {
        if ($this->_data === NULL) {
            $data = $this->getConnector()->findTransactions($this->_storeId);

            // Apply sort criteria and direction
            $order = array_slice($this->_orders, 0, 1);
            $criteria = key($order);
            $direction = array_shift($order);
            if ($criteria && $direction) {
                $sort = array();
                foreach ($data as $key => $row) {
                    $sort[$key] = $row[$criteria];
                }
                array_multisort($sort, ($direction == 'ASC') ? SORT_ASC : SORT_DESC, $data);
            }

            // Apply pagination
            $currentPage = array();
            $startIndex = ($this->getCurPage() - 1) * $this->getPageSize();
            $endIndex = min($startIndex + $this->getPageSize() - 1, $this->getSize() - 1);
            $index = $startIndex;
            while ($index <= $endIndex) {
                $currentPage[] = $data[$index];
                $index ++;
            }

            $this->_data = $currentPage;
        }
        return $this->_data;
    }

    /**
     * Reset loaded for collection data array
     *
     * @return Hps_Securesubmit_Model_Resource_Transaction_Collection
     */
    public function resetData()
    {
        $this->_data = NULL;
        return $this;
    }

    /**
     * Retrieve collection all items count.
     * API does not provide functionality to retrieve count of transactions.
     *
     * @return int
     */
    public function getSize()
    {
        return $this->getConnector()->getAmount();
    }

    /**
     * Prepare query filter
     *
     * @param mixed $field
     * @param mixed $condition
     * @throws Mage_Core_Exception
     * @return Hps_Securesubmit_Model_Resource_Transaction_Collection
     */
    protected function _addQueryFilter($field, $condition)
    {
        // Process transaction date filter. Both "StartUtcDT" and "EndUtcDT" are required!
        if ($field == 'transaction_date' && (isset($condition['from']) || $condition['to'])) {
            // "From" date
            if (isset($condition['from']) && ($condition['from'] instanceof Zend_Date)) {
                $fromDate = $condition['from']; /** @var $fromDate Zend_Date */
                $currentDate = new Zend_Date(Mage::getSingleton('core/date')->gmtDate('Y-m-d'));
                $earliestDate = clone $currentDate;
                $earliestDate->subDay($this->_maxPriorDays);
                if ($fromDate->isEarlier($earliestDate)) {
                    $fromDate = $earliestDate;
                }
                $this->getConnector()->addFilter('StartUtcDT', $this->_formatDate($fromDate));
            } else {
                $from = new Zend_Date(Mage::getSingleton('core/date')->gmtDate('Y-m-d'));
                $from->subDay($this->_priorDays);
                $this->getConnector()->addFilter('StartUtcDT', $this->_formatDate($from));
            }

            // "To" date
            if (isset($condition['to']) && ($condition['to'] instanceof Zend_Date)) {
                $this->getConnector()->addFilter('EndUtcDT', $this->_formatDate($condition['to']));
            } else {
                $this->getConnector()->addFilter('EndUtcDT', $this->_formatDate());
            }
        } else {
            // Prepare condition
            if (isset($condition['like'])) {
                $convertedCondition = str_replace('\_', '_', (string) $condition['like']); // unescape SQL syntax
                $convertedCondition = str_replace("'", '', (string) $convertedCondition);
                $convertedCondition = trim($convertedCondition, '%');
            } else if (isset($condition['eq'])) {
                $convertedCondition = (string) $condition['eq'];
            } else {
                $convertedCondition = (string) $condition;
            }

            // Process credit card filter
            if ($field == 'cc_number') {
                if (strlen($convertedCondition) === 6) {
                    $this->getConnector()->addFilter('CardNbrFirstSix', $convertedCondition);
                } else {
                    $this->getConnector()->addFilter('CardNbrLastFour', $convertedCondition);
                }
            }
            // Issuer result field is the code only and supports range check
            else if ($field == 'issuer_result') {
                if ( ! preg_match('/^[0-9EN][0-9BC](-[0-9EN][0-9BC])?$/', trim($convertedCondition, '%'))) {
                    throw new Mage_Core_Exception('Issuer result query must be the two-digit code (00) or a range of codes (00-05).');
                }
                $this->getConnector()->addFilter('IssuerResult', trim($convertedCondition, '%'));
            }
            // Process other fields
            else if (isset($this->_filtersMapping[$field])) {
                $this->getConnector()->addFilter($this->_filtersMapping[$field], $convertedCondition);
            }
        }
    }

    /**
     * Format date to internal API format
     *
     * @param Zend_Date|string|null $date
     * @return string
     */
    protected function _formatDate($date = NULL)
    {
        if ($date === NULL) {
            $date = Mage::getSingleton('core/date')->gmtDate('Y-m-d');
        } else if ($date instanceof Zend_Date) {
            $date = $date->toString();
        }
        $dateTime = new DateTime($date, new DateTimeZone($this->_timezone));
        return $dateTime->format($this->_dateFormat);
    }
}
