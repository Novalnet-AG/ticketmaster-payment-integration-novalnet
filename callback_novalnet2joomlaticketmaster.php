<?php

 /**
  * Novalnet payment method module
  * This module is used for real time processing of
  * Novalnet transaction of customers.
  *
  * Copyright (c) Novalnet AG
  *
  * Released under the GNU General Public License
  * This free contribution made by request.
  * If you have found this script useful a small
  * recommendation as well as a comment on merchant form
  * would be greatly appreciated.
  *
  * Script : callback_novalnet2joomlaticketmaster.php
  *
  */

define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);

if (file_exists(dirname(__FILE__) . '/defines.php')) {
    include_once dirname(__FILE__) . '/defines.php';
}

if (!defined('_JDEFINES')) {
    define('JPATH_BASE', dirname(__FILE__));
    require_once JPATH_BASE.'/includes/defines.php';
}

require_once JPATH_BASE.'/includes/framework.php';
require_once JPATH_CONFIGURATION . '/configuration.php';
$db = JFactory::getDBO();

//Get vendor configuration details
$novalnetPaymentPlugin  = JPluginHelper::getPlugin('rdmedia', 'novalnetpayment');
$novalnetConfigurations = json_decode($novalnetPaymentPlugin->params);

//Variable Settings
$lineBreak      = empty($_SERVER['HTTP_HOST'])? PHP_EOL: '<br />';

// Order State/Status Settings
          /*    Standard Types of Status:
                    1. Pending = 3
                    2. Paid  = 1
                    3. Failed = 0
                    4. Refunded = 2
          */

// ################### Main Prog. ##########################

$aryCaptureParams = array_map('trim', $_REQUEST);
$callbackTestMode = ($novalnetConfigurations->test_mode == '1');
$nnVendorScript   = new NovalnetVendorScript($aryCaptureParams);

