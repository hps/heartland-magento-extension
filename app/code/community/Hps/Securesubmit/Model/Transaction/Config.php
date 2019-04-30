<?php

class Hps_Securesubmit_Model_Transaction_Config
{
    /**
     * Retrieve card type options
     *
     * @return array
     */
    public function getCardTypeOptions()
    {
        static $options;
        if ( ! $options) {
            $options = array(
                'VISA'      => 'VISA',
                'MC'        => 'MC',
                'DISC'      => 'DISC',
                'AMEX'      => 'AMEX',
                'GIFTCARD'  => 'GIFTCARD',
            );
        }
        return $options;
    }

    /**
     * Retrieve service name type options
     *
     * @return array
     */
    public function getServiceNameTypeOptions()
    {
        static $options;
        if ( ! $options) {
            $options = array(
                'DebitSale'                 => 'DebitSale',
                'DebitReturn'               => 'DebitReturn',
                'CreditOfflineSale'         => 'CreditOfflineSale',
                'CreditOfflineAuth'         => 'CreditOfflineAuth',
                'CreditReturn'              => 'CreditReturn',
                'CreditReversal'            => 'CreditReversal',
                'CreditAuth'                => 'CreditAuth',
                'CreditSale'                => 'CreditSale',
                'CreditVoid'                => 'CreditVoid',
                'CheckSale'                 => 'CheckSale',
                'CheckVoid'                 => 'CheckVoid',
                'GiftCardActivate'          => 'GiftCardActivate',
                'GiftCardAddValue'          => 'GiftCardAddValue',
                'GiftCardReplace'           => 'GiftCardReplace',
                'GiftCardReward'            => 'GiftCardReward',
                'GiftCardSale'              => 'GiftCardSale',
                'GiftCardTip'               => 'GiftCardTip',
                'GiftCardReversal'          => 'GiftCardReversal',
                'GiftCardVoid'              => 'GiftCardVoid',
                'EBTFSPurchase'             => 'EBTFSPurchase',
                'EBTFSReturn'               => 'EBTFSReturn',
                'EBTVoucherPurchase'        => 'EBTVoucherPurchase',
                'EBTCashBackPurchase'       => 'EBTCashBackPurchase',
                'EBTCashBenefitWithdrawal'  => 'EBTCashBenefitWithdrawal',
                'PrePaidAddValue'           => 'PrePaidAddValue',
                'ReCurringBilling'          => 'ReCurringBilling',
            );
        }
        return $options;
    }
}
