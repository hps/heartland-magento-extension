<?php

class HpsTransactionResponseDetail
{
    public $RspCode;
    public $RspText;
    public $AuthCode;
    public $AuthAmt;
    public $AVSRsltCode;
    public $CVVRsltCode;
    public $RefNbr;
    public $CardType;
    public $AVSRsltText;
    public $AVSResultCodeAction;
    public $CVVRsltText;
    public $CVVResultCodeAction;
    public $Transactions;
    
    public $AdditionalFields;

    function BuildResponseDetail($responseDetail)
    {
        // populate standard fields, add anything else to $AdditionalFields
        foreach((array)$responseDetail as $key=>$value)
        {
            if(property_exists('HpsTransactionResponseDetail', $key))
            {
                $this->$key = $value;
            }
            else
            {
                $this->AdditionalFields["$key"] = $value;
            }
        }
    }

    function Validate($transactionId)
    {
        // $transactionId is passed in from HpsTransactionResponse for better error messages
        if ($this->RspCode != NULL and $this->RspCode != "00" and $this->RspCode != "85")
        {
            $Mapper = new ExceptionMapper();
            throw $Mapper->MapIssuerException($this->RspCode, $transactionId);
        }
    }

    function __construct($responseDetail=NULL)
    {
        if($responseDetail!=NULL)
        {
            $this->BuildResponseDetail($responseDetail);
        }    
    }
}

?>
