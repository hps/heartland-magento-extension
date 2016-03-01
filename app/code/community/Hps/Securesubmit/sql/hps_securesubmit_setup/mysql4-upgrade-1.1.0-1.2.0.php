<?php
$installer = Mage::getResourceModel('customer/setup', 'customer_setup');

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$entityTypeId     = $setup->getEntityTypeId('customer');
$attributeSetId   = $setup->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $setup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$installer->addAttribute('customer', 'masterpass_long_access_token',  array(
    'type'     => 'varchar',
    'backend'  => '',
    'label'    => 'MasterPass Long Access Token',
    'input'    => 'text',
    'source'   => '',
    'visible'  => false,
    'required' => false,
    'default'  => '',
    'frontend' => '',
    'unique'   => true,
    'note'     => 'MasterPass Long Access Token'
));

$attribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'masterpass_long_access_token');


$setup->addAttributeToGroup(
    $entityTypeId,
    $attributeSetId,
    $attributeGroupId,
    'masterpass_long_access_token',
    '999'  //sort_order
);

$installer->endSetup();
