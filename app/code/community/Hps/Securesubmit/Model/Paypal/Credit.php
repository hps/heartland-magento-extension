<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

/**
 * PayPal Credit method
 */
class Hps_Securesubmit_Model_Paypal_Credit extends Hps_Securesubmit_Model_Paypal
{
    /**
     * Payment method code
     * @var string
     */
    protected $_code  = 'hps_paypal_credit';

    /**
     * Checkout payment form
     * @var string
     */

    protected $_formBlockType = 'hps_securesubmit/paypal_credit_form';
    protected $_infoBlockType = 'hps_securesubmit/paypal_info';

    /**
     * Checkout redirect URL getter for onepage checkout
     *
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return Mage::getUrl('securesubmit/paypal/credit');
    }

    public function isAvailable($quote = null)
    {
        return Mage::getStoreConfig('payment/hps_paypal_credit/active')
            && parent::isAvailable($quote);
    }
}
