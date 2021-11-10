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
  * Script : novalnetinvoice.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');
include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php' );

class plgRDmediaNovalnetInvoice extends JPlugin {
    public $id = 'novalnetinvoice';

    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for
     * plugins because func_get_args ( void ) returns a copy of all passed arguments
     * NOT references.  This causes problems with cross-referencing necessary for the
     * observer design pattern.
     */
    function plgRDmediaNovalnetInvoice( &$subject, $params ) {

        parent::__construct( $subject , $params );
        ## Loading language:
        $lang = JFactory::getLanguage();
        $utilityObject = new novalnetUtilities;
        $lang->load('plg_rdmedia_novalnetinvoice', JPATH_ADMINISTRATOR);
        $utilityObject->getCommonvalues($this);

        ## load plugin params info
		$this->test_mode = $this->params->def('test_mode');
        $this->payment_period = trim($this->params->def('payment_period'));
        $this->payment_ref1 = trim($this->params->def('payment_reference_1'));
        $this->payment_ref2 = trim($this->params->def('payment_reference_2'));
        $this->payment_ref3 = trim($this->params->def('payment_reference_3'));
        $this->guarantee_inv = trim($this->params->def('guarantee_invoice'));
        $this->guarantee_inv_force = trim($this->params->def('guarantee_invoice_force'));
        $this->invoice_fraud_prevention = $this->params->def('invoice_fraud_prevention');
        $this->invoice_fraud_prevention_amount_limit = $this->params->def('invoice_fraud_prevention_amount_limit');
        $this->invoice_buyer_notification = $this->params->def('invoice_buyer_notification');
        $this->layout = $this->params->def('layout');
        $this->success_tpl = $this->params->def('success_tpl');
        $this->failure_tpl = $this->params->def('failure_tpl');
        $this->currency = $this->params->def('currency');
        $this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
        $this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
        $this->key = '27';
        $this->novalnet_response = array();
        $this->novalnet_request = array();
        $this->payment_type = 'novalnet_invoice';
        $this->paygate_url='https://payport.novalnet.de/paygate.jsp';

    }

