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
  * Script : novalnetUtilities.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');

class novalnetUtilities {
    /**
	 * Validate the merchant details
	 * @param none
	 * @return none
	 */
    function doValidation($thisObject) {
		$novalnetPaymentPlugin = JPluginHelper::getPlugin('rdmedia', 'novalnetpayment');
        $novalnetConfigurations = json_decode($novalnetPaymentPlugin->params);
		$lang = JFactory::getLanguage();
        $lang->load('plg_rdmedia_novalnetcc', JPATH_ADMINISTRATOR);
        $merchant_id = trim($novalnetConfigurations->merchant_id);
        $product_id = trim($novalnetConfigurations->product_id);
        $merchant_authcode = trim($novalnetConfigurations->merchant_authcode);
        $tariff_id = trim($novalnetConfigurations->tariff_id);
	    if(!self::isDigits($merchant_id) || !self::isDigits($product_id) || empty($merchant_authcode) || !self::isDigits($tariff_id)){
            JError::raiseWarning(500, JText::_('PLG_NOVALNETCC_MERCHANT_ERROR'));
            return false;
	    }
        if(empty($thisObject->emailaddress) || empty($thisObject->firstname)){
            JError::raiseWarning(500, JText::_('PLG_NOVALNETCC_CUST_NAME_EMAIL_ERR'));
            return false;
        }
        return true;
	}

	/**
     * Validate the number
     * @param string $element
     * @return string
     */
    function isDigits($element) {
        return preg_match("/^\d+$/", $element);
    }

	/**
	 * To get common parameter
	 * @param $thisObject
	 * @return void
	 */
    function getCommonvalues(&$thisObject){
		$lang = JFactory::getLanguage();
		$utilityObject = new novalnetUtilities;
        $lang->load('plg_rdmedia_'.$thisObject->id, JPATH_ADMINISTRATOR);
        ## Getting the global DB session
        $session = JFactory::getSession();
        ## Gettig the orderid if there is one.
        $thisObject->ordercode = $session->get('ordercode');
        $thisObject->language = strtoupper(substr($lang->getTag(),0,2));
		## Including required paths to calculator.
        include_once( JPATH_SITE.DS.'components'.DS.'com_ticketmaster'.DS.'assets'.DS.'helpers'.DS.'get.amount.php' );
        ## Getting the amounts for this order.
        $thisObject->amount = sprintf('%0.2f', _getAmount($thisObject->ordercode)) * 100;
        $thisObject->fees   = _getFees($thisObject->ordercode);

        $db = JFactory::getDBO();
        $query = $db->getQuery(true)
            ->select('userid, orderid')
            ->from('#__ticketmaster_orders')
            ->where('ordercode="'.$thisObject->ordercode.'"');
        $db->setQuery($query);
        $ids = $db->loadObject();

        $query->clear()
            ->select('firstname, name, userid, address, city, zipcode, phonenumber, emailaddress, country_id')
            ->from('#__ticketmaster_clients')
            ->where('userid="'.$ids->userid.'"');
        $db->setQuery($query);
        $donatordetails = $db->loadObject();
        $query->clear()
            ->select('country_2_code')
            ->from('#__ticketmaster_country')
            ->where('country_id="'.$donatordetails->country_id.'"');
        $db->setQuery($query);
        $country = $db->loadObject();
        $thisObject->country = $country->country_2_code;
        ##System details
        $query->clear()
            ->select('manifest_cache')
            ->from('#__extensions')
            ->where('name="com_ticketmaster"');
        $db->setQuery($query);
        $tm_version = json_decode( $db->loadResult(), true );
        $thisObject->ticketmaster_version = $tm_version['version'];

        $thisObject->orderid = $ids->orderid;
        $utilityObject->_customerDetails($donatordetails, $thisObject);
        ## Return URLS to your website after processing the order.
        $path = JURI::root().'index.php?option=com_ticketmaster&view=transaction&payment_type='.$thisObject->id;
        $thisObject->return_url = $path.'&Itemid='.$thisObject->ordercode;
        $thisObject->cancel_url = $path.'_failed&Itemid='.$thisObject->ordercode;
        $thisObject->form_url   = $path.'_form&Itemid='.$thisObject->ordercode;
	}

	/* Get cofiguration detail
	 *
	 * @param void
	 * @return object
	 * */
	 function configrationDetails(){
		$novalnetPaymentPlugin = JPluginHelper::getPlugin('rdmedia', 'novalnetpayment');
        return json_decode($novalnetPaymentPlugin->params);
	 }

