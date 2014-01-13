<?php
set_include_path(get_include_path() . PATH_SEPARATOR . realpath(dirname(__FILE__) ));

require_once("config/config.php");        //This is where you'll set all your merchant details

require_once("entities/cardInfo.php");
require_once("entities/cardHolderInfo.php");
require_once("entities/token.php");
require_once("entities/transactionResponse.php");

require("infrastructure/posgateway_lib.php");
require("infrastructure/transactions_lib.php");

require_once("Hps.php");

class HpsChargeService
{
    private $CONFIG;
    private $exceptionMapper;

    public function __construct($config=NULL)
    {
        $this->exceptionMapper = new HpsExceptionMapper();
        $this->CONFIG = new HpsServicesConfig($config);
    }

    public function CheckAmount($amount)
    {
        if ($amount <= 0)
        {
            throw $this->exceptionMapper->map_sdk_exception(HpsSdkCodes::$invalidAmount);
        }
    }

    public function CheckCurrency($currency)
    {
        if ($currency == null or $currency == "")
        {
            throw $this->exceptionMapper->map_sdk_exception(HpsSdkCodes::$missingCurrency);
        }
        if (strtolower($currency) != "usd")
        {
            throw $this->exceptionMapper->map_sdk_exception(HpsSdkCodes::$invalidCurrency);
        }
    }

    private function BuildHeader(POSGATEWAY &$processorEngine)
    {
        // Build standard header for messages. 
        $processorEngine->Header->siteId = $this->CONFIG->siteId;
        $processorEngine->Header->deviceId = $this->CONFIG->deviceId;
        $processorEngine->Header->licenseId = $this->CONFIG->licenseId;
        $processorEngine->Header->siteTrace = $this->CONFIG->siteTrace;
        $processorEngine->Header->userName = $this->CONFIG->userName;
        $processorEngine->Header->password = $this->CONFIG->password;
        $processorEngine->Header->developerId = $this->CONFIG->developerId;
        $processorEngine->Header->versionNbr = $this->CONFIG->versionNbr;
        $processorEngine->Header->secretAPIKey = $this->CONFIG->secretAPIKey;
    }

    private function BuildCard(POSGATEWAY &$processorEngine, HpsCardInfo $card)
    {
        $processorEngine->Transaction->Item->CardData = new CardDataType();
        $processorEngine->Transaction->Item->CardData->ManualEntry = new ManualEntry();
        $processorEngine->Transaction->Item->CardData->ManualEntry->CardNbr = $card->CardNbr;
        $processorEngine->Transaction->Item->CardData->ManualEntry->ExpYear = $card->ExpYear;
        $processorEngine->Transaction->Item->CardData->ManualEntry->ExpMonth = $card->ExpMonth;
        $processorEngine->Transaction->Item->CardData->ManualEntry->CVV2 = $card->CVV2;
    }

    private function BuildToken(POSGATEWAY &$processorEngine, HpsToken $token)
    {
        $processorEngine->Transaction->Item->CardData->TokenData = new TokenData();
        $processorEngine->Transaction->Item->CardData->TokenData->TokenValue= $token->TokenValue;
        $processorEngine->Transaction->Item->CardData->TokenData->ExpYear = $token->ExpYear;
        $processorEngine->Transaction->Item->CardData->TokenData->ExpMonth = $token->ExpMonth;
    }

    private function BuildCardHolder(POSGATEWAY &$processorEngine, HpsCardHolderInfo $cardHolder)
    {
        $processorEngine->Transaction->Item->CardHolderData = new CardHolderDataType();
        $processorEngine->Transaction->Item->CardHolderData->CardHolderFirstName = $cardHolder->FirstName; 
        $processorEngine->Transaction->Item->CardHolderData->CardHolderLastName = $cardHolder->LastName;
        $processorEngine->Transaction->Item->CardHolderData->CardHolderAddress = $cardHolder->Address->Address;
        $processorEngine->Transaction->Item->CardHolderData->CardHolderState = $cardHolder->Address->State;
        $processorEngine->Transaction->Item->CardHolderData->CardHolderZip = $cardHolder->Address->Zip;
        $processorEngine->Transaction->Item->CardHolderData->CardHolderCity = $cardHolder->Address->City;
        $processorEngine->Transaction->Item->CardHolderData->CardHolderPhone = $cardHolder->Phone;
        $processorEngine->Transaction->Item->CardHolderData->CardHolderEmail = $cardHolder->Email;
    }

