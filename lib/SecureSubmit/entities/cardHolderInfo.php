<?php
require_once(dirname(__FILE__).DS.'addressInfo.php');

class HpsCardHolderInfo
{
    public $Address;
    public $FirstName;
    public $LastName;
    public $Phone;
    public $Email;

    function __construct($firstName=NULL, $lastName=NULL, $phone=NULL, $email=NULL, $address=NULL)
    {
        $this->Address = new HpsAddressInfo();
        $this->FirstName = $firstName;
        $this->LastName = $lastName;
        $this->Phone = $phone;
        $this->Email = $email;
        if ($address != NULL)
        {
            $this->Address = $address;
        }
    }

    function isValid()
    {
        // in general make sure everything is filled out etc
        return true;
    }
} // end class HpsCardInfo
