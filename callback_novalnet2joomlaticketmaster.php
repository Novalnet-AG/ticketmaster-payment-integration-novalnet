<?php
#########################################################
#                                                       #
#  This script is used for real time capturing of       #
#  parameters passed from Novalnet AG after Payment     #
#  processing of customers.                             #
#                                                       #
#  Copyright (c) Novalnet AG                            #
#                                                       #
#  This script is only free to the use for Merchants of #
#  Novalnet AG                                          #
#                                                       #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#                                                       #
#  Please contact sales@novalnet.de for enquiry or Info #
#                                                       #
######################################################### 
/**
 * Novalnet Callback Script for Joomla Ticketmaster shop
 *
 * NOTICE
 *
 * This script is used for real time capturing of parameters passed
 * from Novalnet AG after Payment processing of customers.
 *
 * This script is only free to the use for Merchants of Novalnet AG
 *
 * If you have found this script useful a small recommendation as well
 * as a comment on merchant form would be greatly appreciated.
 *
 * Please contact sales@novalnet.de for enquiry or info
 *
 * ABSTRACT: This script is called from Novalnet, as soon as a payment
 * done for payment methods, e.g. Prepayment, Invoice, PayPal.
 * An email will be sent if an error occurs
 *
 *
 * @category   Novalnet
 * @package    Novalnet
 * @copyright  Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @notice     1. This script must be placed in joomla ticketmaster shop root folder
 *                to avoid rewrite rules (mod_rewrite)
 *             2. You have to adapt the value of all the variables
 *                commented with 'adapt ...'
 *             3. Set $test/$debug to false for live system
 */
 
error_reporting(0);
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

//Variable Settings
$logFile        = 'novalnet_callback_script_'.date('Y-m-d').'.log';
$log            = false;//false|true; adapt
$debug          = false; //false|true; adapt: set to false for go-live
$test           = false; //false|true; adapt: set to false for go-live
$lineBreak      = empty($_SERVER['HTTP_HOST'])? PHP_EOL: '<br />';
$addSubsequentTidToDb = true;//whether to add the new tid to db; adapt if necessary

$aPaymentTypes = array('INVOICE_CREDIT', 'PAYPAL');//adapt here if needed; Options are:
$allowedPayment = array('novalnet_prepayment', 'novalnet_invoice', 'novalnet_paypal');
$invoiceAllowed = array('INVOICE_CREDIT');
$paypalAllowed = array('PAYPAL');

// Order State/Status Settings
          /*    Standard Types of Status:
					1. Pending = 3
					2. Paid  = 1
					3. Failed = 0
					4. Refunded = 2
          */

$orderState  = '1'; //Note: Indicates Payment accepted.

//Security Setting; only this IP is allowed for call back script
$ipAllowed = '195.143.189.210'; //Novalnet IP, is a fixed value, DO NOT CHANGE!!!!!

//Reporting Email Addresses Settings
$shopInfo		= 'Joomla Ticketmaster' . $lineBreak; //manditory;adapt for your need
$mailHost		= 'Ihr Email Server'; //adapt
$mailPort		= 25; //adapt
$emailFromAddr	= ''; //sender email addr., manditory, adapt it
$emailToAddr	= ''; //recipient email addr., manditory, adapt it
$emailSubject	= 'Novalnet Callback Script Access Report'; //adapt if necessary;
$emailBody		= ''; //Email text, adapt
$emailFromName	= ""; // Sender name, adapt
$emailToName	= ""; // Recipient name, adapt

//Parameters Settings
$hParamsRequired = array(
    'vendor_id'		=> '',
    'tid'			=> '',
    'payment_type'	=> '',
    'status'		=> '',
    'amount'		=> '',
    'tid_payment'	=> '');

if(in_array($_REQUEST['payment_type'], $paypalAllowed)) {
	unset($hParamsRequired['tid_payment']);
}

