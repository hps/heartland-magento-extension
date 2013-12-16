<?php

class CardException extends Exception
{

    public $TransactionId;

    public function __construct($message, $code = 0, $transactionId = 0, Exception $previous = null) 
    {
        $this->TransactionId = $transactionId;    
        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString() 
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\t[TransactionId = $this->TransactionId]\n";
    }

    public function Code()
    {
        return $this->code;
    }
    
    public function Message()
    {
        return $this->message;
    }
}

?>
