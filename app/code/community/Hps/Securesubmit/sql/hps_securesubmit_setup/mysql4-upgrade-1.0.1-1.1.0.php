<?php

$installer = $this;
 
$installer->startSetup();
 
$installer->run("
 
-- DROP TABLE IF EXISTS {$this->getTable('hps_securesubmit/report')};
CREATE TABLE {$this->getTable('hps_securesubmit/report')} (
  `row_id` int(11) unsigned NOT NULL auto_increment,
  `payer_email` varchar(255) NOT NULL default '',
  `order_id` varchar(255) NOT NULL default '',
  `invoice_id` varchar(255) NOT NULL default '',
  `transaction_id` varchar(255) NOT NULL default '',
  `last_known_status` varchar(255) NOT NULL default '',
  `created_time` datetime NULL,
  `update_time` datetime NULL,
  PRIMARY KEY (`row_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 
    ");
 
$installer->endSetup();