/*formatted url
<Site URL>/callback_novalnet2joomlaticketmaster.php?vendor_id=4&status=100&payment_type=INVOICE_CREDIT&tid_payment=12675800001204435&amount=3778&tid=12675800001204435

Parameters:

tid				Callscript Transaction TID
vendor_id		Merchant ID
status			Successfull payment transaction value
order_no		Existing shops order no which need to be update
payment_type	Types of payment process
tid_payment		Existing shops order transaction id
amount			Customer paid amount in cents
*/

//Test Data Settings
if ($test) {
    //$_REQUEST		= $hParamsTest;
    $emailFromName	= "Novalnet"; // Sender name, adapt
    $emailToName	= "Novalnet"; // Recipient name, adapt
    $emailFromAddr	= ''; //manditory for test; adapt
    $emailToAddr	= ''; //manditory for test; adapt
    $emailSubject	= $emailSubject . ' - TEST'; //adapt
}

// ################### Main Prog. ##########################
try {
    //Check Params
    if (checkIP($_REQUEST)) {
        if (checkParams($_REQUEST)) {
			
            //Get Order ID and Set New Order Status
            if ($orderIncrementId = BasicValidation($_REQUEST)) {
                setOrderStatus($orderIncrementId, $_REQUEST); //and send error mails if any
            }
        }
    }

    if (!$emailBody) {
        $emailBody .= 'Novalnet Callback Script called for StoreId Parameters: ' . print_r($_POST, true) . $lineBreak;
        $emailBody .= 'Novalnet callback succ. ' . $lineBreak;
        $emailBody .= 'Params: ' . print_r($_REQUEST, true) . $lineBreak;
    }
} catch (Exception $e) {
    $emailBody .= "Exception catched: $lineBreak\$e:" . $e->getMessage() . $lineBreak;
}

if ($emailBody) {
    if (!sendEmailJoomlaticketmaster($emailBody)) {
        if ($debug) {
            echo "Mailing failed!" . $lineBreak;
            echo "This mail text should be sent: " . $lineBreak;
            echo $emailBody;
        }
    }
}

// ############## Sub Routines #####################
function sendEmailJoomlaticketmaster($emailBody) {
    global $lineBreak, $debug, $test, $emailFromAddr, $emailToAddr, $emailFromName, $emailToName, $emailSubject, $shopInfo, $mailHost, $mailPort;
    $emailBodyT = str_replace('<br />', PHP_EOL, $emailBody);

    //Send Email
    ini_set('SMTP', $mailHost);
    ini_set('smtp_port', $mailPort);

    header('Content-Type: text/html; charset=iso-8859-1');
    $headers = 'From: ' . $emailFromAddr . "\r\n";
    try {
        if ($debug) {
            echo __FUNCTION__ . ': Sending Email suceeded!' . $lineBreak;
        }
        $sendmail = mail($emailToAddr, $emailSubject, $emailBodyT, $headers);
    }
	catch (Exception $e) {
        if ($debug) { echo 'Email sending failed: ' . $e->getMessage(); }
        return false;
    }
    if ($debug) {
        echo 'This text has been sent:' . $lineBreak . $emailBody;
    }
    return true;
}

