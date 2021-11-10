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
  * Script : novalnetsepa.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');

include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php' );

class plgRDmediaNovalnetSepa extends JPlugin
{
	public $id = 'novalnetsepa';
	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for
	 * plugins because func_get_args ( void ) returns a copy of all passed arguments
	 * NOT references.  This causes problems with cross-referencing necessary for the
	 * observer design pattern.
	 */
	 function plgRDmediaNovalnetSepa( &$subject, $params) {
		parent::__construct( $subject , $params);
		## Loading language:
		$lang = JFactory::getLanguage();
		$utilityObject = new novalnetUtilities;
		$lang->load('plg_rdmedia_novalnetsepa', JPATH_ADMINISTRATOR);
		$utilityObject->getCommonvalues($this);

		$this->test_mode = $this->params->def('test_mode');
        $this->auto_refill = $this->params->def('auto_refill');
        $this->guarantee_sepa = $this->params->def('guarantee_sepa');
        $this->guarantee_sepa_force = $this->params->def('guarantee_sepa_force');
        $this->sepa_normal_due_date = $this->params->def('sepa_normal_due_date');
        $this->fraud_check_limit = $this->params->def('sepa_fraud_prevention_amount_limit');
        $this->sepa_fraud_prevention = $this->params->def('sepa_fraud_prevention');
        $this->sepa_buyer_notification = $this->params->def('sepa_buyer_notification');
        $this->layout = $this->params->def('layout');
        $this->success_tpl = $this->params->def('success_tpl');
        $this->failure_tpl = $this->params->def('failure_tpl');
        $this->currency = $this->params->def('currency');
        $this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
        $this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
        $this->key = '37';
        $this->novalnet_response = array();
        $this->novalnet_request = array();
        $this->payment_type = 'novalnet_sepa';
        $this->paygate_url = 'https://payport.novalnet.de/paygate.jsp';
	}

