<?php

    //Header object for request
    //Contains data elements for transaction routing and authnication
    class HEADER
    {
        private $CONFIG;
        public $siteId;
        public $deviceId;
        public $developerId;
        public $versionNbr;
        public $licenseId;
        public $userName;
        public $password;
        public $siteTrace = "";
        public $secretAPIKey = "";

        private $str;

        public function __construct($config = NULL)
        {
            $this->CONFIG = new HpsServicesConfig($config);
            $this->siteId          = $this->CONFIG->siteId;
            $this->deviceId        = $this->CONFIG->deviceId;
            $this->developerId     = $this->CONFIG->developerId;
            $this->versionNbr      = $this->CONFIG->versionNbr;
            $this->licenseId       = $this->CONFIG->licenseId;
            $this->siteTrace       = $this->CONFIG->siteTrace;
            $this->userName        = $this->CONFIG->userName;
            $this->password        = $this->CONFIG->password;
            $this->secretAPIKey = $this->CONFIG->secretAPIKey;
        }

        public function getData()
        {
            if ($this->secretAPIKey != null && $this->secretAPIKey != "")
            {
                $this->str = array("Header" => array("SecretAPIKey" => $this->secretAPIKey, "VersionNbr" => $this->versionNbr, "DeveloperID" => $this->developerId));
            }
            else
            {
                $this->str = array("Header" => array("SecretAPIKey" => $this->secretAPIKey, "SiteId" => $this->siteId, "DeviceId" => $this->deviceId,
                    "DeveloperID" => $this->developerId, "VersionNbr" => $this->versionNbr,
                    "LicenseId" => $this->licenseId, "SiteTrace" => $this->siteTrace, "UserName" => $this->userName, "Password" => $this->password,
                    ));
            }

            return $this->str;
        }
    };


    //Transaction object for request
    //Contains transaction request data
    class TRANSACTION
    {
        public $ItemName ="";
        public $Item = NULL;

        private $str;

        public function getData()
        {
            $this->str = array("Transaction" => $this->Item->getData());

            return $this->str;
        }
    };


    //POSGATEWAY object for request
    //The request object
    class POSGATEWAY
    {
        private $CONFIG;

        public $Header;
        public $Transaction;
        public $Request;

        private $str;
        private $strArray;

         public function __construct($config = NULL)
        {
                $this->CONFIG = new HpsServicesConfig($config);
                $this->Header = new HEADER($config);  // create a Header object
                $this->Transaction = new TRANSACTION();  // create a Transaction object
            }

        public function getData()
        {

            $this->str = array("PosRequest" => array($this->CONFIG->Version => array_merge($this->Header->getData() , $this->Transaction->getData())));

            return $this->str;
        }

        public function getDataArray()
        {
            $this->strArray = $this->getData();

            return $this->strArray;

        }

        public function getRequest()
        {
            $this->Request = "<?xml version='1.0' encoding='utf-8'?>";
            $this->Request = $this->Request . "<soap:Envelope xmlns:soap='http://schemas.xmlsoap.org/soap/envelope/' ";
            $this->Request = $this->Request . "xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' ";
            $this->Request = $this->Request . "xmlns:xsd='http://www.w3.org/2001/XMLSchema'><soap:Body>";

            $this->Request = $this->Request . $this->getData();

            return $this->Request;
        }

    }
