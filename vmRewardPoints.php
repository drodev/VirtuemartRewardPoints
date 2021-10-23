<?php

 /**

 * @author    Dimitris Romanakis

 * @contact   d.romanakis@Outlook.com

 * @copyright Copyright (C) 2014 www.vmPoints.com. All rights reserved.

 * @license   GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html

 */// no direct access

    defined('_JEXEC') or die;

    if (!class_exists('vmPSPlugin'))    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

    class plgVmCustomVmRewardPoints extends vmPSPlugin {

        public function __construct(& $subject, $config){
            parent::__construct($subject, $config);
        }



        public function plgVmOnUpdateOrderPayment($orders,$old_order_status) {


            $status = intval($this->params->get('status'));

            $orderid = $orders->virtuemart_order_id;

            if ($status==1){

                // Order Status Change to
                if ($orderstatus =$orders->order_status==S){

                    $db =& JFactory::getDBO();
                    $query = $db->getQuery(true);
                    $userid = $orders->virtuemart_user_id;
                    $this->prok($userid,$orderid);               
                }
            }



            //when order is cancelled or redund restore points
            if (($orderstatus =$orders->order_status=="X")||($orderstatus =$orders->order_status=="R")){

                $db = JFactory::getDBO();
                $query = $db->getQuery(true);

                $userid = $orders->virtuemart_user_id;

                //get current points of user
                $this->deloncancel($userid,$orderid);
                $query2 = "SELECT coupon_code FROM #__virtuemart_orders WHERE virtuemart_user_id='".$userid."' and  virtuemart_order_id = (select MAX(virtuemart_order_id) from #__virtuemart_orders)"   ;
                $db->setQuery($query2);
                $db->Query();
                $coupon_c = $db->loadResult();
                $del = intval($this->params->get('delon'));

                if(($coupon_c != NULL)&&($del==1)) { 
                    $query= "UPDATE #__virtuemart_coupons SET virtuemart_vendor_id=1 WHERE coupon_code='".$coupon_c."'";
                    $db->setQuery($query);
                    $result = $db->execute(); 
                }
            }
            return;
        }



        public function plgVmConfirmedOrder($cart, $order){
            $db = JFactory::getDBO();
            $user = JFactory::getUser();
            $userid = $user->id;
            
            $query2 = "SELECT coupon_code FROM #__virtuemart_orders WHERE virtuemart_user_id='".$userid."' and  virtuemart_order_id = (select MAX(virtuemart_order_id) from #__virtuemart_orders)"   ;
            $db->setQuery($query2);
            $db->Query();

            $coupon_c = $db->loadResult();
            $del = intval($this->params->get('delon'));

            if(($coupon_c != NULL)&&($del==1)) { 
                $query= "UPDATE #__virtuemart_coupons SET virtuemart_vendor_id=0 WHERE coupon_code='".$coupon_c."'";
                $db->setQuery($query);
                $result = $db->execute(); 
            }

            $status = intval($this->params->get('status'));
            if ($status==0){       
                $query2 = "SELECT virtuemart_order_id FROM #__virtuemart_orders WHERE virtuemart_user_id='".$userid."' and  virtuemart_order_id = (select MAX(virtuemart_order_id) from #__virtuemart_orders)"   ;
                $db->setQuery($query2);
                $db->Query();
                $orderid = $db->loadResult();
                $this->prok($userid,$orderid);
            }
        }



        

        function excode($table,$userid,$orderid){
            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $prefix= JFactory::getDbo()->getPrefix();
            $tablelist= JFactory::getDbo()->getTableList();
            $user = JFactory::getUser();

            //check if user exists on vmpoints table
            $qq = "SELECT count(*)"   . " FROM ".$prefix ."vmpoints"  . " WHERE userid='".$userid."'";
            $db->setQuery($qq, 0);
            $db->query();
            $arethere = $db->loadResult();
            if($arethere==0) { //if not add him!
                $sql = "INSERT INTO ".$prefix . "vmpoints" ." (userid, points) VALUES ( ".$userid.", 0)";
                $db->setQuery($sql);
                $db->Query();
	        }


            //take user's points
            $quer = "SELECT points"   . " FROM ". $table   . " WHERE userid='".$userid."'";
            $db->setQuery($quer, 0);
            $db->query();
            $num_rows = $db->getNumRows();
            $points = $db->loadResult();
            $shiptax = intval($this->params->get('shiptax'));
            if($shiptax == 1) {
                $inctax="order_total";
            }
            else if($shiptax==0) {
                $inctax="order_salesPrice";
            }

            $query2 = "SELECT ".$inctax." FROM ".$prefix . "virtuemart_orders WHERE virtuemart_user_id='".$userid."' and  virtuemart_order_id = '".$orderid."'"   ;
            $db->setQuery($query2);
	        $db->Query();

            $amount = $db->loadResult();
            $ord = "SELECT coupon_discount FROM #__virtuemart_orders WHERE virtuemart_user_id='".$userid."' and  virtuemart_order_id = (select MAX(virtuemart_order_id) from #__virtuemart_orders)"   ;
            $db->setQuery($ord);
            $db->Query();
            $order_dis = $db->loadResult();
		

            //get amount
            $percentage = intval($this->params->get('percentage'));
            //calc amount
            $amount = $amount + $order_dis;
            $amount = ($amount * $percentage)/100;
		      
            //Show message
            if  (strcmp($this->params->get('mini'),"1")==0) {
                $msg = $this->params->get('msg');
                if (strpos($msg,'%points%') !== false) $msg = str_replace("%points%", sprintf('%.2f', round($amount)), $msg);
                JFactory::getApplication()->enqueueMessage($msg, 'message');
            }

            $points = $points + $amount;
            if(strcmp($table,"#__alpha_userpoints")==0) {
                $query->update($db->quoteName('#__alpha_userpoints'))->set('points='.$points.'')->where('userid='.$userid.'');
                $db->setQuery($query);        
		        $db->execute();
            }



            $query1= 'UPDATE #__vmpoints SET points='.$points.' WHERE userid='.$userid;
            $db->setQuery($query1);
            $db->Query();

            if (strcmp($this->params->get('sendmail'),"1")==0) $this->semail($userid,$this->params->get('mytextarea'), $this->params->get('subject'), $points);
           
            try {
                $result = $db->query();
                // Use $db->execute() for Joomla 3.0.
            }



            catch (Exception $e) {

                // Catch the error.

            }



        }



        

        function semail($userid, $body, $subject, $points){

            $db =& JFactory::getDBO();
            $query = $db->getQuery(true);
            if (strpos($body,'%points%') !== false) $body = str_replace("%points%", sprintf('%.2f', $points), $body);
            $mailer = JFactory::getMailer();
            $config = JFactory::getConfig();
            $sender = array(     $config->get( 'config.mailfrom' ),    $config->get( 'config.fromname' ) );
            $mailer->setSender($sender);
            $user = JFactory::getUser();

 	        $query = "SELECT email FROM #__users WHERE id='".$userid."'";
            $db->setQuery($query, 0);
            $recipient = $db->loadResult();
            $mailer->addRecipient($recipient);
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $send = $mailer->Send();
        }



        

        function prok($userid,$orderid){

            $tablelist= JFactory::getDbo()->getTableList();
            $prefix= JFactory::getDbo()->getPrefix();
            $db = JFactory::getDBO();
            $integrate = intval($this->params->get('integrate'));
            $query = $db->getQuery(true);
            $sync = intval($this->params->get('sync'));
     
            if($integrate==0) {
                if(!in_array($prefix . "vmpoints",$tablelist)){

                    //first time
                    $en = "CREATE TABLE ".$prefix. "vmpoints (userid INT(100) PRIMARY KEY, points float)";
                    $db->setQuery($en);
                    $db->Query();
                    $sql = "INSERT INTO ".$prefix . "vmpoints (userid, points) SELECT id, 0 FROM #__users";
                    $db->setQuery($sql);
                    $db->Query();
                }

                if(($sync==2)&&(in_array($prefix . "alpha_userpoints",$tablelist))){
                    $sql = "UPDATE #__vmpoints SET #__vmpoints.points = (SELECT  #__alpha_userpoints.points FROM #__alpha_userpoints WHERE #__vmpoints.userid = #__alpha_userpoints.userid);";
                    $db->setQuery($sql);
                    $db->Query();
                    $sync=0;
                }
                $this->excode("#__vmpoints",$userid,$orderid);

            }
            elseif(($integrate==1)&&(!in_array($prefix . "alpha_userpoints",$tablelist))) JFactory::getApplication()->enqueueMessage("error no aup installed.", 'error');
            elseif(($integrate==1)&&(in_array($prefix . "alpha_userpoints",$tablelist))) {

            if(!in_array($prefix . "vmpoints",$tablelist)) {
                $en = "CREATE TABLE ".$prefix. "vmpoints (userid INT(100) PRIMARY KEY, points float)";
                $db->setQuery($en);
                $db->Query();
                $sql = "INSERT INTO ".$prefix . "vmpoints (userid, points) SELECT userid, points FROM #__alpha_userpoints";
                $db->setQuery($sql);
                $db->Query();
            }
            if($sync==3){
                $sql = "UPDATE #__alpha_userpoints SET #__alpha_userpoints.points = (SELECT  #__vmpoints.points FROM #__vmpoints WHERE #__alpha_userpoints.userid = #__vmpoints.userid);";
                $db->setQuery($sql);
                $db->Query();
                $sync=0;
            }

            $this->excode("#__alpha_userpoints",$userid,$orderid);
            }
        }


        function deloncancel($userid,$orderid){

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $integrate = intval($this->params->get('integrate'));

            if($integrate==0) $table="#__vmpoints";
            elseif($integrate==1) $table="#__alpha_userpoints";

            $query1 = "SELECT points"   . " FROM ".$table   . " WHERE userid='".$userid."'"   ;
            $db->setQuery($query1, 0);
            $points = $db->loadResult();
            $shiptax = intval($this->params->get('shiptax'));
            if($shiptax == 1) $inctax="order_total";
            else if($shiptax==0) $inctax="order_salesPrice";

            $query2 = "SELECT ".$inctax." FROM #__virtuemart_orders"   . " WHERE virtuemart_user_id='".$userid."' and  virtuemart_order_id = '".$orderid."'"   ;
            $db->setQuery($query2, 0);
            $amount = $db->loadResult();
            $ord = "SELECT coupon_discount FROM #__virtuemart_orders WHERE virtuemart_user_id='".$userid."' and  virtuemart_order_id = (select MAX(virtuemart_order_id) from #__virtuemart_orders)"   ;
            $db->setQuery($ord);
            $db->Query();

            $order_dis = $db->loadResult();

            //get percentage
            $percentage = intval($this->params->get('percentage'));
            //calculate amount
            $amount=$amount+$order_dis;
            $amount = ($amount * $percentage)/100;
            $points = $points - $amount;
            $query= 'UPDATE #__vmpoints SET points='.$points.' WHERE userid='.$userid;
            $db->setQuery($query);
            $db->Query();

            if($integrate==1) {
                $query= 'UPDATE #__alpha_userpoints SET points='.$points.' WHERE userid='.$userid;
                $db->setQuery($query);
                $db->Query();
            }
            if (strcmp($this->params->get('sendmail2'),"1")==0) $this->semail($userid,$this->params->get('mytextarea2'), $this->params->get('subject2'), $points);
        }
    }