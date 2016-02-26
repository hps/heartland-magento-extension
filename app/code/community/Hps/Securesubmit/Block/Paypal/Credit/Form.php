<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

/**
 * PayPal Credit payment form
 */
class Hps_Securesubmit_Block_Paypal_Credit_Form extends Hps_Securesubmit_Block_Paypal_Form
{
    /**
     * Payment method code
     * @var string
     */
    protected $_methodCode = 'hps_paypal_credit';

    /**
     * Set template and redirect message
     */
    protected function _construct()
    {
        $this->_config = Mage::getModel('hps_securesubmit/config')->setMethod($this->getMethodCode());
        $mark = Mage::getConfig()->getBlockClassName('core/template');
        $mark = new $mark;
        $mark->setTemplate('paypal/payment/mark.phtml')
            ->setPaymentAcceptanceMarkHref('https://www.securecheckout.billmelater.com/paycapture-content/'
                . 'fetch?hash=AU826TU8&content=/bmlweb/ppwpsiw.html')
            ->setPaymentAcceptanceMarkSrc('https://www.paypalobjects.com/webstatic/en_US/i/buttons/'
                . 'ppc-acceptance-medium.png')
            ->setPaymentWhatIs('See terms');
        $this->setTemplate('paypal/payment/redirect.phtml')
            ->setRedirectMessage(
                Mage::helper('hps_securesubmit')->__('You will be redirected to the PayPal website.')
            )
            ->setMethodTitle('') // Output PayPal mark, omit title
            ->setMethodLabelAfterHtml($mark->toHtml());
    }
}
