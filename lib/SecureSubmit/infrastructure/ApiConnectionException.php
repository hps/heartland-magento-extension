<?php
class ApiConnectionException extends HpsException{

    public function __construct($message, $code = 0, $innerException = null){
        parent::__construct($message, $code, $innerException);
    }

}