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
  * Script : novalnetgiropay.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');
include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php');

class plgRDmediaNovalnetGiropay extends JPlugin {
	public $id = 'novalnetgiropay';
    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for
     * plugins because func_get_args ( void ) returns a copy of all passed arguments
     * NOT references.  This causes problems with cross-referencing necessary for the
     * observer design pattern.
     */
    function plgRDmediaNovalnetGiropay( &$subject, $params  ) {

        parent::__construct( $subject , $params  );

        ## Loading language:
        $lang = JFactory::getLanguage();
        $utilityObject = new novalnetUtilities;
        $lang->load('plg_rdmedia_novalnetgiropay', JPATH_ADMINISTRATOR);
		$utilityObject->getCommonvalues($this);
        $this->test_mode = $this->params->def('test_mode');
        $this->giropay_buyer_notification = trim($this->params->def('giropay_buyer_notification'));
        $this->layout = $this->params->def('layout');
        $this->success_tpl = $this->params->def('success_tpl');
        $this->failure_tpl = $this->params->def('failure_tpl');
        $this->currency = $this->params->def('currency');
        $this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
        $this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
        $this->key = '69';
        $this->novalnet_response = array();
        $this->novalnet_request = array();
        $this->payment_type = 'novalnet_giropay';
        $this->payport_url='https://payport.novalnet.de/giropay';
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
        ## Loading the CSS file for giropay plugin.
        $document = JFactory::getDocument();
        $document->addStyleSheet( JURI::root(true).'/plugins/rdmedia/novalnetgiropay/rdmedia_novalnetgiropay/css/novalnetgiropay.css' );

		## Check if this is Joomla 2.5 or 3.0.+
		$isJ30 = version_compare(JVERSION, '3.0.0', 'ge');

		## This will only be used if you use Joomla 2.5 with bootstrap enabled.
		## Please do not change!
		if(!$isJ30){
			if($config->load_bootstrap == 1){
				$isJ30 = true;
			}
		}

		if($utilityObject->doValidation($this)) {
            if(empty($this->novalnet_response['tid']) && $this->payment_type == 'novalnet_giropay') {
				$this->novalnet_request = $utilityObject->formPaymentParameters('novalnetgiropay',$this);
				$novalnetConfigurations = $utilityObject->configrationDetails();
                if($this->layout == 1 && $isJ30 == true ) {
					$giropayForm = '';
					if($novalnetConfigurations->payment_logo == '1'){
						$giropayForm .= '<img src="plugins/rdmedia/novalnetgiropay/rdmedia_novalnetgiropay/images/novalnet_giropay.png" />';
					}
                    if($this->test_mode == '1') {
                        $giropayForm .="<div style='color:red;'>".JText::_('PLG_NOVALNETGIROPAY_FRONTEND_TESTMODE_DESC')."</div>";
                    }
                    $giropayForm .= '<form action="" method="post" name="novalnetForm">';
                    foreach($this->novalnet_request as $key => $value) {
                        $giropayForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $giropayForm .= '<button class="btn btn-block btn-success" alt="'.JText::_('PLG_NOVALNETGIROPAY_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETGIROPAY_PAYMENT_LOGO_TITLE').'" style="margin-top: 8px;" type="submit" id="enter_giropay" name="enter_giropay">'.JText::_( 'PLG_NOVALNETGIROPAY_BTN_PAY' ).'</button>';
                    $giropayForm .=    '</form>';
                } else {
                    $giropayForm = '<form action="" method="post" name="novalnetForm">';
                    $giropayForm .= '<div id="plg_rdmedia_novalnetgiropay">';
                    $giropayForm .= '<div id="plg_rdmedia_novalnetgiropay_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETGIROPAY_PAYMENT_NAME_WT_NOVALNET');
                    $giropayForm .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETGIROPAY_PAYMENT_DESC')."</span>";
                    $giropayForm .= "<br /><span>".JText::_('PLG_NOVALNETGIROPAY_REDIRECT_DESC')."</span>";
				    $giropayForm .= "<br /><span>".$this->giropay_buyer_notification."</span>";
                    if($this->test_mode == '1'){
                        $giropayForm .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETGIROPAY_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $giropayForm .= '</div>';
                    foreach($this->novalnet_request as $key => $value) {
                        $giropayForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $giropayForm .= '<div id="plg_rdmedia_novalnetgiropay_confirmbutton">';
                    $giropayForm .= '<input type="submit" id="enter_giropay" name="enter_giropay" value="" class="novalnetgiropay_button" alt="'.JText::_('PLG_NOVALNETGIROPAY_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETGIROPAY_PAYMENT_LOGO_TITLE').'" style="width: 116px;">';
                    $giropayForm .= '</div>';
                    $giropayForm .= '</div>';
                    $giropayForm .= '</form>';
                }
                echo $giropayForm;
                if(isset($_REQUEST['enter_giropay']) && empty($this->novalnet_response['tid'])) {
                    $giropayForm = '<label for="redirection_msg">'.JText::_('PLG_NOVALNETGIROPAY_REDIRECT_MESSAGE').'</label>';
                    $giropayForm .= '<form action="'.$this->payport_url.'" method="post" name="novalnetGiropayForm">';
                    foreach($this->novalnet_request as $key => $value) {
                        $giropayForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $giropayForm .= '<input type="submit" id="giropay_form_payment" name="giropay_form_payment" value="'.JText::_('PLG_NOVALNETGIROPAY_REDIRECT_VALUE').'" onclick="document.forms.novalnetGiropayForm.submit();document.getElementById(\'giropay_form_payment\').disabled=\'true\';"/></form>';
                    $giropayForm .= '<script>document.forms.novalnetGiropayForm.submit();</script>';
                    echo $giropayForm;
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
    function novalnetgiropay_failed() {
		$utilityObject = new novalnetUtilities;
        $this->novalnet_response = $_REQUEST;
		$utilityObject->prepareTransactionComments('novalnetgiropay',$this->novalnet_response,$this);
        $this->clearSession();
        $utilityObject->redirectUrl();
    }


    /**
     * Success the payment
     * @param none
     * @return none
     */
    function novalnetgiropay() {
		$session = JFactory::getSession();
        $db = JFactory::getDBO();
		$utilityObject = new novalnetUtilities;
		$this->novalnet_response = $_REQUEST;
		$session->set("novalnet_response_novalnetgiropay", $this->novalnet_response);
        $utilityObject->novalnetTransactionSuccess('novalnetgiropay',$this);
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
        $session->clear('coupon');
        $session->clear('novalnet_response_novalnetgiropay');
        if(isset($_SESSION['nn']))
            unset($_SESSION['nn']);
    }
}
