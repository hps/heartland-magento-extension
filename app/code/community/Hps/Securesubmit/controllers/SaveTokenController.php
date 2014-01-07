<?php

class Hps_Securesubmit_saveTokenController extends Mage_core_Controller_Front_Action{
   public function getTokenDataAction() {
        if(!$this->getRequest()->isXmlHttpRequest()){
            $result = array("error" => true, "message"=>"Unknown Error");
        }else{

            $params = $this->getRequest()->getParams();

            try{
                $storedCard = Mage::getModel('hps_securesubmit/storedcard')
                    ->getCollection()
                    ->addFieldToFilter('token_value',$params['token_value']);
                $card = $storedCard->getData();
                $card = $card[0];
                $result = array("error"=>false,
                    'token' => array(
                        'cc_last4'=>$card['cc_last4'],
                        'cc_exp_month'=>$card['cc_exp_month'],
                        'cc_exp_year'=>$card['cc_exp_year'],
                        'cc_type'=>$card['cc_type']
                    )
                );
            }catch (Exception $e){
                $result = array("error"=>true, "message"=>$e->getMessage());
            }
        }
        echo json_encode($result);
    }
}