    private function SetEncryption(POSGATEWAY &$processorEngine)
    {
        // Set transaction to use encryption if configured to do so
        if($this->CONFIG->useEncryption)
        {
            $processorEngine->Transaction->Item->CardData->EncryptionData = new EncryptionData();
            $processorEngine->Transaction->Item->CardData->EncryptionData->Version = "01";
        }
    }

    private function DoSoapTransaction($request)
    {
        $soapResponse = NULL;

        if ($this->CONFIG->secretAPIKey != NULL && $this->CONFIG->secretAPIKey != "")
        {
            if (strpos($this->CONFIG->secretAPIKey, '_uat_') !== false)
                $this->CONFIG->URL = "https://posgateway.uat.secureexchange.net/Hps.Exchange.PosGateway/PosGatewayService.asmx?wsdl";
            else if (strpos($this->CONFIG->secretAPIKey, '_cert_') !== false)
                $this->CONFIG->URL = "https://posgateway.cert.secureexchange.net/Hps.Exchange.PosGateway/PosGatewayService.asmx?wsdl";
            else
                $this->CONFIG->URL = "https://posgateway.secureexchange.net/Hps.Exchange.PosGateway/PosGatewayService.asmx?wsdl";
        }

        $options = array('trace' => 1, 'exceptions' => 1);

        if($this->CONFIG->useproxy == true ){
            $proxyOptions = $this->CONFIG->proxyOptions;
            $options = array_merge($options, $proxyOptions);
        }

        $client = new SoapClient($this->CONFIG->URL, $options);
        try
        {
            $soapResponse = $client->__soapCall('DoTransaction', $request);
        }
        catch(Exception $e)
        {
            throw $this->exceptionMapper->map_sdk_exception(HpsSdkCodes::$unableToProcessTransaction,$e->getMessage());
        }
        $response = new HpsTransactionResponse($soapResponse);
        $response->Validate();  // Check for errors from gateway and issuer
        return $response;
    }

    public function Charge($amount, $currency, $cardOrToken, $cardHolder=null, $tokenize=false)
    {
        // Route the charge to appropriate function based on parameters (HpsCardInfo or HpsToken)
        if (get_class($cardOrToken) == "HpsCardInfo") 
        {
            return $this->ChargeManualEntry($amount, $currency, $cardOrToken, $cardHolder, $tokenize);
        }
        else
        {
            return $this->ChargeWithToken($amount, $currency, $cardOrToken, $cardHolder, $tokenize);
        }
        
    }

    public function ChargeManualEntry($amount, $currency, HpsCardInfo $card, HpsCardHolderInfo $cardHolder=null, $tokenize=false)
    {
        $processorEngine = new POSGATEWAY();

        // Simple sanity checks
        $this->CheckAmount($amount);
        $this->CheckCurrency($currency);
        $card->Validate();

        // Define standard header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "CreditSale";
        $processorEngine->Transaction->Item  = new CreditSaleReqBlock1Type();

        //Build Request
        $processorEngine->Transaction->Item->Amt = $amount;
        
        //Configure Encryption
        $this->SetEncryption($processorEngine);

        $processorEngine->Transaction->Item->AllowDup = "Y";
        $processorEngine->Transaction->Item->AllowPartialAuth = "Y";

        // Load card data
        $this->BuildCard($processorEngine, $card);
        if($tokenize)
        {
            $processorEngine->Transaction->Item->CardData->TokenRequest = "Y";
        }

        // If included, define cardHolder
        if ($cardHolder != null)
        {
            $this->BuildCardHolder($processorEngine, $cardHolder);
        }

        //Gather Request
        $request = $processorEngine->getData();
        return $this->DoSoapTransaction($request);
    }