function checkParams($request) {
	global $lineBreak, $hParamsRequired, $emailBody, $aPaymentTypes, $db, $allowedPayment, $invoiceAllowed, $paypalAllowed;
	$error = false;
	$emailBody = '';

	if(!$request){
		$emailBody .= 'Novalnet callback received. No params passed over!'.$lineBreak;
		return false;
	}
	if (!isset($request['payment_type']) || ($request['payment_type'] == '') ){
		$emailBody .= "Novalnet callback received. Required Param (payment_type) missing $lineBreak";
		return false;
	}

	if (!in_array($request['payment_type'], $aPaymentTypes)){
		$emailBody .= "Novalnet callback received. Payment type[".$request['payment_type']."] is mismatched!$lineBreak";
		return false;
	}	

	if($hParamsRequired){
		foreach ($hParamsRequired as $k=>$v){
		  if (empty($request[$k])){
			$error = true;
			$emailBody .= 'Novalnet callback received. Required param ('.$k.') missing!'.$lineBreak;
		  }
		}
		if ($error){
		  return false;
		}
	}

	if(!isset($request['status']) or 100 != $request['status']) {
		$emailBody .= 'Novalnet callback received. Status [' . $request['status'] . '] is not valid: Only 100 is allowed.' . "$lineBreak$lineBreak".$lineBreak;
		return false;
	}

	if( (in_array($_REQUEST['payment_type'], $invoiceAllowed)) && strlen($request['tid_payment'])!=17){
		$emailBody .= 'Novalnet callback received. Invalid TID [' . $request['tid_payment'] . '] for Order.' . "$lineBreak$lineBreak".$lineBreak;
		return false;
	}

	if(strlen($request['tid'])!=17){
		if(!in_array($_REQUEST['payment_type'],$paypalAllowed)) {
			$emailBody .= 'Novalnet callback received. New TID is not valid.' . "$lineBreak$lineBreak".$lineBreak;
		} else {
			$emailBody .= 'Novalnet callback received. Invalid TID ['.$request['tid'].'] for Order:'.$request['order_no'].' ' . "$lineBreak$lineBreak".$lineBreak;
		}
		return false;
	}
	return true;
}

function BasicValidation($request){
	global $lineBreak, $emailBody, $debug, $aPaymentTypes, $db, $allowedPayment, $invoiceAllowed, $paypalAllowed;
	$orderDetails = array();
	#$orderNo      = $request['order_no']? $request['order_no']: $request['order_id'];
	$order = getOrderByIncrementId($request);
	if ($debug) {echo'Order Details:<pre>'; print_r($order);echo'</pre>';}

	return $order;// == true
}

function getOrderByIncrementId($request) {
    global $lineBreak, $emailBody, $debug, $aPaymentTypes, $db, $allowedPayment, $invoiceAllowed, $paypalAllowed;
	
	//check amount
	$amount  = $request['amount'];
	$_amount = isset($order_total) ? $order_total * 100 : 0;

	if(!$amount || $amount < 0) {
		$emailBody .= "Novalnet callback received. The requested amount ($amount) must be greater than zero.".$lineBreak.$lineBreak;
		return false;
	}

	/*if (!empty($request['order_no'])){
		return $request['order_no'];
	}elseif(!empty($request['order_id'])){
		return $request['order_id'];
	}*/
	
	$org_tid = (($request['payment_type'] == 'INVOICE_CREDIT') ? $request['tid_payment'] : $request['tid']);
	$tbl_trans = "#__ticketmaster_transactions";
	$tbl_or = "#__ticketmaster_orders";
	$query = "SELECT * from " . $tbl_trans . " WHERE transid LIKE '%" . $org_tid . "%' LIMIT 1";	

	try {
		$db->setQuery($query);
		$db->query();
		$num_rows = @$db->getNumRows();
		$query_res = $db->loadObject();

		if ( $num_rows ) {
			$order_code = $query_res->orderid;
			$payment_method_id = $query_res->type;			
			$order_total = $query_res->amount;
			$search_query = "SELECT * from " . $tbl_or . " WHERE ordercode LIKE '%" . $order_code . "%' LIMIT 1";
			try{
				$db->setQuery($search_query);
				$db->query();
				$num_rows = @$db->getNumRows();
				$search_query_res = $db->loadObject();
				if ( $num_rows ){
					$order_id = $search_query_res->orderid;
					$order_status = $search_query_res->paid;
				}
			} catch (Exception $e) {
				$emailBody .= 'The original order not found in the shop database table (`' . $tbl_or . '`);';
				$emailBody .= 'Reason: ' . $e->getMessage() . $lineBreak . $lineBreak;
				$emailBody .= 'Query : ' . $qry . $lineBreak . $lineBreak;
				return false;
			}	
		}
	} catch (Exception $e) {
		$emailBody .= 'The original order not found in the shop database table (`' . $tbl_trans . '`);';
		$emailBody .= 'Reason: ' . $e->getMessage() . $lineBreak . $lineBreak;
		$emailBody .= 'Query : ' . $qry . $lineBreak . $lineBreak;
		return false;
	}
	
	if ($debug) {echo'Order Details:<pre>';
                	echo "Order Number:".@$order_id."</br>";
               	    echo "Order Total:".@$order_total."</br>" ;
	             echo'</pre>';}

	if( empty($order_id) ){
		$emailBody .= 'Novalnet callback received. Payment TID [' . $org_tid . '] invalid for Order!' . "$lineBreak$lineBreak".$lineBreak;
		return false;
	}
	if( isset($order_id) && $order_id != $request['order_no'] ){
		$emailBody .= "Novalnet callback received. Order no is not valid";
		return false;
	}
		$paymentName = strtolower($payment_method_id);
		
	if( !in_array($paymentName, $allowedPayment) ){
		$emailBody .= "Novalnet callback received. Payment name ($paymentName) is not Paypal/Prepayment/Invoice!$lineBreak$lineBreak";
		return false;
	}

	if(($paymentName == 'novalnet_paypal' && !in_array($request['payment_type'], $paypalAllowed))
		|| ( in_array($paymentName, array('novalnet_invoice', 'novalnet_prepayment')) && !in_array($request['payment_type'], $invoiceAllowed) ) ) {
		$emailBody .= "Novalnet callback received. Payment type (".$request['payment_type'].") is not matched with $paymentName!$lineBreak$lineBreak";
		return false;
	}

    return $order_id; // == true
}

