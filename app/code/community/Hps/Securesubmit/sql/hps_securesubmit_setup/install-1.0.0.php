<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('hps_securesubmit/storedcard'))
    ->addColumn(
        'storedcard_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        array('identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'dt',
        Varien_Db_Ddl_Table::TYPE_DATETIME,
        null,
        array())
    ->addColumn(
        'customer_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        array('unsigned' => true, 'nullable' => false, 'default' => '0')
    )
    ->addColumn(
        'token_value',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        255,
        array('nullable' => false)
    )
    ->addColumn(
        'cc_type',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        25,
        array('nullable' => false)
    )
    ->addColumn(
        'cc_last4',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        4,
        array('nullable' => false)
    )
    ->addColumn(
        'cc_exp_month',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        2,
        array('nullable' => false)
    )
    ->addColumn(
        'cc_exp_year',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        4,
        array('nullable' => false)
    )
    ->addForeignKey(
        $installer->getFkName(
            'hps_securesubmit/storedcard',
            'customer_id',
            'customer/entity',
            'entity_id'),
        'customer_id', 
        $installer->getTable(
            'customer/entity'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, 
        Varien_Db_Ddl_Table::ACTION_CASCADE
    );

$installer->getConnection()->createTable($table);
$installer->endSetup();