    public function ChargeWithToken($amount, $currency, HpsToken $token, HpsCardHolderInfo $cardHolder=NULL, $tokenize=false )
    {
        $processorEngine = new POSGATEWAY();

        // Simple sanity checks
        $this->CheckAmount($amount);
        $this->CheckCurrency($currency);

        // Define standard header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "CreditSale";
        $processorEngine->Transaction->Item  = new CreditSaleReqBlock1Type();

        //Build Request
        $processorEngine->Transaction->Item->Amt = $amount;
        
        //Configure Encryption
        $this->SetEncryption($processorEngine);

        $processorEngine->Transaction->Item->AllowDup = "Y";
        $processorEngine->Transaction->Item->AllowPartialAuth = "Y";

        // Load token data
        $this->BuildToken($processorEngine, $token);
        if($tokenize)
        {
            $processorEngine->Transaction->Item->CardData->TokenRequest = "Y";
        }

        // If included, define cardHolder
        if ($cardHolder != null)
        {
            $this->BuildCardHolder($processorEngine, $cardHolder);
        }
        
        //Gather Request
        $request = $processorEngine->getData();

        return $this->DoSoapTransaction($request);
    }

    public function Authorize($amount, $currency, $cardOrToken, $cardHolder=null, $tokenize=false)
    {
        // Route the charge to appropriate function based on parameters (HpsCardInfo or HpsToken)
        if (get_class($cardOrToken) == "HpsCardInfo") 
        {
            return $this->AuthorizeManualEntry($amount, $currency, $cardOrToken, $cardHolder, $tokenize);
        }
        else
        {
            return $this->AuthorizeWithToken($amount, $currency, $cardOrToken, $cardHolder, $tokenize);
        }
        
    }

    public function AuthorizeManualEntry($amount, $currency, HpsCardInfo $card, HpsCardHolderInfo $cardHolder=null, $tokenize=false)
    {
        $processorEngine = new POSGATEWAY();

        // Simple sanity checks
        $this->CheckAmount($amount);
        $this->CheckCurrency($currency);

        //Define Header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "CreditAuth";
        $processorEngine->Transaction->Item  = new CreditAuthReqBlock1Type();

        //Build Request
        $processorEngine->Transaction->Item->Amt = $amount;

        
        //Configure Encryption
        $this->SetEncryption($processorEngine);

        $processorEngine->Transaction->Item->AllowDup = "Y";

        // Load card data
        $this->BuildCard($processorEngine, $card);
        if($tokenize)
        {
            $processorEngine->Transaction->Item->CardData->TokenRequest = "Y";
        }

        // If included, define cardHolder
        if ($cardHolder != null)
        {
            $this->BuildCardHolder($processorEngine, $cardHolder);
        }


        //Gather Request
        $request = $processorEngine->getData();

        return $this->DoSoapTransaction($request);
    }

    public function AuthorizeWithToken($amount, $currency, HpsToken $token, HpsCardHolderInfo $cardHolder=null, $tokenize=false)
    {
        $processorEngine = new POSGATEWAY();

        // Simple sanity checks
        $this->CheckAmount($amount);
        $this->CheckCurrency($currency);

        //Define Header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "CreditAuth";
        $processorEngine->Transaction->Item  = new CreditAuthReqBlock1Type();

        //Build Request
        $processorEngine->Transaction->Item->Amt = $amount;

        
        //Configure Encryption
        $this->SetEncryption($processorEngine);

        $processorEngine->Transaction->Item->AllowDup = "Y";

        // Load token data
        $this->BuildToken($processorEngine, $token);

        //$this->BuildCard($processorEngine, $card);
        if($tokenize)
        {
            $processorEngine->Transaction->Item->CardData->TokenRequest = "Y";
        }

        // If included, define cardHolder
        if ($cardHolder != null)
        {
            $this->BuildCardHolder($processorEngine, $cardHolder);
        }


        //Gather Request
        $request = $processorEngine->getData();

        return $this->DoSoapTransaction($request);
    }

