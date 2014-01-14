<?php

require_once("transactionResponseDetail.php");

class HpsTransactionResponse
{
    public $TransactionId;   // GatewayTxnId
    public $ResponseCode;    // GatewayRspCode
    public $ResponseMessage; // GatewayRspMsg
    public $DateTime;        // RspDT
    public $ResponseType;    // Example: CreditAuth, CreditSale, etc
    public $TransactionDetails;
    public $TokenData = NULL;
    public $exceptionMapper;

    function __construct($response=NULL, $config=NULL)
    {
        $this->exceptionMapper = new HpsExceptionMapper();
        if ($response != NULL)
        {
            $this->BuildTransactionResponse($response, $config);
        }
        else
        {
            $this->TransactionDetails = new HpsTransactionResponseDetail();
        }
    }

    function BuildTransactionResponse($response, $config=NULL)
    {
        $CONFIG = new HpsServicesConfig($config);
        $ver = $CONFIG->Version;

        if( class_exists("SOAPClient") != true){
            $response = $response->$ver;  // Strip out the added level of "Ver1.0"
        }

        $this->TransactionId = $response->Header->GatewayTxnId;
        $this->ResponseCode = $response->Header->GatewayRspCode; 
        $this->ResponseMessage = $response->Header->GatewayRspMsg;
        if (isset($response->Header->RspDT))
            $this->DateTime = $response->Header->RspDT;
        else
            $this->Validate();

        if (isset($response->Header->TokenData))
            $this->TokenData = $response->Header->TokenData;
        
        // Drill down to what we want 
        if(isset($response->Transaction))
        {
            $responseDetailArray = (array)$response->Transaction;
            $keys = array_keys($responseDetailArray); // the response details are wrapped in a requesttype
            $this->ResponseType = $keys[0];
            $responseDetail = array_pop($responseDetailArray);
            $this->TransactionDetails = new HpsTransactionResponseDetail($responseDetail);
        }
    }

    function Validate()
    {
        if ($this->ResponseCode != "0" or $this->ResponseCode != 0)
        {
            // This indicates a system error
            throw $this->exceptionMapper->map_gateway_exception($this->TransactionId,$this->ResponseCode,$this->ResponseMessage);
        } 

        // Validate response code from Issuer 
        $this->TransactionDetails->Validate($this->TransactionId);
    }

}

?>