function setOrderStatus($incrementId, $request) {
    global $lineBreak, $emailBody, $orderState, $addSubsequentTidToDb, $aPaymentTypes, $db, $allowedPayment, $invoiceAllowed, $paypalAllowed;

	$org_tid = (($request['payment_type'] == 'INVOICE_CREDIT') ? $request['tid_payment'] : $request['tid']);
	
	$tbl_trans = "#__ticketmaster_transactions";
	$tbl_or = "#__ticketmaster_orders";
	$tbl_remark = "#__ticketmaster_remarks";

	$query = "SELECT paid,orderid,ordercode FROM `$tbl_or` where orderid = '$incrementId' "; 	
	$query .= ' LIMIT 1';

	try {
		$db->setQuery($query);
		$db->query();
		$num_rows = @$db->getNumRows();
		$query_res = $db->loadObject();

		if ( $num_rows ) {
			$order_code = $query_res->ordercode;
			$order_id = $query_res->orderid;
			$orderPaid = $query_res->paid;			
			$sql = "SELECT * from " . $tbl_trans . " WHERE orderid LIKE '%" . $order_code . "%' LIMIT 1";
			
			try {
				$db->setQuery($sql);
				$db->query();
				$num_rows = @$db->getNumRows();
				$sql_res = $db->loadObject();
				
				if ( $num_rows ) {
					$paymentName = $sql_res->type;
					
					if(!in_array($paymentName, $allowedPayment)) {
						$emailBody .= "Novalnet callback received. Payment name ($paymentName) is not PayPal/Prepayment/Invoice!$lineBreak$lineBreak";
						return false;
					}

					if(($paymentName == 'novalnet_paypal' && !in_array($request['payment_type'], $paypalAllowed))
						|| ( in_array($paymentName, array('novalnet_invoice', 'novalnet_prepayment')) && !in_array($request['payment_type'], $invoiceAllowed) ) ) {
						$emailBody .= "Novalnet callback received. Payment type (".$request['payment_type'].") is not matched with $paymentName!$lineBreak$lineBreak";
						return false;
					}
				}else{	
					$emailBody .= "Novalnet callback received. Order no is not valid!$lineBreak$lineBreak";
					return false;
				}
			} catch (Exception $e) {
				$emailBody .= 'The original order not found in the shop database table (`' . $tbl_trans . '`);';
				$emailBody .= 'Reason: ' . $e->getMessage() . $lineBreak . $lineBreak;
				$emailBody .= 'Query : ' . $qry . $lineBreak . $lineBreak;
				return false;
			}
		} 
	} catch (Exception $e) {
		$emailBody .= 'The original order not found in the shop database table (`' . $tbl_or . '`);';
		$emailBody .= 'Reason: ' . $e->getMessage() . $lineBreak . $lineBreak;
		$emailBody .= 'Query : ' . $qry . $lineBreak . $lineBreak;
		return false;
	}
	
	if ($incrementId) {
	  if ($orderPaid != $orderState){
		$comments = 'Novalnet Callback Script executed successfully. The subsequent TID: (' . $request['tid'] . ') on ' . date('Y-m-d H:i:s');
	    if($paymentName == 'novalnet_paypal'){
			$comments = 'Novalnet Callback Script executed successfully on ' . date('Y-m-d H:i:s');
		}
		$updateSQL = 'UPDATE '. $tbl_or .' SET paid = '.$orderState.' WHERE orderid = ' . $incrementId.' and ordercode = ' . $order_code . '';
		$db->setQuery($updateSQL);
		$update_order = $db->loadObject();
	
		$sql = "UPDATE #__ticketmaster_remarks SET remarks = CONCAT(remarks, '<br />', '".$comments."') WHERE ordercode = '".$order_code."'";
		$db->setQuery($sql);
		$update_order = $db->loadObject();
		
		if(in_array($paymentName, array('novalnet_invoice', 'novalnet_prepayment'))){
			$emailBody .= 'Novalnet Callback Script executed successfully. Payment for order id: ' . $incrementId . '. New TID: ('. $request['tid'] . ') on ' . date('Y-m-d H:i:s');
			return true;
		}
		elseif($paymentName == 'novalnet_paypal'){
			$emailBody .= 'Novalnet Callback Script executed successfully. Payment for order id: ' . $incrementId .' on ' . date('Y-m-d H:i:s');
			return true;
		}
	  }else {
		$emailBody .= "Novalnet callback received. Callback Script executed already. Refer Order :".$incrementId;
		return false;
	  }
	} else {
		$emailBody .= "Novalnet Callback received. No order for Increment-ID $incrementId found.";
		return false;
	}
}

