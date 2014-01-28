<?php
class HpsException extends Exception{

    public function __construct($message, $code, $innerException = null){
        $this->code = $code;
        parent::__construct($message, 0, $innerException);
    }

    public function code(){
        if($this->code == null){
            return "unknown";
        }else{
            return $this->code;
        }
    }
}