$nnVendorParams  = $nnVendorScript->validateCaptureParams($aryCaptureParams);
$order_reference = $nnVendorScript->getOrderByIncrementId($nnVendorParams);
$orderId = $order_reference->orderid;

        $nn_amount = $aryCaptureParams['amount'];
        $callback_query = "SELECT (callback_amount) AS total_amount FROM #__ticketmaster_novalnet_callback WHERE ordercode = '".$orderId."' ORDER BY id DESC LIMIT 1";
        $db->setQuery($callback_query);
        $callback_query_res = $db->loadObject();
        $sum = $callback_query_res->total_amount;
        $totalsum = $sum + $nn_amount;
        $payment_type_level = $nnVendorScript->getPaymentTypeLevel();
        $callback_comments = '';
        $query = "SELECT paid,orderid,ordercode FROM #__ticketmaster_orders where ordercode = '$orderId'";
        $query .= ' LIMIT 1';
        $db->setQuery($query);
        $ordercode   = $db->loadObject();
        $orderCodeID = $ordercode->ordercode;
        $orderPaid   = $ordercode->paid;
        $orderTotal  = $order_reference->amount*100;

	if($payment_type_level == 2 && $nnVendorParams['status'] == '100' && $nnVendorParams['tid_status'] == '100') #CreditEntry payment and Collections available
	{
		//Subscription renewal
		if($nnVendorParams['subs_billing'] == 1)
		{
				#### Step1: THE SUBSCRIPTION IS RENEWED, PAYMENT IS MADE, SO JUST CREATE A NEW ORDER HERE WITHOUT A PAYMENT PROCESS AND SET THE ORDER STATUS AS PAID ####

				#### Step2: THIS IS OPTIONAL: UPDATE THE BOOKING REFERENCE AT NOVALNET WITH YOUR ORDER_NO BY CALLING NOVALNET GATEWAY, IF U WANT THE USER TO USE ORDER_NO AS PAYMENT REFERENCE ###

				#### Step3: ADJUST THE NEW ORDER CONFIRMATION EMAIL TO INFORM THE USER THAT THIS ORDER IS MADE ON SUBSCRIPTION RENEWAL ###

		}
		//Credit entry of INVOICE or PREPAYMENT or ONLINE_TRANSFER_CREDIT or CASHPAYMENT_CREDIT 

			if($sum < $orderTotal)
			{
				$nnInvoiceParams = array (
					'order_no'        => $orderId,
					'total_sum'       => $sum,
					'order_total'     => $orderTotal,
					'callback_amount' => $nn_amount,
					'tid'             => $order_reference->transid
				);
				$nnVendorScript->invoiceExecute($nnInvoiceParams, $nnVendorParams, $totalsum);
			}
			else
			{
				$nnVendorScript->displayMessage("Novalnet callback received. Callback Script executed already. Refer Order : $orderId");
			}
	}
	else if($payment_type_level == 1 && $nnVendorParams['status'] == '100' && $nnVendorParams['tid_status'] == '100' )
	{
		$bookback_chargeback = (in_array($nnVendorParams['payment_type'],array('PAYPAL_BOOKBACK','CREDITCARD_BOOKBACK','CASHPAYMENT_REFUND', 'PRZELEWY24_REFUND'))) ? 'Refund/Bookback' : 'Chargeback';

		$callback_comments = '<br/>Novalnet callback received. '.$bookback_chargeback.' executed successfully for the TID: '.$nnVendorParams['tid_payment'].' amount:'. $nnVendorParams['amount']/100 .' '.$nnVendorParams['currency']. ' on '.date('Y-m-d').' '. date('H:i:s').' The subsequent TID : '.$nnVendorParams['tid'].'<br/>';

		// Update callback comments in order status history table
		$comments = array (
			'order_no'        => $orderId,
			'callback_comments' => $callback_comments
		);
		$nnVendorScript->insertChargebackComments($comments);
		//Send notification mail to Merchant
		$nnVendorScript->displayMessage($callback_comments);
	}
	else if($payment_type_level == 0) //level 0 payments - Type of payment
	{
		if($nnVendorParams['subs_billing'] == 1 && $nnVendorParams['status'] == '100' && in_array($nnVendorParams['tid_status'],array('100','91','98','99','90')))
		{

			if($nnVendorParams['payment_type'] == 'INVOICE_START')
			{
				#### ENTER THE NECESSARY REFERENCE & BANK ACCOUNT DETAILS IN THE NEW ORDER CONFIRMATION EMAIL ####
			}
			else
			{
				##IF PAYMENT MADE ON SUBSCRIPTION RENEWAL ###

			}
		}else if($nnVendorParams['subs_billing'] == 1 && $nnVendorParams['status'] != '100' ){
                //  ADAPT THE PROCESS ON CANCELLATION OF SUBSCRIPTION
        }else if( in_array($nnVendorParams['payment_type'], array('PAYPAL', 'PRZELEWY24')) && $nnVendorParams['status'] == '100' ) {
			if ($nnVendorParams['tid_status'] == '100') {
				global $aryCaptureParams, $order_reference;
				$query = "SELECT paid FROM #__ticketmaster_orders WHERE ordercode = '$orderId'";
				$query .= ' LIMIT 1';
				$db->setQuery($query);
				$status = $db->loadObject();			
							
				if($sum < $orderTotal) {
															
					$callback_comments .= 'Novalnet Callback Script executed successfully for the TID: ' .$nnVendorParams['tid'] .' with amount ' . $nnVendorParams['amount']/100 . ' ' . $nnVendorParams['currency']. ' on '.date('d-m-Y H:i:s');
					$comments = array (
						'order_no'        => $orderId,
						'callback_comments' => $callback_comments
					);
					
					// Update callback comments in order status history table
					$updateOrders = "UPDATE #__ticketmaster_orders SET paid=1 WHERE ordercode = '$orderId'";
					$db->setQuery($updateOrders);
					$db->query();
					
					// Insert the transaction log in callback table.									
					$totalsum = $sum + $aryCaptureParams['amount'];					
					$nnCallbackSql = 'INSERT INTO #__ticketmaster_novalnet_callback SET callback_log = "'.$_SERVER['REQUEST_URI'] .'", reference_tid = "'.$aryCaptureParams['tid'] .'", callback_amount = "' . $totalsum . '", ordercode = "'.$order_reference->orderid.' ", order_id = "'.$order_reference->order_id.'", status = "'.$aryCaptureParams['status'].' ", callback_tid = "'.$aryCaptureParams["tid"].' ", callback_datetime = "'.date('Y-m-d H:i:s').'",payment_type = "'.$order_reference->type.'",order_reference_no = "'.$order_reference->order_id.'-'.$order_reference->orderid.'"';					
					$db->setQuery($nnCallbackSql);
					$nn_result = $db->loadObject();
					
					$nnVendorScript->updateOrderComments($comments);
					$nnVendorScript->displayMessage($callback_comments);
					
				} else{
					$nnVendorScript->displayMessage('Novalnet Callbackscript received. Payment type ( '.$vendor_params['payment_type'].' ) is not applicable for this process!');					
				}
				
			} elseif ($nnVendorParams['payment_type'] == 'PRZELEWY24' && $nnVendorParams['tid_status'] != '86' ) {
					$cancel_reason = !empty($nnVendorParams['status_desc']) ? $nnVendorParams['status_desc'] :
                    !empty($nnVendorParams['status_text']) ? $nnVendorParams['status_text'] :
                    !empty($nnVendorParams['status_message']) ? $nnVendorParams['status_message'] :
                    'Payment was not successful. An error occurred.';
                    $callback_comments = 'The transaction has been canceled due to: '.$cancel_reason;
					$comments = array (
						'order_no'        => $orderId,
						'callback_comments' => $callback_comments
					);
					// Update callback comments in order status history table
					$updateOrders = "UPDATE #__ticketmaster_orders SET paid=0 WHERE ordercode = '$orderId'";
					$db->setQuery($updateOrders);
					$db->query();					
					$nnVendorScript->updateOrderComments($comments);
					$nnVendorScript->displayMessage($callback_comments);
			} else {
					$nnVendorScript->displayMessage('Novalnet Callbackscript received. Order already Paid');
			}
		}
		else
		{			
			$nnVendorScript->displayMessage( ( ( !in_array($nnVendorParams['tid_status'],array('100','91','98','99','90','86'))) || $nnVendorParams['tid_status'] != '100' ) ? 'Novalnet callback received. Status is not valid: Only 100 is allowed' : 'Novalnet Callbackscript received. Payment type ( '.$nnVendorParams['payment_type'].' ) is not applicable for this process!' );
		}
	}

