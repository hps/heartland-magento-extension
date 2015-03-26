<?php

class HpsBatchService extends HpsService{
    public function closeBatch(){
        $xml = new DOMDocument();
        $hpsTransaction = $xml->createElement('hps:Transaction');
            $hpsBatchClose = $xml->createElement('hps:BatchClose');
        $hpsTransaction->appendChild($hpsBatchClose);

        $response = $this->doTransaction($hpsTransaction);
        $header = $response->Header;

        if($header->GatewayRspCode != "0"){
            throw $this->exceptionMapper->map_gateway_exception($header->GatewayTxnId,$header->GatewayRspCode,$header->GatewayRspMsg);
        }

        $batchClose = $response->Transaction->BatchClose;
        $result = new HpsBatch();
        $result->id = (isset($batchClose->BatchId) ? (string)$batchClose->BatchId : null);
        $result->sequenceNumber = (isset($batchClose->BatchSeqNbr) ? (string)$batchClose->BatchSeqNbr : null);
        $result->totalAmount = (isset($batchClose->TotalAmt) ? (string)$batchClose->TotalAmt : null);
        $result->transactionCount = (isset($batchClose->TxnCnt) ? (string)$batchClose->TxnCnt : null);

        return $result;
    }
} 