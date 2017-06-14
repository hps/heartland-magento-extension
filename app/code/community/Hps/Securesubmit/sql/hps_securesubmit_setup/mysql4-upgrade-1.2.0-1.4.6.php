<?php
/** @var $this Hps_Securesubmit_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

/*
 * Add "storedcard_address" table.
 */
$this->run("
CREATE TABLE IF NOT EXISTS `{$this->getTable('hps_securesubmit/storedcard_address')}` (
  `storedcard_id` int(10) unsigned NOT NULL,
  `customer_address_id` int(10) unsigned NOT NULL,
  UNIQUE KEY `UNQ_STORED_CARD_ID_CUSTOMER_ADDRESS_ID` (`storedcard_id`,`customer_address_id`),
  KEY `customer_address_id` (`customer_address_id`)
) ENGINE=InnoDB;
");

$this->getConnection()->addForeignKey(
    $installer->getFkName('hps_securesubmit/storedcard_address', 'storedcard_id', 'hps_securesubmit/storedcard', 'storedcard_id'),
    $installer->getTable('hps_securesubmit/storedcard_address'),
    'storedcard_id',
    $installer->getTable('hps_securesubmit/storedcard'),
    'storedcard_id',
    Varien_Db_Ddl_Table::ACTION_CASCADE,
    Varien_Db_Ddl_Table::ACTION_CASCADE
);

$this->getConnection()->addForeignKey(
    $installer->getFkName('hps_securesubmit/storedcard_address', 'customer_address_id', 'customer/address_entity', 'entity_id'),
    $installer->getTable('hps_securesubmit/storedcard_address'),
    'customer_address_id',
    $installer->getTable('customer/address_entity'),
    'entity_id',
    Varien_Db_Ddl_Table::ACTION_CASCADE,
    Varien_Db_Ddl_Table::ACTION_CASCADE
);

/**
 * Assign existing stored cards to all existing customer's addresses.
 */
$select = $this->getConnection()->select()
    ->from(
        array('sc' => $this->getTable('hps_securesubmit/storedcard')),
        array('storedcard_id' => 'sc.storedcard_id', 'customer_address_id' => 'cae.entity_id'))
    ->join(array('cae' => $this->getTable('customer/address_entity')), 'sc.customer_id = cae.parent_id', array());
$query = $select->insertIgnoreFromSelect($this->getTable('hps_securesubmit/storedcard_address'));
$this->getConnection()->query($query);

$installer->endSetup();