function checkIP($request) {
    global $lineBreak, $ipAllowed, $test, $emailBody;
    if ($test) {
        $ipAllowed = getRealIpAddr();
    }
    $callerIp = $_SERVER['REMOTE_ADDR'];
    if (!$test && ($ipAllowed != $callerIp)) {
        $emailBody .= 'Novalnet callback received. Unauthorised access from the IP [' . $callerIp . ']' . $lineBreak . $lineBreak;
        $emailBody .= 'Request Params: ' . print_r($request, true);
        return false;
    }
    return true;
}

function isPublicIP($value) {
    if (!$value || count(explode('.', $value)) != 4)
        return false;
    return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
}

function getRealIpAddr() {
    if (isPublicIP(@$_SERVER['HTTP_X_FORWARDED_FOR']))
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    if ($iplist = explode(',', @$_SERVER['HTTP_X_FORWARDED_FOR'])) {
        if (isPublicIP($iplist[0]))
            return $iplist[0];
    }
    if (isPublicIP(@$_SERVER['HTTP_CLIENT_IP']))
        return $_SERVER['HTTP_CLIENT_IP'];
    if (isPublicIP(@$_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
        return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    if (isPublicIP(@$_SERVER['HTTP_FORWARDED_FOR']))
        return $_SERVER['HTTP_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}
?>
