<?php
// Fill in your configuration details below.

class HpsServicesConfig
{
    public $Version = "Ver1.0";
    public $TimeZone = "America/New_York";

    public $useEncryption = false;

    public $URL= "https://posgateway.uat.secureexchange.net/Hps.Exchange.PosGateway/PosGatewayService.asmx?wsdl";
    public $siteId = "";
    public $deviceId = "";
    public $developerId = "";
    public $versionNbr = "1";
    public $licenseId = "";
    public $userName = "";
    public $password = "";
    public $siteTrace = "";
    public $secretAPIKey = "";
    public $useproxy = "";
    public $proxyOptions = "";
    public $avsResponseErrors;
  
    function __construct($URL=NULL, $siteId=NULL, $deviceId=NULL, $developerId=NULL, $versionNbr=NULL, $licenseId=NULL, $userName=NULL, $password=NULL, $siteTrace=NULL, $secretAPIKey=NULL)
    {
        if ($URL != NULL)
        {
            if(! is_string ($URL) and get_class($URL) == "HpsServicesConfig") 
            {
                // Mimic overloading in case someone passes another HpsServicesConfig to the constructor
                $temp = $URL;
                $this->URL = $temp->URL;
                $this->siteId = $temp->siteId;
                $this->deviceId = $temp->deviceId;
                $this->licenseId = $temp->licenseId;
                $this->siteTrace = $temp->siteTrace;
                $this->versionNbr = $temp->versionNbr;
                $this->secretAPIKey = $temp->secretAPIKey;
                $this->avsResponseErrors = $temp->avsResponseErrors;

                if ($temp->userName != NULL && $temp->userName != "")
                    $this->userName = $temp->userName;

                if ($temp->password != NULL && $temp->password != "")
                    $this->password = $temp->password;

                if ($temp->developerId != NULL && $temp->developerId != "")
                    $this->developerId = $temp->developerId;

                return;
            }
            else
            {
                $this->URL = $URL;
            }
        }

        if ($siteId != NULL)
            $this->siteId = $siteId;
        if ($deviceId != NULL)
            $this->deviceId = $deviceId;
        if ($licenseId != NULL)
            $this->licenseId = $licenseId;
        if ($userName != NULL && $userName != "")
            $this->userName = $userName;
        if ($password != NULL && $password != "")
            $this->password = $password;
        if ($siteTrace != NULL)
            $this->siteTrace = $siteTrace;
        if ($developerId != NULL && $developerId != "")
            $this->developerId = $developerId;
        if ($versionNbr != NULL)
            $this->versionNbr = $versionNbr;
        if ($secretAPIKey != NULL)
          $this->secretAPIKey = $secretAPIKey;

        $this->avsResponseErrors = array(
          "B" => "Addr Match, Zip Not Verified",
          "C" => "Addr and Zip Mismatch",
          "D" => "Addr and Zip Match Intl",
          "G" => "Addr Not Verified - Intl",
          "I" => "AVS Not Verified -- Intl",
          "N" => "Addr and Zip No Match",
          "P" => "Addr and Zip Not Verified",
          "R" => "Retry - No Response",
          "S" => "AVS Not Supported",
          "U" => "AVS Not Supported",
        );
    }
}
