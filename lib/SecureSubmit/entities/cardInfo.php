<?php
require_once("exceptions/exceptions.php");

class HpsCardInfo
{
    const UNKNOWN = "Unknown";

    public $CardNbr;
    public $ExpYear;
    public $ExpMonth;
    public $CVV2;

    public function __construct($cardNbr = NULL, $expYear = NULL, $expMonth = NULL, $CVV2 = NULL)
    {
        $this->CardNbr = $cardNbr;
        $this->ExpYear = $expYear;
        $this->ExpMonth = $expMonth;
        $this->CVV2 = $CVV2;
    }

    public function CardType()
    {
        // Returns a string containing the card type if it is identifiable, or "Unknown" otherwise
        $cardRegexes = array(
            "Amex"=> "/^3[47][0-9]{13}$/",
            "MasterCard" => "/^5[1-5][0-9]{14}$/",
            "Visa" => "/^4[0-9]{12}(?:[0-9]{3})?$/",
            "DinersClub" => "/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/",
            "EnRoute" => "/^(2014|2149)/",
            "Discover" => "/^6(?:011|5[0-9]{2})[0-9]{12}$/",
            "Jcb" => "/^(?:2131|1800|35\d{3})\d{11}$/",
        );

        $type = self::UNKNOWN;
        foreach($cardRegexes as $card=>$rx)
        {
            if(preg_match($rx, $this->CardNbr))
            {
                $type = $card;
            }
        }
        return $type;
    }

    public function ValidateNumber()
    {
        // Throw exception if CardType() can not match to a known type regex
        if ($this->CardType() == self::UNKNOWN)
        {
            throw new CardException(ExceptionMessages::IncorrectNumber);
        } 
    }

    public function Validate($strict = false)
    {
        // Validate that required properties are set
        // If $strict, verify that the card number is valid (experimental)
        if($this->CardNbr == NULL)
            throw new CardException(ExceptionMessages::ArgumentNull);
        if($this->ExpYear == NULL or $this->ExpMonth == NULL)
            throw new CardException(ExceptionMessages::ArgumentNull);
        if($strict)
        {
            $this->ValidateNumber();
        }
        return true;
    }
} // end class HpsCardInfo

?>
