<?php
class InvalidRequestException extends HpsException{
    public $param = null;

    public function __construct($message, $param = null, $code = null){
        $this->param = $param;
        parent::__construct($message, $code);
    }

}