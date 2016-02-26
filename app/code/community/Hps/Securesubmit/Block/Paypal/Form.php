<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

/**
 * PayPal Standard payment "form"
 */
class Hps_Securesubmit_Block_Paypal_Form extends Mage_Payment_Block_Form
{
    /**
     * Payment method code
     * @var string
     */
    protected $_methodCode = 'hps_paypal';

    /**
     * Set template and redirect message
     */
    protected function _construct()
    {
        $mark = Mage::getConfig()->getBlockClassName('core/template');
        $mark = new $mark;
        $mark->setTemplate('paypal/payment/mark.phtml')
            ->setPaymentAcceptanceMarkHref('https://www.paypal.com/us/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIsPayPal-outside')
            ->setPaymentAcceptanceMarkSrc('https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-medium.png')
        ; // known issue: code above will render only static mark image
        $this->setTemplate('paypal/payment/redirect.phtml')
            ->setRedirectMessage(
                Mage::helper('hps_securesubmit')->__('You will be redirected to the PayPal website.')
            )
            ->setMethodTitle('') // Output PayPal mark, omit title
            ->setMethodLabelAfterHtml($mark->toHtml())
        ;
        return parent::_construct();
    }

    /**
     * Payment method code getter
     * @return string
     */
    public function getMethodCode()
    {
        return $this->_methodCode;
    }
}
