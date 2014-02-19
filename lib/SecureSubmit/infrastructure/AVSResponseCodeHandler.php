<?php


class AVSResponseCodeHandler {
    private $avsResultCode;
    private $config;
    private $response;
    private $transaction;
    private $transactionId;
    private $transactionType;
    private $ver;

    function __construct($response, $hpsCharge, $config=null)
    {
        $this->config = $config;
        if(count($this->config->avsResponseErrors) == 0){
            return;
        }
        $ver = $this->config->Version;

        if (extension_loaded('soap')) {
            $this->response = $response->$ver;
        }
        $this->transaction = $this->response->Transaction;
        $this->transactionId = $this->response->Header->GatewayTxnId;

        if(isset($this->transaction->CreditSale) && is_object($this->transaction->CreditSale)){
            $this->avsResultCode = $this->transaction->CreditSale->AVSRsltCode;
            $this->evaluate($hpsCharge,'sale');
        }else if(isset($this->transaction->CreditAuth) && is_object($this->transaction->CreditAuth)){
            $this->avsResultCode = $this->transaction->CreditAuth->AVSRsltCode;
            $this->evaluate($hpsCharge,'auth');
        }
    }

    function evaluate($hpsCharge,$type){
        $exceptionFound = false;
        $code = "";
        $message = "";

        foreach ($this->config->avsResponseErrors as $c=>$m) {
            if($this->avsResultCode == $c){
                $code = $c;
                $message = $m;
                $exceptionFound = true;
            }
        }

        if($exceptionFound){
            $hpsCharge->Void($this->transactionId);
            throw new HpsException($message,$code);
        }
    }
} 