<?php

class CardException extends HpsException{
    public  $transaction_id = null;

    public function __construct($transactionId, $code, $message) {
        $this->TransactionId = $transactionId;
        parent::__construct($message, $code);
    }

}