    /**
     * Plugin method with the same name as the event will be called automatically.
     * You have to get at least a function called display, and the name of the processor
     * Now you should be able to display and process transactions.
     */
    function display() {
        $utilityObject = new novalnetUtilities;
        $db = JFactory::getDBO();
        $this->novalnet_request = $utilityObject->formPaymentParameters('novalnetinvoice', $this);

        ## Loading the CSS file.
        $document = JFactory::getDocument();
        $document->addStyleSheet( JURI::root(true).'/plugins/rdmedia/novalnetinvoice/rdmedia_novalnetinvoice/css/novalnetinvoice.css' );
        $session = JFactory::getSession();
        ## Check if this is Joomla 2.5 or 3.0.+
        $isJ30 = version_compare(JVERSION, '3.0.0', 'ge');
        $_SESSION['request_uri'] = $_SERVER['REQUEST_URI'];

		// Hide payment
		if(isset($session)&&!empty($session->get('novalnetinvoice_hidelimit')) && (!empty($session->get('novalnetinvoice_paymnet_hide')) && time() < $session->get('novalnetinvoice_hidelimit'))){
			return '';
		}

		if(!empty($session->get('novalnetinvoice_hidelimit')) && !empty($session->get('novalnetinvoice_hidelimit')) && time() > $session->get('novalnetinvoice_hidelimit')){
			$this->clearSession();
		}

        ## This will only be used if you use Joomla 2.5 with bootstrap enabled.
        ## Please do not change!

        if(!$isJ30){
            if($config->load_bootstrap == 1){
                $isJ30 = true;
            }
        }
        if($utilityObject->doValidation($this)) {
			$novalnetConfigurations = $utilityObject->configrationDetails();
            if(empty($session->get('novalnetinvoice_tid')) && $this->payment_type == 'novalnet_invoice') {
				$invoiceForm ='';
                if($this->layout == 1 && $isJ30 == true ) {
					if($novalnetConfigurations->payment_logo == '1'){
						$invoiceForm .= '<img src="plugins/rdmedia/novalnetinvoice/rdmedia_novalnetinvoice/images/novalnet_invoice.png" />';
					}
                    if($this->test_mode == '1') {
                        $invoiceForm .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETINVOICE_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $invoiceForm .='<form action="" method="post" name="novalnetForm">';
                    $invoiceForm .= $this->showBirthDate();
					if($this->invoice_fraud_prevention_amount_limit > $this->amount && $this->invoice_fraud_prevention != 'NONE' && (in_array($this->country,array('DE','AT','CH')))){
						$invoiceForm .= $utilityObject->fraudModuleFields('novalnetinvoice',$this->invoice_fraud_prevention,$this);
					}
                    $invoiceForm .='<button class="btn btn-block btn-success" style="margin-top: 8px;" type="submit" name="enter_invoice">'.JText::_( 'PLG_NOVALNETINVOICE_BTN_PAY' ).'</button>';
                    $invoiceForm .='</form>';
                } else {
					if($novalnetConfigurations->payment_logo == '1'){
						$invoiceForm .= '<img src="plugins/rdmedia/novalnetinvoice/rdmedia_novalnetinvoice/images/novalnet_invoice.png" />';
					}
                    $invoiceForm ='<form action="" method="post" name="novalnetForm">';
                    $invoiceForm .= '<div id="plg_rdmedia_novalnetinvoice">';
                    $invoiceForm .= '<div id="plg_rdmedia_novalnetinvoice_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETINVOICE_PAYMENT_NAME_WT_NOVALNET');
                    $invoiceForm .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETINVOICE_PAYMENT_DESC')."</span><br/><span>".$this->buyer_notification."</span>";
                    if($this->test_mode == '1'){
                        $invoiceForm .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETINVOICE_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $invoiceForm .= '</div>';
                    $invoiceForm .= '<div id="plg_rdmedia_novalnetinvoice_confirmbutton">';
                    $invoiceForm .= $this->showBirthDate();
					if($this->invoice_fraud_prevention_amount_limit > $this->amount && $this->invoice_fraud_prevention != 'NONE' && (in_array($this->country,array('DE','AT','CH')))){
						$invoiceForm .= $utilityObject->fraudModuleFields('novalnetinvoice',$this->invoice_fraud_prevention,$this);
					}
                    $invoiceForm .= '<input type="submit" name="enter_invoice" value="" class="novalnetinvoice_button" alt="'.JText::_('PLG_NOVALNETINVOICE_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETINVOICE_PAYMENT_LOGO_TITLE').'" style="width: 116px;">';
                    $invoiceForm .= '</div>';
                    $invoiceForm .= '</div>';
                    $invoiceForm .= '</form>';
                }
                echo $invoiceForm;
            }else if(!empty($session->get('novalnetinvoice_tid'))){
				$invoiceForm .= '<form method="post" id="nnInvoiceFraudprevention" name="nnInvoiceFraudprevention">';
				if($this->invoice_fraud_prevention_amount_limit > $this->amount && $this->invoice_fraud_prevention != 'NONE' && (in_array($this->country,array('DE','AT','CH')))){
					$invoiceForm .= $utilityObject->fraudModuleFields('novalnetinvoice',$this->invoice_fraud_prevention,$this);
				}
				$invoiceForm .= '<button class="btn btn-block btn-success" style="margin-top: 8px;" type="submit" name="invoice_fraud_submit">'.JText::_( 'PLG_NOVALNETINVOICE_BTN_PAY' ).'</button></form>';
				 echo $invoiceForm;
			}
			if(isset($_REQUEST['invoice_fraud_submit'])){
				if (in_array($this->invoice_fraud_prevention, array('CALLBACK','SMS'))) {
					$session->set('novalnetinvoice_forgot_pin', $_POST['novalnetinvoice_forgot_pin']);
					$session->set('novalnetinvoice_transcation_pin', $_POST['novalnetinvoice_transcation_pin']);
				}
				if($this->validateFields()){
					if(!empty($session->get('novalnetinvoice_tid'))){
						if (in_array($this->invoice_fraud_prevention, array('CALLBACK','SMS'))) {
							$request_type = (isset($_POST['novalnetinvoice_forgot_pin'])) ? 'TRANSMIT_PIN_AGAIN' : 'PIN_STATUS';
						}
						$xml_data = $utilityObject->xmlCallProcess($request_type, $this->id, $_POST['novalnetinvoice_transcation_pin']);
						 if ($xml_data['status'] == 100) {
							header( "Location: ". $this->return_url);
						 }else{
							if ($xml_data['status'] == '0529006') {
								$session->set('novalnetinvoice_paymnet_hide',true);
								$session->clear('novalnetinvoice_tid');
							}
							$error_message = isset($xml_data['status_message']) ? $xml_data['status_message'] :(isset($xml_data['status_desc']) ? $xml_data['status_desc'] : $xml_data['status_text']);
							JError::raiseWarning(500, $error_message);
						 }
					}
				}
			}
            if(isset($_REQUEST['enter_invoice']) && empty($session->get('novalnetinvoice_tid'))) {
                if($this->validateFields()) {
					if(empty($this->novalnet_response['tid'])){
						$telephone = isset($_POST['novalnetinvoice_telephone']) ? $_POST['novalnetinvoice_telephone'] : '';
						$mobile = isset($_POST['novalnetinvoice_mobile']) ? $_POST['novalnetinvoice_mobile'] : '';
						 $session->set('novalnetinvoice_telephone', $telephone);
						 $session->set('novalnetinvoice', $mobile);
					}
					if($this->invoice_fraud_prevention_amount_limit > $this->amount && $this->invoice_fraud_prevention != 'NONE' && (in_array($this->country,array('DE','AT','CH')))){
						$fraud_params = $utilityObject->getFraudPreventionParams('novalnetinvoice',$this->invoice_fraud_prevention);
						$this->novalnet_request = array_merge($this->novalnet_request,$fraud_params);
					}
                    $response = $utilityObject->performHttpsRequest($this->paygate_url, http_build_query($this->novalnet_request));
                    parse_str($response, $this->novalnet_response);
                    $session->set("novalnet_response_novalnetinvoice", $this->novalnet_response);
                    $session->set("novalnetinvoice_tid",$this->novalnet_response['tid']);
					if($this->invoice_fraud_prevention != 'NONE' && !empty($session->get('novalnetinvoice_tid')) && $this->novalnet_response['status'] == '100'){
						$session->set('novalnet_response_amount',$this->novalnet_response['amount']);
						$session->set('novalnet_response_testmode',$this->novalnet_response['test_mode']);
						$session->set('novalnetinvoice_hidelimit',time() + (30 * 60));
						$error_message = ($this->invoice_fraud_prevention == 'CALLBACK') ? JText::_('PLG_NOVALNETINVOICE_FRAUD_CALLBACK_MESSAGE') : JText::_('PLG_NOVALNETINVOICE_FRAUD_SMS_MESSAGE');
						JError::raiseWarning(500, $error_message);
						header( "Location: ". $utilityObject->redirectUrl());

					}else{
						if($this->novalnet_response['status'] == 100){
							header( "Location: ". $this->return_url);
						} else {
							header( "Location: ". $this->cancel_url);
						}
					}
                }
            return true;
            }
        }
     }

    /**
     * Validate field values
     * @param none
     * @return boolean
     */
    function validateFields() {
		$utilityObject = new novalnetUtilities;
		$session = JFactory::getSession();
		if(empty($session->get('novalnetinvoice_tid'))){
			if(self::checkGuaranteeEnable()) {
				$date_regex = '/^(19|20)\d\d[\-\/.](0[1-9]|1[012])[\-\/.](0[1-9]|[12][0-9]|3[01])$/';
				if(empty($_POST['nninvoice_birth_day']) || !preg_match($date_regex, $_POST['nninvoice_birth_day'])) {
					JError::raiseWarning(500, JText::_('PLG_NOVALNETINVOICER_BIRTHDAY_ERR'));
					return false;
				}
				else {
					$this->novalnet_request['key']  = 41;
					$this->novalnet_request['payment_type'] = 'GUARANTEED_INVOICE_STAR';
					$this->novalnet_request['birth_day']    = $_POST['nninvoice_birth_day'];
				}
			}else if($this->invoice_fraud_prevention != 'NONE' && !$utilityObject->isDigits($_POST['novalnetinvoice_telephone']) || $utilityObject->isDigits($_POST['novalnetinvoice_mobile'])){
					$utilityObject->validateUserInputs($this->id,$this->invoice_fraud_prevention);
					return false;
			}
			return true;
		}else if(!empty($session->get('novalnetinvoice_tid'))){
			if($this->invoice_fraud_prevention != 'NONE'){
				$utilityObject->validateFraudModuleInputfields($this->id);
			}
			return true;
		}
    }

    /**
     * Display date of birth field
     * @param none
     * @return template
     */
    function showBirthDate() {

        if(self::checkGuaranteeEnable()) {
            return '<tr>
                    <td>'.JText::_('PLG_NOVALNETINVOICE_BIRTH_DAY') . '</td>
                    <td>
                        <input type="text" id="nninvoice_birth_day" placeholder="YYYY-MM-DD" name="nninvoice_birth_day" value="" autocomplete="off" />
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
        if(!empty($this->guarantee_inv)) {
            if((!empty($this->guarantee_inv_force) && ($this->amount > 2000 && $this->amount < 500000)) || empty($this->guarantee_inv_force)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Unsuccessful order
     * @param none
     * @return none
     */
    function novalnetinvoice_failed() {
		$utilityObject = new novalnetUtilities;
		$this->novalnet_response = $_REQUEST;
        $utilityObject->prepareTransactionComments('novalnetinvoice',$this->novalnet_response,$this);
        $this->clearSession();
    }

    /**
     * Success the payment
     * @param none
     * @return none
     */
    function novalnetinvoice() {
		$utilityObject = new novalnetUtilities;
        $utilityObject->novalnetTransactionSuccess('novalnetinvoice',$this);
        $this->clearSession();
    }

    /**
     * Clear session values
     * @param none
     * @return none
     */
    function clearSession() {

        $session = JFactory::getSession();
        $session->clear('novalnet_response_novalnetinvoice');
        $session->clear('novalnet_request_invoice');
        $session->clear('ordercode');
        $session->clear('coupon');
		$session->clear('novalnet_response_amount');
		$session->clear('novalnet_response_testmode');
		$session->clear('novalnetinvoice_tid');
		$session->clear('novalnetinvoice_hidelimit');
		$session->clear('novalnetinvoice_paymnet_hide');
        if(isset($_SESSION['nn']))
            unset($_SESSION['nn']);
    }
}
