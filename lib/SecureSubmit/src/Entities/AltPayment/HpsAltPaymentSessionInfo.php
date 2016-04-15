<?php

class HpsAltPaymentSessionInfo extends HpsAltPaymentResponse
{
    /** @var string|null */
    public $status    = null;

    /** @var HpsBuyerData|null */
    public $buyer     = null;

    /** @var HpsPaymentData|null */
    public $payment   = null;

    /** @var HpsShippingInfo|null */
    public $shipping  = null;

    /** @var array(HpsLineItem)|null */
    public $lineItems = null;

    public static function fromDict($rsp, $txnType, $returnType = 'HpsAltPaymentSessionInfo')
    {
        $sessionInfo = $rsp->Transaction->$txnType;
        $buyer     = self::nvpToArray($sessionInfo->Buyer);
        $payment   = self::nvpToArray($sessionInfo->Payment);
        $shipping  = self::nvpToArray($sessionInfo->Shipping->Address);
        $lineItems = self::nvpToArray($sessionInfo->LineItem->Detail);

        $session = parent::fromDict($rsp, $txnType, $returnType);
        $session->status = isset($sessionInfo->Status) ? (string)$sessionInfo->Status : null;

        $session->buyer = new HpsBuyerData();
        $session->buyer->emailAddress = isset($buyer['EmailAddress']) ? (string)$buyer['EmailAddress'] : null;
        $session->buyer->payerId = isset($buyer['BuyerId']) ? (string)$buyer['BuyerId'] : null;
        $session->buyer->status = isset($buyer['Status']) ? (string)$buyer['Status'] : null;
        $session->buyer->countryCode = isset($buyer['CountryCode']) ? (string)$buyer['CountryCode'] : null;
        $session->buyer->firstName = isset($buyer['FirstName']) ? (string)$buyer['FirstName'] : null;
        $session->buyer->lastName = isset($buyer['LastName']) ? (string)$buyer['LastName'] : null;
        $session->buyer->phoneNumber = isset($buyer['PhoneNumber']) ? (string)$buyer['PhoneNumber'] : null;

        $session->shipping = new HpsShippingInfo();
        $session->shipping->name = isset($shipping['ShipName']) ? (string)$shipping['ShipName'] : null;
        $session->shipping->address = new HpsAddress();
        $session->shipping->address->address = isset($shipping['ShipAddress']) ? (string)$shipping['ShipAddress'] : null;
        $session->shipping->address->address2 = isset($shipping['ShipAddress2']) ? (string)$shipping['ShipAddress2'] : null;
        $session->shipping->address->city = isset($shipping['ShipCity']) ? (string)$shipping['ShipCity'] : null;
        $session->shipping->address->state = isset($shipping['ShipState']) ? (string)$shipping['ShipState'] : null;
        $session->shipping->address->zip = isset($shipping['ShipZip']) ? (string)$shipping['ShipZip'] : null;
        $session->shipping->address->country = isset($shipping['ShipCountryCode']) ? (string)$shipping['ShipCountryCode'] : null;

        $session->payment = new HpsPaymentData();
        $session->payment->subtotal = isset($payment['ItemAmount']) ? (string)$payment['ItemAmount'] : null;
        $session->payment->shippingAmount = isset($payment['ShippingAmount']) ? (string)$payment['ShippingAmount'] : null;
        $session->payment->taxAmount = isset($payment['TaxAmount']) ? (string)$payment['TaxAmount'] : null;

        $session->lineItems = array();
        $lineItem = new HpsLineitem();
        $lineItem->name = isset($lineItems['Name']) ? (string)$lineItems['Name'] : null;
        $lineItem->amount = isset($lineItems['Amount']) ? (string)$lineItems['Amount'] : null;
        $lineItem->number = isset($lineItems['Number']) ? (string)$lineItems['Number'] : null;
        $lineItem->quantity = isset($lineItems['Quantity']) ? (string)$lineItems['Quantity'] : null;
        $lineItem->taxAmount = isset($lineItems['TaxAmount']) ? (string)$lineItems['TaxAmount'] : null;

        return $session;
    }
}
