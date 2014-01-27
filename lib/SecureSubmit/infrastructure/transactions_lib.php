<?php
    //ManualEntry Class
    /*
     * Class    : ManualEntry
     * Company    : Heartland Payment Systems
     * Author    : Lawrence Butler
     * Method    : getData()
     * Purpose    : Constructs and return a Manually Entered Card Data array
     */
    class ManualEntry
    {
        public $CardNbr;
        public $ExpMonth;
        public $ExpYear;
        public $CardPresent;
        public $ReaderPresent;
        public $CVV2;
        //
        private $str;

        public function getData()
        {

            $this->str = array("ManualEntry" => array("CardNbr" =>  $this->CardNbr, "ExpMonth" => $this->ExpMonth,
                "ExpYear" => $this->ExpYear, "CardPresent" => $this->CardPresent,
                "ReaderPresent" => $this->ReaderPresent, "CVV2" => $this->CVV2));

            foreach($this->str['ManualEntry'] as $key=>$value){
                if($value == null || $value == ""){
                    unset($this->str['ManualEntry'][$key]);
                }
            }

            return $this->str;
        }
    }

    //TrackData Class
    /*
     * Class    : TrackData
     * Company    : Heartland Payment Systems
     * Author    : Lawrence Butler
     * Method    : getData()
     * Purpose    : Constructs and returns Card Track Data array
     */
    class TrackData
    {
        private $TrackData = null;

        public function getTrackData($TrackData) {
            return $this->TrackData;
        }

        public function setTrackData($TrackData) {
            $this->TrackData = $TrackData;
        }
    }

    class TokenData
    {
        public $TokenValue;
        public $ExpMonth;
        public $ExpYear;
        public $str;

        public function getData()
        {

            $this->str = array("TokenData" => array("TokenValue" =>  $this->TokenValue, "ExpMonth" => $this->ExpMonth,
                "ExpYear" => $this->ExpYear));

            foreach($this->str['TokenData'] as $key=>$value){
                if($value == null || $value == ""){
                    unset($this->str['TokenData'][$key]);
                }
            }
            return $this->str;
        }
    }

    //EncryptionData Class
    /*
     * Class    : EncryptionData
     * Company    : Heartland Payment Systems
     * Author    : Lawrence Butler
     * Method    : getData()
     * Purpose    : Constructs and returns Encryption Data array
     */
    class EncryptionData
    {
        public $Version = NULL;
        private $str;

        public function getData()
        {
            $this->str = array("EncryptionData" => array("Version" => $this->Version));

            return $this->str;
        }
    }

    //CardType Class
    class CardDataType
    {
        // Please note that one and only one of the subfields {Trackdata, ManualEntry, TokenData} must be present in CardData.
        public $TrackData;
        public $ManualEntry = NULL;
        public $TokenData = NULL;
        public $EncryptionData = NULL;
        public $TokenRequest = NULL;
        public $str;

        public function getData()
        {
            if($this->ManualEntry == NULL and $this->TokenData == NULL)
            {
                $this->str = array("CardData" => array("TrackData" => $this->TrackData, "method"=>"swipe"));
            }
            elseif($this->ManualEntry != NULL)
            {
                $this->str =  array("CardData" => $this->ManualEntry->getData());
            }
            else
            {
                $this->str =  array("CardData" => $this->TokenData->getData());
            }

            if($this->TokenRequest != NULL)
            {
                $this->str["CardData"] = array_merge($this->str["CardData"], array("TokenRequest" => $this->TokenRequest));
            }

            if($this->EncryptionData != NULL)
            {
                $this->str = array_merge($this->str, $this->EncryptionData->getData());
            }
            return $this->str;
        }
    }

    //GiftCardType Class
    class GiftCardType
    {
        public $TrackData;
        public $CardNbr;
        public $str;

        public function getData()
        {
            if($this->ManualEntry != NULL)
            {
                $this->str =  $this->ManualEntry->getData();
            }
            else
            {
                $this->str = array("CardData" => array("TrackData" => $this->TrackData));
            }
            return $this->str;
        }
    }

    //CardHolderData Class
    class CardHolderDataType
    {
        public $CardHolderFirstName;
        public $CardHolderLastName;
        public $CardHolderAddress;
        public $CardHolderCity;
        public $CardHolderState;
        public $CardHolderZip;
        public $CardHolderPhone;
        public $CardHolderEmail;

        private $str;
        public function getData()
        {

            $this->str = array("CardHolderData" => array("CardHolderFirstName" => $this->CardHolderFirstName, "CardHolderLastName" => $this->CardHolderLastName,
                "CardHolderAddr" => $this->CardHolderAddress, "CardHolderCity" => $this->CardHolderCity, "CardHolderState" => $this->CardHolderState,
                "CardHolderZip" => $this->CardHolderZip, "CardHolderPhone" => $this->CardHolderPhone, "CardHolderEmail" =>  $this->CardHolderEmail));

            foreach($this->str['CardHolderData'] as $key=>$value){
                if($value == null || $value == ""){
                    unset($this->str['CardHolderData'][$key]);
                }
            }

                return $this->str;
        }
    }

    //CPCDataType Class
    class CPCDataType
    {
        public $CardHolderPONbr;
        public $TaxType;
        public $TaxAmt;

        private $str;
        public function getData()
        {

            $this->str = array("CPCDataType" => array("CardHolderPONbr" => $this->CardHolderPONbr, "TaxType" =>  $this->TaxType,
                "TaxAmt" => $this->TaxAmt));

            foreach($this->str['CPCDataType'] as $key=>$value){
                if($value == null || $value == ""){
                    unset($this->str['CPCDataType'][$key]);
                }
            }
                return $this->str;
        }
    }

    //DirectMktDataType Class
    class DirectMktDataType
    {
        public $DirectMktInvoiceNbr;
        public $DirectMktShipMonth;
        public $DirectMktShipDay;

        private $str;
        public function getData()
        {
            $this->str = array("DirectMktDataType" => array("DirectMktInvoiceNbr" =>  $this->DirectMktInvoiceNbr,
                "DirectMktShipMonth" => $this->DirectMktShipMonth, "DirectMktShipDay" => $this->DirectMktShipDay));

            foreach($this->str['DirectMktDataType'] as $key=>$value){
                if($value == null || $value == ""){
                    unset($this->str['DirectMktDataType'][$key]);
                }
            }

            return $this->str;
        }
    }

    //AdditionAmtType Class
    class AdditionAmtType
    {
        public $AmtType;
        public $Amt;

        private $str;
        public function getData($v)
        {
            $this->str = array($v => array("AmtType" => $this->AmtType, "Amt" => $this->Amt));

            foreach($this->str as $key=>$value){
                if($value == null || $value == ""){
                    unset($this->str[$key]);
                }
            }
            return $this->str;
        }
    }

    //AuthRspStatusType
    class AuthRspStatusType
    {
        public $RspCode;
        public $RspText;
        public $AuthCode;
        public $AVSRsltCode;
        public $CVVRsltCode;
        public $CPCInd;
        public $RefNbr;
        public $AvailableBalance;
        public $AuthAmt;

        private $str;
        public function getData()
        {

            //$this->str = array("AuthRspStatusType" => array("RspCode" => $this->RspCode, "RspText" => $this->RspText, "AuthCode" =>  $this->AuthCode,
            //    "AVSRsltCode" => $this->AVSRsltCode, "CVVRsltCode" => $this->CVVRsltCode, "CPCInd" => $this->CPCInd, "RefNbr" =>  $this->RefNbr, "AvailableBalance" =>  $this->AvailableBalance,
            //    "AuthAmt" =>  $this->AuthAmt));

            return $this->str;
        }
    }

    //AutoSubstantiationType
    class AutoSubstantiationType
    {
        public $FirstAdditionalAmt = NULL;
        public $SecondAdditionalAmt = NULL;
        public $ThirdAdditionalAmt = NULL;
        public $FourthAdditionalAmt = NULL;
        public $MerchantVerificationValue;
        public $RealTimeSubstantiation;
        public function __construct() {
            $this->FirstAdditionalAmt = new AdditionalAmtType();
        }

        private $str;
        public function getData()
        {

            $this->str = array("AutoSubstantiationType" => array( array_merge_recursive($this->FirstAdditionalAmt->getData("FirstAdditionalAmt"),
                $this->SecondAdditionalAmt->getData("SecondAdditionalAmt"),  $this->ThirdAdditionalAmt->getData("ThirdAdditionalAmt"),
                $this->FourthAdditionalAmt->getData("FourthAdditionalAmt")), "MerchantVerification" =>  $this->MerchantVerification,
                "RealTimeSubstantiation" => $this->RealTimeSubstantiation));

            foreach($this->str['AutoSubstantiationType'] as $key=>$value){
                if($value == null || $value == ""){
                    unset($this->str['AutoSubstantiationType'][$key]);
                }
            }

            return $this->str;
        }
    }

    //CreditAuthReqBlock1Type
    class CreditAuthReqBlock1Type
    {
        public $CardData = NULL;
        public $Amt = NULL;
        public $GratuityAmtInfo = NULL;
        public $CPCReq = NULL;
        public $CardHolderData;
        public $DirectMktData;
        public $AllowDup;
        public $LodgingData= NULL;
        public $AutoSubstantiation= NULL;
        public $AllowPartialAuth;
        private $str;

        public function __construct() {
            $this->CardData = new CardDataType();
        }
        public function getData() {

                if($this->Amt != null && $this->Amt != ""){
                    $a1 = array_merge($this->CardData->getData(), array("Amt" => $this->Amt));
                }else{
                    $a1 = $this->CardData->getData();
                }

                if($this->GratuityAmtInfo != NULL)
                { $a1 = array_merge($a1,  array("GratuityAmtInfo" => $this->GratuityAmtInfo)); }

                if($this->CPCReq != NULL)
                { $a1 = array_merge($a1,  array("CPCReq",$this->CPCReq)); }

                if($this->CardHolderData != NULL )
                    { $a1 = array_merge($a1,  $this->CardHolderData->getData()); }

                if($this->DirectMktData != NULL )
                    { $a1 = array_merge($a1,  $this->DirectMktData->getData()); }

                if($this->AllowDup != NULL)
                { $a1 = array_merge($a1, array("AllowDup" => $this->AllowDup)); }

                if($this->LodgingData != NULL )
                    { $a1 = array_merge($a1,  $this->LodgingData->getData()); }

                if($this->AutoSubstantiation != NULL )
                    { $a1 = array_merge($a1,  $this->AutoSubstantiation->getData()); }

                if($this->AllowPartialAuth != NULL)
                    {$a1 = array_merge($a1, array("AllowPartialAuth" => $this->AllowPartialAuth)); }

                $a1 = array("Block1" => $a1);
                $a1 = array("CreditAuth" => $a1);

                $this->str = $a1;
                return $this->str;
        }
    }

    //CreditAccountVerifyBlock1Type
    class CreditAccountVerifyBlock1Type
    {
        public $CardData = NULL;
        public $Amt = NULL;
        public $GratuityAmtInfo = NULL;
        public $CPCReq = NULL;
        public $CardHolderData;
        public $DirectMktData;
        public $AllowDup;
        public $LodgingData= NULL;
        public $AutoSubstantiation= NULL;
        public $AllowPartialAuth;
        private $str;

        public function __construct() {
            $this->CardData = new CardDataType();
        }
        public function getData() {

                if($this->Amt != null && $this->Amt != ""){
                    $a1 = array_merge($this->CardData->getData(), array("Amt" => $this->Amt));
                }else{
                    $a1 = $this->CardData->getData();
                }

                if($this->CardHolderData != NULL )
                    { $a1 = array_merge($a1,  $this->CardHolderData->getData()); }

                $a1 = array("Block1" => $a1);
                $a1 = array("CreditAccountVerify" => $a1);

                $this->str = $a1;
                return $this->str;

        }
    }


    //CreditIncrementalAuthReqBlock1Type
    class CreditIncrementalAuthReqBlock1Type
    {
        public $GatewayTxnId;
        public $Amt;
        public $LodgingData;
        private $str;

        public function getData()
        {
            $this->str = array();
            if($this->GatewayTxnId != null || $this->GatewayTxnId != ""){
                $this->str['GatewayTxnid'] = $this->GatewayTxnId;
            }
            if($this->Amt != null || $this->Amt != ""){
                $this->str['Amt'] = $this->Amt;
            }
            $this->str[] = $this->LodgingData->getData();

            //$this->str = array("GatewayTxnId" => $this->GatewayTxnId, "Amt" => $this->Amt,
            //    $this->LodgingData->getData());


            return $this->str;
        }
    }

    //CreditReturnReqBlock1Type
    class CreditReturnReqBlock1Type
    {
        public $GatewayTxnId = NULL;
        public $CardData;
        public $Amt;
        public $CardHolderData;
        public $DirectMktData;
        public $AllowDup;
        private $str;

        public function __construct() {
            $this->CardData = new CardDataType();
        }

        public function getData()
        {

            if($this->GatewayTxnId != NULL)
                $a1 = array_merge(array("GatewayTxnId" => $this->GatewayTxnId), array("Amt" => $this->Amt));
            else
                $a1 = array_merge($this->CardData->getData(), array("Amt" => $this->Amt));
            if($this->CardHolderData != NULL )
                { $a1 = array_merge($a1,  $this->CardHolderData->getData()); }

            if($this->DirectMktData != NULL )
                { $a1 = array_merge($a1,  $this->DirectMktData->getData()); }

            if($this->AllowDup != NULL)
                { $a1 = array_merge($a1, array("AllowDup" => $this->AllowDup)); }

            $a1 = array("Block1" => $a1);
            $a1 = array("CreditReturn" => $a1);

            $this->str = $a1;

            return $this->str;
        }
    }

    //CreditVoidReqType
    class CreditVoidReqType
    {
        public $GatewayTxnId = NULL;
        private $str;

        public function __construct() {}

        public function getData()
        {
            $a1 = array("GatewayTxnId" => $this->GatewayTxnId);
            $a1 = array("CreditVoid" => $a1);
            $this->str = $a1;
            return $this->str;
        }
    }

    //FindTransactionsReqBlock1Type
    class FindTransactionsReqBlock1Type
    {
        public $StartUtcDT = NULL;
        public $EndUtcDT = NULL;
        public $ServiceName = NULL;
        private $str;

        public function __construct() {
        }

        public function getData()
        {

            $a1 = array("StartUtcDT" => $this->StartUtcDT);
            $a1 = array_merge($a1, array("EndUtcDT" => $this->EndUtcDT));

            if($this->ServiceName != NULL )
                { $a1 = array_merge($a1, array("ServiceName" => $this->ServiceName)); }

            $a1 = array("Criteria" => $a1);
            $a1 = array("FindTransactions" => $a1);

            $this->str = $a1;

            return $this->str;
        }
    }

    //CreditAddToBatchReqBlock1Type
    class CreditAddToBatchReqBlock1Type
    {
        public $GatewayTxnId=NULL;
        public $Amt=NULL;
        private $str;

        public function __construct() {
        }

        public function getData()
        {

            $a1 = array("GatewayTxnId" => $this->GatewayTxnId);

            if($this->Amt != NULL && $this->Amt != "")
                { $a1 = array_merge($a1, array("Amt" => $this->Amt)); }

            $a1 = array("CreditAddToBatch" => $a1);

            $this->str = $a1;

            return $this->str;
        }
    }

    //CreditReversalReqBlock1Type
    class CreditReversalReqBlock1Type
    {
        public $GatewayTxnId=NULL;
        public $CardData=NULL;
        public $Amt;
        private $str;

        public function __construct() {
            $this->CardData = new CardDataType();
        }

        public function getData()
        {
            // Credit Reversals can use either GatewayTxnId or CardData, but not both. 
            // See POS Gateway Developer Guide for more details.
            if($this->GatewayTxnId != NULL)
            {
                $a1 = array("GatewayTxnId" => $this->GatewayTxnId);
                $a1 = array_merge($a1, array("Amt" => $this->Amt));
            }
            else
            {
                $a1 = array_merge($this->CardData->getData(), array("Amt" => $this->Amt));
            }
            $a1 = array("Block1" => $a1);
            $a1 = array("CreditReversal" => $a1);

            $this->str = $a1;
            return $this->str;
        }
    }

    //CreditSaleReqBlock1Type
    class CreditSaleReqBlock1Type
    {
        public $CardData = NULL;
        public $Amt;
        public $GratuityAmtInfo;
        public $CPCReq;
        public $CardHolderData = NULL;
        public $DirectMktData = NULL;
        public $AllowDup;
        public $LodgingData = NULL;
        public $AutoSubstantiation = NULL;
        public $AllowPartialAuth;

        private $str = NULL;
        public function __construct() {
            $this->CardData = new CardDataType();
        }
        public function getData()
        {

                $a1 = array_merge($this->CardData->getData(), array("Amt" => $this->Amt));

                if($this->GratuityAmtInfo != NULL)
                { $a1 = array_merge($a1,  array("GratuityAmtInfo" => $this->GratuityAmtInfo)); }

                if($this->CPCReq != NULL)
                { $a1 = array_merge($a1,  array("CPCReq",$this->CPCReq)); }

                if($this->CardHolderData != NULL )
                    { $a1 = array_merge($a1,  $this->CardHolderData->getData()); }

                if($this->DirectMktData != NULL )
                    { $a1 = array_merge($a1,  $this->DirectMktData->getData()); }

                if($this->AllowDup != NULL)
                { $a1 = array_merge($a1, array("AllowDup" => $this->AllowDup)); }

                if($this->LodgingData != NULL )
                    { $a1 = array_merge($a1,  $this->LodgingData->getData()); }

                if($this->AutoSubstantiation != NULL )
                    { $a1 = array_merge($a1,  $this->AutoSubstantiation->getData()); }

                if($this->AllowPartialAuth != NULL)
                    {$a1 = array_merge($a1, array("AllowPartialAuth" => $this->AllowPartialAuth)); }

                $a1 = array("Block1" => $a1);
                $a1 = array("CreditSale" => $a1);


                $this->str = $a1;

            return $this->str;
        }
    }

    //CreditOfflineAuthReqBlock1Type
    class CreditOfflineAuthReqBlock1Type
    {
        public $CardData;
        public $Amt;
        public $GratuityAmtInfo;
        public $CPCReq;
        public $OfflineAuthCode;
        public $CardHolderData;
        public $DirectMktData;
        public $AllowDup;
        public $LodgingData;
        public $AutoSubstantiation;
        private $str;
        public function __construct() {
            $this->CardData = new CardDataType();
        }

        public function getData()
        {

            $a1 = array_merge($this->CardData->getData(), array("Amt" => $this->Amt));

            if($this->GratuityAmtInfo != NULL)
                { $a1 = array_merge($a1,  array("GratuityAmtInfo" => $this->GratuityAmtInfo)); }

            if($this->CPCReq != NULL)
                { $a1 = array_merge($a1,  array("CPCReq",$this->CPCReq)); }

            $a1 = array_merge($a1, array("OfflineAuthCode", $this->OfflineAuthCode));

            if($this->CardHolderData != NULL )
                    { $a1 = array_merge($a1,  $this->CardHolderData->getData()); }

            if($this->DirectMktData != NULL )
                    { $a1 = array_merge($a1,  $this->DirectMktData->getData()); }

            if($this->AllowDup != NULL)
                { $a1 = array_merge($a1, array("AllowDup" => $this->AllowDup)); }

            if($this->LodgingData != NULL )
                    { $a1 = array_merge($a1,  $this->LodgingData->getData()); }

            if($this->AutoSubstantiation != NULL )
                    { $a1 = array_merge($a1,  $this->AutoSubstantiation->getData()); }

            $a1 = array("Block1" => $a1);
            $a1 = array("CreditOfflineAuth" => $a1);


            $this->str = $a1;

            return $this->str;
        }
    }

    //CreditOfflineSaleReqBlock1Type
    class CreditOfflineSaleReqBlock1Type
    {
        public $CardData;
        public $Amt;
        public $GratuityAmtInfo;
        public $CPCReq;
        public $OfflineAuthCode;
        public $CardHolderData;
        public $DirectMktData;
        public $AllowDup;
        public $LodgingData;
        public $AutoSubstantiation;
        private $str;
        public function __construct() {
            $this->CardData = new CardDataType();
        }

        public function getData()
        {

            $a1 = array_merge($this->CardData->getData(), array("Amt" => $this->Amt));

            if($this->GratuityAmtInfo != NULL)
                { $a1 = array_merge($a1,  array("GratuityAmtInfo" => $this->GratuityAmtInfo)); }

            if($this->CPCReq != NULL)
                { $a1 = array_merge($a1,  array("CPCReq",$this->CPCReq)); }

            $a1 = array_merge($a1, array("OfflineAuthCode", $this->OfflineAuthCode));

            if($this->CardHolderData != NULL )
                    { $a1 = array_merge($a1,  $this->CardHolderData->getData()); }

            if($this->DirectMktData != NULL )
                    { $a1 = array_merge($a1,  $this->DirectMktData->getData()); }

            if($this->AllowDup != NULL)
                { $a1 = array_merge($a1, array("AllowDup" => $this->AllowDup)); }

            if($this->LodgingData != NULL )
                    { $a1 = array_merge($a1,  $this->LodgingData->getData()); }

            if($this->AutoSubstantiation != NULL )
                    { $a1 = array_merge($a1,  $this->AutoSubstantiation->getData()); }

            $a1 = array("Block1" => $a1);
            $a1 = array("CreditOfflineSale" => $a1);


            $this->str = $a1;


            return $this->str;
        }
    }

    //DebitReturnReqBlock1Type
    class DebitReturnReqBlock1Type
    {
        public $TrackData;
        public $Amt;
        public $PinBlock;
        public $CardHolderData;
        public $AllowDup;

        private $str;

        public function getData()
        {

            $a1 = array_merge($this->CardData->getData(), array("Amt" => $this->Amt, "PinBlock" => $this->PinBlock));
            if($this->CardHolderData != NULL )
                    { $a1 = array_merge($a1,  $this->CardHolderData->getData()); }

            if($this->AllowDup != NULL)
                { $a1 = array_merge($a1, array("AllowDup" => $this->AllowDup)); }

            $a1 = array("Block1" => $a1);
            $a1 = array("DebitReturn" => $a1);

            return $this->str;
        }
    }

    //DebitReversalReqBlock1Type
    class DebitReversalReqBlock1Type
    {
        public $GatewayTxnId;
        public $TrackData;
        public $Amt;

        private $str;

        public function getData()
        {

            $a1 = array_merge(array("GatewayTxnId" => $this->GatewayTxnId), $this->CardHolderData->getData());

            $a1 = array_merge($a1,  $this->Amt);

            $a1 = array("Block1" => $a1);
            $a1 = array("DebitReversal" => $a1);

            return $this->str;
        }
    }

    //DebitSaleReqBlock1Type
    class DebitSaleReqBlock1Type
    {
        public $TrackData;
        public $Amt;
        public $PinBlock;
        public $CashbackAmtInfo;
        public $CardHolderData;
        public $AllowDup;
        public $LodgingData;

        private $str;

        public function getData()
        {

            $a1 = array("TrackData" => $this->TrackData);
            $a1 = array_merge($a1, array("Amt" => $this->Amt));
            $a1 = array_merge($a1, array("PinBlock" => $this->PinBlock));

            if($this->CashbackAmtInfo != NULL)
                { $a1 = array_merge($a1, array("CashbackAmtInfo" => $this->CashbackAmtInfo)); }

            if($this->CardHolderData != NULL)
                { $a1 = array_merge($a1, $this->CardHolderData->getData()); }

            if($this->AllowDup != NULL)
                { $a1 = array_merge($a1, array("AllowDup" => $this->AllowDup)); }

            if($this->LodgingData != NULL )
                    { $a1 = array_merge($a1,  $this->LodgingData->getData()); }

            $a1 = array("Block1" => $a1);
            $a1 = array("DebitSale" => $a1);
            $this->str = $a1;

            return $this->str;
        }
    }

    //PrePaidAddValueReqBlock1Type
    class PrePaidAddValueReqBlock1Type
    {
        public $CardData = NULL;
        public $Amt = NULL;
        public $CardHolderData;
        public $AllowDup;

        private $str;

        public function __construct() {
            $this->CardData = new CardDataType();
        }
        public function getData() {

                $a1 = array_merge($this->CardData->getData(), array("Amt" => $this->Amt));

                if($this->CardHolderData != NULL )
                    { $a1 = array_merge($a1,  $this->CardHolderData->getData()); }

                if($this->AllowDup != NULL)
                { $a1 = array_merge($a1, array("AllowDup" => $this->AllowDup)); }

                $a1 = array("Block1" => $a1);
                $a1 = array("PrePaidAddValue" => $a1);

                $this->str = $a1;
                return $this->str;
        }
    }

    //PrePaidBalanceInquiryReqBlock1Type
    class PrePaidBalanceInquiryReqBlock1Type
    {
        public $CardData = NULL;
        public $CardHolderData;

        private $str;

        public function __construct() {
            $this->CardData = new CardDataType();
        }
        public function getData() {

                $a1 = $this->CardData->getData();

                if($this->CardHolderData != NULL )
                    { $a1 = array_merge($a1,  $this->CardHolderData->getData()); }

                $a1 = array("Block1" => $a1);
                $a1 = array("PrePaidBalanceInquiry" => $a1);

                $this->str = $a1;
                return $this->str;
        }
    }

    //DebitAddValueReqBlock1Type
    class DebitAddValueReqBlock1Type
    {
        public $TrackData;
        public $Amt;
        public $PinBlock;
        public $CardHolderData;
        public $AllowDup;

        private $str;

        public function getData()
        {
            $a1 = array_merge($this->CardData->getData(), array("Amt" => $this->Amt));
            $a1 = array_merge($a1, array("PinBlock" => $this->PinBlock));

            if($this->CardHolderData != NULL)
                { $a1 = array_merge($a1, $this->CardHolderData->getData()); }

            if($this->AllowDup != NULL)
                { $a1 = array_merge($a1, array("AllowDup" => $this->AllowDup)); }

            $a1 = array("Block1" => $a1);
            $a1 = array("DebitAddValue" => $a1);

            $this->str = $a1;

            return $this->str;
        }
    }

    //ExtraChargesDataType
    class ExtraChargesDataType
    {
        public $Restaurant;
        public $GiftShop;
        public $MiniBar;
        public $Telephone;
        public $Other;
        public $Laundry;

        private $str;

        public function getData()
        {
             if($this->Restaurant != NULL) { $a1 = array("Restaurant" => $this->Restaurant); }
            if($this->GiftShop != NULL) { $a1 = array_merge($a1, array("GiftShop" => $this->GiftShop)); }
            if($this->MiniBar != NULL) { $a1 = array_merge($a1, array("MiniBar" => $this->MiniBar)); }
            if($this->Telephone != NULL) { $a1 = array_merge($a1, array("Telephone" => $this->Telephone)); }
            if($this->Other != NULL) { $a1 = array_merge($a1, array("Other" => $this->Other)); }
            if($this->Laundry != NULL) { $a1 = array_merge($a1, array("Laundry" => $this->Laundry)); }


            $a1 = array("Block1" => $a1);
            $a1 = array("ExtraCharges" => $a1);

            $this->str = $a1;

            $this->str;
        }
    }

    //ReportSearchSiteTraceCriteraType
    class ReportSearchSiteTraceCriteraType
    {
        public $SiteTrace;
        private $str;

        public function getData()
        {
            $this->str = array("ReportSearchSiteTraceCritera" => array("SiteTrace" => $this->SiteTrace));

            return $this->str;
        }
    }

    //SiteTraceCriteria
    class SiteTraceCriteria
    {
        public $ReportSearchSiteTraceCriteraType;
        private $str;

        public function __construct() {
            $this->ReportSearchSiteTraceCriteraType= new ReportSearchSiteTraceCriteraType();
        }

        public function getData()
        {

            $this->str = array("SiteTraceCriteria" => array("ReportSearchSiteTraceCriteraType" =>
                $this->ReportSearchSiteTraceCriteraType->getData()));

            return $this->str;
        }
    }

    //ReportSearchCriteriaType
    class ReportSearchCriteriaType
    {
        public $SiteTraceCriteria;
        private $str;

        public function __construct() {
            $this->SiteTraceCriteria= new SiteTraceCriteria();
        }

        public function getData()
        {

            $this->str = array("ReportSearchCriteria" => array("SiteTraceCriteria" => $this->SiteTraceCriteria->getData()));

            return $this->str;
        }
    }

    //GiftCardActivateReqBlock1Type
    class GiftCardActivateReqBlock1Type
    {
        public $CardData;
        public $Amt;
        private $CardNbr;
        public function __construct() {
            $this->CardData= new CardDataType();
        }

        public function getData()
        {
            $this->str = array("GiftCardActivate" => array("Block1" =>  $this->CardData->getData(), "CardNbr" =>$this->CardNbr));

            return $this->str;
        }
    }

    //GiftCardAddValueReqBlock1Type
    class GiftCardAddValueReqBlock1Type
    {
        public $CardData;
        public $Amt;
        private $str;

        public function __construct() {
            $this->CardData= new CardDataType();
        }

        public function getData()
        {
            $this->str = array("GiftCardActivate" => array("Block1" =>  $this->CardData->getData(), "Amt" =>$this->Amt));

            return $this->str;
        }
    }

    //GiftCardBalanceReqBlock1Type
    class GiftCardBalanceReqBlock1Type
    {
        public $CardData;
        public $str;
        public $Amt;
        public function __construct() {
            $this->CardData= new CardDataType();
        }

        public function getData()
        {
            $this->str = array("GiftCardBalance" => array("Block1" =>  $this->CardData->getData(), "Amt" =>$this->Amt));

            return $this->str;
        }
    }

    //GiftCardDeactivateReqBlock1Type
    class GiftCardDeactivateReqBlock1Type
    {
        public $CardData;
        private $str;
        private $CardNbr;

        public function __construct() {
            $this->CardData= new CardDataType();
        }

        public function getData()
        {
            $this->str = array("GiftCardDeactivate" => array("Block1" => array_merge($this->CardData->getData(),
                array("CardNbr" => $this->CardNbr))));

            return $this->str;
        }
    }

    //GiftCardDataType
    class GiftCardDataType
    {
        public $TrackData;
        public $CardNbr;
        private $str;

        public function getData()
        {
            $this->str = array("GiftCardData" => array_merge($this->TrackData->getData(), array("CardNbr" => $this->CardNbr)));

            return $this->str;
        }
    }

    //GiftCardReplaceReqBlock1Type
    class GiftCardReplaceReqBlock1Type
    {
        public $OldCardData;
        public $NewCarddata;
        private $str;

        public function __construct() {
            $this->OldCardData = new CardDataType();
            $this->NewCardData = new CardDataType();
        }

        public function getData()
        {
            $this->str = array("GiftCardReplace" => array_merge( $this->OldCardData->getData(), $this->NewCardData->getData()));

            return $this->str;
        }
    }

    //GiftCardSaleReqBlock1Type
    class GiftCardSaleReqBlock1Type
    {
        public $CardData;
        public $Amt;
        public $GratuityAmtInfo;
        private $str;

        public function __construct() {
            $this->CardData = new CardDataType();
        }

        public function getData()
        {

            $this->str = array("GiftCardSale" => array_merge( $this->OldCardData->getData(),
                array("Amt" => $this->Amt, "" => $this->GratuityAmtInfo )));

            return $this->str;
        }
    }

    //GiftCardVoidReqBlock1Type
    class GiftCardVoidReqBlock1Type
    {
        public $GatewayTxnId;
        private $str;

        public function getData()
        {
            $this->str = array("GiftCardVoid" => array("GatewayTxnId" => $this->GatewayTxnId));

            $this->str;
        }
    }

    //GiftCardTotalsType
    class GiftCardTotalsType
    {
        public $RspCode;
        public $RspText;
        public $SaleCnt;
        public $SaleAmt;
        public $ActivateCnt;
        public $ActivateAmt;
        public $AddValeCnt;
        public $AddValeAmt;
        public $VoidCnt;
        public $VoidAmt;
        public $DeactivateCnt;
        public $DeactivateAmt;

        private $str;
        public function getData()
        {
            $this->str = "<GiftCardTotalsType>";

            $a1 = array("RspCode" => $this->RspCode);
            $a1 = array_merge($a1, array("RspText" => $this->RspText));
            $a1 = array_merge($a1, array("SaleCnt" => $this->SaleCnt));
            $a1 = array_merge($a1, array("SaleAmt" => $this->SaleAmt));
            $a1 = array_merge($a1, array("ActivateCnt" => $this->ActivateCnt));
            $a1 = array_merge($a1, array("ActivateAmt" => $this->ActivateAmt));
            $a1 = array_merge($a1, array("AddValueCnt" => $this->AddValueCnt));
            $a1 = array_merge($a1, array("AddValueAmt" => $this->AddValueAmt));
            $a1 = array_merge($a1, array("VoidCnt" => $this->VoidCnt));
            $a1 = array_merge($a1, array("VoidAmt" => $this->VoidAmt));
            $a1 = array_merge($a1, array("DeactivateCnt" => $this->DeactivateCnt));
            $a1 = array_merge($a1, array("DeactivateAmt" => $this->DeactivateAmt));

            $this->str = array("GiftCardTotal" => $a1);


            return $this->str;
        }
    }

    //LodgingDataType
    class LodgingDataType
    {
        public $PrestigiousPropertyLimit;
        public $NoShow;
        public $AdvanceDepositType;
        public $LodgingDataEdit;
        public $PeferredCustomer;

        private $str;
        public function getData()
        {

            if($this->PrestigiousPropertyLimit != NULL)
                { $a1 = array("PrestigiousPropertyLimit" => $this->PrestigiousPropertyLimit); }

            if($this->NoShow != NULL)
                { $a1 = array_merge($a1, array("NoShow" => $this->NoShow)); }

            if($this->AdvanceDepositType != NULL)
                { $a1 = array_merge($a1, array("AdvanceDepositType" => $this->AdvanceDepositType)); }

            if($this->LodgingDataEditType != NULL)
                { $a1 = array_merge($a1, $this->LodgingDataEditType->getData()); }

            if($this->AdvanceDepositType != NULL)
                { $a1 = array_merge($a1, array("AdvanceDepositType" => $this->AdvanceDepositType)); }

            $this->str = array("LodgingDataType" => $a1);

            return $this->str;
        }
    }

    //LodgingDataEditType
    class LodgingDataEditType
    {
        public $FolioNumber;
        public $Duration;
        public $CheckInDate;
        public $CheckOutDate;
        public $Rate;
        public $ExtraCharges;
        public $ExtraChargeAmtInfo;

        private $str;
        public function getData()
        {

            if($this->FolioNumber != NULL)
                { $a1 = array("FolioNumber" => $this->FolioNumber); }

            if($this->Duration != NULL)
                { $a1 = array("Duration" => $this->Duration); }

            if($this->CheckInDate != NULL)
                { $a1 = array("CheckInDate" => $this->CheckInDate); }

            if($this->Rate != NULL)
                { $a1 = array("Rate" => $this->Rate); }

            if($this->ExtraCharges != NULL)
                { $a1 = array("ExtraCharges" => $this->ExtraCharges->getData()); }

            if($this->ExtraChargesAmtInfo != NULL)
                { $a1 = array("ExtraChargesAmtInfo" => $this->ExtraChargesAmtInfo); }

            $this->str = array("LodgingDataEditType" => $a1);

            return $this->str;
        }
    }

    //TestCredentials Class
    class TestCredentials
    {
        public function getData()
        {
            return $this->str = array("TestCredentials"=> "");
        }
    }

    //BatchClose Class
    class BatchClose
    {
        public function getData()
        {
            return $this->str = array("BatchClose"=> "");
        }
    }


    //PrestigiousPropertyLimitType
    class PrestigiousPropertyLimitType
    {
        public $NON_PARTICIPATING;
        public $LIMIT_500;
        public $LIMIT_1000;
        public $LIMIT_1500;
        public function __construct() {
            $this->NON_PARTICIPATING = NON_PARTICIPATING;
            $this->LIMIT_500   = LIMIT_500;
            $this->LIMIT_1000  = LIMIT_1000;
            $this->LIMIT_1500  = LIMIT_1500;
        }
    }

?>
