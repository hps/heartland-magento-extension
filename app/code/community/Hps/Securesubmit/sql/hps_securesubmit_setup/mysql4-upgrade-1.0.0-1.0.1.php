<?php
/** @var $this Hps_Securesubmit_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

/*
 * Add 'use_stored_card' column to 'sales_flat_quote_payment' and 'sales_flat_order_payment' tables.
 */

$installer->getConnection()->addColumn($installer->getTable('sales/quote_payment'), 'securesubmit_use_stored_card', 'TINYINT UNSIGNED DEFAULT NULL');
$installer->getConnection()->addColumn($installer->getTable('sales/order_payment'), 'securesubmit_use_stored_card', 'TINYINT UNSIGNED DEFAULT NULL');

$installer->endSetup();