	/* Get payment parameter
	 *
	 * @param $paymentName
	 * @param $thisObject
	 * @return array
	 * */
	function formPaymentParameters($paymentName, $thisObject){
        $novalnetConfigurations = self::configrationDetails();
        // Get remote ip address
        $ip = self::_getRealIpAddr();

        if((isset($thisObject->firstname) && empty($thisObject->name)) || (isset($thisObject->name) && empty($thisObject->firstname))){
            if(empty($thisObject->name)) {
                $name = preg_split("/[\s]+/", $thisObject->firstname, 2);
            } else if(empty($thisObject->firstname)){
                $name = preg_split("/[\s]+/", $thisObject->name, 2);
            }
            $thisObject->firstname = $name[0];
            if(isset($name[1])) {
                $thisObject->name = $name[1];
            } else {
                $thisObject->name = $name[0];
            }
        }

		$reference1 = trim(strip_tags($thisObject->trans_reference_2));
		$reference2 = trim(strip_tags($thisObject->trans_reference_1));

        $paymentParameters = array_merge(array(
			'test_mode'        => $thisObject->params['test_mode'],
            'amount'           => $thisObject->amount,
            'order_id'         => $thisObject->ordercode,
            'order_no'         => $thisObject->ordercode,
            'first_name'       => $thisObject->firstname,
            'last_name'        => $thisObject->name,
            'email'            => $thisObject->emailaddress,
            'gender'           => 'u',
            'street'           => $thisObject->address,
            'search_in_street' => '1',
            'city'             => $thisObject->city,
            'zip'              => $thisObject->zipcode,
            'tel'              => $thisObject->phonenumber,
            'country_code'     => $thisObject->country,
            'country'          => $thisObject->country,
            'language'         => $thisObject->language,
            'lang'             => $thisObject->language,
            'currency'         => $thisObject->currency,
            "remote_ip"        => ( $ip == '::1' || filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) ? '127.0.0.1' : $ip,
            'customer_no'      => $thisObject->userid,
            'key'              => $thisObject->key,
            'system_name'      => 'joomla-ticketmaster',
            'system_version'   => JVERSION.'-'.$thisObject->ticketmaster_version.'-NN2.0.0',
            'system_url'       => JURI::root(),
			"system_ip"        => ( $_SERVER['SERVER_ADDR'] == '::1' || filter_var( $_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) ? '127.0.0.1' : $_SERVER['SERVER_ADDR'],
        ),array_filter(array(
            'notify_url'       => !empty($novalnetConfigurations->notification_url) ? $novalnetConfigurations->notification_url : '',
            'input1'		   => !empty($reference1) ? 'Reference1' : '',
            'inputval1' 	   => !empty($reference1) ? $reference1 : '',
            'input2'		   => !empty($reference2) ? 'Reference2' : '',
            'inputval2' 	   => !empty($reference2) ? $reference2 : '',
            'referrer_id'      => self::isDigits($novalnetConfigurations->referrer_id) ? $novalnetConfigurations->referrer_id : '',
            'on_hold'		  => (!empty($novalnetConfigurations->manual_limit) && self::isDigits($novalnetConfigurations->manual_limit) && $thisObject->amount >= $novalnetConfigurations->manual_limit && in_array($paymentName,array('novalnetinvoice','novalnetcc','novalnetsepa'))) ? 1 : '',
        )));
		// Check sepa due date
        if($paymentName == 'novalnetsepa'){
			if (!empty($thisObject->sepa_normal_due_date) && $thisObject->sepa_normal_due_date >= 7) {
				$sepa_due_date = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') + $thisObject->sepa_normal_due_date, date('Y')));
			} else {
				$sepa_due_date = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') + 7, date('Y')));
			}
			$paymentParameters['sepa_due_date'] = $sepa_due_date;
		}
		// Check cashpayment slip due date
		if($paymentName == 'novalnetbarzahlen') {			
			$slip_due_date = trim($thisObject->slip_due_date);
			$slip_due_date = (is_numeric($slip_due_date) && $slip_due_date >= 0) ? $slip_due_date : 14;
			$paymentParameters['cashpayment_due_date'] = date('Y-m-d', strtotime('+'.$slip_due_date.' days'));			
		}
        if($paymentName == 'novalnetcc'){
			$paymentParameters = array_merge( $paymentParameters, array(
			'vendor_id'           => trim($novalnetConfigurations->merchant_id),
			'product_id'          => trim($novalnetConfigurations->product_id),
			'tariff_id'           => trim($novalnetConfigurations->tariff_id),
			'vendor_authcode'     => trim($novalnetConfigurations->merchant_authcode),
			'implementation'      => 'PHP_PCI',
            ));
			$encodeParams = array(
                'vendor_authcode',
                'product_id',
                'tariff_id',
                'amount',
                'test_mode',
                'uniqid'
            );
		}else{
		    $paymentParameters = array_merge( $paymentParameters, array(
				'vendor'           => trim($novalnetConfigurations->merchant_id),
				'product'          => trim($novalnetConfigurations->product_id),
				'tariff'           => trim($novalnetConfigurations->tariff_id),
				'auth_code'        => trim($novalnetConfigurations->merchant_authcode),
            ));

			$encodeParams = array(
				'auth_code',
				'product',
				'tariff',
				'amount',
				'test_mode',
				'uniqid'
			);
		}
		if(in_array( $paymentName, array( 'novalnetbanktransfer', 'novalnetideal', 'novalnetpaypal', 'novalneteps', 'novalnetgiropay', 'novalnetprzelewy24' ))){
			$paymentParameters['implementation'] = 'PHP';
		}
		if( in_array( $paymentName, array( 'novalnetbanktransfer', 'novalnetcc', 'novalnetideal', 'novalnetpaypal', 'novalneteps', 'novalnetgiropay', 'novalnetprzelewy24' ) ) ) {
            $paymentParameters = array_merge( $paymentParameters, array(
                'return_url'          => $thisObject->return_url,
                'return_method'       => 'POST',
                'error_return_url'    => $thisObject->cancel_url,
                'error_return_method' => 'POST',
                'user_variable_0'     => JURI::root(),
                'uniqid'   			  => uniqid(),
            ) );
			self::doEncode( $paymentParameters, $encodeParams );
            $paymentParameters['hash'] = self::generateHashValue( $paymentParameters );
        }
        if(in_array($paymentName, array('novalnetinvoice', 'novalnetprepayment'))){
            if($paymentName == 'novalnetinvoice')
                if(preg_match('/^\d+$/',$thisObject->payment_period)){
                    $paymentParameters['due_date'] = self::_getDueDate($thisObject->payment_period);
                }
            $paymentParameters['invoice_type']  = ($paymentName == 'novalnetinvoice') ? 'INVOICE' : 'PREPAYMENT';
            $paymentParameters['invoice_ref']  = 'BNR-' . $novalnetConfigurations->product_id .'-' . $thisObject->ordercode;
        }        
        return (array_map("trim", $paymentParameters));
    }

	/**
     * Get customer details
     * @param array $customerInfo
     * @return none
     */
    public static function _customerDetails($customerInfo, &$thisObject) {
        unset($customerInfo->country_id);
        foreach ($customerInfo as $key => $value) {
            $thisObject->{$key} = $value;
        }
    }

	/**
     * Calculate due date
     * @param string $days
     * @return string
     */
    function _getDueDate($days=0) {
        return $days == '' ? NULL : date('Y-m-d', strtotime('+' . max(0, intval($days)) . ' days'));
    }

	/**
     * Get client Ip address
     * @param none
     * @return none
     */
    function _getRealIpAddr() {

        if(self::isPublicIP(isset($_SERVER['HTTP_X_FORWARDED_FOR'])? $_SERVER['HTTP_X_FORWARDED_FOR'] :'') )
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        if($iplist=explode(',', isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '')) {
            if(self::isPublicIP($iplist[0]))
                return $iplist[0];
        }
        if (self::isPublicIP(isset($_SERVER['HTTP_CLIENT_IP']) ?$_SERVER['HTTP_CLIENT_IP'] : ''))
            return $_SERVER['HTTP_CLIENT_IP'];
        if (self::isPublicIP(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) ?$_SERVER['HTTP_X_CLUSTER_CLIENT_IP'] :''))
            return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        if (self::isPublicIP(isset($_SERVER['HTTP_FORWARDED_FOR']) ?$_SERVER['HTTP_FORWARDED_FOR'] : '' ))
            return $_SERVER['HTTP_FORWARDED_FOR'];
        return $_SERVER['REMOTE_ADDR'];
    }
	/** Get redirect url
	 *
	 * return $return_url
	 * */
	public function redirectUrl() {
        $url 		= self::getBetweenSring($_SESSION['request_uri'], 'index.php/', '/payment');
        $return_url = JURI::root() . 'index.php/' . $url . '/payment';
        $app        = JFactory::getApplication();
        $app->redirect($return_url);
    }

