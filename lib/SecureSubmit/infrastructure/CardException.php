<?php

class CardException extends HpsException{
    public  $TransactionId = null;
    public  $resultText = null;

    public function __construct($transactionId, $code, $message) {
        $this->TransactionId = $transactionId;
        parent::__construct($message, $code);
    }

}
