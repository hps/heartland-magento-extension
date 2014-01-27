<?php

class HpsToken
{
    public $TokenValue;
    public $ExpYear;
    public $ExpMonth;

    function __construct($tokenValue = NULL, $expYear = NULL, $expMonth = NULL)
    {
        $this->TokenValue = $tokenValue;
        $this->ExpYear = $expYear;
        $this->ExpMonth = $expMonth;
    }
}
