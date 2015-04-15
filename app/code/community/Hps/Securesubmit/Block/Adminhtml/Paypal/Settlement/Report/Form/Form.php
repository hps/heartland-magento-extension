<?php

class Hps_Securesubmit_Block_Adminhtml_Paypal_Settlement_Report_Form_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $model = Mage::registry('current_transaction');

        $form = new Varien_Data_Form();
        $form->addField('row_id', 'label', array(
            'name'  => 'row_id',
            'label' => Mage::helper('hps_securesubmit')->__('ID'),
            'title' => Mage::helper('hps_securesubmit')->__('ID'),
            'value' => $model->getData('row_id'),
        ));
        $form->addField('order_id', 'label', array(
            'name'  => 'order_id',
            'label' => Mage::helper('hps_securesubmit')->__('Order ID'),
            'title' => Mage::helper('hps_securesubmit')->__('Order ID'),
            'value' => $model->getData('order_id'),
        ));
        $form->addField('payer_email', 'label', array(
            'name'  => 'payer_email',
            'label' => Mage::helper('hps_securesubmit')->__('Payer Email'),
            'title' => Mage::helper('hps_securesubmit')->__('Payer Email'),
            'value' => $model->getData('payer_email'),
        ));
        $form->addField('transaction_id', 'label', array(
            'name'  => 'transaction_id',
            'label' => Mage::helper('hps_securesubmit')->__('Transaction ID'),
            'title' => Mage::helper('hps_securesubmit')->__('Transaction ID'),
            'value' => $model->getData('transaction_id'),
        ));
        $form->addField('last_known_status', 'label', array(
            'name'  => 'last_known_status',
            'label' => Mage::helper('hps_securesubmit')->__('Last Known Status'),
            'title' => Mage::helper('hps_securesubmit')->__('Last Known Status'),
            'value' => $model->getData('last_known_status'),
        ));
        $form->addField('created_time', 'label', array(
            'name'  => 'created_time',
            'label' => Mage::helper('hps_securesubmit')->__('Creation Time'),
            'title' => Mage::helper('hps_securesubmit')->__('Creation Time'),
            'value' => $model->getData('created_time'),
        ));
        $form->addField('update_time', 'label', array(
            'name'  => 'update_time',
            'label' => Mage::helper('hps_securesubmit')->__('Update Time'),
            'title' => Mage::helper('hps_securesubmit')->__('Update Time'),
            'value' => $model->getData('update_time'),
        ));

        $this->setForm($form);
        return parent::_prepareForm();
    }
}
