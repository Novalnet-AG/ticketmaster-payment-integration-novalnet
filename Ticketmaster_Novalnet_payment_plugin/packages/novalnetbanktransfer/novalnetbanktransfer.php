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
  * Script : novalnetbanktransfer.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');
include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php' );

class plgRDmediaNovalnetBanktransfer extends JPlugin {
	public $id = 'novalnetbanktransfer';
	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for
	 * plugins because func_get_args ( void ) returns a copy of all passed arguments
	 * NOT references.  This causes problems with cross-referencing necessary for the
	 * observer design pattern.
	 */
	function plgRDmediaNovalnetBanktransfer( &$subject, $params  ) {
		parent::__construct( $subject , $params);
		## Loading language:
		$lang = JFactory::getLanguage();
		$utilityObject = new novalnetUtilities;
		$lang->load('plg_rdmedia_novalnetbanktransfer', JPATH_ADMINISTRATOR);
		$utilityObject->getCommonvalues($this);
		$this->test_mode = $this->params->def('test_mode');
		$this->layout = $this->params->def('layout');
		$this->success_tpl = $this->params->def('success_tpl');
		$this->failure_tpl = $this->params->def('failure_tpl');
		$this->currency = $this->params->def('currency');
		$this->instant_bank_buyer_notification = trim($this->params->def('instant_bank_buyer_notification'));
		$this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
		$this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
		$this->key = '33';
		$this->novalnet_response = array();
		$this->novalnet_request = array();
		$this->payment_type = 'novalnet_banktransfer';
		$this->nnSiteUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']?'https://':'http://';
		$this->payport_url='https://payport.novalnet.de/online_transfer_payport';
	}

	/**
	 * Plugin method with the same name as the event will be called automatically.
	 * You have to get at least a function called display, and the name of the processor
	 * Now you should be able to display and process transactions.
	 *
	 */
	function display() {
		$utilityObject = new novalnetUtilities;
		$_SESSION['request_uri'] = $_SERVER['REQUEST_URI'];
		$this->novalnet_request = $utilityObject->formPaymentParameters('novalnetbanktransfer', $this);
		$isJ30 = version_compare(JVERSION, '3.0.0', 'ge');

		## This will only be used if you use Joomla 2.5 with bootstrap enabled.
		## Please do not change!
		if(!$isJ30) {
			if($config->load_bootstrap == 1) {
				$isJ30 = true;
			}
		}
		if($utilityObject->doValidation($this)) {
			$novalnetConfigurations = $utilityObject->configrationDetails();
			if(empty($this->novalnet_response['tid']) && $this->payment_type == 'novalnet_banktransfer'){
				$banktransferForm = '';
				if($this->layout == 1 && $isJ30 == true ) {
					if($novalnetConfigurations->payment_logo == '1'){
						$banktransferForm = '<img src="plugins/rdmedia/novalnetbanktransfer/rdmedia_novalnetbanktransfer/images/novalnet_banktransfer.png" />';
					}
					if($this->test_mode == '1') {
						$banktransferForm .= "<div style='color:red;'>".JText::_('PLG_NOVALNETBANKTRANSFER_FRONTEND_TESTMODE_DESC')."</div>";
					}
					$banktransferForm .= '<form action="" method="post" name="novalnetForm">';
					foreach($this->novalnet_request as $k => $v) {
						$banktransferForm .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />' . "\n";
					}
					$banktransferForm .= '<button class="btn btn-block btn-success" style="margin-top: 8px;" type="submit" id="enter_bank" name="enter_bank">'.JText::_( 'PLG_NOVALNETBANKTRANSFER_BTN_PAY' ).'</button>';
					$banktransferForm .= '</form>';
				}else {

					$banktransferForm = '<form action="" method="post" name="novalnetForm">';
					$banktransferForm .= '<div id="plg_rdmedia_novalnetbanktransfer">';
					$banktransferForm .= '<div id="plg_rdmedia_novalnetbanktransfer_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETBANKTRANSFER_PAYMENT_NAME_WT_NOVALNET');
					$banktransferForm .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETBANKTRANSFER_PAYMENT_DESC')."</span>";
					$banktransferForm .= "<br /><span>".JText::_('PLG_NOVALNETBANKTRANSFER_REDIRECT_DESC')."</span>";
					$banktransferForm .= "<br/><span>".$this->instant_bank_buyer_notification."</span>";
					if($this->test_mode == '1'){
						$banktransferForm .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETBANKTRANSFER_FRONTEND_TESTMODE_DESC')."</span>";
					}
					$banktransferForm .= '</div>';
					foreach($this->novalnet_request as $key => $value) {
						$banktransferForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
					}
					$banktransferForm .= '<div id="plg_rdmedia_novalnetbanktransfer_confirmbutton">';
					$banktransferForm .= '<input type="submit" id="enter_bank" name="enter_bank" value="" class="novalnetbanktransfer_button" alt="'.JText::_('PLG_NOVALNETBANKTRANSFER_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETBANKTRANSFER_PAYMENT_LOGO_TITLE').'" style="width: 116px;">';
					$banktransferForm .= '</div></div></form>';
				}
				echo $banktransferForm;
				if(isset($_REQUEST['enter_bank']) && empty($this->novalnet_response['tid'])) {
					$form = '<label for="redirection_msg">'.JText::_('PLG_NOVALNETBANKTRANSFER_REDIRECT_MESSAGE').'</label>';
					$form .= '<form action="'.$this->payport_url.'" method="post" name="novalnetBankForm">';
					foreach($this->novalnet_request as $key => $value) {
						$form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
					}
					$form .= '<input type="submit" id="bank_form_payment" name="bank_form_payment" value="'.JText::_('PLG_NOVALNETBANKTRANSFER_REDIRECT_VALUE').'" onClick="document.forms.novalnetBankForm.submit();document.getElementById(\'bank_form_payment\').disabled=\'true\';"/></form>';
					$form .= '<script>document.forms.novalnetBankForm.submit();</script>';
					echo $form;
				}
			}
		 return true;
		}
	}

	/**
     * Unsuccessful order
     * @param none
     * @return none
     */
    function novalnetbanktransfer_failed() {
		$utilityObject = new novalnetUtilities;
        $this->novalnet_response = $_REQUEST;
		$utilityObject->prepareTransactionComments('novalnetbanktransfer',$this->novalnet_response,$this);
        $this->clearSession();
        $utilityObject->redirectUrl();
	}

	/**
     * Success the payment
     * @param none
     * @return none
     */
    function novalnetbanktransfer() {
		$session = JFactory::getSession();
        $db = JFactory::getDBO();
		$utilityObject = new novalnetUtilities;
		$this->novalnet_response = $_REQUEST;
		$session->set("novalnet_response_novalnetbanktransfer", $this->novalnet_response);
        $utilityObject->novalnetTransactionSuccess('novalnetbanktransfer',$this);
		$this->clearSession();
	}

	/**
     * Clear session values
     * @param none
     * @return none
     */
    function clearSession(){
        $session = JFactory::getSession();
        $session->clear('ordercode');
        $session->clear('novalnet_request');
        $session->clear('novalnet_response_novalnetbanktransfer');
        $session->clear($this->ordercode);
        $session->clear('coupon');
        if(isset($_SESSION['nn']))
            unset($_SESSION['nn']);

	}
}
?>