	function getBetweenSring($content, $start, $end) {
        $explode_value = explode($start, $content);
        $explode_value = explode($end, $explode_value[1]);
        return $explode_value[0];
    }
    /**
     * To check the public ip
     * @param string $value
     * @return boolean
     */
    function isPublicIP($value) {
        return (count(explode('.', $value)) == 4 && !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value));
    }

    /**
     * To encode the given data
     * @param array $data
     * @return none
     */
    public function doEncode( &$data, $encoded_params )
    {
		$novalnetConfigurations = self::configrationDetails();
        if (!function_exists('base64_encode') or !function_exists('pack') or !function_exists('crc32')) {
            return 'Error: func n/a';
        }
        foreach ( $encoded_params as $value ) {
            $encoded_data = $data[$value];
            try {
                $crc          = sprintf( '%u', crc32( $encoded_data ) );
                $encoded_data = $crc . "|" . $encoded_data;
                $encoded_data = bin2hex( $encoded_data . trim($novalnetConfigurations->password));
                $encoded_data = strrev( base64_encode( $encoded_data ) );
            }
            catch ( Exception $e ) {
                echo ( 'Error: ' . $e );
            }
            $data[$value] = $encoded_data;
        }
    }

    /**
     * To decode the given data
     * @param string $data
     * @return none
     */
    public function doDecode($data) {
		$novalnetConfigurations = self::configrationDetails();
        if (!function_exists('base64_decode') or !function_exists('pack') or !function_exists('crc32')) {
            return 'Error: function not applicable';
        }
        try {
            $data = base64_decode(strrev($data));
            $data = pack("H" . strlen($data), $data);
            $data = substr($data, 0, stripos($data, trim($novalnetConfigurations->password)));
            $pos = strpos($data, "|");
            if ($pos === false) {
                return("Error: CKSum not found!");
            }
            $crc = substr($data, 0, $pos);
            $value = trim(substr($data, $pos + 1));
            if ($crc != sprintf('%u', crc32($value))) {
                return("Error; CKSum invalid!");
            }
            return $value;
        } catch (Exception $e) {
            echo('Error: ' . $e);
        }
    }

    /**
     * To generate hash value
     *
     * @package     Novalnet payment package
     * @param       $data
     * @param       $this_obj
     * @return      string
     */
    public function generateHashValue( $data )
    {
		$novalnetConfigurations = self::configrationDetails();
		if(isset($data['vendor_authcode'])){
			return md5( $data['vendor_authcode'] . $data['product_id'] . $data['tariff_id'] . $data['amount'] . $data['test_mode'] . $data['uniqid'] . strrev( trim($novalnetConfigurations->password) ) );
		}
		return md5( $data['auth_code'] . $data['product'] . $data['tariff'] . $data['amount'] . $data['test_mode'] . $data['uniqid'] . strrev( trim($novalnetConfigurations->password) ) );
    }

    /**
     * This is a general function to safely open a connection to a server,
     * post data when needed and read the result.
     * @param string $url
     * @param string $form
     * @return mixed
     */
    function performHttpsRequest($url, $form) {
		$novalnetconfiguration = self::configrationDetails();

		//  Novalnet Proxy & timeout configuration
        $proxy    = trim($novalnetconfiguration->proxy);
        $time_out = trim($novalnetconfiguration->gateway_time);

        $curl_process           	= curl_init( $url );
        curl_setopt( $curl_process, CURLOPT_POST, 1 );
        curl_setopt( $curl_process, CURLOPT_POSTFIELDS, $form );
        curl_setopt( $curl_process, CURLOPT_FOLLOWLOCATION, 0 );
        curl_setopt( $curl_process, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $curl_process, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $curl_process, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $curl_process, CURLOPT_TIMEOUT, self::isDigits($time_out) ? $time_out : 240 );

		// Set proxy
        if ( !empty($proxy) ) {
            curl_setopt( $curl_process, CURLOPT_PROXY, $proxy );
        }

		// Execute cURL
		$data = curl_exec( $curl_process );

		// Log cURL error
        if( curl_errno( $curl_process ) ) {
			$error =  JError::raiseWarning(500,urlencode( curl_error($curl_process) ));
			self::redirectUrl();
        }

        curl_close( $curl_process );
        return $data;
    }

    /**
     * To form successful and unsuccessful order comments
     * @param none
     * @return none
     */
    function prepareTransactionComments($paymentId,$response,&$thisObject){
		if(in_array($paymentId,array( 'novalnetbanktransfer', 'novalnetcc', 'novalnetideal', 'novalnetpaypal', 'novalneteps', 'novalnetgiropay', 'novalnetprzelewy24' ))){
			$response['test_mode'] = self::doDecode($response['test_mode']);
		}
		//Load db
        $db = JFactory::getDBO();
		// Load user_profile plugin language
        $lang = JFactory::getLanguage();
        $lang->load('plg_rdmedia_novalnetpayment', JPATH_ADMINISTRATOR);
		$paymentName = strtoupper($paymentId);

		//Get configuration details
		$novalnetConfigurations = self::configrationDetails();

		$NewLine = "\n";
		$note .= JText::_('PLG_'.$paymentName.'_PAYMENT_NAME'). '<br>';
        $note .= JText::_('PLG_NOVALNETPAYMENT_TRANSACTION_DETAIL') .'<br>';
        $note .= JText::_('PLG_NOVALNETPAYMENT_TRANSACTION_ID'). $response['tid'];
        if(!empty($response['test_mode']) || !empty($thisObject->test_mode)) {
            $note .= '<br>'.JText::_('PLG_'.$paymentName.'_TEST_ORDER');
        }

        if($response['status'] == '100' || $response['status'] == '90') {
            if(in_array($paymentId, array('novalnetinvoice', 'novalnetprepayment'))){
				$paymentReferenceConfiguration   = JPluginHelper::getPlugin('rdmedia', $paymentId);
				$paymentReference = json_decode($paymentReferenceConfiguration->params);
                $note .= $NewLine . JText::_('PLG_'.$paymentName.'_TRANSFER_NOTE') . $NewLine;
                if(isset($response['due_date']) && $response['due_date']!='') {
                    $note .= JText::_('PLG_'.$paymentName.'_DUE_DATE') . $this->novalnet_response['due_date'] . $NewLine;
                }
                $sAmt = str_replace(".", ",", $response['amount']);
                $note .= JText::_('PLG_'.$paymentName.'_ACCOUNT_HOLDER') . " NOVALNET AG" . $NewLine;
                $note .= JText::_('PLG_'.$paymentName.'_IBAN') . $response['invoice_iban'] . $NewLine;
                $note .= JText::_('PLG_'.$paymentName.'_SWIFT_BIC') . $response['invoice_bic'] . $NewLine;
                $note .= JText::_('PLG_'.$paymentName.'_BANK') . $response['invoice_bankname'] .' '.$response['invoice_bankplace']. $NewLine;
                $note .= JText::_('PLG_'.$paymentName.'_AMOUNT') . $sAmt . ' ' . "&euro;" . $NewLine;

                $refrences      = array($paymentReference->payment_reference_1, $paymentReference->payment_reference_2, $paymentReference->payment_reference_3);
                $ac_refernce    = array_count_values($refrences);
                $i = 1;
                $note    .= (($ac_refernce['1'] > 1) ? JText::_('PLG_'.$paymentName.'_MULTI_REFERENCE_ENABLED') : JText::_('PLG_'.$paymentName.'_SINGLE_REFERENCE_ENABLED')) . PHP_EOL;
                foreach($refrences as $k => $v) {
                    if ($refrences[$k] == '1') {
                        $note .= ($ac_refernce['1'] == 1) ? JText::_('PLG_'.$paymentName.'_REFERENCE') : sprintf(JText::_('PLG_'.$paymentName.'_REFERENCE_LANG'), $i++);
                        $note .= (($k == 0 ) ? ': BNR-' . $novalnetConfigurations->product_id . '-' . $thisObject->ordercode : ( $k == 1 ? ': TID '. $response['tid']  : ': ' . JText::_('PLG_'.$paymentName.'_ORDER') . ' ' . $thisObject->ordercode)). $NewLine;
                    }
                }
            }else if($paymentId == 'novalnetbarzahlen') {
				$i = 1;		
				$note .= '<br>'. sprintf(JText::_('PLG_NOVALNETBARZAHLEN_EXPIRY_DATE_MESSAGE'), $response['cashpayment_due_date']) .'<br>';
				$note .= '<br>'. JText::_('PLG_NOVALNETBARZAHLEN_NEAR_STORE'). '<br>';
				foreach($response as $key => $value) {
					if(!empty($response['nearest_store_title_'.$i])){
						$note .= PHP_EOL. $response['nearest_store_title_'.$i]. '<br>';
						$note .= $response['nearest_store_street_'.$i]. '<br>';
						$note .= $response['nearest_store_city_'.$i]. '<br>';
						$note .= $response['nearest_store_zipcode_'.$i]. '<br>';
						$payment_country = $this->getCountryName($response['nearest_store_country_'.$i]);
						$note .= $payment_country->country. '<br>';
						$i++;
					}
				}
			}
		}else {
			$errorMessage = (!empty($response['status_text']) ? $response['status_text'] : (!empty($response['status_desc']) ? $this->novalnet_response['status_desc'] : JText::_('PLG_NOVALNETPAYMENT_TRANSACTION_ERROR')));
			JError::raiseWarning(500, $errorMessage);
			$note .= '<br>'.$errorMessage;
			$query = $db->getQuery(true)
            ->select('ordercode')
            ->from('#__ticketmaster_remarks')
            ->where('ordercode="'.$thisObject->ordercode.'"');
			$db->setQuery($query);
			$data = $db->loadObject();
			if($data->ordercode == $thisObject->ordercode && !empty($response['tid'])){
				$query->clear()
					->update($db->qn('#__ticketmaster_remarks'))
					->set($db->qn('remarks') . ' = CONCAT(remarks, "<br /><br />", "'.$note.'")')
					->where($db->qn('ordercode') . ' = "'. $thisObject->ordercode.'"');
			}else if(!empty($response['tid'])){
				$query->clear()
					->insert('#__ticketmaster_remarks')
					->columns(array($db->quoteName('remarks'), $db->quoteName('ordercode')))
					->values("'$note'" . ', ' . $thisObject->ordercode);
			}
			$result = $db->setQuery($query)->execute();
			self::exceptionHandle($result);
		}
	    return $note;
	}

	/** Store the order comments in the table and send comments by mail
	 *
	 * @param $paymentName
	 * @param $thisObject
	 * @return $novalnetComments
	 * */
    function novalnetTransactionSuccess($paymentName,&$thisObject){
		$novalnetPaymentPlugin = JPluginHelper::getPlugin('rdmedia', $paymentName);
        $novalnetPaymentConfiguration = json_decode($novalnetPaymentPlugin->params);

        ## Getting the global DB session
        $session = JFactory::getSession();

        ## Gettig the orderid if there is one.
        $this->novalnet_response = $session->get('novalnet_response_'.$paymentName);

        $db = JFactory::getDBO();
        // Load user_profile plugin language
        $lang = JFactory::getLanguage();
        $lang->load('plg_rdmedia_'.$paymentName, JPATH_ADMINISTRATOR);

        ## Include the confirmation class to sent the tickets.
        $path = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_ticketmaster'.DS.'classes'.DS.'createtickets.class.php';
        $override = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_ticketmaster'.DS.'classes'.DS.'override'.DS.'createtickets.class.php';

        $order_amount = '';
        $redirectPayments = array( 'novalnetbanktransfer','novalnetcc','novalnetideal','novalnetpaypal','novalneteps', 'novalnetgiropay', 'novalnetprzelewy24' );
		if(in_array($paymentName,$redirectPayments)){
			$order_amount = self::doDecode($this->novalnet_response['amount']);
			if($this->novalnet_response['hash2'] != self::generateHashValue($this->novalnet_response)){
				$error =  JError::raiseWarning(500, JText::_('PLG_NOVALNETPAYPAL_CHECKHASH_ERR'));
				self::redirectUrl();
			}
		}else{
			$order_amount = $this->novalnet_response['amount'] * 100;
		}
        $_SESSION['nn']['txn_details'] = $novalnetComments = nl2br(self::prepareTransactionComments($paymentName,$this->novalnet_response,$thisObject));
        $query = $db->getQuery(true)
            ->select('ordercode')
            ->from('#__ticketmaster_remarks')
            ->where('ordercode="'.$thisObject->ordercode.'"');
        $db->setQuery($query);
        $data = $db->loadObject();

        if($data->ordercode == $thisObject->ordercode && !empty($this->novalnet_response['tid'])){
            $query->clear()
                ->update($db->qn('#__ticketmaster_remarks'))
                ->set($db->qn('remarks') . ' = CONCAT(remarks, "<br /><br />", "'.$novalnetComments.'")')
                ->where($db->qn('ordercode') . ' = "'. $thisObject->ordercode.'"');
        } else if($this->novalnet_response['tid'] != ''){
            $query->clear()
                ->insert('#__ticketmaster_remarks')
                ->columns(array($db->quoteName('remarks'), $db->quoteName('ordercode')))
                ->values("'$novalnetComments'" . ', ' . $thisObject->ordercode);
        }
        $result = $db->setQuery($query)->execute();
        self::exceptionHandle($result);

		if( (in_array($this->novalnet_response['tid_status'], array('90', '86')) && in_array($paymentName, array('novalnetpaypal', 'novalnetprzelewy24'))) || in_array($paymentName, array('novalnetinvoice', 'novalnetprepayment','novalnetbarzahlen'))){
			$query->clear()
				->update($db->qn('#__ticketmaster_orders'))
				->set($db->qn('paid') . '= 3, '. $db->qn('published').' = 1')
				->where($db->qn('ordercode') . ' = "'. $thisObject->ordercode.'"');
		}else{
		   $query->clear()
				->update($db->qn('#__ticketmaster_orders'))
				->set($db->qn('paid') . '= 1, '. $db->qn('published').' = 1')
				->where($db->qn('ordercode') . ' = "'. $thisObject->ordercode.'"');
		}
        $result = $db->setQuery($query)->execute();
        self::exceptionHandle($result);
        JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_ticketmaster'.DS.'tables');
        $row = JTable::getInstance('transaction', 'Table');

        ## Now store all data in the transactions table
        $row->transid = $this->novalnet_response['tid'];
        $row->userid = $thisObject->userid;
        $row->amount = (in_array($paymentName,$redirectPayments)) ? self::doDecode($this->novalnet_response['amount'])/100 : $this->novalnet_response['amount'];
        $row->type = $thisObject->payment_type;
        $row->email_paypal = $thisObject->emailaddress;
        $row->orderid = $thisObject->ordercode;

		$order_amount = (in_array($thisObject->payment_type,array('novalnet_invoice','novalnet_prepayment','novalnet_barzahlen'))) || (in_array($this->novalnet_response['tid_status'], array('90', '86')) && in_array($paymentName, array('novalnetpaypal', 'novalnetprzelewy24'))) ? 0 : $order_amount;

        $transactions = 0;
        if(!empty($this->novalnet_response['tid'])) {
            $query->clear()
                ->select('pid')
                ->from('#__ticketmaster_transactions')
                ->where('transid="'.$this->novalnet_response['tid'].'"');
            $db->setQuery($query);
            $data = $db->loadObject();
            $transactions = count($data);
        }
        if($transactions == 0) {
            $row->store();
        }
		$query->clear()
            ->insert('#__ticketmaster_novalnet_callback')
            ->columns(array($db->quoteName('order_id'), $db->quoteName('ordercode'), $db->quoteName('callback_amount'), $db->quoteName('reference_tid'), $db->quoteName('status'), $db->quoteName('callback_datetime'), $db->quoteName('order_reference_no'), $db->quoteName('payment_type')))
            ->values("'".$thisObject->orderid . "', '".$thisObject->ordercode."', '".$order_amount."', '".$this->novalnet_response['tid']."', '".$this->novalnet_response['status']."', '".date('Y-m-d')."', '".$thisObject->orderid.'-'.$thisObject->ordercode."', '".$thisObject->payment_type."'");
        $result = $db->setQuery($query)->execute();
        self::exceptionHandle($result);

        $query->clear()
            ->select('orderid')
            ->from('#__ticketmaster_orders')
            ->where('ordercode="'.$thisObject->ordercode.'"');
        $db->setQuery($query);
        $data = $db->loadObjectList();
        $count = count($data);
        for ($i = 0; $i < $count; $i++ ) {
            $row  = &$data[$i];
            ## Check if the override is there.
            if (file_exists($override)) {
                ## Yes, now we use it.
                require_once($override);
            } else {
                ## No, use the standard
                require_once($path);
            }
            if(isset($row->orderid)) {
                $creator = new ticketcreator( (int)$row->orderid );
                $creator->doPDF();
            }
        }

        ## Include the confirmation class to sent the tickets.
        $path_include = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_ticketmaster'.DS.'classes'.DS.'sendonpayment.class.php';
        include_once( $path_include );

        ## Sending the ticket immediatly to the client.
        $creator = new sendonpayment( (int)$thisObject->ordercode );
        $creator->send();

        ## Getting the desired info from the configuration table
        $query->clear()
            ->select('mailbody')
            ->from('#__ticketmaster_emails')
            ->where('emailid="'.$thisObject->success_tpl.'"');
        $db->setQuery($query);
        $config = $db->loadObject();
		$successComments = !empty($config->mailbody) ? $config->mailbody : '';
        echo $successComments.'<br/>'.$novalnetComments;
    }

	/**
     * Handle the error
     * @param boolean $result
     * @return none
     */
    function exceptionHandle($result) {

        try {
            if(!$result) {
                throw new Exception("Unexpected error has been occured, datas are cant able to insert or update into the table ticketmaster_orders");
            }
        } catch(Exception $e) {
            echo $e->getMessage();
        }
    }

	 /**
     * Display fraud module field's
     * @param none
     * @return template
     */
    function fraudModuleFields($paymentName,$fraud_prevention,$thisObject) {
        // Get paymnet session
        $session = JFactory::getSession();
        $this->novalnet_response = $session->get('novalnet_response_'.$paymentName);
        $getFraudfields = $session->get('novalnet_response_'.$paymentName);

		// Load user_profile plugin language
        $lang = JFactory::getLanguage();
        $lang->load('plg_rdmedia_'.$paymentName, JPATH_ADMINISTRATOR);
        $paymentType = strtoupper($paymentName);
		if(empty($session->get($paymentName.'_tid'))){
				if($fraud_prevention = 'CALLBACK'){
				 return '<tr>
						<td>'.JText::_('PLG_'.$paymentType.'_TELEPHONE').'</td>
						<td>
							<input type="text" id="'.$paymentName.'_telephone"  name="'.$paymentName.'_telephone" autocomplete="off" value="'.$thisObject->phonenumber.'" />
						</td>
					</tr>';
				}else{
				  return '<tr>
						<td>'.JText::_('PLG_'.$paymentType.'_MOBILE') . '</td>
						<td>
							<input type="text" id="'.$paymentName.'_mobile" name="'.$paymentName.'_mobile" autocomplete="off" value="" />
						</td>
					</tr>';
				}
		}else{
		 return '<table><tbody><fieldset><tr>
				<td>'.JText::_('PLG_'.$paymentType.'_TRANSACTION') . '</td>
				<td>
				   <input type="text" id="'.$paymentName.'_transcation_pin" name="'.$paymentName.'_transcation_pin" autocomplete="off" value="" />
				</td>
			</tr>
			<tr>
				<td>'.JText::_('PLG_'.$paymentType.'_FORGET_PIN') . '</td>
				<td>
				   <input type="checkbox" id="'.$paymentName.'_forgot_pin" name="'.$paymentName.'_forgot_pin" value="1" />
				</td>
			</tr></fieldset></tbody></table>';
		}
        return '';
    }

    /* To get fraud prevention type and parameter
     *
     * @param string $payment_name
     * @param string $fraud_module_type
     *
     * @return array $fraud_module_value
     * */
     function getFraudPreventionParams($paymentName,$fraudPreventionType){
		// Get paymnet session
        $session = JFactory::getSession();
		// Get fraud prevention parameter
        if($fraudPreventionType == 'CALLBACK'){
            $fraud_module_value['pin_by_callback'] = 1;
            $fraud_module_value['tel']= $session->get($paymentName.'_telephone');
        }else {
            $fraud_module_value['pin_by_sms'] = 1;
            $fraud_module_value['mobile'] = $session->get($paymentName.'_mobile');
        }
        return $fraud_module_value;
	 }

	 /* Validate pin fields
	  *
	  * @param $paymentType
	  *
	  * */
	function validateFraudModuleInputfields($paymentType){
		// Get paymnet session
        $session = JFactory::getSession();
        // payment name change to upper case
		$paymentName = strtoupper($paymentType);
		$value = $session->get($paymentType.'_transcation_pin');
		if ($session->get($paymentType.'_forgot_pin') != 1 && (empty($session->get($paymentType.'_transcation_pin')) || (!preg_match('/^[a-zA-Z0-9]+$/',$session->get($paymentType.'_transcation_pin'))))) {
			$error_message = empty($session->get($paymentType.'_transcation_pin')) ? JText::_('PLG_'.$paymentName.'_FRAUD_EMPTY_PIN') : JText::_('PLG_'.$paymentName.'_INCORRECT_PIN');
			JError::raiseWarning(500, $error_message);
        }
	}

	/*
	* Validate input form callback parameters
	* @param $fraudPreventionType
	* @param $paymentType
	*
	* @return array
	*/
	function validateUserInputs($paymentType,$fraudPreventionType){
		// Get paymnet session
        $session = JFactory::getSession();
		// payment name change to upper case
		$paymentName = strtoupper($paymentType);
		if(in_array($fraudPreventionType,array('CALLBACK','SMS')) || !empty($session->get($paymentType.'_telephone')) || !empty($session->get($paymentType.'_mobile'))){
			$error_message = ($fraudPreventionType == 'CALLBACK' && (!is_numeric($session->get($paymentType.'_telephone')))) ? JText::_('PLG_'.$paymentName.'_TELEPHONE_NUMBER_EMPTY_MESSAGE') : (($fraudPreventionType == 'SMS' && (!is_numeric($session->get($paymentType.'_mobile')))) ? JText::_('PLG_'.$paymentName.'_TELEPHONE_NUMBER_EMPTY_MESSAGE') : '');
			JError::raiseWarning(500, $error_message);
		}
	}


	/* Perform xml call
	 *
	 * @param string $request_type
	 * @param string $paymentType
	 * @param integer $pin
	 *
	 * @return array
	 * */
    static public function xmlCallProcess($request_type,$paymentName,$pin){

		// Get paymnet session
        $session = JFactory::getSession();
        $response_tid = $session->get($paymentName.'_tid');

        // Get remote ip address
        $remote_ip = self::_getRealIpAddr();

		// Get vendor configuration detail
		$novalnetVendorDetails = self::configrationDetails();
        $xml_detail='<?xml version="1.0" encoding="UTF-8"?><nnxml>
            <info_request>
            <vendor_id>'.$novalnetVendorDetails->merchant_id.'</vendor_id>
            <vendor_authcode>'.$novalnetVendorDetails->merchant_authcode.'</vendor_authcode>
            <product_id>'.$novalnetVendorDetails->product_id.'</product_id>
            <remote_ip>'.$remote_ip.'</remote_ip>
            <request_type>'.$request_type.'</request_type>
            <tid>'.$response_tid.'</tid>';
        $xml_detail .= ($request_type == 'PIN_STATUS') ? '<pin>'.$pin.'</pin></info_request></nnxml>' : '</info_request></nnxml>';
        $curl_details = self::performHttpsRequest('https://payport.novalnet.de/nn_infoport.xml',$xml_detail);
		return (array) simplexml_load_string($curl_details);
    }

	/**
	* To get the country name
	* @return object
	*/
    function getCountryName($country_code)
    {
		$db = JFactory::getDBO();
		$query = $db->getQuery(true)
            ->select('country')
            ->from('#__ticketmaster_country')
            ->where('country_2_code ="'.$country_code.'"');
        $db->setQuery($query);
        return $db->loadObject();
    }
}
?>