class NovalnetVendorScript {

    /** @Array Type of payment available - Level : 0 */
    protected $aryPayments = array('CREDITCARD', 'INVOICE_START', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_INVOICE_START', 'PAYPAL','ONLINE_TRANSFER', 'IDEAL', 'EPS', 'NOVALTEL_DE', 'PAYSAFECARD','GIROPAY', 'PRZELEWY24', 'CASHPAYMENT');

    /** @Array Type of Charge backs available - Level : 1 */
    protected $aryChargebacks = array('RETURN_DEBIT_SEPA', 'REVERSAL', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK','REFUND_BY_BANK_TRANSFER_EU', 'NOVALTEL_DE_CHARGEBACK', 'PAYPAL_BOOKBACK', 'PRZELEWY24_REFUND', 'CASHPAYMENT_REFUND');

    /** @Array Type of CreditEntry payment and Collections available - Level : 2 */
    protected $aryCollection = array('INVOICE_CREDIT','GUARANTEED_INVOICE','CREDIT_ENTRY_CREDITCARD','CREDIT_ENTRY_SEPA','DEBT_COLLECTION_SEPA','DEBT_COLLECTION_CREDITCARD','NOVALTEL_DE_COLLECTION','NOVALTEL_DE_CB_REVERSAL','ONLINE_TRANSFER_CREDIT', 'CASHPAYMENT_CREDIT');

    /** @IP-ADDRESS Novalnet IP, is a fixed value, DO NOT CHANGE!!!!! */
    protected $ipAllowed = array('195.143.189.210', '195.143.189.214');

    protected $aPaymentTypes = array(
        'novalnet_invoice'      => array('INVOICE_CREDIT', 'INVOICE_START', 'SUBSCRIPTION_STOP'),
        'novalnet_prepayment'   => array('INVOICE_CREDIT', 'INVOICE_START', 'SUBSCRIPTION_STOP'),
        'novalnet_paypal'       => array('PAYPAL', 'PAYPAL_BOOKBACK','SUBSCRIPTION_STOP'),
        'novalnet_banktransfer' => array('ONLINE_TRANSFER','ONLINE_TRANSFER_CREDIT','REFUND_BY_BANK_TRANSFER_EU'),
        'novalnet_cc'           => array('CREDITCARD', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK', 'SUBSCRIPTION_STOP', 'CREDIT_ENTRY_CREDITCARD', 'DEBT_COLLECTION_CREDITCARD'),
        'novalnet_ideal'        => array('IDEAL','REFUND_BY_BANK_TRANSFER_EU'),
        'novalnet_sepa'         => array('DIRECT_DEBIT_SEPA', 'RETURN_DEBIT_SEPA', 'SUBSCRIPTION_STOP', 'DEBT_COLLECTION_SEPA', 'CREDIT_ENTRY_SEPA','REFUND_BY_BANK_TRANSFER_EU'),
        'novalnet_eps'          => array('EPS','REFUND_BY_BANK_TRANSFER_EU'),
        'novalnet_giropay'      => array('GIROPAY','REFUND_BY_BANK_TRANSFER_EU'),
        'novalnet_przelewy24'   => array('PRZELEWY24', 'PRZELEWY24_REFUND'),
        'novalnet_barzahlen'    => array('CASHPAYMENT', 'CASHPAYMENT_REFUND', 'CASHPAYMENT_CREDIT'),
    );

    protected $invoiceAllowed = array('INVOICE_CREDIT' , 'INVOICE_START', 'CASHPAYMENT_CREDIT');
    Protected $paramsRequired = array('vendor_id', 'status', 'amount', 'payment_type', 'tid');

    function __construct($aryCapture = array()) {
		self::validateIp();
        if(empty($aryCapture))
        {
            self::displayMessage('Novalnet callback received. No params passed over!');
        }
    }

	// Validate ip address
    function validateIp(){
        global $callbackTestMode;
		$clientIp  =  $_SERVER['REMOTE_ADDR'];
        if(!in_array($clientIp, $this->ipAllowed) && !$callbackTestMode ) {
            echo "Novalnet callback received. Unauthorised access from the IP ".$clientIp;exit;
        }
    }

    //Validating the Novalnet Required Parameters
    function validateCaptureParams($aryCaptureParams) {
        global $lineBreak;
        if(in_array($aryCaptureParams['payment_type'], array_merge( $this->aryChargebacks, $this->invoiceAllowed))) {
            $this->paramsRequired = array_merge($this->paramsRequired, array('tid_payment'));
        }
        if(!empty($aryCaptureParams))
        {
            $error = '';
            foreach($this->paramsRequired as $value)
            {
                if(empty( $aryCaptureParams[$value]))
                {
                    $error .= 'Required param(' . $value . ') missing!'.$lineBreak;
                }
            }
            if ($error != '')
            {
                self::displayMessage($error);
            }

            if(!in_array($aryCaptureParams['payment_type'], array_merge($this->aryCollection, $this->aryChargebacks, $this->aryPayments)))
            {
                self::displayMessage('Novalnet callback received. Payment type ['.$aryCaptureParams['payment_type'].'] is mismatched!');
            }

            if(!isset($aryCaptureParams['status']) || !is_numeric($aryCaptureParams['status']))
            {
                self::displayMessage('Novalnet callback received. Status ('.$aryCaptureParams['status'].') is not valid.');
            }
            else
            {
                if (in_array($aryCaptureParams['payment_type'], array_merge($this->aryChargebacks, $this->invoiceAllowed)))
                {
                    if (!is_numeric($aryCaptureParams['tid_payment']) || strlen($aryCaptureParams['tid_payment']) != 17)
                    {
                        self::displayMessage('Novalnet callback received. Invalid TID ['. $aryCaptureParams['tid_payment'] . '] for Order.');
                    }
                }
                if (strlen($aryCaptureParams['tid']) != 17 || !is_numeric($aryCaptureParams['tid']))
                {
                    self::displayMessage('Novalnet callback received. TID [' . $aryCaptureParams['tid'] . '] is not valid.');
                }
            }            
            if(in_array($aryCaptureParams['payment_type'], array_merge($this->aryChargebacks, $this->invoiceAllowed)))
            {
                $aryCaptureParams['shop_tid'] = $aryCaptureParams['tid_payment'];
            } elseif($aryCaptureParams['tid'] != '')
            {
                $aryCaptureParams['shop_tid'] = $aryCaptureParams['tid'];
            }
        }
        return $aryCaptureParams;
    }

    function getOrderByIncrementId($aryCaptureValues = array()){
        global $db;
        $tid_payment = $aryCaptureValues['shop_tid'];
        $order_no = (!empty($aryCaptureValues['order_no'])) ? $aryCaptureValues['order_no'] : (!empty($aryCaptureValues['order_id']) ? $aryCaptureValues['order_id'] : '');
        $query = "SELECT orderid, type, amount, transid FROM #__ticketmaster_transactions WHERE transid LIKE '%" . $tid_payment . "%' LIMIT 1";
        try
        {

            $db->setQuery($query);
            $order = $db->loadObject();
            $order_code = $order->orderid;
			$search_query = "SELECT orderid, paid FROM #__ticketmaster_orders WHERE ordercode LIKE '%" . $order_code . "%' LIMIT 1";
            $db->setQuery($search_query);
            $orderdetails = $db->loadObject();

            if(!is_object($order)) {
                if($order_no) {
                    $query = "SELECT * from #__ticketmaster_novalnet_callback WHERE order_reference_no LIKE '%" . $order_no . "%' LIMIT 1";
                    $db->setQuery($query);
                    $update_order = $db->loadObject();
                    $payment_type = $update_order->payment_type;
                    $table_trans_query = "SELECT type, amount, transid FROM #__ticketmaster_transactions WHERE orderid LIKE '%" . $order_no . "%' LIMIT 1";
                    $db->setQuery($table_trans_query);
                    $order = $db->loadObject();

                    if(!is_object($order))
                       $comments = $this->updateOrderDetails($aryCaptureValues, $order->type, $order_no, $tid_payment);
					   self::displayMessage($comments);
                }else{
					self::displayMessage('Transaction mapping failed');
				}
            }
			$order->order_id = $orderdetails->orderid;
            $order->paid = $orderdetails->paid;
        }catch (Exception $e)
        {
            $emailBody .= 'The original order not found in the shop database table;';
            $emailBody .= 'Reason: '.$e->getMessage();
            return false;
        }
        if (!empty($order_no) && $order_no != $order->orderid) {
            self::displayMessage('Novalnet callback received. Order no is not valid');
        }

        if ((!array_key_exists($order->type, $this->aPaymentTypes))|| !in_array($aryCaptureValues['payment_type'], $this->aPaymentTypes[$order->type]))
        {
            self::displayMessage("Novalnet callback received. Payment type [".$aryCaptureValues['payment_type']."] is mismatched!");
        }
    return $order;
    }

	//Get Payment type Level
    function getPaymentTypeLevel()
    {
        global $nnVendorParams;
        if(in_array($nnVendorParams['payment_type'], $this->aryPayments))
        {
            return 0;
        }
        else if(in_array($nnVendorParams['payment_type'], $this->aryChargebacks))
        {
            return 1;
        }
        else if(in_array($nnVendorParams['payment_type'], $this->aryCollection))
        {
            return 2;
        }
        return false;
    }

    function updateOrderDetails($aryCaptureValues, $payment_type, $order_no, $tid_payment){

        global $db;
        $nn_amount = $aryCaptureValues['amount']/100;
        $nn_amount = sprintf('%0.2f', $nn_amount);
		$comments  = 'Novalnet Transaction ID : ' . $aryCaptureValues['shop_tid'] ;
		$comments .= $aryCaptureValues['test_mode'] ? '<br> Test order' : '';

        if($aryCaptureValues['status'] != '100' && !in_array($aryCaptureValues['tid_status'],array('100','91','98','99','90','86'))){
			$comments = (!empty($aryCaptureValues['status_message']) ? $aryCaptureValues['status_message'] : (!empty($aryCaptureValues['status_desc']) ? $aryCaptureValues['status_desc'] : $aryCaptureValues['status_text']));

			//update failure status
			$sql = "UPDATE #__ticketmaster_orders SET paid = '0' WHERE ordercode = '".$order_no."'";
			$db->setQuery($sql);
            $update_status = $db->loadObject();
		}
		foreach($this->aPaymentTypes as $key => $val){
			if(in_array($aryCaptureValues['payment_type'],$val)){
				$paymentType = $key;
			}
		}
        $nn_sql = "INSERT INTO #__ticketmaster_transactions (transid,userid,amount, type, orderid) VALUES ('".$aryCaptureValues['tid']."', '".$aryCaptureValues['customer_no'] ."','".$nn_amount ."', '".$paymentType."', '".$order_no."')";
        $db->setQuery($nn_sql);
        $update_order = $db->loadObject();
        $search_query = 'SELECT ordercode FROM #__ticketmaster_remarks WHERE ordercode = '.$order_no.'';
        $db->setQuery($search_query);
        $data = $db->loadObject();
        if($data->ordercode == $order_no && $tid_payment != ''){
            $sql = "UPDATE #__ticketmaster_remarks SET remarks = CONCAT(remarks, '<br /><br /><br />', '".$comments."'), ordercode = '".$order_no."'WHERE ordercode = '".$order_no."'";
            $db->setQuery($sql);
            $update_comments = $db->loadObject();
        }elseif($tid_payment != ''){
            $sql = "INSERT INTO #__ticketmaster_remarks (remarks, ordercode) VALUES ('".$comments."', '".$order_no."')";
            $db->setQuery($sql);
            $update_comments = $db->loadObject();
        }

		// Insert values in callbck table
        $query = 'SELECT orderid FROM #__ticketmaster_orders where ordercode = "'.$order_no.'"';
        $query .= ' LIMIT 1';
        $db->setQuery($query);
        $result_sql  = $db->loadObject();

		$nnCallbackSql = 'INSERT INTO #__ticketmaster_novalnet_callback SET callback_log = "'.$_SERVER['REQUEST_URI'] .'", reference_tid = "'.$aryCaptureValues['tid'] .'", callback_amount = "' . $nn_amount . '", ordercode = "'.$order_no.' ", order_id = "'.$result_sql->orderid.' ", status = "'.$aryCaptureValues['status'].' ", callback_tid = "'.$aryCaptureValues["tid"].' ", callback_datetime = "'.date('Y-m-d H:i:s').'", payment_type = "'.$paymentType.'"';
        $db->setQuery($nnCallbackSql);
        $nn_result = $db->loadObject();

        return $comments;

    }

	//Update Novalnet Callback execution for INVOICE_CREDIT
    function invoiceExecute($invoiceProcessParams, $aryParams, $sum)
    {
        global $db,$lineBreak, $orderPaid, $orderCodeID,$order_reference;
		$sql = 'SELECT payment_type FROM #__ticketmaster_novalnet_callback WHERE ordercode = '.$invoiceProcessParams['order_no'].'';
        $db->setQuery($sql);
        $data = $db->loadObject();
        $comments='';
        $nn_sql = 'INSERT INTO #__ticketmaster_novalnet_callback SET callback_log = "'.$_SERVER['REQUEST_URI'] .'", reference_tid = "'.$invoiceProcessParams['tid'] .'", callback_amount = "' . $sum . '", ordercode = "'.$invoiceProcessParams['order_no'].' ", order_id = "'.$order_reference->order_id.'", status = "'.$aryParams['status'].' ", callback_tid = "'.$aryParams["tid"].' ", callback_datetime = "'.date('Y-m-d H:i:s').'",payment_type = "'.$data->payment_type.'",order_reference_no = "'.$order_reference->order_id.'-'.$invoiceProcessParams['order_no'].'"';
        $db->setQuery($nn_sql);
        $update_order = $db->loadObject();
        $callback_amt = $invoiceProcessParams['callback_amount']/100;
        $updateSQL = 'UPDATE #__ticketmaster_orders SET paid = 3 WHERE ordercode = ' . $invoiceProcessParams['order_no'] .' and ordercode = ' . $orderCodeID . '';
        $db->setQuery($updateSQL);
        $db->query();
        $comments = $emailBody = "Novalnet Callback Script executed successfully for the TID: " . $invoiceProcessParams['tid']. " with amount ". $callback_amt . " " . $aryParams['currency']. " on " . date('d-m-Y H:i:s'). ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $aryParams['tid'];
        $sql = "UPDATE #__ticketmaster_remarks SET remarks = CONCAT(remarks, '<br />', '".$comments."') WHERE ordercode = '".$invoiceProcessParams['order_no']."'";
        $db->setQuery($sql);
        $db->query();
        if($sum >= $invoiceProcessParams['order_total']){
            $updateSQL = 'UPDATE #__ticketmaster_orders SET paid = 1 WHERE ordercode = ' . $invoiceProcessParams['order_no'] .' and ordercode = ' . $orderCodeID . '';
            $db->setQuery($updateSQL);
            $db->query();
        }
        self::sendNotifyMail($emailBody);
        echo $comments;
    }

    // Update Charge back execution comments in database
    function insertChargebackComments($comments = array())
    {
        global $db;
        // Update callback comments in order status history table
        $remark = "UPDATE #__ticketmaster_remarks SET remarks = CONCAT(remarks, '<br />', '".$comments['callback_comments']."') WHERE ordercode= '".$comments['order_no']."'";
        $db->setQuery($remark);
        $db->query();
        self::sendNotifyMail($comments['callback_comments']);
    }

    function updateOrderComments($comments = array()){
        global $db;
        $remark = "UPDATE #__ticketmaster_remarks SET remarks = CONCAT(remarks, '<br />', '".$comments['callback_comments']."') WHERE ordercode= '".$comments['order_no']."'";
        $db->setQuery($remark);
        $db->query();
        self::sendNotifyMail($comments['callback_comments']);
    }

	/* Send transcation details in mail
	 *
	 * @param emailBody
	 * @return void
	 *
	 * */
    function sendNotifyMail($emailBody) {
		global $db;
		$novalnetPaymentPlugin  = JPluginHelper::getPlugin('rdmedia', 'novalnetpayment');
		$novalnetConfigurations = json_decode($novalnetPaymentPlugin->params);

		## Getting the desired info from the configuration table
		$sql = "SELECT email FROM #__ticketmaster_config WHERE configid = 1 ";
		$db->setQuery($sql);
	    $config = $db->loadObject();

		## Imaport mail functions:
        jimport( 'joomla.mail.mail' );

		$shop_owner_email = $config->email ;
        $fromAddress = $shop_owner_email;
        $toAddress = !empty($novalnetConfigurations->email_to) ? $novalnetConfigurations->email_to : $shop_owner_email;
        $emailToBcc = !empty($novalnetConfigurations->email_bcc) ? $novalnetConfigurations->email_bcc : '';
        $emailSubject = 'Novalnet Callback script notification';
        $sendCallbackMail = $novalnetConfigurations->email_mode ;

		## Compile mailer function:
        $obj = JFactory::getMailer();
        $obj->setSender( $fromAddress);
        $obj->isHTML(true);
        $obj->setBody($emailBody);
        $obj->addRecipient($toAddress);
        $obj->setSubject($emailSubject);
		if (!empty($emailToBcc)){
			$obj->addRecipient($emailToBcc);
		}
		if ($sendCallbackMail == 1) {
			 echo 'Mail sent!';
			 $obj->Send();
		}else{
			echo 'Mail not send!';
		}
        return true;
    }

    /**Display callback message
     *
     * @param $errorMsg
     * @param $exit
     *
     * @return $errorMsg
     * */
    function displayMessage($errorMsg = 'Authentication Failed!', $exit=false) {
        echo $errorMsg;
        exit;
    }
}
?>
