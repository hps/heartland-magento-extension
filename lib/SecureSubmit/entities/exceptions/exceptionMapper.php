<?php
require_once("exceptions.php");

class ExceptionMapper
{

    private static $SystemExceptions;
    public static $IssuerExceptions;

    public function BuildSystemExceptions()
    {
         self::$SystemExceptions = array(
         "-2" => new AuthenticationException(ExceptionMessages::AuthenticationError, ExceptionCodes::AuthenticationError),
         "1" => new HpsException("Gateway system error."),
         "2" => new HpsException("Duplicate transactions."),
         "3" => new HpsException("Invalid original transaction."),
         "4" => new HpsException("Transaction already associated with batch."),
         "5" => new HpsException(ExceptionMessages::NoOpenBatch, ExceptionCodes::NoOpenBatch),
         "9" => new HpsException("No transaction associated with batch."),
         "12" => new InvalidRequestException("Invalid CPC data."),
         "13" => new InvalidRequestException("Invalid card data."),
         "14" => new CardException(ExceptionMessages::IncorrectNumber, ExceptionCodes::IncorrectNumber),
         "30" => new HpsException("Gateway timed out.") 
        );
    }

    public function MapSystemException($responseCode, $transactionId)
    {
        // Note that this function returns one of four different types of exceptions
        $SystemException = self::$SystemExceptions[$responseCode];
        if($SystemException == "")
        {
            $SystemException = new HpsException("Unknown system error."); 
        }
        $SystemException->TransactionId = $transactionId;
        return $SystemException;
    }

    public function BuildIssuerExceptions()
    {
         self::$IssuerExceptions = array(
         "02" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "03" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "04" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "05" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "06" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "07" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "12" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "13" => new CardException(ExceptionMessages::ChargeAmount, ExceptionCodes::ChargeAmount),
         "14" => new CardException(ExceptionMessages::IncorrectNumber, ExceptionCodes::IncorrectNumber),
         "15" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "19" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "21" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "41" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "43" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "44" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "51" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "52" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "53" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "54" => new CardException(ExceptionMessages::CardExpired, ExceptionCodes::CardExpired),
         "55" => new CardException(ExceptionMessages::InvalidPin, ExceptionCodes::InvalidPin),
         "56" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "57" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "58" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "61" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "62" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "63" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "65" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "75" => new CardException(ExceptionMessages::PinRetriesExceeded, ExceptionCodes::PinRetriesExceeded),
         "76" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "77" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "78" => new CardException(ExceptionMessages::CardDeclined, ExceptionCodes::CardDeclined),
         "80" => new CardException(ExceptionMessages::InvalidExpiry, ExceptionCodes::InvalidExpiry),
         "86" => new CardException(ExceptionMessages::PinVerification, ExceptionCodes::PinVerification),
         "91" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "96" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "EB" => new CardException(ExceptionMessages::IncorrectCvc, ExceptionCodes::IncorrectCvc),
         "EC" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         "N7" => new CardException(ExceptionMessages::IncorrectCvc, ExceptionCodes::IncorrectCvc),
         "R1" => new CardException(ExceptionMessages::ProcessingError, ExceptionCodes::ProcessingError),
         );
    }
    
    public function MapIssuerException($responseCode, $transactionId)
    {
        $IssuerException = self::$IssuerExceptions[$responseCode];
        if($IssuerException == "")
        {
            $IssuerException = new CardException("Unknown error from issuer.");
        }
        $IssuerException->TransactionId = $transactionId;
        return $IssuerException;
    }
    
    public function __construct()
    {
        $this->BuildIssuerExceptions();
        $this->BuildSystemExceptions();
    }
}

?>
