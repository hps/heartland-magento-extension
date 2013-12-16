<?php
// Configuration class to hold exception messages

class ExceptionMessages
{
    const ArgumentNull = "Argument can not be null.";
    const AuthenticationError =  "Authentication error. Please double check your service configuration.";
    const CardDeclined = "The card was declined.";
    const CardExpired = "The card has expired.";
    const ChargeAmount = "Charge amount must be greater than or equal 0.";
    const FutureDate = "Date can not be in the future.";
    const IncorrectCvc = "The card's security code is incorrect.";
    const IncorrectNumber = "The card number is incorrect.";
    const InvalidConfig = "The HPS SDK has not been properly configured.";
    const InvalidCurrency = "The only supported currency is 'usd'";
    const InvalidPin = "The 4-digit pin is invalid.";
    const PinRetriesExceeded = "Maximum number of pin retries exceeded.";
    const PinVerification = "Can't varify card pin number.";
    const ProcessingError = "An error occurred while processing the card.";
    const TransactionIdLessThanEqualZero = "Transaction ID must be greater than zero.";
    const UnexpectedResponseCode = "Unsuccessful response code recieved from HPS Gateway - ";
    const UnexpectedResponseType = "Received unexpected response from POS Gateway. (Request and response transaction types don't match).";
    const InvalidExpiry = "Invalid expiration date.";
    const NoOpenBatch = "No current batch.";
}

class ExceptionCodes
{
    const ArgumentNull = 0;
    const AuthenticationError =  1;
    const CardDeclined = 2;
    const CardExpired = 3;
    const ChargeAmount = 4;
    const FutureDate = 5;
    const IncorrectCvc = 6;
    const IncorrectNumber = 7;
    const InvalidConfig = 8;
    const InvalidCurrency = 9;
    const InvalidPin = 10;
    const PinRetriesExceeded = 11;
    const PinVerification = 12;
    const ProcessingError = 13;
    const TransactionIdLessThanEqualZero = 14;
    const UnexpectedResponseCode = 15;
    const UnexpectedResponseType = 16;
    const InvalidExpiry = 17;
    const NoOpenBatch = 18;
}

?>