    public function ToUtc($dateTime)
    {
        $dateFormat = 'Y-m-d\TH:i:s.00\Z';
        return gmdate($dateFormat, $dateTime->Format('U'));

    }

    public function ListTransactions($startDateTime, $endDateTime, $filter = NULL)
    {
        if($startDateTime > date("Y-m-d H:i:s")){
            throw $this->exceptionMapper->map_sdk_exception(HpsSdkCodes::$invalidStartDate);
        }
        else if($startDateTime > date("Y-m-d H:i:s")){
            throw $this->exceptionMapper->map_sdk_exception(HpsSdkCodes::$invalidEndDate);
        }

        $processorEngine = new POSGATEWAY();

        //Define Header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "FindTransactions";
        $processorEngine->Transaction->Item  = new FindTransactionsReqBlock1Type();
        $processorEngine->Transaction->Item->StartUtcDT = $startDateTime;
        $processorEngine->Transaction->Item->EndUtcDT = $endDateTime;
        if(isset($filter))
            $processorEngine->Transaction->Item->ServiceName = $filter;

        //Configure Encryption
        $this->SetEncryption($processorEngine);

        //Gather Request
        $request = $processorEngine->getData();

        $response = $this->DoSoapTransaction($request);
        if($response->ResponseCode !=0){
            $transactionId = $response->TransactionId;
            $responseCode = $response->ResponseCode;
            $responseText = $response->ResponseMessage;
            throw $this->exceptionMapper->map_gateway_exception($transactionId,$responseCode,$responseText);
        }
        $response = $response->TransactionDetails->AdditionalFields['Transactions'];
        return $response;

    }


    public function Verify(HpsCardInfo $card, HpsCardHolderInfo $cardHolder=null)
    {
        $processorEngine = new POSGATEWAY();
        
        //Define Header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "CreditAccountVerify";
        $processorEngine->Transaction->Item  = new CreditAccountVerifyBlock1Type();

        //Configure Encryption
        $this->SetEncryption($processorEngine);

        // Load card data
        $this->BuildCard($processorEngine, $card);

        // If included, define cardHolder
        if ($cardHolder != null)
        {
            $this->BuildCardHolder($processorEngine, $cardHolder);
        }

        //Gather Request
        $request = $processorEngine->getData();

        $response = $this->DoSoapTransaction($request);
        if($response->ResponseCode != 0){
            throw $this->exceptionMapper->map_gateway_exception($response->TransactionId,$response->ResponseCode,$response->ResponseMessage);
        }
        return $response;
    }

    public function Capture($transactionId, $amount=NULL)
    {
        $processorEngine = new POSGATEWAY();
        
        //Define Header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "CreditAddToBatch";
        $processorEngine->Transaction->Item  = new CreditAddToBatchReqBlock1Type();

        //Configure Encryption
        $this->SetEncryption($processorEngine);

        // Set values, minimum required is a transaction ID
        $processorEngine->Transaction->Item->GatewayTxnId = $transactionId;
        $processorEngine->Transaction->Item->Amt = $amount;

        //Gather Request
        $request = $processorEngine->getData();

        $response = $this->DoSoapTransaction($request);
        if($response->ResponseCode != 0){
            throw $this->exceptionMapper->map_gateway_exception($response->TransactionId,$response->ResponseCode,$response->ResponseMessage);
        }
        return $response;
    }

    public function Reverse($amount, $currency, $cardOrTransactionId)
    {
        // Reverse can take a transactionId or a card, but not both
        // Determine which reverse function to use, card or transactionId
        if (is_int($cardOrTransactionId))
        {
            return $this->ReverseWithTransactionId($amount, $currency, $cardOrTransactionId);
        }
        else
        {
            return $this->ReverseWithCard($amount, $currency, $cardOrTransactionId);
        }

    }


