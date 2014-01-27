<?php

class HpsAddressInfo
{
    public $Address;
    public $City;
    public $State;
    public $Zip;
    public $Country;

    function __construct($address = NULL, $city = NULL, $state = NULL, $zip = NULL, $country = NULL)
    {
        $this->Address = $address;
        $this->City = $city;
        $this->State = $state;
        $this->Zip = $zip;
        $this->Country = $country;
    }
}
