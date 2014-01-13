<?php
class HpsException extends Exception{
    public  $innerException    = null,
        $responseCode      = null,
        $responseText      = null;

    public function __construct($message,$code, $innerException = null){
        $this->code = $code;
        $this->innerException = $innerException;
        parent::__construct($message);
    }

    public function code(){
        if($this->code == null){
            return "unknown";
        }else{
            return $this->code;
        }
    }
} 