    public function ReverseWithCard($amount, $currency, $card)
    {   
        $response = NULL;
        $this->CheckCurrency($currency);
        $processorEngine = new POSGATEWAY();
        
        //Define Header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "CreditReversal";
        $processorEngine->Transaction->Item  = new CreditReversalReqBlock1Type();

        //Configure Encryption
        $this->SetEncryption($processorEngine);

        //Build Request
        $processorEngine->Transaction->Item->Amt = $amount;
        
        $this->BuildCard($processorEngine, $card);

        $request = $processorEngine->GetData();

        return $this->DoSoapTransaction($request);
    }

    public function ReverseWithTransactionId($amount, $currency, $transactionId)
    {   
        $this->CheckCurrency($currency);
        $response = NULL;
        $processorEngine = new POSGATEWAY();
        
        //Define Header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "CreditReversal";
        $processorEngine->Transaction->Item  = new CreditReversalReqBlock1Type();

        //Configure Encryption
        $this->SetEncryption($processorEngine);

        //Build Request
        $processorEngine->Transaction->Item->Amt = $amount;
        $processorEngine->Transaction->Item->GatewayTxnId = $transactionId;

        $request = $processorEngine->GetData();

        return $this->DoSoapTransaction($request);
    }

    public function Refund($amount, $currency, $cardOrTransactionId, $cardHolder=NULL)
    {
        // Refund can take a transactionId or a card, but not both
        // Determine which refund function to use, card or transactionId
        if (is_int($cardOrTransactionId))
        {
            return $this->RefundWithTransactionId($amount, $currency, $cardOrTransactionId);
        }
        else
        {
            return $this->RefundWithCard($amount, $currency, $cardOrTransactionId, $cardHolder);
        }

    }

    public function RefundWithTransactionId($amount, $currency, $transactionId)
    {   
        $processorEngine = new POSGATEWAY();
        
        // Simple sanity checks
        $this->CheckAmount($amount);
        $this->CheckCurrency($currency);

        //Define Header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "CreditReturn";
        $processorEngine->Transaction->Item  = new CreditReturnReqBlock1Type();

        //Configure Encryption
        $this->SetEncryption($processorEngine);

        //Build Request
        $processorEngine->Transaction->Item->Amt = $amount;
        $processorEngine->Transaction->Item->GatewayTxnId = $transactionId;
        
        $request = $processorEngine->GetData();

        return $this->DoSoapTransaction($request);
    }

    public function RefundWithCard($amount, $currency, HpsCardInfo $card, HpsCardHolderInfo $cardHolder=null)
    {   
        $processorEngine = new POSGATEWAY();
        
        // Simple sanity checks
        $this->CheckAmount($amount);
        $this->CheckCurrency($currency);

        //Define Header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "CreditReturn";
        $processorEngine->Transaction->Item  = new CreditReturnReqBlock1Type();

        //Configure Encryption
        $this->SetEncryption($processorEngine);

        //Build Request
        $processorEngine->Transaction->Item->Amt = $amount;
        
        // Load card data
        $this->BuildCard($processorEngine, $card);

        $processorEngine->Transaction->Item->AllowDup = "Y";

        // If included, define cardHolder
        if ($cardHolder != null)
        {
            $this->BuildCardHolder($processorEngine, $cardHolder);
        }

        $request = $processorEngine->GetData();

        return $this->DoSoapTransaction($request);
    }

    public function BatchClose()
    {
        $processorEngine = new POSGATEWAY();
        //Define Header
        $this->BuildHeader($processorEngine);

        //Define Transaction
        $processorEngine->Transaction->ItemName = "BatchClose";
        $processorEngine->Transaction->Item  = new BatchClose();

        //Build Request
        $request = $processorEngine->GetData();

        return $this->DoSoapTransaction($request);
    }

} // End class HpsChargeService 
    
?>
