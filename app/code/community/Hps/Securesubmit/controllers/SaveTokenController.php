<?php

class Hps_Securesubmit_saveTokenController extends Mage_core_Controller_Front_Action{
    public function saveTokenAction() {
        if(!$this->getRequest()->isXmlHttpRequest()){
            $result = array("error" => true, "message"=>"Unknown Error");
        }else{

            $params = $this->getRequest()->getParams();

            $customer = Mage::getModel('customer/customer');
            $customer->load($params['customer_id']);

            if(!isset($params['token']) || $params['token'] == ""){
                $result = array("error"=>true,"message"=>"Missing Token Value");
            }elseif(!isset($params['card_type']) || $params['card_type'] == ""){
                $result = array("error"=>true,"message"=>"Missing Card Type");
            }elseif(!isset($params['card_last_four']) || strlen($params['card_last_four']) != 4){
                $result = array("error"=>true,"message"=>"Improper Card Last Four");
            }elseif(!isset($params['card_exp_month']) || strlen($params['card_exp_month']) != 2){
                $result = array("error"=>true,"message"=>"Improper Card Exp Month");
            }elseif(!isset($params['card_exp_year']) || strlen($params['card_exp_year']) != 4){
                $result = array("error"=>true,"message"=>"Improper Card Exp Year");
            }else{

                if($customer->getEmail()){
                    $currentTimestamp = Mage::getModel('core/date')->timestamp(time());
                    $currentDate = date('Y-m-d H:i:s', $currentTimestamp);

                    $storedCard = Mage::getModel('hps_securesubmit/storedcard');
                    $storedCard->setDt($currentDate)
                                ->setCustomerId($params['customer_id'])
                                ->setTokenValue($params['token'])
                                ->setCcType($params['card_type'])
                                ->setCcLast4($params['card_last_four'])
                                ->setCcExpMonth($params['card_exp_month'])
                                ->setCcExpYear($params['card_exp_year']);
                    try{
                        $storedCard->save();
                    }catch (Exception $e){
                        if($e->getCode() == '23000'){
                            $result = array("error"=>true, "message"=>"Customer Not Found  : Card could not be saved.");
                            echo json_encode($result);
                        }
                    }
                    $result = array("error"=>false, "message"=>"Success");
                }else{
                    $result = array("error"=>true, "message"=>"Customer Not Found  : Card could not be saved.");
                }
            }

        }
        echo json_encode($result);
    }
}