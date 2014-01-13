<?php

class WebService_PosGatewayService_PosGatewayInterface extends SOAP_Client {
    function WebService_PosGatewayService_PosGatewayInterface($path = 'https://posgateway.cert.secureexchange.net/Hps.Exchange.PosGateway/PosGatewayService.asmx')
    {
        $this->SOAP_Client($path, 0);
    }
    function &DoTransaction($PosRequest)
    {
        $PosRequest = new SOAP_Value('{http://Hps.Exchange.PosGateway}PosRequest', '', $PosRequest);
        $result = $this->call('DoTransaction',
            $v = array('PosRequest' => $PosRequest),
            array('namespace' => 'http://Hps.Exchange.PosGateway',
                'soapaction' => '',
                'style' => 'document',
                'use' => 'literal'));
        return $result;
    }

} 