	/**
	 * Plugin method with the same name as the event will be called automatically.
	 * You have to get at least a function called display, and the name of the processor
	 * Now you should be able to display and process transactions.
	 *
	 */
	function display() {
		$utilityObject = new novalnetUtilities;
        $db = JFactory::getDBO();
		$_SESSION['request_uri'] = $_SERVER['REQUEST_URI'];
        ## Loading the CSS file.
        $document = JFactory::getDocument();
        $document->addStyleSheet( JURI::root(true).'/plugins/rdmedia/novalnetsepa/rdmedia_novalnetsepa/css/novalnetsepa.css' );

		$session = JFactory::getSession();

        $this->novalnet_request = $utilityObject->formPaymentParameters('novalnetsepa', $this);

		// Hide payment
		if(!empty($session->get('novalnetsepa_hidelimit')) && !empty($session->get('novalnetsepa_paymnet_hide')) && time() < $session->get('novalnetsepa_hidelimit')){
			return '';
		}
		if(!empty($session->get('novalnetsepa_hidelimit')) && time() > $session->get('novalnetsepa_hidelimit')){
			$this->clearSession();
		}


		## Check if this is Joomla 2.5 or 3.0.+
		$isJ30 = version_compare(JVERSION, '3.0.0', 'ge');

		## This will only be used if you use Joomla 2.5 with bootstrap enabled.
		## Please do not change!

		if(!$isJ30){
			if($config->load_bootstrap == 1){
				$isJ30 = true;
			}
		}
		if((isset($this->firstname) && empty($this->name)) || (isset($this->name) && empty($this->firstname))){
			if(empty($this->name)){
				$name = preg_split("/[\s]+/", $this->firstname, 2);
			}elseif(empty($this->firstname)){
				$name = preg_split("/[\s]+/", $this->name, 2);
			}
			$this->firstname = $name[0];
			if(isset($name[1])){
				$this->name = $name[1];
			}else{
				$this->name = $name[0];
			}
		}
		if($utilityObject->doValidation($this)){
			$novalnetConfigurations = $utilityObject->configrationDetails();
			if(empty($this->novalnet_response['tid']) && $this->payment_type == 'novalnet_sepa'){
                if($this->layout == 1 && $isJ30 == true ) {
					if($novalnetConfigurations->payment_logo == '1'){
                       $sepaForm = '<img src="plugins/rdmedia/novalnetsepa/rdmedia_novalnetsepa/images/novalnet_sepa.png" />';
                    }
                    if($this->test_mode == '1'){
                        $sepaForm .=  "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETSEPA_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $sepaForm .= '<form action="'.$this->form_url.'" method="post" name="novalnetForm">';
                    foreach($this->novalnet_request as $key => $value) {
                        $sepaForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $sepaForm .= '<button class="btn btn-block btn-success" alt="'.JText::_('PLG_NOVALNETSEPA_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETSEPA_PAYMENT_LOGO_TITLE').'" style="margin-top: 8px;" type="submit" id="enter_sepa" name="enter_sepa">'.JText::_( 'PLG_NOVALNETSEPA_BTN_PAY' ).'</button>';
                    $sepaForm .= '</form>';
                } else {
                    $sepaForm = '<form action="'.$this->form_url.'" method="post" name="novalnetForm">';
                    $sepaForm .= '<div id="plg_rdmedia_novalnetsepa">';
                    $sepaForm .= '<div id="plg_rdmedia_novalnetsepa_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETSEPA_PAYMENT_NAME_WT_NOVALNET');
                    $sepaForm .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETSEPA_PAYMENT_DESC')."</span>";
                    $sepaForm .= "<br/><span>".$this->sepa_buyer_notification."</span>";
                    if($this->test_mode == '1'){
                        $sepaForm .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETSEPA_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $sepaForm .= '</div>';
                    foreach($this->novalnet_request as $key => $value) {
                        $sepaForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $sepaForm .= '<div id="plg_rdmedia_novalnetsepa_confirmbutton">';
                    $sepaForm .= '<input type="submit" id="enter_sepa" name="enter_sepa" value="" class="novalnetsepa_button" alt="'.JText::_('PLG_NOVALNETSEPA_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETSEPA_PAYMENT_LOGO_TITLE').'" style="width: 116px;">';
                    $sepaForm .= '</div>';
                    $sepaForm .= '</div>';
                    $sepaForm .= '</form>';
                }
               echo $sepaForm;
            }
		   return true;
		}
	}

	function novalnetsepa_failed() {
		$utilityObject = new novalnetUtilities;
		$this->novalnet_response = $_REQUEST;
        $utilityObject->prepareTransactionComments('novalnetsepa',$this->novalnet_response,$this);
		$this->clearSession();
	}

	function novalnetsepa_form() {
		$utilityObject = new novalnetUtilities;
        $session = JFactory::getSession();
        $db = JFactory::getDBO();
        if(isset($_REQUEST['enter_sepa'])) {
            $session->set("novalnet_request_sepa", $_POST);
        }
        if(isset($_REQUEST['elv_sepa_form']) || isset($_REQUEST['fraud_sepa_form'])) {

            $this->novalnet_request = $_POST;
            $sepaHash = $session->get('novalnet_sepa_hash');
            if(!empty($sepaHash)) {
                $session->set('novalnet_sepa_hash', $this->novalnet_request['nn_sepa_hash']);
            }
            if(empty($this->novalnet_response['tid'])){
				$telephone = isset($this->novalnet_request['novalnetsepa_telephone']) ? $this->novalnet_request['novalnetsepa_telephone'] : '';
				$mobile = isset($this->novalnet_request['novalnetsepa_mobile']) ? $this->novalnet_request['novalnetsepa_mobile'] : '';
				$session->set('novalnetsepa_telephone', $telephone);
				$session->set('novalnetsepa_mobile', $mobile);
			}

            if(!empty($session->get('novalnetsepa_tid'))){
				$session->set('novalnetsepa_forgot_pin', $this->novalnet_request['novalnetsepa_forgot_pin']);
				$session->set('novalnetsepa_transcation_pin', $this->novalnet_request['novalnetsepa_transcation_pin']);
				if (in_array($this->sepa_fraud_prevention, array('CALLBACK','SMS'))) {
					$request_type = (isset($_POST['novalnetsepa_forgot_pin'])) ? 'TRANSMIT_PIN_AGAIN' : 'PIN_STATUS';
				}
				$xml_data = $utilityObject->xmlCallProcess($request_type, $this->id, $_POST['novalnetsepa_transcation_pin']);
				 if ($xml_data['status'] == 100) {
					header( "Location: ". $this->return_url);
				 }else{
					if ($xml_data['status'] == '0529006') {
						$session->set('novalnetsepa_paymnet_hide',true);
						header( "Location: ". $utilityObject->redirectUrl());
						$session->clear('novalnetsepa_tid');
					}
					$error_message = isset($xml_data['status_message']) ? $xml_data['status_message'] :(isset($xml_data['status_desc']) ? $xml_data['status_desc'] : $xml_data['status_text']);
					JError::raiseWarning(500, $error_message);
				 }
			}

			if($this->doFormValidation()) {
				$payment_params = array(
					'bank_account_holder' => $this->novalnet_request['nnsepa_holder'],
					'sepa_hash'           => $this->novalnet_request['nn_sepa_hash'],
					'sepa_unique_id'      => $this->novalnet_request['nn_sepa_unique'],
					'iban_bic_confirmed'  => $this->novalnet_request['nnsepa_mandate_confirm']
					);
				if(self::checkGuaranteeEnable()) {
					$payment_params['key']  = 40;
					$payment_params['payment_type'] = 'GUARANTEED_DIRECT_DEBIT_SEPA';
					$payment_params['birth_day']    = $this->novalnet_request['nnsepa_birth_day'];
				}
				if($this->fraud_check_limit > $this->amount && $this->sepa_fraud_prevention != 'NONE' && (in_array($this->country,array('DE','AT','CH')))){
					$fraud_params = $utilityObject->getFraudPreventionParams('novalnetsepa',$this->sepa_fraud_prevention);
				}
				if($this->sepa_fraud_prevention != 'NONE'){
					$this->novalnet_request = array_merge($session->get('novalnet_request_sepa'), $payment_params,$fraud_params);
				}else {
					$this->novalnet_request = array_merge($session->get('novalnet_request_sepa'), $payment_params);
				}
				unset($this->novalnet_request['enter_sepa']);
				$response = $utilityObject->performHttpsRequest($this->paygate_url,http_build_query($this->novalnet_request));
				parse_str($response, $novalnet_response);
				$this->novalnet_response = $novalnet_response;
				$session->set("novalnet_response_novalnetsepa", $this->novalnet_response);
				$session->set("novalnetsepa_tid",$this->novalnet_response['tid']);

				if($this->sepa_fraud_prevention != 'NONE' && !empty($this->novalnet_response['tid']) && $this->novalnet_response['status'] == '100'){
					$session->set('novalnet_response_amount',$this->novalnet_response['amount']);
					$session->set('novalnet_response_testmode',$this->novalnet_response['test_mode']);
					$session->set('novalnetsepa_hidelimit',time() + (30 * 60));
					$error_message = ($this->sepa_fraud_prevention == 'CALLBACK') ? JText::_('PLG_NOVALNETSEPA_FRAUD_CALLBACK_MESSAGE') : JText::_('PLG_NOVALNETSEPA_FRAUD_SMS_MESSAGE');
					JError::raiseWarning(500, $error_message);
				}else{
					if(isset($this->novalnet_response['status']) && $this->novalnet_response['status'] == '100') {
						header( "Location: ". $this->return_url);
					}else{
						header( "Location: ". $this->cancel_url);
					}
				}
			}
        }
        echo $this->sepaForm();
	}

    /**
     * Display SEPA form
     * @param none
     * @return none
     */
    function sepaForm() {
		$utilityObject = new novalnetUtilities;
        $session = JFactory::getSession();
        $sepaHash = $session->get('novalnet_sepa_hash');
        $sepa_config= $session->get('novalnet_request_sepa');
        $nnSepaHash = (!empty($this->auto_refill) && !empty($sepaHash) ? $sepaHash : '');

        $display = '<link rel="stylesheet" media="all" href="'.JURI::base(true).'/plugins/rdmedia/novalnetsepa/rdmedia_novalnetsepa/css/novalnetsepa.css">
       <legend>'.JText::_('PLG_NOVALNETSEPA_REQUIRED_FIELD_LABLE').' :</legend>';
		$sepa_response = $session->get('novalnet_response_novalnetsepa');
		if(empty($session->get('novalnetsepa_tid'))){
			$display .= '<div class=component-content">
				<div class=item-page">
				<div style="margin-bottom:2%;">'.JText::_('PLG_NOVALNETSEPA_PAYMENT_DESC').'</div>';
				if((bool) $this->test_mode) {
					$display .= "<div style='color:red;'>".JText::_('PLG_NOVALNETSEPA_FRONTEND_TESTMODE_DESC')."</div>";
				}
			$display .= '<form action="" method="post" id="novalnetSepa" name="novalnetSepa">
				<fieldset>
				<table>
					<tr>
						<td>'.JText::_('PLG_NOVALNETSEPA_HOLDER') . '<font color="red">*</font></td>
						<td>
							<input type="text" id="nnsepa_holder" name="nnsepa_holder" onchange="unset_generated_hash_related_elements();" onkeypress = "return sepa_validate_account_number(event);" autocomplete="off" value="'.$this->firstname.' '.$this->name.'" />
						</td>
					</tr>
					<tr>
						<td>'.JText::_('PLG_NOVALNETSEPA_COUNTRY') . '<font color="red">*</font></td>
						<td>
							<select style="width:218px;" id="nn_sepa_bank_country" onchange="unset_generated_hash_related_elements();" name="nn_sepa_bank_country">';
							$display .= '<option value="">'.JText::_('PLG_NOVALNETSEPA_SELECT').'</option>';
								$country = self::getCountryList();
								foreach($country as $country_val) {
									$display .= '<option value=' . $country_val->code . ' ' . (($this->country == $country_val->code) ? 'selected="selected"' : '') . '>' . $country_val->country_name . '</option>';
								}
								$display .= '</select>
						</td>
					</tr>
					<tr>
						<td id="nnaccount_label" style="width: 27%;">'.JText::_('PLG_NOVALNETSEPA_ACCOUNT_NO') . '<font color="red">*</font></td>
						<td id="nnaccount_number" style="width: 30%;">
							<input type="text" id="nnsepa_account_number" name="nnsepa_account_number" onchange="unset_generated_hash_related_elements();" autocomplete="off" onkeypress = "return sepa_validate_account_number(event);" value="" />
						</td>
						<td id="novalnet_sepa_iban_span" style="display:none;"></td>
						</tr>
					</tr>
					<tr>
						<td>'.JText::_('PLG_NOVALNETSEPA_BANK_CDOE') . '<font color="red">*</font></td>
						<td>
							<input type="text" id="nnsepa_bank_code" name="nnsepa_bank_code" onchange="unset_generated_hash_related_elements();" onkeypress = "return sepa_validate_account_number(event);" value="" autocomplete="off" />
						</td>
						<td id="novalnet_sepa_bic_span" style="display:none;"></td>
					</tr>
					<tr>
						<td></td>
						<td style="width: 30%;"><a onclick="generate_sepa_iban_bic(this);"><input type="checkbox" id="nnsepa_mandate_confirm" style="margin-bottom:1%;" name="nnsepa_mandate_confirm" value="1" /></a>&nbsp;'.
							JText::_('PLG_NOVALNETSEPA_MANDATE_CONFIRM').
						'</td>
						<td></td>
					</tr>';
				$display .= self::showBirthDate();
				if($this->fraud_check_limit > $this->amount && $this->sepa_fraud_prevention != 'NONE' && (in_array($this->country,array('DE','AT','CH')))){
					$display .= $utilityObject->fraudModuleFields('novalnetsepa',$this->sepa_fraud_prevention,$this);
				}
				$display .='</table>
				<div class="loader_sepa" id="loader" style="display:none;"></div>
				<input type="hidden" id="nn_vendor" name="nn_vendor" value="'.$sepa_config['vendor'].'"/>
				<input type="hidden" id="nn_auth_code" name="nn_auth_code" value="'.$sepa_config['auth_code'].'"/>
				<input type="hidden" id="nn_sepa_hash"  name="nn_sepa_hash" value=""/>
				<input type="hidden" id="nn_sepa_iban" name="nn_sepa_iban" />
				<input type="hidden" id="nn_sepa_bic" name="nn_sepa_bic" />
				<input type="hidden" id="nn_sepa_unique" name="nn_sepa_unique" value="'.self::getRandomString().'" />
				<input type="hidden" id="nn_sepa_iban_bic_error" value="'.JText::_ ('PLG_NOVALNETSEPA_ERROR_IBANCONFIRMED') .'" />
				<input type="hidden" id="nn_sepa_merchant_error" value="'.JText::_ ('PLG_NOVALNETSEPA_MERCHANT_ERROR') .'" />
				<input type="hidden" id="nn_sepa_account_error" value="'.JText::_ ('PLG_NOVALNETSEPA_ACCOUNT_ERROR') .'" />
				<input type="hidden" id="nn_sepa_country_error" value="'.JText::_ ('PLG_NOVALNETSEPA_SELECT_COUNTRY') .'" />
				<input type="hidden" id="sepaiban" value= "" name="sepaiban">
				<input type="hidden" id="sepabic" value="" name="sepabic">
				<input type="hidden" id="nn_sepa_input_panhash" value="'.$nnSepaHash.'" name="nn_sepa_input_panhash">
				<script src="'.JURI::base(true).'/plugins/rdmedia/novalnetsepa/rdmedia_novalnetsepa/js/novalnet_sepa.js" type="text/javascript" language="javascript"></script>';
				$display .= '</fieldset>
				<input type="submit" name="elv_sepa_form" id="elv_sepa_form" value="'.JText::_('PLG_NOVALNETSEPA_BUTTON_PAYMENT_PROCEED').'"/></form></div></div>';
		}else{
			$display .= '<form method="post" id="nnSepaFraudprevention" name="nnSepaFraudprevention">';
			if($this->fraud_check_limit > $this->amount && $this->sepa_fraud_prevention != 'NONE' && (in_array($this->country,array('DE','AT','CH')))){
				$display .= $utilityObject->fraudModuleFields('novalnetsepa',$this->sepa_fraud_prevention,$this);
			}
			$display .= '<input type="submit" name="fraud_sepa_form" id="fraud_sepa_form" value="'.JText::_('PLG_NOVALNETSEPA_BUTTON_PAYMENT_PROCEED').'"/></form>';
		}
        echo $display;
    }

    /**
     * Display date of birth field
     * @param none
     * @return template
     */
    function showBirthDate() {

        if(self::checkGuaranteeEnable()) {
            return '<tr>
                    <td>'.JText::_('PLG_NOVALNETSEPA_BIRTH_DAY') . '</td>
                    <td>
                        <input type="text" id="nnsepa_birth_day" placeholder="YYYY-MM-DD" name="nnsepa_birth_day" autocomplete="off" value="" />
                    </td>
                </tr>';
        }
        return '';
    }

    /**
     * Check the condition for guarantee payment
     * @param none
     * @return boolean
     */
    function checkGuaranteeEnable() {

        if(!empty($this->guarantee_sepa)) {

            if((!empty($this->guarantee_sepa_force) && ($this->amount > 2000 && $this->amount < 500000)) || empty($this->guarantee_sepa_force)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get random 30 digit string
     * @param none
     * @return string
     */
    function getRandomString() {

        $randomwordarray = explode( ',', 'a,b,c,d,e,f,g,h,i,j,k,l,m,1,2,3,4,5,6,7,8,9,0' );
        shuffle( $randomwordarray );
        return substr( implode( $randomwordarray,'' ), 0, 30 );
    }

    /**
     * Vaidate account details
     * @param none
     * @return none
     */
    function doFormValidation() {
		$utilityObject = new novalnetUtilities;
		$session = JFactory::getSession();
		if(empty($session->get('novalnetsepa_tid'))){
			if(empty($this->novalnet_request['nnsepa_mandate_confirm'])) {
				JError::raiseWarning(500, JText::_('PLG_NOVALNETSEPA_ERROR_IBANCONFIRMED'));
				return false;
			}
			else if(empty($this->novalnet_request['nn_sepa_hash']) || empty($this->novalnet_request['nn_sepa_unique'])
			|| empty($this->novalnet_request['nnsepa_holder'])) {
				JError::raiseWarning(500, JText::_('PLG_NOVALNETSEPA_PAYMENT_ERR'));
				return false;
			}
			else if(self::checkGuaranteeEnable()) {
				$date_regex = '/^(19|20)\d\d[\-\/.](0[1-9]|1[012])[\-\/.](0[1-9]|[12][0-9]|3[01])$/';
				if(empty($this->novalnet_request['nnsepa_birth_day']) || !preg_match($date_regex, $this->novalnet_request['nnsepa_birth_day'])) {
					JError::raiseWarning(500, JText::_('PLG_NOVALNETSEPA_BIRTHDAY_ERR'));
					return false;
				}
			}else if($this->sepa_fraud_prevention != 'NONE' && !$utilityObject->isDigits($session->get('novalnetsepa_telephone')) || $utilityObject->isDigits($session->get('novalnetsepa_mobile'))){
				$utilityObject->validateUserInputs($this->id,$this->sepa_fraud_prevention);
				return false;
			}
			return true;
		}else if(!empty($session->get('novalnetsepa_tid'))){
			if($this->sepa_fraud_prevention != 'NONE'){
				$utilityObject->validateFraudModuleInputfields($this->id);
			}
		}
    }

	/**
     * Success the payment
     * @param none
     * @return none
     */
    function novalnetsepa() {
		$utilityObject = new novalnetUtilities;
		$session = JFactory::getSession();
		$response = $session->get('novalnet_response_novalnetsepa');
		$utilityObject->novalnetTransactionSuccess('novalnetsepa',$this);
		## Removing the session, it's not needed anymore.
        $this->clearSession();
	}

    /**
     * To get the list of countries
     * @param null
     * @return object
     */
    function getCountryList() {

        $db = JFactory::getDBO();
        $query = $db->getQuery(true)
            ->select('country_2_code AS code, country AS country_name')
            ->from('#__ticketmaster_country')
            ->where('published=1 AND country_id !=1')
            ->order($db->quoteName('country') . ' ASC');
        $db->setQuery($query);
        return $db->loadObjectList();
    }
	/**
     * Clear session values
     * @param none
     * @return none
     */
    function clearSession($clear = false){

        $session = JFactory::getSession();
        $session->clear('novalnet_request_sepa');
        $session->clear('novalnet_response_novalnetsepa');
        $session->clear('ordercode');
        $session->clear('coupon');
        $session->clear('novalnet_sepa_hash');
        $session->clear('novalnet_response_amount');
		$session->clear('novalnet_response_testmode');
		$session->clear('novalnetsepa_tid');
		$session->clear('novalnetsepa_hidelimit');
		$session->clear('novalnetsepa_paymnet_hide');
        if($clear) {
            unset($_SESSION['nn']);
        }
    }
}

?>
